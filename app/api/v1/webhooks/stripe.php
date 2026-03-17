<?php
/**
 * Stripe Webhook Handler
 * contextkeeper.org
 * 
 * Endpoint: POST /app/api/v1/webhooks/stripe.php
 * 
 * Handles:
 *   - checkout.session.completed (activate subscription)
 *   - invoice.payment_succeeded (confirm payment, extend access)
 *   - invoice.payment_failed (flag account, log event)
 *   - customer.subscription.updated (plan changes, cancellation scheduled)
 *   - customer.subscription.deleted (downgrade to free)
 */

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../lib/Database.php';
require_once __DIR__ . '/../../lib/StripeHelper.php';

// Stripe sends raw JSON, not form-encoded
$payload = file_get_contents('php://input');
$sigHeader = $_SERVER['HTTP_STRIPE_SIGNATURE'] ?? '';

// Verify webhook signature
try {
    $event = StripeHelper::constructWebhookEvent($payload, $sigHeader);
} catch (\Stripe\Exception\SignatureVerificationException $e) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid signature']);
    exit;
} catch (\Exception $e) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid payload']);
    exit;
}

$db = Database::getInstance();

// Idempotency: check if we already processed this event
$stmt = $db->prepare("SELECT id FROM webhook_events WHERE stripe_event_id = ? LIMIT 1");
$stmt->execute([$event->id]);
if ($stmt->fetch()) {
    // Already processed, return 200 so Stripe stops retrying
    http_response_code(200);
    echo json_encode(['status' => 'already_processed']);
    exit;
}

// Log the event before processing (for audit trail)
$db->prepare(
    "INSERT INTO webhook_events (stripe_event_id, event_type, payload, processed) VALUES (?, ?, ?, 0)"
)->execute([$event->id, $event->type, $payload]);

$eventLogId = (int)$db->lastInsertId();

try {
    switch ($event->type) {

        case 'checkout.session.completed':
            handleCheckoutComplete($event->data->object, $db);
            break;

        case 'invoice.payment_succeeded':
            handlePaymentSucceeded($event->data->object, $db);
            break;

        case 'invoice.payment_failed':
            handlePaymentFailed($event->data->object, $db);
            break;

        case 'customer.subscription.updated':
            handleSubscriptionUpdated($event->data->object, $db);
            break;

        case 'customer.subscription.deleted':
            handleSubscriptionDeleted($event->data->object, $db);
            break;

        default:
            // Unhandled event type - acknowledge receipt
            break;
    }

    // Mark as processed
    $db->prepare("UPDATE webhook_events SET processed = 1, processed_at = NOW() WHERE id = ?")
       ->execute([$eventLogId]);

} catch (\Exception $e) {
    // Log error but still return 200 to prevent Stripe retry loops
    $db->prepare("UPDATE webhook_events SET processed = 2, error_message = ? WHERE id = ?")
       ->execute([$e->getMessage(), $eventLogId]);
}

http_response_code(200);
echo json_encode(['status' => 'ok']);
exit;


// ---- Event Handlers ----

function handleCheckoutComplete(\Stripe\Checkout\Session $session, PDO $db): void
{
    $userId = (int)($session->metadata->user_id ?? 0);
    $plan = $session->metadata->plan ?? '';
    $customerId = $session->customer ?? '';
    $subscriptionId = $session->subscription ?? '';

    if ($userId <= 0 || empty($plan) || empty($subscriptionId)) {
        return;
    }

    // Fetch the subscription to get period end
    $stripe = new StripeHelper();
    $subscription = $stripe->getSubscription($subscriptionId);
    $periodEnd = $subscription ? date('Y-m-d H:i:s', $subscription->current_period_end) : null;

    // Update user record
    $db->prepare(
        "UPDATE users SET 
            plan = ?, 
            stripe_customer_id = ?, 
            stripe_subscription_id = ?, 
            subscription_status = 'active',
            current_period_end = ?
         WHERE id = ?"
    )->execute([$plan, $customerId, $subscriptionId, $periodEnd, $userId]);
}

function handlePaymentSucceeded(\Stripe\Invoice $invoice, PDO $db): void
{
    $customerId = $invoice->customer ?? '';
    if (empty($customerId)) return;

    // Find user by Stripe customer ID
    $stmt = $db->prepare("SELECT id FROM users WHERE stripe_customer_id = ? LIMIT 1");
    $stmt->execute([$customerId]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$user) return;

    // Update subscription status and period end
    $subscriptionId = $invoice->subscription ?? '';
    if (!empty($subscriptionId)) {
        $stripe = new StripeHelper();
        $subscription = $stripe->getSubscription($subscriptionId);
        if ($subscription) {
            $periodEnd = date('Y-m-d H:i:s', $subscription->current_period_end);
            $db->prepare(
                "UPDATE users SET subscription_status = 'active', current_period_end = ? WHERE id = ?"
            )->execute([$periodEnd, $user['id']]);
        }
    }
}

function handlePaymentFailed(\Stripe\Invoice $invoice, PDO $db): void
{
    $customerId = $invoice->customer ?? '';
    if (empty($customerId)) return;

    $stmt = $db->prepare("SELECT id FROM users WHERE stripe_customer_id = ? LIMIT 1");
    $stmt->execute([$customerId]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$user) return;

    // Set status to past_due (Stripe handles retry logic)
    $db->prepare(
        "UPDATE users SET subscription_status = 'past_due' WHERE id = ?"
    )->execute([$user['id']]);
}

function handleSubscriptionUpdated(\Stripe\Subscription $subscription, PDO $db): void
{
    $customerId = $subscription->customer ?? '';
    if (empty($customerId)) return;

    $stmt = $db->prepare("SELECT id FROM users WHERE stripe_customer_id = ? LIMIT 1");
    $stmt->execute([$customerId]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$user) return;

    $stripe = new StripeHelper();
    $plan = $stripe->mapSubscriptionToPlan($subscription);
    $status = $subscription->status; // active, past_due, canceled, etc.
    $periodEnd = date('Y-m-d H:i:s', $subscription->current_period_end);
    $cancelAtPeriodEnd = $subscription->cancel_at_period_end;

    $effectiveStatus = $cancelAtPeriodEnd ? 'canceling' : $status;

    $db->prepare(
        "UPDATE users SET plan = ?, stripe_subscription_id = ?, subscription_status = ?, current_period_end = ? WHERE id = ?"
    )->execute([$plan, $subscription->id, $effectiveStatus, $periodEnd, $user['id']]);
}

function handleSubscriptionDeleted(\Stripe\Subscription $subscription, PDO $db): void
{
    $customerId = $subscription->customer ?? '';
    if (empty($customerId)) return;

    $stmt = $db->prepare("SELECT id FROM users WHERE stripe_customer_id = ? LIMIT 1");
    $stmt->execute([$customerId]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$user) return;

    // Downgrade to free
    $db->prepare(
        "UPDATE users SET plan = 'free', stripe_subscription_id = NULL, subscription_status = 'canceled', current_period_end = NULL WHERE id = ?"
    )->execute([$user['id']]);
}

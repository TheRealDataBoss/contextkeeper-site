<?php
/**
 * Stripe Integration Library
 * contextkeeper.org
 * 
 * Handles: checkout session creation, customer management,
 * subscription status, cancellation, and Customer Portal.
 * 
 * Usage:
 *   require_once 'lib/StripeHelper.php';
 *   $stripe = new StripeHelper();
 *   $session = $stripe->createCheckoutSession($user, 'pro');
 *   header('Location: ' . $session->url);
 */

require_once __DIR__ . '/../vendor/autoload.php';

class StripeHelper
{
    private \Stripe\StripeClient $client;

    // Map internal plan names to Stripe price IDs
    private array $priceMap;

    public function __construct()
    {
        \Stripe\Stripe::setApiKey(STRIPE_SECRET_KEY);
        $this->client = new \Stripe\StripeClient(STRIPE_SECRET_KEY);

        $this->priceMap = [
            'pro'  => STRIPE_PRICE_PRO,
            'team' => STRIPE_PRICE_TEAM,
        ];
    }

    /**
     * Get or create a Stripe customer for a user.
     * Stores the customer ID back in the database.
     */
    public function getOrCreateCustomer(array $user): string
    {
        // If user already has a Stripe customer ID, verify it exists
        if (!empty($user['stripe_customer_id'])) {
            try {
                $this->client->customers->retrieve($user['stripe_customer_id']);
                return $user['stripe_customer_id'];
            } catch (\Stripe\Exception\InvalidRequestException $e) {
                // Customer was deleted in Stripe, create a new one
            }
        }

        // Create new customer
        $customer = $this->client->customers->create([
            'email' => $user['email'],
            'name' => $user['name'] ?? '',
            'metadata' => [
                'user_id' => $user['id'],
                'source' => 'contextkeeper'
            ]
        ]);

        // Store customer ID in database
        $db = Database::getInstance();
        $db->prepare("UPDATE users SET stripe_customer_id = ? WHERE id = ?")
           ->execute([$customer->id, $user['id']]);

        return $customer->id;
    }

    /**
     * Create a Stripe Checkout Session for subscription.
     * Returns the Session object (use ->url to redirect).
     */
    public function createCheckoutSession(array $user, string $plan): \Stripe\Checkout\Session
    {
        if (!isset($this->priceMap[$plan])) {
            throw new \InvalidArgumentException("Unknown plan: $plan");
        }

        $customerId = $this->getOrCreateCustomer($user);

        $session = $this->client->checkout->sessions->create([
            'customer' => $customerId,
            'mode' => 'subscription',
            'line_items' => [[
                'price' => $this->priceMap[$plan],
                'quantity' => 1,
            ]],
            'success_url' => APP_URL . '/app/dashboard/billing.php?session_id={CHECKOUT_SESSION_ID}&success=1',
            'cancel_url' => APP_URL . '/app/dashboard/billing.php?canceled=1',
            'metadata' => [
                'user_id' => (string)$user['id'],
                'plan' => $plan,
            ],
            'subscription_data' => [
                'metadata' => [
                    'user_id' => (string)$user['id'],
                    'plan' => $plan,
                ],
            ],
            'allow_promotion_codes' => true,
        ]);

        return $session;
    }

    /**
     * Create a Stripe Customer Portal session.
     * Users can manage billing, update payment, cancel subscription.
     */
    public function createPortalSession(string $customerId): \Stripe\BillingPortal\Session
    {
        return $this->client->billingPortal->sessions->create([
            'customer' => $customerId,
            'return_url' => APP_URL . '/app/dashboard/billing.php',
        ]);
    }

    /**
     * Retrieve a subscription by ID.
     */
    public function getSubscription(string $subscriptionId): ?\Stripe\Subscription
    {
        try {
            return $this->client->subscriptions->retrieve($subscriptionId);
        } catch (\Stripe\Exception\InvalidRequestException $e) {
            return null;
        }
    }

    /**
     * Cancel a subscription (at period end by default).
     */
    public function cancelSubscription(string $subscriptionId, bool $immediately = false): ?\Stripe\Subscription
    {
        try {
            if ($immediately) {
                return $this->client->subscriptions->cancel($subscriptionId);
            } else {
                return $this->client->subscriptions->update($subscriptionId, [
                    'cancel_at_period_end' => true,
                ]);
            }
        } catch (\Stripe\Exception\InvalidRequestException $e) {
            return null;
        }
    }

    /**
     * Get invoices for a customer.
     */
    public function getInvoices(string $customerId, int $limit = 10): array
    {
        try {
            $invoices = $this->client->invoices->all([
                'customer' => $customerId,
                'limit' => $limit,
            ]);
            return $invoices->data;
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * Verify and parse a webhook event.
     * Throws on invalid signature.
     */
    public static function constructWebhookEvent(string $payload, string $sigHeader): \Stripe\Event
    {
        return \Stripe\Webhook::constructEvent(
            $payload,
            $sigHeader,
            STRIPE_WEBHOOK_SECRET
        );
    }

    /**
     * Map a Stripe subscription to an internal plan name.
     */
    public function mapSubscriptionToPlan(\Stripe\Subscription $subscription): string
    {
        $priceId = $subscription->items->data[0]->price->id ?? '';

        $reverseMap = array_flip($this->priceMap);
        return $reverseMap[$priceId] ?? 'free';
    }

    /**
     * Update user's subscription state in the database.
     * Called by webhook handler after processing events.
     */
    public static function syncUserSubscription(
        int $userId,
        string $plan,
        string $subscriptionId,
        string $status,
        ?string $currentPeriodEnd = null
    ): void {
        $db = Database::getInstance();
        $db->prepare(
            "UPDATE users SET plan = ?, stripe_subscription_id = ?, subscription_status = ?, current_period_end = ? WHERE id = ?"
        )->execute([
            $plan,
            $subscriptionId,
            $status,
            $currentPeriodEnd,
            $userId
        ]);
    }
}

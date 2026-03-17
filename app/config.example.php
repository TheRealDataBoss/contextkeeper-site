<?php
/**
 * contextkeeper Configuration
 * Production - contextkeeper.org
 *
 * INSTRUCTIONS:
 * 1. Copy this file to config.php
 * 2. Fill in your actual credentials
 * 3. config.php is gitignored and will NOT be committed
 */

// ---- Database ----
define('DB_HOST', 'localhost');
define('DB_NAME', 'your_db_name');
define('DB_USER', 'your_db_user');
define('DB_PASS', 'your_db_password');

// ---- Application ----
define('APP_URL', 'https://contextkeeper.org');
define('APP_PATH', '/app');
define('APP_SECRET', 'generate-a-64-char-hex-string-here');

// ---- Stripe ----
define('STRIPE_SECRET_KEY', 'sk_test_...');
define('STRIPE_PUBLISHABLE_KEY', 'pk_test_...');
define('STRIPE_WEBHOOK_SECRET', 'whsec_...');
define('STRIPE_PRICE_PRO', 'price_...');
define('STRIPE_PRICE_TEAM', 'price_...');

// ---- Google OAuth ----
define('GOOGLE_CLIENT_ID', '');
define('GOOGLE_CLIENT_SECRET', '');
define('GOOGLE_REDIRECT_URI', APP_URL . APP_PATH . '/auth/google-oauth.php');

// ---- Email (Resend) ----
define('RESEND_API_KEY', '');
define('FROM_EMAIL', 'noreply@contextkeeper.org');

// ---- Session Config ----
ini_set('session.cookie_httponly', '1');
ini_set('session.cookie_secure', '1');
ini_set('session.cookie_samesite', 'Lax');
ini_set('session.use_strict_mode', '1');
ini_set('session.gc_maxlifetime', '86400'); // 24 hours

// ---- Timezone ----
date_default_timezone_set('UTC');

<?php
/**
 * Manual autoloader for Stripe PHP SDK
 * contextkeeper.org
 * 
 * This replaces Composer's autoloader for cPanel shared hosting
 * where Composer may not be available.
 */

spl_autoload_register(function ($class) {
    // Only handle Stripe classes
    if (strpos($class, 'Stripe\\') !== 0) {
        return;
    }

    // Convert namespace to file path
    $file = __DIR__ . '/stripe/stripe-php/lib/' . str_replace('\\', '/', substr($class, 7)) . '.php';

    if (file_exists($file)) {
        require_once $file;
    }
});

// Load Stripe's own init file which sets up its internal autoloading
require_once __DIR__ . '/stripe/stripe-php/init.php';

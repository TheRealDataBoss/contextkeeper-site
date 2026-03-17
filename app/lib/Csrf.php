<?php
/**
 * CSRF Protection Library
 * contextkeeper.org
 * 
 * Generates and validates session-backed CSRF tokens for all HTML forms.
 * Usage:
 *   require_once 'lib/Csrf.php';
 *   Csrf::init();                          // call after session_start()
 *   echo Csrf::field();                    // in <form>, outputs hidden input
 *   Csrf::validate($_POST['csrf_token']);  // on POST, throws on failure
 */

class Csrf
{
    private static string $sessionKey = '_csrf_token';
    private static string $fieldName = 'csrf_token';

    /**
     * Initialize CSRF token in session if not present.
     * Call after session_start().
     */
    public static function init(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            return;
        }
        if (empty($_SESSION[self::$sessionKey])) {
            $_SESSION[self::$sessionKey] = bin2hex(random_bytes(32));
        }
    }

    /**
     * Get the current CSRF token.
     */
    public static function token(): string
    {
        return $_SESSION[self::$sessionKey] ?? '';
    }

    /**
     * Output a hidden form field with the CSRF token.
     */
    public static function field(): string
    {
        $token = htmlspecialchars(self::token(), ENT_QUOTES, 'UTF-8');
        return '<input type="hidden" name="' . self::$fieldName . '" value="' . $token . '">';
    }

    /**
     * Validate a submitted CSRF token against the session token.
     * Returns true if valid, false if invalid.
     */
    public static function validate(?string $submitted): bool
    {
        if (empty($submitted) || empty($_SESSION[self::$sessionKey])) {
            return false;
        }
        return hash_equals($_SESSION[self::$sessionKey], $submitted);
    }

    /**
     * Validate and halt with error if invalid.
     * Use this in POST handlers for a quick guard.
     */
    public static function guard(): void
    {
        $submitted = $_POST[self::$fieldName] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? null;
        if (!self::validate($submitted)) {
            http_response_code(403);
            // For pages that render HTML, set an error flag
            // For API-style responses, just die
            die('Invalid or missing security token. Please reload the page and try again.');
        }
    }

    /**
     * Regenerate the CSRF token (call after sensitive actions if desired).
     */
    public static function regenerate(): void
    {
        $_SESSION[self::$sessionKey] = bin2hex(random_bytes(32));
    }
}

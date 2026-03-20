<?php
/**
 * contextkeeper UUID Helper
 *
 * Generates RFC 4122 version 4 UUIDs for governance table primary keys.
 * Uses cryptographically secure random bytes via random_bytes().
 */

class UUID {

    /**
     * Generate a version 4 UUID.
     *
     * @return string 36-character UUID (lowercase hex with hyphens)
     */
    public static function v4(): string {
        $bytes = random_bytes(16);

        // Set version to 0100 (UUID v4)
        $bytes[6] = chr(ord($bytes[6]) & 0x0f | 0x40);
        // Set variant to 10xx (RFC 4122)
        $bytes[8] = chr(ord($bytes[8]) & 0x3f | 0x80);

        return sprintf(
            '%s-%s-%s-%s-%s',
            bin2hex(substr($bytes, 0, 4)),
            bin2hex(substr($bytes, 4, 2)),
            bin2hex(substr($bytes, 6, 2)),
            bin2hex(substr($bytes, 8, 2)),
            bin2hex(substr($bytes, 10, 6))
        );
    }
}


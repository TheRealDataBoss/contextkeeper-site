<?php
/**
 * contextkeeper Encryption Library
 * 
 * AES-256-CBC encryption for connector credentials.
 * Key derivation: APP_SECRET + per-user salt.
 * Never stores plaintext, never logs decrypted values.
 */

class Encryption {
    private string $cipher = 'aes-256-cbc';

    /**
     * Derive an encryption key from the app secret and user-specific salt.
     */
    private function deriveKey(int $userId): string {
        if (!defined('APP_SECRET') || empty(APP_SECRET)) {
            throw new Exception('APP_SECRET not configured.');
        }

        $salt = 'ck_user_' . $userId . '_vault';
        return hash('sha256', APP_SECRET . $salt, true);
    }

    /**
     * Encrypt a plaintext string.
     * Returns base64-encoded IV + ciphertext.
     */
    public function encrypt(string $plaintext, int $userId): string {
        $key = $this->deriveKey($userId);
        $ivLength = openssl_cipher_iv_length($this->cipher);
        $iv = openssl_random_pseudo_bytes($ivLength);

        $encrypted = openssl_encrypt($plaintext, $this->cipher, $key, OPENSSL_RAW_DATA, $iv);

        if ($encrypted === false) {
            throw new Exception('Encryption failed.');
        }

        // Prepend IV to ciphertext, then base64 encode
        return base64_encode($iv . $encrypted);
    }

    /**
     * Decrypt a base64-encoded IV + ciphertext string.
     * Returns the plaintext or null on failure.
     */
    public function decrypt(string $ciphertext, int $userId): ?string {
        $key = $this->deriveKey($userId);
        $data = base64_decode($ciphertext, true);

        if ($data === false) {
            return null;
        }

        $ivLength = openssl_cipher_iv_length($this->cipher);

        if (strlen($data) < $ivLength) {
            return null;
        }

        $iv = substr($data, 0, $ivLength);
        $encrypted = substr($data, $ivLength);

        $decrypted = openssl_decrypt($encrypted, $this->cipher, $key, OPENSSL_RAW_DATA, $iv);

        return $decrypted !== false ? $decrypted : null;
    }
}

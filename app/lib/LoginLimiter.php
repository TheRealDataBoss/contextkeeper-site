<?php
/**
 * Login Rate Limiter
 * contextkeeper.org
 * 
 * Enforces 5 failed login attempts per 15 minutes per IP address.
 * Uses MySQL-backed storage (login_attempts table).
 * 
 * Usage:
 *   $limiter = new LoginLimiter();
 *   if ($limiter->isBlocked($ip)) { show error; return; }
 *   // attempt login...
 *   if (failed) { $limiter->recordFailure($ip); }
 *   if (success) { $limiter->clearAttempts($ip); }
 */

class LoginLimiter
{
    private PDO $db;
    private int $maxAttempts = 5;
    private int $windowSeconds = 900; // 15 minutes

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    /**
     * Check if an IP is currently blocked.
     */
    public function isBlocked(string $ip): bool
    {
        $cutoff = date('Y-m-d H:i:s', time() - $this->windowSeconds);
        $stmt = $this->db->prepare(
            "SELECT COUNT(*) FROM login_attempts 
             WHERE ip_address = ? AND attempted_at > ? AND success = 0"
        );
        $stmt->execute([$ip, $cutoff]);
        return (int)$stmt->fetchColumn() >= $this->maxAttempts;
    }

    /**
     * Record a failed login attempt.
     */
    public function recordFailure(string $ip, ?string $email = null): void
    {
        $stmt = $this->db->prepare(
            "INSERT INTO login_attempts (ip_address, email_attempted, success) VALUES (?, ?, 0)"
        );
        $stmt->execute([$ip, $email]);
    }

    /**
     * Clear attempts for an IP on successful login.
     */
    public function clearAttempts(string $ip): void
    {
        $cutoff = date('Y-m-d H:i:s', time() - $this->windowSeconds);
        $stmt = $this->db->prepare(
            "DELETE FROM login_attempts WHERE ip_address = ? AND attempted_at > ?"
        );
        $stmt->execute([$ip, $cutoff]);
    }

    /**
     * Get remaining attempts for an IP.
     */
    public function remainingAttempts(string $ip): int
    {
        $cutoff = date('Y-m-d H:i:s', time() - $this->windowSeconds);
        $stmt = $this->db->prepare(
            "SELECT COUNT(*) FROM login_attempts 
             WHERE ip_address = ? AND attempted_at > ? AND success = 0"
        );
        $stmt->execute([$ip, $cutoff]);
        $used = (int)$stmt->fetchColumn();
        return max(0, $this->maxAttempts - $used);
    }

    /**
     * Get the client IP address, handling common proxy headers.
     */
    public static function getClientIp(): string
    {
        if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $ips = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
            return trim($ips[0]);
        }
        return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    }

    /**
     * Cleanup old attempts (call periodically or via cron).
     */
    public function cleanup(): int
    {
        $cutoff = date('Y-m-d H:i:s', time() - $this->windowSeconds * 4);
        $stmt = $this->db->prepare("DELETE FROM login_attempts WHERE attempted_at < ?");
        $stmt->execute([$cutoff]);
        return $stmt->rowCount();
    }
}

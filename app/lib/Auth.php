<?php
/**
 * contextkeeper Auth Library
 * 
 * Handles:
 *   - PHP session authentication (dashboard)
 *   - API key authentication (X-API-Key header)
 *   - API key generation
 *   - Plan limit enforcement
 */

class Auth {
    private $db;

    public function __construct() {
        $this->db = Database::getInstance();
    }

    /**
     * Authenticate an API request via X-API-Key header or active session.
     * Returns user row on success, null on failure.
     */
    public function authenticateApiRequest(): ?array {
        // Try API key first
        $apiKey = $_SERVER['HTTP_X_API_KEY'] ?? null;
        if ($apiKey) {
            return $this->authenticateByApiKey($apiKey);
        }

        // Fall back to session
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        if (!empty($_SESSION['user_id'])) {
            return $this->getUserById((int)$_SESSION['user_id']);
        }

        return null;
    }

    /**
     * Authenticate by API key.
     */
    public function authenticateByApiKey(string $apiKey): ?array {
        if (strlen($apiKey) !== 64 || !ctype_xdigit($apiKey)) {
            return null;
        }

        $stmt = $this->db->prepare(
            "SELECT * FROM users WHERE api_key = ? LIMIT 1"
        );
        $stmt->execute([$apiKey]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        return $user ?: null;
    }

    /**
     * Get user by ID.
     */
    public function getUserById(int $id): ?array {
        $stmt = $this->db->prepare("SELECT * FROM users WHERE id = ? LIMIT 1");
        $stmt->execute([$id]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        return $user ?: null;
    }

    /**
     * Generate a cryptographically random 64-char hex API key.
     */
    public static function generateApiKey(): string {
        return bin2hex(random_bytes(32));
    }

    /**
     * Regenerate API key for a user. Returns the new key.
     */
    public function regenerateApiKey(int $userId): string {
        $newKey = self::generateApiKey();
        $stmt = $this->db->prepare("UPDATE users SET api_key = ? WHERE id = ?");
        $stmt->execute([$newKey, $userId]);
        return $newKey;
    }

    /**
     * Check if user has reached their session limit for the current billing period.
     */
    public function checkSessionLimit(array $user, int $projectId): bool {
        $limits = $this->getPlanLimits($user['plan']);
        if ($limits['sessions_per_month'] === -1) {
            return true; // unlimited
        }

        $monthStart = date('Y-m-01 00:00:00');
        $stmt = $this->db->prepare(
            "SELECT COUNT(*) FROM sessions_log 
             WHERE project_id IN (SELECT id FROM projects WHERE user_id = ?) 
             AND created_at >= ?"
        );
        $stmt->execute([$user['id'], $monthStart]);
        $count = (int)$stmt->fetchColumn();

        return $count < $limits['sessions_per_month'];
    }

    /**
     * Check if user has reached their project limit.
     */
    public function checkProjectLimit(array $user): bool {
        $limits = $this->getPlanLimits($user['plan']);
        if ($limits['projects'] === -1) {
            return true; // unlimited
        }

        $stmt = $this->db->prepare(
            "SELECT COUNT(*) FROM projects WHERE user_id = ?"
        );
        $stmt->execute([$user['id']]);
        $count = (int)$stmt->fetchColumn();

        return $count < $limits['projects'];
    }

    /**
     * Get plan limits.
     * Returns -1 for unlimited.
     */
    public function getPlanLimits(string $plan): array {
        $plans = [
            'free' => [
                'projects' => 1,
                'connectors' => 3,
                'sessions_per_month' => 50,
                'api_access' => false,
                'cloud_sync' => false,
                'shared_state' => false,
            ],
            'pro' => [
                'projects' => -1,
                'connectors' => 10,
                'sessions_per_month' => -1,
                'api_access' => true,
                'cloud_sync' => true,
                'shared_state' => false,
            ],
            'team' => [
                'projects' => -1,
                'connectors' => -1,
                'sessions_per_month' => -1,
                'api_access' => true,
                'cloud_sync' => true,
                'shared_state' => true,
            ],
            'enterprise' => [
                'projects' => -1,
                'connectors' => -1,
                'sessions_per_month' => -1,
                'api_access' => true,
                'cloud_sync' => true,
                'shared_state' => true,
            ],
        ];

        return $plans[$plan] ?? $plans['free'];
    }

    /**
     * Require that the user owns a given project. Returns project row or calls jsonError.
     */
    public function requireProjectOwnership(array $user, string $slug): array {
        $stmt = $this->db->prepare(
            "SELECT * FROM projects WHERE user_id = ? AND slug = ? LIMIT 1"
        );
        $stmt->execute([$user['id'], $slug]);
        $project = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$project) {
            jsonError('Project not found.', 404);
        }

        return $project;
    }

    /**
     * Require project ownership by ID. Returns project row or calls jsonError.
     */
    public function requireProjectOwnershipById(array $user, int $projectId): array {
        $stmt = $this->db->prepare(
            "SELECT * FROM projects WHERE user_id = ? AND id = ? LIMIT 1"
        );
        $stmt->execute([$user['id'], $projectId]);
        $project = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$project) {
            jsonError('Project not found.', 404);
        }

        return $project;
    }
}

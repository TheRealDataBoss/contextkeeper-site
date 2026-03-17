<?php
/**
 * contextkeeper Bundle Generation Engine
 * Sprint 6 - P0 Core Product Output
 *
 * Generates deterministic ZIP bundles containing a complete
 * project snapshot: metadata, connectors, decisions, invariants,
 * and session history.
 *
 * Security: connector config_encrypted fields are included as-is
 * (still encrypted). No plaintext secrets ever appear in output.
 */

class BundleService {
    private PDO $db;
    private int $userId;

    public function __construct(PDO $db, int $userId) {
        $this->db = $db;
        $this->userId = $userId;
    }

    /**
     * Generate a ZIP bundle for a project.
     *
     * @param array $project  Project row from DB
     * @param string $agent   Agent identifier (e.g. 'api', 'dashboard')
     * @return array{path: string, filename: string, metadata: array}
     * @throws Exception on failure
     */
    public function generate(array $project, string $agent = 'api'): array {
        $projectId = (int)$project['id'];
        $timestamp = gmdate('Y-m-d\TH:i:s\Z');
        $safeTimestamp = str_replace([':', '-'], '', gmdate('Ymd\THis\Z'));

        // Collect all data first (fail fast before creating files)
        $projectData = $this->buildProjectJson($project);
        $connectorsData = $this->buildConnectorsJson($projectId);
        $decisionsData = $this->buildDecisionsJson($projectId);
        $invariantsData = $this->buildInvariantsJson($projectId);
        $sessionsData = $this->buildSessionsJson($projectId);

        $metadata = [
            'project_id' => $projectId,
            'project_slug' => $project['slug'],
            'generated_at' => $timestamp,
            'agent' => $agent,
            'connector_count' => count($connectorsData),
            'decision_count' => count($decisionsData),
            'invariant_count' => count($invariantsData),
            'session_count' => count($sessionsData),
            'schema_version' => 'v1',
        ];

        // Compute authority SHA from all collected data for integrity
        $authoritySha = $this->computeAuthoritySha(
            $projectData,
            $connectorsData,
            $decisionsData,
            $invariantsData
        );
        $metadata['authority_sha'] = $authoritySha;

        // Ensure storage directory exists
        $storageDir = $this->ensureStorageDir();

        // Build ZIP
        $filename = "bundle_{$project['slug']}_{$safeTimestamp}.zip";
        $zipPath = $storageDir . '/' . $filename;

        // Remove any leftover file from a previous failed attempt
        if (file_exists($zipPath)) {
            unlink($zipPath);
        }

        $zip = new ZipArchive();
        $result = $zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE);

        if ($result !== true) {
            throw new Exception('Failed to create ZIP archive. ZipArchive error code: ' . $result);
        }

        try {
            $zip->addFromString('project.json', $this->encodeJson($projectData));
            $zip->addFromString('connectors.json', $this->encodeJson($connectorsData));
            $zip->addFromString('decisions.json', $this->encodeJson($decisionsData));
            $zip->addFromString('invariants.json', $this->encodeJson($invariantsData));
            $zip->addFromString('sessions.json', $this->encodeJson($sessionsData));
            $zip->addFromString('metadata.json', $this->encodeJson($metadata));

            if (!$zip->close()) {
                throw new Exception('Failed to finalize ZIP archive.');
            }
        } catch (Exception $e) {
            // Clean up partial file
            $zip->close();
            if (file_exists($zipPath)) {
                unlink($zipPath);
            }
            throw $e;
        }

        // Verify the file was actually written
        if (!file_exists($zipPath) || filesize($zipPath) === 0) {
            if (file_exists($zipPath)) {
                unlink($zipPath);
            }
            throw new Exception('ZIP archive was not written to disk.');
        }

        // Record in sessions_log
        $bundleSessionId = $this->recordBundleSession(
            $projectId,
            $agent,
            count($decisionsData),
            count($invariantsData),
            $authoritySha
        );

        // Update project session count
        $this->db->prepare(
            "UPDATE projects SET sessions_count = sessions_count + 1, updated_at = NOW() WHERE id = ?"
        )->execute([$projectId]);

        // Log usage
        $this->db->prepare(
            "INSERT INTO usage_log (user_id, action, project_id, metadata) VALUES (?, 'bundle_generate', ?, ?)"
        )->execute([
            $this->userId,
            $projectId,
            json_encode([
                'bundle_session_id' => $bundleSessionId,
                'filename' => $filename,
                'decisions' => count($decisionsData),
                'invariants' => count($invariantsData),
                'connectors' => count($connectorsData),
                'sessions' => count($sessionsData),
                'size_bytes' => filesize($zipPath),
            ]),
        ]);

        return [
            'path' => $zipPath,
            'filename' => $filename,
            'session_id' => $bundleSessionId,
            'authority_sha' => $authoritySha,
            'metadata' => $metadata,
        ];
    }

    /**
     * Stream a previously generated bundle file for download.
     * Returns false if file not found.
     */
    public function streamDownload(string $filename): bool {
        // Sanitize filename: alphanumeric, underscores, hyphens, dots only
        if (!preg_match('/^bundle_[a-z0-9\-]+_\d{8}T\d{6}Z\.zip$/', $filename)) {
            return false;
        }

        $storageDir = $this->getStorageDir();
        $filePath = $storageDir . '/' . $filename;

        if (!file_exists($filePath) || !is_readable($filePath)) {
            return false;
        }

        // Prevent path traversal
        $realPath = realpath($filePath);
        $realStorageDir = realpath($storageDir);
        if ($realPath === false || $realStorageDir === false) {
            return false;
        }
        if (strpos($realPath, $realStorageDir) !== 0) {
            return false;
        }

        $fileSize = filesize($filePath);

        header('Content-Type: application/zip');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . $fileSize);
        header('Cache-Control: no-cache, no-store, must-revalidate');
        header('Pragma: no-cache');
        header('Expires: 0');

        readfile($filePath);
        return true;
    }

    /**
     * List bundle files for a project slug.
     * Returns array of file info sorted newest first.
     */
    public function listBundleFiles(string $slug): array {
        $storageDir = $this->getStorageDir();
        if (!is_dir($storageDir)) {
            return [];
        }

        $pattern = $storageDir . '/bundle_' . preg_quote($slug, '/') . '_*.zip';
        $files = glob($pattern);

        if ($files === false || empty($files)) {
            return [];
        }

        $result = [];
        foreach ($files as $file) {
            $basename = basename($file);
            $result[] = [
                'filename' => $basename,
                'size_bytes' => filesize($file),
                'created_at' => date('Y-m-d H:i:s', filemtime($file)),
            ];
        }

        // Sort newest first
        usort($result, function ($a, $b) {
            return strcmp($b['filename'], $a['filename']);
        });

        return $result;
    }

    // ----------------------------------------------------------------
    // Data collection methods - deterministic ordering via ORDER BY id
    // ----------------------------------------------------------------

    private function buildProjectJson(array $project): array {
        return [
            'id' => (int)$project['id'],
            'name' => $project['name'],
            'slug' => $project['slug'],
            'current_state' => $project['current_state'],
            'state_vector' => $project['state_vector']
                ? json_decode($project['state_vector'], true)
                : null,
            'sessions_count' => (int)$project['sessions_count'],
            'decisions_count' => (int)$project['decisions_count'],
            'created_at' => $project['created_at'],
            'updated_at' => $project['updated_at'],
        ];
    }

    private function buildConnectorsJson(int $projectId): array {
        // Connectors are user-scoped, not project-scoped
        // Include all user connectors in the bundle
        $stmt = $this->db->prepare(
            "SELECT id, type, name, config_encrypted, last_sync, status, created_at
             FROM connectors
             WHERE user_id = ?
             ORDER BY id ASC"
        );
        $stmt->execute([$this->userId]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $connectors = [];
        foreach ($rows as $row) {
            $connectors[] = [
                'id' => (int)$row['id'],
                'type' => $row['type'],
                'name' => $row['name'],
                // Config stays encrypted - never expose plaintext secrets
                'config_encrypted' => '[REDACTED]',
                'last_sync' => $row['last_sync'],
                'status' => $row['status'],
                'created_at' => $row['created_at'],
            ];
        }

        return $connectors;
    }

    private function buildDecisionsJson(int $projectId): array {
        $stmt = $this->db->prepare(
            "SELECT id, session_id, title, rationale, alternatives_rejected,
                    established_by, created_at
             FROM decisions
             WHERE project_id = ?
             ORDER BY id ASC"
        );
        $stmt->execute([$projectId]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $decisions = [];
        foreach ($rows as $row) {
            $decisions[] = [
                'id' => (int)$row['id'],
                'session_id' => $row['session_id'] !== null ? (int)$row['session_id'] : null,
                'title' => $row['title'],
                'rationale' => $row['rationale'],
                'alternatives_rejected' => $row['alternatives_rejected']
                    ? json_decode($row['alternatives_rejected'], true)
                    : null,
                'established_by' => $row['established_by'],
                'created_at' => $row['created_at'],
            ];
        }

        return $decisions;
    }

    private function buildInvariantsJson(int $projectId): array {
        $stmt = $this->db->prepare(
            "SELECT id, name, assertion, scope, established_by, active, created_at
             FROM invariants
             WHERE project_id = ?
             ORDER BY id ASC"
        );
        $stmt->execute([$projectId]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $invariants = [];
        foreach ($rows as $row) {
            $invariants[] = [
                'id' => (int)$row['id'],
                'name' => $row['name'],
                'assertion' => $row['assertion'],
                'scope' => $row['scope'],
                'established_by' => $row['established_by'],
                'active' => (bool)$row['active'],
                'created_at' => $row['created_at'],
            ];
        }

        return $invariants;
    }

    private function buildSessionsJson(int $projectId): array {
        $stmt = $this->db->prepare(
            "SELECT id, agent, action, decisions_captured, invariants_captured,
                    files_captured, authority_sha, repo_sha, created_at
             FROM sessions_log
             WHERE project_id = ?
             ORDER BY id ASC"
        );
        $stmt->execute([$projectId]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $sessions = [];
        foreach ($rows as $row) {
            $sessions[] = [
                'id' => (int)$row['id'],
                'agent' => $row['agent'],
                'action' => $row['action'],
                'decisions_captured' => (int)$row['decisions_captured'],
                'invariants_captured' => (int)$row['invariants_captured'],
                'files_captured' => (int)$row['files_captured'],
                'authority_sha' => $row['authority_sha'],
                'repo_sha' => $row['repo_sha'],
                'created_at' => $row['created_at'],
            ];
        }

        return $sessions;
    }

    // ----------------------------------------------------------------
    // Helpers
    // ----------------------------------------------------------------

    /**
     * Deterministic JSON encoding: sorted keys, unescaped unicode, pretty print.
     */
    private function encodeJson($data): string {
        $json = json_encode(
            $data,
            JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
        );

        if ($json === false) {
            throw new Exception('JSON encoding failed: ' . json_last_error_msg());
        }

        return $json;
    }

    /**
     * Compute a SHA-256 over the canonical project state.
     * Used for bundle integrity verification.
     */
    private function computeAuthoritySha(
        array $project,
        array $connectors,
        array $decisions,
        array $invariants
    ): string {
        $canonical = json_encode([
            'project' => $project,
            'connectors' => $connectors,
            'decisions' => $decisions,
            'invariants' => $invariants,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        return hash('sha256', $canonical);
    }

    /**
     * Record a bundle generation event in sessions_log.
     */
    private function recordBundleSession(
        int $projectId,
        string $agent,
        int $decisionsCount,
        int $invariantsCount,
        string $authoritySha
    ): int {
        $stmt = $this->db->prepare(
            "INSERT INTO sessions_log
             (project_id, agent, action, decisions_captured, invariants_captured, authority_sha)
             VALUES (?, ?, 'bundle', ?, ?, ?)"
        );
        $stmt->execute([
            $projectId,
            $agent,
            $decisionsCount,
            $invariantsCount,
            $authoritySha,
        ]);

        return (int)$this->db->lastInsertId();
    }

    private function getStorageDir(): string {
        return __DIR__ . '/../storage/bundles';
    }

    /**
     * Ensure the storage directory exists with proper permissions.
     */
    private function ensureStorageDir(): string {
        $dir = $this->getStorageDir();

        if (!is_dir($dir)) {
            if (!mkdir($dir, 0750, true)) {
                throw new Exception('Failed to create bundle storage directory.');
            }
        }

        if (!is_writable($dir)) {
            throw new Exception('Bundle storage directory is not writable.');
        }

        return $dir;
    }
}

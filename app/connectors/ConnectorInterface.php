<?php
/**
 * contextkeeper Connector Interface
 * 
 * All connector implementations must implement this interface.
 * Connectors provide read/write access to external data sources
 * (GitHub, S3, Google Drive, databases, etc.)
 */

interface ConnectorInterface {
    /**
     * Initialize the connector with decrypted configuration.
     * @param array $config Decrypted connector config (tokens, keys, etc.)
     * @return bool True if connection config is valid
     */
    public function connect(array $config): bool;

    /**
     * Test the connection. Returns true if the external service is reachable
     * and credentials are valid.
     * @return bool
     */
    public function test(): bool;

    /**
     * List files/resources at a given path.
     * @param string $path Path to list (default: root)
     * @return array List of file/resource descriptors
     */
    public function list(string $path = '/'): array;

    /**
     * Read file/resource content.
     * @param string $path Path to the file/resource
     * @return string Content of the file/resource
     */
    public function read(string $path): string;

    /**
     * Write content to a file/resource.
     * @param string $path Path to write to
     * @param string $content Content to write
     * @return bool True on success
     */
    public function write(string $path, string $content): bool;

    /**
     * Sync project state with this connector's data source.
     * @param int $projectId Project to sync
     * @return array Sync result summary (files_synced, errors, etc.)
     */
    public function sync(int $projectId): array;

    /**
     * Get the connector type identifier (e.g., 'github', 's3').
     * @return string
     */
    public function getType(): string;

    /**
     * Get the human-readable connector name (e.g., 'GitHub', 'AWS S3').
     * @return string
     */
    public function getName(): string;
}

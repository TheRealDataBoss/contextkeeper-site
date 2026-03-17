<?php
/**
 * Local File Connector
 *
 * Config: { "base_path": "/home/user/projects/myapp/.contextkeeper" }
 *
 * Stores contextkeeper state files on the local server filesystem.
 * Primarily for self-hosted deployments or development.
 *
 * Security: base_path is sandboxed - directory traversal is blocked.
 */

require_once __DIR__ . '/ConnectorInterface.php';

class Local_fileConnector implements ConnectorInterface
{
    private string $basePath = '';

    public function connect(array $config): bool
    {
        if (empty($config['base_path'])) {
            return false;
        }

        $resolved = realpath($config['base_path']);

        // If the directory doesn't exist yet, try to create it
        if ($resolved === false) {
            if (!@mkdir($config['base_path'], 0750, true)) {
                return false;
            }
            $resolved = realpath($config['base_path']);
        }

        if ($resolved === false || !is_dir($resolved)) {
            return false;
        }

        $this->basePath = $resolved;
        return true;
    }

    public function test(): bool
    {
        return is_dir($this->basePath)
            && is_readable($this->basePath)
            && is_writable($this->basePath);
    }

    public function list(string $path = '/'): array
    {
        $dir = $this->safePath($path);
        if (!$dir || !is_dir($dir)) {
            return [];
        }

        $items = [];
        $iterator = new \DirectoryIterator($dir);

        foreach ($iterator as $entry) {
            if ($entry->isDot()) continue;

            $items[] = [
                'name' => $entry->getFilename(),
                'path' => $this->relativePath($entry->getPathname()),
                'type' => $entry->isDir() ? 'dir' : 'file',
                'size' => $entry->isFile() ? $entry->getSize() : 0,
            ];
        }

        usort($items, fn($a, $b) => strcmp($a['name'], $b['name']));
        return $items;
    }

    public function read(string $path): string
    {
        $file = $this->safePath($path);
        if (!$file || !is_file($file) || !is_readable($file)) {
            return '';
        }

        $content = file_get_contents($file);
        return $content !== false ? $content : '';
    }

    public function write(string $path, string $content): bool
    {
        $file = $this->safePath($path, true);
        if (!$file) {
            return false;
        }

        // Ensure parent directory exists
        $dir = dirname($file);
        if (!is_dir($dir)) {
            if (!@mkdir($dir, 0750, true)) {
                return false;
            }
        }

        return file_put_contents($file, $content, LOCK_EX) !== false;
    }

    public function sync(int $projectId): array
    {
        $files = $this->list('/');
        $synced = 0;
        $errors = [];

        foreach ($files as $file) {
            if ($file['type'] === 'file') {
                $content = $this->read($file['path']);
                if ($content !== '') {
                    $synced++;
                } else {
                    $errors[] = $file['path'] . ': empty or unreadable';
                }
            }
        }

        return [
            'files_synced' => $synced,
            'errors' => $errors,
            'source' => 'local:' . $this->basePath,
        ];
    }

    public function getType(): string { return 'local_file'; }
    public function getName(): string { return 'Local File'; }

    /**
     * Resolve a user-supplied path against basePath, blocking traversal.
     */
    private function safePath(string $path, bool $allowNew = false): ?string
    {
        $path = str_replace(['..', "\0"], '', $path);
        $path = ltrim($path, '/');

        $full = $this->basePath . DIRECTORY_SEPARATOR . $path;

        if ($allowNew) {
            // For write operations, the file may not exist yet
            // but the parent must be inside basePath
            $parentReal = realpath(dirname($full));
            if ($parentReal === false) {
                // Parent doesn't exist - check that the intended parent is within basePath
                $intended = $this->basePath . DIRECTORY_SEPARATOR . dirname($path);
                if (str_starts_with($intended, $this->basePath)) {
                    return $full;
                }
                return null;
            }
            return str_starts_with($parentReal, $this->basePath) ? $full : null;
        }

        $resolved = realpath($full);
        if ($resolved === false) return null;
        return str_starts_with($resolved, $this->basePath) ? $resolved : null;
    }

    /**
     * Get the path relative to basePath for display.
     */
    private function relativePath(string $absolute): string
    {
        $base = $this->basePath . DIRECTORY_SEPARATOR;
        if (str_starts_with($absolute, $base)) {
            return substr($absolute, strlen($base));
        }
        return basename($absolute);
    }
}

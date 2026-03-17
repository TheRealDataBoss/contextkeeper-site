<?php
/**
 * contextkeeper Validator Library
 * 
 * Centralized input validation for API endpoints.
 * Returns cleaned data or throws validation errors.
 */

class Validator {

    /**
     * Parse and validate JSON request body.
     * Returns decoded array or calls jsonError.
     */
    public static function jsonBody(): array {
        $raw = file_get_contents('php://input');
        if (empty($raw)) {
            jsonError('Request body is empty. Send JSON.', 400);
        }

        $data = json_decode($raw, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            jsonError('Invalid JSON: ' . json_last_error_msg(), 400);
        }

        return $data;
    }

    /**
     * Require specific fields in data array.
     * Returns only the required fields (trimmed strings).
     */
    public static function requireFields(array $data, array $fields): array {
        $clean = [];
        $missing = [];

        foreach ($fields as $field) {
            if (!isset($data[$field]) || (is_string($data[$field]) && trim($data[$field]) === '')) {
                $missing[] = $field;
            } else {
                $clean[$field] = is_string($data[$field]) ? trim($data[$field]) : $data[$field];
            }
        }

        if (!empty($missing)) {
            jsonError('Missing required fields: ' . implode(', ', $missing), 400, [
                'missing_fields' => $missing,
            ]);
        }

        return $clean;
    }

    /**
     * Extract optional fields from data array, with defaults.
     */
    public static function optionalFields(array $data, array $fieldsWithDefaults): array {
        $clean = [];
        foreach ($fieldsWithDefaults as $field => $default) {
            if (isset($data[$field])) {
                $clean[$field] = is_string($data[$field]) ? trim($data[$field]) : $data[$field];
            } else {
                $clean[$field] = $default;
            }
        }
        return $clean;
    }

    /**
     * Validate and sanitize a project slug.
     * Must be lowercase alphanumeric + hyphens, 1-255 chars.
     */
    public static function slug(string $input): string {
        $slug = strtolower(trim($input));
        $slug = preg_replace('/[^a-z0-9\-]/', '-', $slug);
        $slug = preg_replace('/-+/', '-', $slug);
        $slug = trim($slug, '-');

        if (empty($slug) || strlen($slug) > 255) {
            jsonError('Invalid slug. Use lowercase letters, numbers, and hyphens (1-255 chars).', 400);
        }

        return $slug;
    }

    /**
     * Validate that a string does not exceed a max length.
     */
    public static function maxLength(string $value, int $max, string $fieldName): string {
        if (strlen($value) > $max) {
            jsonError("Field '{$fieldName}' exceeds maximum length of {$max} characters.", 400);
        }
        return $value;
    }

    /**
     * Validate an ENUM value.
     */
    public static function enum(string $value, array $allowed, string $fieldName): string {
        if (!in_array($value, $allowed, true)) {
            jsonError("Invalid value for '{$fieldName}'. Allowed: " . implode(', ', $allowed), 400);
        }
        return $value;
    }

    /**
     * Validate a positive integer.
     */
    public static function positiveInt($value, string $fieldName): int {
        $val = filter_var($value, FILTER_VALIDATE_INT);
        if ($val === false || $val < 1) {
            jsonError("Field '{$fieldName}' must be a positive integer.", 400);
        }
        return $val;
    }

    /**
     * Validate pagination parameters.
     * Returns [limit, offset].
     */
    public static function pagination(array $params, int $maxLimit = 100): array {
        $limit = isset($params['limit']) ? (int)$params['limit'] : 50;
        $offset = isset($params['offset']) ? (int)$params['offset'] : 0;

        $limit = max(1, min($limit, $maxLimit));
        $offset = max(0, $offset);

        return [$limit, $offset];
    }

    /**
     * Validate JSON data (must be valid JSON string or array/object).
     */
    public static function jsonData($value, string $fieldName): string {
        if (is_array($value) || is_object($value)) {
            return json_encode($value);
        }

        if (is_string($value)) {
            $decoded = json_decode($value, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                jsonError("Field '{$fieldName}' must be valid JSON.", 400);
            }
            return $value;
        }

        jsonError("Field '{$fieldName}' must be valid JSON.", 400);
        return ''; // unreachable, satisfies static analysis
    }
}

<?php

namespace App\Traits;

trait SanitizesInput
{
    /**
     * Sanitize string input
     */
    protected function sanitizeString(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        // Remove null bytes
        $value = str_replace(chr(0), '', $value);
        
        // Trim whitespace
        $value = trim($value);
        
        // Convert special characters to HTML entities
        $value = htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
        
        return $value;
    }

    /**
     * Sanitize array of strings
     */
    protected function sanitizeArray(array $values): array
    {
        return array_map(function ($value) {
            if (is_string($value)) {
                return $this->sanitizeString($value);
            }
            if (is_array($value)) {
                return $this->sanitizeArray($value);
            }
            return $value;
        }, $values);
    }

    /**
     * Clean filename for safe storage
     */
    protected function sanitizeFilename(string $filename): string
    {
        // Remove path traversal attempts
        $filename = basename($filename);
        
        // Remove dangerous characters
        $filename = preg_replace('/[^a-zA-Z0-9._-]/', '_', $filename);
        
        // Prevent double extensions
        $parts = explode('.', $filename);
        if (count($parts) > 2) {
            $ext = array_pop($parts);
            $filename = implode('_', $parts) . '.' . $ext;
        }
        
        return $filename;
    }
}

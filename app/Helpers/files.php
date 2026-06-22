<?php

declare(strict_types=1);

/**
 * data-light File Handling Helpers
 *
 * @author Suraj Dubey
 * @link https://mrsuraj.rf.gd
 * @link https://github.com/5ur4jd-dev
 */

if (!function_exists('generateFilename')) {
    function generateFilename(string $extension): string
    {
        return bin2hex(random_bytes(16)) . '.' . $extension;
    }
}

if (!function_exists('formatFileSize')) {
    function formatFileSize(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $unitIndex = 0;
        $size = $bytes;

        while ($size >= 1024 && $unitIndex < count($units) - 1) {
            $size /= 1024;
            $unitIndex++;
        }

        return round($size, 2) . ' ' . $units[$unitIndex];
    }
}

if (!function_exists('getMimeType')) {
    function getMimeType(string $filePath): string
    {
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime = finfo_file($finfo, $filePath);
        finfo_close($finfo);
        return $mime ?: 'application/octet-stream';
    }
}

if (!function_exists('fileHash')) {
    function fileHash(string $filePath): string
    {
        return hash_file('sha256', $filePath);
    }
}

if (!function_exists('allowedFileTypes')) {
    function allowedFileTypes(): array
    {
        return [
            // CSV
            'text/csv',
            'text/plain',
            'application/csv',
            'application/vnd.ms-excel',
            // Excel
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'application/vnd.ms-excel.sheet.macroenabled.12',
            // JSON
            'application/json',
            'text/json',
        ];
    }
}

if (!function_exists('allowedExtensions')) {
    function allowedExtensions(): array
    {
        return ['csv', 'xlsx', 'xls', 'json'];
    }
}

if (!function_exists('sanitizeFilename')) {
    function sanitizeFilename(string $filename): string
    {
        $filename = preg_replace('/[^a-zA-Z0-9._-]/', '_', $filename);
        $filename = preg_replace('/_{2,}/', '_', $filename);
        return trim($filename, '_');
    }
}

if (!function_exists('maskApiKey')) {
    function maskApiKey(string $key): string
    {
        if (strlen($key) <= 12) {
            return '****';
        }
        return substr($key, 0, 8) . '****' . substr($key, -4);
    }
}

if (!function_exists('deleteFile')) {
    function deleteFile(string $filePath): bool
    {
        if (file_exists($filePath) && is_file($filePath)) {
            return unlink($filePath);
        }
        return false;
    }
}

if (!function_exists('isDuplicateUpload')) {
    function isDuplicateUpload(string $filePath, \PDO $db): ?array
    {
        $hash = fileHash($filePath);
        $stmt = $db->prepare("SELECT * FROM datasets WHERE stored_filename LIKE ? LIMIT 1");
        $stmt->execute(['%' . $hash . '%']);
        return $stmt->fetch() ?: null;
    }
}


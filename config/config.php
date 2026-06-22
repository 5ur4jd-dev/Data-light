<?php

declare(strict_types=1);

/**
 * data-light - Main Configuration File
 *
 * @author Suraj Dubey
 * @link https://mrsuraj.rf.gd
 * @link https://github.com/5ur4jd-dev
 */

// Base paths
$basePath = dirname(__DIR__);
$storagePath = $basePath . '/storage';
$uploadsPath = $storagePath . '/uploads';
$dbPath = $storagePath . '/data-light.sqlite';

// Create storage directories if they don't exist
if (!is_dir($storagePath)) {
    mkdir($storagePath, 0755, true);
}
if (!is_dir($uploadsPath)) {
    mkdir($uploadsPath, 0755, true);
}

// Application settings
return [
    'app' => [
        'name' => 'data-light',
        'display_name' => 'data-light',
        'version' => '1.0.0',
        'url' => $_ENV['APP_URL'] ?? '',
        'env' => $_ENV['APP_ENV'] ?? 'production',
    ],
    'database' => [
        'path' => $dbPath,
    ],
    'storage' => [
        'uploads_path' => $uploadsPath,
        'max_upload_size' => 50 * 1024 * 1024, // 50MB
    ],
    'ai' => [
        'default_model' => 'nvidia/nemotron-3-ultra-550b-a55b:free',
        'api_endpoint' => 'https://openrouter.ai/api/v1/chat/completions',
    ],
];


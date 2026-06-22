<?php

declare(strict_types=1);

/**
 * data-light - API Status Endpoint
 *
 * @author Suraj Dubey
 * @link https://mrsuraj.rf.gd
 * @link https://github.com/5ur4jd-dev
 */

require_once __DIR__ . '/../vendor/autoload.php';

use App\Core\Database;
use App\Core\Env;
use App\Core\Response;
use App\Services\OpenRouterService;

// CORS
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Load env
$basePath = dirname(__DIR__);
Env::load($basePath);

// Initialize database
$dbPath = $basePath . '/storage/data-light.sqlite';
Database::initialize($dbPath);
Database::setupTables();

// Check AI status
$aiService = new OpenRouterService();
$aiEnabled = $aiService->isConfigured();

Response::json([
    'status' => 'ok',
    'service' => 'data-light',
    'version' => '1.0.0',
    'ai_enabled' => $aiEnabled,
    'timestamp' => date('c'),
]);


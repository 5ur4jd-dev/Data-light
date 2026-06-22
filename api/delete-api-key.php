<?php

declare(strict_types=1);

/**
 * data-light - Delete API Key Endpoint
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
header('Access-Control-Allow-Methods: POST, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST' && $_SERVER['REQUEST_METHOD'] !== 'DELETE') {
    Response::error('Method not allowed', 405);
}

// Load env & database
$basePath = dirname(__DIR__);
Env::load($basePath);
$dbPath = $basePath . '/storage/data-light.sqlite';
Database::initialize($dbPath);
Database::setupTables();

$service = new OpenRouterService();
$service->deleteApiKey();

Response::success([], 'API key deleted successfully');


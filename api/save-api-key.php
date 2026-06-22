<?php

declare(strict_types=1);

/**
 * data-light - Save API Key Endpoint
 *
 * @author Suraj Dubey
 * @link https://mrsuraj.rf.gd
 * @link https://github.com/5ur4jd-dev
 */

require_once __DIR__ . '/../vendor/autoload.php';

use App\Core\Database;
use App\Core\Env;
use App\Core\Response;
use App\Core\Validator;
use App\Services\OpenRouterService;

// CORS
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    Response::error('Method not allowed', 405);
}

// Load env & database
$basePath = dirname(__DIR__);
Env::load($basePath);
$dbPath = $basePath . '/storage/data-light.sqlite';
Database::initialize($dbPath);
Database::setupTables();

// Get input
$input = json_decode(file_get_contents('php://input'), true);
$apiKey = trim($input['api_key'] ?? '');
$model = trim($input['model'] ?? 'nvidia/nemotron-3-ultra-550b-a55b:free');

// Validate
$validator = new Validator();
$validator->required('api_key', $apiKey, 'API key is required');

if ($validator->fails()) {
    Response::error($validator->firstError(), 400, ['errors' => $validator->errors()]);
}

// Validate API key format
if (!str_starts_with($apiKey, 'sk-')) {
    Response::error('Invalid API key format. Key should start with "sk-"', 400);
}

$service = new OpenRouterService();
$service->saveApiKey($apiKey);
$service->saveModel($model);

Response::success([
    'masked_key' => maskApiKey($apiKey),
    'model' => $model,
], 'API key saved successfully');


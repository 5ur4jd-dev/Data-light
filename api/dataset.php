<?php

declare(strict_types=1);

/**
 * data-light - Single Dataset Endpoint
 *
 * @author Suraj Dubey
 * @link https://mrsuraj.rf.gd
 * @link https://github.com/5ur4jd-dev
 */

require_once __DIR__ . '/../vendor/autoload.php';

use App\Core\Database;
use App\Core\Env;
use App\Core\Response;
use App\Services\DatasetService;

// CORS
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    Response::error('Method not allowed', 405);
}

// Load env & database
$basePath = dirname(__DIR__);
Env::load($basePath);
$dbPath = $basePath . '/storage/data-light.sqlite';
Database::initialize($dbPath);
Database::setupTables();

$id = $_GET['id'] ?? null;
if (!$id || !is_numeric($id)) {
    Response::error('Invalid dataset ID', 400);
}

$service = new DatasetService();
$dataset = $service->getDataset((int)$id);

if (!$dataset) {
    Response::notFound('Dataset not found');
}

$dataset['created_at_formatted'] = date('M j, Y g:i A', strtotime($dataset['created_at']));

Response::success([
    'dataset' => $dataset,
]);


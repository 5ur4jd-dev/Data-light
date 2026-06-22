<?php

declare(strict_types=1);

/**
 * data-light - Export Analysis Endpoint
 *
 * @author Suraj Dubey
 * @link https://mrsuraj.rf.gd
 * @link https://github.com/5ur4jd-dev
 */

require_once __DIR__ . '/../vendor/autoload.php';

use App\Core\Database;
use App\Core\Env;
use App\Core\Response;
use App\Services\AnalysisService;

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
$format = $_GET['format'] ?? 'json';

if (!$id || !is_numeric($id)) {
    Response::error('Invalid analysis ID', 400);
}

if ($format !== 'json') {
    Response::error('Only JSON format is supported', 400);
}

$service = new AnalysisService();
$analysis = $service->getAnalysis((int)$id);

if (!$analysis) {
    Response::notFound('Analysis not found');
}

// Build export data
$exportData = [
    'meta' => [
        'exported_from' => 'data-light',
        'export_date' => date('c'),
        'version' => '1.0.0',
    ],
    'analysis' => [
        'id' => $analysis['id'],
        'dataset_id' => $analysis['dataset_id'],
        'dataset_name' => $analysis['dataset_name'],
        'status' => $analysis['status'],
        'created_at' => $analysis['created_at'],
    ],
    'results' => $analysis['results'] ?? [],
];

$filename = 'analysis_' . $analysis['id'] . '_' . $analysis['dataset_name'] . '_' . date('Y-m-d') . '.json';

header('Content-Type: application/json; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: no-cache');

echo json_encode($exportData, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
exit;


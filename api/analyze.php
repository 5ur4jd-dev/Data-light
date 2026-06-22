<?php

declare(strict_types=1);

/**
 * data-light - Run Analysis Endpoint
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

$id = $_GET['id'] ?? null;
if (!$id || !is_numeric($id)) {
    Response::error('Invalid dataset ID', 400);
}

$useAi = ($_GET['ai'] ?? 'true') === 'true';

try {
    // Run statistical analysis
    $analysisService = new AnalysisService();
    $result = $analysisService->analyzeDataset((int)$id);

    // Generate AI insights if enabled
    if ($useAi) {
        $aiService = new OpenRouterService();
        $aiInsights = $aiService->generateInsights($result['summary']);
        $result['ai_insights'] = $aiInsights;

        // Update stored results with AI insights
        $existing = $result;
        $existing['ai_insights'] = $aiInsights;
        \App\Core\Database::update(
            'analyses',
            ['results' => json_encode([
                'overview' => $result['overview'],
                'data_quality' => $result['data_quality'],
                'column_analysis' => $result['column_analysis'],
                'correlations' => $result['correlations'],
                'insights' => $result['insights'],
                'ai_insights' => $aiInsights,
                'summary' => $result['summary'],
            ])],
            'id = ?',
            [$result['id']]
        );
    }

    Response::success([
        'analysis' => $result,
    ], 'Analysis completed successfully');
} catch (\Exception $e) {
    Response::serverError($e->getMessage());
}


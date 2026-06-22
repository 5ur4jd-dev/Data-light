<?php

declare(strict_types=1);

/**
 * data-light - Dataset Upload Endpoint
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
use App\Services\DatasetService;

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

// Check file upload
if (!isset($_FILES['file']) || $_FILES['file']['error'] === UPLOAD_ERR_NO_FILE) {
    Response::error('No file uploaded', 400);
}

$file = $_FILES['file'];

// Check upload errors
if ($file['error'] !== UPLOAD_ERR_OK) {
    $errorMessages = [
        UPLOAD_ERR_INI_SIZE => 'File exceeds server limit',
        UPLOAD_ERR_FORM_SIZE => 'File exceeds form limit',
        UPLOAD_ERR_PARTIAL => 'File partially uploaded',
        UPLOAD_ERR_NO_TMP_DIR => 'No temporary directory',
        UPLOAD_ERR_CANT_WRITE => 'Failed to write file',
        UPLOAD_ERR_EXTENSION => 'Upload stopped by extension',
    ];
    Response::error($errorMessages[$file['error']] ?? 'Upload failed', 400);
}

// Validate
$validator = new Validator();
$maxSize = 50 * 1024 * 1024; // 50MB

$validator
    ->fileSize('file', $file, $maxSize)
    ->fileExtension('file', $file['name'], ['csv', 'xlsx', 'xls', 'json']);

if ($validator->fails()) {
    Response::error($validator->firstError(), 400, ['errors' => $validator->errors()]);
}

// Process upload
$uploadsPath = $basePath . '/storage/uploads';
$service = new DatasetService();

try {
    $result = $service->createDataset($file, $uploadsPath);

    Response::success([
        'dataset' => [
            'id' => $result['id'],
            'name' => $result['name'],
            'original_filename' => $result['original_filename'],
            'file_type' => $result['file_type'],
            'rows_count' => $result['rows_count'],
            'columns_count' => $result['columns_count'],
            'column_names' => $result['column_names'],
            'dtypes' => $result['dtypes'],
            'preview' => $result['preview'],
        ]
    ], 'Dataset uploaded successfully');
} catch (\Exception $e) {
    // Clean up uploaded file on error
    if (isset($file['tmp_name']) && file_exists($file['tmp_name'])) {
        unlink($file['tmp_name']);
    }
    Response::serverError($e->getMessage());
}


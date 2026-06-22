<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use RuntimeException;

/**
 * data-light Statistical Analysis Engine
 *
 * @author Suraj Dubey
 * @link https://mrsuraj.rf.gd
 * @link https://github.com/5ur4jd-dev
 */
class AnalysisService
{
    private DatasetService $datasetService;

    public function __construct()
    {
        $this->datasetService = new DatasetService();
    }

    public function analyzeDataset(int $datasetId): array
    {
        $datasetData = $this->datasetService->getDatasetData($datasetId);
        $data = $datasetData['data'];
        $headers = $datasetData['headers'];

        if (empty($data)) {
            throw new RuntimeException('Dataset is empty.');
        }

        $dataset = Database::fetch("SELECT name FROM datasets WHERE id = ?", [$datasetId]);
        $datasetName = $dataset['name'] ?? 'Unknown';

        // Determine column types
        $columnTypes = $this->inferColumnTypes($data, $headers);
        $numericColumns = array_keys(array_filter($columnTypes, fn($t) => $t === 'numeric'));
        $categoricalColumns = array_keys(array_filter($columnTypes, fn($t) => $t !== 'numeric'));

        // 1. Overview
        $overview = [
            'rows' => count($data),
            'columns' => count($headers),
            'dataset_size' => count($data) * count($headers),
            'dataset_type' => $this->classifyDatasetType($columnTypes),
        ];

        // 2. Data Quality
        $dataQuality = $this->analyzeDataQuality($data, $headers);

        // 3. Column Analysis
        $columnAnalysis = [];
        foreach ($headers as $header) {
            $colData = array_column($data, $header);
            $missing = missingCount($colData);
            $unique = uniqueCount($colData);

            $analysis = [
                'name' => $header,
                'type' => $columnTypes[$header] ?? 'string',
                'total_values' => count($colData),
                'missing' => $missing,
                'missing_percent' => round(($missing / count($colData)) * 100, 2),
                'unique' => $unique,
                'unique_percent' => round(($unique / count($colData)) * 100, 2),
                'completeness' => round(((count($colData) - $missing) / count($colData)) * 100, 2),
            ];

            // Numeric statistics
            if (($columnTypes[$header] ?? 'string') === 'numeric') {
                $numericValues = array_filter($colData, fn($v) => is_numeric(str_replace([','], [''], (string)$v)) && (string)$v !== '');
                $cleanValues = array_map(fn($v) => (float) str_replace([','], [''], (string)$v), $numericValues);

                if (!empty($cleanValues)) {
                    $quarts = quartiles($cleanValues);
                    $analysis['statistics'] = [
                        'count' => count($cleanValues),
                        'mean' => round(mean($cleanValues), 4),
                        'median' => round($quarts['q2'] ?? 0, 4),
                        'std_dev' => round(standardDeviation($cleanValues) ?? 0, 4),
                        'min' => round(minVal($cleanValues) ?? 0, 4),
                        'max' => round(maxVal($cleanValues) ?? 0, 4),
                        'q1' => round($quarts['q1'] ?? 0, 4),
                        'q2' => round($quarts['q2'] ?? 0, 4),
                        'q3' => round($quarts['q3'] ?? 0, 4),
                        'range' => round((maxVal($cleanValues) ?? 0) - (minVal($cleanValues) ?? 0), 4),
                    ];

                    // Detect outliers using IQR method
                    $iqr = ($quarts['q3'] ?? 0) - ($quarts['q1'] ?? 0);
                    $lowerBound = ($quarts['q1'] ?? 0) - 1.5 * $iqr;
                    $upperBound = ($quarts['q3'] ?? 0) + 1.5 * $iqr;
                    $outliers = array_filter($cleanValues, fn($v) => $v < $lowerBound || $v > $upperBound);
                    $analysis['statistics']['outliers_count'] = count($outliers);
                }
            }

            // Categorical analysis
            if (($columnTypes[$header] ?? 'string') !== 'numeric') {
                $analysis['categories'] = valueCounts($colData, 15);
                $analysis['category_count'] = count(array_unique(array_map('strval', $colData)));
            }

            $columnAnalysis[$header] = $analysis;
        }

        // 4. Correlations
        $correlationResult = ['matrix' => [], 'strong' => []];
        if (count($numericColumns) >= 2) {
            $correlationResult = correlationMatrix($data, $numericColumns);
        }

        // 5. Rule-based Insights
        $insights = $this->generateInsights($overview, $dataQuality, $columnAnalysis, $correlationResult['strong'] ?? []);

        // Build summary for AI
        $summary = $this->buildSummary($overview, $dataQuality, $columnAnalysis, $correlationResult);

        // Store analysis
        $analysisId = Database::insert('analyses', [
            'dataset_id' => $datasetId,
            'dataset_name' => $datasetName,
            'status' => 'completed',
            'config' => json_encode(['columns_analyzed' => count($headers)]),
            'results' => json_encode([
                'overview' => $overview,
                'data_quality' => $dataQuality,
                'column_analysis' => $columnAnalysis,
                'correlations' => $correlationResult,
                'insights' => $insights,
                'summary' => $summary,
            ]),
        ]);

        return [
            'id' => $analysisId,
            'dataset_id' => $datasetId,
            'dataset_name' => $datasetName,
            'status' => 'completed',
            'overview' => $overview,
            'data_quality' => $dataQuality,
            'column_analysis' => $columnAnalysis,
            'correlations' => $correlationResult,
            'insights' => $insights,
            'summary' => $summary,
        ];
    }

    private function inferColumnTypes(array $data, array $headers): array
    {
        $types = [];
        foreach ($headers as $header) {
            $numericCount = 0;
            $dateCount = 0;
            $total = 0;

            foreach ($data as $row) {
                $value = trim((string)($row[$header] ?? ''));
                if ($value === '' || $value === 'null' || $value === 'NULL') continue;

                $total++;
                $cleanValue = str_replace([','], [''], $value);

                if (is_numeric($cleanValue)) {
                    $numericCount++;
                }

                if (strtotime($value) !== false && preg_match('/\d{2,4}[-\/]\d{1,2}/', $value)) {
                    $dateCount++;
                }
            }

            if ($total === 0) {
                $types[$header] = 'string';
            } elseif ($dateCount / $total > 0.8) {
                $types[$header] = 'datetime';
            } elseif ($numericCount / $total > 0.8) {
                $types[$header] = 'numeric';
            } else {
                $types[$header] = 'string';
            }
        }
        return $types;
    }

    private function classifyDatasetType(array $columnTypes): string
    {
        $numericCount = count(array_filter($columnTypes, fn($t) => $t === 'numeric'));
        $total = count($columnTypes);

        if ($numericCount === $total) return 'Numeric';
        if ($numericCount === 0) return 'Categorical';
        if ($numericCount / $total > 0.6) return 'Primarily Numeric';
        if ($numericCount / $total > 0.3) return 'Mixed';
        return 'Primarily Categorical';
    }

    private function analyzeDataQuality(array $data, array $headers): array
    {
        $totalCells = count($data) * count($headers);
        $missingCells = 0;
        $missingByColumn = [];
        $duplicateRows = 0;
        $seenRows = [];

        foreach ($data as $row) {
            $rowKey = json_encode(array_map('strval', $row));
            if (isset($seenRows[$rowKey])) {
                $duplicateRows++;
            } else {
                $seenRows[$rowKey] = true;
            }

            foreach ($headers as $header) {
                $value = trim((string)($row[$header] ?? ''));
                if ($value === '' || $value === 'null' || $value === 'NULL') {
                    $missingCells++;
                    $missingByColumn[$header] = ($missingByColumn[$header] ?? 0) + 1;
                }
            }
        }

        $completeness = $totalCells > 0 ? round((($totalCells - $missingCells) / $totalCells) * 100, 2) : 100;

        return [
            'total_cells' => $totalCells,
            'missing_cells' => $missingCells,
            'missing_percent' => $totalCells > 0 ? round(($missingCells / $totalCells) * 100, 2) : 0,
            'duplicate_rows' => $duplicateRows,
            'completeness' => $completeness,
            'missing_by_column' => $missingByColumn,
        ];
    }

    private function generateInsights(array $overview, array $dataQuality, array $columnAnalysis, array $strongCorrelations): array
    {
        $insights = [];

        // High missing values
        foreach ($columnAnalysis as $col => $analysis) {
            if (($analysis['missing_percent'] ?? 0) > 50) {
                $insights[] = [
                    'title' => 'High Missing Values',
                    'message' => "Column '{$col}' has {$analysis['missing_percent']}% missing values. Consider imputation or removal.",
                    'type' => 'warning',
                    'column' => $col,
                ];
            }
        }

        // Strong correlations
        foreach ($strongCorrelations as $corr) {
            $insights[] = [
                'title' => 'Strong Correlation Detected',
                'message' => "{$corr['column1']} and {$corr['column2']} show a {$corr['strength']} {$corr['direction']} correlation (r = {$corr['correlation']}).",
                'type' => 'info',
            ];
        }

        // Low completeness
        if ($dataQuality['completeness'] < 80) {
            $insights[] = [
                'title' => 'Low Data Completeness',
                'message' => "Overall data completeness is {$dataQuality['completeness']}%. Consider data cleaning.",
                'type' => 'warning',
            ];
        }

        // Potential outliers
        foreach ($columnAnalysis as $col => $analysis) {
            if (isset($analysis['statistics']['outliers_count']) && $analysis['statistics']['outliers_count'] > 0) {
                $outlierPercent = round(($analysis['statistics']['outliers_count'] / $analysis['statistics']['count']) * 100, 2);
                if ($outlierPercent > 5) {
                    $insights[] = [
                        'title' => 'Potential Outliers',
                        'message' => "Column '{$col}' contains {$analysis['statistics']['outliers_count']} potential outliers ({$outlierPercent}% of data).",
                        'type' => 'warning',
                        'column' => $col,
                    ];
                }
            }
        }

        // Category imbalance
        foreach ($columnAnalysis as $col => $analysis) {
            if (isset($analysis['categories']) && count($analysis['categories']) > 0) {
                $topPercent = $analysis['categories'][0]['percentage'] ?? 0;
                if ($topPercent > 80 && count($analysis['categories']) > 2) {
                    $insights[] = [
                        'title' => 'Category Imbalance',
                        'message' => "Column '{$col}' is heavily imbalanced: '{$analysis['categories'][0]['value']}' represents {$topPercent}% of values.",
                        'type' => 'info',
                        'column' => $col,
                    ];
                }
            }
        }

        // Duplicate rows
        if ($dataQuality['duplicate_rows'] > 0) {
            $insights[] = [
                'title' => 'Duplicate Rows',
                'message' => "Found {$dataQuality['duplicate_rows']} duplicate rows in the dataset.",
                'type' => 'warning',
            ];
        }

        // Good completeness
        if ($dataQuality['completeness'] >= 95) {
            $insights[] = [
                'title' => 'High Quality Data',
                'message' => "Dataset has excellent completeness at {$dataQuality['completeness']}%.",
                'type' => 'success',
            ];
        }

        return $insights;
    }

    private function buildSummary(array $overview, array $dataQuality, array $columnAnalysis, array $correlationResult): array
    {
        $numericCols = array_filter($columnAnalysis, fn($a) => ($a['type'] ?? '') === 'numeric');
        $catCols = array_filter($columnAnalysis, fn($a) => ($a['type'] ?? '') !== 'numeric');

        $summary = [
            'overview' => $overview,
            'data_quality' => [
                'completeness' => $dataQuality['completeness'],
                'missing_percent' => $dataQuality['missing_percent'],
                'duplicate_rows' => $dataQuality['duplicate_rows'],
            ],
            'column_stats' => [],
        ];

        foreach ($numericCols as $name => $analysis) {
            $summary['column_stats'][$name] = [
                'type' => 'numeric',
                'mean' => $analysis['statistics']['mean'] ?? null,
                'median' => $analysis['statistics']['median'] ?? null,
                'std_dev' => $analysis['statistics']['std_dev'] ?? null,
                'min' => $analysis['statistics']['min'] ?? null,
                'max' => $analysis['statistics']['max'] ?? null,
                'missing_percent' => $analysis['missing_percent'] ?? 0,
            ];
        }

        foreach ($catCols as $name => $analysis) {
            $summary['column_stats'][$name] = [
                'type' => 'categorical',
                'unique_count' => $analysis['unique'] ?? 0,
                'top_categories' => array_slice(array_column($analysis['categories'] ?? [], 'value'), 0, 3),
                'missing_percent' => $analysis['missing_percent'] ?? 0,
            ];
        }

        $summary['strong_correlations'] = array_map(fn($c) => [
            'columns' => [$c['column1'], $c['column2']],
            'correlation' => $c['correlation'],
        ], $correlationResult['strong'] ?? []);

        return $summary;
    }

    public function getAnalysis(int $id): ?array
    {
        $analysis = Database::fetch("SELECT * FROM analyses WHERE id = ?", [$id]);
        if (!$analysis) {
            return null;
        }

        $analysis['results'] = json_decode($analysis['results'], true) ?? [];
        $analysis['config'] = json_decode($analysis['config'], true) ?? [];
        return $analysis;
    }

    public function getAllAnalyses(): array
    {
        return Database::fetchAll(
            "SELECT id, dataset_id, dataset_name, status, created_at FROM analyses ORDER BY created_at DESC"
        );
    }

    public function deleteAnalysis(int $id): bool
    {
        return Database::delete('analyses', 'id = ?', [$id]) > 0;
    }

    public function getAnalysisCount(): int
    {
        $result = Database::fetch("SELECT COUNT(*) as count FROM analyses");
        return (int) ($result['count'] ?? 0);
    }
}


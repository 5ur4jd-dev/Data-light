<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use RuntimeException;

/**
 * data-light Dataset Management Service
 *
 * @author Suraj Dubey
 * @link https://mrsuraj.rf.gd
 * @link https://github.com/5ur4jd-dev
 */
class DatasetService
{
    public function getAllDatasets(string $search = '', string $sort = 'newest'): array
    {
        $sql = "SELECT id, name, original_filename, file_type, rows_count, columns_count, created_at FROM datasets";
        $params = [];

        if ($search) {
            $sql .= " WHERE name LIKE ? OR original_filename LIKE ?";
            $params[] = '%' . $search . '%';
            $params[] = '%' . $search . '%';
        }

        $sql .= match ($sort) {
            'oldest' => " ORDER BY created_at ASC",
            'name_asc' => " ORDER BY name ASC",
            'name_desc' => " ORDER BY name DESC",
            'rows_desc' => " ORDER BY rows_count DESC",
            'rows_asc' => " ORDER BY rows_count ASC",
            default => " ORDER BY created_at DESC",
        };

        return Database::fetchAll($sql, $params);
    }

    public function getDataset(int $id): ?array
    {
        $dataset = Database::fetch("SELECT * FROM datasets WHERE id = ?", [$id]);
        if (!$dataset) {
            return null;
        }

        $dataset['column_names'] = json_decode($dataset['column_names'], true) ?? [];
        $dataset['dtypes'] = json_decode($dataset['dtypes'], true) ?? [];
        $dataset['preview'] = json_decode($dataset['preview'], true) ?? [];

        return $dataset;
    }

    public function createDataset(array $fileData, string $uploadsPath): array
    {
        $tmpPath = $fileData['tmp_name'];
        $originalName = sanitizeFilename($fileData['name']);
        $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));

        if (!in_array($extension, allowedExtensions(), true)) {
            throw new RuntimeException('Invalid file extension. Allowed: csv, xlsx, xls, json');
        }

        $storedFilename = generateFilename($extension);
        $filePath = $uploadsPath . '/' . $storedFilename;

        if (!move_uploaded_file($tmpPath, $filePath)) {
            throw new RuntimeException('Failed to move uploaded file.');
        }

        // Parse the file
        $reader = $this->getReader($extension);
        $reader->load($filePath);

        $columnNames = $reader->getHeaders();
        $dtypes = $reader->inferColumnTypes();
        $rowCount = $reader->getRowCount();
        $columnCount = $reader->getColumnCount();
        $preview = $reader->getPreview(20);

        // Store in database
        $id = Database::insert('datasets', [
            'name' => pathinfo($originalName, PATHINFO_FILENAME),
            'original_filename' => $originalName,
            'stored_filename' => $storedFilename,
            'file_path' => $filePath,
            'file_type' => $extension,
            'rows_count' => $rowCount,
            'columns_count' => $columnCount,
            'column_names' => json_encode($columnNames),
            'dtypes' => json_encode($dtypes),
            'preview' => json_encode($preview),
        ]);

        return [
            'id' => $id,
            'name' => pathinfo($originalName, PATHINFO_FILENAME),
            'original_filename' => $originalName,
            'file_type' => $extension,
            'rows_count' => $rowCount,
            'columns_count' => $columnCount,
            'column_names' => $columnNames,
            'dtypes' => $dtypes,
            'preview' => $preview,
        ];
    }

    public function deleteDataset(int $id): bool
    {
        $dataset = Database::fetch("SELECT file_path, stored_filename FROM datasets WHERE id = ?", [$id]);
        if (!$dataset) {
            return false;
        }

        // Delete file
        if (file_exists($dataset['file_path'])) {
            unlink($dataset['file_path']);
        }

        // Delete related analyses
        Database::delete('analyses', 'dataset_id = ?', [$id]);

        // Delete dataset record
        Database::delete('datasets', 'id = ?', [$id]);

        return true;
    }

    public function getDatasetData(int $id): array
    {
        $dataset = Database::fetch("SELECT file_path, file_type, column_names FROM datasets WHERE id = ?", [$id]);
        if (!$dataset) {
            throw new RuntimeException('Dataset not found.');
        }

        $reader = $this->getReader($dataset['file_type']);
        $reader->load($dataset['file_path']);

        return [
            'headers' => $reader->getHeaders(),
            'data' => $reader->getData(),
            'row_count' => $reader->getRowCount(),
            'column_count' => $reader->getColumnCount(),
        ];
    }

    private function getReader(string $extension): CsvReader|ExcelReader|JsonReader
    {
        return match ($extension) {
            'csv' => new CsvReader(),
            'xlsx', 'xls' => new ExcelReader(),
            'json' => new JsonReader(),
            default => throw new RuntimeException('Unsupported file type: ' . $extension),
        };
    }

    public function getDatasetCount(): int
    {
        $result = Database::fetch("SELECT COUNT(*) as count FROM datasets");
        return (int) ($result['count'] ?? 0);
    }
}


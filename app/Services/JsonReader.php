<?php

declare(strict_types=1);

namespace App\Services;

use RuntimeException;

/**
 * data-light JSON File Reader
 *
 * @author Suraj Dubey
 * @link https://mrsuraj.rf.gd
 * @link https://github.com/5ur4jd-dev
 */
class JsonReader
{
    private array $data = [];
    private array $headers = [];
    private int $rowCount = 0;

    public function load(string $filePath): self
    {
        if (!file_exists($filePath)) {
            throw new RuntimeException('JSON file not found.');
        }

        $content = file_get_contents($filePath);
        if ($content === false) {
            throw new RuntimeException('Unable to read JSON file.');
        }

        $decoded = json_decode($content, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new RuntimeException('Invalid JSON: ' . json_last_error_msg());
        }

        // Handle both array of objects and single object
        if (!is_array($decoded)) {
            throw new RuntimeException('JSON must contain an array of objects.');
        }

        // If it's an associative array (single object), wrap it
        if (empty($decoded)) {
            throw new RuntimeException('JSON file is empty.');
        }

        if (array_keys($decoded) !== range(0, count($decoded) - 1)) {
            $decoded = [$decoded];
        }

        $this->data = [];
        $this->rowCount = 0;

        // Collect all unique keys as headers
        $allKeys = [];
        foreach ($decoded as $item) {
            if (is_array($item)) {
                $allKeys = array_merge($allKeys, array_keys($item));
            }
        }
        $this->headers = array_values(array_unique($allKeys));

        if (empty($this->headers)) {
            throw new RuntimeException('JSON file has no recognizable data.');
        }

        // Normalize data
        foreach ($decoded as $item) {
            if (!is_array($item)) continue;

            $row = [];
            foreach ($this->headers as $key) {
                $value = $item[$key] ?? '';
                if (is_array($value) || is_object($value)) {
                    $value = json_encode($value);
                }
                $row[$key] = (string)$value;
            }

            $this->data[] = $row;
            $this->rowCount++;
        }

        return $this;
    }

    public function getHeaders(): array
    {
        return $this->headers;
    }

    public function getData(): array
    {
        return $this->data;
    }

    public function getRowCount(): int
    {
        return $this->rowCount;
    }

    public function getColumnCount(): int
    {
        return count($this->headers);
    }

    public function getPreview(int $rows = 10): array
    {
        return array_slice($this->data, 0, $rows);
    }

    public function inferColumnTypes(): array
    {
        $types = [];
        foreach ($this->headers as $header) {
            $types[$header] = $this->inferType($header);
        }
        return $types;
    }

    private function inferType(string $column): string
    {
        $numericCount = 0;
        $dateCount = 0;
        $total = 0;

        foreach ($this->data as $row) {
            if (!isset($row[$column])) continue;
            $value = trim((string)$row[$column]);
            if ($value === '' || $value === 'null' || $value === 'NULL') continue;

            $total++;

            if (is_numeric(str_replace([','], [''], $value))) {
                $numericCount++;
            }

            if (strtotime($value) !== false && preg_match('/\d{2,4}[-\/]\d{1,2}/', $value)) {
                $dateCount++;
            }
        }

        if ($total === 0) return 'string';
        if ($dateCount / $total > 0.8) return 'datetime';
        if ($numericCount / $total > 0.8) return 'numeric';
        return 'string';
    }
}


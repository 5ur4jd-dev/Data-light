<?php

declare(strict_types=1);

namespace App\Services;

use RuntimeException;

/**
 * data-light CSV File Reader
 *
 * @author Suraj Dubey
 * @link https://mrsuraj.rf.gd
 * @link https://github.com/5ur4jd-dev
 */
class CsvReader
{
    private array $data = [];
    private array $headers = [];
    private int $rowCount = 0;

    public function load(string $filePath): self
    {
        if (!file_exists($filePath)) {
            throw new RuntimeException('CSV file not found.');
        }

        $handle = fopen($filePath, 'r');
        if (!$handle) {
            throw new RuntimeException('Unable to open CSV file.');
        }

        // Detect delimiter
        $sample = fgets($handle, 1024);
        rewind($handle);

        $delimiters = [',', "\t", ';', '|'];
        $bestDelimiter = ',';
        $maxCols = 0;

        foreach ($delimiters as $delimiter) {
            $test = str_getcsv($sample, $delimiter);
            if (count($test) > $maxCols) {
                $maxCols = count($test);
                $bestDelimiter = $delimiter;
            }
        }

        // Read headers
        $this->headers = fgetcsv($handle, 0, $bestDelimiter);
        if (!$this->headers) {
            fclose($handle);
            throw new RuntimeException('CSV file has no headers.');
        }

        // Clean headers
        $this->headers = array_map(function ($h) {
            return trim((string)$h);
        }, $this->headers);

        // Read data rows
        $this->data = [];
        $this->rowCount = 0;
        while (($row = fgetcsv($handle, 0, $bestDelimiter)) !== false) {
            if (count($row) === count($this->headers)) {
                $this->data[] = array_combine($this->headers, $row);
                $this->rowCount++;
            }
        }

        fclose($handle);
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


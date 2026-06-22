<?php

declare(strict_types=1);

namespace App\Services;

use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use RuntimeException;

/**
 * data-light Excel File Reader (XLSX, XLS)
 *
 * @author Suraj Dubey
 * @link https://mrsuraj.rf.gd
 * @link https://github.com/5ur4jd-dev
 */
class ExcelReader
{
    private array $data = [];
    private array $headers = [];
    private int $rowCount = 0;
    private Spreadsheet $spreadsheet;

    public function load(string $filePath): self
    {
        if (!file_exists($filePath)) {
            throw new RuntimeException('Excel file not found.');
        }

        try {
            $this->spreadsheet = IOFactory::load($filePath);
            $worksheet = $this->spreadsheet->getActiveSheet();
            $rows = $worksheet->toArray();

            if (empty($rows)) {
                throw new RuntimeException('Excel file is empty.');
            }

            // First row as headers
            $this->headers = array_map(function ($h) {
                return trim((string)$h);
            }, array_shift($rows));

            // Clean headers - replace empty headers with Column_N
            foreach ($this->headers as $i => $header) {
                if ($header === '') {
                    $this->headers[$i] = 'Column_' . ($i + 1);
                }
            }

            // Read data rows
            $this->data = [];
            $this->rowCount = 0;

            foreach ($rows as $row) {
                // Skip completely empty rows
                if (count(array_filter($row, fn($v) => $v !== null && $v !== '')) === 0) {
                    continue;
                }

                // Pad row if needed
                while (count($row) < count($this->headers)) {
                    $row[] = '';
                }

                $this->data[] = array_combine(
                    array_slice($this->headers, 0, count($row)),
                    $row
                );
                $this->rowCount++;
            }
        } catch (\Exception $e) {
            throw new RuntimeException('Error reading Excel file: ' . $e->getMessage());
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


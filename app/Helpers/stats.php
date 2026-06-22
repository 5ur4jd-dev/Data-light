<?php

declare(strict_types=1);

/**
 * data-light Statistical Analysis Helpers
 *
 * @author Suraj Dubey
 * @link https://mrsuraj.rf.gd
 * @link https://github.com/5ur4jd-dev
 */

if (!function_exists('mean')) {
    function mean(array $values): ?float
    {
        $clean = array_filter($values, fn($v) => is_numeric($v) && $v !== '');
        if (empty($clean)) return null;
        return array_sum($clean) / count($clean);
    }
}

if (!function_exists('median')) {
    function median(array $values): ?float
    {
        $clean = array_filter($values, fn($v) => is_numeric($v) && $v !== '');
        if (empty($clean)) return null;
        sort($clean);
        $count = count($clean);
        $mid = (int) floor($count / 2);

        if ($count % 2) {
            return (float) $clean[$mid];
        }
        return ((float) $clean[$mid - 1] + (float) $clean[$mid]) / 2;
    }
}

if (!function_exists('standardDeviation')) {
    function standardDeviation(array $values): ?float
    {
        $clean = array_filter($values, fn($v) => is_numeric($v) && $v !== '');
        if (empty($clean)) return null;

        $avg = mean($clean);
        if ($avg === null) return null;

        $variance = 0;
        foreach ($clean as $val) {
            $variance += pow((float)$val - $avg, 2);
        }
        $variance /= count($clean);
        return sqrt($variance);
    }
}

if (!function_exists('quartiles')) {
    function quartiles(array $values): array
    {
        $clean = array_filter($values, fn($v) => is_numeric($v) && $v !== '');
        if (empty($clean)) return ['q1' => null, 'q2' => null, 'q3' => null];

        sort($clean);
        $count = count($clean);

        $q2 = median($clean);

        if ($count % 2 === 0) {
            $lowerHalf = array_slice($clean, 0, (int)($count / 2));
            $upperHalf = array_slice($clean, (int)($count / 2));
        } else {
            $lowerHalf = array_slice($clean, 0, (int)floor($count / 2));
            $upperHalf = array_slice($clean, (int)ceil($count / 2));
        }

        $q1 = median($lowerHalf);
        $q3 = median($upperHalf);

        return [
            'q1' => $q1,
            'q2' => $q2,
            'q3' => $q3
        ];
    }
}

if (!function_exists('minVal')) {
    function minVal(array $values): ?float
    {
        $clean = array_filter($values, fn($v) => is_numeric($v) && $v !== '');
        if (empty($clean)) return null;
        return (float) min($clean);
    }
}

if (!function_exists('maxVal')) {
    function maxVal(array $values): ?float
    {
        $clean = array_filter($values, fn($v) => is_numeric($v) && $v !== '');
        if (empty($clean)) return null;
        return (float) max($clean);
    }
}

if (!function_exists('countValues')) {
    function countValues(array $values): int
    {
        return count(array_filter($values, fn($v) => is_numeric($v) && $v !== ''));
    }
}

if (!function_exists('sumValues')) {
    function sumValues(array $values): ?float
    {
        $clean = array_filter($values, fn($v) => is_numeric($v) && $v !== '');
        if (empty($clean)) return null;
        return array_sum($clean);
    }
}

if (!function_exists('missingCount')) {
    function missingCount(array $values): int
    {
        $missing = 0;
        foreach ($values as $v) {
            if ($v === '' || $v === null || $v === 'null' || $v === 'NULL' || $v === 'NaN' || $v === 'nan') {
                $missing++;
            }
        }
        return $missing;
    }
}

if (!function_exists('uniqueCount')) {
    function uniqueCount(array $values): int
    {
        return count(array_unique(array_map('strval', $values)));
    }
}

if (!function_exists('valueCounts')) {
    function valueCounts(array $values, int $limit = 20): array
    {
        $counts = [];
        $total = count($values);

        foreach ($values as $v) {
            $key = (string) $v;
            if (!isset($counts[$key])) {
                $counts[$key] = 0;
            }
            $counts[$key]++;
        }

        arsort($counts);

        $result = [];
        foreach (array_slice($counts, 0, $limit, true) as $value => $count) {
            $result[] = [
                'value' => $value,
                'count' => $count,
                'percentage' => round(($count / $total) * 100, 2)
            ];
        }

        return $result;
    }
}

if (!function_exists('correlation')) {
    function correlation(array $x, array $y): ?float
    {
        $n = count($x);
        if ($n === 0 || count($y) !== $n) return null;

        $validPairs = [];
        for ($i = 0; $i < $n; $i++) {
            if (is_numeric($x[$i]) && is_numeric($y[$i]) && $x[$i] !== '' && $y[$i] !== '') {
                $validPairs[] = [(float)$x[$i], (float)$y[$i]];
            }
        }

        $m = count($validPairs);
        if ($m < 2) return null;

        $sumX = $sumY = $sumXY = $sumX2 = $sumY2 = 0;
        foreach ($validPairs as [$xi, $yi]) {
            $sumX += $xi;
            $sumY += $yi;
            $sumXY += $xi * $yi;
            $sumX2 += $xi * $xi;
            $sumY2 += $yi * $yi;
        }

        $denominator = sqrt(($m * $sumX2 - $sumX * $sumX) * ($m * $sumY2 - $sumY * $sumY));
        if ($denominator == 0) return null;

        return round(($m * $sumXY - $sumX * $sumY) / $denominator, 4);
    }
}

if (!function_exists('correlationMatrix')) {
    function correlationMatrix(array $data, array $numericColumns): array
    {
        $matrix = [];
        $strongCorrelations = [];

        foreach ($numericColumns as $col1) {
            $matrix[$col1] = [];
            foreach ($numericColumns as $col2) {
                if ($col1 === $col2) {
                    $matrix[$col1][$col2] = 1.0;
                } else {
                    $corr = correlation(
                        array_column($data, $col1),
                        array_column($data, $col2)
                    );
                    $matrix[$col1][$col2] = $corr ?? 0;

                    if ($corr !== null && abs($corr) >= 0.5 && $col1 < $col2) {
                        $strongCorrelations[] = [
                            'column1' => $col1,
                            'column2' => $col2,
                            'correlation' => round($corr, 4),
                            'strength' => abs($corr) >= 0.8 ? 'Very Strong' : (abs($corr) >= 0.7 ? 'Strong' : 'Moderate'),
                            'direction' => $corr > 0 ? 'Positive' : 'Negative'
                        ];
                    }
                }
            }
        }

        return [
            'matrix' => $matrix,
            'strong' => $strongCorrelations
        ];
    }
}


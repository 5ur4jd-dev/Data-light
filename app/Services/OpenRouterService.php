<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use RuntimeException;

/**
 * data-light OpenRouter AI Integration Service
 *
 * @author Suraj Dubey
 * @link https://mrsuraj.rf.gd
 * @link https://github.com/5ur4jd-dev
 */
class OpenRouterService
{
    private string $apiEndpoint = 'https://openrouter.ai/api/v1/chat/completions';

    public function isConfigured(): bool
    {
        $key = $this->getApiKey();
        return !empty($key);
    }

    public function getApiKey(): ?string
    {
        $setting = Database::fetch("SELECT key_value FROM app_settings WHERE key_name = 'openrouter_api_key'");
        return $setting['key_value'] ?? null;
    }

    public function getModel(): string
    {
        $setting = Database::fetch("SELECT key_value FROM app_settings WHERE key_name = 'openrouter_model'");
        return $setting['key_value'] ?? 'nvidia/nemotron-3-ultra-550b-a55b:free';
    }

    public function saveApiKey(string $apiKey): bool
    {
        $existing = Database::fetch("SELECT id FROM app_settings WHERE key_name = 'openrouter_api_key'");

        if ($existing) {
            Database::update(
                'app_settings',
                ['key_value' => $apiKey, 'updated_at' => date('Y-m-d H:i:s')],
                'key_name = ?',
                ['openrouter_api_key']
            );
        } else {
            Database::insert('app_settings', [
                'key_name' => 'openrouter_api_key',
                'key_value' => $apiKey,
            ]);
        }

        return true;
    }

    public function saveModel(string $model): bool
    {
        $existing = Database::fetch("SELECT id FROM app_settings WHERE key_name = 'openrouter_model'");

        if ($existing) {
            Database::update(
                'app_settings',
                ['key_value' => $model, 'updated_at' => date('Y-m-d H:i:s')],
                'key_name = ?',
                ['openrouter_model']
            );
        } else {
            Database::insert('app_settings', [
                'key_name' => 'openrouter_model',
                'key_value' => $model,
            ]);
        }

        return true;
    }

    public function deleteApiKey(): bool
    {
        Database::delete('app_settings', "key_name IN ('openrouter_api_key', 'openrouter_model')");
        return true;
    }

    public function getMaskedKey(): ?string
    {
        $key = $this->getApiKey();
        if (empty($key)) return null;
        return maskApiKey($key);
    }

    public function generateInsights(array $datasetSummary): array
    {
        $apiKey = $this->getApiKey();
        if (empty($apiKey)) {
            return [
                [
                    'title' => 'AI Insight Error',
                    'message' => 'AI insights unavailable. Rule-based analysis completed.',
                    'type' => 'warning'
                ]
            ];
        }

        $model = $this->getModel();

        $prompt = $this->buildPrompt($datasetSummary);

        try {
            $response = $this->callApi($prompt, $apiKey, $model);
            $insights = $this->parseResponse($response);

            if (empty($insights)) {
                return [
                    [
                        'title' => 'AI Insight Error',
                        'message' => 'AI insights unavailable. Rule-based analysis completed.',
                        'type' => 'warning'
                    ]
                ];
            }

            return $insights;
        } catch (\Exception $e) {
            return [
                [
                    'title' => 'AI Insight Error',
                    'message' => 'AI insights unavailable. Rule-based analysis completed.',
                    'type' => 'warning'
                ]
            ];
        }
    }

    private function buildPrompt(array $summary): string
    {
        $overview = $summary['overview'] ?? [];
        $quality = $summary['data_quality'] ?? [];
        $colStats = $summary['column_stats'] ?? [];
        $correlations = $summary['strong_correlations'] ?? [];

        $prompt = "Analyze this dataset summary and return 3-5 business insights as valid JSON.\n\n";
        $prompt .= "Dataset Overview:\n";
        $prompt .= "- Rows: " . ($overview['rows'] ?? 'N/A') . "\n";
        $prompt .= "- Columns: " . ($overview['columns'] ?? 'N/A') . "\n";
        $prompt .= "- Type: " . ($overview['dataset_type'] ?? 'N/A') . "\n";
        $prompt .= "- Completeness: " . ($quality['completeness'] ?? 'N/A') . "%\n";
        $prompt .= "- Duplicate Rows: " . ($quality['duplicate_rows'] ?? 'N/A') . "\n\n";

        $prompt .= "Column Statistics:\n";
        foreach ($colStats as $name => $stats) {
            if ($stats['type'] === 'numeric') {
                $prompt .= "- {$name} (numeric): mean=" . ($stats['mean'] ?? 'N/A') . ", median=" . ($stats['median'] ?? 'N/A') . ", std_dev=" . ($stats['std_dev'] ?? 'N/A') . ", range=[" . ($stats['min'] ?? 'N/A') . " to " . ($stats['max'] ?? 'N/A') . "], missing=" . ($stats['missing_percent'] ?? 0) . "%\n";
            } else {
                $prompt .= "- {$name} (categorical): unique=" . ($stats['unique_count'] ?? 'N/A') . ", top=[" . implode(', ', $stats['top_categories'] ?? []) . "], missing=" . ($stats['missing_percent'] ?? 0) . "%\n";
            }
        }

        if (!empty($correlations)) {
            $prompt .= "\nStrong Correlations:\n";
            foreach ($correlations as $corr) {
                $prompt .= "- " . implode(' & ', $corr['columns'] ?? []) . ": r=" . ($corr['correlation'] ?? 'N/A') . "\n";
            }
        }

        $prompt .= "\nReturn ONLY a JSON array. Each object must have: title (string), message (string), type (one of: info, warning, success). No markdown, no code blocks, just raw JSON.";

        return $prompt;
    }

    private function callApi(string $prompt, string $apiKey, string $model): string
    {
        $payload = [
            'model' => $model,
            'messages' => [
                [
                    'role' => 'system',
                    'content' => 'You are a data analytics expert. Provide concise, actionable business insights based on dataset summaries. Return only valid JSON arrays. No markdown formatting.'
                ],
                [
                    'role' => 'user',
                    'content' => $prompt
                ]
            ],
            'temperature' => 0.3,
            'max_tokens' => 1000,
        ];

        $ch = curl_init($this->apiEndpoint);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($payload),
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $apiKey,
                'HTTP-Referer: ' . ($_SERVER['HTTP_HOST'] ?? 'data-light.local'),
                'X-Title: data-light',
            ],
            CURLOPT_TIMEOUT => 60,
            CURLOPT_SSL_VERIFYPEER => true,
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200 || $response === false) {
            throw new RuntimeException('OpenRouter API request failed with HTTP ' . $httpCode);
        }

        return $response;
    }

    private function parseResponse(string $response): array
    {
        $decoded = json_decode($response, true);
        if (!$decoded || !isset($decoded['choices'][0]['message']['content'])) {
            return [];
        }

        $content = $decoded['choices'][0]['message']['content'];

        // Extract JSON from potential markdown code blocks
        $content = preg_replace('/^```json\s*/', '', $content);
        $content = preg_replace('/\s*```$/', '', $content);
        $content = trim($content);

        $insights = json_decode($content, true);
        if (!is_array($insights)) {
            return [];
        }

        // Validate and sanitize
        $validTypes = ['info', 'warning', 'success'];
        $sanitized = [];
        foreach ($insights as $insight) {
            if (!isset($insight['title']) || !isset($insight['message'])) {
                continue;
            }
            $type = $insight['type'] ?? 'info';
            if (!in_array($type, $validTypes, true)) {
                $type = 'info';
            }
            $sanitized[] = [
                'title' => (string) $insight['title'],
                'message' => (string) $insight['message'],
                'type' => $type,
            ];
        }

        return $sanitized;
    }
}


<?php

namespace App\Services\Seo;

use Illuminate\Support\Facades\Http;

class DataForSeoClient
{
    private string $baseUrl;
    private string $login;
    private string $password;

    public function __construct()
    {
        $this->baseUrl  = 'https://api.dataforseo.com/v3';
        $this->login    = (string) config('services.dataforseo.login');
        $this->password = (string) config('services.dataforseo.password');
    }

protected function post(string $path, array $payload): array
{
    $endpoint = rtrim($this->baseUrl, '/') . '/' . ltrim($path, '/');

    try {
        $res = Http::withBasicAuth($this->login, $this->password)
            ->acceptJson()
            ->asJson()
            ->timeout(30)
            ->retry(2, 500)
            ->post($endpoint, $payload);

        $json = $res->json();

        if (!$res->successful()) {
            logger()->error('DataForSEO HTTP error', [
                'path' => $path,
                'status' => $res->status(),
                'body' => $res->body(),
                'json' => $json,
            ]);

            return [
                'ok' => false,
                'http_status' => $res->status(),
                'json' => $json,
                'raw' => $res->body(),
            ];
        }

        // ✅ DataForSEO renvoie aussi des erreurs "logiques" avec HTTP 200
        // ex: tasks_error > 0
        if (is_array($json) && (($json['tasks_error'] ?? 0) > 0)) {
            logger()->error('DataForSEO tasks_error', [
                'path' => $path,
                'json' => $json,
            ]);

            return [
                'ok' => false,
                'http_status' => $res->status(),
                'json' => $json,
            ];
        }

        return [
            'ok' => true,
            'http_status' => $res->status(),
            'json' => $json,
        ];
    } catch (\Throwable $e) {
        logger()->error('DataForSEO exception', [
            'path' => $path,
            'message' => $e->getMessage(),
        ]);

        return [
            'ok' => false,
            'exception' => true,
            'message' => $e->getMessage(),
        ];
    }
}


    /** Backlinks Summary */
    public function backlinksSummary(string $domain): array
    {
        return $this->post('backlinks/summary/live', [[
            'target' => $domain,
            'include_subdomains' => true,
            'internal_list_limit' => 0,
            'external_list_limit' => 0,
        ]]);
    }

    /** Alias compat */
    public function summary(string $domain): array
    {
        return $this->backlinksSummary($domain);
    }

    public function anchors(string $domain, int $limit = 10): array
    {
        return $this->post('backlinks/anchors/live', [[
            'target' => $domain,
            'limit' => $limit,
            'order_by' => ['backlinks,desc'],
            'include_subdomains' => true,
        ]]);
    }

    public function newLostBacklinks(string $domain, int $days = 30): array
    {
        return $this->post('backlinks/history/live', [[
            'target' => $domain,
            'date_from' => now()->subDays($days)->toDateString(),
            'date_to' => now()->toDateString(),
            'include_subdomains' => true,
            'group_range' => 'day',
        ]]);
    }

    public function bulkTrafficEstimation(array $targets, int $locationCode = 2250, string $languageCode = 'fr'): array
    {
        return $this->post('dataforseo_labs/google/bulk_traffic_estimation/live', [[
            'targets' => array_values($targets),
            'item_types' => ['organic'],
            'location_code' => $locationCode,
            'language_code' => $languageCode,
        ]]);
    }

    public function rankedKeywords(string $target, int $locationCode = 2250, string $languageCode = 'fr', int $limit = 100): array
    {
        return $this->post('dataforseo_labs/google/ranked_keywords/live', [[
            'target' => $target,
            'location_code' => $locationCode,
            'language_code' => $languageCode,
            'limit' => $limit,
        ]]);
    }

    /** SERP Google Organic (Live Advanced) */
    public function serpGoogleOrganicLiveAdvanced(string $keyword, int $locationCode = 2250, string $languageCode = 'fr', int $depth = 20): array
    {
        return $this->post('serp/google/organic/live/advanced', [[
            'keyword' => $keyword,
            'location_code' => $locationCode,
            'language_code' => $languageCode,
            'device' => 'desktop',
            'os' => 'windows',
            'depth' => $depth,
        ]]);
    }
}

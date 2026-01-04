<?php

namespace App\Services\Seo;

use Illuminate\Support\Facades\Http;

class MozClient
{
    public function urlMetrics(string $urlOrDomain): array
    {
        // D’après la doc Moz API (URL Metrics) :contentReference[oaicite:2]{index=2}
        $payload = [
            'targets' => [$urlOrDomain],
        ];

        $res = Http::withBasicAuth(
                config('services.moz.access_id'),
                config('services.moz.secret_key')
            )
            ->timeout(60)
            ->post('https://lsapi.seomoz.com/v2/url_metrics', $payload);

        $res->throw();

        return $res->json();
    }
}

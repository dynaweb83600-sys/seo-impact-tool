<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ContentSuggestion;
use App\Models\ReportItem;
use Illuminate\Http\Request;
use App\Services\Content\ContentClaimValidator;
use App\Services\Seo\CompetitorMetricsService;

class ReportItemContentSuggestionController extends Controller
{
    public function index(
        Request $request,
        ReportItem $item,
        ContentClaimValidator $validator,
        CompetitorMetricsService $competitorMetrics
    ) {
        $token  = (string) $request->query('token');
        $report = $item->report;

        if (!$report || !$token || $token !== $report->access_token) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $badWords = [
            'stoppropagation','preventdefault','addeventlistener','queryselector',
            'cookie','consent','panier','checkout','login','compte'
        ];

        $filterAndValidate = function ($collection) use ($validator, $badWords) {
            return $collection
                ->map(function ($suggestion) use ($validator) {
                    $data = [
                        'suggested_title' => $suggestion->suggested_title,
                        'primary_keyword' => $suggestion->primary_keyword,
                        'outline_h2' => $suggestion->outline_h2,
                        'must_have_json' => $suggestion->must_have_json,
                    ];
                    
                    $v = $validator->validateSuggestion($data);
                    $suggestion->validator = $v['verdict'] ?? [];
                    return $suggestion;
                })
                ->filter(function ($suggestion) use ($badWords) {
                    $v = $suggestion->validator ?? [];

                    if (($v['is_factually_correct'] ?? null) === false) return false;
                    if (($v['is_trivial_or_inherent'] ?? null) === true) return false;
                    if (($v['is_good_topic'] ?? null) === false) return false;

                    $allData = [
                        'title' => $suggestion->suggested_title ?? '',
                        'keywords' => $suggestion->secondary_keywords_json ?? [],
                        'outline_h2' => $suggestion->outline_h2 ?? [],
                        'outline_h3' => $suggestion->outline_h3 ?? [],
                    ];
                    
                    $blob = mb_strtolower(json_encode($allData, JSON_UNESCAPED_UNICODE));
                    foreach ($badWords as $bw) {
                        if (str_contains($blob, $bw)) return false;
                    }

                    return true;
                })
                ->values();
        };

        $pagesRaw = ContentSuggestion::where('report_item_id', $item->id)
            ->where('content_type', 'page')
            ->orderByDesc('priority_score')
            ->get();

        $articlesRaw = ContentSuggestion::where('report_item_id', $item->id)
            ->where('content_type', 'article')
            ->orderByDesc('priority_score')
            ->get();

        $pages    = $filterAndValidate($pagesRaw);
        $articles = $filterAndValidate($articlesRaw);

        $hosts = collect($item->competitors ?? [])
            ->map(function ($c) {
                if (is_array($c)) return $c['host'] ?? null;
                $urlHost = parse_url((string) $c, PHP_URL_HOST);
                return $urlHost ?: null;
            })
            ->filter()
            ->map(fn($h) => mb_strtolower(preg_replace('/^www\./i', '', $h)))
            ->unique()
            ->values()
            ->take(8)
            ->all();

        $metricsByHost = $hosts ? $competitorMetrics->getMany($hosts) : [];

        return response()->json([
            'item_id'  => $item->id,
            'domain'   => $item->domain,
            'pages'    => $pages,
            'articles' => $articles,
            'competitors' => [
                'hosts'   => $hosts,
                'metrics' => $metricsByHost,
            ],
        ]);
    }
}

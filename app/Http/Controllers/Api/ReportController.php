<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Report;
use Illuminate\Http\Request;


class ReportController extends Controller
{
    public function show(Request $request, Report $report)
    {
        $token = $request->query('token');

        if (!$token || $token !== $report->access_token) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $report->load(['items' => fn ($q) => $q->orderBy('domain')]);

        return response()->json([
            'status' => $report->status,
            'requested_count' => (int) $report->requested_count,
            'processed_count' => (int) $report->processed_count,

            'items' => $report->items->map(function ($i) {
                return [
					'id' => $i->id,
                    'domain' => $i->domain,
                    'da' => $i->da,
                    'pa' => $i->pa,
                    'linking_domains' => $i->linking_domains,
                    'inbound_links' => $i->inbound_links,

                    'dofollow_links' => $i->dofollow_links,
                    'nofollow_links' => $i->nofollow_links,
                    'new_backlinks_30d' => $i->new_backlinks_30d,
                    'lost_backlinks_30d' => $i->lost_backlinks_30d,
                    'top_anchors' => $i->top_anchors,

                    'domain_created_at' => optional($i->domain_created_at)->format('Y-m-d'),
                    'domain_age_years' => $i->domain_age_years,
                    'domain_age_years_rounded' => $i->domainAgeYearsRounded(),
                    'domain_age_label' => $i->domainAgeLabel(),

                    'organic_keywords' => $i->organic_keywords,
                    'traffic_estimated' => $i->traffic_estimated,
                    'traffic_etv' => $i->traffic_etv,

                    'dofollow_ratio' => $i->dofollowRatio(),
                    'nofollow_ratio' => $i->nofollowRatio(),
                    'backlinks_per_ref_domain' => $i->backlinksPerRefDomain(),
                    'net_backlinks_30d' => $i->netBacklinks30d(),
                    'growth_trend' => $i->growthTrend(),

                    'estimated_seo_value_eur' => $i->estimatedSeoValueEur(),
                    'estimated_seo_value_text' => $i->formatEuro($i->estimatedSeoValueEur()),

                    'seo_diagnosis' => $i->seoDiagnosis(),

                    // ✅ IA stockée en DB (option B)
                    'ai_tooltips'  => $i->ai_tooltips ?? [],
                    'ai_diagnosis' => $i->ai_diagnosis ?? [],
                    'ai_details'   => $i->ai_details ?? [],
                    'ai_generated_at' => optional($i->ai_generated_at)?->toISOString(),
                ];
            })->values()->all(),
        ]);
    }


    public function exportCsv(Request $request, Report $report)
    {
        $token = $request->query('token');

        if (!$token || $token !== $report->access_token) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $report->load(['items' => fn ($q) => $q->orderBy('domain')]);

        $filename = "report_{$report->id}.csv";

        $headers = [
            "Content-Type" => "text/csv; charset=UTF-8",
            "Content-Disposition" => "attachment; filename=\"$filename\"",
        ];

        $callback = function () use ($report) {
            $out = fopen('php://output', 'w');

            // BOM UTF-8 pour Excel (accents OK)
            fprintf($out, chr(0xEF).chr(0xBB).chr(0xBF));

            // ✅ Header CSV (aligné avec les valeurs)
            fputcsv($out, [
                'domain','da','pa','linking_domains','inbound_links',
                'dofollow_links','nofollow_links',
                'new_backlinks_30d','lost_backlinks_30d',
                'domain_created_at','domain_age_years','domain_age_label',
                'Keywords (SEO)','Traffic (ETV)',
                'Dofollow (%)','Nofollow (%)',
                'Backlinks / Ref Domain','Net Backlinks (30j)',
                'Growth Trend','Estimated SEO Value (€)',
                'SEO Diagnosis'
            ], ';');

            foreach ($report->items as $i) {

                $diagnosisText = collect($i->seoDiagnosis())
                    ->map(fn($d) => ($d['message'] ?? '') . ' => ' . ($d['action'] ?? ''))
                    ->filter()
                    ->implode(' | ');

                fputcsv($out, [
                    $i->domain,
                    $i->da,
                    $i->pa,
                    $i->linking_domains,
                    $i->inbound_links,
                    $i->dofollow_links,
                    $i->nofollow_links,
                    $i->new_backlinks_30d,
                    $i->lost_backlinks_30d,
                    optional($i->domain_created_at)->format('Y-m-d'),
                    $i->domainAgeYearsRounded(),
                    $i->domainAgeLabel(),
                    $i->organic_keywords,
                    $i->traffic_etv,
                    $i->dofollowRatio(),
                    $i->nofollowRatio(),
                    $i->backlinksPerRefDomain(),
                    $i->netBacklinks30d(),
                    $i->growthTrend(),
                    $i->estimatedSeoValueEur(),
                    $diagnosisText,
                ], ';');
            }

            fclose($out);
        };

        return response()->stream($callback, 200, $headers);
    }
	
	public function contentSuggestions(ReportItem $item, Request $request)
{
    try {
        // 1) Si déjà en DB (cache)
        if (!empty($item->content_suggestions)) {
            return response()->json($item->content_suggestions);
        }

        // 2) Sinon, générer via OpenAI
        $plan = app(\App\Services\ContentPlanService::class)->generate($item);

        // $plan doit être: ['pages'=>[...], 'articles'=>[...]]
        $item->content_suggestions = $plan;
        $item->save();

        return response()->json($plan);

    } catch (\Throwable $e) {
        // ⚠️ IMPORTANT : log complet
        \Log::warning('OpenAI content plan errorr1', [
            'domain' => $item->domain,
            'item_id' => $item->id,
            'message' => $e->getMessage(),
            'code' => $e->getCode(),
        ]);

        // ✅ Fallback (au lieu de vide)
        $fallback = $this->fallbackSuggestions($item);

        return response()->json([
            'pages' => $fallback['pages'],
            'articles' => $fallback['articles'],
            'warning' => 'Plan IA indisponible, suggestions générées en mode fallback.',
        ], 200);
    }
}


private function fallbackSuggestions(ReportItem $item): array
{
    $domain = $item->domain;

    return [
        'pages' => [
            [
                'id' => 'p1',
                'priority_label' => 'Haute',
                'intent' => 'commerciale',
                'priority_score' => 85,
                'suggested_title' => "Accueil — Présentation & promesse ($domain)",
                'primary_keyword' => 'marque + bénéfices',
                'outline_h2' => ['Bénéfices', 'Preuves', 'Produits/Services', 'FAQ', 'Contact'],
            ],
            [
                'id' => 'p2',
                'priority_label' => 'Haute',
                'intent' => 'transactionnelle',
                'priority_score' => 80,
                'suggested_title' => "Page pilier — Guide complet (mot-clé principal)",
                'primary_keyword' => 'mot-clé principal',
                'outline_h2' => ['Définition', 'Bienfaits', 'Comment choisir', 'Erreurs', 'FAQ'],
            ],
        ],
        'articles' => [
            [
                'id' => 'a1',
                'priority_label' => 'Moyenne',
                'intent' => 'informationnelle',
                'priority_score' => 65,
                'suggested_title' => "Top 10 questions sur [thématique] (réponses claires)",
                'primary_keyword' => 'question + thématique',
                'outline_h2' => ['Question 1', 'Question 2', 'Question 3', 'FAQ'],
            ],
        ],
    ];
}


}

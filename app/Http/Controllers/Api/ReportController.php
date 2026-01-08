<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Report;
use App\Models\ReportItem;
use Illuminate\Http\Request;

class ReportController extends Controller
{
    public function show(Request $request, Report $report)
    {
        $token = $request->query('token');

        $isOwner = auth()->check() && auth()->id() === (int) $report->user_id;
        $hasValidToken = $token && hash_equals((string) $report->access_token, (string) $token);

        if (!$isOwner && !$hasValidToken) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        // ✅ IMPORTANT : recharger depuis la DB + items (évite état stale)
        $report = Report::query()
            ->with(['items' => fn ($q) => $q->orderBy('domain')])
            ->findOrFail($report->id);

        // ✅ Fallback : si le report n’a pas gsc_connected mais que des items ont des datas, on considère connecté
        $itemsHaveGsc = $report->items->contains(function ($i) {
            return $i->gsc_clicks_30d !== null
                || $i->gsc_impressions_30d !== null
                || $i->gsc_position_30d !== null
                || $i->gsc_site_url !== null;
        });

        $gscConnected = $report->gsc_connected;
        if ($gscConnected === null && $itemsHaveGsc) {
            $gscConnected = true;
        }

        return response()->json([
            'status'          => $report->status,
            'requested_count' => $report->requested_count,
            'processed_count' => $report->processed_count,

            'items' => $report->items->map(function ($i) {
                return [
                    'id'              => $i->id,
                    'domain'          => $i->domain,

                    'da'              => $i->da,
                    'pa'              => $i->pa,
                    'linking_domains' => $i->linking_domains,
                    'inbound_links'   => $i->inbound_links,
                    'dofollow_links'  => $i->dofollow_links,
                    'nofollow_links'  => $i->nofollow_links,
                    'new_backlinks_30d'  => $i->new_backlinks_30d,
                    'lost_backlinks_30d' => $i->lost_backlinks_30d,

                    'domain_created_at' => optional($i->domain_created_at)->format('Y-m-d'),
                    'domain_age_years'  => method_exists($i, 'domainAgeYearsRounded') ? $i->domainAgeYearsRounded() : null,
                    'domain_age_label'  => method_exists($i, 'domainAgeLabel') ? $i->domainAgeLabel() : null,

                    // ✅ GSC
                    'gsc_clicks_30d'      => $i->gsc_clicks_30d,
                    'gsc_impressions_30d' => $i->gsc_impressions_30d,
                    'gsc_position_30d'    => $i->gsc_position_30d,
                    'gsc_site_url'        => $i->gsc_site_url,
                    'gsc_updated_at'      => optional($i->gsc_updated_at)->toISOString(),

                    'organic_keywords' => $i->organic_keywords,
                    'traffic_etv'      => $i->traffic_etv,

                    'traffic_estimated' => $i->traffic_estimated,

                    // si ton UI attend "visits_month"
                    'visits_month' => $i->gsc_clicks_30d,
                ];
            }),

            'gsc' => [
                'connected' => (bool) $gscConnected,
                'property'  => $report->gsc_property,
            ],
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

            fprintf($out, chr(0xEF).chr(0xBB).chr(0xBF));

            fputcsv($out, [
                'domain','da','pa','linking_domains','inbound_links',
                'dofollow_links','nofollow_links',
                'new_backlinks_30d','lost_backlinks_30d',
                'domain_created_at','domain_age_years','domain_age_label',
                'Keywords (SEO)','Traffic (ETV)',
                'GSC Clicks (30j)','GSC Impressions (30j)','GSC Position (30j)',
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
                    method_exists($i, 'domainAgeYearsRounded') ? $i->domainAgeYearsRounded() : null,
                    method_exists($i, 'domainAgeLabel') ? $i->domainAgeLabel() : null,
                    $i->organic_keywords,
                    $i->traffic_etv,
                    $i->gsc_clicks_30d,
                    $i->gsc_impressions_30d,
                    $i->gsc_position_30d,
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
            if (!empty($item->content_suggestions)) {
                return response()->json($item->content_suggestions);
            }

            $plan = app(\App\Services\ContentPlanService::class)->generate($item);

            $item->content_suggestions = $plan;
            $item->save();

            return response()->json($plan);

        } catch (\Throwable $e) {
            \Log::warning('OpenAI content plan errorr1', [
                'domain'  => $item->domain,
                'item_id' => $item->id,
                'message' => $e->getMessage(),
                'code'    => $e->getCode(),
            ]);

            $fallback = $this->fallbackSuggestions($item);

            return response()->json([
                'pages'    => $fallback['pages'],
                'articles' => $fallback['articles'],
                'warning'  => 'Plan IA indisponible, suggestions générées en mode fallback.',
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

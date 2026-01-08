<?php

namespace App\Jobs;

use App\Models\Report;
use App\Models\ReportItem;
use App\Services\Seo\DataForSeoClient;
use App\Services\Seo\WhoisService;
use App\Services\OpenAiSeoCopyService;
use App\Services\OpenAiSeoContentPlanService;
use App\Services\Seo\CompetitorDiscoveryService;
use App\Services\Seo\SeedKeywordService;
use App\Services\Seo\OnsiteSeedExtractorService;
use App\Services\Seo\TopicProfileService;
use App\Services\Seo\PageAnalyzerService;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class FetchDomainMetricsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    // Optionnel (si tu veux contrôler le worker)
    public int $timeout = 300;
    public int $tries = 1;

    public function __construct(
        public int $reportId,
        public array $domains
    ) {}

    public function handle(
        DataForSeoClient $dfs,
        WhoisService $whois,
        OpenAiSeoCopyService $ai,
        OpenAiSeoContentPlanService $contentAi,
        CompetitorDiscoveryService $competitors,
        TopicProfileService $topicProfile,
        OnsiteSeedExtractorService $onsiteSeeds,
        SeedKeywordService $seedKeywords,
        PageAnalyzerService $pageAnalyzer
    ): void {
        $report = Report::findOrFail($this->reportId);

        // Passe en running + init compteurs
        try {
            $report->update([
                'status'          => 'running',
                'requested_count' => count($this->domains),
                'processed_count' => 0,
            ]);
        } catch (\Throwable $e) {
            // si certains champs n'existent pas, on ignore
        }

        $processed = 0;

        // Unwrap: certains de tes services renvoient ['json'=>...], d'autres ['body'=>...]
        $unwrap = function ($resp) {
            return data_get($resp, 'json', data_get($resp, 'body', $resp));
        };

        foreach ($this->domains as $d) {

            // Normalisation domaine
            $domain = strtolower(trim((string) $d));
            $domain = preg_replace('#^https?://#', '', $domain);
            $domain = preg_replace('#^www\.#', '', $domain);
            $domain = trim($domain, "/ \t\n\r\0\x0B");

            if ($domain === '') {
                $processed++;
                try { $report->update(['processed_count' => $processed]); } catch (\Throwable) {}
                continue;
            }

            try {
                /* =========================
                   1) BACKLINKS SUMMARY
                ========================= */
                $dfsSummary = $unwrap($dfs->backlinksSummary($domain));
                $sumTask0   = data_get($dfsSummary, 'tasks.0', []);
                $sumItem0   = data_get($sumTask0, 'result.0', []);

                $dfsOk = ((int) data_get($dfsSummary, 'status_code', 0) === 20000)
                      && ((int) data_get($sumTask0, 'status_code', 0) === 20000);

                if (!$dfsOk) {
                    // Toujours créer un item en erreur
                    ReportItem::updateOrCreate(
                        ['report_id' => $report->id, 'domain' => $domain],
                        [
                            'raw_json' => [
                                'error'     => true,
                                'dataforseo'=> $dfsSummary,
                                'message'   => 'DataForSEO backlinks summary failed',
                            ],
                        ]
                    );

                    Log::warning('DataForSEO summary failed', [
                        'domain'      => $domain,
                        'status_code' => data_get($dfsSummary, 'status_code'),
                        'task_status' => data_get($sumTask0, 'status_code'),
                    ]);

                    $processed++;
                    try { $report->update(['processed_count' => $processed]); } catch (\Throwable) {}
                    continue;
                }

                /* =========================
                   2) TRAFIC / KEYWORDS
                ========================= */
                $traffic = $unwrap($dfs->bulkTrafficEstimation([$domain], 2250, 'fr'));
                $trItem0 = data_get($traffic, 'tasks.0.result.0.items.0', []);

                $organicKeywords = data_get($trItem0, 'metrics.organic.count');
                //$trafficEtv      = data_get($trItem0, 'metrics.organic.etv');
                //$trafficEstimated = data_get($trItem0, 'metrics.organic.etv') ?? data_get($trItem0, 'metrics.organic.traffic');
				
				$trafficEstimated = data_get($trItem0, 'metrics.organic.traffic'); // ✅ visites
				$trafficEtv       = data_get($trItem0, 'metrics.organic.etv');     // ✅ valeur €

                /* =========================
                   3) WHOIS
                ========================= */
                $createdAt = null;
                $ageYears  = null;

                try {
                    $createdAt = $whois->getCreatedAt($domain);
                    if ($createdAt) {
                        $c = Carbon::parse($createdAt);
                        $ageYears = $c->isFuture() ? 0 : round($c->diffInDays(now()) / 365.25, 2);
                    }
                } catch (\Throwable $e) {
                    Log::warning('Whois error', [
                        'domain'  => $domain,
                        'message' => $e->getMessage(),
                    ]);
                }

                /* =========================
                   4) LINKS
                ========================= */
                $backlinksTotal = (int) data_get($sumItem0, 'backlinks', 0);
                $nofollowLinks  = (int) data_get($sumItem0, 'referring_links_attributes.nofollow', 0);
                $dofollowLinks  = $backlinksTotal ? max(0, $backlinksTotal - $nofollowLinks) : null;

                /* =========================
                   5) SAVE ITEM (toujours)
                ========================= */
                $ri = ReportItem::updateOrCreate(
                    ['report_id' => $report->id, 'domain' => $domain],
                    [
                        'linking_domains' => (int) data_get($sumItem0, 'referring_domains', 0) ?: null,
                        'inbound_links'   => $backlinksTotal ?: null,
                        'raw_json'        => [
                            'dataforseo' => $dfsSummary,
                            'traffic'    => $traffic,
                        ],
                    ]
                );

                // ⚠️ IMPORTANT : n’écris QUE des colonnes existantes en DB
                $ri->forceFill([
                    'nofollow_links'    => $nofollowLinks ?: null,
                    'dofollow_links'    => $dofollowLinks,
                    'domain_created_at' => $createdAt,
                    'domain_age_years'  => $ageYears,
                    'organic_keywords'  => $organicKeywords,
                    'traffic_estimated' => $trafficEstimated,
					'traffic_etv'       => $trafficEtv,
                ])->save();

                /* =========================
                   5bis) TOPIC PROFILE
                ========================= */
                if (empty($ri->topic_profile)) {
                    try {
                        $profile = $topicProfile->buildFromHomepage($ri->domain);
                        $ri->topic_profile = $profile;
                        $ri->save();
                    } catch (\Throwable $e) {
                        Log::warning('Topic profile error', [
                            'domain'  => $ri->domain,
                            'message' => $e->getMessage(),
                        ]);
                    }
                }

                /* =========================
                   5ter) SEED KEYWORDS (on-site first)
                ========================= */
                if (empty($ri->seed_keywords)) {
                    try {
                        $seeds = $onsiteSeeds->extract($ri->domain, 15);

                        Log::info('Onsite seeds extracted', [
                            'domain' => $ri->domain,
                            'seeds'  => $seeds,
                        ]);

                        if (empty($seeds)) {
                            $rkResp = $dfs->rankedKeywords($ri->domain, 2250, 'fr', 100);
                            $rkBody = $unwrap($rkResp);

                            Log::info('Ranked keywords fetched', [
                                'domain'      => $ri->domain,
                                'status'      => data_get($rkBody, 'status_code'),
                                'task_status' => data_get($rkBody, 'tasks.0.status_code'),
                                'items_count' => count(data_get($rkBody, 'tasks.0.result.0.items', [])),
                            ]);

                            $ok = ((int) data_get($rkBody, 'status_code') === 20000)
                               && ((int) data_get($rkBody, 'tasks.0.status_code') === 20000);

                            if ($ok) {
                                $seeds = $seedKeywords->buildSeeds(
                                    $rkBody,
                                    $ri->topic_profile ?? [],
                                    15
                                );

                                Log::info('Fallback DataForSEO seeds', [
                                    'domain' => $ri->domain,
                                    'seeds'  => $seeds,
                                ]);

                                $raw = $ri->raw_json ?? [];
                                $raw['ranked_keywords'] = $rkResp;
                                $ri->raw_json = $raw;
                                $ri->save();
                            }
                        }

                        $ri->seed_keywords = array_values(array_unique($seeds));
                        $ri->save();

                    } catch (\Throwable $e) {
                        Log::warning('Seed keywords error', [
                            'domain'  => $ri->domain,
                            'message' => $e->getMessage(),
                        ]);
                    }
                }

                /* =========================
                   5quater) PAGES (V1.5)
                ========================= */
                try {
                    $rkBody = data_get($ri->raw_json, 'ranked_keywords.body')
                        ?? data_get($ri->raw_json, 'ranked_keywords')
                        ?? null;

                    if (empty($rkBody)) {
                        $rkResp = $dfs->rankedKeywords($ri->domain, 2250, 'fr', 50);
                        $rkBody = $unwrap($rkResp);

                        $raw = $ri->raw_json ?? [];
                        $raw['ranked_keywords'] = $rkResp;
                        $ri->raw_json = $raw;
                        $ri->save();
                    }

                    $pageAnalyzer->populateFromRankedKeywords($ri, (array) $rkBody);

                } catch (\Throwable $e) {
                    Log::warning('Pages populate error', [
                        'domain'  => $ri->domain,
                        'message' => $e->getMessage(),
                    ]);
                }

                /* =========================
                   6) IA COPY
                ========================= */
                if (!$ri->ai_generated_at) {
                    try {
                        $aiCopy = $ai->generate([
                            'domain' => $ri->domain,
                            'rd'     => $ri->linking_domains,
                            'bl'     => $ri->inbound_links,
                            'kw'     => $ri->organic_keywords,
                            'etv'    => $ri->traffic_etv,
                        ]);

                        $ri->ai_tooltips     = $aiCopy['tooltips'] ?? [];
                        $ri->ai_diagnosis    = $aiCopy['diagnosis'] ?? [];
                        $ri->ai_details      = $aiCopy['details'] ?? [];
                        $ri->ai_generated_at = now();
                        $ri->save();
                    } catch (\Throwable $e) {
                        Log::warning('OpenAI copy error', [
                            'domain' => $domain,
                            'error'  => $e->getMessage(),
                        ]);
                    }
                }

                /* =========================
                   7) CONTENT PLAN
                ========================= */
                if (!$ri->contentSuggestions()->exists()) {
                    try {
                        $plan = $contentAi->generate([
                            'domain'        => $ri->domain,
                            'seed_keywords' => $ri->seed_keywords ?? [],
                            'topic_profile' => $ri->topic_profile ?? [],
                            'competitors'   => $ri->competitors ?? [],
                            'metrics' => [
                                'rd'  => $ri->linking_domains,
                                'kw'  => $ri->organic_keywords,
                                'etv' => $ri->traffic_etv,
                            ],
                            'market' => ['country' => 'fr', 'language' => 'fr'],
                        ]);

                        foreach (['pages', 'articles'] as $type) {
                            foreach (($plan[$type] ?? []) as $item) {
                                $ri->contentSuggestions()->create(array_merge(
                                    ['type' => rtrim($type, 's')],
                                    $item,
                                    ['ai_generated_at' => now()]
                                ));
                            }
                        }
                    } catch (\Throwable $e) {
                        Log::warning('OpenAI content plan error', [
                            'domain'  => $domain,
                            'item_id' => $ri->id ?? null,
                            'message' => $e->getMessage(),
                            'code'    => $e->getCode(),
                        ]);
                    }
                }

                // ✅ FIN OK domaine
                $processed++;
                try { $report->update(['processed_count' => $processed]); } catch (\Throwable) {}

            } catch (\Throwable $e) {
                // 🔥 Catch GLOBAL domaine
                Log::error('FetchDomainMetricsJob domain error', [
                    'domain'    => $domain,
                    'report_id' => $report->id,
                    'message'   => $e->getMessage(),
                ]);

                try {
                    ReportItem::updateOrCreate(
                        ['report_id' => $report->id, 'domain' => $domain],
                        [
                            'raw_json' => [
                                'error'   => true,
                                'message' => $e->getMessage(),
                            ],
                        ]
                    );
                } catch (\Throwable $e2) {
                    // rien
                }

                $processed++;
                try { $report->update(['processed_count' => $processed]); } catch (\Throwable) {}
                continue;
            }
        }


		try {
			// après avoir traité tous les domaines et créé les ReportItem
			if (!empty($report->user?->gsc_token_json)) {
				\App\Jobs\FetchGscForReportJob::dispatch($report->id, $report->user_id);
				$report->update(['gsc_connected' => true, 'gsc_property' => 'multi']);
			}
		} catch (\Throwable $e) {}


        // ✅ Fin normale
        try {
			$report->update([
				'status' => 'done',
				'processed_count' => $report->items()->count(),
				'completed_at' => now(),
			]);


        } catch (\Throwable $e) {
            // ignore si colonne manquante
            $report->update([
                'status'       => 'done',
                'completed_at' => now(),
            ]);
        }
    }

    public function failed(\Throwable $e): void
    {
        try {
            $report = Report::find($this->reportId);
            if ($report) {
                $report->update([
                    'status' => 'failed',
                ]);
            }
        } catch (\Throwable $e2) {}

        Log::error('FetchDomainMetricsJob failed', [
            'report_id' => $this->reportId,
            'message'   => $e->getMessage(),
        ]);
    }
}

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

        $unwrap = fn ($resp) => data_get($resp, 'body', $resp);

        // compteur "live" (reprend si retry)
        $processed = (int) $report->processed_count;

        foreach ($this->domains as $d) {

            $domain = strtolower(trim((string) $d));
            $domain = preg_replace('#^https?://#', '', $domain);
            $domain = preg_replace('#^www\.#', '', $domain);
            $domain = trim($domain, "/ \t\n\r\0\x0B");

            if ($domain === '') {
                continue;
            }

            // IMPORTANT: check avant updateOrCreate pour éviter surcomptage en retry
            $alreadyExists = ReportItem::where('report_id', $report->id)
                ->where('domain', $domain)
                ->exists();

            /* =========================
               1) BACKLINKS SUMMARY
            ========================= */
            $dfsSummary = $unwrap($dfs->backlinksSummary($domain));
            $sumTask0   = data_get($dfsSummary, 'tasks.0', []);
            $sumItem0   = data_get($sumTask0, 'result.0', []);

			// ✅ Authority/Rank DataForSEO (selon endpoint, ça peut être rank ou domain_rank)
			$dfsRank =
				data_get($sumItem0, 'rank')
				?? data_get($sumItem0, 'domain_rank')
				?? data_get($sumItem0, 'metrics.rank')
				?? null;

			$dfsRank = is_numeric($dfsRank) ? (int) $dfsRank : null;



            if (
                (int) data_get($dfsSummary, 'status_code', 20000) !== 20000 ||
                (int) data_get($sumTask0, 'status_code', 20000) !== 20000
            ) {
                ReportItem::updateOrCreate(
                    ['report_id' => $report->id, 'domain' => $domain],
                    ['raw_json' => ['dataforseo' => $dfsSummary]]
                );

                // live progress même si continue + pas de double count en retry
                if (!$alreadyExists) {
                    $processed++;
                    $report->update(['processed_count' => $processed]);
                }

                continue;
            }

            /* =========================
               2) TRAFIC / KEYWORDS
            ========================= */
            $traffic = $unwrap($dfs->bulkTrafficEstimation([$domain], 2250, 'fr'));
            $trItem0 = data_get($traffic, 'tasks.0.result.0.items.0', []);

            $organicKeywords = data_get($trItem0, 'metrics.organic.count');
            $trafficEtv      = data_get($trItem0, 'metrics.organic.etv');

			// ✅ Estimation visites SEO mensuelles (ETV / CPC moyen)
			$cpc = (float) config('seo.avg_cpc_eur', (float) env('SEO_AVG_CPC_EUR', 1.2));
			$trafficVisits = null;

			if (is_numeric($trafficEtv) && $cpc > 0) {
				$trafficVisits = (int) round(((float) $trafficEtv) / $cpc);
			}


            /* =========================
               3) WHOIS
            ========================= */
            $createdAt = $whois->getCreatedAt($domain);
            $ageYears  = null;

            if ($createdAt) {
                try {
                    $c = Carbon::parse($createdAt);
                    $ageYears = $c->isFuture() ? 0 : round($c->diffInDays(now()) / 365.25, 2);
                } catch (\Throwable) {
                    // ignore parse error
                }
            }

            /* =========================
               4) LINKS
            ========================= */
            $backlinksTotal = (int) data_get($sumItem0, 'backlinks', 0);
            $nofollowLinks  = (int) data_get($sumItem0, 'referring_links_attributes.nofollow', 0);
            $dofollowLinks  = $backlinksTotal ? max(0, $backlinksTotal - $nofollowLinks) : null;

            /* =========================
               5) SAVE ITEM
            ========================= */
			
			// ===== Authority score =====
			$rdScore  = (int) data_get($sumItem0, 'referring_domains', 0);
			$kwScore  = (int) ($organicKeywords ?? 0);
			$etvScore = (float) ($trafficEtv ?? 0);

			$sRD  = ($rdScore > 0)  ? min(100, (log10($rdScore + 1) / log10(6000 + 1)) * 100) : 0;
			$sKW  = ($kwScore > 0)  ? min(100, (log10($kwScore + 1) / log10(5000 + 1)) * 100) : 0;
			$sETV = ($etvScore > 0) ? min(100, (log10($etvScore + 1) / log10(2000 + 1)) * 100) : 0;

			$raw   = ($sRD * 0.55) + ($sKW * 0.25) + ($sETV * 0.20);
			$gamma = 3.4;

			// --- Authority ---
			$authorityInternal = (int) round(100 * pow(($raw / 100), $gamma));

			$authorityFinal = (is_numeric($dfsRank) && (int)$dfsRank > 0)
				? (int) $dfsRank
				: $authorityInternal;

			$authoritySource = (is_numeric($dfsRank) && (int)$dfsRank > 0)
				? 'dataforseo_rank'
				: 'computed';

			// --- Save ---
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

			$ri->forceFill([
				'da'               => $authorityFinal,
				'authority_final'  => $authorityFinal,
				'authority_source' => $authoritySource,

				'nofollow_links'    => $nofollowLinks ?: null,
				'dofollow_links'    => $dofollowLinks,
				'domain_created_at' => $createdAt,
				'domain_age_years'  => $ageYears,

				'organic_keywords'  => $organicKeywords,
				'traffic_etv'       => $trafficEtv,
				'traffic_estimated' => $trafficVisits, // ✅ visites/mois calculées depuis ETV/CPC
			])->save();




            // live progress après un succès (sans double count en retry)
            if (!$alreadyExists) {
                $processed++;
                $report->update(['processed_count' => $processed]);
            }

            // =========================
            // 5bis) TOPIC PROFILE
            // =========================
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

            // =========================
            // 5ter) SEED KEYWORDS (on-site first)
            // =========================
            if (empty($ri->seed_keywords)) {
                try {
                    // 1️⃣ extraction onsite
                    $seeds = $onsiteSeeds->extract($ri->domain, 15);

                    Log::info('Onsite seeds extracted', [
                        'domain' => $ri->domain,
                        'seeds'  => $seeds,
                    ]);

                    // 2️⃣ fallback DataForSEO ranked_keywords si onsite vide
                    if (empty($seeds)) {
                        $rkResp = $dfs->rankedKeywords($ri->domain, 2250, 'fr', 100);

                        Log::info('Ranked keywords fetched', [
                            'domain'       => $ri->domain,
                            'status'       => data_get($rkResp, 'status_code'),
                            'task_status'  => data_get($rkResp, 'tasks.0.status_code'),
                            'items_count'  => count(data_get($rkResp, 'tasks.0.result.0.items', [])),
                            'sample'       => array_slice(
                                array_map(fn ($i) => data_get($i, 'keyword'), data_get($rkResp, 'tasks.0.result.0.items', [])),
                                0,
                                10
                            ),
                        ]);

                        $ok = ((int) data_get($rkResp, 'status_code') === 20000)
                            && ((int) data_get($rkResp, 'tasks.0.status_code') === 20000);

                        if ($ok) {
                            $seeds = $seedKeywords->buildSeeds(
                                $rkResp,
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

            // =========================
            // 5quater) PAGES (V1.5)
            // =========================
            try {
                // 1) On réutilise ranked_keywords si déjà en raw_json
                $rkBody = data_get($ri->raw_json, 'ranked_keywords.body')
                    ?? data_get($ri->raw_json, 'ranked_keywords')
                    ?? null;

                // 2) Sinon on fetch vite fait (limite faible)
                if (empty($rkBody)) {
                    $rkResp = $dfs->rankedKeywords($ri->domain, 2250, 'fr', 50);
                    $rkBody = data_get($rkResp, 'body', $rkResp);

                    // on le garde en raw_json (optionnel mais pratique)
                    $raw = $ri->raw_json ?? [];
                    $raw['ranked_keywords'] = $rkResp;
                    $ri->raw_json = $raw;
                    $ri->save();
                }

                // 3) Peupler report_pages
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
                        'domain'         => $ri->domain,
                        'seed_keywords'   => $ri->seed_keywords ?? [],
                        'topic_profile'   => $ri->topic_profile ?? [],
                        'competitors'     => $ri->competitors ?? [],
                        'metrics'        => [
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
                        'trace'   => $e->getTraceAsString(),
                    ]);
                }
            }
        }

        // Final: on garantit la cohérence sans casser le "live"
        $finalCount = ReportItem::where('report_id', $report->id)->count();

        $report->update([
            'requested_count' => count($this->domains),
            'processed_count' => max($processed, $finalCount),
            'status'          => 'done',
            'completed_at'    => now(),
        ]);
    }
}

<?php

namespace App\Jobs;

use App\Models\Report;
use App\Models\User;
use App\Services\GscService;
use Google\Service\SearchConsole;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class FetchGscForReportJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public int $reportId, public int $userId) {}

    public function handle(GscService $gsc): void
    {
        Log::info('GSC job start', [
		  'report_id' => $this->reportId,
		  'user_id' => $this->userId,
		]);

				
		$report = Report::with('items')->find($this->reportId);
        if (!$report) return;

        $user = User::find($this->userId);
        if (!$user) {
            $report->update(['gsc_connected' => false, 'gsc_property' => null]);
            return;
        }

        // Token en array
        $token = $user->gsc_token_json;
        if (is_string($token)) {
            $token = json_decode($token, true);
        }

        if (empty($token) || empty($token['access_token'])) {
            $report->update(['gsc_connected' => false, 'gsc_property' => null]);
            return;
        }

        // Client + service
        $client = $gsc->makeClient($token);

        // ✅ Refresh si expiré + sauvegarde si nouveau access_token renvoyé
        try {
            if ($client->isAccessTokenExpired() && !empty($token['refresh_token'])) {
                $new = $client->fetchAccessTokenWithRefreshToken($token['refresh_token']);

                if (!empty($new['access_token'])) {
                    $token = array_merge($token, $new);

                    $user->gsc_token_json = $token;
                    $user->gsc_connected = true;
                    $user->save();

                    $client->setAccessToken($token);
                }
            }
        } catch (\Throwable $e) {
            Log::warning('GSC token refresh failed', [
                'user_id' => $user->id,
                'report_id' => $report->id,
                'err' => $e->getMessage(),
            ]);
        }

        $svc = new SearchConsole($client);

        // 1) list sites
        try {
            $siteUrls = $gsc->listSiteUrls($svc);

			Log::info('GSC sites loaded', [
			  'count' => count($siteUrls),
			  'sample' => array_slice($siteUrls, 0, 5),
			]);

            $report->update([
                'gsc_connected' => true,
                'gsc_property'  => 'multi',
            ]);
        } catch (\Throwable $e) {
            Log::warning('GSC listSites failed', [
                'user_id' => $user->id,
                'report_id' => $report->id,
                'err' => $e->getMessage(),
            ]);

            $report->update(['gsc_connected' => false, 'gsc_property' => null]);
            return;
        }

        // 2) per item: resolve + query
        foreach ($report->items as $item) {
            try {
                $siteUrl = $gsc->resolveSiteUrlForDomain($item->domain, $siteUrls);

                if (!$siteUrl) {
                    $item->update([
                        'gsc_clicks_30d' => null,
                        'gsc_impressions_30d' => null,
                        'gsc_position_30d' => null,
                        'gsc_site_url' => null,
                        'gsc_updated_at' => now(),
                    ]);
                    continue;
                }

                $totals = $gsc->fetchTotals30d($svc, $siteUrl);

                $item->update([
                    'gsc_clicks_30d' => $totals['clicks'] ?? 0,
                    'gsc_impressions_30d' => $totals['impressions'] ?? 0,
                    'gsc_position_30d' => $totals['position'] ?? 0,
                    'gsc_site_url' => $siteUrl,
                    'gsc_updated_at' => now(),
                ]);

            } catch (\Throwable $e) {
                Log::warning('GSC fetch failed', [
                    'report_id' => $report->id,
                    'item_id' => $item->id,
                    'domain' => $item->domain,
                    'err' => $e->getMessage(),
                ]);

                $item->update([
                    'gsc_updated_at' => now(),
                ]);
            }
        }
    }
}

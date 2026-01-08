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
        if (!$report) {
            Log::warning('GSC report not found', ['report_id' => $this->reportId]);
            return;
        }

        Log::info('GSC report loaded', [
            'report_id' => $report->id,
            'items_count' => $report->items->count(),
        ]);

        $user = User::find($this->userId);
        if (!$user) {
            $report->update(['gsc_connected' => false, 'gsc_property' => null]);
            Log::warning('GSC user not found', ['user_id' => $this->userId, 'report_id' => $report->id]);
            return;
        }

        // token as array
        $token = $user->gsc_token_json;
        if (is_string($token)) {
            $token = json_decode($token, true);
        }
        if (!is_array($token)) $token = [];

        if (empty($token['access_token'])) {
            $report->update(['gsc_connected' => false, 'gsc_property' => null]);
            Log::warning('GSC missing access_token', ['user_id' => $user->id, 'report_id' => $report->id]);
            return;
        }

        $client = $gsc->makeClient($token);

        // refresh token if expired
        try {
            if ($client->isAccessTokenExpired() && !empty($token['refresh_token'])) {
                $new = $client->fetchAccessTokenWithRefreshToken($token['refresh_token']);

                if (!empty($new['access_token'])) {
                    $token = array_merge($token, $new);
                    $user->gsc_token_json = $token;
                    $user->gsc_connected = true;
                    $user->save();

                    $client->setAccessToken($token);
                    Log::info('GSC token refreshed', ['user_id' => $user->id]);
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

        // list properties
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

            // refresh model to avoid stale in-memory values
            $report->refresh();

        } catch (\Throwable $e) {
            Log::warning('GSC listSites failed', [
                'user_id' => $user->id,
                'report_id' => $report->id,
                'err' => $e->getMessage(),
            ]);

            $report->update(['gsc_connected' => false, 'gsc_property' => null]);
            return;
        }

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

                Log::info('GSC resolve', [
                    'domain' => $item->domain,
                    'resolved' => $siteUrl,
                ]);

                $totals = $gsc->fetchTotals30d($svc, $siteUrl);

                Log::info('GSC totals', [
                    'domain' => $item->domain,
                    'data' => $totals,
                ]);

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

                $item->update(['gsc_updated_at' => now()]);
            }
        }

        Log::info('GSC job finished', ['report_id' => $report->id]);
    }
}

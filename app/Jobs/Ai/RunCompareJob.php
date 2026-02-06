<?php

namespace App\Jobs\Ai;

use App\Models\AiJob;
use App\Models\CompareRun;
use App\Services\Compare\CompareService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class RunCompareJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public int $aiJobId) {}

    public function handle(CompareService $service): void
    {
        $job = AiJob::findOrFail($this->aiJobId);

        $req = (array) data_get($job->result, 'request', []);
        $url  = (string) ($req['url'] ?? $job->url ?? '');
        $locationCode = (int) ($req['location_code'] ?? 2250);
        $languageCode = (string) ($req['language_code'] ?? 'fr');
        $reportItemId = (int) ($req['report_item_id'] ?? 0);

        $job->update([
            'status' => 'running',
            'progress_step' => 1,
            'progress_label' => 'Analyse SERP & concurrents…',
        ]);

        $run = CompareRun::create([
            'report_item_id' => $reportItemId ?: null,
            'client_url' => $url,
            'competitor_url' => '',
            'location_code' => $locationCode,
            'language_code' => $languageCode,
            'status' => 'running',
        ]);

        try {
            request()->merge(['report_item_id' => $reportItemId ?: null]);

            $result = $service->run(
                url: $url,
                locationCode: $locationCode,
                languageCode: $languageCode
            );

            $topics  = (array) ($result['topics'] ?? []);
            $actions = (array) ($result['actions'] ?? []);
            $competitors = (array) ($result['competitors'] ?? []);

            $run->update([
                'status' => 'done',
                'serp_topics_json' => $topics ?: null,
                'serp_intent' => $result['serp_intent'] ?? null,
                'similarity_score' => $result['similarity_score'] ?? null,
                'serp_fit_score' => $result['serp_fit_score'] ?? null,
                'diff_json' => $result['diff'] ?? null,
                'actions_json' => $actions ?: null,
                'client_snapshot_json' => $result['input'] ?? null,
                'competitor_snapshot_json' => $competitors ?: null,
            ]);

            $job->update([
                'status' => 'done',
                'progress_step' => 2,
                'progress_label' => 'Terminé',
                'result' => [
                    'compare_run_id' => $run->id,
                    'summary' => [
                        'topics_count' => count($topics),
                        'actions_count' => count($actions),
                        'competitors_count' => count($competitors),
                    ],
                ],
            ]);
        } catch (\Throwable $e) {
            $run->update([
                'status' => 'failed',
                'error_message' => $e->getMessage(),
            ]);

            $job->update([
                'status' => 'failed',
                'error_message' => $e->getMessage(),
                'progress_label' => 'Erreur',
            ]);

            throw $e;
        }
    }
}

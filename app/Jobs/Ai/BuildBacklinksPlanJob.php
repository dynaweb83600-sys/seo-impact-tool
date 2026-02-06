<?php

namespace App\Jobs\Ai;

use App\Models\AiJob;
use App\Models\BacklinksPlan;
use App\Models\ReportItem;
use App\Services\Content\OpenAIResponsesClient;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class BuildBacklinksPlanJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public int $aiJobId, public int $reportItemId) {}

    public function handle(OpenAIResponsesClient $openai): void
    {
        $job = AiJob::findOrFail($this->aiJobId);
        $item = ReportItem::findOrFail($this->reportItemId);

        $job->update([
            'status' => 'running',
            'progress_step' => 1,
            'progress_label' => 'Calcul des objectifs…',
        ]);

        $domain = (string) ($item->domain ?? '');
        $rdCurrent = (int) ($item->linking_domains ?? 0);

        $authority = data_get($job->result, 'authority');
        if ($authority === null) {
            $authority = (float) ($item->authority_final ?? 0);
        }

        $rdTarget3m = max($rdCurrent + 10, (int) round($rdCurrent * 1.2));

        $plan = BacklinksPlan::create([
            'report_item_id' => $item->id,
            'domain' => $domain,
            'status' => 'draft',
            'rd_current' => $rdCurrent,
            'rd_target_3m' => $rdTarget3m,
            'rd_gap' => max(0, $rdTarget3m - $rdCurrent),
        ]);

        $job->update([
            'progress_step' => 2,
            'progress_label' => 'Génération du plan…',
        ]);

        $prompt = <<<PROMPT
Tu es un expert netlinking.
Je veux un PLAN STRUCTURÉ en JSON (pas de texte).

Contexte:
- Domaine: {$domain}
- Autorité: {$authority}
- RD actuels: {$rdCurrent}
- Objectif 3 mois: {$rdTarget3m}
- Site: e-commerce

Rends EXACTEMENT ce JSON:
{
  "monthly_plan": {"m1": <int>, "m2": <int>, "m3": <int>},
  "anchor_mix": {"brand_or_url": <int>, "semi_optimized": <int>, "exact": <int>},
  "targets": [
    {"url": "<string>", "type": "category|product|pillar|home", "rd": <int>, "reason": "<string>"}
  ],
  "link_mix": [
    {"type":"guest_post|niche_blog|partner|directory|pr","share":<int>,"notes":"<string>"}
  ],
  "footprints": [
    {"goal":"<string>","queries":["<string>","<string>","<string>"]}
  ]
}

Contraintes:
- Les parts (anchor_mix) doivent faire 100.
- monthly_plan m1+m2+m3 = rd_gap (ou très proche).
- Targets: 5 items max.
PROMPT;

        $json = $openai->generateJson($prompt);
        if (!$json) {
            $raw = $openai->generateHtml($prompt);
            $raw = preg_replace('/```(json)?|```/i', '', $raw);
            $json = json_decode(trim($raw), true);
        }

        if (!is_array($json)) {
            throw new \RuntimeException("Backlinks plan: invalid JSON from AI");
        }

        $plan->update([
            'status' => 'generated',
            'monthly_plan_json' => $json['monthly_plan'] ?? null,
            'anchor_mix_json' => $json['anchor_mix'] ?? null,
            'targets_json' => $json['targets'] ?? null,
            'link_mix_json' => $json['link_mix'] ?? null,
            'footprints_json' => $json['footprints'] ?? null,
        ]);

        $job->update([
            'status' => 'done',
            'progress_step' => 3,
            'progress_label' => 'Terminé',
            'result' => [
                'backlinks_plan_id' => $plan->id,
            ],
        ]);
    }

    public function failed(\Throwable $e): void
    {
        AiJob::whereKey($this->aiJobId)->update([
            'status' => 'failed',
            'error_message' => $e->getMessage(),
            'progress_label' => 'Erreur',
        ]);
    }
}

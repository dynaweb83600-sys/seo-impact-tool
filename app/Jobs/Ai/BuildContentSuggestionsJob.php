<?php

namespace App\Jobs\Ai;

use App\Models\AiJob;
use App\Models\ContentSuggestion;
use App\Models\ReportItem;
use App\Services\Content\OpenAIResponsesClient;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class BuildContentSuggestionsJob implements ShouldQueue
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
            'progress_label' => 'Analyse du domaine…',
        ]);

        $domain = (string) ($item->domain ?? '');
        $competitors = (array) ($item->competitors ?? []);
        
        $seedKeywords = $this->extractSeedKeywords($item);

        $job->update([
            'progress_step' => 2,
            'progress_label' => 'Génération des suggestions…',
        ]);

        $prompt = $this->buildPrompt($domain, $seedKeywords, $competitors);
        $rawResponse = $openai->generateHtml($prompt);
        
        $suggestions = $this->parseAiResponse($rawResponse);

        $job->update([
            'progress_step' => 3,
            'progress_label' => 'Enregistrement…',
        ]);

        $createdIds = [];
        $existingHashes = ContentSuggestion::where('report_item_id', $item->id)
            ->pluck('similarity_hash')
            ->filter()
            ->toArray();

        foreach ($suggestions as $sug) {
            $hash = ContentSuggestion::calculateSimilarityHash(
                $sug['suggested_title'] ?? '',
                $sug['secondary_keywords'] ?? []
            );

            $dedupDecision = in_array($hash, $existingHashes) ? 'blocked' : 'ok';
            if ($dedupDecision === 'blocked') {
                continue;
            }

            $created = ContentSuggestion::create([
                'report_item_id' => $item->id,
                'domain' => $domain,
                'content_type' => $sug['content_type'] ?? 'page',
                'format_variant' => $sug['format_variant'] ?? null,
                'angle_note' => $sug['angle_note'] ?? null,
                'intent' => $sug['intent'] ?? null,
                'serp_intent' => $sug['serp_intent'] ?? null,
                'priority_score' => $sug['priority_score'] ?? 50,
                'priority_label' => $sug['priority_label'] ?? 'Moyenne',
                'primary_keyword' => $sug['primary_keyword'] ?? null,
                'secondary_keywords_json' => $sug['secondary_keywords'] ?? [],
                'entities_json' => $sug['entities'] ?? [],
                'suggested_title' => $sug['suggested_title'] ?? null,
                'suggested_slug' => $sug['suggested_slug'] ?? null,
                'outline_h2' => $sug['outline_h2'] ?? [],
                'outline_h3' => $sug['outline_h3'] ?? [],
                'must_have_json' => $sug['must_have'] ?? [],
                'material_stats_json' => $sug['material_stats'] ?? [],
                'similarity_hash' => $hash,
                'dedup_decision' => $dedupDecision,
                'generation_status' => 'draft',
            ]);

            $createdIds[] = $created->id;
            $existingHashes[] = $hash;
        }

        $job->update([
            'status' => 'done',
            'progress_step' => 4,
            'progress_label' => 'Terminé',
            'result' => [
                'content_suggestions_count' => count($createdIds),
                'content_suggestions_ids' => $createdIds,
            ],
        ]);
    }

    private function extractSeedKeywords(ReportItem $item): array
    {
        $keywords = [];
        
        if (method_exists($item, 'gscData')) {
            $gscData = $item->gscData()->limit(20)->get();
            foreach ($gscData as $row) {
                $keywords[] = $row->query ?? '';
            }
        }
        
        return array_filter($keywords);
    }

    private function buildPrompt(string $domain, array $keywords, array $competitors): string
    {
        $kwList = implode(', ', array_slice($keywords, 0, 15));
        $compList = implode(', ', array_slice(array_map(function($c) {
            return is_array($c) ? ($c['host'] ?? '') : $c;
        }, $competitors), 0, 5));

        return <<<PROMPT
Tu es un expert SEO content strategist.

Domaine: {$domain}
Keywords seed: {$kwList}
Competitors: {$compList}

Génère 8 suggestions de contenu (mix pages piliers + articles) en JSON:
[
  {
    "content_type": "page|article",
    "format_variant": "guide|comparatif|transactionnel|checklist|faq",
    "angle_note": "angle différenciant court",
    "intent": "info|transactionnel|mixte",
    "serp_intent": "info|transactionnel|mixte",
    "priority_score": 0-100,
    "priority_label": "Haute|Moyenne|Basse",
    "primary_keyword": "mot-clé principal",
    "secondary_keywords": ["kw1","kw2"],
    "entities": ["entity1","entity2"],
    "suggested_title": "Titre SEO optimisé",
    "suggested_slug": "slug-url",
    "outline_h2": ["H2 1","H2 2","H2 3"],
    "outline_h3": [],
    "must_have": ["1 tableau comparatif","10 FAQ"],
    "material_stats": {"table_count":1,"faq_count":10}
  }
]

Contraintes:
- Diversifie les formats (guide, comparatif, transactionnel, etc.)
- Priority score basé sur volume + difficulté + intent
- Must_have = éléments concrets anti-générique
PROMPT;
    }

    private function parseAiResponse(string $raw): array
    {
        $raw = preg_replace('/```(json)?|```/i', '', $raw);
        $raw = trim($raw);
        
        $decoded = json_decode($raw, true);
        
        if (!is_array($decoded)) {
            return [
                [
                    'content_type' => 'page',
                    'suggested_title' => 'Page pilier générée',
                    'priority_score' => 70,
                    'priority_label' => 'Haute',
                    'outline_h2' => ['Introduction', 'Section 1', 'Conclusion'],
                ],
                [
                    'content_type' => 'article',
                    'suggested_title' => 'Article de blog généré',
                    'priority_score' => 60,
                    'priority_label' => 'Moyenne',
                    'outline_h2' => ['Introduction', 'Corps', 'Conclusion'],
                ],
            ];
        }
        
        return $decoded;
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

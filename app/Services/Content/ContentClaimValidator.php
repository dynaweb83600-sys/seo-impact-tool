<?php

namespace App\Services\Content;

use App\Models\ClaimValidation;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;

class ContentClaimValidator
{
    public function __construct(
        private OpenAIResponsesClient $openai, // on le crée juste après
    ) {}

    /**
     * Valide un titre (et éventuellement un brief) + renvoie un verdict.
     * Cache DB automatique.
     */
    public function validateSuggestion(array $suggestion): array
    {
        $title = trim((string)($suggestion['suggested_title'] ?? ''));
        $primaryKeyword = trim((string)($suggestion['primary_keyword'] ?? ''));
        $intent = trim((string)($suggestion['intent'] ?? ''));

        // Extraction heuristique "soft" (pas des règles métier), juste pour cacher intelligemment.
        [$product, $claim] = $this->extractProductAndClaim($title, $primaryKeyword);

        $cacheKey = $this->makeCacheKey($product, $claim, $intent);

        // 1) Cache hit ?
        $cached = ClaimValidation::where('cache_key', $cacheKey)->first();
        if ($cached) {
            return [
                'ok' => true,
                'from_cache' => true,
                'product' => $product,
                'claim' => $claim,
                'verdict' => [
                    'is_factually_correct'   => $cached->is_factually_correct,
                    'is_trivial_or_inherent' => $cached->is_trivial_or_inherent,
                    'is_good_topic'          => $cached->is_good_topic,
                    'reason'                 => $cached->reason,
                    'replacement_titles'     => $cached->replacement_titles ?? [],
                ],
            ];
        }

        // 2) Appel OpenAI validator
        $payload = $this->buildValidatorPayload($title, $primaryKeyword, $intent, $product, $claim);

        $json = $this->openai->validateClaims($payload);

        // Sécurité: JSON strict attendu
        $verdict = [
            'is_factually_correct'   => $json['is_factually_correct'] ?? null,
            'is_trivial_or_inherent' => $json['is_trivial_or_inherent'] ?? null,
            'is_good_topic'          => $json['is_good_topic'] ?? null,
            'reason'                 => $json['reason'] ?? null,
            'replacement_titles'     => $json['replacement_titles'] ?? [],
            'detected_claims'        => $json['detected_claims'] ?? [],
        ];

        ClaimValidation::create([
            'product' => $product,
            'claim' => $claim,
            'category' => null,
            'cache_key' => $cacheKey,
            'is_factually_correct' => $verdict['is_factually_correct'],
            'is_trivial_or_inherent' => $verdict['is_trivial_or_inherent'],
            'is_good_topic' => $verdict['is_good_topic'],
            'reason' => $verdict['reason'],
            'replacement_titles' => $verdict['replacement_titles'],
            'raw_validator_json' => $json,
        ]);

        return [
            'ok' => true,
            'from_cache' => false,
            'product' => $product,
            'claim' => $claim,
            'verdict' => $verdict,
        ];
    }

    /**
     * Heuristique légère (PAS de vérité métier)
     * - cherche des patterns de claim ("sans X", "0% X", "anti X", "riche en X", etc.)
     * - produit : si primary keyword contient un nom simple en tête, sinon null
     */
    private function extractProductAndClaim(string $title, string $primaryKeyword): array
    {
        $t = mb_strtolower($title);
        $k = mb_strtolower($primaryKeyword);

        $claim = null;

        // capture claims fréquents, sans hardcoder "gluten/miel"
        $patterns = [
            '/\bsans\s+([a-zàâäéèêëîïôöùûüç0-9\- ]{3,30})\b/u',    // "sans sucre", "sans gluten"
            '/\b0%\s*([a-zàâäéèêëîïôöùûüç0-9\- ]{3,30})\b/u',      // "0% sucre"
            '/\banti[\-\s]?([a-zàâäéèêëîïôöùûüç0-9\- ]{3,30})\b/u', // "anti-inflammatoire"
            '/\briche\s+en\s+([a-zàâäéèêëîïôöùûüç0-9\- ]{3,30})\b/u',
            '/\bfaible\s+en\s+([a-zàâäéèêëîïôöùûüç0-9\- ]{3,30})\b/u',
        ];

        foreach ($patterns as $p) {
            if (preg_match($p, $t, $m)) {
                $claim = trim($m[0]); // on garde la forme complète "sans gluten"
                break;
            }
        }

        // Produit: on tente sur primary_keyword (ex "miel gingembre", "propolis")
        $product = null;
        if ($k) {
            // prend le premier mot "fort" (>=3 chars)
            $parts = preg_split('/\s+/u', trim($k));
            foreach ($parts as $w) {
                $w = trim($w);
                if (mb_strlen($w) >= 3) {
                    $product = $w;
                    break;
                }
            }
        }

        return [$product, $claim];
    }

    private function makeCacheKey(?string $product, ?string $claim, string $intent): string
    {
        $base = json_encode([
            'product' => $product ? Str::limit($product, 64, '') : null,
            'claim' => $claim ? Str::limit($claim, 64, '') : null,
            'intent' => Str::limit($intent, 32, ''),
        ], JSON_UNESCAPED_UNICODE);

        return hash('sha256', $base ?: '');
    }

    private function buildValidatorPayload(string $title, string $primaryKeyword, string $intent, ?string $product, ?string $claim): array
    {
        return [
            'title' => $title,
            'primary_keyword' => $primaryKeyword,
            'intent' => $intent,
            'product_hint' => $product,
            'claim_hint' => $claim,
        ];
    }
}

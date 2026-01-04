<?php

namespace App\Services\Content;

use GuzzleHttp\Client;
use Illuminate\Support\Facades\Log;

class OpenAIResponsesClient
{
    public function __construct(private Client $http) {}

    public function validateClaims(array $payload): array
    {
        $apiKey = env('OPENAI_API_KEY');
        if (!$apiKey) {
            throw new \RuntimeException('OPENAI_API_KEY manquant dans .env');
        }

        $system = <<<SYS
Tu es un validateur strict de sujets SEO.
But: empêcher les sujets qui sont factuellement faux, trompeurs, ou "trop triviaux" (affirmations évidentes/intrinsèques).
Tu réponds UNIQUEMENT en JSON strict, sans texte autour.
SYS;

        $user = [
            'title' => $payload['title'] ?? '',
            'primary_keyword' => $payload['primary_keyword'] ?? '',
            'intent' => $payload['intent'] ?? '',
            'product_hint' => $payload['product_hint'] ?? null,
            'claim_hint' => $payload['claim_hint'] ?? null,
        ];

        $schema = [
            'type' => 'object',
            'additionalProperties' => false,
            'properties' => [
                'detected_claims' => [
                    'type' => 'array',
                    'items' => [
                        'type' => 'object',
                        'additionalProperties' => false,
                        'properties' => [
                            'claim_text' => ['type' => 'string'],
                            'claim_type' => ['type' => 'string'],
                        ],
                        'required' => ['claim_text','claim_type'],
                    ]
                ],
                'is_factually_correct' => ['type' => ['boolean','null']],
                'is_trivial_or_inherent' => ['type' => ['boolean','null']],
                'is_good_topic' => ['type' => ['boolean','null']],
                'reason' => ['type' => ['string','null']],
                'replacement_titles' => [
                    'type' => 'array',
                    'items' => ['type' => 'string'],
                ],
            ],
            'required' => [
                'detected_claims',
                'is_factually_correct',
                'is_trivial_or_inherent',
                'is_good_topic',
                'reason',
                'replacement_titles'
            ],
        ];

        $body = [
            'model' => env('OPENAI_VALIDATOR_MODEL', 'gpt-4o-mini'),
            'input' => [
                ['role' => 'system', 'content' => $system],
                ['role' => 'user', 'content' => json_encode($user, JSON_UNESCAPED_UNICODE)],
            ],
            'text' => [
                'format' => [
                    'type' => 'json_schema',
                    'name' => 'seo_topic_validator',
                    'schema' => $schema,
                    'strict' => true,
                ]
            ],
            'temperature' => 0.2,
        ];

        $data = $this->postResponses($body, $apiKey);

        $text = $data['output'][0]['content'][0]['text'] ?? null;
        if (!$text) {
            Log::warning('OpenAI validator: no text', ['data' => $data]);
            throw new \RuntimeException('Réponse OpenAI invalide (pas de texte).');
        }

        $json = json_decode($text, true);
        if (!is_array($json)) {
            Log::warning('OpenAI validator: non-json', ['text' => $text]);
            throw new \RuntimeException('Réponse validator non JSON.');
        }

        return $json;
    }

    public function generateText(string $prompt, string $model = 'gpt-4.1-mini'): string
    {
        $apiKey = env('OPENAI_API_KEY');
        if (!$apiKey) {
            throw new \RuntimeException('OPENAI_API_KEY manquant dans .env');
        }

        $body = [
            'model' => $model,
            'input' => $prompt,
            'temperature' => 0.4,
        ];

        $data = $this->postResponses($body, $apiKey);

        // Responses API : le texte est dans output[0].content[0].text
        $text = $data['output'][0]['content'][0]['text'] ?? '';

        // fallback si jamais
        if (!$text && isset($data['output_text'])) {
            $text = (string) $data['output_text'];
        }

        return (string) $text;
    }

    public function generateHtml(string $prompt, string $model = 'gpt-4.1-mini'): string
    {
        $text = $this->generateText($prompt, $model);
        return $this->sanitizeHtml($text);
    }

    private function postResponses(array $body, string $apiKey): array
    {
        $res = $this->http->post('https://api.openai.com/v1/responses', [
            'headers' => [
                'Authorization' => "Bearer {$apiKey}",
                'Content-Type'  => 'application/json',
            ],
            'timeout' => 25,
            'connect_timeout' => 10,
            'json' => $body,
        ]);

        $data = json_decode((string) $res->getBody(), true);

        if (!is_array($data)) {
            throw new \RuntimeException('Réponse OpenAI illisible (JSON invalide).');
        }

        return $data;
    }

    private function sanitizeHtml(string $html): string
    {
        $allowed = '<h1><h2><h3><p><ul><ol><li><strong><b><em><i><br><a>';
        $clean = strip_tags($html, $allowed);
        $clean = preg_replace('~href\s*=\s*([\'"])\s*javascript:.*?\1~i', 'href="#"', $clean);
        return trim($clean);
    }
}

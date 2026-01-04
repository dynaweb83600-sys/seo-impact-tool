<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class OpenAiSeoContentPlanService
{
    public function generate(array $payload): array
    {
        $apiKey = config('services.openai.key');
        $model  = config('services.openai.model', 'gpt-4.1-mini');

        if (!$apiKey) {
            throw new \RuntimeException("OPENAI_API_KEY manquante");
        }

        $schema = [
            "type" => "object",
            "properties" => [
                "pages" => [
                    "type" => "array",
                    "items" => [
                        "type" => "object",
                        "properties" => [
                            "priority_score" => ["type" => "integer"],
                            "priority_label" => ["type" => "string"],
                            "intent" => ["type" => "string"],
                            "primary_keyword" => ["type" => "string"],
                            "secondary_keywords" => ["type" => "array", "items" => ["type" => "string"]],
                            "suggested_title" => ["type" => "string"],
                            "suggested_slug" => ["type" => "string"],
                            "target_url_hint" => ["type" => "string"],
                            "outline_h2" => ["type" => "array", "items" => ["type" => "string"]],
                            "questions_faq" => ["type" => "array", "items" => ["type" => "string"]],
                            "internal_links_to" => ["type" => "array", "items" => ["type" => "string"]],
                            "estimated_etv_gain" => ["type" => "number"],
                            "difficulty_hint" => ["type" => "string"],
                            "why" => ["type" => "string"],
                            "proof" => [
                                "type" => "object",
                                "properties" => [
                                    "competitors" => ["type" => "array", "items" => ["type" => "string"]],
                                    "missing_keywords" => ["type" => "array", "items" => ["type" => "string"]],
                                ],
                                "required" => ["competitors", "missing_keywords"],
                                "additionalProperties" => false
                            ],
                        ],
                        "required" => ["priority_score","priority_label","intent","primary_keyword","secondary_keywords","suggested_title","suggested_slug","target_url_hint","outline_h2","questions_faq","internal_links_to","estimated_etv_gain","difficulty_hint","why","proof"],
                        "additionalProperties" => false
                    ]
                ],
                "articles" => [
                    "type" => "array",
                    "items" => [
                        "type" => "object",
                        "properties" => [
                            "priority_score" => ["type" => "integer"],
                            "priority_label" => ["type" => "string"],
                            "intent" => ["type" => "string"],
                            "primary_keyword" => ["type" => "string"],
                            "secondary_keywords" => ["type" => "array", "items" => ["type" => "string"]],
                            "suggested_title" => ["type" => "string"],
                            "suggested_slug" => ["type" => "string"],
                            "target_url_hint" => ["type" => "string"],
                            "outline_h2" => ["type" => "array", "items" => ["type" => "string"]],
                            "questions_faq" => ["type" => "array", "items" => ["type" => "string"]],
                            "internal_links_to" => ["type" => "array", "items" => ["type" => "string"]],
                            "estimated_etv_gain" => ["type" => "number"],
                            "difficulty_hint" => ["type" => "string"],
                            "why" => ["type" => "string"],
                            "proof" => [
                                "type" => "object",
                                "properties" => [
                                    "competitors" => ["type" => "array", "items" => ["type" => "string"]],
                                    "missing_keywords" => ["type" => "array", "items" => ["type" => "string"]],
                                ],
                                "required" => ["competitors", "missing_keywords"],
                                "additionalProperties" => false
                            ],
                        ],
                        "required" => ["priority_score","priority_label","intent","primary_keyword","secondary_keywords","suggested_title","suggested_slug","target_url_hint","outline_h2","questions_faq","internal_links_to","estimated_etv_gain","difficulty_hint","why","proof"],
                        "additionalProperties" => false
                    ]
                ],
                "summary" => [
                    "type" => "object",
                    "properties" => [
                        "top_priority" => ["type" => "string"],
                        "sequence" => ["type" => "array", "items" => ["type" => "string"]],
                        "notes" => ["type" => "string"],
                    ],
                    "required" => ["top_priority","sequence","notes"],
                    "additionalProperties" => false
                ],
            ],
            "required" => ["pages","articles","summary"],
            "additionalProperties" => false
        ];

        $prompt = [
            [
                "role" => "system",
                "content" =>
                    "Tu es un assistant SEO orienté décision. Écris en FRANÇAIS, simple, actionnable.\n"
					."Objectif: proposer un plan de contenu (PAGES conversion + ARTICLES support) PRIORISÉ.\n"
					."Règles STRICTES:\n"
					."1) Tu dois te baser PRIORITAIREMENT sur seed_keywords et topic_profile.tokens. Tu ne proposes QUE des sujets cohérents avec ces signaux.\n"
					."2) Interdiction d'inventer une autre thématique (ex: CBD/casino/adult) si elle n'est pas présente dans seed_keywords/topic_profile.\n"
					."3) Si les signaux sont trop faibles ou ambigus, tu proposes un plan générique 'à valider' (ex: catégories produits + guides d'achat), sans mentionner de sujets sensibles.\n"
					."4) Pour proof.missing_keywords: si tu n'as pas de keyword gaps réels fournis dans le payload, mets une liste courte dérivée des seed_keywords (variantes proches), sans inventer de thématique.\n"
					."5) Zéro blabla, uniquement du concret."
            ],
            [
                "role" => "user",
                "content" =>
                    "Génère un plan de contenu (pages + articles) à partir de ce JSON:\n" .
                    json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) .
                    "\nRetourne UNIQUEMENT du JSON conforme au schéma."
            ]
        ];

        $req = [
            "model" => $model,
            "input" => $prompt,
            "text" => [
                "format" => [
                    "type" => "json_schema",
                    "name" => "seo_content_plan",
                    "strict" => true,
                    "schema" => $schema
                ]
            ]
        ];

        $res = Http::withToken($apiKey)->acceptJson()->post("https://api.openai.com/v1/responses", $req);

        if (!$res->ok()) {
            throw new \RuntimeException("OpenAI error: " . $res->body());
        }

        $data = $res->json();
        $jsonText = $data['output_text'] ?? data_get($data, 'output.0.content.0.text', '{}');

        return json_decode($jsonText, true) ?: [];
    }
}

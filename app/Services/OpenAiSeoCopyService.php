<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class OpenAiSeoCopyService
{
    public function generate(array $row): array
    {
		$apiKey = config('services.openai.key');
		$model  = config('services.openai.model', 'gpt-4.1-mini');

        if (!$apiKey) {
            throw new \RuntimeException("OPENAI_API_KEY manquante");
        }

        $schema = [
            "type" => "object",
            "properties" => [
                "tooltips" => [
                    "type" => "object",
                    "properties" => [
                        "authority" => ["type"=>"string"],
                        "rd" => ["type"=>"string"],
                        "bl" => ["type"=>"string"],
                        "kw" => ["type"=>"string"],
                        "etv" => ["type"=>"string"],
                        "age" => ["type"=>"string"],
                        "dofpct" => ["type"=>"string"],
                        "net30" => ["type"=>"string"],
                        "backlinks_quality" => ["type"=>"string"]
                    ],
                    "required" => ["authority","rd","bl","kw","etv","age","dofpct","net30","backlinks_quality"],
                    "additionalProperties" => false
                ],
                "diagnosis" => [
                    "type" => "array",
                    "items" => [
                        "type" => "object",
                        "properties" => [
                            "message" => ["type"=>"string"],
                            "action"  => ["type"=>"string"]
                        ],
                        "required" => ["message","action"],
                        "additionalProperties" => false
                    ]
                ],
                "details" => [
                    "type" => "array",
                    "items" => [
                        "type" => "object",
                        "properties" => [
                            "title" => ["type"=>"string"],
                            "why"   => ["type"=>"string"],
                            "recommendation" => [
                                "type"=>"array",
                                "items"=>["type"=>"string"]
                            ]
                        ],
                        "required" => ["title","why","recommendation"],
                        "additionalProperties" => false
                    ]
                ]
            ],
            "required" => ["tooltips","diagnosis","details"],
            "additionalProperties" => false
        ];

        $payload = [
            "model" => $model,
            "input" => [
                [
                    "role" => "system",
                    "content" => "Tu es un assistant SEO. Écris en FRANÇAIS, simple, pédagogique, sans jargon. Réponses courtes et actionnables."
                ],
                [
                    "role" => "user",
                    "content" => "Génère les textes pour un outil d'analyse SEO à partir des métriques suivantes:\n"
                        . json_encode($row, JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT)
                        . "\nRetourne UNIQUEMENT du JSON conforme au schéma."
                ]
            ],
            // ✅ Nouveau format Structured Outputs dans Responses API
            "text" => [
                "format" => [
                    "type" => "json_schema",
                    "name" => "seo_copy",
                    "strict" => true,
                    "schema" => $schema,
                ],
            ],
        ];


		//dd($payload);
        $res = Http::withToken($apiKey)
            ->acceptJson()
            ->post("https://api.openai.com/v1/responses", $payload);

        if (!$res->ok()) {
            throw new \RuntimeException("OpenAI error: ".$res->body());
        }

        $data = $res->json();

        $jsonText = $data['output_text'] ?? null;
        if (!$jsonText) {
            $jsonText = data_get($data, 'output.0.content.0.text', '{}');
        }

        return json_decode($jsonText, true) ?: [];
    }
}

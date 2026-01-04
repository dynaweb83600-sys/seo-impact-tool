<?php
// app/Services/Seo/GenerateSeoContentService.php
namespace App\Services\Seo;

use App\Models\ReportItemContentSuggestion;
use Illuminate\Support\Facades\Http;

class GenerateSeoContentService
{
    public function generateFromSuggestion(ReportItemContentSuggestion $s): string
    {
        $apiKey = config('services.openai.key');
        $model  = config('services.openai.model', 'gpt-4.1-mini');

        if (!$apiKey) {
            throw new \RuntimeException("OPENAI_API_KEY manquante");
        }

        $type = $s->type; // 'page' ou 'article'

        // Payload “verrouillé” = pas d’invention
        $spec = [
            'type' => $type,
            'primary_keyword' => $s->primary_keyword,
            'secondary_keywords' => $s->secondary_keywords ?? [],
            'suggested_title' => $s->suggested_title,
            'suggested_slug' => $s->suggested_slug,
            'target_url_hint' => $s->target_url_hint,
            'outline_h2' => $s->outline_h2 ?? [],
            'questions_faq' => $s->questions_faq ?? [],
            'internal_links_to' => $s->internal_links_to ?? [],
            'tone' => 'FR, clair, vendeur mais pas bullshit',
        ];

        $prompt = [
            [
                "role" => "system",
                "content" =>
                    "Tu es un rédacteur SEO expert.\n"
                    ."Tu écris en FRANÇAIS.\n"
                    ."Tu dois produire un contenu FINAL en HTML.\n"
                    ."IMPORTANT: tu suis STRICTEMENT le plan fourni (title/keyword/outline)."
            ],
            [
                "role" => "user",
                "content" =>
                    "Génère le contenu HTML complet pour ce brief:\n"
                    .json_encode($spec, JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT)
                    ."\nContraintes:\n"
                    ."- Retourne uniquement du HTML (pas de JSON).\n"
                    ."- Structure: <h1> puis sections <h2>, paragraphes, listes si utile.\n"
                    ."- Inclure une section FAQ (questions_faq) en bas.\n"
                    ."- Ajouter des liens internes (internal_links_to) sous forme d'ancres (href='#').\n"
                    ."- Longueur: page=900-1400 mots, article=1200-1800 mots.\n"
            ],
        ];

        $req = [
            "model" => $model,
            "input" => $prompt,
        ];

        $res = Http::withToken($apiKey)
            ->acceptJson()
            ->post("https://api.openai.com/v1/responses", $req);

        if (!$res->ok()) {
            throw new \RuntimeException("OpenAI error: " . $res->body());
        }

        $data = $res->json();
        return (string)($data['output_text'] ?? data_get($data, 'output.0.content.0.text', ''));
    }
}

<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ReportItem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class ReportItemController extends Controller
{
    public function contentSuggestions(Request $request, ReportItem $item)
    {
        // ✅ même sécurité token que tes reports
        $token = $request->query('token');
        if (!$token || $token !== $item->report->access_token) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        try {
			$cached = $item->content_suggestions;

			$hasCache =
			  is_array($cached)
			  && !empty($cached['pages'])
			  && !empty($cached['articles']);

			if ($hasCache) {
			  return response()->json($cached);
			}

            // 2) génération IA
            $plan = app(\App\Services\ContentPlanService::class)->generate($item);

            // validation minimale
			if (
			  !is_array($plan)
			  || empty($plan['pages'])
			  || empty($plan['articles'])
			) {
			  $plan = $this->fallbackSuggestions($item);
			  $plan['warning'] = 'Plan IA vide → fallback utilisé.';
			}

            $item->content_suggestions = $plan;
            $item->save();

            return response()->json($plan);

        } catch (\Throwable $e) {

            Log::warning('OpenAI content plan errorrr2', [
                'domain'  => $item->domain,
                'item_id' => $item->id,
                'message' => $e->getMessage(),
                'code'    => $e->getCode(),
            ]);

            // ✅ fallback au lieu de renvoyer vide
            $fallback = $this->fallbackSuggestions($item);

            return response()->json([
                'pages' => $fallback['pages'],
                'articles' => $fallback['articles'],
                'warning' => 'Plan IA indisponible, suggestions générées en mode fallback.',
            ], 200);
        }
    }

    private function fallbackSuggestions(ReportItem $item): array
    {
        $domain = $item->domain;

        return [
            'pages' => [
                [
                    'id' => 'p1',
                    'priority_label' => 'Haute',
                    'intent' => 'commerciale',
                    'priority_score' => 85,
                    'suggested_title' => "Accueil — Présentation & promesse ($domain)",
                    'primary_keyword' => 'marque + bénéfices',
                    'outline_h2' => ['Bénéfices', 'Preuves', 'Produits/Services', 'FAQ', 'Contact'],
                ],
                [
                    'id' => 'p2',
                    'priority_label' => 'Haute',
                    'intent' => 'transactionnelle',
                    'priority_score' => 80,
                    'suggested_title' => "Page pilier — Guide complet (mot-clé principal)",
                    'primary_keyword' => 'mot-clé principal',
                    'outline_h2' => ['Définition', 'Bienfaits', 'Comment choisir', 'Erreurs', 'FAQ'],
                ],
            ],
            'articles' => [
                [
                    'id' => 'a1',
                    'priority_label' => 'Moyenne',
                    'intent' => 'informationnelle',
                    'priority_score' => 65,
                    'suggested_title' => "Top 10 questions sur [thématique] (réponses claires)",
                    'primary_keyword' => 'question + thématique',
                    'outline_h2' => ['Question 1', 'Question 2', 'Question 3', 'FAQ'],
                ],
            ],
        ];
    }
}

<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ReportItem;
use Illuminate\Http\Request;
use App\Services\Content\ContentClaimValidator;

class ReportItemContentSuggestionController extends Controller
{
    public function index(Request $request, ReportItem $item, ContentClaimValidator $validator)
    {
        // ✅ 0) Auth via token (tu gardes)
        $token = $request->query('token');
        $report = $item->report; // relation à créer si pas déjà

        if (!$report || !$token || $token !== $report->access_token) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        // ✅ 1) récupérer les suggestions (et garder ton tri)
        $pages = $item->contentSuggestions()
            ->where('type', 'page')
            ->orderByDesc('priority_score')
            ->get();

        $articles = $item->contentSuggestions()
            ->where('type', 'article')
            ->orderByDesc('priority_score')
            ->get();

        // ✅ 2) validation / filtrage automatique
        $pages = $pages
            ->map(function ($s) use ($validator) {
                $v = $validator->validateSuggestion($s->toArray());
                $s->validator = $v['verdict'];   // optionnel (debug / UI)
                return $s;
            })
            ->filter(function ($s) {
                $v = $s->validator ?? [];

                if (($v['is_factually_correct'] ?? null) === false) return false;
                if (($v['is_trivial_or_inherent'] ?? null) === true) return false;
                if (($v['is_good_topic'] ?? null) === false) return false;

                return true;
            })
            ->values();

        $articles = $articles
            ->map(function ($s) use ($validator) {
                $v = $validator->validateSuggestion($s->toArray());
                $s->validator = $v['verdict'];
                return $s;
            })
            ->filter(function ($s) {
                $v = $s->validator ?? [];

                if (($v['is_factually_correct'] ?? null) === false) return false;
                if (($v['is_trivial_or_inherent'] ?? null) === true) return false;
                if (($v['is_good_topic'] ?? null) === false) return false;

                return true;
            })
            ->values();

        // ✅ 3) réponse identique à avant (pour ne rien casser côté front)
        return response()->json([
            'item_id' => $item->id,
            'domain' => $item->domain,
            'pages' => $pages,
            'articles' => $articles,
        ]);
    }
}

<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ReportItem;
use App\Services\Content\OpenAIResponsesClient;

class ReportItemBacklinksController extends Controller
{
public function show(ReportItem $item, OpenAIResponsesClient $openai)
{
	
	\Log::info('BacklinksAdvice DEBUG', [
    'route' => request()->path(),
    'item_id' => $item->id,
    'domain' => $item->domain,
    'authority_final' => $item->authority_final,
    'da' => $item->da,
]);

	
    // ✅ 1) Autorité : même logique que le tableau
    $authority = (int) (
        $item->authority_final
        ?? $item->da
        ?? 0
    );

    // ✅ 2) Metrics de base
    $rd = (int) ($item->linking_domains ?? 0);
    $bl = (int) ($item->inbound_links ?? 0);

    // ✅ 3) Visites : tu stockes traffic_estimated
    $visits = (int) (
        $item->traffic_estimated
        ?? 0
    );

    // ✅ 4) ETV en €/mois
    $etv = number_format((float) ($item->traffic_etv ?? 0), 2, '.', '');

    $prompt = "<<<PROMPT
Tu es un expert SEO.

IMPORTANT :
- ETV = valeur estimée du trafic en € / mois (pas des visites).
- Le trafic SEO estimé est en visites / mois.
- Ne transforme JAMAIS les nombres.

Analyse les données suivantes pour un site e-commerce
et produis un diagnostic clair et professionnel.

Données :
- Autorité du domaine : {$authority} / 100
- Domaines référents : {$rd}
- Backlinks : {$bl}
- Trafic SEO estimé : {$visits} visites / mois
- Valeur SEO (ETV) : {$etv} € / mois
- Type de site : e-commerce

Structure OBLIGATOIRE de la réponse (en HTML) :
- <h3>Analyse actuelle</h3>
- <ul> avec les métriques
- <h3>Faut-il obtenir des backlinks ?</h3>
- <h3>Combien en obtenir</h3>
- <h3>Pages à cibler</h3>
- <h3>Rythme recommandé</h3>
- <h3>Actions concrètes</h3>

Contraintes :
- HTML simple uniquement (h3, p, ul, li, strong)
- Pas de markdown
- Pas de noms d’outils
- Ton clair, professionnel, pédagogique
PROMPT;";

    $html = $openai->generateHtml($prompt);
    $html = preg_replace('/```html|```/i', '', $html);

    //return response()->json(['html' => $html]);
	return response()->json([
    'debug' => [
        'id'              => $item->id,
        'domain'          => $item->domain,
        'authority_final' => $item->authority_final,
        'da'              => $item->da,
        'linking_domains' => $item->linking_domains,
        'inbound_links'   => $item->inbound_links,
        'traffic_estimated' => $item->traffic_estimated,
        'traffic_etv'     => $item->traffic_etv,
        'raw_keys'        => is_array($item->raw_json ?? null) ? array_keys($item->raw_json) : null,
    ],
    'html' => $html,
]);

	
}


}

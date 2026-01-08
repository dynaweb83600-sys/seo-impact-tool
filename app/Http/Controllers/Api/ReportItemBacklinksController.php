<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ReportItem;
use Illuminate\Http\Request;
use App\Services\Content\OpenAIResponsesClient;

class ReportItemBacklinksController extends Controller
{
	public function show(Request $request, ReportItem $item, OpenAIResponsesClient $openai)
	{
		// ✅ token check
		$token = (string) $request->query('token');
		$report = $item->report; // assure-toi d'avoir la relation report()

		if (!$token || !$report || $token !== $report->access_token) {
			return response()->json(['message' => 'Unauthorized'], 401);
		}

		// ✅ authority (default 0 pour activer fallback)
		$authority = (int) $request->query('authority', 0);

		if ($authority <= 0) {
			// fallback...
		}

		$rd = (int) ($item->linking_domains ?? 0);
		$bl = (int) ($item->inbound_links ?? 0);

		//$traffic = number_format((float) ($item->traffic_estimated ?? 0), 0, '.', '');
		
		
		$trafficVisits = null;

		// 1) GSC clicks (30j) si dispo
		if ($item->gsc_clicks_30d !== null) {
			$trafficVisits = (int) $item->gsc_clicks_30d;
		}
		// 2) sinon, fallback sur traffic_estimated si tu l’utilises vraiment
		elseif ($item->traffic_estimated !== null) {
			$trafficVisits = (int) round((float) $item->traffic_estimated);
		}
		// 3) sinon, fallback sur traffic_etv (DataForSEO, ton "visites/mois estimées")
		elseif ($item->traffic_etv !== null) {
			$trafficVisits = (int) round((float) $item->traffic_etv);
		}

		$traffic = $trafficVisits === null
		? 'N/A'
		: number_format($trafficVisits, 0, '.', '');

		$etv     = number_format((float) ($item->traffic_etv ?? 0), 2, '.', '');

		$prompt = <<<PROMPT
	Tu es un expert SEO.

	IMPORTANT :
	- Trafic SEO estimé = nombre de visites mensuelles.
	- ETV (Estimated Traffic Value) = valeur estimée en euros par mois (décimal).
	- Ne transforme JAMAIS les nombres.

	Analyse les données suivantes pour un site e-commerce
	et produis un diagnostic clair et professionnel.

	Données :
	- Autorité du domaine : {$authority} / 100
	- Domaines référents : {$rd}
	- Backlinks : {$bl}
	- Trafic SEO estimé : {$trafficVisits} visites / mois
	- Valeur trafic (ETV) : {$etv} €
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
	PROMPT;

		$html = $openai->generateHtml($prompt);
		$html = preg_replace('/```html|```/i', '', $html);

		return response()->json(['html' => $html]);
	}


}

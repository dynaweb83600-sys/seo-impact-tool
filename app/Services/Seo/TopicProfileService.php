<?php

namespace App\Services\Seo;

use Illuminate\Support\Facades\Http;

class TopicProfileService
{
    public function buildFromHomepage(string $domain): array
    {
        $url = 'https://' . $domain;

        try {
            $html = Http::timeout(8)->get($url)->body();
        } catch (\Throwable) {
            return $this->fallbackProfile($domain);
        }

        $text = $this->extractTextSignals($html);

        // tokens simples : tu peux améliorer ensuite (stemming etc.)
        $tokens = $this->topTokens($text);

        // blacklist générique (hors sujet fréquent)
        $banned = [];


        return [
            'source' => 'homepage',
            'url' => $url,
            'tokens' => array_values(array_slice($tokens, 0, 15)),
            'banned' => $banned,
        ];
    }

   private function extractTextSignals(string $html): string
	{
		$html = preg_replace('#<script\b[^>]*>.*?</script>#is', ' ', $html);
		$html = preg_replace('#<style\b[^>]*>.*?</style>#is', ' ', $html);

		$signals = [];

		// title
		if (preg_match('#<title>(.*?)</title>#is', $html, $m)) $signals[] = strip_tags($m[1]);

		// meta description
		if (preg_match('#<meta[^>]+name=["\']description["\'][^>]+content=["\']([^"\']+)["\']#is', $html, $m)) $signals[] = $m[1];

		// h1/h2/h3
		foreach (['h1','h2','h3'] as $tag) {
			if (preg_match_all('#<'.$tag.'[^>]*>(.*?)</'.$tag.'>#is', $html, $mm)) {
				foreach ($mm[1] as $t) $signals[] = strip_tags($t);
			}
		}

		// Liens (menus/catégories)
		if (preg_match_all('#<a[^>]*>(.*?)</a>#is', $html, $mm)) {
			foreach ($mm[1] as $t) {
				$t = trim(strip_tags($t));
				if (mb_strlen($t) >= 3 && mb_strlen($t) <= 60) $signals[] = $t;
			}
		}

		$text = mb_strtolower(implode(' ', $signals));
		$text = preg_replace('/\s+/', ' ', $text);

		return $text;
	}

	private function topTokens(string $text): array
	{
		// stopwords + mots e-commerce (à élargir)
		$stop = [
			'le','la','les','de','des','du','un','une','et','ou','pour','avec','sur','dans','à','au','aux','en','par',
			'notre','votre','vos','plus','moins','bien','meilleur','meilleure','qualité','bio','naturel','naturelle',
			'acheter','achat','commande','commander','boutique','produit','produits','prix','promo','promotion',
			'livraison','paiement','panier','compte','connexion','inscription','contact','avis','garantie'
		];

		preg_match_all('/[a-zàâçéèêëîïôûùüÿñæœ]{3,}/iu', $text, $m);
		$words = $m[0] ?? [];

		$freq = [];
		foreach ($words as $w) {
			$w = mb_strtolower($w);
			if (in_array($w, $stop, true)) continue;
			$freq[$w] = ($freq[$w] ?? 0) + 1;
		}

		arsort($freq);

		// important : on évite les tokens trop génériques restants
		$genericBan = ['huile', 'huiles', 'complément', 'compléments', 'santé', 'bienêtre', 'bien-etre'];

		$out = [];
		foreach (array_keys($freq) as $w) {
			if (in_array($w, $genericBan, true)) continue;
			$out[] = $w;
			if (count($out) >= 20) break;
		}

		return $out;
	}


    private function fallbackProfile(string $domain): array
	{
		return [
			'source' => 'fallback',
			'tokens' => [
				preg_replace('/\..+$/', '', $domain)
			],
			'banned' => [], // AUCUNE censure de thématique
		];
	}

}

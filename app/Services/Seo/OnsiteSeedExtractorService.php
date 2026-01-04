<?php

namespace App\Services\Seo;

use Illuminate\Support\Facades\Http;

class OnsiteSeedExtractorService
{
    public function extract(string $domain, int $max = 15): array
    {
        $url = 'https://' . $domain;

        try {
            $html = Http::timeout(12)
                ->withHeaders(['User-Agent' => 'Mozilla/5.0'])
                ->get($url)
                ->body();
        } catch (\Throwable) {
            return [];
        }

        // 1) enlever ce qui pollue
        $html = preg_replace('#<script\b[^>]*>.*?</script>#is', ' ', $html);
        $html = preg_replace('#<style\b[^>]*>.*?</style>#is', ' ', $html);
        $html = preg_replace('#<svg\b[^>]*>.*?</svg>#is', ' ', $html);

        // 2) retirer zones UI fréquentes (header/nav/footer/modal/cookie/cart/wishlist)
        $html = preg_replace('#<header\b[^>]*>.*?</header>#is', ' ', $html);
        $html = preg_replace('#<nav\b[^>]*>.*?</nav>#is', ' ', $html);
        $html = preg_replace('#<footer\b[^>]*>.*?</footer>#is', ' ', $html);

        // modals / cookies (best effort)
        $html = preg_replace('#<div[^>]+(cookie|consent|modal|popup|drawer|cart|basket|wishlist)[^>]*>.*?</div>#is', ' ', $html);

        // 3) extractions SEO ciblées
        $signals = [];

        if (preg_match('#<title[^>]*>(.*?)</title>#is', $html, $m)) $signals[] = $m[1];
        if (preg_match('#<meta[^>]+name=["\']description["\'][^>]+content=["\']([^"\']+)["\']#is', $html, $m)) $signals[] = $m[1];

        // H1/H2/H3
        preg_match_all('#<h[1-3][^>]*>(.*?)</h[1-3]>#is', $html, $mm);
        foreach (($mm[1] ?? []) as $t) $signals[] = $t;

        // textes de liens (catégories / produits) - on évite les liens UI
        preg_match_all('#<a[^>]*>(.*?)</a>#is', $html, $am);
        foreach (($am[1] ?? []) as $t) {
            $signals[] = $t;
        }

        // 4) nettoyer / décoder / normaliser
        $text = html_entity_decode(implode(' ', $signals), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = strip_tags($text);
        $text = mb_strtolower($text);
        $text = preg_replace('/[\x{E000}-\x{F8FF}]/u', ' ', $text); // private use (icônes)
        $text = preg_replace('/[^a-zàâçéèêëîïôûùüÿñæœ\s]/iu', ' ', $text);
        $text = preg_replace('/\s+/', ' ', $text);

        // 5) stopwords + blacklist UI
        $stop = [
            'le','la','les','de','des','du','un','une','et','ou','pour','avec','sur','dans','à','au','aux','en','par',
            'vos','notre','votre','plus','moins','tout','tous','voir','découvrir'
        ];

        $bannedContains = [
            'panier','wishlist','liste de souhaits','supprimer','quick','view','connexion','compte',
            'livraison','retour','cookie','consent','newsletter','promo','recherche'
        ];

        // 6) candidats = mots/phrases (2-3 mots) + scoring freq
        preg_match_all('/[a-zàâçéèêëîïôûùüÿñæœ]{3,}/iu', $text, $m);
        $words = array_map('mb_strtolower', $m[0] ?? []);
        $words = array_values(array_filter($words, fn($w) => !in_array($w, $stop, true)));

        $freq = [];
        foreach ($words as $w) {
            foreach ($bannedContains as $b) {
                if ($b !== '' && str_contains($w, $b)) continue 2;
            }
            $freq[$w] = ($freq[$w] ?? 0) + 1;
        }

        arsort($freq);
        $topWords = array_slice(array_keys($freq), 0, 30);

        // petites phrases (bigrams) pour capter "miel bio", "miel artisanal" etc.
        $bigrams = [];
        for ($i=0; $i<count($words)-1; $i++) {
            $a = $words[$i]; $b = $words[$i+1];
            if (in_array($a, $stop, true) || in_array($b, $stop, true)) continue;
            $bg = "$a $b";
            foreach ($bannedContains as $ban) {
                if ($ban !== '' && str_contains($bg, $ban)) continue 2;
            }
            $bigrams[$bg] = ($bigrams[$bg] ?? 0) + 1;
        }
        arsort($bigrams);
        $topBigrams = array_slice(array_keys($bigrams), 0, 20);

        // mix final
        $out = array_values(array_unique(array_merge($topBigrams, $topWords)));

        return array_slice($out, 0, $max);
    }
}

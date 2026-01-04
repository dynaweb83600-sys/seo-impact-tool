<?php

namespace App\Services\Seo;

class SeedKeywordService
{
    public function buildSeeds(array $rankedKeywordsResp, array $topicProfile = [], int $max = 10): array
    {
        $items = data_get($rankedKeywordsResp, 'tasks.0.result.0.items', []);
        if (!is_array($items)) return [];

        $tokens = array_values(array_filter(array_map('mb_strtolower', (array)($topicProfile['tokens'] ?? []))));
        $banned = array_values(array_filter(array_map('mb_strtolower', (array)($topicProfile['banned'] ?? []))));

        // 1) nettoyage + filtre par topic
        $filtered = [];
        foreach ($items as $it) {
            $kw = trim((string) data_get($it, 'keyword', ''));
            if ($kw === '' || mb_strlen($kw) < 3) continue;

            $kwLower = mb_strtolower($kw);

            // blacklist
            foreach ($banned as $b) {
                if ($b !== '' && str_contains($kwLower, $b)) {
                    continue 2;
                }
            }

            // si on a des tokens, on exige un match minimal
            if (!empty($tokens)) {
                $ok = false;
                foreach ($tokens as $t) {
                    if ($t !== '' && str_contains($kwLower, $t)) { $ok = true; break; }
                }
                if (!$ok) continue;
            }

            $filtered[] = $it;
        }

        // 2) tri par score
        usort($filtered, function($a, $b){
            $scoreA = (float) data_get($a,'etv',0) + (float) data_get($a,'search_volume',0)/1000 - (float) data_get($a,'rank_group',50)/100;
            $scoreB = (float) data_get($b,'etv',0) + (float) data_get($b,'search_volume',0)/1000 - (float) data_get($b,'rank_group',50)/100;

            if ($scoreB !== $scoreA) return $scoreB <=> $scoreA;
            return strcmp((string) data_get($a,'keyword',''), (string) data_get($b,'keyword',''));
        });

        $out = [];
        foreach ($filtered as $it) {
            $kw = trim((string) data_get($it,'keyword',''));
            $out[] = $kw;
            if (count($out) >= $max) break;
        }

        return array_values(array_unique($out));
    }
}

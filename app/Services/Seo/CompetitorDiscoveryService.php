<?php

namespace App\Services\Seo;

use Illuminate\Support\Str;

class CompetitorDiscoveryService
{
    public function __construct(private DataForSeoClient $dfs) {}

    /**
     * @param string $domain Ex: naturapisante.fr
     * @param array $seedKeywords Ex: ['collagène marin', 'spiruline bio', ...]
     * @param int $maxCompetitors Ex: 3
     * @param int $serpDepth Ex: 20
     * @return array Ex:
     * [
     *   ['domain' => 'concurrent.fr', 'score' => 8, 'avg_pos' => 3.2],
     *   ...
     * ]
     */
    public function detect(string $domain, array $seedKeywords, int $maxCompetitors = 3, int $serpDepth = 20): array
    {
        $domain = $this->normalizeDomain($domain);
        $seedKeywords = collect($seedKeywords)
            ->filter(fn ($k) => is_string($k) && trim($k) !== '')
            ->map(fn ($k) => trim($k))
            ->unique()
            ->take(10) // limite pour rester raisonnable
            ->values()
            ->all();

        if (empty($seedKeywords)) {
            return [];
        }

        // Accumulateur: domain => ['hits'=>int, 'pos_sum'=>float, 'pos_count'=>int]
        $acc = [];

        foreach ($seedKeywords as $kw) {
            $resp = $this->dfs->serpGoogleOrganicLiveAdvanced($kw, 2250, 'fr', $serpDepth);

            $task0 = data_get($resp, 'tasks.0', []);
            $ok = ((int) data_get($resp, 'status_code') === 20000)
               && ((int) data_get($task0, 'status_code') === 20000);

            if (!$ok) {
                // si tu veux logger, fais-le au niveau Job
                continue;
            }

            $items = data_get($task0, 'result.0.items', []);
            if (!is_array($items)) continue;

            foreach ($items as $it) {
                // On ne garde que les résultats organiques classiques
                $type = data_get($it, 'type');
                if (!in_array($type, ['organic', 'featured_snippet', 'people_also_ask'], true)) {
                    // Tu peux resserrer à 'organic' uniquement si tu veux
                    // continue;
                }

                $url = (string) data_get($it, 'url', '');
                $pos = (float) data_get($it, 'rank_absolute', data_get($it, 'rank_group', null));

                if (!$url || !$pos) continue;

                $d = $this->extractDomainFromUrl($url);
                $d = $this->normalizeDomain($d);

                if (!$d) continue;
                if ($d === $domain) continue; // exclure le site analysé

                // Filtrage basique (optionnel) : exclure quelques gros sites non concurrents
                if ($this->isIgnorableDomain($d)) continue;

                if (!isset($acc[$d])) {
                    $acc[$d] = ['hits' => 0, 'pos_sum' => 0.0, 'pos_count' => 0];
                }

                // Score : +1 par apparition
                $acc[$d]['hits']++;

                // Moyenne de position (plus bas = meilleur)
                $acc[$d]['pos_sum'] += $pos;
                $acc[$d]['pos_count']++;
            }
        }

        // Calcul score final:
        // - hits (fréquence d'apparition) est le signal principal
        // - avg_pos sert à départager
        $out = [];
        foreach ($acc as $d => $v) {
            $avgPos = $v['pos_count'] > 0 ? ($v['pos_sum'] / $v['pos_count']) : 999;
            $out[] = [
                'domain' => $d,
                'score' => (int) $v['hits'],
                'avg_pos' => round($avgPos, 2),
            ];
        }

        usort($out, function ($a, $b) {
            // 1) score desc
            if ($b['score'] !== $a['score']) return $b['score'] <=> $a['score'];
            // 2) avg_pos asc
            return $a['avg_pos'] <=> $b['avg_pos'];
        });

        return array_slice($out, 0, $maxCompetitors);
    }

    private function normalizeDomain(?string $d): ?string
    {
        if (!$d) return null;
        $d = strtolower(trim($d));
        $d = preg_replace('#^https?://#', '', $d);
        $d = preg_replace('#^www\.#', '', $d);
        $d = trim($d, "/ \t\n\r\0\x0B");
        return $d ?: null;
    }

    private function extractDomainFromUrl(string $url): ?string
    {
        $host = parse_url($url, PHP_URL_HOST);
        return $host ? (string) $host : null;
    }

    private function isIgnorableDomain(string $d): bool
    {
        // Ajuste à ta sauce
        $ban = [
            'facebook.com', 'instagram.com', 'youtube.com', 'tiktok.com', 'pinterest.com',
            'wikipedia.org', 'amazon.', 'ebay.', 'leboncoin.fr',
        ];

        foreach ($ban as $b) {
            if (Str::contains($d, $b)) return true;
        }
        return false;
    }
}

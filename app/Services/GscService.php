<?php

namespace App\Services;

use Carbon\Carbon;
use Google\Client as GoogleClient;
use Google\Service\SearchConsole;
use Illuminate\Support\Str;

class GscService
{
    public function makeClient(array $token): \Google\Client
	{
		$client = new \Google\Client();

		$client->setClientId(config('services.google.client_id'));
		$client->setClientSecret(config('services.google.client_secret'));

		$client->setAccessType('offline');
		$client->setPrompt('consent');

		// IMPORTANT: $token doit être un array avec 'access_token'
		$client->setAccessToken($token);

		return $client;
	}


    /**
     * Retourne la liste brute des siteUrls accessibles en GSC
     * ex: ["sc-domain:example.com", "https://example.com/"]
     */
		public function listSiteUrls(SearchConsole $svc): array
		{
			$sites = $svc->sites->listSites();
			$out = [];

			foreach (($sites->getSiteEntry() ?? []) as $entry) {
				$siteUrl = $entry->getSiteUrl();
				$perm    = $entry->getPermissionLevel(); // <-- IMPORTANT

				// garde seulement les propriétés vraiment exploitables
				if (!$siteUrl) continue;
				if (!in_array($perm, ['siteOwner', 'siteFullUser'], true)) continue;

				$out[] = $siteUrl;
			}

			return array_values(array_unique($out));
		}



    /**
     * Trouve la propriété la plus adaptée pour un domaine.
     * Priorité:
     * 1) sc-domain:domain.tld
     * 2) https://domain.tld/
     * 3) https://www.domain.tld/
     * 4) http://...
     */
    public function resolveSiteUrlForDomain(string $domain, array $siteUrls): ?string
    {
        $domain = strtolower(trim($domain));
        $domain = preg_replace('#^https?://#', '', $domain);
        $domain = rtrim($domain, '/');

        // 1) Domain property
        $wantedDomainProp = "sc-domain:{$domain}";
        if (in_array($wantedDomainProp, $siteUrls, true)) {
            return $wantedDomainProp;
        }

        // 2) url-prefix exact
        $candidates = [
            "https://{$domain}/",
            "https://www.{$domain}/",
            "http://{$domain}/",
            "http://www.{$domain}/",
        ];

        foreach ($candidates as $c) {
            if (in_array($c, $siteUrls, true)) return $c;
        }

        // fallback “match” (au cas où l’URL est en sous-domaine)
        // ex: domain = example.com et siteUrl = https://shop.example.com/
        foreach ($siteUrls as $su) {
            if (Str::startsWith($su, 'sc-domain:')) continue;

            $host = parse_url($su, PHP_URL_HOST);
            if (!$host) continue;

            $host = strtolower($host);
            if ($host === $domain || Str::endsWith($host, '.'.$domain)) {
                return $su;
            }
        }

        return null;
    }

    /**
     * Query totals (clics/impr/position) sur les 30 derniers jours complets.
     */
	public function fetchTotals30d(SearchConsole $svc, string $siteUrl): array
	{
		$end = Carbon::yesterday();
		$start = $end->copy()->subDays(29);

		$req = new SearchConsole\SearchAnalyticsQueryRequest();
		$req->setStartDate($start->toDateString());
		$req->setEndDate($end->toDateString());

		// ❌ SUPPRIMER dimensions
		// $req->setDimensions(['date']);

		$req->setRowLimit(1); // UNE seule ligne globale

		$res = $svc->searchanalytics->query($siteUrl, $req);
		$rows = $res->getRows() ?? [];

		if (!count($rows)) {
			return [
				'clicks' => 0,
				'impressions' => 0,
				'position' => null,
				'start' => $start->toDateString(),
				'end' => $end->toDateString(),
			];
		}

		$r = $rows[0];

		return [
			'clicks' => (int) round($r->getClicks() ?? 0),
			'impressions' => (int) round($r->getImpressions() ?? 0),
			'position' => $r->getPosition() !== null ? round($r->getPosition(), 2) : null,
			'start' => $start->toDateString(),
			'end' => $end->toDateString(),
		];
	}




}

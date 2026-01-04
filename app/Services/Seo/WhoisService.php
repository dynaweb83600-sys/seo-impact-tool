<?php

namespace App\Services\Seo;

use Iodev\Whois\Factory;

class WhoisService
{
    /**
     * Retourne la date de création du domaine (Y-m-d) ou null
     */
    public function getCreatedAt(string $domain): ?string
    {
        // Nettoyage (https://, www, /)
        $domain = preg_replace('#^https?://#', '', $domain);
        $domain = preg_replace('#^www\.#', '', $domain);
        $domain = explode('/', $domain)[0];

        try {
            $whois = Factory::get()->createWhois();
            $info = $whois->loadDomainInfo($domain);

            if (!$info) {
                return null;
            }

            $timestamp = $info->getCreationDate();

            return $timestamp
                ? date('Y-m-d', $timestamp)
                : null;

        } catch (\Throwable $e) {
            // important : ne jamais casser le job pour un whois
            return null;
        }
    }

    /**
     * Ancienneté en années (ex: 3.4)
     */
    public function getAgeYears(string $domain): ?float
    {
        $createdAt = $this->getCreatedAt($domain);
        if (!$createdAt) return null;

        return round(
            now()->diffInDays(\Carbon\Carbon::parse($createdAt)) / 365,
            1
        );
    }
}

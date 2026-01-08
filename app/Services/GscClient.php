<?php

namespace App\Services;

use Google\Client;

class GscClient
{
    public static function make(array $token): Client
    {
        $client = new Client();
        $client->setClientId(config('services.google.client_id'));
        $client->setClientSecret(config('services.google.client_secret'));

        // optionnel mais propre
        if (config('services.google.redirect')) {
            $client->setRedirectUri(config('services.google.redirect'));
        }

        $client->setAccessType('offline');
        $client->setPrompt('consent');
        $client->setScopes(['https://www.googleapis.com/auth/webmasters.readonly']);

        $client->setAccessToken($token);

        return $client;
    }
}

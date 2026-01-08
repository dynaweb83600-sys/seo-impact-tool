<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Laravel\Socialite\Facades\Socialite;
use Illuminate\Support\Facades\Log;
use Google\Client as GoogleClient;
use Google\Service\SearchConsole;

class GscController extends Controller
{
    public function connect(Request $request)
    {
        return Socialite::driver('google')
            ->scopes(['https://www.googleapis.com/auth/webmasters.readonly'])
            ->with(['access_type' => 'offline', 'prompt' => 'consent'])
            ->stateless()
            ->redirect();
    }

    public function callback(Request $request)
    {
        $googleUser = Socialite::driver('google')->stateless()->user();

        $token = [
            'access_token'  => $googleUser->token,
            'refresh_token' => $googleUser->refreshToken,
            'expires_in'    => $googleUser->expiresIn,
            'created'       => time(),
        ];

        $user = auth()->user();
        if (!$user) abort(403);

        // ✅ avec cast 'array', $user->gsc_token_json est déjà un array (ou null)
        $existing = $user->gsc_token_json ?? [];

        // si refresh_token absent, on garde l'ancien
        if (empty($token['refresh_token']) && !empty($existing['refresh_token'])) {
            $token['refresh_token'] = $existing['refresh_token'];
        }

        $user->gsc_token_json = $token;
        $user->gsc_connected = true;

        // ✅ choisir une property valide et la stocker
        $user->gsc_property = $this->pickBestProperty($token);

        $user->save();

        return redirect('/domain-checker')->with('success', 'Google Search Console connecté ✅');
    }

    private function pickBestProperty(array $token): ?string
    {
        try {
            $client = new GoogleClient();
            $client->setClientId(config('services.google.client_id'));
            $client->setClientSecret(config('services.google.client_secret'));
            $client->setAccessToken($token);

            // refresh automatique si nécessaire
            if ($client->isAccessTokenExpired() && !empty($token['refresh_token'])) {
                $client->fetchAccessTokenWithRefreshToken($token['refresh_token']);
            }

            $svc = new SearchConsole($client);
            $sites = $svc->sites->listSites()->getSiteEntry() ?? [];

            // exemple: on préfère une propriété sc-domain: si présente
            $props = array_map(fn($s) => $s->getSiteUrl(), $sites);

            // si tu veux forcer une property par défaut, tu peux loguer ici
            Log::info('GSC properties', ['props' => $props]);

            // choix “best effort” : une sc-domain si existe, sinon première
            foreach ($props as $p) {
                if (str_starts_with($p, 'sc-domain:')) return $p;
            }
            return $props[0] ?? null;

        } catch (\Throwable $e) {
            Log::error('GSC pickBestProperty failed', ['msg' => $e->getMessage()]);
            return null;
        }
    }
}

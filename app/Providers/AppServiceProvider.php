<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use GuzzleHttp\Client;
use App\Services\Content\OpenAIResponsesClient;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // ✅ ICI EXACTEMENT
        $this->app->singleton(OpenAIResponsesClient::class, function () {
            return new OpenAIResponsesClient(new Client([
                'timeout' => 25,
                'connect_timeout' => 10,
            ]));
        });
    }

    public function boot(): void
    {
        //
    }
}

<?php

namespace App\Providers;

use GuzzleHttp\Client;
use Illuminate\Support\ServiceProvider;
use OpenAI;
use OpenAI\Contracts\ClientContract;

class OpenAIServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(ClientContract::class, function () {
            $httpClient = new Client([
                'verify' => 'C:\laragon\bin\php\php-8.4.6-nts-Win32-vs17-x64\extras\ssl\cacert.pem',
            ]);

            return OpenAI::factory()
                ->withApiKey(config('openai.api_key'))
                ->withOrganization(config('openai.organization'))
                ->withProject(config('openai.project'))
                ->withHttpClient($httpClient)
                ->make();
        });
    }
}

<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Jira\JiraClient;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(JiraClient::class, function () {
            $baseUrl = config('jira.base_url');
            if (!$baseUrl) {
                throw new \RuntimeException('JIRA_BASE_URL is not set');
            }

            $token = config('jira.token');
            if (!$token) {
                throw new \RuntimeException('JIRA_TOKEN is not set');
            }


            return new JiraClient($baseUrl, $token, config('jira.verify_tls'));
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}

<?php

declare(strict_types=1);

namespace App\Providers;

use App\Jira\JiraClient;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(JiraClient::class, function () {
            $baseUrl = config('jira.base_url');
            if (! $baseUrl) {
                throw new \RuntimeException('JIRA_BASE_URL is not set');
            }

            $token = config('jira.token');
            if (! $token) {
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

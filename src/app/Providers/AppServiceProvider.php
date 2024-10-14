<?php

namespace App\Providers;

use BeyondCode\LaravelWebSockets\Facades\WebSocketsRouter;
use BeyondCode\LaravelWebSockets\WebSockets\WebSocketHandler;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register()
    {
        if ($this->app->environment('production')) {
            $this->registerAwsServiceProvider();
        }
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        WebSocketsRouter::webSocket('/app/{appKey}', WebSocketHandler::class);
    }

    /**
     * Register AWS Service Provider if it exists.
     */
    private function registerAwsServiceProvider()
    {
        $awsProviderClass = 'Aws\Laravel\AwsServiceProvider';
        if (class_exists($awsProviderClass)) {
            $this->app->register($awsProviderClass);
        }
    }
}

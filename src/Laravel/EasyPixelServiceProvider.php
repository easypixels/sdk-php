<?php

namespace EasyPixel\Laravel;

use EasyPixel\Client;
use EasyPixel\Manager;
use Illuminate\Support\ServiceProvider;

class EasyPixelServiceProvider extends ServiceProvider
{
    /**
     * Register SDK services in the container.
     *
     * @return void
     */
    public function register()
    {
        $this->mergeConfigFrom(
            __DIR__ . '/config/easypixel.php',
            'easypixel'
        );

        $this->app->singleton(Manager::class, function ($app) {
            $token   = $app->make('config')->get('easypixel.token', '');
            $baseUrl = $app->make('config')->get('easypixel.base_url', 'https://api.easypixel.ru');
            return new Manager($token, $baseUrl);
        });

        $this->app->singleton(Client::class, function ($app) {
            return $app->make(Manager::class)->client();
        });
    }

    /**
     * Bootstrap SDK services.
     *
     * @return void
     */
    public function boot()
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__ . '/config/easypixel.php' => $this->app->configPath('easypixel.php'),
            ], 'easypixel');
        }
    }

    /**
     * Get the services provided by the provider.
     *
     * @return array
     */
    public function provides()
    {
        return [Manager::class, Client::class];
    }
}

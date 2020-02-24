<?php

namespace Mtrajano\LaravelSwagger;

use Illuminate\Support\ServiceProvider;

class SwaggerServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                GenerateSwaggerDoc::class,
                Ui::class,
            ]);
        }

        $source = __DIR__ . '/../config/laravel-swagger.php';

        $this->publishes([
            $source => config_path('laravel-swagger.php'),
        ]);

        $this->mergeConfigFrom(
            $source, 'laravel-swagger'
        );

        $this->loadRoutesFrom(__DIR__.'/routes.php');

        $this->registerSwaggerDisk();
    }

    protected function registerSwaggerDisk(): void
    {
        app()->config['filesystems.disks.swagger'] = [
            'driver' => 'local',
            'root' => '/',
        ];
    }
}

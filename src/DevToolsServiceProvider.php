<?php

declare(strict_types=1);

namespace Juling\DevTools;

use Illuminate\Support\ServiceProvider;
use Juling\DevTools\Console\Commands;

class DevToolsServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap any package services.
     */
    public function register(): void
    {
        $this->mergeConfigFrom(dirname(__DIR__).'/config/devtools.php', 'devtools');
    }

    /**
     * Bootstrap any package services.
     */
    public function boot(): void
    {
        $this->publishes([
            dirname(__DIR__).'/config/devtools.php' => config_path('devtools.php'),
        ]);

        if ($this->app->runningInConsole()) {
            $this->commands([
                Commands\GenController::class,
                Commands\GenDict::class,
                Commands\GenEntity::class,
                Commands\GenEnums::class,
                Commands\GenModel::class,
                Commands\GenPagesRoute::class,
                Commands\GenRepository::class,
                Commands\GenRoute::class,
                Commands\GenService::class,
                Commands\GenTypescript::class,
                Commands\GenView::class,
                Commands\InitCommand::class,
            ]);
        }
    }
}

<?php

namespace Codemonkey\SPXMcpServer;

use Codemonkey\SPXMcpServer\Mcp\McpServer;
use Illuminate\Routing\Router;
use Illuminate\Support\ServiceProvider;
use Laravel\Mcp\Facades\Mcp;

class SPXMcpServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__ . '/../config/spx-mcp.php',
            'spx-mcp'
        );
    }

    public function boot(Router $router): void
    {
        if (!$this->shouldRun()) {
            return;
        }

        Mcp::local('spx-mcp', McpServer::class);

        $this->registerPublishing();
        $this->registerCommands();
    }

    private function registerPublishing(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__ . '/../config/spx-mcp.php' => config_path('spx-mcp.php'),
            ], 'spx-mcp-config');
        }
    }

    private function registerCommands(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                Console\StartCommand::class,
                Console\InstallCommand::class,
            ]);
        }
    }

    private function shouldRun(): bool
    {
        if (!config('spx-mcp.enabled', true)) {
            return false;
        }

        if (app()->runningUnitTests()) {
            return false;
        }

        if (!app()->environment('local') && config('app.debug', false) !== true) {
            return false;
        }

        return true;
    }
}
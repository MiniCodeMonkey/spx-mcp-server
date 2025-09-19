<?php

declare(strict_types=1);

namespace Codemonkey\SpxMcpServer\Console;

use Illuminate\Console\Command;
use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand('spx-mcp:install', 'Install SPX MCP Server')]
class InstallCommand extends Command
{
    public function handle(): void
    {
        if (!$this->confirm('This will add the SPX MCP server to your Claude Code .mcp.json file. Do you want to continue?')) {
            $this->warn('Installation cancelled.');
            return;
        }

        $this->ensureMcpJsonExists();

        if ($this->addSpxMcpServerToMcpJson()) {
            $this->info('SPX MCP server added to your .mcp.json file.');
        }
    }

    private function ensureMcpJsonExists(): void
    {
        if (!file_exists($this->getMcpConfigFilename())) {
            $defaultConfig = [
                'mcpServers' => [],
            ];

            $this->writeMcpConfig($defaultConfig);
        }
    }

    private function addSpxMcpServerToMcpJson(): bool
    {
        $existingConfig = json_decode(file_get_contents($this->getMcpConfigFilename()), true, 512, JSON_THROW_ON_ERROR);
        if (isset($existingConfig['mcpServers']['spx-mcp'])) {
            $this->error('SPX MCP server already exists in your .mcp.json configuration file.');
            return false;
        }

        $existingConfig['mcpServers']['spx-mcp'] = [
            'command' => 'php',
            'args' => [
                './artisan',
                'spx-mcp:serve'
            ]
        ];


        $this->writeMcpConfig($existingConfig);

        return true;
    }

    private function getMcpConfigFilename(): string
    {
        return base_path('.mcp.json');
    }

    private function writeMcpConfig(array $config): void
    {
        file_put_contents($this->getMcpConfigFilename(), json_encode($config, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }
}

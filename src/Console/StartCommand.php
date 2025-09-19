<?php

declare(strict_types=1);

namespace Codemonkey\SpxMcpServer\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand('spx-mcp:serve', 'Starts SPX MCP Server (usually from mcp.json)')]
class StartCommand extends Command
{
    public function handle(): int
    {
        return Artisan::call('mcp:start spx-mcp');
    }
}

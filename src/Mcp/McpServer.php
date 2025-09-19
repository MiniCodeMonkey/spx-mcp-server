<?php

namespace Codemonkey\SPXMcpServer\Mcp;

use Codemonkey\SPXMcpServer\Mcp\Tools\AnalyzeProfile;
use Codemonkey\SPXMcpServer\Mcp\Tools\ListProfiles;
use Laravel\Mcp\Server;

class McpServer extends Server
{
    /**
     * The MCP server's name.
     */
    protected string $name = 'SPX MCP Server';

    /**
     * The MCP server's version.
     */
    protected string $version = '0.0.1';

    /**
     * The MCP server's instructions for the LLM.
     */
    protected string $instructions = 'SPX php profiler MCP server that offers tools and resources to inspect profiling data and aid in performance optimization.';

    /**
     * The tools registered with this MCP server.
     *
     * @var array<int, class-string<\Laravel\Mcp\Server\Tool>>
     */
    protected array $tools = [
        ListProfiles::class,
        AnalyzeProfile::class,
    ];

    /**
     * The resources registered with this MCP server.
     *
     * @var array<int, class-string<\Laravel\Mcp\Server\Resource>>
     */
    protected array $resources = [
        // WeatherGuidelinesResource::class,
    ];

    /**
     * The prompts registered with this MCP server.
     *
     * @var array<int, class-string<\Laravel\Mcp\Server\Prompt>>
     */
    protected array $prompts = [
        // DescribeWeatherPrompt::class,
    ];
}
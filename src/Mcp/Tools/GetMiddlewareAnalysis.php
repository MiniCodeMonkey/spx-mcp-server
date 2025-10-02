<?php

namespace Codemonkey\SPXMcpServer\Mcp\Tools;

use Illuminate\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;

class GetMiddlewareAnalysis extends ProfileAnalysisTool
{
    protected string $description = 'Analyze middleware execution including execution order, time per middleware, and total middleware overhead.';

    public function handle(Request $request): Response
    {
        $profileKey = $request->get('profile_key');

        $parser = $this->parseProfile($profileKey);
        if ($parser instanceof Response) {
            return $parser;
        }

        $middleware = $parser->getMiddlewareAnalysis();

        if ($middleware['total_calls'] === 0) {
            return Response::text("No middleware execution detected in this profile.\n");
        }

        $output = "=== Middleware Analysis ===\n\n";
        $output .= sprintf("Total middleware time: %s\n", $this->formatTime($middleware['total_time']));
        $output .= sprintf("Total middleware calls: %d\n", $middleware['total_calls']);
        $output .= sprintf("Unique middleware: %d\n\n", count($middleware['middleware_list']));

        $output .= "Middleware Performance:\n";
        foreach ($middleware['middleware_list'] as $name => $mw) {
            $output .= sprintf("  %s\n", $name);
            $output .= sprintf("    Time: %s\n", $this->formatTime($mw['total_time']));
            $output .= sprintf("    Calls: %d\n", $mw['call_count']);
            if ($mw['call_count'] > 0) {
                $output .= sprintf("    Avg: %s\n", $this->formatTime($mw['total_time'] / $mw['call_count']));
            }
        }

        if (!empty($middleware['execution_order'])) {
            $output .= "\nExecution Order (first 10):\n";
            foreach (array_slice($middleware['execution_order'], 0, 10) as $i => $exec) {
                $output .= sprintf("  %d. %s (level %d)\n", $i + 1, $exec['name'], $exec['level']);
            }
        }

        return Response::text($output);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'profile_key' => $schema->string()->description('The SPX profile key to analyze.')->required(),
        ];
    }
}
<?php

namespace Codemonkey\SPXMcpServer\Mcp\Tools;

use Illuminate\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;

class GetRecursiveFunctions extends ProfileAnalysisTool
{
    /**
     * The tool's description.
     */
    protected string $description = 'Detect and analyze recursive function calls, including recursion depth and time spent in recursive calls.';

    /**
     * Handle the tool request.
     */
    public function handle(Request $request): Response
    {
        $profileKey = $request->get('profile_key');

        $parser = $this->parseProfile($profileKey);
        if ($parser instanceof Response) {
            return $parser;
        }

        $recursiveFunctions = $parser->getRecursiveFunctions();

        if (empty($recursiveFunctions)) {
            return Response::text("No recursive functions detected in this profile.\n");
        }

        $output = sprintf("=== Recursive Functions (%d found) ===\n\n", count($recursiveFunctions));

        foreach ($recursiveFunctions as $func) {
            $output .= sprintf("%s\n", $func['name']);
            $output .= sprintf("  Max recursion depth: %d\n", $func['max_depth']);
            $output .= sprintf("  Total recursive calls: %d\n", $func['total_recursive_calls']);
            $output .= sprintf("  Total time: %s\n", $this->formatTime($func['total_time']));
            if ($func['total_recursive_calls'] > 0) {
                $output .= sprintf("  Avg time per call: %s\n", 
                    $this->formatTime($func['total_time'] / $func['total_recursive_calls']));
            }
            $output .= "\n";
        }

        return Response::text($output);
    }

    /**
     * Get the tool's input schema.
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'profile_key' => $schema->string()
                ->description('The SPX profile key to analyze.')
                ->required(),
        ];
    }
}

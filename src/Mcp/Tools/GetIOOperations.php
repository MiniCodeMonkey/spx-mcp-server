<?php

namespace Codemonkey\SPXMcpServer\Mcp\Tools;

use Illuminate\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;

class GetIOOperations extends ProfileAnalysisTool
{
    protected string $description = 'Analyze I/O operations categorized by type: file I/O, network I/O, and socket I/O.';

    public function handle(Request $request): Response
    {
        $profileKey = $request->get('profile_key');
        $limit = $request->get('limit', 10);

        $parser = $this->parseProfile($profileKey);
        if ($parser instanceof Response) {
            return $parser;
        }

        $io = $parser->getIOOperations();

        $totalTime = 0;
        $totalOps = 0;
        foreach ($io as $category) {
            $totalTime += $category['total_time'];
            $totalOps += $category['total_operations'];
        }

        $output = "=== I/O Operations Analysis ===\n\n";
        $output .= sprintf("Total I/O time: %s\n", $this->formatTime($totalTime));
        $output .= sprintf("Total I/O operations: %d\n\n", $totalOps);

        foreach ($io as $categoryName => $category) {
            if ($category['total_operations'] === 0) continue;

            $output .= sprintf("%s I/O:\n", ucfirst($categoryName));
            $output .= sprintf("  Total time: %s\n", $this->formatTime($category['total_time']));
            $output .= sprintf("  Operations: %d\n", $category['total_operations']);
            $output .= sprintf("  Functions: %d\n", count($category['functions']));

            if (!empty($category['functions'])) {
                $output .= sprintf("  Top %d functions:\n", min($limit, count($category['functions'])));
                foreach (array_slice($category['functions'], 0, $limit) as $func) {
                    $output .= sprintf("    - %s (%d calls, %s)\n",
                        substr($func['name'], 0, 40),
                        $func['call_count'],
                        $this->formatTime($func['inclusive_time']));
                }
            }
            $output .= "\n";
        }

        return Response::text($output);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'profile_key' => $schema->string()->description('The SPX profile key to analyze.')->required(),
            'limit' => $schema->integer()->description('Number of functions to show per category (default: 10)')->default(10),
        ];
    }
}
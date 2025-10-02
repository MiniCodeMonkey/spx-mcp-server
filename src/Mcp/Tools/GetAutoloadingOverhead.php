<?php

namespace Codemonkey\SPXMcpServer\Mcp\Tools;

use Illuminate\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;

class GetAutoloadingOverhead extends ProfileAnalysisTool
{
    protected string $description = 'Analyze autoloading overhead including Composer autoload, include, require operations.';

    public function handle(Request $request): Response
    {
        $profileKey = $request->get('profile_key');
        $limit = $request->get('limit', 30);

        $parser = $this->parseProfile($profileKey);
        if ($parser instanceof Response) {
            return $parser;
        }

        $autoloadStats = $parser->getAutoloadingOverhead();
        $summaryStats = $parser->getSummaryStats();

        $output = "=== Autoloading Overhead ===\n\n";
        $output .= sprintf("Total autoload time: %s\n", $this->formatTime($autoloadStats['total_time']));
        $output .= sprintf("Total autoload calls: %d\n", $autoloadStats['total_calls']);
        
        if ($summaryStats['total_time_us'] > 0) {
            $percentage = ($autoloadStats['total_time'] / $summaryStats['total_time_us']) * 100;
            $output .= sprintf("Percentage of total: %.2f%%\n", $percentage);
        }
        
        $output .= sprintf("\nTop %d Autoload Functions:\n", min($limit, count($autoloadStats['functions'])));
        
        foreach (array_slice($autoloadStats['functions'], 0, $limit) as $func) {
            $output .= sprintf("  %s\n", substr($func['name'], 0, 50));
            $output .= sprintf("    Time: %s\n", $this->formatTime($func['inclusive_time']));
            $output .= sprintf("    Calls: %d\n", $func['call_count']);
            if ($func['call_count'] > 0) {
                $output .= sprintf("    Avg: %s\n", $this->formatTime($func['inclusive_time'] / $func['call_count']));
            }
            $output .= "\n";
        }

        return Response::text($output);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'profile_key' => $schema->string()->description('The SPX profile key to analyze.')->required(),
            'limit' => $schema->integer()->description('Number of functions to return (default: 30)')->default(30),
        ];
    }
}

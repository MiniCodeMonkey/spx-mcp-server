<?php

namespace Codemonkey\SPXMcpServer\Mcp\Tools;

use Illuminate\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;

class GetDatabaseQueries extends ProfileAnalysisTool
{
    protected string $description = 'Analyze database query performance including SELECT, INSERT, UPDATE, DELETE operations and detect potential N+1 query problems.';

    public function handle(Request $request): Response
    {
        $profileKey = $request->get('profile_key');

        $parser = $this->parseProfile($profileKey);
        if ($parser instanceof Response) {
            return $parser;
        }

        $queries = $parser->getDatabaseQueries();
        $summaryStats = $parser->getSummaryStats();

        $output = "=== Database Query Analysis ===\n\n";
        $output .= sprintf("Total database time: %s\n", $this->formatTime($queries['total_time']));
        $output .= sprintf("Total queries: %d\n", $queries['total_queries']);

        if ($summaryStats['total_time_us'] > 0) {
            $percentage = ($queries['total_time'] / $summaryStats['total_time_us']) * 100;
            $output .= sprintf("Percentage of total: %.2f%%\n", $percentage);
        }

        $output .= "\nOperations Breakdown:\n";
        foreach ($queries['operations'] as $opType => $operations) {
            if (empty($operations)) continue;
            $opTime = array_sum(array_column($operations, 'exclusive_time'));
            $opCalls = array_sum(array_column($operations, 'call_count'));
            $output .= sprintf("  %s: %d calls, %s\n",
                strtoupper($opType), $opCalls, $this->formatTime($opTime));
        }

        if (!empty($queries['potential_n_plus_one'])) {
            $output .= "\n⚠️  Potential N+1 Queries Detected:\n";
            foreach ($queries['potential_n_plus_one'] as $func) {
                $output .= sprintf("  %s\n", substr($func['name'], 0, 50));
                $output .= sprintf("    Calls: %d (may indicate N+1 problem)\n", $func['call_count']);
                $output .= sprintf("    Total time: %s\n", $this->formatTime($func['exclusive_time']));
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
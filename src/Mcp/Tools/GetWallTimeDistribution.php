<?php

namespace Codemonkey\SPXMcpServer\Mcp\Tools;

use Illuminate\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;

class GetWallTimeDistribution extends ProfileAnalysisTool
{
    /**
     * The tool's description.
     */
    protected string $description = 'Analyze wall time distribution showing I/O wait vs CPU time. Functions are categorized as IO-heavy (>70% I/O), balanced (30-70%), or CPU-heavy (<30% I/O).';

    /**
     * Handle the tool request.
     */
    public function handle(Request $request): Response
    {
        $profileKey = $request->get('profile_key');
        $limit = $request->get('limit', 20);

        $parser = $this->parseProfile($profileKey);
        if ($parser instanceof Response) {
            return $parser;
        }

        $distribution = $parser->getWallTimeDistribution();

        $output = "=== Wall Time Distribution ===\n\n";

        foreach ($distribution as $category => $functions) {
            $categoryName = str_replace('_', ' ', ucfirst($category));
            $output .= sprintf("%s Functions (%d total, showing %d):\n",
                $categoryName, count($functions), min($limit, count($functions)));

            foreach (array_slice($functions, 0, $limit) as $func) {
                $output .= sprintf("  %s\n", substr($func['name'], 0, 50));
                $output .= sprintf("    Calls: %d\n", $func['call_count']);
                if ($func['call_count'] > 0) {
                    $avgWallTime = $func['inclusive_time'] / $func['call_count'];
                    $avgCpuTime = $func['inclusive_cpu'] / $func['call_count'];
                    $avgIoWait = $func['io_wait_time'] / $func['call_count'];
                    $output .= sprintf("    Avg wall time: %s\n", $this->formatTime($avgWallTime));
                    $output .= sprintf("    Avg CPU time: %s\n", $this->formatTime($avgCpuTime));
                    $output .= sprintf("    Avg I/O wait: %s (%.1f%%)\n",
                        $this->formatTime($avgIoWait),
                        $func['io_percentage']);
                }
                $output .= sprintf("    Total wall time (exclusive): %s\n", $this->formatTime($func['exclusive_time']));
                $output .= "\n";
            }
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
            'limit' => $schema->integer()
                ->description('Number of functions to show per category (default: 20)')
                ->default(20),
        ];
    }
}
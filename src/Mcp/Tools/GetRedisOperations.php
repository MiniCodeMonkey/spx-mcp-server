<?php

namespace Codemonkey\SPXMcpServer\Mcp\Tools;

use Illuminate\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;

class GetRedisOperations extends ProfileAnalysisTool
{
    protected string $description = 'Analyze Redis/cache operations including GET, SET, DEL operations and their performance impact.';

    public function handle(Request $request): Response
    {
        $profileKey = $request->get('profile_key');

        $parser = $this->parseProfile($profileKey);
        if ($parser instanceof Response) {
            return $parser;
        }

        $redis = $parser->getRedisOperations();

        if ($redis['total_operations'] === 0) {
            return Response::text("No Redis operations detected in this profile.\n");
        }

        $output = "=== Redis Operations Analysis ===\n\n";
        $output .= sprintf("Total Redis time: %s\n", $this->formatTime($redis['total_time']));
        $output .= sprintf("Total operations: %d\n", $redis['total_operations']);
        $output .= sprintf("Avg time per operation: %s\n",
            $this->formatTime($redis['total_time'] / $redis['total_operations']));

        $output .= "\nOperations Breakdown:\n";
        foreach ($redis['operations'] as $opType => $operations) {
            if (empty($operations)) continue;
            $opTime = array_sum(array_column($operations, 'inclusive_time'));
            $opCalls = array_sum(array_column($operations, 'call_count'));
            $output .= sprintf("  %s: %d calls, %s\n",
                strtoupper($opType), $opCalls, $this->formatTime($opTime));
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
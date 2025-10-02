<?php

namespace Codemonkey\SPXMcpServer\Mcp\Tools;

use Illuminate\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;

class GetThirdPartyPackageImpact extends ProfileAnalysisTool
{
    protected string $description = 'Analyze the performance impact of third-party packages by grouping functions by vendor/namespace.';

    public function handle(Request $request): Response
    {
        $profileKey = $request->get('profile_key');
        $limit = $request->get('limit', 20);

        $parser = $this->parseProfile($profileKey);
        if ($parser instanceof Response) {
            return $parser;
        }

        $packages = $parser->getThirdPartyPackageImpact();
        $summaryStats = $parser->getSummaryStats();

        $output = sprintf("=== Third-Party Package Impact (Top %d of %d) ===\n\n",
            min($limit, count($packages)), count($packages));

        foreach (array_slice($packages, 0, $limit, true) as $packageName => $package) {
            $output .= sprintf("%s\n", $packageName);
            $output .= sprintf("  Total time: %s\n", $this->formatTime($package['total_time']));

            if ($summaryStats['total_time_us'] > 0) {
                $percentage = ($package['total_time'] / $summaryStats['total_time_us']) * 100;
                $output .= sprintf("  Percentage: %.2f%%\n", $percentage);
            }

            $output .= sprintf("  Total calls: %d\n", $package['total_calls']);
            $output .= sprintf("  Functions: %d\n", count($package['functions']));
            $output .= "\n";
        }

        return Response::text($output);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'profile_key' => $schema->string()->description('The SPX profile key to analyze.')->required(),
            'limit' => $schema->integer()->description('Number of packages to return (default: 20)')->default(20),
        ];
    }
}
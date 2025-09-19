<?php

namespace Codemonkey\SpxMcpServer\Mcp\Tools;

use Illuminate\JsonSchema\JsonSchema;
use Illuminate\Support\Collection;
use JsonException;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;

class ListProfiles extends Tool
{
    /**
     * The tool's description.
     */
    protected string $description = 'Lists SPX profile dumps and optionally filter by URL and wall time.';

    /**
     * Handle the tool request.
     */
    public function handle(Request $request): Response
    {
        $url = $request->get('url');
        $minWallTime = $request->get('min_wall_time', 0);

        $directory = config('spx-mcp.spx_data_dir');

        if (!is_dir($directory)) {
            return Response::error('SPX data directory (' . $directory . ') does not exist.');
        }

        $profiles = $this->getProfiles($directory)
            ->filter(function ($profile) use ($url, $minWallTime) {
                if ($url && (!isset($profile['http_request_uri']) || stripos($profile['http_request_uri'], $url) === false)) {
                    return false;
                }
                if ($minWallTime && (!isset($profile['wall_time_ms']) || $profile['wall_time_ms'] < (int)$minWallTime)) {
                    return false;
                }
                return true;
            });

        if ($profiles->isEmpty()) {
            return Response::text('No profiles found matching the criteria.');
        }

        $profileDescriptions = $profiles->map(function ($profile) {
            return sprintf(
                "Profile Key: %s\nExecuted At: %s\nHost: %s\nPID: %d\nTID: %d\nURL: %s\nWall Time (ms): %d\nPeak Memory (bytes): %d\nCalled Functions: %d\nCall Count: %d\n",
                $profile['key'] ?? 'N/A',
                isset($profile['exec_ts']) ? date('Y-m-d H:i:s', $profile['exec_ts']) : 'N/A',
                $profile['host_name'] ?? 'N/A',
                $profile['process_pid'] ?? 0,
                $profile['process_tid'] ?? 0,
                $profile['http_request_uri'] ?? 'N/A',
                $profile['wall_time_ms'] ?? 0,
                $profile['peak_memory_usage'] ?? 0,
                $profile['called_function_count'] ?? 0,
                $profile['call_count'] ?? 0
            );
        });

        return Response::text('The following SPX profile dumps were found: ' . PHP_EOL . $profileDescriptions->join("\n---\n"));
    }

    /**
     * Get the tool's input schema.
     *
     * @return array<string, \Illuminate\JsonSchema\JsonSchema>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'url' => $schema->string()
                ->description('The URL to get a profile for (wildcard search).'),
            'min_wall_time' => $schema->string()
                ->description('The minimum wall time in ms to return profiles for.')
        ];
    }

    private function getProfiles(string $directory): Collection
    {
        return collect(scandir($directory))
            ->filter(fn($file) => str_ends_with($file, '.json'))
            ->map(function ($file) use ($directory) {
                try {
                    return json_decode(file_get_contents($directory . '/' . $file), true, 512, JSON_THROW_ON_ERROR);
                } catch (JsonException) {
                    return null;
                }
            })->filter();
    }
}
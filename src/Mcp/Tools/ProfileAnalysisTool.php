<?php

namespace Codemonkey\SPXMcpServer\Mcp\Tools;

use Codemonkey\SPXMcpServer\Mcp\SPX\ProfileParser;
use Illuminate\JsonSchema\JsonSchema;
use JsonException;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;
use Throwable;

abstract class ProfileAnalysisTool extends Tool
{
    /**
     * Parse profile and return the ProfileParser instance
     */
    protected function parseProfile(string $profileKey): ProfileParser|Response
    {
        $this->log('Parsing profile with key: ' . $profileKey);
        $metaFile = config('spx-mcp.spx_data_dir') . '/' . $profileKey . '.json';
        if (!file_exists($metaFile)) {
            return Response::error('Profile with key ' . $profileKey . ' not found.');
        }

        $this->log('Found metadata file: ' . $metaFile);

        try {
            $metadata = json_decode(file_get_contents($metaFile), true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            return Response::error('Failed to decode profile data: ' . $e->getMessage());
        }

        $this->log('Parsing metadata: ' . print_r($metadata, true));

        try {
            $parser = new ProfileParser($metadata['enabled_metrics'] ?? []);
            $this->log('Initialized parser, now parsing data file');
            $parser->parse(config('spx-mcp.spx_data_dir') . '/' . $profileKey . '.txt.gz');
            $this->log('Parsed profile data successfully');
            return $parser;
        } catch (Throwable $e) {
            $this->log('Error parsing profile data: ' . $e->getMessage());
            return Response::error('Failed to parse profile data file: ' . $e->getMessage());
        }
    }

    /**
     * Get base schema with common fields
     */
    protected function getBaseSchema(JsonSchema $schema): array
    {
        return [
            'profile_key' => $schema->string()
                ->description('The SPX profile key to analyze. Example: `spx-full-20250919_114821-b9eab65bde08-85-96354734`')
                ->required(),
            'limit' => $schema->integer()
                ->description('Number of functions to return (default: 50)')
                ->default(50),
        ];
    }

    /**
     * Format time in microseconds to human readable format
     */
    protected function formatTime(float $microseconds): string
    {
        if ($microseconds < 1000) {
            return sprintf('%.2fμs', $microseconds);
        }

        if ($microseconds < 1000000) {
            return sprintf('%.2fms', $microseconds / 1000);
        }

        return sprintf('%.2fs', $microseconds / 1000000);
    }

    /**
     * Format memory in bytes to human readable format
     */
    protected function formatMemory(float $bytes): string
    {
        if ($bytes < 0) {
            $sign = '-';
            $bytes = abs($bytes);
        } else {
            $sign = '';
        }

        if ($bytes < 1024) {
            return sprintf('%s%dB', $sign, $bytes);
        }

        if ($bytes < 1048576) {
            return sprintf('%s%.2fKB', $sign, $bytes / 1024);
        }

        if ($bytes >= 1073741824) {
            return sprintf('%s%.2fGB', $sign, $bytes / 1073741824);
        }

        return sprintf('%s%.2fMB', $sign, $bytes / 1048576);
    }

    /**
     * Format function statistics for output
     */
    protected function formatFunctionStats(array $functions, string $title, string $sortMetric): string
    {
        $limit = count($functions);
        $output = "=== {$title} (Top {$limit}) ===\n";

        foreach ($functions as $i => $func) {
            $output .= sprintf("%2d. %-50s\n",
                $i + 1,
                substr($func['name'], 0, 50)
            );

            if (in_array($sortMetric, ['exclusive_time', 'inclusive_time'])) {
                $output .= sprintf("    Exclusive time: %s\n", $this->formatTime($func['exclusive_time']));
                $output .= sprintf("    Inclusive time: %s\n", $this->formatTime($func['inclusive_time']));
            }

            if (in_array($sortMetric, ['exclusive_memory', 'inclusive_memory'])) {
                $output .= sprintf("    Exclusive memory: %s\n", $this->formatMemory($func['exclusive_memory']));
                $output .= sprintf("    Inclusive memory: %s\n", $this->formatMemory($func['inclusive_memory']));
            }

            if (in_array($sortMetric, ['exclusive_cpu', 'inclusive_cpu'])) {
                $output .= sprintf("    Exclusive CPU: %s\n", $this->formatTime($func['exclusive_cpu']));
                $output .= sprintf("    Inclusive CPU: %s\n", $this->formatTime($func['inclusive_cpu']));
            }

            $output .= sprintf("    Calls: %d\n", $func['call_count']);

            if ($func['call_count'] > 0) {
                $output .= sprintf("    Avg time per call: %s\n",
                    $this->formatTime($func['inclusive_time'] / $func['call_count']));
            }

            $output .= "\n";
        }

        return $output;
    }

    protected function log(string $line): void
    {
        file_put_contents(__DIR__ . '/../../spx-mcp.log', date('Y-m-d H:i:s') . ' ' . $line . "\n", FILE_APPEND);
    }
}
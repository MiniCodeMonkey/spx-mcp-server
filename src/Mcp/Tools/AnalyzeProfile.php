<?php

namespace Codemonkey\SPXMcpServer\Mcp\Tools;

use Codemonkey\SPXMcpServer\Mcp\SPX\ProfileParser;
use Illuminate\JsonSchema\JsonSchema;
use JsonException;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;
use Throwable;

class AnalyzeProfile extends Tool
{
    /**
     * The tool's description.
     */
    protected string $description = 'Analyzes a single SPX profile dump file and returns a summary of its contents.';

    /**
     * Handle the tool request.
     */
    public function handle(Request $request): Response
    {
        $profileKey = $request->get('profile_key');
        $metaFile = config('spx-mcp.spx_data_dir') . '/' . $profileKey . '.json';
        if (!file_exists($metaFile)) {
            return Response::error('Profile with key ' . $profileKey . ' not found.');
        }

        try {
            $metadata = json_decode(file_get_contents($metaFile), true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            return Response::error('Failed to decode profile data: ' . $e->getMessage());
        }

        try {
            $parser = new ProfileParser($metadata['enabled_metrics'] ?? []);
            $parser->parse(config('spx-mcp.spx_data_dir') . '/' . $profileKey . '.txt.gz');
        } catch (Throwable $e) {
            return Response::error('Failed to parse profile data file: ' . $e->getMessage());
        }

        return Response::text($this->printReport($parser));
    }

    /**
     * Get the tool's input schema.
     *
     * @return array<string, \Illuminate\JsonSchema\JsonSchema>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'profile_key' => $schema->string()
                ->description('The SPX profile key to analyze. Example: `spx-full-20250919_114821-b9eab65bde08-85-96354734`')
                ->required(),
        ];
    }

    /**
     * Format time in microseconds to human readable format
     */
    private function formatTime(float $microseconds): string
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
    private function formatMemory(float $bytes): string
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

    private function printReport(ProfileParser $parser): string
    {
        $output = '';
        $summary = $parser->getSummaryStats();

        $output .= "=== SPX Profile Summary ===\n";
        $output .= sprintf("Total Functions: %d\n", $summary['total_functions']);
        $output .= sprintf("Total Calls: %d\n", $summary['total_calls']);
        $output .= sprintf("Total Time: %s\n", $this->formatTime($summary['total_time_us']));
        $output .= sprintf("Peak Memory: %s\n\n", $this->formatMemory($summary['peak_memory_bytes']));

        $output .= "=== Top 10 Slowest Functions (by exclusive time) ===\n";
        foreach ($parser->getSlowestFunctions(10) as $i => $func) {
            $output .= sprintf("%2d. %-50s %s excl, %s incl, %d calls\n",
                $i + 1,
                substr($func['name'], 0, 50),
                $this->formatTime($func['exclusive_time']),
                $this->formatTime($func['inclusive_time']),
                $func['call_count']
            );
        }

        $output .= "\n=== Top 10 Most Called Functions ===\n";
        foreach ($parser->getMostCalledFunctions(10) as $i => $func) {
            $output .= sprintf("%2d. %-50s %d calls, %s avg\n",
                $i + 1,
                substr($func['name'], 0, 50),
                $func['call_count'],
                $this->formatTime($func['inclusive_time'] / $func['call_count'])
            );
        }

        $output .= "\n=== Top 10 Memory Users ===\n";
        foreach ($parser->getMemoryHogs(10) as $i => $func) {
            $output .= sprintf("%2d. %-50s %s incl, %s excl, %d calls\n",
                $i + 1,
                substr($func['name'], 0, 50),
                $this->formatMemory($func['inclusive_memory']),
                $this->formatMemory($func['exclusive_memory']),
                $func['call_count']
            );
        }

        return $output;
    }
}
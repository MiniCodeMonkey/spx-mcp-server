<?php

namespace Codemonkey\SPXMcpServer\Mcp\SPX;

use Exception;

class ProfileParser
{
    private array $functions = [];
    private array $events = [];
    private array $metrics = [];
    private array $functionStats = [];

    /**
     * @param array $enabledMetrics Associative array of metric_key => bool from JSON metadata
     */
    public function __construct(array $enabledMetrics)
    {
        $this->metrics = array_keys(array_filter($enabledMetrics));
    }

    /**
     * Parse an SPX profile data file
     *
     * @param string $filePath Path to the .txt.gz file
     * @throws Exception if file cannot be opened or parsed
     */
    public function parse(string $filePath): void
    {
        $handle = gzopen($filePath, 'r');
        if (!$handle) {
            throw new Exception("Cannot open file: $filePath");
        }

        $section = null;
        while (!gzeof($handle)) {
            $line = trim(gzgets($handle));

            if ($line === '[events]') {
                $section = 'events';
                continue;
            }
            if ($line === '[functions]') {
                $section = 'functions';
                continue;
            }

            if (empty($line)) continue;

            if ($section === 'events') {
                $this->parseEvent($line);
            } elseif ($section === 'functions') {
                $this->functions[] = $line;
            }
        }

        gzclose($handle);
        $this->calculateStats();
    }

    /**
     * Parse a single event line from the profile
     */
    private function parseEvent(string $line): void
    {
        $parts = explode(' ', $line);
        $funcIdx = (int)$parts[0];
        $isStart = $parts[1] === '1';

        $metrics = [];
        for ($i = 2; $i < count($parts); $i++) {
            $metricIdx = $i - 2;
            if ($metricIdx < count($this->metrics)) {
                $metricKey = $this->metrics[$metricIdx];
                $metrics[$metricKey] = (float)$parts[$i];
            }
        }

        $this->events[] = [
            'func_idx' => $funcIdx,
            'is_start' => $isStart,
            'metrics' => $metrics
        ];
    }

    /**
     * Calculate statistics from parsed events
     */
    private function calculateStats(): void
    {
        $callStack = [];

        foreach ($this->events as $event) {
            $funcIdx = $event['func_idx'];

            if (!isset($this->functionStats[$funcIdx])) {
                $this->functionStats[$funcIdx] = [
                    'name' => $this->functions[$funcIdx] ?? 'Unknown',
                    'call_count' => 0,
                    'inclusive_time' => 0,
                    'exclusive_time' => 0,
                    'inclusive_memory' => 0,
                    'exclusive_memory' => 0,
                    'inclusive_cpu' => 0,
                    'exclusive_cpu' => 0,
                ];
            }

            if ($event['is_start']) {
                // Push to stack
                $callStack[] = [
                    'func_idx' => $funcIdx,
                    'start_metrics' => $event['metrics'],
                    'children_time' => 0,
                    'children_memory' => 0,
                    'children_cpu' => 0,
                ];
                $this->functionStats[$funcIdx]['call_count']++;
            } else {
                // Pop from stack and calculate metrics
                if (empty($callStack)) {
                    throw new Exception("Call stack underflow at function index $funcIdx");
                }

                $frame = array_pop($callStack);
                if ($frame['func_idx'] !== $funcIdx) {
                    throw new Exception("Call stack mismatch! Expected {$frame['func_idx']}, got $funcIdx");
                }

                // Calculate inclusive metrics (total time/memory in function)
                $wallTime = ($event['metrics']['wt'] ?? 0) - ($frame['start_metrics']['wt'] ?? 0);
                $memory = ($event['metrics']['mu'] ?? 0) - ($frame['start_metrics']['mu'] ?? 0);
                $cpuTime = ($event['metrics']['ct'] ?? 0) - ($frame['start_metrics']['ct'] ?? 0);

                $this->functionStats[$funcIdx]['inclusive_time'] += $wallTime;
                $this->functionStats[$funcIdx]['inclusive_memory'] += $memory;
                $this->functionStats[$funcIdx]['inclusive_cpu'] += $cpuTime;

                // Calculate exclusive metrics (minus time in child calls)
                $exclusiveTime = $wallTime - $frame['children_time'];
                $exclusiveMemory = $memory - $frame['children_memory'];
                $exclusiveCpu = $cpuTime - $frame['children_cpu'];

                $this->functionStats[$funcIdx]['exclusive_time'] += $exclusiveTime;
                $this->functionStats[$funcIdx]['exclusive_memory'] += $exclusiveMemory;
                $this->functionStats[$funcIdx]['exclusive_cpu'] += $exclusiveCpu;

                // Update parent's children metrics
                if (!empty($callStack)) {
                    $parentIdx = count($callStack) - 1;
                    $callStack[$parentIdx]['children_time'] += $wallTime;
                    $callStack[$parentIdx]['children_memory'] += $memory;
                    $callStack[$parentIdx]['children_cpu'] += $cpuTime;
                }
            }
        }

        if (!empty($callStack)) {
            throw new Exception("Call stack not empty at end of processing - incomplete profile?");
        }
    }

    /**
     * Get the slowest functions by exclusive wall time
     *
     * @param int $limit Number of functions to return
     * @return array Function statistics sorted by exclusive time
     */
    public function getSlowestFunctions(int $limit = 50): array
    {
        $stats = $this->functionStats;
        usort($stats, fn($a, $b) => $b['exclusive_time'] <=> $a['exclusive_time']);
        return array_slice($stats, 0, $limit);
    }

    /**
     * Get the most frequently called functions
     *
     * @param int $limit Number of functions to return
     * @return array Function statistics sorted by call count
     */
    public function getMostCalledFunctions(int $limit = 50): array
    {
        $stats = $this->functionStats;
        usort($stats, fn($a, $b) => $b['call_count'] <=> $a['call_count']);
        return array_slice($stats, 0, $limit);
    }

    /**
     * Get functions with highest memory usage
     *
     * @param int $limit Number of functions to return
     * @return array Function statistics sorted by inclusive memory
     */
    public function getMemoryHogs(int $limit = 50): array
    {
        $stats = $this->functionStats;
        usort($stats, fn($a, $b) => $b['inclusive_memory'] <=> $a['inclusive_memory']);
        return array_slice($stats, 0, $limit);
    }

    /**
     * Get functions with highest CPU time
     *
     * @param int $limit Number of functions to return
     * @return array Function statistics sorted by exclusive CPU time
     */
    public function getCPUIntensiveFunctions(int $limit = 50): array
    {
        $stats = $this->functionStats;
        usort($stats, fn($a, $b) => $b['exclusive_cpu'] <=> $a['exclusive_cpu']);
        return array_slice($stats, 0, $limit);
    }

    /**
     * Get all function statistics
     *
     * @return array All function statistics
     */
    public function getAllFunctionStats(): array
    {
        return $this->functionStats;
    }

    /**
     * Get summary statistics for the entire profile
     *
     * @return array Summary statistics
     */
    public function getSummaryStats(): array
    {
        $totalCalls = 0;
        $totalFunctions = count($this->functionStats);
        $maxTime = 0;
        $maxMemory = 0;

        foreach ($this->functionStats as $stats) {
            $totalCalls += $stats['call_count'];
            $maxTime = max($maxTime, $stats['inclusive_time']);
            $maxMemory = max($maxMemory, $stats['inclusive_memory']);
        }

        return [
            'total_functions' => $totalFunctions,
            'total_calls' => $totalCalls,
            'total_time_us' => $maxTime,
            'total_time_ms' => $maxTime / 1000,
            'peak_memory_bytes' => $maxMemory,
            'peak_memory_mb' => $maxMemory / (1024 * 1024),
        ];
    }
}
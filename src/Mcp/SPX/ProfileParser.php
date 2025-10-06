<?php

namespace Codemonkey\SPXMcpServer\Mcp\SPX;

use Exception;

class ProfileParser
{
    private array $functions = [];
    private array $events = [];
    private array $metrics = [];
    private array $functionStats = [];
    private array $callTree = [];
    private array $timeline = [];
    private array $recursionTracking = [];

    /**
     * @param array $enabledMetrics Array of metric names or associative array of metric_key => bool from JSON metadata
     */
    public function __construct(array $enabledMetrics)
    {
        ini_set('memory_limit', '-1');
        // Handle both plain array ["wt", "ct", ...] and associative array ["wt" => true, "ct" => false, ...]
        if (array_keys($enabledMetrics) === range(0, count($enabledMetrics) - 1)) {
            // Plain array - use directly
            $this->metrics = $enabledMetrics;
        } else {
            // Associative array - filter and extract keys
            $this->metrics = array_keys(array_filter($enabledMetrics));
        }
    }

    protected function log(string $line): void
    {
        file_put_contents(__DIR__ . '/../../spx-mcp.log', date('Y-m-d H:i:s') . ' ' . $line . "\n", FILE_APPEND);
    }

    /**
     * Parse an SPX profile data file
     *
     * @param string $filePath Path to the .txt.gz file
     * @throws Exception if file cannot be opened or parsed
     */
    public function parse(string $filePath): void
    {
        $this->log('Parsing profile: ' . $filePath);
        $handle = gzopen($filePath, 'r');
        if (!$handle) {
            throw new Exception("Cannot open file: $filePath");
        }

        $section = null;
        while (!gzeof($handle)) {
            $line = trim(gzgets($handle));

            if ($line === '[events]') {
                $this->log('Got events');
                $section = 'events';
                continue;
            }
            if ($line === '[functions]') {
                $this->log('Got functions');
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

        $this->log('EOF');

        gzclose($handle);
        $this->log('Calculating stats');
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
        $callPath = [];
        $recursionStack = [];

        foreach ($this->events as $eventIdx => $event) {
            $funcIdx = $event['func_idx'];
            $funcName = $this->functions[$funcIdx] ?? 'Unknown';

            if (!isset($this->functionStats[$funcIdx])) {
                $this->functionStats[$funcIdx] = [
                    'name' => $funcName,
                    'call_count' => 0,
                    'inclusive_time' => 0,
                    'exclusive_time' => 0,
                    'inclusive_memory' => 0,
                    'exclusive_memory' => 0,
                    'inclusive_cpu' => 0,
                    'exclusive_cpu' => 0,
                    'io_wait_time' => 0,
                ];
            }

            if ($event['is_start']) {
                // Track recursion
                if (in_array($funcIdx, $recursionStack)) {
                    if (!isset($this->recursionTracking[$funcIdx])) {
                        $this->recursionTracking[$funcIdx] = [
                            'name' => $funcName,
                            'max_depth' => 0,
                            'total_recursive_calls' => 0,
                            'total_time' => 0,
                        ];
                    }
                    $depth = count(array_filter($recursionStack, fn($f) => $f === $funcIdx));
                    $this->recursionTracking[$funcIdx]['max_depth'] = max($this->recursionTracking[$funcIdx]['max_depth'], $depth);
                    $this->recursionTracking[$funcIdx]['total_recursive_calls']++;
                }
                $recursionStack[] = $funcIdx;

                // Build call tree
                $level = count($callStack);
                $parentIdx = !empty($callStack) ? $callStack[count($callStack) - 1]['func_idx'] : null;

                // Timeline entry
                $this->timeline[] = [
                    'event_idx' => $eventIdx,
                    'func_idx' => $funcIdx,
                    'func_name' => $funcName,
                    'is_start' => true,
                    'level' => $level,
                    'timestamp' => $event['metrics']['wt'] ?? 0,
                    'memory' => $event['metrics']['mu'] ?? 0,
                ];

                // Push to stack
                $callStack[] = [
                    'func_idx' => $funcIdx,
                    'start_metrics' => $event['metrics'],
                    'children_time' => 0,
                    'children_memory' => 0,
                    'children_cpu' => 0,
                    'level' => $level,
                    'parent_idx' => $parentIdx,
                ];
                $callPath[] = $funcName;
                $this->functionStats[$funcIdx]['call_count']++;

                // Build hierarchical call tree
                if (!isset($this->callTree[$funcIdx])) {
                    $this->callTree[$funcIdx] = [
                        'name' => $funcName,
                        'children' => [],
                        'call_paths' => [],
                    ];
                }
                $this->callTree[$funcIdx]['call_paths'][] = implode(' -> ', $callPath);
                if ($parentIdx !== null) {
                    $this->callTree[$parentIdx]['children'][$funcIdx] = true;
                }
            } else {
                // Pop recursion stack
                if (!empty($recursionStack)) {
                    array_pop($recursionStack);
                }

                // Pop from stack and calculate metrics
                if (empty($callStack)) {
                    throw new Exception("Call stack underflow at function index $funcIdx");
                }

                $frame = array_pop($callStack);
                array_pop($callPath);
                if ($frame['func_idx'] !== $funcIdx) {
                    throw new Exception("Call stack mismatch! Expected {$frame['func_idx']}, got $funcIdx");
                }

                // Calculate inclusive metrics (total time/memory in function)
                // Note: SPX metrics are in nanoseconds, convert to microseconds
                $wallTime = (($event['metrics']['wt'] ?? 0) - ($frame['start_metrics']['wt'] ?? 0)) / 1000;
                $memory = ($event['metrics']['mu'] ?? 0) - ($frame['start_metrics']['mu'] ?? 0);
                $cpuTime = (($event['metrics']['ct'] ?? 0) - ($frame['start_metrics']['ct'] ?? 0)) / 1000;

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

                // Calculate I/O wait time (wall time minus CPU time)
                $ioWaitTime = $wallTime - $cpuTime;
                $this->functionStats[$funcIdx]['io_wait_time'] += $ioWaitTime;

                // Update recursion tracking
                if (isset($this->recursionTracking[$funcIdx])) {
                    $this->recursionTracking[$funcIdx]['total_time'] += $exclusiveTime;
                }

                // Timeline entry
                $this->timeline[] = [
                    'event_idx' => $eventIdx,
                    'func_idx' => $funcIdx,
                    'func_name' => $funcName,
                    'is_start' => false,
                    'level' => $frame['level'],
                    'timestamp' => $event['metrics']['wt'] ?? 0,
                    'memory' => $event['metrics']['mu'] ?? 0,
                    'duration' => $wallTime,
                ];

                // Update parent's children metrics
                if (!empty($callStack)) {
                    $parentIdx = count($callStack) - 1;
                    $callStack[$parentIdx]['children_time'] += $wallTime;
                    $callStack[$parentIdx]['children_memory'] += $memory;
                    $callStack[$parentIdx]['children_cpu'] += $cpuTime;

                    // Handle recursion: subtract exclusive time from recursive parent's children
                    // This prevents double-counting in recursive scenarios
                    for ($k = count($callStack) - 1; $k >= 0; $k--) {
                        if ($callStack[$k]['func_idx'] === $funcIdx) {
                            $callStack[$k]['children_time'] -= $exclusiveTime;
                            $callStack[$k]['children_memory'] -= $exclusiveMemory;
                            $callStack[$k]['children_cpu'] -= $exclusiveCpu;
                            break;
                        }
                    }
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

    /**
     * Get functions sorted by exclusive time (wt - ct)
     *
     * @param int $limit Number of functions to return
     * @return array Function statistics sorted by exclusive time
     */
    public function getExclusiveTimeFunctions(int $limit = 50): array
    {
        $stats = array_map(function ($stat) {
            $stat['exclusive_time_calc'] = $stat['inclusive_time'] - $stat['inclusive_cpu'];
            return $stat;
        }, $this->functionStats);

        usort($stats, fn($a, $b) => $b['exclusive_time'] <=> $a['exclusive_time']);
        return array_slice($stats, 0, $limit);
    }

    /**
     * Get the call tree structure
     *
     * @return array Hierarchical call tree
     */
    public function getCallTree(): array
    {
        return $this->callTree;
    }

    /**
     * Get timeline view of function execution
     *
     * @return array Timeline of function calls
     */
    public function getTimelineView(): array
    {
        return $this->timeline;
    }

    /**
     * Get execution phases based on namespace patterns
     *
     * @return array Grouped execution phases
     */
    public function getExecutionPhases(): array
    {
        $phases = [
            'bootstrap' => [],
            'routing' => [],
            'middleware' => [],
            'controller' => [],
            'database' => [],
            'view' => [],
            'response' => [],
            'other' => [],
        ];

        foreach ($this->timeline as $entry) {
            if (!$entry['is_start']) continue;

            $funcName = $entry['func_name'];

            if (strpos($funcName, 'Composer\\Autoload') !== false ||
                strpos($funcName, 'Application::') !== false ||
                strpos($funcName, 'bootstrap') !== false) {
                $phases['bootstrap'][] = $entry;
            } elseif (strpos($funcName, 'Router::') !== false ||
                strpos($funcName, 'Route::') !== false ||
                strpos($funcName, 'Routing\\') !== false) {
                $phases['routing'][] = $entry;
            } elseif (strpos($funcName, 'Middleware::') !== false ||
                strpos($funcName, 'Middleware\\') !== false) {
                $phases['middleware'][] = $entry;
            } elseif (strpos($funcName, 'Controller::') !== false ||
                strpos($funcName, 'Controller\\') !== false ||
                strpos($funcName, 'Http\\Controllers\\') !== false) {
                $phases['controller'][] = $entry;
            } elseif (strpos($funcName, 'Database\\') !== false ||
                strpos($funcName, 'DB::') !== false ||
                strpos($funcName, 'Model::') !== false) {
                $phases['database'][] = $entry;
            } elseif (strpos($funcName, 'View::') !== false ||
                strpos($funcName, 'Blade::') !== false ||
                strpos($funcName, 'render') !== false) {
                $phases['view'][] = $entry;
            } elseif (strpos($funcName, 'Response::') !== false ||
                strpos($funcName, 'send') !== false) {
                $phases['response'][] = $entry;
            } else {
                $phases['other'][] = $entry;
            }
        }

        return $phases;
    }

    /**
     * Get recursive function calls
     *
     * @return array Functions with recursion statistics
     */
    public function getRecursiveFunctions(): array
    {
        return array_values($this->recursionTracking);
    }

    /**
     * Get wall time distribution (I/O vs CPU)
     *
     * @return array Functions grouped by I/O wait percentage
     */
    public function getWallTimeDistribution(): array
    {
        $distribution = [
            'io_heavy' => [],  // >70% I/O wait
            'balanced' => [],  // 30-70% I/O wait
            'cpu_heavy' => [], // <30% I/O wait
        ];

        foreach ($this->functionStats as $stat) {
            if ($stat['inclusive_time'] == 0) continue;

            $ioPercentage = ($stat['io_wait_time'] / $stat['inclusive_time']) * 100;
            $stat['io_percentage'] = $ioPercentage;

            if ($ioPercentage > 70) {
                $distribution['io_heavy'][] = $stat;
            } elseif ($ioPercentage > 30) {
                $distribution['balanced'][] = $stat;
            } else {
                $distribution['cpu_heavy'][] = $stat;
            }
        }

        // Sort each category by wall time
        foreach ($distribution as &$category) {
            usort($category, fn($a, $b) => $b['inclusive_time'] <=> $a['inclusive_time']);
        }

        return $distribution;
    }

    /**
     * Get autoloading overhead
     *
     * @return array Autoloading statistics
     */
    public function getAutoloadingOverhead(): array
    {
        $autoloadStats = [
            'total_time' => 0,
            'total_calls' => 0,
            'functions' => [],
        ];

        foreach ($this->functionStats as $funcIdx => $stat) {
            $funcName = $stat['name'];

            if (strpos($funcName, 'Composer\\Autoload') !== false ||
                strpos($funcName, '::loadClass') !== false ||
                strpos($funcName, 'include') !== false ||
                strpos($funcName, 'require') !== false ||
                strpos($funcName, 'spl_autoload_call') !== false) {

                $autoloadStats['total_time'] += $stat['exclusive_time'];
                $autoloadStats['total_calls'] += $stat['call_count'];
                $autoloadStats['functions'][] = $stat;
            }
        }

        // Sort functions by time
        usort($autoloadStats['functions'], fn($a, $b) => $b['inclusive_time'] <=> $a['inclusive_time']);

        return $autoloadStats;
    }

    /**
     * Get third-party package impact
     *
     * @return array Package statistics grouped by vendor
     */
    public function getThirdPartyPackageImpact(): array
    {
        $packages = [];

        foreach ($this->functionStats as $stat) {
            $funcName = $stat['name'];

            // Extract vendor/package from namespace
            if (preg_match('/^([A-Z][a-zA-Z0-9_]*\\\\[A-Z][a-zA-Z0-9_]*)\\\\/i', $funcName, $matches)) {
                $package = $matches[1];

                if (!isset($packages[$package])) {
                    $packages[$package] = [
                        'name' => $package,
                        'total_time' => 0,
                        'total_calls' => 0,
                        'functions' => [],
                    ];
                }

                $packages[$package]['total_time'] += $stat['exclusive_time'];
                $packages[$package]['total_calls'] += $stat['call_count'];
                $packages[$package]['functions'][] = $stat;
            }
        }

        // Sort packages by total time
        uasort($packages, fn($a, $b) => $b['total_time'] <=> $a['total_time']);

        return $packages;
    }

    /**
     * Get database query analysis
     *
     * @return array Database operation statistics
     */
    public function getDatabaseQueries(): array
    {
        $queries = [
            'total_time' => 0,
            'total_queries' => 0,
            'operations' => [
                'select' => [],
                'insert' => [],
                'update' => [],
                'delete' => [],
                'other' => [],
            ],
            'potential_n_plus_one' => [],
        ];

        foreach ($this->functionStats as $stat) {
            $funcName = $stat['name'];

            if (strpos($funcName, 'Illuminate\\Database') !== false ||
                strpos($funcName, 'DB::') !== false ||
                strpos($funcName, 'PDO::') !== false ||
                strpos($funcName, 'mysqli') !== false) {

                $queries['total_time'] += $stat['exclusive_time'];
                $queries['total_queries'] += $stat['call_count'];

                // Categorize by operation type
                if (stripos($funcName, 'select') !== false || stripos($funcName, 'get') !== false) {
                    $queries['operations']['select'][] = $stat;
                } elseif (stripos($funcName, 'insert') !== false || stripos($funcName, 'create') !== false) {
                    $queries['operations']['insert'][] = $stat;
                } elseif (stripos($funcName, 'update') !== false) {
                    $queries['operations']['update'][] = $stat;
                } elseif (stripos($funcName, 'delete') !== false) {
                    $queries['operations']['delete'][] = $stat;
                } else {
                    $queries['operations']['other'][] = $stat;
                }

                // Detect potential N+1 queries (high call count)
                if ($stat['call_count'] > 10 && stripos($funcName, 'select') !== false) {
                    $queries['potential_n_plus_one'][] = $stat;
                }
            }
        }

        return $queries;
    }

    /**
     * Get Redis operation analysis
     *
     * @return array Redis operation statistics
     */
    public function getRedisOperations(): array
    {
        $redis = [
            'total_time' => 0,
            'total_operations' => 0,
            'operations' => [
                'get' => [],
                'set' => [],
                'del' => [],
                'other' => [],
            ],
        ];

        foreach ($this->functionStats as $stat) {
            $funcName = $stat['name'];

            if (stripos($funcName, 'Redis') !== false ||
                stripos($funcName, 'Cache\\RedisStore') !== false ||
                stripos($funcName, 'Predis') !== false) {

                $redis['total_time'] += $stat['exclusive_time'];
                $redis['total_operations'] += $stat['call_count'];

                // Categorize by operation type
                if (stripos($funcName, 'get') !== false || stripos($funcName, 'fetch') !== false) {
                    $redis['operations']['get'][] = $stat;
                } elseif (stripos($funcName, 'set') !== false || stripos($funcName, 'put') !== false) {
                    $redis['operations']['set'][] = $stat;
                } elseif (stripos($funcName, 'del') !== false || stripos($funcName, 'forget') !== false) {
                    $redis['operations']['del'][] = $stat;
                } else {
                    $redis['operations']['other'][] = $stat;
                }
            }
        }

        return $redis;
    }

    /**
     * Get I/O operation analysis
     *
     * @return array I/O operation statistics categorized by type
     */
    public function getIOOperations(): array
    {
        $io = [
            'file' => [
                'total_time' => 0,
                'total_operations' => 0,
                'functions' => [],
            ],
            'network' => [
                'total_time' => 0,
                'total_operations' => 0,
                'functions' => [],
            ],
            'socket' => [
                'total_time' => 0,
                'total_operations' => 0,
                'functions' => [],
            ],
        ];

        foreach ($this->functionStats as $stat) {
            $funcName = $stat['name'];

            // File I/O
            if (preg_match('/^(file_|fread|fwrite|fopen|fclose|file_get_contents|file_put_contents|readfile|is_file|is_dir|scandir|glob)/i', $funcName)) {
                $io['file']['total_time'] += $stat['exclusive_time'];
                $io['file']['total_operations'] += $stat['call_count'];
                $io['file']['functions'][] = $stat;
            } // Network I/O
            elseif (stripos($funcName, 'curl_') !== false ||
                stripos($funcName, 'http') !== false ||
                stripos($funcName, 'guzzle') !== false ||
                stripos($funcName, 'RPC::call') !== false) {
                $io['network']['total_time'] += $stat['exclusive_time'];
                $io['network']['total_operations'] += $stat['call_count'];
                $io['network']['functions'][] = $stat;
            } // Socket I/O
            elseif (stripos($funcName, 'socket_') !== false ||
                stripos($funcName, 'SocketRelay') !== false ||
                stripos($funcName, 'stream_socket') !== false) {
                $io['socket']['total_time'] += $stat['exclusive_time'];
                $io['socket']['total_operations'] += $stat['call_count'];
                $io['socket']['functions'][] = $stat;
            }
        }

        // Sort functions by time within each category
        foreach ($io as &$category) {
            usort($category['functions'], fn($a, $b) => $b['inclusive_time'] <=> $a['inclusive_time']);
        }

        return $io;
    }

    /**
     * Get middleware analysis
     *
     * @return array Middleware execution statistics
     */
    public function getMiddlewareAnalysis(): array
    {
        $middleware = [
            'total_time' => 0,
            'total_calls' => 0,
            'middleware_list' => [],
            'execution_order' => [],
        ];

        foreach ($this->timeline as $entry) {
            if (!$entry['is_start']) continue;

            $funcName = $entry['func_name'];

            if (stripos($funcName, 'Middleware::handle') !== false ||
                stripos($funcName, 'Middleware::terminate') !== false ||
                preg_match('/[A-Z][a-zA-Z]*Middleware::/i', $funcName)) {

                // Extract middleware name
                $middlewareName = $funcName;
                if (preg_match('/([A-Z][a-zA-Z]*Middleware)/i', $funcName, $matches)) {
                    $middlewareName = $matches[1];
                }

                if (!isset($middleware['middleware_list'][$middlewareName])) {
                    $middleware['middleware_list'][$middlewareName] = [
                        'name' => $middlewareName,
                        'total_time' => 0,
                        'call_count' => 0,
                        'first_call_timestamp' => $entry['timestamp'],
                    ];
                }

                // Find corresponding function stats
                if (isset($this->functionStats[$entry['func_idx']])) {
                    $stat = $this->functionStats[$entry['func_idx']];
                    $middleware['middleware_list'][$middlewareName]['total_time'] += $stat['exclusive_time'];
                    $middleware['middleware_list'][$middlewareName]['call_count'] += $stat['call_count'];
                    $middleware['total_time'] += $stat['exclusive_time'];
                    $middleware['total_calls'] += $stat['call_count'];
                }

                $middleware['execution_order'][] = [
                    'name' => $middlewareName,
                    'timestamp' => $entry['timestamp'],
                    'level' => $entry['level'],
                ];
            }
        }

        // Sort middleware by execution order
        usort($middleware['execution_order'], fn($a, $b) => $a['timestamp'] <=> $b['timestamp']);

        return $middleware;
    }
}
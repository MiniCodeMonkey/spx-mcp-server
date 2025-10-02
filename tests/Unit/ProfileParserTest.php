<?php

use Codemonkey\SPXMcpServer\Mcp\SPX\ProfileParser;

it('can parse an SPX profile dump', function () {
    $jsonPath = __DIR__ . '/../fixtures/spx-full-20251002_141757-f9e4875aa584-90-1264812635.json';
    $profilePath = __DIR__ . '/../fixtures/spx-full-20251002_141757-f9e4875aa584-90-1264812635.txt.gz';

    // Load metadata from JSON
    $metadata = json_decode(file_get_contents($jsonPath), true);

    // Get enabled metrics from metadata
    $enabledMetrics = [];
    foreach ($metadata['enabled_metrics'] as $metric) {
        $enabledMetrics[$metric] = true;
    }

    // Create parser instance
    $parser = new ProfileParser($enabledMetrics);

    // Parse the profile - should not throw
    expect(fn() => $parser->parse($profilePath))->not->toThrow(Exception::class);

    // Verify we can get stats
    $summaryStats = $parser->getSummaryStats();
    expect($summaryStats)->toBeArray()
        ->toHaveKeys(['total_functions', 'total_calls', 'total_time_us', 'total_time_ms', 'peak_memory_bytes', 'peak_memory_mb']);

    // Verify counts are greater than 0
    expect($summaryStats['total_functions'])->toBeGreaterThan(0);
    expect($summaryStats['total_calls'])->toBeGreaterThan(0);
    expect($summaryStats['total_time_us'])->toBeGreaterThan(0);
    expect($summaryStats['total_time_ms'])->toBeGreaterThan(0);

    // Verify time conversion is correct (ms = us / 1000)
    expect($summaryStats['total_time_ms'])->toEqual($summaryStats['total_time_us'] / 1000);

    // Verify memory conversion is correct (MB = bytes / 1024 / 1024)
    expect($summaryStats['peak_memory_mb'])->toEqual($summaryStats['peak_memory_bytes'] / (1024 * 1024));

    // Get slowest functions - should return an array
    $slowest = $parser->getSlowestFunctions();
    expect($slowest)->toBeArray();

    // Get most called functions - should return an array
    $mostCalled = $parser->getMostCalledFunctions();
    expect($mostCalled)->toBeArray();

    // Get memory hogs - should return an array
    $memoryHogs = $parser->getMemoryHogs();
    expect($memoryHogs)->toBeArray();

    // Get CPU intensive functions - should return an array
    $cpuIntensive = $parser->getCPUIntensiveFunctions();
    expect($cpuIntensive)->toBeArray();
});
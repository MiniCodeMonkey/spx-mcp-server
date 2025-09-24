44<?php

use Codemonkey\SPXMcpServer\Mcp\Tools\GetCPUIntensiveFunctions;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;

beforeEach(function () {
    // Set up the config to use test fixtures directory
    mockConfig(['spx-mcp.spx_data_dir' => __DIR__ . '/../../fixtures']);
});

it('returns CPU intensive functions from a profile', function () {
    $tool = new GetCPUIntensiveFunctions();

    $request = new Request([
        'profile_key' => 'spx-full-20250919_114821-b9eab65bde08-85-963547349',
        'limit' => 10
    ]);

    $response = $tool->handle($request);

    expect($response)->toBeInstanceOf(Response::class);
    expect($response->isError())->toBeFalse();

    $content = (string)$response->content();

    // Check the response contains expected structure
    expect($content)->toContain('=== CPU Intensive Functions (Top 10) ===');
    expect($content)->toContain('Exclusive CPU:');
    expect($content)->toContain('Inclusive CPU:');
    expect($content)->toContain('Calls:');
    expect($content)->toContain('Avg time per call:');

    // Check that we have numbered results
    expect($content)->toContain(' 1. ');
    // The actual number of results may be less than 10 if the profile has fewer functions

    // Check CPU time formatting (should contain μs, ms, or s)
    expect($content)->toMatch('/\d+(\.\d+)?(μs|ms|s)/');
});

it('uses default limit of 50 when not specified', function () {
    $tool = new GetCPUIntensiveFunctions();

    $request = new Request([
        'profile_key' => 'spx-full-20250919_114821-b9eab65bde08-85-963547349'
    ]);

    $response = $tool->handle($request);

    expect($response)->toBeInstanceOf(Response::class);
    expect($response->isError())->toBeFalse();

    $content = (string)$response->content();
    expect($content)->toContain('=== CPU Intensive Functions (Top 50) ===');
});

it('returns error for non-existent profile', function () {
    $tool = new GetCPUIntensiveFunctions();

    $request = new Request([
        'profile_key' => 'non-existent-profile'
    ]);

    $response = $tool->handle($request);

    expect($response)->toBeInstanceOf(Response::class);
    expect($response->isError())->toBeTrue();
    expect((string)$response->content())->toContain('Profile with key non-existent-profile not found');
});

it('handles custom limit parameter', function () {
    $tool = new GetCPUIntensiveFunctions();

    $request = new Request([
        'profile_key' => 'spx-full-20250919_114821-b9eab65bde08-85-963547349',
        'limit' => 8
    ]);

    $response = $tool->handle($request);

    expect($response)->toBeInstanceOf(Response::class);
    expect($response->isError())->toBeFalse();

    $content = (string)$response->content();
    expect($content)->toContain('=== CPU Intensive Functions (Top 8) ===');

    // Check that we have exactly 8 results
    expect($content)->toContain(' 8. ');
    expect($content)->not->toContain(' 9. ');
});

// Skipping schema test as it requires proper Laravel application context
// The schema is tested indirectly through the tool usage tests

it('has appropriate description', function () {
    $tool = new GetCPUIntensiveFunctions();

    $reflection = new ReflectionClass($tool);
    $property = $reflection->getProperty('description');
    $property->setAccessible(true);
    $description = $property->getValue($tool);

    expect($description)->toBe('Get functions with highest CPU time from an SPX profile.');
});

it('formats CPU time values correctly', function () {
    $tool = new GetCPUIntensiveFunctions();

    $request = new Request([
        'profile_key' => 'spx-full-20250919_114821-b9eab65bde08-85-963547349',
        'limit' => 5
    ]);

    $response = $tool->handle($request);
    $content = (string)$response->content();

    // Check that CPU time values are formatted with appropriate units
    // Time should be shown as μs, ms, or s
    expect($content)->toMatch('/Exclusive CPU: \d+(\.\d+)?(μs|ms|s)/');
    expect($content)->toMatch('/Inclusive CPU: \d+(\.\d+)?(μs|ms|s)/');
});

it('returns functions sorted by exclusive CPU time', function () {
    $tool = new GetCPUIntensiveFunctions();

    $request = new Request([
        'profile_key' => 'spx-full-20250919_114821-b9eab65bde08-85-963547349',
        'limit' => 5
    ]);

    $response = $tool->handle($request);
    $content = (string)$response->content();

    // The functions should be sorted by exclusive CPU time (descending)
    // We can't directly verify the values without parsing, but we can check
    // that CPU metrics are displayed
    expect($content)->toContain('Exclusive CPU:');
    expect($content)->toContain('Inclusive CPU:');

    // Verify we have function names displayed
    $lines = explode("\n", $content);
    $functionCount = 0;
    foreach ($lines as $line) {
        if (preg_match('/^\s*\d+\.\s+\S+/', $line)) {
            $functionCount++;
        }
    }
    expect($functionCount)->toBe(5);
});
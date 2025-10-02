<?php

use Codemonkey\SPXMcpServer\Mcp\Tools\GetMemoryHogs;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;

beforeEach(function () {
    // Set up the config to use test fixtures directory
    mockConfig(['spx-mcp.spx_data_dir' => __DIR__ . '/../../fixtures']);
});

it('returns memory hog functions from a profile', function () {
    $tool = new GetMemoryHogs();

    $request = new Request([
        'profile_key' => 'spx-full-20251002_141757-f9e4875aa584-90-1264812635',
        'limit' => 10
    ]);

    $response = $tool->handle($request);

    expect($response)->toBeInstanceOf(Response::class);
    expect($response->isError())->toBeFalse();

    $content = (string)$response->content();

    // Check the response contains expected structure
    expect($content)->toContain('=== Top Memory Users (Top 10) ===');
    expect($content)->toContain('Exclusive memory:');
    expect($content)->toContain('Inclusive memory:');
    expect($content)->toContain('Calls:');
    expect($content)->toContain('Avg time per call:');

    // Check that we have numbered results
    expect($content)->toContain(' 1. ');
    // The actual number of results may be less than 10 if the profile has fewer functions

    // Check memory formatting (should contain KB, MB, or GB)
    expect($content)->toMatch('/\d+(\.\d+)?(B|KB|MB|GB)/');
});

it('uses default limit of 50 when not specified', function () {
    $tool = new GetMemoryHogs();

    $request = new Request([
        'profile_key' => 'spx-full-20251002_141757-f9e4875aa584-90-1264812635'
    ]);

    $response = $tool->handle($request);

    expect($response)->toBeInstanceOf(Response::class);
    expect($response->isError())->toBeFalse();

    $content = (string)$response->content();
    expect($content)->toContain('=== Top Memory Users (Top 50) ===');
});

it('returns error for non-existent profile', function () {
    $tool = new GetMemoryHogs();

    $request = new Request([
        'profile_key' => 'non-existent-profile'
    ]);

    $response = $tool->handle($request);

    expect($response)->toBeInstanceOf(Response::class);
    expect($response->isError())->toBeTrue();
    expect((string)$response->content())->toContain('Profile with key non-existent-profile not found');
});

it('handles custom limit parameter', function () {
    $tool = new GetMemoryHogs();

    $request = new Request([
        'profile_key' => 'spx-full-20251002_141757-f9e4875aa584-90-1264812635',
        'limit' => 7
    ]);

    $response = $tool->handle($request);

    expect($response)->toBeInstanceOf(Response::class);
    expect($response->isError())->toBeFalse();

    $content = (string)$response->content();
    expect($content)->toContain('=== Top Memory Users (Top 7) ===');

    // Check that we have exactly 7 results
    expect($content)->toContain(' 7. ');
    expect($content)->not->toContain(' 8. ');
});

// Skipping schema test as it requires proper Laravel application context
// The schema is tested indirectly through the tool usage tests

it('has appropriate description', function () {
    $tool = new GetMemoryHogs();

    $reflection = new ReflectionClass($tool);
    $property = $reflection->getProperty('description');
    $property->setAccessible(true);
    $description = $property->getValue($tool);

    expect($description)->toBe('Get functions with highest memory usage from an SPX profile.');
});

it('formats memory values correctly', function () {
    $tool = new GetMemoryHogs();

    $request = new Request([
        'profile_key' => 'spx-full-20251002_141757-f9e4875aa584-90-1264812635',
        'limit' => 5
    ]);

    $response = $tool->handle($request);
    $content = (string)$response->content();

    // Check that memory values are formatted with appropriate units
    // Memory should be shown as B, KB, MB, or GB
    expect($content)->toMatch('/Exclusive memory: -?\d+(\.\d+)?(B|KB|MB|GB)/');
    expect($content)->toMatch('/Inclusive memory: -?\d+(\.\d+)?(B|KB|MB|GB)/');
});
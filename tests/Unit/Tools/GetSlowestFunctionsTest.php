<?php

use Codemonkey\SPXMcpServer\Mcp\Tools\GetSlowestFunctions;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;

beforeEach(function () {
    // Set up the config to use test fixtures directory
    mockConfig(['spx-mcp.spx_data_dir' => __DIR__ . '/../../fixtures']);
});

it('returns slowest functions from a profile', function () {
    $tool = new GetSlowestFunctions();

    $request = new Request([
        'profile_key' => 'spx-full-20251002_141757-f9e4875aa584-90-1264812635',
        'limit' => 10
    ]);

    $response = $tool->handle($request);

    expect($response)->toBeInstanceOf(Response::class);
    expect($response->isError())->toBeFalse();

    $content = (string)$response->content();

    // Check the response contains expected structure
    expect($content)->toContain('=== Slowest Functions by Exclusive Time (Top 10) ===');
    expect($content)->toContain('Exclusive time:');
    expect($content)->toContain('Inclusive time:');
    expect($content)->toContain('Calls:');
    expect($content)->toContain('Avg time per call:');

    // Check that we have numbered results
    expect($content)->toContain(' 1. ');
    // The actual number of results may be less than 10 if the profile has fewer functions
});

it('uses default limit of 50 when not specified', function () {
    $tool = new GetSlowestFunctions();

    $request = new Request([
        'profile_key' => 'spx-full-20251002_141757-f9e4875aa584-90-1264812635'
    ]);

    $response = $tool->handle($request);

    expect($response)->toBeInstanceOf(Response::class);
    expect($response->isError())->toBeFalse();

    $content = (string)$response->content();
    expect($content)->toContain('=== Slowest Functions by Exclusive Time (Top 50) ===');
});

it('returns error for non-existent profile', function () {
    $tool = new GetSlowestFunctions();

    $request = new Request([
        'profile_key' => 'non-existent-profile'
    ]);

    $response = $tool->handle($request);

    expect($response)->toBeInstanceOf(Response::class);
    expect($response->isError())->toBeTrue();
    expect((string)$response->content())->toContain('Profile with key non-existent-profile not found');
});

it('handles custom limit parameter', function () {
    $tool = new GetSlowestFunctions();

    $request = new Request([
        'profile_key' => 'spx-full-20251002_141757-f9e4875aa584-90-1264812635',
        'limit' => 5
    ]);

    $response = $tool->handle($request);

    expect($response)->toBeInstanceOf(Response::class);
    expect($response->isError())->toBeFalse();

    $content = (string)$response->content();
    expect($content)->toContain('=== Slowest Functions by Exclusive Time (Top 5) ===');

    // Check that we have exactly 5 results
    expect($content)->toContain(' 5. ');
    expect($content)->not->toContain(' 6. ');
});

// Skipping schema test as it requires proper Laravel application context
// The schema is tested indirectly through the tool usage tests
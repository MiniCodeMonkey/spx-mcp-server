<?php

use Codemonkey\SPXMcpServer\Mcp\Tools\GetExclusiveTimeFunctions;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;

beforeEach(function () {
    mockConfig(['spx-mcp.spx_data_dir' => __DIR__ . '/../../fixtures']);
});

it('returns exclusive time functions from a profile', function () {
    $tool = new GetExclusiveTimeFunctions();

    $request = new Request([
        'profile_key' => 'spx-full-20251002_141757-f9e4875aa584-90-1264812635',
        'limit' => 10
    ]);

    $response = $tool->handle($request);

    expect($response)->toBeInstanceOf(Response::class);
    expect($response->isError())->toBeFalse();

    $content = (string)$response->content();
    expect($content)->toContain('=== Functions by Exclusive Time (Top 10) ===');
    expect($content)->toContain('Exclusive time:');
    expect($content)->toContain(' 1. ');
});

it('uses default limit when not specified', function () {
    $tool = new GetExclusiveTimeFunctions();

    $request = new Request([
        'profile_key' => 'spx-full-20251002_141757-f9e4875aa584-90-1264812635'
    ]);

    $response = $tool->handle($request);

    expect($response)->toBeInstanceOf(Response::class);
    expect($response->isError())->toBeFalse();
});

it('returns error for non-existent profile', function () {
    $tool = new GetExclusiveTimeFunctions();

    $request = new Request([
        'profile_key' => 'non-existent-profile'
    ]);

    $response = $tool->handle($request);

    expect($response)->toBeInstanceOf(Response::class);
    expect($response->isError())->toBeTrue();
});
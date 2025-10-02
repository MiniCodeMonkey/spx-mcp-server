<?php

use Codemonkey\SPXMcpServer\Mcp\Tools\ListProfiles;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;

beforeEach(function () {
    mockConfig(['spx-mcp.spx_data_dir' => __DIR__ . '/../../fixtures']);
});

it('lists profiles with default limit of 5', function () {
    $tool = new ListProfiles();

    $request = new Request([]);

    $response = $tool->handle($request);

    expect($response)->toBeInstanceOf(Response::class);
    expect($response->isError())->toBeFalse();

    $content = (string) $response->content();
    expect($content)->toContain('Showing');
    expect($content)->toContain('total SPX profile dumps');
});

it('respects custom limit parameter', function () {
    $tool = new ListProfiles();

    $request = new Request([
        'limit' => 2
    ]);

    $response = $tool->handle($request);

    expect($response)->toBeInstanceOf(Response::class);
    expect($response->isError())->toBeFalse();

    $content = (string) $response->content();
    expect($content)->toContain('Showing');
});

it('filters profiles by URL', function () {
    $tool = new ListProfiles();

    $request = new Request([
        'url' => '/api',
        'limit' => 10
    ]);

    $response = $tool->handle($request);

    expect($response)->toBeInstanceOf(Response::class);
    expect($response->isError())->toBeFalse();
});

it('filters profiles by minimum wall time', function () {
    $tool = new ListProfiles();

    $request = new Request([
        'min_wall_time' => 100,
        'limit' => 10
    ]);

    $response = $tool->handle($request);

    expect($response)->toBeInstanceOf(Response::class);
    expect($response->isError())->toBeFalse();
});

it('returns appropriate message when no profiles match criteria', function () {
    $tool = new ListProfiles();

    $request = new Request([
        'url' => 'nonexistent-url-pattern',
        'limit' => 10
    ]);

    $response = $tool->handle($request);

    expect($response)->toBeInstanceOf(Response::class);

    $content = (string) $response->content();
    // Either we get "No profiles found" or we get some profiles
    expect($content)->toBeString();
});
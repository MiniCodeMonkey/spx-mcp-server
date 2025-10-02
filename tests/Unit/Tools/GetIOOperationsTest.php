<?php

use Codemonkey\SPXMcpServer\Mcp\Tools\GetIOOperations;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;

beforeEach(function () {
    mockConfig(['spx-mcp.spx_data_dir' => __DIR__ . '/../../fixtures']);
});

it('returns I/O operations from a profile', function () {
    $tool = new GetIOOperations();

    $request = new Request([
        'profile_key' => 'spx-full-20251002_141757-f9e4875aa584-90-1264812635',
        'limit' => 5
    ]);

    $response = $tool->handle($request);

    expect($response)->toBeInstanceOf(Response::class);
    expect($response->isError())->toBeFalse();

    $content = (string)$response->content();
    expect($content)->toContain('===');
});

it('returns error for non-existent profile', function () {
    $tool = new GetIOOperations();

    $request = new Request([
        'profile_key' => 'non-existent-profile'
    ]);

    $response = $tool->handle($request);

    expect($response)->toBeInstanceOf(Response::class);
    expect($response->isError())->toBeTrue();
    expect((string)$response->content())->toContain('Profile with key non-existent-profile not found');
});

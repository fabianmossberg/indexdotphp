<?php

declare(strict_types=1);

use IndexDotPhp\Router\Response;

it('builds a 204 No Content response with an empty body', function () {
    $response = Response::noContent();

    expect($response->status())->toBe(204);
    expect($response->body())->toBe('');
});

it('builds a redirect response with the Location header', function () {
    $response = Response::redirect('/dashboard');

    expect($response->status())->toBe(302);
    expect($response->header('Location'))->toBe('/dashboard');
});

it('builds a permanent redirect (301) when the status arg is given', function () {
    $response = Response::redirect('/new', 301);

    expect($response->status())->toBe(301);
    expect($response->header('Location'))->toBe('/new');
});

it('lets Response::make() be customised via fluent setters', function () {
    $response = Response::make()
        ->withStatus(201)
        ->withItems(['id' => 7])
        ->withMessage('created');

    expect($response->status())->toBe(201);
    expect($response->body())->toBe('{"items":{"id":7},"message":["created"]}');
});

it('appends multiple messages via withMessage()', function () {
    $response = Response::ok(['x' => 1])
        ->withMessage('first')
        ->withMessage('second');

    expect($response->body())->toBe('{"items":{"x":1},"message":["first","second"]}');
});

it('renders meta into the envelope when withMeta() is used', function () {
    $response = Response::make()
        ->withItems([])
        ->withMeta(['total' => 84, 'page' => 1, 'size' => 20, 'pages' => 5]);

    expect($response->body())->toBe('{"items":[],"meta":{"total":84,"page":1,"size":20,"pages":5}}');
});

it('sets and reads custom headers via withHeader() and header()', function () {
    $response = Response::ok([])
        ->withHeader('X-Trace-Id', 'abc-123');

    expect($response->header('X-Trace-Id'))->toBe('abc-123');
    expect($response->headers())->toMatchArray(['X-Trace-Id' => 'abc-123']);
});

it('shortcuts Content-Type via withContentType()', function () {
    $response = Response::ok([])->withContentType('application/vnd.api+json');

    expect($response->header('Content-Type'))->toBe('application/vnd.api+json');
});

it('bypasses the JSON envelope entirely with withRaw()', function () {
    $response = Response::make()
        ->withStatus(200)
        ->withRaw("col1,col2\nval1,val2\n", 'text/csv');

    expect($response->body())->toBe("col1,col2\nval1,val2\n");
    expect($response->header('Content-Type'))->toBe('text/csv');
});

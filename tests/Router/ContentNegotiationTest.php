<?php

declare(strict_types=1);

use IndexDotPhp\Router\Request;
use IndexDotPhp\Router\Response;
use IndexDotPhp\Router\Router;
use IndexDotPhp\Router\ServerRequest;

it('accepts() returns true for an exact match in the Accept header', function () {
    $router = new Router();
    $router->get('/x', [], fn(): Response => Response::ok([
        'csv'  => Request::accepts('text/csv'),
        'json' => Request::accepts('application/json'),
    ]));

    $response = $router->dispatch(new ServerRequest(
        method:  'GET',
        path:    '/x',
        headers: ['Accept' => 'text/csv, application/json'],
    ));

    expect($response->body())->toBe('{"items":{"csv":true,"json":true}}');
});

it('accepts() honours type/* and */* wildcards', function () {
    $router = new Router();
    $router->get('/x', [], fn(): Response => Response::ok([
        'html'  => Request::accepts('text/html'),
        'plain' => Request::accepts('text/plain'),
        'json'  => Request::accepts('application/json'),
    ]));

    $response = $router->dispatch(new ServerRequest(
        method:  'GET',
        path:    '/x',
        headers: ['Accept' => 'text/*, */*;q=0.5'],
    ));

    expect($response->body())->toBe('{"items":{"html":true,"plain":true,"json":true}}');
});

it('accepts() returns false when the most-specific match has q=0', function () {
    $router = new Router();
    $router->get('/x', [], fn(): Response => Response::ok([
        'html' => Request::accepts('text/html'),
    ]));

    $response = $router->dispatch(new ServerRequest(
        method:  'GET',
        path:    '/x',
        headers: ['Accept' => 'text/html;q=0, text/*;q=1'],
    ));

    expect($response->body())->toBe('{"items":{"html":false}}');
});

it('accepts() returns true when no Accept header is present', function () {
    $router = new Router();
    $router->get('/x', [], fn(): Response => Response::ok([
        'json' => Request::accepts('application/json'),
    ]));

    $response = $router->dispatch(new ServerRequest(method: 'GET', path: '/x'));

    expect($response->body())->toBe('{"items":{"json":true}}');
});

it('preferredContentType() picks the highest-q supported type', function () {
    $router = new Router();
    $router->get('/x', [], fn(): Response => Response::ok([
        'pick' => Request::preferredContentType(['application/json', 'text/csv']),
    ]));

    $response = $router->dispatch(new ServerRequest(
        method:  'GET',
        path:    '/x',
        headers: ['Accept' => 'text/csv;q=0.5, application/json;q=0.9'],
    ));

    expect($response->body())->toBe('{"items":{"pick":"application/json"}}');
});

it('preferredContentType() returns null when nothing supported is acceptable', function () {
    $router = new Router();
    $router->get('/x', [], fn(): Response => Response::ok([
        'pick' => Request::preferredContentType(['application/xml']),
    ]));

    $response = $router->dispatch(new ServerRequest(
        method:  'GET',
        path:    '/x',
        headers: ['Accept' => 'text/html, application/json'],
    ));

    expect($response->body())->toBe('{"items":{"pick":null}}');
});

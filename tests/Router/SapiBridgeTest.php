<?php

declare(strict_types=1);

use IndexDotPhp\Router\Request;
use IndexDotPhp\Router\Response;
use IndexDotPhp\Router\Router;
use IndexDotPhp\Router\ServerRequest;

it('builds a ServerRequest from superglobal-style arrays via fromGlobals', function () {
    $req = ServerRequest::fromGlobals(
        server: [
            'REQUEST_METHOD'  => 'POST',
            'REQUEST_URI'     => '/api/users?page=2',
            'HTTP_X_TRACE_ID' => 'abc-123',
            'CONTENT_TYPE'    => 'application/json',
            'CONTENT_LENGTH'  => '42',
        ],
        get:     ['page' => '2'],
        cookies: ['session' => 'tok'],
        body:    '{"x":1}',
    );

    expect($req->method)->toBe('POST');
    expect($req->path)->toBe('/api/users');
    expect($req->query('page'))->toBe('2');
    expect($req->header('content-type'))->toBe('application/json');
    expect($req->header('x-trace-id'))->toBe('abc-123');
    expect($req->cookie('session'))->toBe('tok');
    expect($req->body())->toBe('{"x":1}');
});

it('Router::dispatch() with no args reads from $_SERVER and friends', function () {
    $_SERVER['REQUEST_METHOD'] = 'GET';
    $_SERVER['REQUEST_URI']    = '/echo';

    $router = new Router();
    $router->get('/echo', [], fn(): Response => Response::ok(['method' => Request::method()]));

    $response = $router->dispatch();

    expect($response->body())->toBe('{"items":{"method":"GET"}}');

    unset($_SERVER['REQUEST_METHOD'], $_SERVER['REQUEST_URI']);
});

it('Response::send() emits the body to the output buffer', function () {
    $response = Response::ok(['hello' => 'world']);

    ob_start();
    $response->send();
    $output = ob_get_clean();

    expect($output)->toBe('{"items":{"hello":"world"}}');
});

it('Router::dispatch()->send() chains end-to-end through the SAPI bridge', function () {
    $_SERVER['REQUEST_METHOD'] = 'GET';
    $_SERVER['REQUEST_URI']    = '/api';

    $router = new Router();
    $router->get('/api', [], fn(): Response => Response::ok(['ok' => true]));

    ob_start();
    $router->dispatch()->send();
    $output = ob_get_clean();

    expect($output)->toBe('{"items":{"ok":true}}');

    unset($_SERVER['REQUEST_METHOD'], $_SERVER['REQUEST_URI']);
});

<?php

declare(strict_types=1);

use IndexDotPhp\Router\Request;
use IndexDotPhp\Router\Response;
use IndexDotPhp\Router\Router;
use IndexDotPhp\Router\ServerRequest;

it('reads a query param via Request::query and falls back to the default', function () {
    $router = new Router();
    $router->get('/x', [], fn (): Response => Response::ok([
        'value'   => Request::query('q'),
        'default' => Request::query('missing', 'def'),
    ]));

    $response = $router->dispatch(new ServerRequest(
        method: 'GET',
        path:   '/x',
        query:  ['q' => 'hello'],
    ));

    expect($response->body())->toBe('{"data":{"value":"hello","default":"def"}}');
});

it('coerces query params to int via queryInt; non-digit input returns the default', function () {
    $router = new Router();
    $router->get('/x', [], fn (): Response => Response::ok([
        'page'   => Request::queryInt('page'),
        'broken' => Request::queryInt('broken', 99),
    ]));

    $response = $router->dispatch(new ServerRequest(
        method: 'GET',
        path:   '/x',
        query:  ['page' => '42', 'broken' => 'abc'],
    ));

    expect($response->body())->toBe('{"data":{"page":42,"broken":99}}');
});

it('coerces query params to bool via queryBool with case-insensitive truthy values', function () {
    $router = new Router();
    $router->get('/x', [], fn (): Response => Response::ok([
        'a' => Request::queryBool('a'),
        'b' => Request::queryBool('b'),
        'c' => Request::queryBool('c'),
        'd' => Request::queryBool('d', true),
    ]));

    $response = $router->dispatch(new ServerRequest(
        method: 'GET',
        path:   '/x',
        query:  ['a' => 'TRUE', 'b' => '1', 'c' => 'no'],
    ));

    expect($response->body())->toBe('{"data":{"a":true,"b":true,"c":false,"d":true}}');
});

it('returns the raw request body via Request::body()', function () {
    $router = new Router();
    $router->post('/x', [], fn (): Response => Response::ok(['raw' => Request::body()]));

    $response = $router->dispatch(new ServerRequest(
        method: 'POST',
        path:   '/x',
        body:   'hello world',
    ));

    expect($response->body())->toBe('{"data":{"raw":"hello world"}}');
});

it('parses a JSON body to an associative array via Request::bodyJson()', function () {
    $router = new Router();
    $router->post('/x', [], fn (): Response => Response::ok(Request::bodyJson()));

    $response = $router->dispatch(new ServerRequest(
        method: 'POST',
        path:   '/x',
        body:   '{"name":"alice","age":30}',
    ));

    expect($response->body())->toBe('{"data":{"name":"alice","age":30}}');
});

it('returns null from Request::bodyJson() when the body is empty', function () {
    $router = new Router();
    $router->post('/x', [], fn (): Response => Response::ok(['parsed' => Request::bodyJson()]));

    $response = $router->dispatch(new ServerRequest(method: 'POST', path: '/x', body: ''));

    expect($response->body())->toBe('{"data":{"parsed":null}}');
});

it('throws a JsonException from Request::bodyJson() when the body is malformed JSON', function () {
    $req = new ServerRequest(method: 'POST', path: '/x', body: '{not valid json');

    $req->bodyJson();
})->throws(JsonException::class);

it('looks up headers case-insensitively via Request::header()', function () {
    $router = new Router();
    $router->get('/x', [], fn (): Response => Response::ok([
        'lower' => Request::header('content-type'),
        'mixed' => Request::header('Content-Type'),
        'upper' => Request::header('CONTENT-TYPE'),
        'miss'  => Request::header('x-missing'),
    ]));

    $response = $router->dispatch(new ServerRequest(
        method:  'GET',
        path:    '/x',
        headers: ['Content-Type' => 'application/json'],
    ));

    expect($response->body())->toBe('{"data":{"lower":"application/json","mixed":"application/json","upper":"application/json","miss":null}}');
});

it('exposes method and path via the Request facade', function () {
    $router = new Router();
    $router->standardVerbs('/users/:id', [], fn (): Response => Response::ok([
        'method' => Request::method(),
        'path'   => Request::path(),
    ]));

    $response = $router->dispatch(new ServerRequest(method: 'PATCH', path: '/users/42'));

    expect($response->body())->toBe('{"data":{"method":"PATCH","path":"/users/42"}}');
});

it('returns all headers (lowercased) via Request::headers()', function () {
    $router = new Router();
    $router->get('/x', [], fn (): Response => Response::ok(Request::headers()));

    $response = $router->dispatch(new ServerRequest(
        method:  'GET',
        path:    '/x',
        headers: ['Content-Type' => 'application/json', 'X-Custom' => 'v'],
    ));

    expect($response->body())->toBe('{"data":{"content-type":"application/json","x-custom":"v"}}');
});

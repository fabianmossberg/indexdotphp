<?php

declare(strict_types=1);

use IndexDotPhp\Router\Request;
use IndexDotPhp\Router\Response;
use IndexDotPhp\Router\Router;
use IndexDotPhp\Router\ServerRequest;

it('runs the int decoder and coerces the matched param to a real int', function () {
    $router = new Router();
    $router->get('/users/:id', ['decode' => ['id' => 'int']], fn (): Response => Response::ok([
        'id'   => Request::param('id'),
        'type' => gettype(Request::param('id')),
    ]));

    $response = $router->dispatch(new ServerRequest(method: 'GET', path: '/users/42'));

    expect($response->body())->toBe('{"data":{"id":42,"type":"integer"}}');
});

it('returns 404 when the int decoder rejects non-digit input', function () {
    $router = new Router();
    $router->get('/users/:id', ['decode' => ['id' => 'int']], fn (): Response => Response::ok([]));

    $response = $router->dispatch(new ServerRequest(method: 'GET', path: '/users/abc'));

    expect($response->status())->toBe(404);
    expect($response->body())->toBe('{"data":null,"error":{"status":404,"code":"ROUTE_NOT_FOUND","message":"Route not found"}}');
});

it('uses a custom decoder registered via registerDecoder', function () {
    $router = new Router();
    $router->registerDecoder(
        'hex',
        fn (string $s): ?string =>
        preg_match('/^[a-f0-9]+$/i', $s) ? strtolower($s) : null
    );
    $router->get('/x/:hash', ['decode' => ['hash' => 'hex']], fn (): Response => Response::ok([
        'hash' => Request::param('hash'),
    ]));

    $response = $router->dispatch(new ServerRequest(method: 'GET', path: '/x/DEADBEEF'));

    expect($response->body())->toBe('{"data":{"hash":"deadbeef"}}');
});

it('returns 400 when decode_failure is set to 400', function () {
    $router = new Router();
    $router->get('/users/:id', [
        'decode'         => ['id' => 'int'],
        'decode_failure' => 400,
    ], fn (): Response => Response::ok([]));

    $response = $router->dispatch(new ServerRequest(method: 'GET', path: '/users/abc'));

    expect($response->status())->toBe(400);
});

it('built-in slug decoder accepts valid slugs and rejects others', function () {
    $router = new Router();
    $router->get('/posts/:slug', ['decode' => ['slug' => 'slug']], fn (): Response => Response::ok([
        'slug' => Request::param('slug'),
    ]));

    $ok = $router->dispatch(new ServerRequest(method: 'GET', path: '/posts/my-cool-post-1'));
    expect($ok->status())->toBe(200);
    expect($ok->body())->toBe('{"data":{"slug":"my-cool-post-1"}}');

    $bad = $router->dispatch(new ServerRequest(method: 'GET', path: '/posts/UPPERCASE'));
    expect($bad->status())->toBe(404);
});

it('parses comma-separated lists via csv-int and csv-string', function () {
    $router = new Router();
    $router->get('/ints/:ids', ['decode' => ['ids'   => 'csv-int']], fn (): Response => Response::ok([
        'ids' => Request::param('ids'),
    ]));
    $router->get('/tags/:names', ['decode' => ['names' => 'csv-string']], fn (): Response => Response::ok([
        'names' => Request::param('names'),
    ]));

    $ints = $router->dispatch(new ServerRequest(method: 'GET', path: '/ints/1,2,3'));
    expect($ints->body())->toBe('{"data":{"ids":[1,2,3]}}');

    $tags = $router->dispatch(new ServerRequest(method: 'GET', path: '/tags/foo,bar'));
    expect($tags->body())->toBe('{"data":{"names":["foo","bar"]}}');

    $bad = $router->dispatch(new ServerRequest(method: 'GET', path: '/ints/1,abc,3'));
    expect($bad->status())->toBe(404);
});

it('no longer ships a no-op string decoder; using it raises Unknown decoder', function () {
    $router = new Router();
    $router->get('/x/:name', ['decode' => ['name' => 'string']], fn (): Response => Response::ok([]));

    $router->dispatch(new ServerRequest(method: 'GET', path: '/x/anything'));
})->throws(LogicException::class, 'Unknown decoder: string');

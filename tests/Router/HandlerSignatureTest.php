<?php

declare(strict_types=1);

use IndexDotPhp\Router\Request;
use IndexDotPhp\Router\Response;
use IndexDotPhp\Router\Router;
use IndexDotPhp\Router\ServerRequest;

it('invokes a no-arg handler that reads via the static facade', function () {
    $router = new Router();
    $router->get('/users/:id', [], fn (): Response => Response::ok([
        'id' => Request::param('id'),
    ]));

    $response = $router->dispatch(new ServerRequest(method: 'GET', path: '/users/42'));

    expect($response->body())->toBe('{"data":{"id":"42"}}');
});

it('invokes a handler that declares ServerRequest as its first parameter', function () {
    $router = new Router();
    $router->get('/users/:id', [], fn (ServerRequest $req): Response => Response::ok([
        'id' => $req->param('id'),
    ]));

    $response = $router->dispatch(new ServerRequest(method: 'GET', path: '/users/42'));

    expect($response->body())->toBe('{"data":{"id":"42"}}');
});

it('tolerates a middleware that ignores its $next parameter (short-circuit)', function () {
    $router = new Router();
    $router->use(fn (): Response => Response::ok(['short' => 'circuit']));
    $router->get('/x', [], fn (): Response => Response::ok(['handler' => 'unreached']));

    $response = $router->dispatch(new ServerRequest(method: 'GET', path: '/x'));

    expect($response->body())->toBe('{"data":{"short":"circuit"}}');
});

it('tolerates a middleware that declares no parameters at all', function () {
    $router = new Router();
    $router->use(fn (): Response => Response::ok(['no' => 'args']));
    $router->get('/x', [], fn (): Response => Response::ok([]));

    $response = $router->dispatch(new ServerRequest(method: 'GET', path: '/x'));

    expect($response->body())->toBe('{"data":{"no":"args"}}');
});

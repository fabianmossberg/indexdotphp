<?php

declare(strict_types=1);

use IndexDotPhp\Router\Request;
use IndexDotPhp\Router\Response;
use IndexDotPhp\Router\Router;
use IndexDotPhp\Router\ServerRequest;

it('matches a constrained dynamic segment when the value satisfies the regex', function () {
    $router = new Router();
    $router->get('/users/:id<\d+>', [], fn (): Response => Response::ok([
        'id' => Request::param('id'),
    ]));

    $response = $router->dispatch(new ServerRequest(method: 'GET', path: '/users/42'));

    expect($response->status())->toBe(200);
    expect($response->body())->toBe('{"items":{"id":"42"}}');
});

it('does not match a constrained segment when the value violates the regex', function () {
    $router = new Router();
    $router->get('/users/:id<\d+>', [], fn (): Response => Response::ok([]));

    $response = $router->dispatch(new ServerRequest(method: 'GET', path: '/users/abc'));

    expect($response->status())->toBe(404);
});

it('ranks a constrained segment above an unconstrained one for the same path', function () {
    $router = new Router();
    $router->get('/users/:slug', [], fn (): Response => Response::ok(['route' => 'slug']));
    $router->get('/users/:id<\d+>', [], fn (): Response => Response::ok(['route' => 'id']));

    $response = $router->dispatch(new ServerRequest(method: 'GET', path: '/users/42'));

    expect($response->body())->toBe('{"items":{"route":"id"}}');
});

it('captures across slashes when the constraint allows it (multi-segment)', function () {
    $router = new Router();
    $router->get('/files/:path<.+>', [], fn (): Response => Response::ok([
        'path' => Request::param('path'),
    ]));

    $response = $router->dispatch(new ServerRequest(method: 'GET', path: '/files/a/b/c'));

    expect($response->status())->toBe(200);
    expect($response->body())->toBe('{"items":{"path":"a/b/c"}}');
});

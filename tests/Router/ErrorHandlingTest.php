<?php

declare(strict_types=1);

use IndexDotPhp\Router\Request;
use IndexDotPhp\Router\Response;
use IndexDotPhp\Router\Router;
use IndexDotPhp\Router\ServerRequest;

it('returns the default 404 envelope when no route matches', function () {
    $router = new Router();

    $response = $router->dispatch(new ServerRequest(method: 'GET', path: '/nope'));

    expect($response->status())->toBe(404);
    expect($response->body())->toBe('{"data":null,"message":["route_not_found"]}');
});

it('returns the default 405 envelope when path matches but method does not', function () {
    $router = new Router();
    $router->get('/foo', [], fn (): Response => Response::ok([]));

    $response = $router->dispatch(new ServerRequest(method: 'POST', path: '/foo'));

    expect($response->status())->toBe(405);
    expect($response->body())->toBe('{"data":null,"message":["method_not_allowed"]}');
});

it('lets onError(404) override the default 404 handler', function () {
    $router = new Router();
    $router->onError(404, fn (): Response => Response::error(404, 'custom_not_found'));

    $response = $router->dispatch(new ServerRequest(method: 'GET', path: '/nope'));

    expect($response->status())->toBe(404);
    expect($response->body())->toBe('{"data":null,"message":["custom_not_found"]}');
});

it('exposes allowed_methods on the request attribute bag for 405 handlers', function () {
    $router = new Router();
    $router->get('/foo', [], fn (): Response => Response::ok([]));
    $router->put('/foo', [], fn (): Response => Response::ok([]));
    $router->onError(405, fn (): Response => Response::error(
        405,
        'allowed: ' . Request::attr('allowed_methods'),
    ));

    $response = $router->dispatch(new ServerRequest(method: 'POST', path: '/foo'));

    expect($response->status())->toBe(405);
    expect($response->body())->toContain('GET')->toContain('PUT');
});

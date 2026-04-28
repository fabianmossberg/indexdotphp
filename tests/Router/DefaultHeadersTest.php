<?php

declare(strict_types=1);

use IndexDotPhp\Router\Response;
use IndexDotPhp\Router\Router;
use IndexDotPhp\Router\ServerRequest;

it('applies default headers to a successful matched response', function () {
    $router = new Router();
    $router->defaultHeaders(['X-Powered-By' => 'He-Man']);
    $router->get('/x', [], fn (): Response => Response::ok([]));

    $response = $router->dispatch(new ServerRequest(method: 'GET', path: '/x'));

    expect($response->header('X-Powered-By'))->toBe('He-Man');
});

it('applies default headers to the default 404 response', function () {
    $router = new Router();
    $router->defaultHeaders(['X-Powered-By' => 'He-Man']);

    $response = $router->dispatch(new ServerRequest(method: 'GET', path: '/nope'));

    expect($response->status())->toBe(404);
    expect($response->header('X-Powered-By'))->toBe('He-Man');
});

it('applies default headers to the default 405 response', function () {
    $router = new Router();
    $router->defaultHeaders(['X-Powered-By' => 'He-Man']);
    $router->get('/foo', [], fn (): Response => Response::ok([]));

    $response = $router->dispatch(new ServerRequest(method: 'POST', path: '/foo'));

    expect($response->status())->toBe(405);
    expect($response->header('X-Powered-By'))->toBe('He-Man');
});

it('applies default headers to the OPTIONS auto-handler response', function () {
    $router = new Router();
    $router->defaultHeaders(['X-Powered-By' => 'He-Man']);
    $router->get('/foo', [], fn (): Response => Response::ok([]));

    $response = $router->dispatch(new ServerRequest(method: 'OPTIONS', path: '/foo'));

    expect($response->status())->toBe(204);
    expect($response->header('X-Powered-By'))->toBe('He-Man');
});

it('applies default headers to onException responses', function () {
    $router = new Router();
    $router->defaultHeaders(['X-Powered-By' => 'He-Man']);
    $router->onException(fn (Throwable $e): Response => Response::error(500, $e->getMessage()));
    $router->get('/boom', [], fn (): Response => throw new RuntimeException('kaboom'));

    $response = $router->dispatch(new ServerRequest(method: 'GET', path: '/boom'));

    expect($response->status())->toBe(500);
    expect($response->header('X-Powered-By'))->toBe('He-Man');
});

it('does not override a header that the response already set', function () {
    $router = new Router();
    $router->defaultHeaders(['X-Powered-By' => 'He-Man']);
    $router->get('/x', [], fn (): Response => Response::ok([])->withHeader('X-Powered-By', 'Skeletor'));

    $response = $router->dispatch(new ServerRequest(method: 'GET', path: '/x'));

    expect($response->header('X-Powered-By'))->toBe('Skeletor');
});

it('merges multiple defaultHeaders() calls', function () {
    $router = new Router();
    $router->defaultHeaders(['X-Powered-By' => 'He-Man']);
    $router->defaultHeaders(['X-Frame-Options' => 'DENY']);
    $router->get('/x', [], fn (): Response => Response::ok([]));

    $response = $router->dispatch(new ServerRequest(method: 'GET', path: '/x'));

    expect($response->header('X-Powered-By'))->toBe('He-Man');
    expect($response->header('X-Frame-Options'))->toBe('DENY');
});

it('lets sub-routers register defaults on the root', function () {
    $router = new Router();
    $api = $router->prefix('/api');
    $api->defaultHeaders(['X-Powered-By' => 'He-Man']);
    $api->get('/x', [], fn (): Response => Response::ok([]));

    $response = $router->dispatch(new ServerRequest(method: 'GET', path: '/api/x'));

    expect($response->header('X-Powered-By'))->toBe('He-Man');
});

it('attaches Router::stripHeaders() names to the dispatched response', function () {
    $router = new Router();
    $router->stripHeaders(['X-Powered-By', 'Server']);
    $router->get('/x', [], fn (): Response => Response::ok([]));

    $response = $router->dispatch(new ServerRequest(method: 'GET', path: '/x'));

    expect($response->strippedHeaders())->toBe(['X-Powered-By', 'Server']);
});

it('attaches stripHeaders to error responses too', function () {
    $router = new Router();
    $router->stripHeaders(['X-Powered-By']);

    $response = $router->dispatch(new ServerRequest(method: 'GET', path: '/nope'));

    expect($response->status())->toBe(404);
    expect($response->strippedHeaders())->toBe(['X-Powered-By']);
});

it('attaches stripHeaders to onException responses', function () {
    $router = new Router();
    $router->stripHeaders(['X-Powered-By']);
    $router->onException(fn (Throwable $e): Response => Response::error(500, $e->getMessage()));
    $router->get('/boom', [], fn (): Response => throw new RuntimeException('kaboom'));

    $response = $router->dispatch(new ServerRequest(method: 'GET', path: '/boom'));

    expect($response->strippedHeaders())->toBe(['X-Powered-By']);
});

it('deduplicates repeated stripHeaders() calls', function () {
    $router = new Router();
    $router->stripHeaders(['X-Powered-By']);
    $router->stripHeaders(['X-Powered-By', 'Server']);
    $router->get('/x', [], fn (): Response => Response::ok([]));

    $response = $router->dispatch(new ServerRequest(method: 'GET', path: '/x'));

    expect($response->strippedHeaders())->toBe(['X-Powered-By', 'Server']);
});

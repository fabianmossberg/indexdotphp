<?php

declare(strict_types=1);

use IndexDotPhp\Router\Middleware\Timing;
use IndexDotPhp\Router\Response;
use IndexDotPhp\Router\Router;
use IndexDotPhp\Router\ServerRequest;

it('adds a Server-Timing header to the response without breaking dispatch', function () {
    $router = new Router();
    $router->use(new Timing());
    $router->get('/x', [], fn (): Response => Response::ok(['ok' => true]));

    $response = $router->dispatch(new ServerRequest(method: 'GET', path: '/x'));

    expect($response->status())->toBe(200);
    expect($response->body())->toBe('{"data":{"ok":true}}');
    expect($response->header('Server-Timing'))->toMatch('/^total;dur=\d+(\.\d+)?$/');
});

it('records a positive duration when the handler does work', function () {
    $router = new Router();
    $router->use(new Timing());
    $router->get('/slow', [], function (): Response {
        usleep(1500);
        return Response::ok([]);
    });

    $response = $router->dispatch(new ServerRequest(method: 'GET', path: '/slow'));

    preg_match('/dur=([\d.]+)/', $response->header('Server-Timing'), $matches);
    expect((float) $matches[1])->toBeGreaterThan(0);
});

it('records named sub-spans alongside the total via Timing::measure()', function () {
    $router = new Router();
    $router->use(new Timing());
    $router->get('/x', [], function (): Response {
        Timing::measure('db', fn () => usleep(500));
        Timing::measure('render', fn () => usleep(500));
        return Response::ok([]);
    });

    $response = $router->dispatch(new ServerRequest(method: 'GET', path: '/x'));

    $header = $response->header('Server-Timing');
    expect($header)->toContain('db;dur=');
    expect($header)->toContain('render;dur=');
    expect($header)->toContain('total;dur=');
});

it('returns the closure result from Timing::measure()', function () {
    $router = new Router();
    $router->use(new Timing());
    $router->get('/x', [], fn (): Response => Response::ok([
        'value' => Timing::measure('compute', fn () => 6 * 7),
    ]));

    $response = $router->dispatch(new ServerRequest(method: 'GET', path: '/x'));

    expect($response->body())->toBe('{"data":{"value":42}}');
});

it('accumulates time when the same name is measured multiple times', function () {
    $router = new Router();
    $router->use(new Timing());
    $router->get('/x', [], function (): Response {
        Timing::measure('db', fn () => usleep(500));
        Timing::measure('db', fn () => usleep(500));
        return Response::ok([]);
    });

    $response = $router->dispatch(new ServerRequest(method: 'GET', path: '/x'));

    preg_match('/db;dur=([\d.]+)/', $response->header('Server-Timing'), $matches);
    expect((float) $matches[1])->toBeGreaterThan(0.5);
});

it('still records a Timing::measure() entry when the closure throws', function () {
    $router = new Router();
    $router->use(new Timing());
    $router->onException(fn (Throwable $e): Response => Response::error(500, $e->getMessage()));
    $router->get('/x', [], function (): Response {
        Timing::measure('failing', function () {
            usleep(200);
            throw new RuntimeException('boom');
        });
        return Response::ok([]);
    });

    $response = $router->dispatch(new ServerRequest(method: 'GET', path: '/x'));

    expect($response->status())->toBe(500);
});

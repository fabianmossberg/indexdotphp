<?php

declare(strict_types=1);

use IndexDotPhp\Router\Middleware\Timing;
use IndexDotPhp\Router\Response;
use IndexDotPhp\Router\Router;
use IndexDotPhp\Router\ServerRequest;

it('adds a Server-Timing header to the response without breaking dispatch', function () {
    $router = new Router();
    $router->use(new Timing());
    $router->get('/x', [], fn(): Response => Response::ok(['ok' => true]));

    $response = $router->dispatch(new ServerRequest(method: 'GET', path: '/x'));

    expect($response->status())->toBe(200);
    expect($response->body())->toBe('{"items":{"ok":true}}');
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

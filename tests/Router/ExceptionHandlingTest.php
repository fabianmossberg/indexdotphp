<?php

declare(strict_types=1);

use IndexDotPhp\Router\Response;
use IndexDotPhp\Router\Router;
use IndexDotPhp\Router\ServerRequest;

it('propagates handler exceptions when no onException is registered', function () {
    $router = new Router();
    $router->get('/foo', [], fn(): Response => throw new RuntimeException('boom'));

    $router->dispatch(new ServerRequest(method: 'GET', path: '/foo'));
})->throws(RuntimeException::class, 'boom');

it('routes handler exceptions through onException when one is registered', function () {
    $router = new Router();
    $router->onException(fn(Throwable $e): Response => Response::error(500, $e->getMessage()));
    $router->get('/foo', [], fn(): Response => throw new RuntimeException('boom'));

    $response = $router->dispatch(new ServerRequest(method: 'GET', path: '/foo'));

    expect($response->status())->toBe(500);
    expect($response->body())->toBe('{"items":null,"message":["boom"]}');
});

it('also catches exceptions thrown from error handlers', function () {
    $router = new Router();
    $router->onError(404, fn(): Response => throw new RuntimeException('handler_failed'));
    $router->onException(fn(Throwable $e): Response => Response::error(500, $e->getMessage()));

    $response = $router->dispatch(new ServerRequest(method: 'GET', path: '/nope'));

    expect($response->status())->toBe(500);
    expect($response->body())->toContain('handler_failed');
});

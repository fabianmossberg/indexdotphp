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
    expect($response->body())->toBe('{"data":null,"error":{"status":404,"code":"ROUTE_NOT_FOUND","message":"Route not found"}}');
});

it('returns the default 405 envelope when path matches but method does not', function () {
    $router = new Router();
    $router->get('/foo', [], fn (): Response => Response::ok([]));

    $response = $router->dispatch(new ServerRequest(method: 'POST', path: '/foo'));

    expect($response->status())->toBe(405);
    expect($response->body())->toBe('{"data":null,"error":{"status":405,"code":"METHOD_NOT_ALLOWED","message":"Method not allowed"}}');
});

it('lets onError(404) override the default 404 handler', function () {
    $router = new Router();
    $router->onError(404, fn (): Response => Response::error(404, 'custom_not_found'));

    $response = $router->dispatch(new ServerRequest(method: 'GET', path: '/nope'));

    expect($response->status())->toBe(404);
    expect($response->body())->toBe('{"data":null,"error":{"status":404,"code":"NOT_FOUND","message":"custom_not_found"}}');
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

it('runs the default error handler on a router-returned 404 and passes the ServerRequest', function () {
    $router = new Router();
    $captured = null;
    $router->onError(function (Response $r, ServerRequest $req) use (&$captured): Response {
        $captured = $req;
        return Response::raw('default:' . $r->status(), 'text/plain')->withStatus($r->status());
    });

    $response = $router->dispatch(new ServerRequest(method: 'GET', path: '/nope'));

    expect($captured)->toBeInstanceOf(ServerRequest::class);
    expect($captured->method)->toBe('GET');
    expect($captured->path)->toBe('/nope');
    expect($response->status())->toBe(404);
    expect($response->body())->toBe('default:404');
    expect($response->header('Content-Type'))->toBe('text/plain');
});

it('runs the default error handler on a handler-returned 4xx', function () {
    $router = new Router();
    $router->get('/teapot', [], fn (): Response => Response::error(418, 'short and stout'));
    $router->onError(fn (Response $r): Response => Response::raw('default:' . $r->status(), 'text/plain')->withStatus($r->status()));

    $response = $router->dispatch(new ServerRequest(method: 'GET', path: '/teapot'));

    expect($response->status())->toBe(418);
    expect($response->body())->toBe('default:418');
});

it('runs the status-specific handler before the default, and the default post-processes its result', function () {
    $router = new Router();
    $router->onError(404, fn (): Response => Response::error(404, 'specific_404'));
    $router->onError(fn (Response $r): Response => Response::raw('wrapped:' . $r->status(), 'text/plain')->withStatus($r->status()));

    $response = $router->dispatch(new ServerRequest(method: 'GET', path: '/nope'));

    expect($response->status())->toBe(404);
    expect($response->body())->toBe('wrapped:404');
});

it('skips the default error handler for 2xx responses', function () {
    $invocations = 0;
    $router = new Router();
    $router->get('/ok', [], fn (): Response => Response::ok(['hello' => 'world']));
    $router->onError(function (Response $r) use (&$invocations): Response {
        $invocations++;
        return $r;
    });

    $response = $router->dispatch(new ServerRequest(method: 'GET', path: '/ok'));

    expect($response->status())->toBe(200);
    expect($invocations)->toBe(0);
});

it('runs the default error handler on an onException response', function () {
    $router = new Router();
    $router->get('/boom', [], function (): Response {
        throw new \RuntimeException('boom');
    });
    $router->onException(fn (\Throwable $e): Response => Response::error(500, $e->getMessage()));
    $router->onError(fn (Response $r): Response => Response::raw('caught:' . $r->status(), 'text/plain')->withStatus($r->status()));

    $response = $router->dispatch(new ServerRequest(method: 'GET', path: '/boom'));

    expect($response->status())->toBe(500);
    expect($response->body())->toBe('caught:500');
});

it('does not re-process the default handler\'s own returned error response', function () {
    $invocations = 0;
    $router = new Router();
    $router->onError(function (Response $r) use (&$invocations): Response {
        $invocations++;
        return Response::error(503, 'service down', code: 'DOWN');
    });

    $response = $router->dispatch(new ServerRequest(method: 'GET', path: '/nope'));

    expect($invocations)->toBe(1);
    expect($response->status())->toBe(503);
    expect($response->body())->toContain('DOWN');
});

it('throws when onError is called with an int status but no handler', function () {
    $router = new Router();
    expect(fn () => $router->onError(404))->toThrow(\InvalidArgumentException::class);
});

it('throws when onError is called with a callable first argument and a second argument', function () {
    $router = new Router();
    $a = fn (Response $r): Response => $r;
    $b = fn (Response $r): Response => $r;
    expect(fn () => $router->onError($a, $b))->toThrow(\InvalidArgumentException::class);
});

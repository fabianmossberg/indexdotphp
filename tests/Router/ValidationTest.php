<?php

declare(strict_types=1);

use IndexDotPhp\Router\Response;
use IndexDotPhp\Router\Router;
use IndexDotPhp\Router\ServerRequest;

it('runs the handler when the validator returns null', function () {
    $validatorRan = false;
    $router = new Router();
    $router->post('/users', [
        'validate' => function (ServerRequest $req) use (&$validatorRan): ?array {
            $validatorRan = true;
            return null;
        },
    ], fn (): Response => Response::ok(['created' => true]));

    $response = $router->dispatch(new ServerRequest(method: 'POST', path: '/users', body: '{"x":1}'));

    expect($validatorRan)->toBeTrue();
    expect($response->status())->toBe(200);
    expect($response->body())->toBe('{"data":{"created":true}}');
});

it('returns a 422 with VALIDATION_FAILED when the validator returns an array', function () {
    $router = new Router();
    $router->post('/users', [
        'validate' => fn (ServerRequest $req): ?array => [
            'email' => 'is required',
            'age'   => 'must be a number',
        ],
    ], fn (): Response => Response::ok([]));

    $response = $router->dispatch(new ServerRequest(method: 'POST', path: '/users', body: '{}'));

    expect($response->status())->toBe(422);
    expect($response->body())->toBe(
        '{"data":{"email":"is required","age":"must be a number"},"error":{"status":422,"code":"VALIDATION_FAILED","message":"Validation failed"}}',
    );
});

it('does not invoke the handler when the validator returns errors', function () {
    $handlerRan = false;
    $router = new Router();
    $router->post('/users', [
        'validate' => fn (ServerRequest $req): ?array => ['_' => 'nope'],
    ], function () use (&$handlerRan): Response {
        $handlerRan = true;
        return Response::ok([]);
    });

    $router->dispatch(new ServerRequest(method: 'POST', path: '/users'));

    expect($handlerRan)->toBeFalse();
});

it('passes the ServerRequest to the validator so it can inspect body, query, headers', function () {
    $seenBody = null;
    $router = new Router();
    $router->post('/users', [
        'validate' => function (ServerRequest $req) use (&$seenBody): ?array {
            $seenBody = $req->bodyJson();
            return null;
        },
    ], fn (): Response => Response::ok([]));

    $router->dispatch(new ServerRequest(method: 'POST', path: '/users', body: '{"name":"alice"}'));

    expect($seenBody)->toBe(['name' => 'alice']);
});

it('runs the validator after per-route middleware', function () {
    $trail = [];
    $router = new Router();
    $router->post('/users', [
        'middleware' => [
            function (ServerRequest $req, callable $next) use (&$trail): Response {
                $trail[] = 'mw';
                return $next($req);
            },
        ],
        'validate' => function (ServerRequest $req) use (&$trail): ?array {
            $trail[] = 'validate';
            return null;
        },
    ], function () use (&$trail): Response {
        $trail[] = 'handler';
        return Response::ok([]);
    });

    $router->dispatch(new ServerRequest(method: 'POST', path: '/users'));

    expect($trail)->toBe(['mw', 'validate', 'handler']);
});

it('lets per-route middleware short-circuit before validation runs', function () {
    $validatorRan = false;
    $router = new Router();
    $router->post('/users', [
        'middleware' => [
            fn (ServerRequest $req, callable $next): Response => Response::error(401, 'unauthenticated', code: 'UNAUTHENTICATED'),
        ],
        'validate' => function (ServerRequest $req) use (&$validatorRan): ?array {
            $validatorRan = true;
            return null;
        },
    ], fn (): Response => Response::ok([]));

    $response = $router->dispatch(new ServerRequest(method: 'POST', path: '/users'));

    expect($response->status())->toBe(401);
    expect($validatorRan)->toBeFalse();
});

it('lets the validator return a nested error structure as the data payload', function () {
    $router = new Router();
    $router->post('/orders', [
        'validate' => fn (ServerRequest $req): ?array => [
            'items' => [
                ['index' => 0, 'message' => 'sku missing'],
                ['index' => 2, 'message' => 'qty must be positive'],
            ],
        ],
    ], fn (): Response => Response::ok([]));

    $response = $router->dispatch(new ServerRequest(method: 'POST', path: '/orders'));

    expect($response->status())->toBe(422);
    expect($response->body())->toContain('"items":[{"index":0,"message":"sku missing"}');
});

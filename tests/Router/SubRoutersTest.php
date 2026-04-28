<?php

declare(strict_types=1);

use IndexDotPhp\Router\Request;
use IndexDotPhp\Router\Response;
use IndexDotPhp\Router\Router;
use IndexDotPhp\Router\ServerRequest;

it('prepends the prefix to routes registered on a sub-router', function () {
    $router = new Router();
    $api = $router->prefix('/api');
    $api->get('/users', [], fn (): Response => Response::ok(['route' => 'users']));

    $response = $router->dispatch(new ServerRequest(method: 'GET', path: '/api/users'));

    expect($response->status())->toBe(200);
    expect($response->body())->toBe('{"items":{"route":"users"}}');
});

it('handles nested sub-routers with cumulative prefixes', function () {
    $router = new Router();
    $api = $router->prefix('/api');
    $v1  = $api->prefix('/v1');
    $v1->get('/users', [], fn (): Response => Response::ok(['nested' => true]));

    $response = $router->dispatch(new ServerRequest(method: 'GET', path: '/api/v1/users'));

    expect($response->status())->toBe(200);
    expect($response->body())->toBe('{"items":{"nested":true}}');
});

it('runs sub-router middleware after parent and before per-route middleware', function () {
    $router = new Router();
    $router->use(function (ServerRequest $req, callable $next): Response {
        $req->setAttr('trail', 'p,');
        return $next($req);
    });

    $api = $router->prefix('/api');
    $api->use(function (ServerRequest $req, callable $next): Response {
        $req->setAttr('trail', $req->attr('trail') . 'sub,');
        return $next($req);
    });

    $api->get('/x', [
        'middleware' => [
            function (ServerRequest $req, callable $next): Response {
                $req->setAttr('trail', $req->attr('trail') . 'route,');
                return $next($req);
            },
        ],
    ], fn (): Response => Response::ok(['trail' => Request::attr('trail') . 'handler']));

    $response = $router->dispatch(new ServerRequest(method: 'GET', path: '/api/x'));

    expect($response->body())->toBe('{"items":{"trail":"p,sub,route,handler"}}');
});

it('does not apply sub-router middleware to routes outside the sub-router', function () {
    $router = new Router();
    $api = $router->prefix('/api');
    $api->use(fn (ServerRequest $r, callable $next): Response => Response::error(401, 'denied'));

    $router->get('/public', [], fn (): Response => Response::ok(['public' => true]));

    $response = $router->dispatch(new ServerRequest(method: 'GET', path: '/public'));

    expect($response->status())->toBe(200);
    expect($response->body())->toBe('{"items":{"public":true}}');
});

it('uses the root error handlers for unmatched paths under a sub-router prefix', function () {
    $router = new Router();
    $api = $router->prefix('/api');
    $api->get('/users', [], fn (): Response => Response::ok([]));

    $response = $router->dispatch(new ServerRequest(method: 'GET', path: '/api/nope'));

    expect($response->status())->toBe(404);
    expect($response->body())->toBe('{"items":null,"message":["route_not_found"]}');
});

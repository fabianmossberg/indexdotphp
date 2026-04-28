<?php

declare(strict_types=1);

use IndexDotPhp\Router\Request;
use IndexDotPhp\Router\Response;
use IndexDotPhp\Router\Router;
use IndexDotPhp\Router\ServerRequest;

it('runs a single global middleware before the handler', function () {
    $router = new Router();
    $router->use(function (ServerRequest $req, callable $next): Response {
        $req->setAttr('mw', 'ran');
        return $next($req);
    });
    $router->get('/x', [], fn (): Response => Response::ok(['attr' => Request::attr('mw')]));

    $response = $router->dispatch(new ServerRequest(method: 'GET', path: '/x'));

    expect($response->body())->toBe('{"data":{"attr":"ran"}}');
});

it('runs multiple global middlewares in registration order', function () {
    $router = new Router();
    $router->use(function (ServerRequest $req, callable $next): Response {
        $req->setAttr('trail', $req->attr('trail', '') . 'A');
        return $next($req);
    });
    $router->use(function (ServerRequest $req, callable $next): Response {
        $req->setAttr('trail', $req->attr('trail', '') . 'B');
        return $next($req);
    });
    $router->get('/x', [], fn (): Response => Response::ok(['trail' => Request::attr('trail')]));

    $response = $router->dispatch(new ServerRequest(method: 'GET', path: '/x'));

    expect($response->body())->toBe('{"data":{"trail":"AB"}}');
});

it('lets middleware short-circuit by not calling next', function () {
    $router = new Router();
    $router->use(function (ServerRequest $req, callable $next): Response {
        return Response::error(401, 'denied');
    });
    $router->get('/x', [], fn (): Response => Response::ok(['handler' => 'reached']));

    $response = $router->dispatch(new ServerRequest(method: 'GET', path: '/x'));

    expect($response->status())->toBe(401);
    expect($response->body())->toBe('{"data":null,"message":["denied"]}');
});

it('runs per-route middleware after global middleware', function () {
    $router = new Router();
    $router->use(function (ServerRequest $req, callable $next): Response {
        $req->setAttr('trail', 'global,');
        return $next($req);
    });
    $router->get('/x', [
        'middleware' => [
            function (ServerRequest $req, callable $next): Response {
                $req->setAttr('trail', $req->attr('trail') . 'route,');
                return $next($req);
            },
        ],
    ], fn (): Response => Response::ok(['trail' => Request::attr('trail') . 'handler']));

    $response = $router->dispatch(new ServerRequest(method: 'GET', path: '/x'));

    expect($response->body())->toBe('{"data":{"trail":"global,route,handler"}}');
});

it('lets middleware see and replace the response on the way out', function () {
    $router = new Router();
    $router->use(function (ServerRequest $req, callable $next): Response {
        $inner = $next($req);
        return Response::ok(['wrapped' => true, 'inner_status' => $inner->status()]);
    });
    $router->get('/x', [], fn (): Response => Response::ok(['handler' => true]));

    $response = $router->dispatch(new ServerRequest(method: 'GET', path: '/x'));

    expect($response->body())->toBe('{"data":{"wrapped":true,"inner_status":200}}');
});

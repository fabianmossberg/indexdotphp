<?php

declare(strict_types=1);

use IndexDotPhp\Router\Request;
use IndexDotPhp\Router\Response;
use IndexDotPhp\Router\Router;
use IndexDotPhp\Router\ServerRequest;

it('paginates with defaults: page=1, size=20, computes pages from total', function () {
    $router = new Router();
    $router->get(
        '/items',
        ['pagination' => true],
        fn (): Response =>
        Response::list(['a', 'b', 'c'], 84)
    );

    $response = $router->dispatch(new ServerRequest(method: 'GET', path: '/items'));

    expect($response->status())->toBe(200);
    expect($response->body())->toBe('{"items":["a","b","c"],"meta":{"total":84,"page":1,"size":20,"pages":5}}');
});

it('reads page and per_page from the query string', function () {
    $router = new Router();
    $router->get('/items', ['pagination' => true], fn (): Response => Response::list(
        [Request::page(), Request::size()],
        100
    ));

    $response = $router->dispatch(new ServerRequest(
        method: 'GET',
        path:   '/items',
        query:  ['page' => '3', 'per_page' => '10'],
    ));

    expect($response->body())->toBe('{"items":[3,10],"meta":{"total":100,"page":3,"size":10,"pages":10}}');
});

it('caps per_page at max_pagination_size (default 100)', function () {
    $router = new Router();
    $router->get('/items', ['pagination' => true], fn (): Response => Response::list(
        [Request::size()],
        500
    ));

    $response = $router->dispatch(new ServerRequest(
        method: 'GET',
        path:   '/items',
        query:  ['per_page' => '500'],
    ));

    expect($response->body())->toContain('"size":100');
    expect($response->body())->toContain('[100]');
});

it('reports pages=0 when total is 0', function () {
    $router = new Router();
    $router->get('/items', ['pagination' => true], fn (): Response => Response::list([], 0));

    $response = $router->dispatch(new ServerRequest(method: 'GET', path: '/items'));

    expect($response->body())->toBe('{"items":[],"meta":{"total":0,"page":1,"size":20,"pages":0}}');
});

it('does not add meta when handler returns Response::ok on a paginated route', function () {
    $router = new Router();
    $router->get('/items', ['pagination' => true], fn (): Response => Response::ok(['no', 'total']));

    $response = $router->dispatch(new ServerRequest(method: 'GET', path: '/items'));

    expect($response->body())->toBe('{"items":["no","total"]}');
});

it('honours custom pagination_query_keys passed to the Router config', function () {
    $router = new Router([
        'pagination_query_keys' => ['page' => 'p', 'size' => 'limit'],
    ]);
    $router->get('/items', ['pagination' => true], fn (): Response => Response::list(
        [Request::page(), Request::size()],
        50
    ));

    $response = $router->dispatch(new ServerRequest(
        method: 'GET',
        path:   '/items',
        query:  ['p' => '2', 'limit' => '5'],
    ));

    expect($response->body())->toBe('{"items":[2,5],"meta":{"total":50,"page":2,"size":5,"pages":10}}');
});

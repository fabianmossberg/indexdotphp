<?php

declare(strict_types=1);

use IndexDotPhp\Router\Response;
use IndexDotPhp\Router\Router;

it('generates a URL from a named route via Router::url()', function () {
    $router = new Router();
    $router->get('/users/:id', ['name' => 'users.show'], fn(): Response => Response::ok([]));

    expect($router->url('users.show', ['id' => 42]))->toBe('/users/42');
});

it('strips regex constraints from the pattern when generating a URL', function () {
    $router = new Router();
    $router->get('/users/:id<\d+>/posts/:slug<[a-z-]+>', ['name' => 'post.show'], fn(): Response => Response::ok([]));

    expect($router->url('post.show', ['id' => 42, 'slug' => 'hello']))->toBe('/users/42/posts/hello');
});

it('honors sub-router prefix when generating URLs', function () {
    $router = new Router();
    $api = $router->prefix('/api');
    $api->get('/users/:id', ['name' => 'users.show'], fn(): Response => Response::ok([]));

    expect($router->url('users.show', ['id' => 42]))->toBe('/api/users/42');
});

it('URL-encodes parameter values using RFC 3986 (rawurlencode)', function () {
    $router = new Router();
    $router->get('/items/:slug', ['name' => 'item'], fn(): Response => Response::ok([]));

    expect($router->url('item', ['slug' => 'a b/c']))->toBe('/items/a%20b%2Fc');
});

it('throws when asked to generate a URL for an unknown route name', function () {
    $router = new Router();
    $router->url('nope');
})->throws(RuntimeException::class, 'No route named: nope');

it('throws when a required parameter is missing', function () {
    $router = new Router();
    $router->get('/users/:id', ['name' => 'show'], fn(): Response => Response::ok([]));

    $router->url('show', []);
})->throws(RuntimeException::class, "Missing param 'id' for route 'show'");

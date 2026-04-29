<?php

declare(strict_types=1);

use IndexDotPhp\Router\Response;
use IndexDotPhp\Router\Router;
use IndexDotPhp\Router\ServerRequest;

it('registers each HTTP verb method', function (string $method, string $fn) {
    $router = new Router();
    $router->{$fn}('/x', [], fn (): Response => Response::ok(['m' => $method]));

    $response = $router->dispatch(new ServerRequest(method: $method, path: '/x'));

    expect($response->status())->toBe(200);
    expect($response->body())->toBe('{"data":{"m":"' . $method . '"}}');
})->with([
    'GET'    => ['GET',    'get'],
    'POST'   => ['POST',   'post'],
    'PUT'    => ['PUT',    'put'],
    'PATCH'  => ['PATCH',  'patch'],
    'DELETE' => ['DELETE', 'delete'],
]);

it('registers multiple methods for one pattern via match()', function () {
    $router = new Router();
    $router->match(['GET', 'POST'], '/foo', [], fn (): Response => Response::ok(['ok' => true]));

    expect($router->dispatch(new ServerRequest(method: 'GET', path: '/foo'))->status())->toBe(200);
    expect($router->dispatch(new ServerRequest(method: 'POST', path: '/foo'))->status())->toBe(200);
});

it('matches all standard verbs via standardVerbs()', function () {
    $router = new Router();
    $router->standardVerbs('/x', [], fn (): Response => Response::ok(['ok' => true]));

    foreach (['GET', 'POST', 'PUT', 'PATCH', 'DELETE'] as $method) {
        expect($router->dispatch(new ServerRequest(method: $method, path: '/x'))->status())->toBe(200);
    }
});

it('uppercases method names passed to match() so lowercase still binds', function () {
    $router = new Router();
    $router->match(['get', 'post'], '/x', [], fn (): Response => Response::ok(['ok' => true]));

    expect($router->dispatch(new ServerRequest(method: 'GET', path: '/x'))->status())->toBe(200);
    expect($router->dispatch(new ServerRequest(method: 'POST', path: '/x'))->status())->toBe(200);
});

<?php

declare(strict_types=1);

use IndexDotPhp\Router\Request;
use IndexDotPhp\Router\Response;
use IndexDotPhp\Router\Router;
use IndexDotPhp\Router\ServerRequest;

it('lets static segments beat dynamic ones even when registered after', function () {
    $router = new Router();
    $router->get('/users/:id', [], fn(): Response => Response::ok([
        'route' => 'show',
        'id'    => Request::param('id'),
    ]));
    $router->get('/users/me', [], fn(): Response => Response::ok([
        'route' => 'me',
    ]));

    $response = $router->dispatch(new ServerRequest(method: 'GET', path: '/users/me'));

    expect($response->status())->toBe(200);
    expect($response->body())->toBe('{"items":{"route":"me"}}');
});

it('still matches the dynamic segment when the static does not', function () {
    $router = new Router();
    $router->get('/users/:id', [], fn(): Response => Response::ok([
        'route' => 'show',
        'id'    => Request::param('id'),
    ]));
    $router->get('/users/me', [], fn(): Response => Response::ok([
        'route' => 'me',
    ]));

    $response = $router->dispatch(new ServerRequest(method: 'GET', path: '/users/42'));

    expect($response->status())->toBe(200);
    expect($response->body())->toBe('{"items":{"route":"show","id":"42"}}');
});

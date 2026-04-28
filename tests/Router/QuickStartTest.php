<?php

declare(strict_types=1);

use IndexDotPhp\Router\Request;
use IndexDotPhp\Router\Response;
use IndexDotPhp\Router\Router;
use IndexDotPhp\Router\ServerRequest;

it('returns a greeting from a path param', function () {
    $router = new Router();

    $router->get('/hello/:name', [], fn (): Response => Response::ok([
        'greeting' => 'Hello, ' . Request::param('name'),
    ]));

    $response = $router->dispatch(new ServerRequest(method: 'GET', path: '/hello/world'));

    expect($response->status())->toBe(200);
    expect($response->body())->toBe('{"data":{"greeting":"Hello, world"}}');
});

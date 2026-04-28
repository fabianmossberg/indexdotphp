<?php

declare(strict_types=1);

use IndexDotPhp\Router\Response;
use IndexDotPhp\Router\Router;
use IndexDotPhp\Router\ServerRequest;

it('auto-handles HEAD by running the GET handler and stripping the body', function () {
    $router = new Router();
    $router->get('/x', [], fn(): Response => Response::ok(['msg' => 'hi']));

    $response = $router->dispatch(new ServerRequest(method: 'HEAD', path: '/x'));

    expect($response->status())->toBe(200);
    expect($response->body())->toBe('');
});

it('auto-handles OPTIONS with Allow listing registered methods plus HEAD and OPTIONS', function () {
    $router = new Router();
    $router->get('/x',  [], fn(): Response => Response::ok([]));
    $router->post('/x', [], fn(): Response => Response::ok([]));

    $response = $router->dispatch(new ServerRequest(method: 'OPTIONS', path: '/x'));

    expect($response->status())->toBe(204);
    expect($response->header('Allow'))->toBe('GET, HEAD, OPTIONS, POST');
});

it('omits HEAD from Allow when no GET is registered for the path', function () {
    $router = new Router();
    $router->post('/x', [], fn(): Response => Response::ok([]));

    $response = $router->dispatch(new ServerRequest(method: 'OPTIONS', path: '/x'));

    expect($response->header('Allow'))->toBe('OPTIONS, POST');
});

it('returns 404 for OPTIONS on an unmatched path', function () {
    $router = new Router();
    $router->get('/exists', [], fn(): Response => Response::ok([]));

    $response = $router->dispatch(new ServerRequest(method: 'OPTIONS', path: '/missing'));

    expect($response->status())->toBe(404);
});

it('lets an explicit HEAD route win over the auto-HEAD handler', function () {
    $router = new Router();
    $router->get('/x', [], fn(): Response => Response::ok(['from' => 'GET']));
    $router->match(['HEAD'], '/x', [], fn(): Response => Response::ok(['from' => 'HEAD-explicit']));

    $response = $router->dispatch(new ServerRequest(method: 'HEAD', path: '/x'));

    expect($response->body())->toBe('{"items":{"from":"HEAD-explicit"}}');
});

it('lets an explicit OPTIONS route win over the auto-OPTIONS handler', function () {
    $router = new Router();
    $router->get('/x', [], fn(): Response => Response::ok([]));
    $router->match(['OPTIONS'], '/x', [], fn(): Response => Response::ok(['from' => 'OPTIONS-explicit']));

    $response = $router->dispatch(new ServerRequest(method: 'OPTIONS', path: '/x'));

    expect($response->status())->toBe(200);
    expect($response->body())->toBe('{"items":{"from":"OPTIONS-explicit"}}');
});

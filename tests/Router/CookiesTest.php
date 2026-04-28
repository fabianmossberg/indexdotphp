<?php

declare(strict_types=1);

use IndexDotPhp\Router\Request;
use IndexDotPhp\Router\Response;
use IndexDotPhp\Router\Router;
use IndexDotPhp\Router\ServerRequest;

it('reads a cookie via Request::cookie() and falls back to null', function () {
    $router = new Router();
    $router->get('/x', [], fn(): Response => Response::ok([
        'session' => Request::cookie('session'),
        'missing' => Request::cookie('missing'),
    ]));

    $response = $router->dispatch(new ServerRequest(
        method:  'GET',
        path:    '/x',
        cookies: ['session' => 'abc123'],
    ));

    expect($response->body())->toBe('{"items":{"session":"abc123","missing":null}}');
});

it('sets a cookie via Response::withCookie() with default options', function () {
    $response = Response::ok([])->withCookie('flash', 'hello');

    expect($response->cookies())->toMatchArray([
        'flash' => ['value' => 'hello', 'options' => []],
    ]);
});

it('sets a cookie with explicit options (path, httpOnly, sameSite)', function () {
    $response = Response::ok([])->withCookie('session', 'abc123', [
        'path'     => '/',
        'httpOnly' => true,
        'sameSite' => 'Strict',
    ]);

    $cookies = $response->cookies();
    expect($cookies['session']['value'])->toBe('abc123');
    expect($cookies['session']['options'])->toBe([
        'path'     => '/',
        'httpOnly' => true,
        'sameSite' => 'Strict',
    ]);
});

it('overwrites the same cookie name when withCookie() is called twice', function () {
    $response = Response::ok([])
        ->withCookie('flash', 'first')
        ->withCookie('flash', 'second');

    expect($response->cookies()['flash']['value'])->toBe('second');
});

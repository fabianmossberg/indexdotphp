<?php

declare(strict_types=1);

use IndexDotPhp\Router\Request;
use IndexDotPhp\Router\Response;
use IndexDotPhp\Router\Router;
use IndexDotPhp\Router\ServerRequest;

it('parses a comma-separated query value via queryCsv', function () {
    $router = new Router();
    $router->get('/x', [], fn (): Response => Response::ok([
        'tags' => Request::queryCsv('tags'),
    ]));

    $response = $router->dispatch(new ServerRequest(
        method: 'GET',
        path:   '/x',
        query:  ['tags' => 'a,b,c'],
    ));

    expect($response->body())->toBe('{"items":{"tags":["a","b","c"]}}');
});

it('returns the defaults when queryCsv key is missing or empty', function () {
    $router = new Router();
    $router->get('/x', [], fn (): Response => Response::ok([
        'missing' => Request::queryCsv('missing', ['fallback']),
        'empty'   => Request::queryCsv('empty', ['fallback']),
    ]));

    $response = $router->dispatch(new ServerRequest(
        method: 'GET',
        path:   '/x',
        query:  ['empty' => ''],
    ));

    expect($response->body())->toBe('{"items":{"missing":["fallback"],"empty":["fallback"]}}');
});

it('coerces integers via queryCsvInts and returns defaults on any non-digit', function () {
    $router = new Router();
    $router->get('/x', [], fn (): Response => Response::ok([
        'ok'    => Request::queryCsvInts('ok'),
        'mixed' => Request::queryCsvInts('mixed', [99]),
    ]));

    $response = $router->dispatch(new ServerRequest(
        method: 'GET',
        path:   '/x',
        query:  ['ok' => '1,2,3', 'mixed' => '1,abc,3'],
    ));

    expect($response->body())->toBe('{"items":{"ok":[1,2,3],"mixed":[99]}}');
});

it('filters queryCsvInts via the allowed list and falls back when any value is outside it', function () {
    $router = new Router();
    $router->get('/x', [], fn (): Response => Response::ok([
        'ok'  => Request::queryCsvInts('ok', [], [1, 2, 3]),
        'bad' => Request::queryCsvInts('bad', [99], [1, 2, 3]),
    ]));

    $response = $router->dispatch(new ServerRequest(
        method: 'GET',
        path:   '/x',
        query:  ['ok' => '1,2', 'bad' => '1,4'],
    ));

    expect($response->body())->toBe('{"items":{"ok":[1,2],"bad":[99]}}');
});

it('filters queryCsvStrings via the allowed list', function () {
    $router = new Router();
    $router->get('/x', [], fn (): Response => Response::ok([
        'ok'  => Request::queryCsvStrings('ok', [], ['asc', 'desc']),
        'bad' => Request::queryCsvStrings('bad', ['asc'], ['asc', 'desc']),
    ]));

    $response = $router->dispatch(new ServerRequest(
        method: 'GET',
        path:   '/x',
        query:  ['ok' => 'asc,desc', 'bad' => 'sideways'],
    ));

    expect($response->body())->toBe('{"items":{"ok":["asc","desc"],"bad":["asc"]}}');
});

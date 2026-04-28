# IndexDotPhp

[![tests](https://github.com/fabianmossberg/indexdotphp/actions/workflows/tests.yml/badge.svg)](https://github.com/fabianmossberg/indexdotphp/actions/workflows/tests.yml)
[![PHP](https://img.shields.io/badge/php-%5E8.3-blue.svg)](https://www.php.net/)
[![License](https://img.shields.io/badge/license-MIT-blue.svg)](#license)

A tiny HTTP router for PHP. Matching, middleware, sub-routers, named decoders,
pagination, and standardized JSON responses. The goal is to give you just
enough to stand up a small PHP app without reaching for a full framework.

## Status

PHP 8.3+. Not yet tagged for release; consume from the git repo for now.

## Installation

This package isn't on Packagist yet. Add the git repo and `dev` stability to
your project's `composer.json`:

```json
{
    "repositories": [
        {
            "type": "git",
            "url": "git@github.com:fabianmossberg/indexdotphp.git"
        }
    ],
    "require": {
        "fabianmossberg/indexdotphp": "*"
    },
    "minimum-stability": "dev",
    "prefer-stable": true
}
```

Then `composer install`. To pick up new commits, run `composer update
fabianmossberg/indexdotphp`.

## Quick start

Create `public/index.php`:

```php
<?php
require __DIR__ . '/../vendor/autoload.php';

use IndexDotPhp\Router\Request;
use IndexDotPhp\Router\Response;
use IndexDotPhp\Router\Router;

$router = new Router();

$router->get('/hello/:name', [], fn(): Response => Response::ok([
    'greeting' => 'Hello, ' . Request::param('name'),
]));

$router->dispatch()->send();
```

Run with PHP's built-in server:

```bash
php -S localhost:8000 -t public public/index.php
```

`GET http://localhost:8000/hello/world` returns:

```json
{ "data": { "greeting": "Hello, world" } }
```

## Features

- **HTTP verbs**: `get`, `post`, `put`, `patch`, `delete`, `match([...])`, `any`
- **Path params**: `/users/:id`, `/users/:id<\d+>` (regex constraint), `/files/:path<.+>` (multi-segment)
- **Match priority**: static segments beat dynamic; constrained dynamic beats unconstrained; registration order breaks ties
- **Middleware**: global (`$router->use(...)`), per-route (`'middleware' => [...]`), and sub-router scoped — onion model
- **Sub-routers**: `$api = $router->prefix('/api/v1')` — nested prefixes accumulate, middleware is scoped to the subtree
- **Decoders**: `'decode' => ['id' => 'int']` route option; built-ins for `int`, `string`, `slug`, `csv-int`, `csv-string`; register custom via `Router::registerDecoder`
- **Pagination**: `'pagination' => true` route option, `Response::list($data, $total)`, automatic `meta` envelope
- **Cookies**: `Request::cookie()`, `Response::withCookie($name, $value, $options)`
- **Errors**: `Router::onError($status, callable)` for 404 / 405 / decode failures, `Router::onException(callable)` for top-level catch
- **Built-in middleware**: `IndexDotPhp\Router\Middleware\Timing` (Server-Timing header)

## Timing

Ship-with-the-library middleware that adds a [`Server-Timing`](https://developer.mozilla.org/en-US/docs/Web/HTTP/Reference/Headers/Server-Timing)
header. Wrap operations you want to profile with `Timing::measure()` to break
them out into named sub-spans:

```php
use IndexDotPhp\Router\Middleware\Timing;

$router->use(new Timing());

$router->get('/users', [], function (): Response {
    $users = Timing::measure('db.users',  fn() => getUsers());
    $count = Timing::measure('db.count',  fn() => countUsers());
    $body  = Timing::measure('render',    fn() => renderUsers($users));

    return Response::ok(['users' => $body, 'count' => $count]);
});
```

Result:

```
Server-Timing: db.users;dur=43.7, db.count;dur=4.2, render;dur=12.5, total;dur=60.5
```

Each entry shows up as its own row in browser dev tools (Chrome: Network →
Timing tab; Firefox: Performance panel). `total` is recorded automatically;
all other entries come from `measure()` calls. Repeated measures with the
same name accumulate — useful for summing multiple DB calls under one
label. `measure()` returns the closure's result, and uses `try/finally` so
the time is recorded even if the closure throws.

For traditional PHP-FPM (one request per process) you can register `Timing`
anywhere in your middleware chain. For long-running servers (Swoole,
RoadRunner), put it first — the middleware resets recorded entries on each
invocation, so any `measure()` calls before it runs are discarded.

## Wire envelope

Every JSON response uses the same shape:

```json
{
  "data":    <value>,
  "meta":    { "total": 84, "page": 1, "size": 20, "pages": 5 },
  "message": ["debug: cache hit"]
}
```

`data` is always present. `meta` only appears for paginated routes (or when set
explicitly via `withMeta`). `message` only appears when at least one message was
appended via `Response::ok($x, 'msg')`, `Response::error(...)`, or
`->withMessage('msg')`.

If you want a different shape (custom keys at the root, or non-JSON output like
CSV / HTML / files), use the raw factory or fluent escape hatch:

```php
Response::raw('{"users":[],"count":0}', 'application/json');
Response::make()->withStatus(201)->withRaw($csv, 'text/csv');
```

Raw responses bypass the envelope entirely — `meta` and `message` are not added.

## Running the tests

```bash
composer test       # run the suite
composer test:cov   # with coverage (requires pcov or xdebug)
```

The coverage script gates at 80%; the suite currently sits around 96%.

## Contributing

See [CONTRIBUTING.md](./CONTRIBUTING.md) for commit conventions, the release
process, and dev setup.

## License

MIT

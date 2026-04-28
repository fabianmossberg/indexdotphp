# IndexDotPhp

A tiny PHP toolkit for small APIs and websites. The first part — and currently
the only part — is an HTTP router with matching, middleware, sub-routers,
named decoders, pagination, and standardized JSON responses. Future plans
include a JWT helper and a thin database wrapper. The goal is to give you just
enough to stand up a small PHP app without reaching for a full framework.

## Status

PHP 8.1+. Not yet tagged for release; consume from the git repo for now.

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
{ "items": { "greeting": "Hello, world" } }
```

## Features

- **HTTP verbs**: `get`, `post`, `put`, `patch`, `delete`, `match([...])`, `any`
- **Path params**: `/users/:id`, `/users/:id<\d+>` (regex constraint), `/files/:path<.+>` (multi-segment)
- **Match priority**: static segments beat dynamic; constrained dynamic beats unconstrained; registration order breaks ties
- **Middleware**: global (`$router->use(...)`), per-route (`'middleware' => [...]`), and sub-router scoped — onion model
- **Sub-routers**: `$api = $router->prefix('/api/v1')` — nested prefixes accumulate, middleware is scoped to the subtree
- **Decoders**: `'decode' => ['id' => 'int']` route option; built-ins for `int`, `string`, `slug`, `csv-int`, `csv-string`; register custom via `Router::registerDecoder`
- **Pagination**: `'pagination' => true` route option, `Response::list($items, $total)`, automatic `meta` envelope
- **Cookies**: `Request::cookie()`, `Response::withCookie($name, $value, $options)`
- **Errors**: `Router::onError($status, callable)` for 404 / 405 / decode failures, `Router::onException(callable)` for top-level catch
- **Built-in middleware**: `IndexDotPhp\Router\Middleware\Timing` (Server-Timing header)

## Wire envelope

Every JSON response uses the same shape:

```json
{
  "items":   <value>,
  "meta":    { "total": 84, "page": 1, "size": 20, "pages": 5 },
  "message": ["debug: cache hit"]
}
```

`items` is always present. `meta` only appears for paginated routes (or when set
explicitly via `withMeta`). `message` only appears when at least one message was
appended via `Response::ok($x, 'msg')`, `Response::error(...)`, or
`->withMessage('msg')`. For non-JSON output (CSV, HTML, files), use
`Response::make()->withRaw($body, $contentType)` to bypass the envelope.

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

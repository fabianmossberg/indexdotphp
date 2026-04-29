# IndexDotPhp

[![tests](https://github.com/fabianmossberg/indexdotphp/actions/workflows/tests.yml/badge.svg)](https://github.com/fabianmossberg/indexdotphp/actions/workflows/tests.yml)
[![PHP](https://img.shields.io/badge/php-%5E8.3-blue.svg)](https://www.php.net/)
[![License](https://img.shields.io/badge/license-MIT-blue.svg)](#license)

A tiny HTTP router for PHP. Matching, middleware, sub-routers, named decoders,
pagination, and standardized JSON responses. The goal is to give you just
enough to stand up a small PHP app without reaching for a full framework.

## Status

Tagged releases follow [SemVer](https://semver.org/). Requires PHP 8.3+.

## Installation

```bash
composer require fabianmossberg/indexdotphp
```

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

- **HTTP verbs**: `get`, `post`, `put`, `patch`, `delete`, `match([...])`, `standardVerbs`
- **Path params**: `/users/:id`, `/users/:id<\d+>` (regex constraint), `/files/:path<.+>` (multi-segment)
- **Match priority**: static segments beat dynamic; constrained dynamic beats unconstrained; registration order breaks ties
- **Middleware**: global (`$router->use(...)`), per-route (`'middleware' => [...]`), and sub-router scoped — onion model
- **Sub-routers**: `$api = $router->prefix('/api/v1')` — nested prefixes accumulate, middleware is scoped to the subtree
- **Decoders**: `'decode' => ['id' => 'int']` route option; built-ins for `int`, `slug`, `csv-int`, `csv-string`; register custom via `Router::registerDecoder`
- **Validation**: `'validate' => fn($req) => $errors ?? null` route option for request-shape checks; failures auto-emit 422 with `VALIDATION_FAILED`
- **Pagination**: `'pagination' => true` route option, `Response::list($data, $total)`, automatic `meta` envelope
- **Cookies**: `Request::cookie()`, `Response::withCookie($name, $value, $options)`
- **Headers**: `Response::withHeader / withoutHeader`, `Router::defaultHeaders([...])` for static headers on every response, `Router::stripHeaders([...])` to suppress SAPI defaults like `X-Powered-By`
- **Response factories**: `Response::ok` / `list` (enveloped JSON), `Response::error` (error envelope), `Response::raw` / `html` / `json` / `text` (bypass envelope), `Response::noContent` / `redirect`, `Response::make()` (fluent builder)
- **Errors**: `Router::onError($status, callable)` for status-specific handlers (404 / 405 / decode failures), `Router::onError(callable)` for a default handler that post-processes any error response, `Router::onException(callable)` for top-level catch
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

Successful responses (status < 400) use this shape:

```json
{
  "data":    <value>,
  "meta":    { "total": 84, "page": 1, "size": 20, "pages": 5 },
  "message": ["debug: cache hit"]
}
```

Error responses (status ≥ 400) carry an `error` block instead:

```json
{
  "data":  null,
  "error": {
    "status":  500,
    "code":    "INTERNAL_SERVER_ERROR",
    "message": "Database is on fire"
  }
}
```

The shape switches automatically based on status — there's no flag. `code` is a
machine-readable identifier (stable across translations, useful for client
branching); `message` is human-readable. If you don't pass `code:` explicitly,
the router derives one from the HTTP status (`404` → `NOT_FOUND`, `429` →
`TOO_MANY_REQUESTS`, etc.):

```php
Response::error(500, 'Database is on fire', code: 'DB_CONNECTION_FAILED');
Response::error(422, 'invalid input')->withCode('VALIDATION_FAILED');
Response::error(404, 'no such order');  // code defaults to NOT_FOUND
```

The `data` slot is still available on errors — useful for validation responses
where you want field-level details:

```php
Response::error(
    422,
    'validation failed',
    code: 'VALIDATION_FAILED',
    data: ['errors' => ['email' => 'must be a string']],
);
```

Built-in errors come pre-coded: `route_not_found` → `ROUTE_NOT_FOUND`,
`method_not_allowed` → `METHOD_NOT_ALLOWED`, `decode_failure` → `DECODE_FAILED`.

### Default error handler

Cross-cutting error rendering — content negotiation, custom HTML pages, error
logging — registers in one place via `onError(callable)` and runs for *any*
error response, including handler-returned `Response::error(...)` and
`onException` responses:

```php
$router->onError(function (Response $r, ServerRequest $req): Response {
    if ($req->accepts('text/html')) {
        return Response::html(renderError([
            'status'  => $r->status(),
            'code'    => $r->errorCode(),
            'message' => $r->errorMessage(),
        ]))->withStatus($r->status());
    }
    return $r;
});
```

The default handler runs *after* any status-specific `onError($status, $handler)`
and post-processes the response. Status-specific handlers produce a fresh
response (`fn (ServerRequest): Response`); the default handler post-processes
an existing one (`fn (Response, ServerRequest): Response`). Returning another
`Response::error(...)` from the default handler does not re-trigger it — there
is no recursion.

If you want a completely different shape (custom keys at the root, or non-JSON
output like CSV / HTML / files), use one of the raw factories or the fluent
escape hatch:

```php
Response::html('<h1>hello</h1>');                    // text/html;charset=utf-8
Response::text('access denied');                      // text/plain;charset=utf-8
Response::json(['greeting' => 'Hi']);                 // application/json, no envelope
Response::raw($body, 'application/vnd.api+json');     // any content-type
Response::make()->withStatus(201)->withRaw($csv, 'text/csv');
```

Raw responses bypass the envelope entirely — `data`, `error`, `meta`, and
`message` are not added. `Response::json()` is specifically the *non-enveloped*
JSON form (`{"greeting":"Hi"}`), distinct from `Response::ok()` which produces
the framework's standard `{"data":{"greeting":"Hi"}}`.

## Running the tests

```bash
composer test       # run the suite
composer test:cov   # with coverage (requires pcov or xdebug)
composer lint       # php-cs-fixer dry-run
composer stan       # phpstan
composer check      # lint + stan + test:cov in one go
```

The coverage script gates at 80%; the suite currently sits around 96%.

## Contributing

See [CONTRIBUTING.md](./CONTRIBUTING.md) for commit conventions, the release
process, and dev setup.

## License

MIT

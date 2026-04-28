# IndexDotPhp

A tiny PHP toolkit for small APIs and websites.

The first part — and currently the only part — is an HTTP router. Future plans
include a JWT helper and a thin database wrapper. The goal is to give you just
enough to stand up a small PHP app without reaching for a full framework.

## Status

Early development. PHP 8.1+. Not yet tagged for release.

## Installation

```bash
composer require fabianmossberg/indexdotphp
```

## Quick start

```php
<?php
require __DIR__ . '/vendor/autoload.php';

use IndexDotPhp\Router\Router;
use IndexDotPhp\Router\Request;
use IndexDotPhp\Router\Response;

$router = new Router();

$router->get('/hello/:name', [], function (): Response {
    return Response::ok(['greeting' => 'Hello, ' . Request::param('name')]);
});

$router->dispatch()->send();
```

`GET /hello/world` returns:

```json
{ "items": { "greeting": "Hello, world" } }
```

## License

MIT

<?php

declare(strict_types=1);

use IndexDotPhp\Router\Response;

it('builds a 204 No Content response with an empty body', function () {
    $response = Response::noContent();

    expect($response->status())->toBe(204);
    expect($response->body())->toBe('');
});

it('builds a redirect response with the Location header', function () {
    $response = Response::redirect('/dashboard');

    expect($response->status())->toBe(302);
    expect($response->header('Location'))->toBe('/dashboard');
});

it('builds a permanent redirect (301) when the status arg is given', function () {
    $response = Response::redirect('/new', 301);

    expect($response->status())->toBe(301);
    expect($response->header('Location'))->toBe('/new');
});

it('renders an empty body for redirects (no JSON envelope alongside Location)', function () {
    $response = Response::redirect('/dashboard');

    expect($response->body())->toBe('');
});

it('lets Response::make() be customised via fluent setters', function () {
    $response = Response::make()
        ->withStatus(201)
        ->withData(['id' => 7])
        ->withMessage('created');

    expect($response->status())->toBe(201);
    expect($response->body())->toBe('{"data":{"id":7},"message":["created"]}');
});

it('appends multiple messages via withMessage()', function () {
    $response = Response::ok(['x' => 1])
        ->withMessage('first')
        ->withMessage('second');

    expect($response->body())->toBe('{"data":{"x":1},"message":["first","second"]}');
});

it('renders meta into the envelope when withMeta() is used', function () {
    $response = Response::make()
        ->withData([])
        ->withMeta(['total' => 84, 'page' => 1, 'size' => 20, 'pages' => 5]);

    expect($response->body())->toBe('{"data":[],"meta":{"total":84,"page":1,"size":20,"pages":5}}');
});

it('sets and reads custom headers via withHeader() and header()', function () {
    $response = Response::ok([])
        ->withHeader('X-Trace-Id', 'abc-123');

    expect($response->header('X-Trace-Id'))->toBe('abc-123');
    expect($response->headers())->toMatchArray(['X-Trace-Id' => 'abc-123']);
});

it('shortcuts Content-Type via withContentType()', function () {
    $response = Response::ok([])->withContentType('application/vnd.api+json');

    expect($response->header('Content-Type'))->toBe('application/vnd.api+json');
});

it('bypasses the JSON envelope entirely with withRaw()', function () {
    $response = Response::make()
        ->withStatus(200)
        ->withRaw("col1,col2\nval1,val2\n", 'text/csv');

    expect($response->body())->toBe("col1,col2\nval1,val2\n");
    expect($response->header('Content-Type'))->toBe('text/csv');
});

it('builds a raw response via Response::raw() with a custom content type', function () {
    $response = Response::raw('{"users":[],"count":0}', 'application/json');

    expect($response->status())->toBe(200);
    expect($response->body())->toBe('{"users":[],"count":0}');
    expect($response->header('Content-Type'))->toBe('application/json');
});

it('defaults Response::raw() content type to text/plain', function () {
    $response = Response::raw('hello');

    expect($response->body())->toBe('hello');
    expect($response->header('Content-Type'))->toBe('text/plain');
});

it('removes a previously set header via withoutHeader()', function () {
    $response = Response::ok([])
        ->withHeader('X-Foo', 'bar')
        ->withHeader('X-Baz', 'qux')
        ->withoutHeader('X-Foo');

    expect($response->header('X-Foo'))->toBeNull();
    expect($response->header('X-Baz'))->toBe('qux');
});

it('is a no-op when withoutHeader() is called for a header that was never set', function () {
    $response = Response::ok([])->withoutHeader('X-Missing');

    expect($response->header('X-Missing'))->toBeNull();
    expect($response->headers())->toBe([]);
});

it('renders the error envelope when an explicit code is passed to Response::error()', function () {
    $response = Response::error(500, 'Database is on fire', code: 'DB_CONNECTION_FAILED');

    expect($response->body())->toBe('{"data":null,"error":{"status":500,"code":"DB_CONNECTION_FAILED","message":"Database is on fire"}}');
});

it('lets withCode() set the error code fluently', function () {
    $response = Response::error(422, 'invalid input')->withCode('VALIDATION_FAILED');

    expect($response->body())->toBe('{"data":null,"error":{"status":422,"code":"VALIDATION_FAILED","message":"invalid input"}}');
});

it('derives a default error code from common HTTP statuses when none is set', function () {
    expect(Response::error(400, 'bad')->body())
        ->toBe('{"data":null,"error":{"status":400,"code":"BAD_REQUEST","message":"bad"}}');
    expect(Response::error(403, 'no')->body())
        ->toBe('{"data":null,"error":{"status":403,"code":"FORBIDDEN","message":"no"}}');
    expect(Response::error(429, 'slow down')->body())
        ->toBe('{"data":null,"error":{"status":429,"code":"TOO_MANY_REQUESTS","message":"slow down"}}');
});

it('falls back to CLIENT_ERROR / SERVER_ERROR for unmapped statuses', function () {
    expect(Response::error(418, "I'm a teapot")->body())
        ->toBe('{"data":null,"error":{"status":418,"code":"CLIENT_ERROR","message":"I\'m a teapot"}}');
    expect(Response::error(599, 'unknown server thing')->body())
        ->toBe('{"data":null,"error":{"status":599,"code":"SERVER_ERROR","message":"unknown server thing"}}');
});

it('lets the data slot carry validation details on error responses', function () {
    $response = Response::error(
        422,
        'validation failed',
        code: 'VALIDATION_FAILED',
        data: ['errors' => ['email' => 'must be a string']],
    );

    expect($response->body())->toBe(
        '{"data":{"errors":{"email":"must be a string"}},"error":{"status":422,"code":"VALIDATION_FAILED","message":"validation failed"}}',
    );
});

it('renders the success envelope when status is below 400', function () {
    $response = Response::ok(['id' => 1])->withMessage('cache hit');

    expect($response->body())->toBe('{"data":{"id":1},"message":["cache hit"]}');
});

it('rejects Response::error() with a status below 400', function () {
    Response::error(200, 'this is not an error');
})->throws(InvalidArgumentException::class, 'Response::error() requires a status >= 400, got 200');

it('rejects Response::error() with status 399 (boundary check)', function () {
    Response::error(399, 'still not an error');
})->throws(InvalidArgumentException::class);

it('accepts Response::error() with status exactly 400', function () {
    $response = Response::error(400, 'bad input');

    expect($response->status())->toBe(400);
});

it('rejects withMessage() on a response built via Response::error()', function () {
    Response::error(500, 'boom')->withMessage('debug breadcrumb');
})->throws(LogicException::class, 'withMessage() is not supported on error responses');

it('rejects withMessage() once the status has been bumped above 400', function () {
    Response::ok([])->withStatus(500)->withMessage('debug breadcrumb');
})->throws(LogicException::class);

it('still accepts withMessage() on success responses', function () {
    $response = Response::ok([])
        ->withMessage('first')
        ->withMessage('second');

    expect($response->body())->toBe('{"data":[],"message":["first","second"]}');
});

it('builds an HTML response via Response::html() with charset', function () {
    $response = Response::html('<h1>hello</h1>');

    expect($response->status())->toBe(200);
    expect($response->body())->toBe('<h1>hello</h1>');
    expect($response->header('Content-Type'))->toBe('text/html;charset=utf-8');
});

it('builds a plain-text response via Response::text() with charset', function () {
    $response = Response::text('hello world');

    expect($response->status())->toBe(200);
    expect($response->body())->toBe('hello world');
    expect($response->header('Content-Type'))->toBe('text/plain;charset=utf-8');
});

it('lets Response::text() be further customised with status and headers', function () {
    $response = Response::text('access denied')
        ->withStatus(403)
        ->withHeader('X-Reason', 'forbidden');

    expect($response->status())->toBe(403);
    expect($response->body())->toBe('access denied');
    expect($response->header('X-Reason'))->toBe('forbidden');
});

it('lets Response::html() be further customised with status and cookies', function () {
    $response = Response::html('<p>nope</p>')
        ->withStatus(404)
        ->withCookie('seen_404', '1');

    expect($response->status())->toBe(404);
    expect($response->body())->toBe('<p>nope</p>');
    expect($response->cookies())->toHaveKey('seen_404');
});

it('builds a non-enveloped JSON response via Response::json()', function () {
    $response = Response::json(['greeting' => 'Hi']);

    expect($response->status())->toBe(200);
    expect($response->body())->toBe('{"greeting":"Hi"}');
    expect($response->header('Content-Type'))->toBe('application/json');
});

it('Response::json() emits raw data, not the framework envelope', function () {
    $raw = Response::json(['greeting' => 'Hi']);
    $enveloped = Response::ok(['greeting' => 'Hi']);

    expect($raw->body())->toBe('{"greeting":"Hi"}');
    expect($enveloped->body())->toBe('{"data":{"greeting":"Hi"}}');
});

it('Response::json() encodes with the same flags as the envelope encoder (unescaped slashes)', function () {
    $response = Response::json(['url' => 'http://example.com/a/b']);

    expect($response->body())->toBe('{"url":"http://example.com/a/b"}');
});

it('Response::json() throws JsonException on unencodable input', function () {
    $handle = fopen('php://memory', 'r');
    try {
        expect(fn () => Response::json($handle))->toThrow(JsonException::class);
    } finally {
        fclose($handle);
    }
});

it('exposes the error message via errorMessage() on Response::error() responses', function () {
    $response = Response::error(500, 'Database is on fire', code: 'DB_DOWN');

    expect($response->errorMessage())->toBe('Database is on fire');
});

it('exposes the explicit error code via errorCode() when one was set', function () {
    $response = Response::error(500, 'boom', code: 'DB_DOWN');

    expect($response->errorCode())->toBe('DB_DOWN');
});

it('falls back to the default error code derived from the HTTP status', function () {
    expect(Response::error(404, 'gone')->errorCode())->toBe('NOT_FOUND');
    expect(Response::error(429, 'slow')->errorCode())->toBe('TOO_MANY_REQUESTS');
    expect(Response::error(500, 'oops')->errorCode())->toBe('INTERNAL_SERVER_ERROR');
});

it('falls back to CLIENT_ERROR / SERVER_ERROR for unmapped statuses on errorCode()', function () {
    expect(Response::error(418, 'teapot')->errorCode())->toBe('CLIENT_ERROR');
    expect(Response::error(599, 'unknown')->errorCode())->toBe('SERVER_ERROR');
});

it('returns null from errorMessage() and errorCode() on success responses', function () {
    $response = Response::ok(['x' => 1]);

    expect($response->errorMessage())->toBeNull();
    expect($response->errorCode())->toBeNull();
});

it('returns null from errorCode() on responses whose status was bumped below 400', function () {
    $response = Response::error(500, 'boom')->withStatus(200);

    expect($response->errorCode())->toBeNull();
});

it('returns null from errorMessage() on responses whose status was bumped below 400', function () {
    $response = Response::error(500, 'boom')->withStatus(200);

    expect($response->errorMessage())->toBeNull();
});

it('returns null from errorMessage() on >= 400 responses where no message was ever set', function () {
    $response = Response::make()->withStatus(500);

    expect($response->status())->toBe(500);
    expect($response->errorMessage())->toBeNull();
    expect($response->errorCode())->toBe('INTERNAL_SERVER_ERROR'); // errorCode has a fallback; errorMessage does not
});

it('reflects withCode() updates in errorCode()', function () {
    $response = Response::error(422, 'bad input')->withCode('VALIDATION_FAILED');

    expect($response->errorCode())->toBe('VALIDATION_FAILED');
});

it('lets Response::json() be further customised with status and headers', function () {
    $response = Response::json(['ok' => true])
        ->withStatus(201)
        ->withHeader('Location', '/x');

    expect($response->status())->toBe(201);
    expect($response->body())->toBe('{"ok":true}');
    expect($response->header('Location'))->toBe('/x');
});

<?php

declare(strict_types=1);

namespace IndexDotPhp\Router;

/**
 * Per-route options accepted by `match()` and the verb shortcuts (`get`,
 * `post`, …). All keys are optional.
 *
 *  - `middleware`: per-route middleware, runs after global + sub-router
 *    middleware and before the handler. Signature:
 *    `function (ServerRequest $req, callable $next): Response`.
 *  - `decode`: maps a path-parameter name to a registered decoder name
 *    (built-ins: `int`, `slug`, `csv-int`, `csv-string`). A decoder
 *    returning null short-circuits with the status from `decode_failure`.
 *  - `decode_failure`: HTTP status used when a decoder returns null.
 *    Defaults to 404; set to 400 for "this is a bad request" semantics.
 *  - `pagination`: when true, the router reads `page` and `per_page` from
 *    the query string, exposes them via `Request::page()` / `size()`, and
 *    auto-fills `meta.total/page/size/pages` on a `Response::list()`.
 *  - `validate`: pre-handler input validator. Returns null to continue,
 *    or an array of field-level errors to short-circuit with a 422
 *    `VALIDATION_FAILED` envelope. Signature:
 *    `function (ServerRequest $req): ?array`.
 *  - `name`: stable name for `Router::url('name', [...])` URL generation.
 *
 * @phpstan-type RouteOptions array{
 *     middleware?: list<callable>,
 *     decode?: array<string, string>,
 *     decode_failure?: int,
 *     pagination?: bool,
 *     validate?: callable(ServerRequest): ?array,
 *     name?: string,
 * }
 *
 * @phpstan-type RouteShape array{
 *     methods: list<string>,
 *     pattern: string,
 *     regex: string,
 *     paramNames: list<string>,
 *     specificity: list<int>,
 *     middleware: list<callable>,
 *     decode: array<string, string>,
 *     decode_failure: int,
 *     pagination: bool,
 *     validate: ?callable,
 *     name: ?string,
 *     handler: callable,
 *     router: Router
 * }
 */
final class Router
{
    private const STANDARD_METHODS = ['GET', 'POST', 'PUT', 'PATCH', 'DELETE'];

    private ?Router $parent = null;
    private string $prefix = '';

    /**
     * Shared across every Router in a prefix() chain — the root constructs
     * one, and prefix() copies the reference into the child. Holding the
     * route list, sort flag, and named-route map on a separate object makes
     * it structurally impossible for a sub-router to accumulate its own
     * stale routes.
     */
    private RouteTable $table;

    /** @var array<int|string, callable> */
    private array $errorHandlers;

    /** @var callable|null */
    private $exceptionHandler = null;

    /** @var list<callable> */
    private array $middleware = [];

    /** @var array<string, callable> */
    private array $decoders;

    /** @var array{default_size: int, max_size: int, page_key: string, size_key: string} */
    private array $paginationConfig;

    /** @var array<string, string> */
    private array $defaultHeaders = [];

    /** @var list<string> */
    private array $strippedHeaders = [];

    public function __construct(array $config = [])
    {
        $this->table = new RouteTable();
        $this->errorHandlers = [
            404 => fn (): Response => Response::error(404, 'Route not found', code: 'ROUTE_NOT_FOUND'),
            405 => fn (): Response => Response::error(405, 'Method not allowed', code: 'METHOD_NOT_ALLOWED'),
        ];

        $defaultSize = $config['default_pagination_size'] ?? 20;
        $maxSize     = $config['max_pagination_size'] ?? 100;

        if (!is_int($defaultSize) || $defaultSize < 1) {
            throw new \InvalidArgumentException(
                'default_pagination_size must be an int >= 1, got ' . var_export($defaultSize, true),
            );
        }
        if (!is_int($maxSize) || $maxSize < 1) {
            throw new \InvalidArgumentException(
                'max_pagination_size must be an int >= 1, got ' . var_export($maxSize, true),
            );
        }

        $this->paginationConfig = [
            'default_size' => $defaultSize,
            'max_size'     => $maxSize,
            'page_key'     => $config['pagination_query_keys']['page'] ?? 'page',
            'size_key'     => $config['pagination_query_keys']['size'] ?? 'per_page',
        ];

        $this->decoders = [
            'int'        => fn (string $s): ?int => ctype_digit($s) ? (int) $s : null,
            'slug'       => fn (string $s): ?string => preg_match('/^[a-z0-9-]+$/', $s) ? $s : null,
            'csv-int'    => function (string $s): ?array {
                $parts = explode(',', $s);
                foreach ($parts as $p) {
                    if (!ctype_digit($p)) {
                        return null;
                    }
                }
                return array_map('intval', $parts);
            },
            'csv-string' => fn (string $s): array => explode(',', $s),
        ];
    }

    public function registerDecoder(string $name, callable $decoder): self
    {
        $this->root()->decoders[$name] = $decoder;
        return $this;
    }

    public function prefix(string $segment): self
    {
        $child = new self();
        $child->parent = $this;
        $child->prefix = $this->prefix . $segment;
        $child->table = $this->table;
        return $child;
    }

    /**
     * Register an error handler.
     *
     * Two forms:
     *  - `onError(int $status, callable $handler)` — status-specific handler that
     *    *produces* an error Response for that status. Signature:
     *    `function (ServerRequest $req): Response`. Used for 404 / 405 / decoder
     *    failure routes.
     *  - `onError(callable $handler)` — default handler that *post-processes*
     *    any Response with status >= 400, including handler-returned errors and
     *    onException responses. Signature:
     *    `function (Response $response, ServerRequest $req): Response`. Runs
     *    after any status-specific handler so it can rewrite the body / headers
     *    (e.g. render HTML for browser clients). Its return value is sent as-is;
     *    re-raising another error response does not re-invoke the default.
     */
    public function onError(int|callable $status, ?callable $handler = null): self
    {
        if (is_callable($status)) {
            if ($handler !== null) {
                throw new \InvalidArgumentException(
                    'onError() does not accept a second argument when the first is a callable; '
                    . 'use onError(int $status, callable $handler) for status-specific handlers '
                    . 'or onError(callable $handler) for the default handler.',
                );
            }
            $this->root()->errorHandlers['*'] = $status;
            return $this;
        }
        if ($handler === null) {
            throw new \InvalidArgumentException(
                'onError(int $status, callable $handler) requires a handler when called with a status code.',
            );
        }
        $this->root()->errorHandlers[$status] = $handler;
        return $this;
    }

    public function onException(callable $handler): self
    {
        $this->root()->exceptionHandler = $handler;
        return $this;
    }

    public function use(callable $middleware): self
    {
        $this->middleware[] = $middleware;
        return $this;
    }

    /**
     * Register headers applied to every response — including 404, 405, OPTIONS,
     * and exception responses — at send time. Defaults do not override headers
     * the response already set, so routes and middleware can opt out per response.
     *
     * @param array<string, string> $headers
     */
    public function defaultHeaders(array $headers): self
    {
        $root = $this->root();
        foreach ($headers as $name => $value) {
            $root->defaultHeaders[$name] = $value;
        }
        return $this;
    }

    /**
     * Headers to remove at send time via PHP's header_remove(). Used to
     * suppress SAPI-injected defaults — most commonly `X-Powered-By` from
     * php.ini's expose_php directive — that the router itself never sets.
     *
     * @param list<string> $names
     */
    public function stripHeaders(array $names): self
    {
        $root = $this->root();
        foreach ($names as $name) {
            if (!in_array($name, $root->strippedHeaders, true)) {
                $root->strippedHeaders[] = $name;
            }
        }
        return $this;
    }

    /**
     * Register a route for one or more HTTP methods. Verb shortcuts
     * (`get`, `post`, …) call this with a single-method list.
     *
     * @param list<string>  $methods HTTP methods, case-insensitive (`'GET'`, `'post'`).
     * @param RouteOptions  $options Route options. See class docblock for the full key list.
     */
    public function match(array $methods, string $pattern, array $options, callable $handler): self
    {
        $methods = array_map(strtoupper(...), $methods);
        $compiled = $this->compile($methods, $this->prefix . $pattern, $options, $handler);
        $this->table->add($compiled);
        return $this;
    }

    /**
     * Generate a URL for a named route, substituting the given params into the
     * pattern (regex constraints stripped).
     *
     * @param array<string, string|int> $params
     */
    public function url(string $name, array $params = []): string
    {
        $pattern = $this->table->patternFor($name);
        if ($pattern === null) {
            throw new \RuntimeException("No route named: {$name}");
        }

        return preg_replace_callback(
            '#:([a-zA-Z_][a-zA-Z0-9_]*)(?:<[^>]+>)?#',
            function (array $m) use ($params, $name): string {
                $key = $m[1];
                if (!array_key_exists($key, $params)) {
                    throw new \RuntimeException("Missing param '{$key}' for route '{$name}'");
                }
                return rawurlencode((string) $params[$key]);
            },
            $pattern,
        );
    }

    /** @param RouteOptions $options */
    public function get(string $pattern, array $options, callable $handler): self
    {
        return $this->match(['GET'], $pattern, $options, $handler);
    }

    /** @param RouteOptions $options */
    public function post(string $pattern, array $options, callable $handler): self
    {
        return $this->match(['POST'], $pattern, $options, $handler);
    }

    /** @param RouteOptions $options */
    public function put(string $pattern, array $options, callable $handler): self
    {
        return $this->match(['PUT'], $pattern, $options, $handler);
    }

    /** @param RouteOptions $options */
    public function patch(string $pattern, array $options, callable $handler): self
    {
        return $this->match(['PATCH'], $pattern, $options, $handler);
    }

    /** @param RouteOptions $options */
    public function delete(string $pattern, array $options, callable $handler): self
    {
        return $this->match(['DELETE'], $pattern, $options, $handler);
    }

    /** @param RouteOptions $options */
    public function standardVerbs(string $pattern, array $options, callable $handler): self
    {
        return $this->match(self::STANDARD_METHODS, $pattern, $options, $handler);
    }

    public function dispatch(?ServerRequest $req = null): Response
    {
        if ($this->parent !== null) {
            return $this->parent->dispatch($req);
        }

        $req = $req ?? ServerRequest::fromGlobals();

        try {
            Request::bind($req);

            $pipeline = $this->buildPipeline(
                fn (ServerRequest $r): Response => $this->dispatchInner($r),
                $this->middleware,
            );
            return $this->applyResponsePolicy($pipeline($req), $req);
        } catch (\Throwable $e) {
            if ($this->exceptionHandler === null) {
                throw $e;
            }
            return $this->applyResponsePolicy(($this->exceptionHandler)($e), $req);
        }
    }

    private function applyResponsePolicy(Response $response, ServerRequest $req): Response
    {
        $response = $this->applyDefaultErrorHandler($response, $req);
        foreach ($this->defaultHeaders as $name => $value) {
            if ($response->header($name) === null) {
                $response->withHeader($name, $value);
            }
        }
        if ($this->strippedHeaders !== []) {
            $response->withStrippedHeaders($this->strippedHeaders);
        }
        return $response;
    }

    /**
     * Run an error response through the registered default-error handler, if
     * any. Non-error responses are returned untouched. The default handler's
     * return value is final — it is not itself re-processed, so a default that
     * accidentally returns another `Response::error(...)` is rendered as-is.
     */
    private function applyDefaultErrorHandler(Response $response, ServerRequest $req): Response
    {
        if ($response->status() < 400) {
            return $response;
        }
        $default = $this->root()->errorHandlers['*'] ?? null;
        if ($default === null) {
            return $response;
        }
        return $default($response, $req);
    }

    private function dispatchInner(ServerRequest $req): Response
    {
        $path = rtrim($req->path, '/') ?: '/';

        $pathMatched     = false;
        $allowedMethods  = [];
        $matchedRoute    = null;
        $matchedMatches  = null;
        $stripBody       = false;

        foreach ($this->table->sorted() as $route) {
            if (!preg_match($route['regex'], $path, $matches)) {
                continue;
            }
            $pathMatched = true;

            if (in_array($req->method, $route['methods'], true)) {
                $matchedRoute   = $route;
                $matchedMatches = $matches;
                $stripBody      = false;
                break;
            }

            foreach ($route['methods'] as $m) {
                $allowedMethods[$m] = true;
            }

            if ($matchedRoute === null && $req->method === 'HEAD' && in_array('GET', $route['methods'], true)) {
                $matchedRoute   = $route;
                $matchedMatches = $matches;
                $stripBody      = true;
            }
        }

        if ($matchedRoute !== null) {
            return $this->executeMatched($matchedRoute, $matchedMatches, $req, $stripBody);
        }

        if ($pathMatched) {
            if ($req->method === 'OPTIONS') {
                $allowed = array_keys($allowedMethods);
                if (in_array('GET', $allowed, true)) {
                    $allowed[] = 'HEAD';
                }
                $allowed[] = 'OPTIONS';
                $allowed = array_values(array_unique($allowed));
                sort($allowed);
                return Response::make()
                    ->withStatus(204)
                    ->withHeader('Allow', implode(', ', $allowed));
            }

            $req->setAttr('allowed_methods', implode(', ', array_keys($allowedMethods)));
            return ($this->errorHandlers[405])($req);
        }

        return ($this->errorHandlers[404])($req);
    }

    /**
     * @param RouteShape                $route
     * @param array<string|int, string> $matches
     */
    private function executeMatched(array $route, array $matches, ServerRequest $req, bool $stripBody): Response
    {
        $params = [];
        foreach ($route['paramNames'] as $name) {
            $value = $matches[$name];
            if (isset($route['decode'][$name])) {
                $decoderName = $route['decode'][$name];
                $decoder = $this->decoders[$decoderName]
                    ?? throw new \LogicException("Unknown decoder: {$decoderName}");
                $value = $decoder($value);
                if ($value === null) {
                    $status = $route['decode_failure'];
                    $handler = $this->errorHandlers[$status]
                        ?? fn (): Response => Response::error($status, 'Decode failed', code: 'DECODE_FAILED');
                    return $handler($req);
                }
            }
            $params[$name] = $value;
        }

        $bound = $req->withParams($params);

        $page = 1;
        $size = $this->paginationConfig['default_size'];
        if ($route['pagination']) {
            $rawSize = $bound->queryInt($this->paginationConfig['size_key'], $this->paginationConfig['default_size']);
            if ($rawSize < 1) {
                $rawSize = $this->paginationConfig['default_size'];
            }
            $size = min($rawSize, $this->paginationConfig['max_size']);
            $page = max(1, $bound->queryInt($this->paginationConfig['page_key'], 1));
            $bound->setAttr(Request::PAGE_ATTR, $page);
            $bound->setAttr(Request::SIZE_ATTR, $size);
        }

        Request::bind($bound);

        $inherited = $this->collectMiddleware($route['router']);
        $perRoute = $route['middleware'];
        if ($route['validate'] !== null) {
            $validate = $route['validate'];
            $perRoute[] = static function (ServerRequest $req, callable $next) use ($validate): Response {
                $errors = $validate($req);
                if ($errors !== null) {
                    return Response::error(422, 'Validation failed', code: 'VALIDATION_FAILED', data: $errors);
                }
                return $next($req);
            };
        }
        $pipeline = $this->buildPipeline(
            $route['handler'],
            [...$inherited, ...$perRoute],
        );
        $response = $pipeline($bound);

        if ($route['pagination'] && $response->total() !== null) {
            $total = $response->total();
            $pages = $total > 0 && $size > 0 ? (int) ceil($total / $size) : 0;
            $response->withMeta([
                'total' => $total,
                'page'  => $page,
                'size'  => $size,
                'pages' => $pages,
            ]);
        }

        if ($stripBody) {
            $response->withoutBody();
        }

        return $response;
    }

    /**
     * @param  list<string> $methods
     * @return RouteShape
     */
    private function compile(array $methods, string $pattern, array $options, callable $handler): array
    {
        $paramNames = [];
        $regex = preg_replace_callback(
            '#:([a-zA-Z_][a-zA-Z0-9_]*)(?:<([^>]+)>)?#',
            function (array $m) use (&$paramNames): string {
                $paramNames[] = $m[1];
                $inner = $m[2] ?? '[^/]+';
                return '(?<' . $m[1] . '>' . $inner . ')';
            },
            $pattern,
        );

        $specificity = [];
        foreach (explode('/', ltrim($pattern, '/')) as $segment) {
            if (!str_starts_with($segment, ':')) {
                $specificity[] = 2;
            } elseif (str_contains($segment, '<')) {
                $specificity[] = 1;
            } else {
                $specificity[] = 0;
            }
        }

        return [
            'methods'        => $methods,
            'pattern'        => $pattern,
            'regex'          => '#\A' . $regex . '\z#u',
            'paramNames'     => $paramNames,
            'specificity'    => $specificity,
            'middleware'     => $options['middleware'] ?? [],
            'decode'         => $options['decode'] ?? [],
            'decode_failure' => $options['decode_failure'] ?? 404,
            'pagination'     => $options['pagination'] ?? false,
            'validate'       => $options['validate'] ?? null,
            'name'           => $options['name'] ?? null,
            'handler'        => $handler,
            'router'         => $this,
        ];
    }

    /**
     * @param list<callable> $middlewares
     */
    private function buildPipeline(callable $handler, array $middlewares): callable
    {
        $next = $handler;
        foreach (array_reverse($middlewares) as $mw) {
            $current = $next;
            $next = fn (ServerRequest $req): Response => $mw($req, $current);
        }
        return $next;
    }

    private function root(): self
    {
        return $this->parent === null ? $this : $this->parent->root();
    }

    /**
     * Collect middleware for a sub-router walk, excluding the root router.
     * Root middleware is applied once at the outer level in dispatch() so it
     * also wraps 404 / 405 / OPTIONS responses; including it here would cause
     * it to run twice for matched routes.
     *
     * @return list<callable>
     */
    private function collectMiddleware(Router $for): array
    {
        $chain = [];
        $r = $for;
        while ($r->parent !== null) {
            $chain = [...$r->middleware, ...$chain];
            $r = $r->parent;
        }
        return $chain;
    }
}

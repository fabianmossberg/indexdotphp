<?php

declare(strict_types=1);

namespace IndexDotPhp\Router;

final class Router
{
    private const STANDARD_METHODS = ['GET', 'POST', 'PUT', 'PATCH', 'DELETE'];

    private ?Router $parent = null;
    private string $prefix = '';

    /** @var list<array{methods: list<string>, pattern: string, regex: string, paramNames: list<string>, specificity: list<int>, middleware: list<callable>, handler: callable, router: Router}> */
    private array $routes = [];

    private bool $sorted = true;

    /** @var array<int, callable> */
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
    private array $namedRoutes = [];

    public function __construct(array $config = [])
    {
        $this->errorHandlers = [
            404 => fn (): Response => Response::error(404, 'Route not found', code: 'ROUTE_NOT_FOUND'),
            405 => fn (): Response => Response::error(405, 'Method not allowed', code: 'METHOD_NOT_ALLOWED'),
        ];

        $this->paginationConfig = [
            'default_size' => $config['default_pagination_size'] ?? 20,
            'max_size'     => $config['max_pagination_size'] ?? 100,
            'page_key'     => $config['pagination_query_keys']['page'] ?? 'page',
            'size_key'     => $config['pagination_query_keys']['size'] ?? 'per_page',
        ];

        $this->decoders = [
            'int'        => fn (string $s): ?int => ctype_digit($s) ? (int) $s : null,
            'string'     => fn (string $s): string => $s,
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
        return $child;
    }

    public function onError(int $status, callable $handler): self
    {
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
     * @param list<string> $methods
     */
    public function match(array $methods, string $pattern, array $options, callable $handler): self
    {
        $compiled = $this->compile($methods, $this->prefix . $pattern, $options, $handler);
        $compiled['router'] = $this;
        $root = $this->root();
        $root->routes[] = $compiled;
        $root->sorted = false;
        if ($compiled['name'] !== null) {
            $root->namedRoutes[$compiled['name']] = $compiled['pattern'];
        }
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
        $pattern = $this->root()->namedRoutes[$name] ?? null;
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

    public function get(string $pattern, array $options, callable $handler): self
    {
        return $this->match(['GET'], $pattern, $options, $handler);
    }

    public function post(string $pattern, array $options, callable $handler): self
    {
        return $this->match(['POST'], $pattern, $options, $handler);
    }

    public function put(string $pattern, array $options, callable $handler): self
    {
        return $this->match(['PUT'], $pattern, $options, $handler);
    }

    public function patch(string $pattern, array $options, callable $handler): self
    {
        return $this->match(['PATCH'], $pattern, $options, $handler);
    }

    public function delete(string $pattern, array $options, callable $handler): self
    {
        return $this->match(['DELETE'], $pattern, $options, $handler);
    }

    public function any(string $pattern, array $options, callable $handler): self
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
            return $pipeline($req);
        } catch (\Throwable $e) {
            if ($this->exceptionHandler === null) {
                throw $e;
            }
            return ($this->exceptionHandler)($e);
        }
    }

    private function dispatchInner(ServerRequest $req): Response
    {
        if (!$this->sorted) {
            usort($this->routes, fn (array $a, array $b): int => $b['specificity'] <=> $a['specificity']);
            $this->sorted = true;
        }

        $path = rtrim($req->path, '/') ?: '/';

        $pathMatched     = false;
        $allowedMethods  = [];
        $matchedRoute    = null;
        $matchedMatches  = null;
        $stripBody       = false;

        foreach ($this->routes as $route) {
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
     * @param array{methods: list<string>, pattern: string, regex: string, paramNames: list<string>, specificity: list<int>, middleware: list<callable>, decode: array<string, string>, decode_failure: int, pagination: bool, handler: callable, router: Router} $route
     * @param array<string|int, string>                                                                                                                                                                                            $matches
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
            $size = min(
                $bound->queryInt($this->paginationConfig['size_key'], $this->paginationConfig['default_size']),
                $this->paginationConfig['max_size'],
            );
            $page = max(1, $bound->queryInt($this->paginationConfig['page_key'], 1));
            $bound->setAttr('_page', $page);
            $bound->setAttr('_size', $size);
        }

        Request::bind($bound);

        $inherited = $this->collectMiddleware($route['router']);
        $pipeline = $this->buildPipeline(
            $route['handler'],
            [...$inherited, ...$route['middleware']],
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
     * @param list<string> $methods
     * @return array{methods: list<string>, pattern: string, regex: string, paramNames: list<string>, specificity: list<int>, middleware: list<callable>, handler: callable}
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
            'name'           => $options['name'] ?? null,
            'handler'        => $handler,
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
        while ($r !== null && $r->parent !== null) {
            $chain = [...$r->middleware, ...$chain];
            $r = $r->parent;
        }
        return $chain;
    }
}

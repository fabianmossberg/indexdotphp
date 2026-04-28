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

    public function __construct(array $config = [])
    {
        $this->errorHandlers = [
            404 => fn(): Response => Response::error(404, 'route_not_found'),
            405 => fn(): Response => Response::error(405, 'method_not_allowed'),
        ];

        $this->decoders = [
            'int'        => fn(string $s): ?int => ctype_digit($s) ? (int) $s : null,
            'string'     => fn(string $s): string => $s,
            'slug'       => fn(string $s): ?string => preg_match('/^[a-z0-9-]+$/', $s) ? $s : null,
            'csv-int'    => function (string $s): ?array {
                $parts = explode(',', $s);
                foreach ($parts as $p) {
                    if (!ctype_digit($p)) {
                        return null;
                    }
                }
                return array_map('intval', $parts);
            },
            'csv-string' => fn(string $s): array => explode(',', $s),
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
        return $this;
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

        if ($req === null) {
            throw new \LogicException('SAPI-bound dispatch is not implemented yet; pass a ServerRequest.');
        }

        try {
            Request::bind($req);

            if (!$this->sorted) {
                usort($this->routes, fn(array $a, array $b): int => $b['specificity'] <=> $a['specificity']);
                $this->sorted = true;
            }

            $path = rtrim($req->path, '/') ?: '/';

            $pathMatched = false;
            $allowedMethods = [];

            foreach ($this->routes as $route) {
                if (!preg_match($route['regex'], $path, $matches)) {
                    continue;
                }
                $pathMatched = true;

                if (!in_array($req->method, $route['methods'], true)) {
                    foreach ($route['methods'] as $m) {
                        $allowedMethods[$m] = true;
                    }
                    continue;
                }

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
                                ?? fn(): Response => Response::error($status, 'decode_failed');
                            return $handler($req);
                        }
                    }
                    $params[$name] = $value;
                }

                $bound = $req->withParams($params);
                Request::bind($bound);

                $inherited = $this->collectMiddleware($route['router']);
                $pipeline = $this->buildPipeline(
                    $route['handler'],
                    [...$inherited, ...$route['middleware']],
                );
                return $pipeline($bound);
            }

            if ($pathMatched) {
                $req->setAttr('allowed_methods', implode(', ', array_keys($allowedMethods)));
                return ($this->errorHandlers[405])($req);
            }

            return ($this->errorHandlers[404])($req);
        } catch (\Throwable $e) {
            if ($this->exceptionHandler === null) {
                throw $e;
            }
            return ($this->exceptionHandler)($e);
        }
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
            $next = fn(ServerRequest $req): Response => $mw($req, $current);
        }
        return $next;
    }

    private function root(): self
    {
        return $this->parent === null ? $this : $this->parent->root();
    }

    /** @return list<callable> */
    private function collectMiddleware(Router $for): array
    {
        $chain = [];
        $r = $for;
        while ($r !== null) {
            $chain = [...$r->middleware, ...$chain];
            $r = $r->parent;
        }
        return $chain;
    }
}

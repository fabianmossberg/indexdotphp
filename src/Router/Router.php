<?php

declare(strict_types=1);

namespace IndexDotPhp\Router;

final class Router
{
    private const STANDARD_METHODS = ['GET', 'POST', 'PUT', 'PATCH', 'DELETE'];

    /** @var list<array{methods: list<string>, pattern: string, regex: string, paramNames: list<string>, specificity: list<int>, handler: callable}> */
    private array $routes = [];

    private bool $sorted = true;

    /** @var array<int, callable> */
    private array $errorHandlers;

    public function __construct(array $config = [])
    {
        $this->errorHandlers = [
            404 => fn(): Response => Response::error(404, 'route_not_found'),
            405 => fn(): Response => Response::error(405, 'method_not_allowed'),
        ];
    }

    public function onError(int $status, callable $handler): self
    {
        $this->errorHandlers[$status] = $handler;
        return $this;
    }

    /**
     * @param list<string> $methods
     */
    public function match(array $methods, string $pattern, array $options, callable $handler): self
    {
        $this->routes[] = $this->compile($methods, $pattern, $handler);
        $this->sorted = false;
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
        if ($req === null) {
            throw new \LogicException('SAPI-bound dispatch is not implemented yet; pass a ServerRequest.');
        }

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
                $params[$name] = $matches[$name];
            }

            $bound = $req->withParams($params);
            Request::bind($bound);

            return ($route['handler'])($bound);
        }

        if ($pathMatched) {
            $req->setAttr('allowed_methods', implode(', ', array_keys($allowedMethods)));
            Request::bind($req);
            return ($this->errorHandlers[405])($req);
        }

        Request::bind($req);
        return ($this->errorHandlers[404])($req);
    }

    /**
     * @param list<string> $methods
     * @return array{methods: list<string>, pattern: string, regex: string, paramNames: list<string>, specificity: list<int>, handler: callable}
     */
    private function compile(array $methods, string $pattern, callable $handler): array
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
            'methods'     => $methods,
            'pattern'     => $pattern,
            'regex'       => '#\A' . $regex . '\z#u',
            'paramNames'  => $paramNames,
            'specificity' => $specificity,
            'handler'     => $handler,
        ];
    }
}

<?php

declare(strict_types=1);

namespace IndexDotPhp\Router;

final class Router
{
    /** @var list<array{method: string, pattern: string, regex: string, paramNames: list<string>, handler: callable}> */
    private array $routes = [];

    public function __construct(array $config = [])
    {
    }

    public function get(string $pattern, array $options, callable $handler): self
    {
        $this->routes[] = $this->compile('GET', $pattern, $handler);
        return $this;
    }

    public function dispatch(?ServerRequest $req = null): Response
    {
        if ($req === null) {
            throw new \LogicException('SAPI-bound dispatch is not implemented yet; pass a ServerRequest.');
        }

        $path = rtrim($req->path, '/') ?: '/';

        foreach ($this->routes as $route) {
            if ($route['method'] !== $req->method) {
                continue;
            }
            if (!preg_match($route['regex'], $path, $matches)) {
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

        throw new \RuntimeException('No matching route (404 handling not implemented yet).');
    }

    /**
     * @return array{method: string, pattern: string, regex: string, paramNames: list<string>, handler: callable}
     */
    private function compile(string $method, string $pattern, callable $handler): array
    {
        $paramNames = [];
        $regex = preg_replace_callback(
            '#:([a-zA-Z_][a-zA-Z0-9_]*)#',
            function (array $m) use (&$paramNames): string {
                $paramNames[] = $m[1];
                return '(?<' . $m[1] . '>[^/]+)';
            },
            $pattern,
        );

        return [
            'method'     => $method,
            'pattern'    => $pattern,
            'regex'      => '#\A' . $regex . '\z#u',
            'paramNames' => $paramNames,
            'handler'    => $handler,
        ];
    }
}

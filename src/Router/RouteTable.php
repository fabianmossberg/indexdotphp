<?php

declare(strict_types=1);

namespace IndexDotPhp\Router;

/**
 * Storage for the compiled route list, the lazy-sort flag, and the named-route
 * lookup map. A Router tree shares a single RouteTable instance via prefix():
 * the root constructs one, and each sub-router takes the parent's reference.
 * This makes "routes live on the root" a structural fact rather than an
 * unwritten convention.
 *
 * @internal
 *
 * @phpstan-import-type RouteShape from Router
 */
final class RouteTable
{
    /** @var list<RouteShape> */
    private array $routes = [];

    private bool $sorted = true;

    /** @var array<string, string> */
    private array $namedRoutes = [];

    /** @param RouteShape $route */
    public function add(array $route): void
    {
        $this->routes[] = $route;
        $this->sorted = false;
        if ($route['name'] !== null) {
            $this->namedRoutes[$route['name']] = $route['pattern'];
        }
    }

    public function patternFor(string $name): ?string
    {
        return $this->namedRoutes[$name] ?? null;
    }

    /** @return list<RouteShape> */
    public function sorted(): array
    {
        if (!$this->sorted) {
            usort($this->routes, fn (array $a, array $b): int => $b['specificity'] <=> $a['specificity']);
            $this->sorted = true;
        }
        return $this->routes;
    }
}

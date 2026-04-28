<?php

declare(strict_types=1);

namespace IndexDotPhp\Router;

final class ServerRequest
{
    /** @var array<string, mixed> */
    private array $params = [];

    public function __construct(
        public readonly string $method,
        public readonly string $path,
    ) {
    }

    public function param(string $name, mixed $default = null): mixed
    {
        return $this->params[$name] ?? $default;
    }

    /** @return array<string, mixed> */
    public function params(): array
    {
        return $this->params;
    }

    /** @internal Router populates path params after a successful match. */
    public function withParams(array $params): self
    {
        $clone = clone $this;
        $clone->params = $params;
        return $clone;
    }
}

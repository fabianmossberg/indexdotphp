<?php

declare(strict_types=1);

namespace IndexDotPhp\Router;

final class ServerRequest
{
    /** @var array<string, mixed> */
    private array $params = [];

    /** @var array<string, mixed> */
    private array $attrs = [];

    /** @var array<string, string> */
    private readonly array $query;

    /** @var array<string, string> */
    private readonly array $headers;

    /** @var array<string, string> */
    private readonly array $cookies;

    private readonly string $rawBody;

    /**
     * @param array<string, string> $query
     * @param array<string, string> $headers
     * @param array<string, string> $cookies
     */
    public function __construct(
        public readonly string $method,
        public readonly string $path,
        array $query = [],
        array $headers = [],
        string $body = '',
        array $cookies = [],
    ) {
        $this->query = $query;
        $this->headers = array_change_key_case($headers, CASE_LOWER);
        $this->cookies = $cookies;
        $this->rawBody = $body;
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

    public function attr(string $name, mixed $default = null): mixed
    {
        return $this->attrs[$name] ?? $default;
    }

    public function setAttr(string $name, mixed $value): void
    {
        $this->attrs[$name] = $value;
    }

    public function query(string $name, ?string $default = null): ?string
    {
        return $this->query[$name] ?? $default;
    }

    public function queryInt(string $name, ?int $default = null): ?int
    {
        $v = $this->query[$name] ?? null;
        if ($v === null || !ctype_digit($v)) {
            return $default;
        }
        return (int) $v;
    }

    public function queryBool(string $name, bool $default = false): bool
    {
        $v = $this->query[$name] ?? null;
        if ($v === null) {
            return $default;
        }
        return in_array(strtolower($v), ['true', '1', 'on', 'yes'], true);
    }

    public function body(): string
    {
        return $this->rawBody;
    }

    public function bodyJson(bool $assoc = true): mixed
    {
        if ($this->rawBody === '') {
            return null;
        }
        return json_decode($this->rawBody, $assoc);
    }

    public function header(string $name): ?string
    {
        return $this->headers[strtolower($name)] ?? null;
    }

    /** @return array<string, string> */
    public function headers(): array
    {
        return $this->headers;
    }

    public function cookie(string $name): ?string
    {
        return $this->cookies[$name] ?? null;
    }

    /** @internal Router populates path params after a successful match. */
    public function withParams(array $params): self
    {
        $clone = clone $this;
        $clone->params = $params;
        return $clone;
    }
}

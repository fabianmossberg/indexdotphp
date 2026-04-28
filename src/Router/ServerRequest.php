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

    /**
     * @param array<string, mixed>|null  $server
     * @param array<string, mixed>|null  $get
     * @param array<string, string>|null $cookies
     */
    public static function fromGlobals(
        ?array $server = null,
        ?array $get = null,
        ?array $cookies = null,
        ?string $body = null,
    ): self {
        $server  = $server  ?? $_SERVER;
        $get     = $get     ?? $_GET;
        $cookies = $cookies ?? $_COOKIE;
        $body    = $body    ?? (file_get_contents('php://input') ?: '');

        $method = $server['REQUEST_METHOD'] ?? 'GET';
        $path   = parse_url($server['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';

        $headers = [];
        foreach ($server as $key => $value) {
            if (is_string($key) && str_starts_with($key, 'HTTP_')) {
                $headers[strtolower(str_replace('_', '-', substr($key, 5)))] = (string) $value;
            }
        }
        if (isset($server['CONTENT_TYPE'])) {
            $headers['content-type'] = (string) $server['CONTENT_TYPE'];
        }
        if (isset($server['CONTENT_LENGTH'])) {
            $headers['content-length'] = (string) $server['CONTENT_LENGTH'];
        }

        return new self(
            method:  $method,
            path:    $path,
            query:   $get,
            headers: $headers,
            body:    $body,
            cookies: $cookies,
        );
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

    /**
     * @param  list<string> $defaults
     * @return list<string>
     */
    public function queryCsv(string $name, array $defaults = []): array
    {
        $v = $this->query[$name] ?? null;
        if ($v === null || $v === '') {
            return $defaults;
        }
        return explode(',', $v);
    }

    /**
     * @param  list<int> $defaults
     * @param  list<int> $allowed
     * @return list<int>
     */
    public function queryCsvInts(string $name, array $defaults = [], array $allowed = []): array
    {
        $v = $this->query[$name] ?? null;
        if ($v === null || $v === '') {
            return $defaults;
        }
        $parts = explode(',', $v);
        $result = [];
        foreach ($parts as $p) {
            if (!ctype_digit($p)) {
                return $defaults;
            }
            $n = (int) $p;
            if ($allowed !== [] && !in_array($n, $allowed, true)) {
                return $defaults;
            }
            $result[] = $n;
        }
        return $result;
    }

    /**
     * @param  list<string> $defaults
     * @param  list<string> $allowed
     * @return list<string>
     */
    public function queryCsvStrings(string $name, array $defaults = [], array $allowed = []): array
    {
        $v = $this->query[$name] ?? null;
        if ($v === null || $v === '') {
            return $defaults;
        }
        $parts = explode(',', $v);
        if ($allowed !== []) {
            foreach ($parts as $p) {
                if (!in_array($p, $allowed, true)) {
                    return $defaults;
                }
            }
        }
        return $parts;
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

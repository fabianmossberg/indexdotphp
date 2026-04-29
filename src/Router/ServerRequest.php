<?php

declare(strict_types=1);

namespace IndexDotPhp\Router;

final class ServerRequest
{
    /** @var array<string, mixed> */
    private array $params = [];

    /** @var array<string, mixed> */
    private array $attrs = [];

    /** @var array<string, mixed> */
    private readonly array $query;

    /** @var array<string, string> */
    private readonly array $headers;

    /** @var array<string, string> */
    private readonly array $cookies;

    private readonly string $rawBody;

    /**
     * Query values may be strings (the common case) or arrays when the URL uses
     * PHP's bracket form (`?foo[]=a&foo[]=b`). The typed accessors (`query`,
     * `queryInt`, `queryCsv*`, etc.) silently fall through to their defaults
     * when the underlying value is not a string, so handlers don't need to
     * check the shape themselves.
     *
     * @param array<string, mixed>  $query
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
     * @param array<string, mixed>|null  $get      raw `$_GET`-style array; bracket-form values may be sub-arrays
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
        $v = $this->query[$name] ?? null;
        return is_string($v) ? $v : $default;
    }

    public function queryInt(string $name, ?int $default = null): ?int
    {
        $v = $this->query[$name] ?? null;
        if (!is_string($v) || !ctype_digit($v)) {
            return $default;
        }
        return (int) $v;
    }

    public function queryBool(string $name, bool $default = false): bool
    {
        $v = $this->query[$name] ?? null;
        if (!is_string($v)) {
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
        if (!is_string($v) || $v === '') {
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
        if (!is_string($v) || $v === '') {
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
        if (!is_string($v) || $v === '') {
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
        return json_decode($this->rawBody, $assoc, flags: JSON_THROW_ON_ERROR);
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

    public function accepts(string $contentType): bool
    {
        $entries = $this->parseAccept();
        if ($entries === []) {
            return true;
        }
        return $this->bestMatchQuality($contentType, $entries) > 0;
    }

    /**
     * @param list<string> $supported
     */
    public function preferredContentType(array $supported): ?string
    {
        $entries = $this->parseAccept();
        if ($entries === []) {
            return $supported[0] ?? null;
        }

        $best  = null;
        $bestQ = 0.0;
        foreach ($supported as $type) {
            $q = $this->bestMatchQuality($type, $entries);
            if ($q > $bestQ) {
                $bestQ = $q;
                $best  = $type;
            }
        }
        return $best;
    }

    /** @return list<array{type: string, q: float}> */
    private function parseAccept(): array
    {
        $header = $this->headers['accept'] ?? null;
        if ($header === null || $header === '') {
            return [];
        }

        $entries = [];
        foreach (explode(',', $header) as $part) {
            $part = trim($part);
            if ($part === '') {
                continue;
            }
            $segments = array_map('trim', explode(';', $part));
            $type = $segments[0];
            $q = 1.0;
            for ($i = 1, $n = count($segments); $i < $n; $i++) {
                if (str_starts_with($segments[$i], 'q=')) {
                    $q = (float) substr($segments[$i], 2);
                }
            }
            $entries[] = ['type' => $type, 'q' => $q];
        }
        return $entries;
    }

    /** @param list<array{type: string, q: float}> $entries */
    private function bestMatchQuality(string $type, array $entries): float
    {
        $bestSpec = 0;
        $bestQ    = 0.0;
        foreach ($entries as $e) {
            $spec = $this->matchSpecificity($e['type'], $type);
            if ($spec > $bestSpec) {
                $bestSpec = $spec;
                $bestQ    = $e['q'];
            }
        }
        return $bestQ;
    }

    private function matchSpecificity(string $pattern, string $type): int
    {
        if ($pattern === $type) {
            return 3;
        }
        if (str_ends_with($pattern, '/*')) {
            $prefix = substr($pattern, 0, -1);
            if (str_starts_with($type, $prefix)) {
                return 2;
            }
        }
        if ($pattern === '*/*') {
            return 1;
        }
        return 0;
    }

    /** @internal Router populates path params after a successful match. */
    public function withParams(array $params): self
    {
        $clone = clone $this;
        $clone->params = $params;
        return $clone;
    }
}

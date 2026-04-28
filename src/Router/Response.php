<?php

declare(strict_types=1);

namespace IndexDotPhp\Router;

final class Response
{
    /** @var list<string> */
    private array $messages = [];

    /** @var array<string, string> */
    private array $headers = [];

    /** @var array<string, mixed> */
    private array $meta = [];

    /** @var array<string, array{value: string, options: array<string, mixed>}> */
    private array $cookies = [];

    private ?string $rawBody = null;

    private ?int $total = null;

    private bool $stripBody = false;

    private function __construct(
        private int $status,
        private mixed $items,
    ) {
    }

    public static function ok(mixed $items, ?string $message = null): self
    {
        $r = new self(200, $items);
        if ($message !== null) {
            $r->messages[] = $message;
        }
        return $r;
    }

    public static function error(int $status, string $message, mixed $items = null): self
    {
        $r = new self($status, $items);
        $r->messages[] = $message;
        return $r;
    }

    /** @param array<int|string, mixed> $items */
    public static function list(array $items, int $total, ?string $message = null): self
    {
        $r = new self(200, $items);
        $r->total = $total;
        if ($message !== null) {
            $r->messages[] = $message;
        }
        return $r;
    }

    public static function noContent(): self
    {
        return new self(204, null);
    }

    public static function redirect(string $location, int $status = 302): self
    {
        $r = new self($status, null);
        $r->headers['Location'] = $location;
        return $r;
    }

    public static function make(): self
    {
        return new self(200, null);
    }

    public function withStatus(int $code): self
    {
        $this->status = $code;
        return $this;
    }

    public function withItems(mixed $items): self
    {
        $this->items = $items;
        return $this;
    }

    public function withMessage(string $message): self
    {
        $this->messages[] = $message;
        return $this;
    }

    /** @param array<string, mixed> $meta */
    public function withMeta(array $meta): self
    {
        $this->meta = $meta;
        return $this;
    }

    public function withHeader(string $name, string $value): self
    {
        $this->headers[$name] = $value;
        return $this;
    }

    public function withContentType(string $type): self
    {
        return $this->withHeader('Content-Type', $type);
    }

    public function withRaw(string $body, string $contentType): self
    {
        $this->rawBody = $body;
        return $this->withContentType($contentType);
    }

    /** Strip the body from this response while keeping status/headers (for HEAD). */
    public function withoutBody(): self
    {
        $this->stripBody = true;
        return $this;
    }

    /** @param array<string, mixed> $options */
    public function withCookie(string $name, string $value, array $options = []): self
    {
        $this->cookies[$name] = ['value' => $value, 'options' => $options];
        return $this;
    }

    /** @return array<string, array{value: string, options: array<string, mixed>}> */
    public function cookies(): array
    {
        return $this->cookies;
    }

    public function status(): int
    {
        return $this->status;
    }

    public function total(): ?int
    {
        return $this->total;
    }

    public function header(string $name): ?string
    {
        return $this->headers[$name] ?? null;
    }

    /** @return array<string, string> */
    public function headers(): array
    {
        return $this->headers;
    }

    public function body(): string
    {
        if ($this->stripBody) {
            return '';
        }

        if ($this->rawBody !== null) {
            return $this->rawBody;
        }

        if ($this->status === 204) {
            return '';
        }

        $envelope = ['items' => $this->items];
        if ($this->meta !== []) {
            $envelope['meta'] = $this->meta;
        }
        if ($this->messages !== []) {
            $envelope['message'] = $this->messages;
        }

        return json_encode(
            $envelope,
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR,
        );
    }

    public function send(): void
    {
        http_response_code($this->status);

        $headers = $this->headers;
        if ($this->status !== 204 && $this->rawBody === null && !isset($headers['Content-Type'])) {
            $headers['Content-Type'] = 'application/json';
        }

        foreach ($headers as $name => $value) {
            header(sprintf('%s: %s', $name, $value), true);
        }

        foreach ($this->cookies as $name => $cookie) {
            $opts = array_change_key_case($cookie['options'], CASE_LOWER);
            setcookie($name, $cookie['value'], $opts);
        }

        echo $this->body();
    }
}

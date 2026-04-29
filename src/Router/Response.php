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

    private ?string $errorMessage = null;

    private ?string $errorCode = null;

    /** @var list<string> */
    private array $strippedHeaders = [];

    private const DEFAULT_ERROR_CODES = [
        400 => 'BAD_REQUEST',
        401 => 'UNAUTHORIZED',
        403 => 'FORBIDDEN',
        404 => 'NOT_FOUND',
        405 => 'METHOD_NOT_ALLOWED',
        409 => 'CONFLICT',
        410 => 'GONE',
        422 => 'UNPROCESSABLE_ENTITY',
        429 => 'TOO_MANY_REQUESTS',
        500 => 'INTERNAL_SERVER_ERROR',
        501 => 'NOT_IMPLEMENTED',
        502 => 'BAD_GATEWAY',
        503 => 'SERVICE_UNAVAILABLE',
        504 => 'GATEWAY_TIMEOUT',
    ];

    private function __construct(
        private int $status,
        private mixed $data,
    ) {
    }

    public static function ok(mixed $data, ?string $message = null): self
    {
        $r = new self(200, $data);
        if ($message !== null) {
            $r->messages[] = $message;
        }
        return $r;
    }

    public static function error(int $status, string $message, ?string $code = null, mixed $data = null): self
    {
        $r = new self($status, $data);
        $r->errorMessage = $message;
        $r->errorCode = $code;
        return $r;
    }

    /** @param array<int|string, mixed> $data */
    public static function list(array $data, int $total, ?string $message = null): self
    {
        $r = new self(200, $data);
        $r->total = $total;
        if ($message !== null) {
            $r->messages[] = $message;
        }
        return $r;
    }

    public static function raw(string $body, string $contentType = 'text/plain'): self
    {
        $r = new self(200, null);
        $r->rawBody = $body;
        $r->headers['Content-Type'] = $contentType;
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

    public function withData(mixed $data): self
    {
        $this->data = $data;
        return $this;
    }

    public function withMessage(string $message): self
    {
        $this->messages[] = $message;
        return $this;
    }

    public function withCode(string $code): self
    {
        $this->errorCode = $code;
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

    public function withoutHeader(string $name): self
    {
        unset($this->headers[$name]);
        return $this;
    }

    /**
     * Mark headers to be stripped at send time via PHP's header_remove(), used
     * by Router::stripHeaders() to suppress SAPI defaults like the X-Powered-By
     * value PHP injects from php.ini's expose_php directive.
     *
     * @param list<string> $names
     */
    public function withStrippedHeaders(array $names): self
    {
        foreach ($names as $name) {
            if (!in_array($name, $this->strippedHeaders, true)) {
                $this->strippedHeaders[] = $name;
            }
        }
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

    /** @return list<string> */
    public function strippedHeaders(): array
    {
        return $this->strippedHeaders;
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

        $envelope = ['data' => $this->data];
        if ($this->meta !== []) {
            $envelope['meta'] = $this->meta;
        }
        if ($this->status >= 400) {
            $envelope['error'] = [
                'status'  => $this->status,
                'code'    => $this->errorCode ?? self::DEFAULT_ERROR_CODES[$this->status] ?? ($this->status >= 500 ? 'SERVER_ERROR' : 'CLIENT_ERROR'),
                'message' => $this->errorMessage ?? '',
            ];
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

        foreach ($this->strippedHeaders as $name) {
            header_remove($name);
        }

        if ($this->status !== 204 && $this->rawBody === null && !isset($this->headers['Content-Type'])) {
            $this->headers['Content-Type'] = 'application/json';
        }

        foreach ($this->headers as $name => $value) {
            header(sprintf('%s: %s', $name, $value), true);
        }

        foreach ($this->cookies as $name => $cookie) {
            $opts = array_change_key_case($cookie['options'], CASE_LOWER);
            setcookie($name, $cookie['value'], $opts);
        }

        echo $this->body();
    }
}

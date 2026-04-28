<?php

declare(strict_types=1);

namespace IndexDotPhp\Router;

final class Response
{
    /** @var list<string> */
    private array $messages = [];

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

    public function status(): int
    {
        return $this->status;
    }

    public function body(): string
    {
        $envelope = ['items' => $this->items];
        if ($this->messages !== []) {
            $envelope['message'] = $this->messages;
        }

        return json_encode(
            $envelope,
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR,
        );
    }
}

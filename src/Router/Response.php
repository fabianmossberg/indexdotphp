<?php

declare(strict_types=1);

namespace IndexDotPhp\Router;

final class Response
{
    private function __construct(
        private int $status,
        private mixed $items,
    ) {
    }

    public static function ok(mixed $items, ?string $message = null): self
    {
        return new self(200, $items);
    }

    public function status(): int
    {
        return $this->status;
    }

    public function body(): string
    {
        return json_encode(
            ['items' => $this->items],
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR,
        );
    }
}

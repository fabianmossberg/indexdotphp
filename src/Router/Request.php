<?php

declare(strict_types=1);

namespace IndexDotPhp\Router;

final class Request
{
    private static ?ServerRequest $bound = null;

    /** @internal Router calls this before each handler invocation. */
    public static function bind(ServerRequest $req): void
    {
        self::$bound = $req;
    }

    public static function param(string $name, mixed $default = null): mixed
    {
        return self::current()->param($name, $default);
    }

    /** @return array<string, mixed> */
    public static function params(): array
    {
        return self::current()->params();
    }

    public static function attr(string $name, mixed $default = null): mixed
    {
        return self::current()->attr($name, $default);
    }

    public static function setAttr(string $name, mixed $value): void
    {
        self::current()->setAttr($name, $value);
    }

    private static function current(): ServerRequest
    {
        if (self::$bound === null) {
            throw new \LogicException('Request facade accessed before Router::dispatch() bound a request.');
        }
        return self::$bound;
    }
}

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

    public static function query(string $name, ?string $default = null): ?string
    {
        return self::current()->query($name, $default);
    }

    public static function queryInt(string $name, ?int $default = null): ?int
    {
        return self::current()->queryInt($name, $default);
    }

    public static function queryBool(string $name, bool $default = false): bool
    {
        return self::current()->queryBool($name, $default);
    }

    public static function body(): string
    {
        return self::current()->body();
    }

    public static function bodyJson(bool $assoc = true): mixed
    {
        return self::current()->bodyJson($assoc);
    }

    public static function header(string $name): ?string
    {
        return self::current()->header($name);
    }

    /** @return array<string, string> */
    public static function headers(): array
    {
        return self::current()->headers();
    }

    public static function method(): string
    {
        return self::current()->method;
    }

    public static function path(): string
    {
        return self::current()->path;
    }

    public static function cookie(string $name): ?string
    {
        return self::current()->cookie($name);
    }

    public static function page(): int
    {
        return self::current()->attr('_page', 1);
    }

    public static function size(): int
    {
        return self::current()->attr('_size', 20);
    }

    private static function current(): ServerRequest
    {
        if (self::$bound === null) {
            throw new \LogicException('Request facade accessed before Router::dispatch() bound a request.');
        }
        return self::$bound;
    }
}

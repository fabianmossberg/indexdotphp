<?php

declare(strict_types=1);

namespace IndexDotPhp\Router\Middleware;

use IndexDotPhp\Router\Response;
use IndexDotPhp\Router\ServerRequest;

final class Timing
{
    /** @var array<string, float> */
    private static array $entries = [];

    public function __invoke(ServerRequest $req, callable $next): Response
    {
        self::$entries = [];

        $start = microtime(true);
        $response = $next($req);
        $totalMs = (microtime(true) - $start) * 1000;

        $parts = [];
        foreach (self::$entries as $name => $ms) {
            $parts[] = sprintf('%s;dur=%s', $name, round($ms, 2));
        }
        $parts[] = sprintf('total;dur=%s', round($totalMs, 2));

        $response->withHeader('Server-Timing', implode(', ', $parts));

        return $response;
    }

    /**
     * Time a closure and record it under $name. Repeated calls with the
     * same name accumulate (useful when the same operation runs multiple
     * times per request, e.g. several DB queries).
     *
     * @template T
     * @param callable(): T $work
     * @return T
     */
    public static function measure(string $name, callable $work): mixed
    {
        $start = microtime(true);
        try {
            return $work();
        } finally {
            $elapsedMs = (microtime(true) - $start) * 1000;
            self::$entries[$name] = (self::$entries[$name] ?? 0.0) + $elapsedMs;
        }
    }
}

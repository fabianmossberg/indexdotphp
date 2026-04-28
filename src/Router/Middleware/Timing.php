<?php

declare(strict_types=1);

namespace IndexDotPhp\Router\Middleware;

use IndexDotPhp\Router\Response;
use IndexDotPhp\Router\ServerRequest;

final class Timing
{
    public function __invoke(ServerRequest $req, callable $next): Response
    {
        $start = microtime(true);
        $response = $next($req);
        $ms = round((microtime(true) - $start) * 1000, 2);

        $response->withHeader('Server-Timing', sprintf('total;dur=%s', $ms));

        return $response;
    }
}

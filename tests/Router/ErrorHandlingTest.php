<?php

declare(strict_types=1);

namespace IndexDotPhp\Tests\Router;

use IndexDotPhp\Router\Request;
use IndexDotPhp\Router\Response;
use IndexDotPhp\Router\Router;
use IndexDotPhp\Router\ServerRequest;
use PHPUnit\Framework\TestCase;

final class ErrorHandlingTest extends TestCase
{
    public function testReturnsDefault404WhenNoRouteMatches(): void
    {
        $router = new Router();

        $response = $router->dispatch(new ServerRequest(method: 'GET', path: '/nope'));

        self::assertSame(404, $response->status());
        self::assertSame('{"items":null,"message":["route_not_found"]}', $response->body());
    }

    public function testReturnsDefault405WhenPathMatchesButMethodDoesNot(): void
    {
        $router = new Router();
        $router->get('/foo', [], fn(): Response => Response::ok([]));

        $response = $router->dispatch(new ServerRequest(method: 'POST', path: '/foo'));

        self::assertSame(405, $response->status());
        self::assertSame('{"items":null,"message":["method_not_allowed"]}', $response->body());
    }

    public function testCustomOnError404Overrides(): void
    {
        $router = new Router();
        $router->onError(404, fn(): Response => Response::error(404, 'custom_not_found'));

        $response = $router->dispatch(new ServerRequest(method: 'GET', path: '/nope'));

        self::assertSame(404, $response->status());
        self::assertSame('{"items":null,"message":["custom_not_found"]}', $response->body());
    }

    public function test405HandlerCanReadAllowedMethodsFromAttr(): void
    {
        $router = new Router();
        $router->get('/foo', [], fn(): Response => Response::ok([]));
        $router->put('/foo', [], fn(): Response => Response::ok([]));
        $router->onError(405, fn(): Response => Response::error(
            405,
            'allowed: ' . Request::attr('allowed_methods'),
        ));

        $response = $router->dispatch(new ServerRequest(method: 'POST', path: '/foo'));

        self::assertSame(405, $response->status());
        $body = $response->body();
        self::assertStringContainsString('GET', $body);
        self::assertStringContainsString('PUT', $body);
    }
}

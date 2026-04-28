<?php

declare(strict_types=1);

namespace IndexDotPhp\Tests\Router;

use IndexDotPhp\Router\Request;
use IndexDotPhp\Router\Response;
use IndexDotPhp\Router\Router;
use IndexDotPhp\Router\ServerRequest;
use PHPUnit\Framework\TestCase;

final class QuickStartTest extends TestCase
{
    public function testGetHelloNameReturnsGreeting(): void
    {
        $router = new Router();

        $router->get('/hello/:name', [], function (): Response {
            return Response::ok(['greeting' => 'Hello, ' . Request::param('name')]);
        });

        $response = $router->dispatch(new ServerRequest(method: 'GET', path: '/hello/world'));

        self::assertSame(200, $response->status());
        self::assertSame(
            '{"items":{"greeting":"Hello, world"}}',
            $response->body(),
        );
    }
}

<?php

declare(strict_types=1);

namespace IndexDotPhp\Tests\Router;

use IndexDotPhp\Router\Response;
use IndexDotPhp\Router\Router;
use IndexDotPhp\Router\ServerRequest;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class HttpVerbsTest extends TestCase
{
    /** @return iterable<string, array{string, string}> */
    public static function verbs(): iterable
    {
        yield 'GET'    => ['GET',    'get'];
        yield 'POST'   => ['POST',   'post'];
        yield 'PUT'    => ['PUT',    'put'];
        yield 'PATCH'  => ['PATCH',  'patch'];
        yield 'DELETE' => ['DELETE', 'delete'];
    }

    #[DataProvider('verbs')]
    public function testEachVerbHasMatchingRegistrationMethod(string $method, string $fn): void
    {
        $router = new Router();
        $router->{$fn}('/x', [], fn(): Response => Response::ok(['m' => $method]));

        $response = $router->dispatch(new ServerRequest(method: $method, path: '/x'));

        self::assertSame(200, $response->status());
        self::assertSame('{"items":{"m":"' . $method . '"}}', $response->body());
    }

    public function testMatchRegistersMultipleMethodsForSamePattern(): void
    {
        $router = new Router();
        $router->match(['GET', 'POST'], '/foo', [], fn(): Response => Response::ok(['ok' => true]));

        $get  = $router->dispatch(new ServerRequest(method: 'GET',  path: '/foo'));
        $post = $router->dispatch(new ServerRequest(method: 'POST', path: '/foo'));

        self::assertSame(200, $get->status());
        self::assertSame(200, $post->status());
    }

    public function testAnyMatchesStandardVerbs(): void
    {
        $router = new Router();
        $router->any('/x', [], fn(): Response => Response::ok(['ok' => true]));

        foreach (['GET', 'POST', 'PUT', 'PATCH', 'DELETE'] as $method) {
            $response = $router->dispatch(new ServerRequest(method: $method, path: '/x'));
            self::assertSame(200, $response->status(), "method=$method");
        }
    }
}

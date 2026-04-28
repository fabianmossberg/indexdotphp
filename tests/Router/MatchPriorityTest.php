<?php

declare(strict_types=1);

namespace IndexDotPhp\Tests\Router;

use IndexDotPhp\Router\Request;
use IndexDotPhp\Router\Response;
use IndexDotPhp\Router\Router;
use IndexDotPhp\Router\ServerRequest;
use PHPUnit\Framework\TestCase;

final class MatchPriorityTest extends TestCase
{
    public function testStaticSegmentBeatsDynamicEvenWhenRegisteredAfter(): void
    {
        $router = new Router();
        $router->get('/users/:id', [], fn(): Response => Response::ok([
            'route' => 'show',
            'id'    => Request::param('id'),
        ]));
        $router->get('/users/me', [], fn(): Response => Response::ok([
            'route' => 'me',
        ]));

        $response = $router->dispatch(new ServerRequest(method: 'GET', path: '/users/me'));

        self::assertSame(200, $response->status());
        self::assertSame('{"items":{"route":"me"}}', $response->body());
    }

    public function testDynamicSegmentStillMatchesWhenStaticDoesNot(): void
    {
        $router = new Router();
        $router->get('/users/:id', [], fn(): Response => Response::ok([
            'route' => 'show',
            'id'    => Request::param('id'),
        ]));
        $router->get('/users/me', [], fn(): Response => Response::ok([
            'route' => 'me',
        ]));

        $response = $router->dispatch(new ServerRequest(method: 'GET', path: '/users/42'));

        self::assertSame(200, $response->status());
        self::assertSame('{"items":{"route":"show","id":"42"}}', $response->body());
    }
}

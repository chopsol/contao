<?php

declare(strict_types=1);

/*
 * This file is part of Contao.
 *
 * (c) Leo Feyer
 *
 * @license LGPL-3.0-or-later
 */

namespace Contao\CoreBundle\Tests\Routing;

use Contao\CoreBundle\Routing\FrontendLoader;
use Contao\CoreBundle\Routing\LegacyRouteProvider;
use Contao\CoreBundle\Tests\TestCase;
use PHPUnit\Framework\MockObject\MockObject;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Exception\RouteNotFoundException;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;

class LegacyRouteProviderTest extends TestCase
{
    /**
     * @var FrontendLoader&MockObject
     */
    private $frontendLoader;

    /**
     * @var LegacyRouteProvider
     */
    private $provider;

    protected function setUp(): void
    {
        $this->frontendLoader = $this->createMock(FrontendLoader::class);
        $this->provider = new LegacyRouteProvider($this->frontendLoader);
    }

    public function testReturnsEmptyRouteCollectionForRequest(): void
    {
        $request = $this->createMock(Request::class);
        $request
            ->expects($this->never())
            ->method($this->anything())
        ;

        $collection = $this->provider->getRouteCollectionForRequest($request);

        $this->assertEmpty($collection->all());
    }

    public function testReturnsEmptyArrayForRoutesByNames(): void
    {
        $routes = $this->provider->getRoutesByNames(['foo', 'bar']);

        $this->assertEmpty($routes);
    }

    public function testThrowsExceptionOnUnsupportedRouteName(): void
    {
        $this->expectException(RouteNotFoundException::class);

        $this->provider->getRouteByName('foo');
    }

    public function testLoadsContaoFrontendRouteFromFrontendLoader(): void
    {
        $route = $this->createMock(Route::class);

        $collection = $this->createMock(RouteCollection::class);
        $collection
            ->expects($this->once())
            ->method('get')
            ->with('contao_frontend')
            ->willReturn($route)
        ;

        $this->frontendLoader
            ->expects($this->once())
            ->method('load')
            ->with('.', 'contao_frontend')
            ->willReturn($collection)
        ;

        $this->assertSame($route, $this->provider->getRouteByName('contao_frontend'));
    }

    public function testLoadsContaoIndexRouteFromFrontendLoader(): void
    {
        $route = $this->createMock(Route::class);

        $collection = $this->createMock(RouteCollection::class);
        $collection
            ->expects($this->once())
            ->method('get')
            ->with('contao_index')
            ->willReturn($route)
        ;

        $this->frontendLoader
            ->expects($this->once())
            ->method('load')
            ->with('.', 'contao_frontend')
            ->willReturn($collection)
        ;

        $this->assertSame($route, $this->provider->getRouteByName('contao_index'));
    }

    public function testReturnsTheContaoRootRoute(): void
    {
        $this->frontendLoader
            ->expects($this->never())
            ->method($this->anything())
        ;

        $route = $this->provider->getRouteByName('contao_root');

        $this->assertSame('/', $route->getPath());
        $this->assertSame(
            [
                '_scope' => 'frontend',
                '_token_check' => true,
                '_controller' => 'Contao\CoreBundle\Controller\FrontendController::indexAction',
            ],
            $route->getDefaults()
        );
    }

    public function testReturnsTheContaoCatchAllRoute(): void
    {
        $this->frontendLoader
            ->expects($this->never())
            ->method($this->anything())
        ;

        $route = $this->provider->getRouteByName('contao_catch_all');

        $this->assertSame('/{_url_fragment}', $route->getPath());
        $this->assertSame(
            [
                '_scope' => 'frontend',
                '_token_check' => true,
                '_controller' => 'Contao\CoreBundle\Controller\FrontendController::indexAction',
            ],
            $route->getDefaults()
        );
        $this->assertSame(['_url_fragment' => '.*'], $route->getRequirements());
    }
}

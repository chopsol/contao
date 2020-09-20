<?php

declare(strict_types=1);

/*
 * This file is part of Contao.
 *
 * (c) Leo Feyer
 *
 * @license LGPL-3.0-or-later
 */

namespace Contao\CoreBundle\Tests\Contao;

use Contao\Config;
use Contao\CoreBundle\Framework\Adapter;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\Environment;
use Contao\Frontend;
use Contao\Model\Collection;
use Contao\PageModel;
use Contao\System;
use Contao\TestCase\ContaoTestCase;
use PHPUnit\Framework\MockObject\MockObject;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * @group legacy
 */
class RoutingTest extends ContaoTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Config::set('urlSuffix', '.html');
        Config::set('addLanguageToUrl', false);
        Config::set('useAutoItem', false);

        Environment::reset();

        $container = new ContainerBuilder();
        $container->setParameter('contao.legacy_routing', true);

        System::setContainer($container);

        $_GET = [];
        $GLOBALS['TL_AUTO_ITEM'] = ['items'];
    }

    /**
     * @expectedDeprecation Since contao/core-bundle 4.7: Using "Contao\Frontend::getPageIdFromUrl()" has been deprecated %s.
     */
    public function testReturnsThePageIdFromTheUrl(): void
    {
        $_SERVER['REQUEST_URI'] = 'home.html?foo=bar';

        $request = $this->createMock(Request::class);
        $request
            ->method('getBasePath')
            ->willReturn('')
        ;

        $request
            ->method('getScriptName')
            ->willReturn('index.php')
        ;

        $requestStack = new RequestStack();
        $requestStack->push($request);

        System::getContainer()->set('request_stack', $requestStack);

        $this->assertSame('home', Frontend::getPageIdFromUrl());
        $this->assertEmpty($_GET);
    }

    /**
     * @expectedDeprecation Since contao/core-bundle 4.7: Using "Contao\Frontend::getPageIdFromUrl()" has been deprecated %s.
     */
    public function testReturnsNullIfTheRequestIsEmpty(): void
    {
        $_SERVER['REQUEST_URI'] = '/';

        System::getContainer()->set('request_stack', new RequestStack());

        $this->assertNull(Frontend::getPageIdFromUrl());
        $this->assertEmpty($_GET);
    }

    /**
     * @expectedDeprecation Since contao/core-bundle 4.7: Using "Contao\Frontend::getPageIdFromUrl()" has been deprecated %s.
     */
    public function testReturnsFalseIfTheRequestContainsAutoItem(): void
    {
        $_SERVER['REQUEST_URI'] = 'home/auto_item/foo.html';

        $request = $this->createMock(Request::class);
        $request
            ->method('getBasePath')
            ->willReturn('')
        ;

        $request
            ->method('getScriptName')
            ->willReturn('index.php')
        ;

        $requestStack = new RequestStack();
        $requestStack->push($request);

        System::getContainer()->set('request_stack', $requestStack);

        $this->assertFalse(Frontend::getPageIdFromUrl());
        $this->assertEmpty($_GET);
    }

    /**
     * @expectedDeprecation Since contao/core-bundle 4.7: Using "Contao\Frontend::getPageIdFromUrl()" has been deprecated %s.
     */
    public function testReturnsFalseIfTheUrlSuffixDoesNotMatch(): void
    {
        $_SERVER['REQUEST_URI'] = 'home/auto_item/foo.xml';

        $request = $this->createMock(Request::class);
        $request
            ->method('getBasePath')
            ->willReturn('')
        ;

        $request
            ->method('getScriptName')
            ->willReturn('index.php')
        ;

        $requestStack = new RequestStack();
        $requestStack->push($request);

        System::getContainer()->set('request_stack', $requestStack);

        $this->assertFalse(Frontend::getPageIdFromUrl());
        $this->assertEmpty($_GET);
    }

    /**
     * @expectedDeprecation Since contao/core-bundle 4.7: Using "Contao\Frontend::getPageIdFromUrl()" has been deprecated %s.
     */
    public function testReturnsFalseUponDuplicateParameters(): void
    {
        $_SERVER['REQUEST_URI'] = 'home/foo/bar1/foo/bar2.html';

        $request = $this->createMock(Request::class);
        $request
            ->method('getBasePath')
            ->willReturn('')
        ;

        $request
            ->method('getScriptName')
            ->willReturn('index.php')
        ;

        $requestStack = new RequestStack();
        $requestStack->push($request);

        System::getContainer()->set('request_stack', $requestStack);
        System::getContainer()->set('contao.framework', $this->mockFrameworkWithPageAdapter());

        $this->assertFalse(Frontend::getPageIdFromUrl());
        $this->assertSame(['foo' => 'bar1'], $_GET);
    }

    /**
     * @expectedDeprecation Since contao/core-bundle 4.7: Using "Contao\Frontend::getPageIdFromUrl()" has been deprecated %s.
     */
    public function testReturnsFalseIfTheRequestContainsAnAutoItemKeyword(): void
    {
        $_SERVER['REQUEST_URI'] = 'home/items/bar.html';

        $request = $this->createMock(Request::class);
        $request
            ->method('getBasePath')
            ->willReturn('')
        ;

        $request
            ->method('getScriptName')
            ->willReturn('index.php')
        ;

        $requestStack = new RequestStack();
        $requestStack->push($request);

        System::getContainer()->set('request_stack', $requestStack);
        System::getContainer()->set('contao.framework', $this->mockFrameworkWithPageAdapter());
        Config::set('useAutoItem', true);

        $this->assertFalse(Frontend::getPageIdFromUrl());
        $this->assertEmpty($_GET);
    }

    /**
     * @expectedDeprecation Since contao/core-bundle 4.7: Using "Contao\Frontend::getPageIdFromUrl()" has been deprecated %s.
     */
    public function testReturnsFalseIfAFragmentKeyIsEmpty(): void
    {
        $_SERVER['REQUEST_URI'] = 'home//foo.html';

        $request = $this->createMock(Request::class);
        $request
            ->method('getBasePath')
            ->willReturn('')
        ;

        $request
            ->method('getScriptName')
            ->willReturn('index.php')
        ;

        $requestStack = new RequestStack();
        $requestStack->push($request);

        System::getContainer()->set('request_stack', $requestStack);
        System::getContainer()->set('contao.framework', $this->mockFrameworkWithPageAdapter());

        $this->assertFalse(Frontend::getPageIdFromUrl());
        $this->assertEmpty($_GET);
    }

    /**
     * @expectedDeprecation Since contao/core-bundle 4.7: Using "Contao\Frontend::getPageIdFromUrl()" has been deprecated %s.
     */
    public function testDecodesTheRequestString(): void
    {
        $_SERVER['REQUEST_URI'] = 'h%C3%B6me.html';

        $request = $this->createMock(Request::class);
        $request
            ->method('getBasePath')
            ->willReturn('')
        ;

        $request
            ->method('getScriptName')
            ->willReturn('index.php')
        ;

        $requestStack = new RequestStack();
        $requestStack->push($request);

        System::getContainer()->set('request_stack', $requestStack);

        $this->assertSame('höme', Frontend::getPageIdFromUrl());
        $this->assertEmpty($_GET);
    }

    /**
     * Needs to run in a separate process because it includes the functions.php file.
     *
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     *
     * @expectedDeprecation Since contao/core-bundle 4.7: Using "Contao\Frontend::getPageIdFromUrl()" has been deprecated %s.
     */
    public function testAddsTheAutoItemFragment(): void
    {
        include_once __DIR__.'/../../src/Resources/contao/helper/functions.php';

        $_SERVER['REQUEST_URI'] = 'home/foo.html';

        $request = $this->createMock(Request::class);
        $request
            ->method('getBasePath')
            ->willReturn('')
        ;

        $request
            ->method('getScriptName')
            ->willReturn('index.php')
        ;

        $requestStack = new RequestStack();
        $requestStack->push($request);

        System::getContainer()->set('request_stack', $requestStack);
        System::getContainer()->set('contao.framework', $this->mockFrameworkWithPageAdapter());
        Config::set('useAutoItem', true);

        $this->assertSame('home', Frontend::getPageIdFromUrl());
        $this->assertSame(['auto_item' => 'foo'], $_GET);
    }

    /**
     * @expectedDeprecation Since contao/core-bundle 4.7: Using "Contao\Frontend::getPageIdFromUrl()" has been deprecated %s.
     */
    public function testReturnsNullIfOnlyTheLanguageIsGiven(): void
    {
        $_SERVER['REQUEST_URI'] = 'en/';

        $request = $this->createMock(Request::class);
        $request
            ->method('getBasePath')
            ->willReturn('')
        ;

        $request
            ->method('getScriptName')
            ->willReturn('index.php')
        ;

        $requestStack = new RequestStack();
        $requestStack->push($request);

        System::getContainer()->set('request_stack', $requestStack);
        Config::set('addLanguageToUrl', true);

        $this->assertNull(Frontend::getPageIdFromUrl());
        $this->assertSame(['language' => 'en'], $_GET);
    }

    /**
     * @expectedDeprecation Since contao/core-bundle 4.7: Using "Contao\Frontend::getPageIdFromUrl()" has been deprecated %s.
     */
    public function testReturnsFalseIfTheLanguageIsNotProvided(): void
    {
        $_SERVER['REQUEST_URI'] = 'home.html';

        $request = $this->createMock(Request::class);
        $request
            ->method('getBasePath')
            ->willReturn('')
        ;

        $request
            ->method('getScriptName')
            ->willReturn('index.php')
        ;

        $requestStack = new RequestStack();
        $requestStack->push($request);

        System::getContainer()->set('request_stack', $requestStack);
        Config::set('addLanguageToUrl', true);

        $this->assertFalse(Frontend::getPageIdFromUrl());
        $this->assertEmpty($_GET);
    }

    /**
     * @expectedDeprecation Since contao/core-bundle 4.7: Using "Contao\Frontend::getPageIdFromUrl()" has been deprecated %s.
     */
    public function testReturnsFalseIfTheAliasIsEmpty(): void
    {
        $_SERVER['REQUEST_URI'] = 'en//foo/bar.html';

        $request = $this->createMock(Request::class);
        $request
            ->method('getBasePath')
            ->willReturn('')
        ;

        $request
            ->method('getScriptName')
            ->willReturn('index.php')
        ;

        $requestStack = new RequestStack();
        $requestStack->push($request);

        System::getContainer()->set('request_stack', $requestStack);
        System::getContainer()->set('contao.framework', $this->mockFrameworkWithPageAdapter());
        Config::set('addLanguageToUrl', true);
        Config::set('useAutoItem', true);

        $this->assertFalse(Frontend::getPageIdFromUrl());
        $this->assertSame(['language' => 'en'], $_GET);
    }

    /**
     * @expectedDeprecation Since contao/core-bundle 4.7: Using "Contao\Frontend::getPageIdFromUrl()" has been deprecated %s.
     */
    public function testReturnsFalseIfThereAreNoFragments(): void
    {
        $_SERVER['REQUEST_URI'] = '/.html';

        $request = $this->createMock(Request::class);
        $request
            ->method('getBasePath')
            ->willReturn('/foo')
        ;

        $request
            ->method('getScriptName')
            ->willReturn('index.php')
        ;

        $requestStack = new RequestStack();
        $requestStack->push($request);

        $pageModel = $this->mockAdapter(['findByAliases']);
        $pageModel
            ->method('findByAliases')
            ->willReturn(null)
        ;

        System::getContainer()->set('request_stack', $requestStack);
        System::getContainer()->set('contao.framework', $this->mockFrameworkWithPageAdapter($pageModel));

        $this->assertFalse(Frontend::getPageIdFromUrl());
        $this->assertEmpty($_GET);
    }

    /**
     * @expectedDeprecation Since contao/core-bundle 4.7: Using "Contao\Frontend::getPageIdFromUrl()" has been deprecated %s.
     */
    public function testHandlesFolderUrlsWithoutLanguage(): void
    {
        $_SERVER['REQUEST_URI'] = 'foo/bar/home.html';
        $_SERVER['HTTP_HOST'] = 'domain.com';

        $request = $this->createMock(Request::class);
        $request
            ->method('getBasePath')
            ->willReturn('')
        ;

        $request
            ->method('getScriptName')
            ->willReturn('index.php')
        ;

        $requestStack = new RequestStack();
        $requestStack->push($request);

        /** @var PageModel&MockObject $page */
        $page = $this->mockClassWithProperties(PageModel::class);
        $page->domain = '';
        $page->rootLanguage = 'en';
        $page->rootIsFallback = true;
        $page->alias = 'foo/bar/home';

        $page
            ->method('loadDetails')
            ->willReturn($page)
        ;

        $pageModel = $this->mockAdapter(['findByAliases']);
        $pageModel
            ->method('findByAliases')
            ->with($this->callback(
                function (array $options) {
                    $this->assertSame(['foo/bar/home', 'foo/bar', 'foo'], $options);

                    return true;
                }
            ))
            ->willReturn(new Collection([$page], 'tl_page'))
        ;

        $framework = $this->mockContaoFramework([PageModel::class => $pageModel]);

        System::getContainer()->set('request_stack', $requestStack);
        System::getContainer()->set('contao.framework', $framework);

        $this->assertSame('foo/bar/home', Frontend::getPageIdFromUrl());
        $this->assertEmpty($_GET);
    }

    /**
     * @expectedDeprecation Since contao/core-bundle 4.7: Using "Contao\Frontend::getPageIdFromUrl()" has been deprecated %s.
     */
    public function testHandlesFolderUrlsWithLanguage(): void
    {
        $_SERVER['REQUEST_URI'] = 'en/foo/bar/home/news/test.html';
        $_SERVER['HTTP_HOST'] = 'domain.com';

        $request = $this->createMock(Request::class);
        $request
            ->method('getBasePath')
            ->willReturn('')
        ;

        $request
            ->method('getScriptName')
            ->willReturn('index.php')
        ;

        $requestStack = new RequestStack();
        $requestStack->push($request);

        /** @var PageModel&MockObject $page */
        $page = $this->mockClassWithProperties(PageModel::class);
        $page->domain = 'domain.com';
        $page->rootLanguage = 'en';
        $page->rootIsFallback = true;
        $page->alias = 'foo/bar/home';

        $page
            ->method('loadDetails')
            ->willReturn($page)
        ;

        $pageModel = $this->mockAdapter(['findByAliases']);
        $pageModel
            ->method('findByAliases')
            ->with($this->callback(
                function (array $options) {
                    $this->assertSame(
                        [
                            'foo/bar/home/news/test',
                            'foo/bar/home/news',
                            'foo/bar/home',
                            'foo/bar',
                            'foo',
                        ],
                        $options
                    );

                    return true;
                }
            ))
            ->willReturn(new Collection([$page], 'tl_page'))
        ;

        $framework = $this->mockContaoFramework([PageModel::class => $pageModel]);

        System::getContainer()->set('request_stack', $requestStack);
        System::getContainer()->set('contao.framework', $framework);
        Config::set('addLanguageToUrl', true);

        $this->assertSame('foo/bar/home', Frontend::getPageIdFromUrl());
        $this->assertSame(['language' => 'en', 'news' => 'test'], $_GET);
    }

    /**
     * @expectedDeprecation Since contao/core-bundle 4.7: Using "Contao\Frontend::getPageIdFromUrl()" has been deprecated %s.
     */
    public function testReturnsFalseIfThereAreNoAliases(): void
    {
        $_SERVER['REQUEST_URI'] = 'foo/bar/home.html';
        $_SERVER['HTTP_HOST'] = 'domain.com';

        $request = $this->createMock(Request::class);
        $request
            ->method('getBasePath')
            ->willReturn('')
        ;

        $request
            ->method('getScriptName')
            ->willReturn('index.php')
        ;

        $requestStack = new RequestStack();
        $requestStack->push($request);

        /** @var PageModel&MockObject $page */
        $page = $this->mockClassWithProperties(PageModel::class);
        $page->domain = 'domain.de';
        $page->rootLanguage = 'de';
        $page->rootIsFallback = false;
        $page->alias = 'startseite';

        $page
            ->method('loadDetails')
            ->willReturn($page)
        ;

        $pageModel = $this->mockAdapter(['findByAliases']);
        $pageModel
            ->method('findByAliases')
            ->with($this->callback(
                function (array $options) {
                    $this->assertSame(['foo/bar/home', 'foo/bar', 'foo'], $options);

                    return true;
                }
            ))
            ->willReturn(new Collection([$page], 'tl_page'))
        ;

        $framework = $this->mockContaoFramework([PageModel::class => $pageModel]);

        System::getContainer()->set('request_stack', $requestStack);
        System::getContainer()->set('contao.framework', $framework);

        $this->assertFalse(Frontend::getPageIdFromUrl());
        $this->assertEmpty($_GET);
    }

    /**
     * @return ContaoFramework&MockObject
     */
    private function mockFrameworkWithPageAdapter(Adapter $pageAdapter = null): ContaoFramework
    {
        if (null === $pageAdapter) {
            $pageAdapter = $this->mockAdapter(['findByAliases']);
            $pageAdapter
                ->expects($this->once())
                ->method('findByAliases')
                ->willReturn(null)
            ;
        }

        return $this->mockContaoFramework([PageModel::class => $pageAdapter]);
    }
}

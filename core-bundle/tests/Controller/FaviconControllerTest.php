<?php

declare(strict_types=1);

/*
 * This file is part of Contao.
 *
 * (c) Leo Feyer
 *
 * @license LGPL-3.0-or-later
 */

namespace Contao\CoreBundle\Tests\Controller;

use Contao\CoreBundle\Controller\FaviconController;
use Contao\CoreBundle\Tests\TestCase;
use Contao\FilesModel;
use Contao\PageModel;
use FOS\HttpCache\ResponseTagger;
use PHPUnit\Framework\MockObject\MockObject;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class FaviconControllerTest extends TestCase
{
    public function testNotFoundIfNoFaviconProvided(): void
    {
        $pageModelAdapter = $this->mockAdapter(['findPublishedFallbackByHostname']);
        $pageModelAdapter
            ->expects($this->once())
            ->method('findPublishedFallbackByHostname')
            ->willReturn(null)
        ;

        $framework = $this->mockContaoFramework([PageModel::class => $pageModelAdapter]);
        $framework
            ->expects($this->once())
            ->method('initialize')
        ;

        $request = Request::create('/robots.txt');
        $controller = new FaviconController($framework, $this->createMock(ResponseTagger::class));
        $response = $controller($request);

        $this->assertSame(Response::HTTP_NOT_FOUND, $response->getStatusCode());
    }

    public function testRegularFavicon(): void
    {
        $controller = $this->getController(__DIR__.'/../Fixtures/images/favicon.ico');

        $request = Request::create('/favicon.ico');
        $response = $controller($request);

        $this->assertSame(Response::HTTP_OK, $response->getStatusCode());
        $this->assertSame('image/x-icon', $response->headers->get('Content-Type'));
    }

    public function testSvgFavicon(): void
    {
        $controller = $this->getController(__DIR__.'/../Fixtures/images/favicon.svg');

        $request = Request::create('/favicon.ico');
        $response = $controller($request);

        $this->assertSame(Response::HTTP_OK, $response->getStatusCode());
        $this->assertSame('image/svg+xml', $response->headers->get('Content-Type'));
    }

    private function getController(string $iconPath): FaviconController
    {
        /** @var PageModel&MockObject $pageModel */
        $pageModel = $this->mockClassWithProperties(PageModel::class);
        $pageModel->id = 42;
        $pageModel->favicon = 'favicon-uuid';

        /** @var FilesModel&MockObject $faviconModel */
        $faviconModel = $this->mockClassWithProperties(FilesModel::class);
        $faviconModel->path = $iconPath;
        $faviconModel->extension = substr($iconPath, -3);

        $pageModelAdapter = $this->mockAdapter(['findPublishedFallbackByHostname']);
        $pageModelAdapter
            ->expects($this->once())
            ->method('findPublishedFallbackByHostname')
            ->willReturn($pageModel)
        ;

        $filesModelAdapter = $this->mockAdapter(['findByUuid']);
        $filesModelAdapter
            ->expects($this->once())
            ->method('findByUuid')
            ->with('favicon-uuid')
            ->willReturn($faviconModel)
        ;

        $framework = $this->mockContaoFramework([
            PageModel::class => $pageModelAdapter,
            FilesModel::class => $filesModelAdapter,
        ]);

        $framework
            ->expects($this->once())
            ->method('initialize')
        ;

        $responseTagger = $this->createMock(ResponseTagger::class);
        $responseTagger
            ->expects($this->once())
            ->method('addTags')
            ->with(['contao.db.tl_page.42'])
        ;

        return new FaviconController($framework, $responseTagger);
    }
}

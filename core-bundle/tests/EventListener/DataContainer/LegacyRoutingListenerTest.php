<?php

declare(strict_types=1);

/*
 * This file is part of Contao.
 *
 * (c) Leo Feyer
 *
 * @license LGPL-3.0-or-later
 */

namespace Contao\CoreBundle\Tests\EventListener\DataContainer;

use Contao\CoreBundle\EventListener\DataContainer\LegacyRoutingListener;
use Contao\CoreBundle\Tests\TestCase;
use Contao\DataContainer;
use Contao\Image;
use PHPUnit\Framework\MockObject\MockObject;
use Symfony\Contracts\Translation\TranslatorInterface;

class LegacyRoutingListenerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $GLOBALS['TL_HOOKS'] = [];
        $GLOBALS['TL_DCA'] = [];
    }

    public function testDisablesTheRoutingFields(): void
    {
        $GLOBALS['TL_DCA']['tl_page']['fields']['urlPrefix']['eval'] = [];
        $GLOBALS['TL_DCA']['tl_page']['fields']['urlSuffix']['eval'] = [];

        $adapter = $this->mockAdapter(['getHtml']);
        $framework = $this->mockContaoFramework([Image::class => $adapter]);

        $listener = new LegacyRoutingListener(
            $framework,
            $this->createMock(TranslatorInterface::class)
        );

        $listener->disableRoutingFields();

        $expect = [
            'disabled' => true,
            'helpwizard' => false,
        ];

        $this->assertSame($expect, $GLOBALS['TL_DCA']['tl_page']['fields']['urlPrefix']['eval']);
        $this->assertSame($expect, $GLOBALS['TL_DCA']['tl_page']['fields']['urlSuffix']['eval']);
    }

    public function testAddsTheRoutingWarning(): void
    {
        $GLOBALS['TL_DCA']['tl_page']['fields']['urlPrefix']['eval'] = [];
        $GLOBALS['TL_DCA']['tl_page']['fields']['urlSuffix']['eval'] = [];

        $adapter = $this->mockAdapter(['getHtml']);
        $adapter
            ->expects($this->exactly(2))
            ->method('getHtml')
            ->with('show.svg', '', 'title="disabled"')
            ->willReturn('<img src="show.svg" alt="" title="disabled">')
        ;

        $framework = $this->mockContaoFramework([Image::class => $adapter]);

        $translator = $this->createMock(TranslatorInterface::class);
        $translator
            ->expects($this->exactly(2))
            ->method('trans')
            ->with('tl_page.legacyRouting', [], 'contao_tl_page')
            ->willReturn('disabled')
        ;

        $listener = new LegacyRoutingListener($framework, $translator);
        $listener->disableRoutingFields();

        $this->assertInstanceOf(\Closure::class, $GLOBALS['TL_DCA']['tl_page']['fields']['urlPrefix']['xlabel'][0]);
        $this->assertInstanceOf(\Closure::class, $GLOBALS['TL_DCA']['tl_page']['fields']['urlSuffix']['xlabel'][0]);

        $this->assertSame('<img src="show.svg" alt="" title="disabled">', $GLOBALS['TL_DCA']['tl_page']['fields']['urlPrefix']['xlabel'][0]());
        $this->assertSame('<img src="show.svg" alt="" title="disabled">', $GLOBALS['TL_DCA']['tl_page']['fields']['urlSuffix']['xlabel'][0]());
    }

    public function testOverridesTheUrlPrefixWithPrependLocale(): void
    {
        $listener = new LegacyRoutingListener(
            $this->mockContaoFramework(),
            $this->createMock(TranslatorInterface::class),
            true
        );

        /** @var DataContainer&MockObject $dc */
        $dc = $this->mockClassWithProperties(
            DataContainer::class,
            ['activeRecord' => (object) ['language' => 'en-US']]
        );

        $this->assertSame('en-US', $listener->overrideUrlPrefix('foo', $dc));
    }

    public function testOverridesTheUrlPrefixWithoutPrependLocale(): void
    {
        $listener = new LegacyRoutingListener(
            $this->mockContaoFramework(),
            $this->createMock(TranslatorInterface::class),
            false
        );

        /** @var DataContainer&MockObject $dc */
        $dc = $this->mockClassWithProperties(
            DataContainer::class,
            ['activeRecord' => (object) ['language' => 'en-US']]
        );

        $this->assertSame('', $listener->overrideUrlPrefix('foo', $dc));
    }

    public function testOverridesTheUrlSuffix(): void
    {
        $listener = new LegacyRoutingListener(
            $this->mockContaoFramework(),
            $this->createMock(TranslatorInterface::class),
            false,
            '.bar'
        );

        $this->assertSame('.bar', $listener->overrideUrlSuffix());
    }
}

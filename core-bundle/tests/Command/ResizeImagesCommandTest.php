<?php

declare(strict_types=1);

/*
 * This file is part of Contao.
 *
 * (c) Leo Feyer
 *
 * @license LGPL-3.0-or-later
 */

namespace Contao\CoreBundle\Tests\Command;

use Contao\CoreBundle\Command\ResizeImagesCommand;
use Contao\CoreBundle\Image\ImageFactoryInterface;
use Contao\CoreBundle\Tests\TestCase;
use Contao\Image\DeferredImageInterface;
use Contao\Image\DeferredImageStorageInterface;
use Contao\Image\DeferredResizerInterface;
use Contao\Image\ImageInterface;
use Symfony\Bridge\PhpUnit\ClockMock;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Filesystem\Filesystem;

class ResizeImagesCommandTest extends TestCase
{
    protected function tearDown(): void
    {
        parent::tearDown();

        $fs = new Filesystem();
        $fs->remove($this->getFixturesDir().'/assets/images');
    }

    public function testExecutesWithoutPendingImages(): void
    {
        $fs = new Filesystem();
        $fs->mkdir($this->getFixturesDir().'/assets/images');

        $storage = $this->createMock(DeferredImageStorageInterface::class);
        $storage
            ->method('listPaths')
            ->willReturn([])
        ;

        $command = $this->getCommand(null, null, $storage);
        $tester = new CommandTester($command);
        $code = $tester->execute([], ['capture_stderr_separately' => true]);
        $display = $tester->getDisplay();

        $this->assertSame(0, $code);
        $this->assertRegExp('/Resized 0 images/', $display);
    }

    public function testResizesImages(): void
    {
        $fs = new Filesystem();
        $fs->mkdir($this->getFixturesDir().'/assets/images');

        $factory = $this->createMock(ImageFactoryInterface::class);
        $factory
            ->method('create')
            ->willReturn($this->createMock(DeferredImageInterface::class))
        ;

        $resizer = $this->createMock(DeferredResizerInterface::class);
        $resizer
            ->method('resizeDeferredImage')
            ->willReturn($this->createMock(ImageInterface::class))
        ;

        $storage = $this->createMock(DeferredImageStorageInterface::class);
        $storage
            ->method('listPaths')
            ->willReturn(['image1.jpg', 'image2.jpg'])
        ;

        $command = $this->getCommand($factory, $resizer, $storage);
        $tester = new CommandTester($command);
        $code = $tester->execute(['--no-sub-process' => true], ['capture_stderr_separately' => true]);
        $display = $tester->getDisplay();

        $this->assertSame(0, $code);
        $this->assertRegExp('/image1.jpg/', $display);
        $this->assertRegExp('/image2.jpg/', $display);
        $this->assertRegExp('/Resized 2 images/', $display);
    }

    public function testTimeLimit(): void
    {
        $fs = new Filesystem();
        $fs->mkdir($this->getFixturesDir().'/assets/images');

        $factory = $this->createMock(ImageFactoryInterface::class);
        $factory
            ->method('create')
            ->willReturn($this->createMock(DeferredImageInterface::class))
        ;

        $resizer = $this->createMock(DeferredResizerInterface::class);
        $resizer
            ->method('resizeDeferredImage')
            ->willReturnCallback(
                function () {
                    sleep(1);

                    return $this->createMock(ImageInterface::class);
                }
            )
        ;

        $storage = $this->createMock(DeferredImageStorageInterface::class);
        $storage
            ->method('listPaths')
            ->willReturn(['image1.jpg', 'image2.jpg'])
        ;

        ClockMock::withClockMock(1142164800);

        $command = $this->getCommand($factory, $resizer, $storage);
        $tester = new CommandTester($command);
        $code = $tester->execute(['--no-sub-process' => true, '--time-limit' => 0.5], ['capture_stderr_separately' => true]);
        $display = $tester->getDisplay();

        ClockMock::withClockMock(false);

        $this->assertSame(0, $code);
        $this->assertRegExp('/image1.jpg/', $display);
        $this->assertRegExp('/Time limit of 0.5 seconds reached/', $display);
        $this->assertRegExp('/Resized 1 images/', $display);
        $this->assertNotRegExp('/image2.jpg/', $display);
    }

    private function getCommand(ImageFactoryInterface $factory = null, DeferredResizerInterface $resizer = null, DeferredImageStorageInterface $storage = null): ResizeImagesCommand
    {
        return new ResizeImagesCommand(
            $factory ?? $this->createMock(ImageFactoryInterface::class),
            $resizer ?? $this->createMock(DeferredResizerInterface::class),
            $this->getFixturesDir().'/assets/images',
            $storage ?? $this->createMock(DeferredImageStorageInterface::class)
        );
    }
}

<?php

declare(strict_types=1);

/*
 * This file is part of Contao.
 *
 * (c) Leo Feyer
 *
 * @license LGPL-3.0-or-later
 */

namespace Contao\CoreBundle\Tests\File;

use Contao\ContentModel;
use Contao\CoreBundle\File\Metadata;
use Contao\CoreBundle\Tests\TestCase;
use Contao\FilesModel;
use Contao\System;

class MetadataTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        System::setContainer($this->getContainerWithContaoConfiguration());

        $GLOBALS['TL_DCA']['tl_files']['fields']['meta']['eval']['metaFields'] = [
            'title' => '', 'alt' => '', 'link' => '', 'caption' => '',
        ];
    }

    public function testCreateAndAccessMetadataContainer(): void
    {
        $metadata = new Metadata([
            Metadata::VALUE_ALT => 'alt',
            Metadata::VALUE_CAPTION => 'caption',
            Metadata::VALUE_TITLE => 'title',
            Metadata::VALUE_URL => 'url',
            'foo' => 'bar',
        ]);

        $this->assertFalse($metadata->empty());

        $this->assertSame('alt', $metadata->getAlt());
        $this->assertSame('caption', $metadata->getCaption());
        $this->assertSame('title', $metadata->getTitle());
        $this->assertSame('url', $metadata->getUrl());
        $this->assertSame('bar', $metadata->get('foo'));

        $this->assertSame(
            [
                Metadata::VALUE_ALT => 'alt',
                Metadata::VALUE_CAPTION => 'caption',
                Metadata::VALUE_TITLE => 'title',
                Metadata::VALUE_URL => 'url',
                'foo' => 'bar',
            ],
            $metadata->all()
        );
    }

    public function testGetEmpty(): void
    {
        $metadata = new Metadata([]);

        $this->assertSame('', $metadata->getAlt());
        $this->assertSame('', $metadata->getCaption());
        $this->assertSame('', $metadata->getTitle());
        $this->assertSame('', $metadata->getUrl());

        $this->assertNull($metadata->get('foo'));
    }

    public function testEmpty(): void
    {
        $metadata = new Metadata([]);

        $this->assertTrue($metadata->empty());
    }

    public function testHas(): void
    {
        $metadata = new Metadata([
            Metadata::VALUE_ALT => '',
            'foo' => 'bar',
        ]);

        $this->assertTrue($metadata->has(Metadata::VALUE_ALT));
        $this->assertTrue($metadata->has('foo'));
        $this->assertFalse($metadata->has('bar'));
    }

    public function testCreatesMetadataContainerFromContentModel(): void
    {
        /** @var ContentModel $model */
        $model = (new \ReflectionClass(ContentModel::class))->newInstanceWithoutConstructor();

        $model->setRow([
            'id' => 100,
            'headline' => 'foobar',
            'overwriteMeta' => '1',
            'alt' => 'foo alt',
            'imageTitle' => 'foo title',
            'imageUrl' => 'foo://bar',
            'caption' => 'foo caption',
        ]);

        $this->assertSame(
            [
                Metadata::VALUE_ALT => 'foo alt',
                Metadata::VALUE_CAPTION => 'foo caption',
                Metadata::VALUE_TITLE => 'foo title',
                Metadata::VALUE_URL => 'foo://bar',
            ],
            $model->getOverwriteMetadata()->all()
        );
    }

    public function testDoesNotCreateMetadataContainerFromContentModelIfOverwriteIsDisabled(): void
    {
        /** @var ContentModel $model */
        $model = (new \ReflectionClass(ContentModel::class))->newInstanceWithoutConstructor();

        $model->setRow([
            'id' => 100,
            'headline' => 'foobar',
            'overwriteMeta' => '',
            'alt' => 'foo alt',
        ]);

        $this->assertNull($model->getOverwriteMetadata());
    }

    public function testCreatesMetadataContainerFromFilesModel(): void
    {
        /** @var FilesModel $model */
        $model = (new \ReflectionClass(FilesModel::class))->newInstanceWithoutConstructor();

        $model->setRow([
            'id' => 100,
            'name' => 'test',
            'meta' => serialize([
                'de' => [
                    'title' => 'foo title',
                    'alt' => 'foo alt',
                    'caption' => 'foo caption',
                ],
                'en' => [
                    'title' => 'bar title',
                    'alt' => 'bar alt',
                    'link' => 'foo://bar',
                    'caption' => 'bar caption',
                    'custom' => 'foobar',
                ],
            ]),
        ]);

        $this->assertSame(
            [
                Metadata::VALUE_TITLE => 'bar title',
                Metadata::VALUE_ALT => 'bar alt',
                Metadata::VALUE_URL => 'foo://bar',
                Metadata::VALUE_CAPTION => 'bar caption',
                'custom' => 'foobar',
            ],
            $model->getMetadata('en')->all(),
            'get all meta from single locale'
        );

        $this->assertSame(
            [
                Metadata::VALUE_TITLE => 'foo title',
                Metadata::VALUE_ALT => 'foo alt',
                Metadata::VALUE_URL => '',
                Metadata::VALUE_CAPTION => 'foo caption',
            ],
            $model->getMetadata('es', 'de', 'en')->all(),
            'get all metadata of first matching locale'
        );

        $this->assertNull(
            $model->getMetadata('es'),
            'return null if no metadata is available for a locale'
        );
    }
}

<?php

declare(strict_types=1);

/*
 * This file is part of Contao.
 *
 * (c) Leo Feyer
 *
 * @license LGPL-3.0-or-later
 */

namespace Contao\CoreBundle\Tests\DependencyInjection;

use Contao\CoreBundle\DependencyInjection\Configuration;
use Contao\CoreBundle\Tests\TestCase;
use Contao\Image\ResizeConfiguration;
use Symfony\Component\Config\Definition\ArrayNode;
use Symfony\Component\Config\Definition\BaseNode;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\Config\Definition\Processor;
use Symfony\Component\Config\Definition\PrototypedArrayNode;

class ConfigurationTest extends TestCase
{
    /**
     * @var Configuration
     */
    private $configuration;

    protected function setUp(): void
    {
        parent::setUp();

        $this->configuration = new Configuration($this->getTempDir(), 'en');
    }

    public function testAddsTheImagineService(): void
    {
        $params = [];
        $configuration = (new Processor())->processConfiguration($this->configuration, $params);

        $this->assertNull($configuration['image']['imagine_service']);

        $params = [
            'contao' => [
                'image' => [
                    'imagine_service' => 'my_super_service',
                ],
            ],
        ];

        $configuration = (new Processor())->processConfiguration($this->configuration, $params);

        $this->assertSame('my_super_service', $configuration['image']['imagine_service']);
    }

    /**
     * @dataProvider getPaths
     */
    public function testResolvesThePaths(string $unix, string $windows): void
    {
        $params = [
            'contao' => [
                'web_dir' => $unix,
                'image' => [
                    'target_dir' => $windows,
                ],
            ],
        ];

        $configuration = (new Processor())->processConfiguration($this->configuration, $params);

        $this->assertSame('/tmp/contao', $configuration['web_dir']);
        $this->assertSame('C:/Temp/contao', $configuration['image']['target_dir']);
    }

    public function getPaths(): \Generator
    {
        yield ['/tmp/contao', 'C:\Temp\contao'];
        yield ['/tmp/foo/../contao', 'C:\Temp\foo\..\contao'];
        yield ['/tmp/foo/bar/../../contao', 'C:\Temp\foo\bar\..\..\contao'];
        yield ['/tmp/./contao', 'C:\Temp\.\contao'];
        yield ['/tmp//contao', 'C:\Temp\\\\contao'];
        yield ['/tmp/contao/', 'C:\Temp\contao\\'];
        yield ['/tmp/contao/.', 'C:\Temp\contao\.'];
        yield ['/tmp/contao/foo/..', 'C:\Temp\contao\foo\..'];
    }

    /**
     * @dataProvider getInvalidUploadPaths
     */
    public function testFailsIfTheUploadPathIsInvalid(string $uploadPath): void
    {
        $params = [
            'contao' => [
                'encryption_key' => 's3cr3t',
                'upload_path' => $uploadPath,
            ],
        ];

        $this->expectException(InvalidConfigurationException::class);

        (new Processor())->processConfiguration($this->configuration, $params);
    }

    public function getInvalidUploadPaths(): \Generator
    {
        yield [''];
        yield ['app'];
        yield ['assets'];
        yield ['bin'];
        yield ['config'];
        yield ['contao'];
        yield ['plugins'];
        yield ['share'];
        yield ['system'];
        yield ['templates'];
        yield ['var'];
        yield ['vendor'];
        yield ['web'];
    }

    public function testFailsIfAPredefinedImageSizeNameContainsOnlyDigits(): void
    {
        $params = [
            'contao' => [
                'image' => [
                    'sizes' => [
                        '123' => ['width' => 100, 'height' => 200],
                    ],
                ],
            ],
        ];

        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessageMatches('/The image size name "123" cannot contain only digits/');

        (new Processor())->processConfiguration($this->configuration, $params);
    }

    /**
     * @dataProvider getReservedImageSizeNames
     */
    public function testFailsIfAPredefinedImageSizeNameIsReserved(string $name): void
    {
        $params = [
            'contao' => [
                'image' => [
                    'sizes' => [
                        $name => ['width' => 100, 'height' => 200],
                    ],
                ],
            ],
        ];

        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessageMatches('/"'.$name.'" is a reserved image size name/');

        (new Processor())->processConfiguration($this->configuration, $params);
    }

    public function getReservedImageSizeNames(): \Generator
    {
        yield [ResizeConfiguration::MODE_BOX];
        yield [ResizeConfiguration::MODE_PROPORTIONAL];
        yield [ResizeConfiguration::MODE_CROP];
        yield ['left_top'];
        yield ['center_top'];
        yield ['right_top'];
        yield ['left_center'];
        yield ['center_center'];
        yield ['right_center'];
        yield ['left_bottom'];
        yield ['center_bottom'];
        yield ['right_bottom'];
    }

    public function testDeniesInvalidCrawlUris(): void
    {
        $params = [
            'contao' => [
                'crawl' => [
                    'additional_uris' => ['invalid.com'],
                ],
            ],
        ];

        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('Invalid configuration for path "contao.crawl.additional_uris": All provided additional URIs must start with either http:// or https://.');

        (new Processor())->processConfiguration($this->configuration, $params);
    }

    public function testAllowsOnlySnakeCaseKeys(): void
    {
        /** @var ArrayNode $tree */
        $tree = $this->configuration->getConfigTreeBuilder()->buildTree();

        $this->assertInstanceOf(ArrayNode::class, $tree);

        $this->checkKeys($tree->getChildren());
    }

    /**
     * Ensure that all non-deprecated configuration keys are in lower case and
     * separated by underscores (aka snake_case).
     */
    private function checkKeys(array $configuration): void
    {
        /** @var BaseNode $value */
        foreach ($configuration as $key => $value) {
            if ($value instanceof ArrayNode) {
                $this->checkKeys($value->getChildren());
            }

            /** @var ArrayNode $prototype */
            if ($value instanceof PrototypedArrayNode && ($prototype = $value->getPrototype()) instanceof ArrayNode) {
                $this->checkKeys($prototype->getChildren());
            }

            if (\is_string($key) && !$value->isDeprecated()) {
                $this->assertRegExp('/^[a-z][a-z_]+[a-z]$/', $key);
            }
        }
    }
}

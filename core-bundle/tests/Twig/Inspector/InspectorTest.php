<?php

declare(strict_types=1);

/*
 * This file is part of Contao.
 *
 * (c) Leo Feyer
 *
 * @license LGPL-3.0-or-later
 */

namespace Contao\CoreBundle\Tests\Twig\Inspector;

use Contao\CoreBundle\Csrf\ContaoCsrfTokenManager;
use Contao\CoreBundle\Tests\TestCase;
use Contao\CoreBundle\Twig\Extension\ContaoExtension;
use Contao\CoreBundle\Twig\Global\ContaoVariable;
use Contao\CoreBundle\Twig\Inspector\BlockType;
use Contao\CoreBundle\Twig\Inspector\InspectionException;
use Contao\CoreBundle\Twig\Inspector\Inspector;
use Contao\CoreBundle\Twig\Inspector\InspectorNodeVisitor;
use Contao\CoreBundle\Twig\Loader\ContaoFilesystemLoader;
use Symfony\Component\Cache\Adapter\AdapterInterface;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Cache\Adapter\NullAdapter;
use Twig\Environment;
use Twig\Error\LoaderError;
use Twig\Loader\ArrayLoader;
use Twig\Loader\ChainLoader;
use Twig\Source;

class InspectorTest extends TestCase
{
    public function testAnalyzesBlocks(): void
    {
        $templates = [
            'foo.html.twig' => '{% block foo %}{% block bar %}[…]{% endblock %}{% endblock %}',
            'bar.html.twig' => '',
        ];

        $information = $this->getInspector($templates)->inspectTemplate('foo.html.twig');

        $this->assertSame('foo.html.twig', $information->getName());
        $this->assertSame(['bar', 'foo'], $information->getBlockNames());
        $this->assertSame('{% block foo %}{% block bar %}[…]{% endblock %}{% endblock %}', $information->getCode());
    }

    public function testAnalyzesSlots(): void
    {
        $inspector = $this->getInspector([
            'template.twig' => '{% slot B %}{% endslot %}{% block foo %}{% slot A %}body{% endslot %}{% slot A %}{% endslot %}{% endblock %}',
        ]);

        $information = $inspector->inspectTemplate('template.twig');
        $this->assertSame(['A', 'B'], $information->getSlots());
    }

    public function testAnalyzesUses(): void
    {
        $inspector = $this->getInspector([
            'parent.twig' => '',
            'component1.twig' => '{% block original_a %}{% endblock %}{% block original_b %}{% endblock %}',
            'component2.twig' => '',
            'template.twig' => <<<'SOURCE'
                {% extends "parent.twig" %}
                {% use "component1.twig" with original_a as modified_a, original_b as modified_b %}
                {% use "component2.twig" %}

                SOURCE,
        ]);

        $information = $inspector->inspectTemplate('template.twig');

        $this->assertSame(
            [
                ['component1.twig', ['original_a' => 'modified_a', 'original_b' => 'modified_b']],
                ['component2.twig', []],
            ],
            $information->getUses(),
        );
    }

    public function testAnalyzeBlockHierarchy(): void
    {
        $templates = [
            'leaf.twig' => <<<'SOURCE'
                {% extends "branch.twig" %}
                {% use "component.twig" with foo as other %}

                {# Overwriting implicit parent block: #}
                {% block baz %}
                  +
                {% endblock %}

                SOURCE,

            'branch.twig' => <<<'SOURCE'
                {% extends "root.twig" %}

                {# Enhancing parent block: #}
                {% block foo %}
                  {{ parent() }}
                  +
                {% endblock %}

                {# Overwriting parent block: #}
                {% block bar %}
                  +
                {% endblock %}
                SOURCE,

            'root.twig' => <<<'SOURCE'
                Prototype block:
                {% block foo %}{% endblock %}

                Regular blocks:
                {% block bar %}bar{% endblock %}
                {% block baz %}baz{% endblock %}
                SOURCE,

            'component.twig' => <<<'SOURCE'
                {% block component %}
                    {% block foo %}{% endblock %}
                    {% block boo %}{% endblock %}
                {% endblock %}
                SOURCE,
        ];

        $inspector = $this->getInspector($templates);

        // Test getting hierarchy of block "foo"
        $fooHierarchy = $inspector->getBlockHierarchy('leaf.twig', 'foo');
        $this->assertCount(3, $fooHierarchy);

        $this->assertSame('leaf.twig', $fooHierarchy[0]->getTemplateName());
        $this->assertSame(BlockType::transparent, $fooHierarchy[0]->getType());
        $this->assertSame('foo', $fooHierarchy[0]->getBlockName());
        $this->assertFalse($fooHierarchy[0]->isPrototype());

        $this->assertSame('branch.twig', $fooHierarchy[1]->getTemplateName());
        $this->assertSame(BlockType::enhance, $fooHierarchy[1]->getType());
        $this->assertSame('foo', $fooHierarchy[1]->getBlockName());
        $this->assertFalse($fooHierarchy[1]->isPrototype());

        $this->assertSame('root.twig', $fooHierarchy[2]->getTemplateName());
        $this->assertSame(BlockType::origin, $fooHierarchy[2]->getType());
        $this->assertSame('foo', $fooHierarchy[2]->getBlockName());
        $this->assertTrue($fooHierarchy[2]->isPrototype());

        // Test getting hierarchy of block "bar"
        $barHierarchy = $inspector->getBlockHierarchy('leaf.twig', 'bar');
        $this->assertCount(3, $barHierarchy);

        $this->assertSame('leaf.twig', $barHierarchy[0]->getTemplateName());
        $this->assertSame(BlockType::transparent, $barHierarchy[0]->getType());
        $this->assertSame('bar', $barHierarchy[0]->getBlockName());
        $this->assertFalse($barHierarchy[0]->isPrototype());

        $this->assertSame('branch.twig', $barHierarchy[1]->getTemplateName());
        $this->assertSame(BlockType::overwrite, $barHierarchy[1]->getType());
        $this->assertSame('bar', $barHierarchy[1]->getBlockName());
        $this->assertFalse($barHierarchy[1]->isPrototype());

        $this->assertSame('root.twig', $barHierarchy[2]->getTemplateName());
        $this->assertSame(BlockType::origin, $barHierarchy[2]->getType());
        $this->assertSame('bar', $barHierarchy[2]->getBlockName());
        $this->assertFalse($barHierarchy[2]->isPrototype());

        // Test getting hierarchy of block "baz"
        $bazHierarchy = $inspector->getBlockHierarchy('leaf.twig', 'baz');
        $this->assertCount(3, $bazHierarchy);

        $this->assertSame('leaf.twig', $bazHierarchy[0]->getTemplateName());
        $this->assertSame(BlockType::overwrite, $bazHierarchy[0]->getType());
        $this->assertSame('baz', $bazHierarchy[0]->getBlockName());
        $this->assertFalse($bazHierarchy[0]->isPrototype());

        $this->assertSame('branch.twig', $bazHierarchy[1]->getTemplateName());
        $this->assertSame(BlockType::transparent, $bazHierarchy[1]->getType());
        $this->assertSame('baz', $bazHierarchy[1]->getBlockName());
        $this->assertFalse($bazHierarchy[1]->isPrototype());

        $this->assertSame('root.twig', $bazHierarchy[2]->getTemplateName());
        $this->assertSame(BlockType::origin, $bazHierarchy[2]->getType());
        $this->assertSame('baz', $bazHierarchy[2]->getBlockName());
        $this->assertFalse($bazHierarchy[2]->isPrototype());

        // Test getting hierarchy of block "foo" imported as "other"
        $otherHierarchy = $inspector->getBlockHierarchy('leaf.twig', 'other');
        $this->assertCount(2, $otherHierarchy);

        $this->assertSame('leaf.twig', $otherHierarchy[0]->getTemplateName());
        $this->assertSame(BlockType::transparent, $otherHierarchy[0]->getType());
        $this->assertSame('other', $otherHierarchy[0]->getBlockName());
        $this->assertFalse($otherHierarchy[0]->isPrototype());

        $this->assertSame('component.twig', $otherHierarchy[1]->getTemplateName());
        $this->assertSame(BlockType::origin, $otherHierarchy[1]->getType());
        $this->assertSame('foo', $otherHierarchy[1]->getBlockName());
        $this->assertTrue($otherHierarchy[1]->isPrototype());
    }

    public function testHandlesNonContaoParentTemplates(): void
    {
        $contaoTemplates = [
            '@Contao_A/foo.twig' => <<<'SOURCE'
                {% extends "@Contao_B/foo.twig" %}
                {% use "@Contao_A/component.twig" %}
                {% use "@SymfonyBundle/component.twig" %}
                {% block bar %}{% endblock %}

                SOURCE,

            '@Contao_B/foo.twig' => <<<'SOURCE'
                {% extends "@SymfonyBundle/foo.twig" %}

                SOURCE,

            '@Contao_A/component.twig' => <<<'SOURCE'
                {% block contao_component %}{% endblock %}
                SOURCE,
        ];

        $symfonyTemplates = [
            '@SymfonyBundle/foo.twig' => <<<'SOURCE'
                {% use "@SymfonyBundle/not_inspected.twig" %}
                {% block bar %}{% endblock %}
                {% block baz %}{% endblock %}

                SOURCE,

            '@SymfonyBundle/component.twig' => <<<'SOURCE'
                {% use "@SymfonyBundle/not_inspected.twig" %}
                {% block symfony_component %}{% endblock %}

                SOURCE,

            '@SymfonyBundle/not_inspected.twig' => <<<'SOURCE'
                - ignored -

                SOURCE,
        ];

        $environment = new Environment(new ChainLoader([
            $contaoFilesystemLoader = $this->getContaoFilesystemLoader($contaoTemplates),
            new ArrayLoader($symfonyTemplates),
        ]));

        $cacheAdapter = new ArrayAdapter();

        $environment->addExtension(
            new ContaoExtension(
                $environment,
                $contaoFilesystemLoader,
                $this->createMock(ContaoCsrfTokenManager::class),
                $this->createMock(ContaoVariable::class),
                new InspectorNodeVisitor($cacheAdapter, $environment),
            ),
        );

        $inspector = new Inspector($environment, $cacheAdapter, $contaoFilesystemLoader);

        $templateAInformation = $inspector->inspectTemplate('@Contao_A/foo.twig');

        $this->assertSame(
            ['bar', 'baz', 'contao_component', 'symfony_component'],
            $templateAInformation->getBlockNames(),
        );

        $this->assertSame(
            [['@Contao_A/component.twig', []], ['@SymfonyBundle/component.twig', []]],
            $templateAInformation->getUses(),
        );

        $this->assertSame('@Contao_B/foo.twig', $templateAInformation->getExtends());

        $templateBInformation = $inspector->inspectTemplate('@Contao_B/foo.twig');

        $this->assertSame('@SymfonyBundle/foo.twig', $templateBInformation->getExtends());
    }

    public function testCapturesErrorsWhenFailingToInspect(): void
    {
        $inspector = $this->getInspector();

        $this->expectException(InspectionException::class);
        $this->expectExceptionMessage('Could not inspect template "foo.html.twig". The template does not exist.');

        $inspector->inspectTemplate('foo.html.twig');
    }

    public function testThrowsErrorIfCacheWasNotBuilt(): void
    {
        $inspector = $this->getInspector(['foo.html.twig' => '…'], new NullAdapter());

        $this->expectException(InspectionException::class);
        $this->expectExceptionMessage('Could not inspect template "foo.html.twig". No recorded information was found. Please clear the Twig template cache to make sure templates are recompiled.');

        $inspector->inspectTemplate('foo.html.twig');
    }

    public function testResolvesManagedNamespace(): void
    {
        $information = $this->getInspector(['@Contao_specific/foo.html.twig' => '…'])->inspectTemplate('@Contao/foo.html.twig');

        $this->assertSame('@Contao_specific/foo.html.twig', $information->getName());
    }

    private function getInspector(array $templates = [], AdapterInterface|null $cacheAdapter = null): Inspector
    {
        $filesystemLoader = $this->getContaoFilesystemLoader($templates);
        $environment = new Environment($filesystemLoader);
        $cacheAdapter ??= new ArrayAdapter();

        $environment->addExtension(
            new ContaoExtension(
                $environment,
                $filesystemLoader,
                $this->createMock(ContaoCsrfTokenManager::class),
                $this->createMock(ContaoVariable::class),
                new InspectorNodeVisitor($cacheAdapter, $environment),
            ),
        );

        return new Inspector($environment, $cacheAdapter, $filesystemLoader);
    }

    private function getContaoFilesystemLoader(array $templates): ContaoFilesystemLoader
    {
        $filesystemLoader = $this->createMock(ContaoFilesystemLoader::class);
        $filesystemLoader
            ->method('exists')
            ->willReturnCallback(
                static fn (string $name): bool => \array_key_exists($name, $templates),
            )
        ;

        $filesystemLoader
            ->method('isFresh')
            ->willReturn(true)
        ;

        $filesystemLoader
            ->method('getSourceContext')
            ->willReturnCallback(
                static fn (string $name): Source => new Source($templates[$name] ?? throw new LoaderError(''), $name, "templates/$name"),
            )
        ;

        $randomInstance = random_bytes(8);

        $filesystemLoader
            ->method('getCacheKey')
            ->willReturnCallback(
                // Twig template classes live on in memory during tests - to make them
                // independent for each test, we prefix their cache key with a random string.
                static fn (string $name): string => $randomInstance.$name,
            )
        ;

        $filesystemLoader
            ->method('getInheritanceChains')
            ->willReturnCallback(
                static function () use ($templates): array {
                    $hierarchy = ['identifier' => []];

                    foreach (array_keys($templates) as $name) {
                        $hierarchy['identifier']["templates/$name"] = $name;
                    }

                    return $hierarchy;
                },
            )
        ;

        $filesystemLoader
            ->method('getFirst')
            ->willReturnCallback(
                static fn (string $name): string => str_replace('@Contao/', '@Contao_specific/', $name),
            )
        ;

        return $filesystemLoader;
    }
}

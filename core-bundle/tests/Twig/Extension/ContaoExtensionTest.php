<?php

declare(strict_types=1);

/*
 * This file is part of Contao.
 *
 * (c) Leo Feyer
 *
 * @license LGPL-3.0-or-later
 */

namespace Contao\CoreBundle\Tests\Twig\Extension;

use Contao\Config;
use Contao\CoreBundle\Csrf\ContaoCsrfTokenManager;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\CoreBundle\InsertTag\InsertTagParser;
use Contao\CoreBundle\Security\Authentication\Token\TokenChecker;
use Contao\CoreBundle\Tests\TestCase;
use Contao\CoreBundle\Twig\Extension\ContaoExtension;
use Contao\CoreBundle\Twig\Extension\DeprecationsNodeVisitor;
use Contao\CoreBundle\Twig\Global\ContaoVariable;
use Contao\CoreBundle\Twig\Inheritance\DynamicExtendsTokenParser;
use Contao\CoreBundle\Twig\Inheritance\DynamicIncludeTokenParser;
use Contao\CoreBundle\Twig\Inheritance\DynamicUseTokenParser;
use Contao\CoreBundle\Twig\Inspector\InspectorNodeVisitor;
use Contao\CoreBundle\Twig\Interop\ContaoEscaperNodeVisitor;
use Contao\CoreBundle\Twig\Interop\PhpTemplateProxyNodeVisitor;
use Contao\CoreBundle\Twig\Loader\ContaoFilesystemLoader;
use Contao\CoreBundle\Twig\ResponseContext\AddTokenParser;
use Contao\CoreBundle\Twig\Slots\SlotTokenParser;
use Contao\System;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\LoggerInterface;
use Symfony\Component\Cache\Adapter\NullAdapter;
use Symfony\Component\Filesystem\Path;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Fragment\FragmentHandler;
use Twig\Environment;
use Twig\Error\SyntaxError;
use Twig\Extension\AbstractExtension;
use Twig\Extension\CoreExtension;
use Twig\Extension\EscaperExtension;
use Twig\Loader\ArrayLoader;
use Twig\Node\BodyNode;
use Twig\Node\EmptyNode;
use Twig\Node\Expression\ConstantExpression;
use Twig\Node\Expression\FilterExpression;
use Twig\Node\ModuleNode;
use Twig\Node\Node;
use Twig\Node\Nodes;
use Twig\NodeTraverser;
use Twig\Runtime\EscaperRuntime;
use Twig\Source;
use Twig\TwigFilter;
use Twig\TwigFunction;

class ContaoExtensionTest extends TestCase
{
    protected function tearDown(): void
    {
        unset($GLOBALS['TL_MIME']);

        $this->resetStaticProperties([ContaoFramework::class, System::class, Config::class]);

        parent::tearDown();
    }

    public function testAddsTheNodeVisitors(): void
    {
        $nodeVisitors = $this->getContaoExtension()->getNodeVisitors();

        $this->assertCount(4, $nodeVisitors);

        $this->assertInstanceOf(ContaoEscaperNodeVisitor::class, $nodeVisitors[0]);
        $this->assertInstanceOf(InspectorNodeVisitor::class, $nodeVisitors[1]);
        $this->assertInstanceOf(PhpTemplateProxyNodeVisitor::class, $nodeVisitors[2]);
        $this->assertInstanceOf(DeprecationsNodeVisitor::class, $nodeVisitors[3]);
    }

    public function testAddsTheTokenParsers(): void
    {
        $tokenParsers = $this->getContaoExtension()->getTokenParsers();

        $this->assertCount(5, $tokenParsers);

        $this->assertInstanceOf(DynamicExtendsTokenParser::class, $tokenParsers[0]);
        $this->assertInstanceOf(DynamicIncludeTokenParser::class, $tokenParsers[1]);
        $this->assertInstanceOf(DynamicUseTokenParser::class, $tokenParsers[2]);
        $this->assertInstanceOf(AddTokenParser::class, $tokenParsers[3]);
        $this->assertInstanceOf(SlotTokenParser::class, $tokenParsers[4]);
    }

    public function testAddsTheFunctions(): void
    {
        $expectedFunctions = [
            'include' => ['all'],
            'attrs' => [],
            'figure' => [],
            'contao_figure' => ['html'],
            'picture_config' => [],
            'insert_tag' => [],
            'add_schema_org' => [],
            'contao_sections' => ['html'],
            'contao_section' => ['html'],
            'prefix_url' => [],
            'frontend_module' => ['html'],
            'content_element' => ['html'],
            'csp_nonce' => [],
            'csp_source' => [],
            'csp_hash' => [],
            'content_url' => [],
            'slot' => [],
            'backend_icon' => ['html'],
        ];

        $functions = $this->getContaoExtension()->getFunctions();

        $this->assertCount(\count($expectedFunctions), $functions);

        $node = $this->createMock(Node::class);

        foreach ($functions as $function) {
            $this->assertInstanceOf(TwigFunction::class, $function);

            $name = $function->getName();
            $this->assertArrayHasKey($name, $expectedFunctions);
            $this->assertSame($expectedFunctions[$name], $function->getSafe($node), $name);
        }
    }

    public function testPreventsUseOfSlotFunction(): void
    {
        $environment = new Environment(
            new ArrayLoader(['template.html.twig' => 'foo {{ slot() }} bar']),
        );

        $environment->addExtension($this->getContaoExtension());

        $this->expectException(SyntaxError::class);
        $this->expectExceptionMessage('You cannot use the slot() function outside of a slot');

        $environment->render('template.html.twig');
    }

    public function testAddsTheFilters(): void
    {
        $filters = $this->getContaoExtension()->getFilters();

        $expectedFilters = [
            'escape',
            'e',
            'insert_tag',
            'insert_tag_raw',
            'highlight',
            'highlight_auto',
            'format_bytes',
            'sanitize_html',
            'csp_unsafe_inline_style',
            'csp_inline_styles',
            'encode_email',
            'deserialize',
        ];

        $this->assertCount(\count($expectedFilters), $filters);

        foreach ($filters as $filter) {
            $this->assertInstanceOf(TwigFilter::class, $filter);
            $this->assertContains($filter->getName(), $expectedFilters);
        }
    }

    public function testIncludeFunctionDelegatesToTwigInclude(): void
    {
        $methodCalledException = new \Exception();

        $environment = $this->createMock(Environment::class);
        $environment
            ->expects($this->once())
            ->method('resolveTemplate')
            ->with('@Contao_Bar/foo.html.twig')
            ->willThrowException($methodCalledException)
        ;

        $filesystemLoader = $this->createMock(ContaoFilesystemLoader::class);
        $filesystemLoader
            ->method('getAllFirstByThemeSlug')
            ->with('foo')
            ->willReturn(['' => '@Contao_Bar/foo.html.twig'])
        ;

        $includeFunction = $this->getContaoExtension($environment, $filesystemLoader)->getFunctions()[0];
        $args = [$environment, [], '@Contao/foo'];

        $this->expectExceptionObject($methodCalledException);

        ($includeFunction->getCallable())(...$args);
    }

    public function testIncludeFunctionDelegatesToTwigIncludeWithThemeContext(): void
    {
        $methodCalledException = new \Exception();

        $environment = $this->createMock(Environment::class);
        $environment
            ->expects($this->once())
            ->method('resolveTemplate')
            ->with('@Contao_Theme_theme/foo.html.twig')
            ->willThrowException($methodCalledException)
        ;

        $filesystemLoader = $this->createMock(ContaoFilesystemLoader::class);
        $filesystemLoader
            ->method('getAllFirstByThemeSlug')
            ->with('foo')
            ->willReturn(['theme' => '@Contao_Theme_theme/foo.html.twig', '' => '@Contao_Bar/foo.html.twig'])
        ;

        $filesystemLoader
            ->method('getCurrentThemeSlug')
            ->willReturn('theme')
        ;

        $includeFunction = $this->getContaoExtension($environment, $filesystemLoader)->getFunctions()[0];
        $args = [$environment, [], '@Contao/foo'];

        $this->expectExceptionObject($methodCalledException);

        ($includeFunction->getCallable())(...$args);
    }

    public function testThrowsIfCoreIncludeFunctionIsNotFound(): void
    {
        $environment = $this->createMock(Environment::class);
        $environment
            ->method('getRuntime')
            ->willReturn(new EscaperRuntime())
        ;

        $environment
            ->method('getExtension')
            ->willReturnMap([
                [EscaperExtension::class, new EscaperExtension()],
                [CoreExtension::class, new class() extends AbstractExtension {
                }],
            ])
        ;

        $extension = new ContaoExtension(
            $environment,
            $this->createMock(ContaoFilesystemLoader::class),
            $this->createMock(ContaoCsrfTokenManager::class),
            $this->createMock(ContaoVariable::class),
            new InspectorNodeVisitor(new NullAdapter(), $environment),
        );

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('The Twig\Extension\CoreExtension class was expected to register the "include" Twig function but did not.');

        $extension->getFunctions();
    }

    public function testAllowsOnTheFlyRegisteringTemplatesForInputEncoding(): void
    {
        $contaoExtension = $this->getContaoExtension();
        $escaperNodeVisitor = $contaoExtension->getNodeVisitors()[0];

        $traverser = new NodeTraverser(
            $this->createMock(Environment::class),
            [$escaperNodeVisitor],
        );

        $node = new ModuleNode(
            new BodyNode([
                new FilterExpression(
                    new ConstantExpression('text', 1),
                    new TwigFilter('escape'),
                    new Nodes([
                        new ConstantExpression('html', 1),
                        new ConstantExpression(null, 1),
                        new ConstantExpression(true, 1),
                    ]),
                    1,
                ),
            ]),
            null,
            new EmptyNode(),
            new EmptyNode(),
            new EmptyNode(),
            null,
            new Source('<code>', 'foo.html.twig'),
        );

        $original = (string) $node;

        // Traverse tree first time (no changes expected)
        $traverser->traverse($node);
        $iteration1 = (string) $node;

        // Add rule that allows the template and traverse tree a second time (change expected)
        $contaoExtension->addContaoEscaperRule('/foo\.html\.twig/');

        // Adding the same rule should be ignored
        $contaoExtension->addContaoEscaperRule('/foo\.html\.twig/');

        $traverser->traverse($node);
        $iteration2 = (string) $node;

        $this->assertSame($original, $iteration1);
        $this->assertStringNotContainsString("'contao_html'", $iteration1);
        $this->assertStringContainsString("'contao_html'", $iteration2);
    }

    public function testRenderLegacyTemplate(): void
    {
        $extension = $this->getContaoExtension();

        $container = $this->getContainerWithContaoConfiguration(
            Path::canonicalize(__DIR__.'/../../Fixtures/Twig/legacy'),
        );

        $container->set('contao.insert_tag.parser', new InsertTagParser($this->mockContaoFramework(), $this->createMock(LoggerInterface::class), $this->createMock(FragmentHandler::class), $this->createMock(RequestStack::class)));

        System::setContainer($container);

        $output = $extension->renderLegacyTemplate(
            'foo.html5',
            ['B' => ['overwritten B block']],
            ['foo' => 'bar'],
        );

        $this->assertSame("foo: bar\noriginal A block\noverwritten B block", $output);
    }

    public function testRenderLegacyTemplateNested(): void
    {
        $extension = $this->getContaoExtension();

        $container = $this->getContainerWithContaoConfiguration(
            Path::canonicalize(__DIR__.'/../../Fixtures/Twig/legacy'),
        );

        $container->set('contao.insert_tag.parser', new InsertTagParser($this->mockContaoFramework(), $this->createMock(LoggerInterface::class), $this->createMock(FragmentHandler::class), $this->createMock(RequestStack::class)));

        System::setContainer($container);

        $framework = new \ReflectionClass(ContaoFramework::class);
        $framework->setStaticPropertyValue('nonce', '<nonce>');

        $output = $extension->renderLegacyTemplate(
            'baz.html5',
            ['B' => "root before B\n[[TL_PARENT_<nonce>]]root after B"],
            ['foo' => 'bar'],
        );

        $this->assertSame(
            implode("\n", [
                'foo: bar',
                'baz before A',
                'bar before A',
                'original A block',
                'bar after A',
                'baz after A',
                'root before B',
                'baz before B',
                'original B block',
                'baz after B',
                'root after B',
            ]),
            $output,
        );
    }

    public function testRenderLegacyTemplateWithTemplateFunctions(): void
    {
        $tokenChecker = $this->createMock(TokenChecker::class);
        $tokenChecker
            ->method('hasBackendUser')
            ->willReturn(true)
        ;

        $container = $this->getContainerWithContaoConfiguration(Path::canonicalize(__DIR__.'/../../Fixtures/Twig/legacy'));
        $container->set('contao.security.token_checker', $tokenChecker);
        $container->set('contao.insert_tag.parser', new InsertTagParser($this->mockContaoFramework(), $this->createMock(LoggerInterface::class), $this->createMock(FragmentHandler::class), $this->createMock(RequestStack::class)));

        System::setContainer($container);

        $GLOBALS['TL_LANG'] = [
            'MONTHS' => ['a', 'b'],
            'DAYS' => ['c', 'd'],
            'MONTHS_SHORT' => ['e', 'f'],
            'DAYS_SHORT' => ['g', 'h'],
            'DP' => ['select_a_time' => 'i', 'use_mouse_wheel' => 'j', 'time_confirm_button' => 'k', 'apply_range' => 'l', 'cancel' => 'm', 'week' => 'n'],
        ];

        $output = $this->getContaoExtension()->renderLegacyTemplate('with_template_functions.html5', [], []);

        $expected =
            "1\n".
            'Locale.define("en-US","Date",{months:["a","b"],days:["c","d"],months_abbr:["e","f"],days_abbr:["g","h"]});'.
            'Locale.define("en-US","DatePicker",{select_a_time:"i",use_mouse_wheel:"j",time_confirm_button:"k",apply_range:"l",cancel:"m",week:"n"});';

        $this->assertSame($expected, $output);

        unset($GLOBALS['TL_LANG']);
    }

    #[DataProvider('provideTemplateNames')]
    public function testDefaultEscaperRules(string $templateName): void
    {
        $extension = $this->getContaoExtension();

        $property = new \ReflectionProperty(ContaoExtension::class, 'contaoEscaperFilterRules');
        $rules = $property->getValue($extension);

        $this->assertCount(2, $rules);

        foreach ($rules as $rule) {
            if (1 === preg_match($rule, $templateName)) {
                return;
            }
        }

        $this->fail(\sprintf('No escaper rule matched template "%s".', $templateName));
    }

    public static function provideTemplateNames(): iterable
    {
        yield '@Contao namespace' => ['@Contao/foo.html.twig'];
        yield '@Contao namespace with folder' => ['@Contao/foo/bar.html.twig'];
        yield '@Contao_* namespace' => ['@Contao_Global/foo.html.twig'];
        yield '@Contao_* namespace with folder' => ['@Contao_Global/foo/bar.html.twig'];
        yield 'core-bundle template' => ['@ContaoCore/Image/Studio/figure.html.twig'];
    }

    /**
     * @param Environment&MockObject $environment
     */
    private function getContaoExtension(Environment|null $environment = null, ContaoFilesystemLoader|null $filesystemLoader = null): ContaoExtension
    {
        $environment ??= $this->createMock(Environment::class);
        $filesystemLoader ??= $this->createMock(ContaoFilesystemLoader::class);

        $environment
            ->method('getRuntime')
            ->willReturn(new EscaperRuntime())
        ;

        $environment
            ->method('getExtension')
            ->willReturnMap([
                [EscaperExtension::class, new EscaperExtension()],
                [CoreExtension::class, new CoreExtension()],
            ])
        ;

        return new ContaoExtension(
            $environment,
            $filesystemLoader,
            $this->createMock(ContaoCsrfTokenManager::class),
            $this->createMock(ContaoVariable::class),
            new InspectorNodeVisitor(new NullAdapter(), $environment),
        );
    }
}

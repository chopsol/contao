<?php

declare(strict_types=1);

/*
 * This file is part of Contao.
 *
 * (c) Leo Feyer
 *
 * @license LGPL-3.0-or-later
 */

namespace Contao\CoreBundle\Tests\Twig\Inheritance;

use Contao\CoreBundle\Tests\TestCase;
use Contao\CoreBundle\Twig\Inheritance\DynamicExtendsTokenParser;
use Contao\CoreBundle\Twig\Loader\ContaoFilesystemLoader;
use PHPUnit\Framework\Attributes\DataProvider;
use Twig\Environment;
use Twig\Error\SyntaxError;
use Twig\Lexer;
use Twig\Loader\LoaderInterface;
use Twig\Parser;
use Twig\Source;

class DynamicExtendsTokenParserTest extends TestCase
{
    public function testGetTag(): void
    {
        $tokenParser = new DynamicExtendsTokenParser($this->createMock(ContaoFilesystemLoader::class));

        $this->assertSame('extends', $tokenParser->getTag());
    }

    #[DataProvider('provideSources')]
    public function testHandlesContaoExtends(string $code, string ...$expectedStrings): void
    {
        $filesystemLoader = $this->createMock(ContaoFilesystemLoader::class);
        $filesystemLoader
            ->method('getAllDynamicParentsByThemeSlug')
            ->willReturnCallback(
                function (string $name, string $path) {
                    $this->assertSame('/path/to/the/template.html.twig', $path);

                    $hierarchy = [
                        'foo.html.twig' => '<foo-parent>',
                        'bar.html.twig' => '<bar-parent>',
                    ];

                    if (null !== ($resolved = $hierarchy[$name] ?? null)) {
                        return ['' => $resolved];
                    }

                    throw new \LogicException('Template not found in hierarchy.');
                },
            )
        ;

        $environment = new Environment($this->createMock(LoaderInterface::class));
        $environment->addTokenParser(new DynamicExtendsTokenParser($filesystemLoader));

        $source = new Source(
            $code,
            'template.html.twig',
            '/path/to/the/template.html.twig',
        );

        $tokenStream = (new Lexer($environment))->tokenize($source);
        $parentNode = (new Parser($environment))->parse($tokenStream)->getNode('parent');

        foreach ($expectedStrings as $expectedString) {
            $this->assertStringContainsString($expectedString, (string) $parentNode);
        }
    }

    public static function provideSources(): iterable
    {
        yield 'regular extend' => [
            "{% extends '@Foo/bar.html.twig' %}",
            '@Foo/bar.html.twig',
        ];

        yield 'Contao extend' => [
            "{% extends '@Contao/foo.html.twig' %}",
            '<foo-parent>',
        ];

        yield 'conditional extend' => [
            "{% extends x == 1 ? '@Foo/bar.html.twig' : '@Foo/baz.html.twig' %}",
            '@Foo/bar.html.twig', '@Foo/baz.html.twig',
        ];

        yield 'conditional Contao extend' => [
            "{% extends x == 1 ? '@Contao/foo.html.twig' : '@Contao/bar.html.twig' %}",
            '<foo-parent>', '<bar-parent>',
        ];

        yield 'optional extend' => [
            "{% extends ['a.html.twig', 'b.html.twig'] %}",
            'a.html.twig', 'b.html.twig',
        ];

        yield 'optional Contao extend' => [
            // Files missing in the hierarchy should be ignored in this case
            "{% extends ['@Contao/missing.html.twig', '@Contao/bar.html.twig']  %}",
            '@Contao/missing.html.twig', '<bar-parent>',
        ];
    }

    public function testFailsWhenExtendingAnInvalidTemplate(): void
    {
        $filesystemLoader = $this->createMock(ContaoFilesystemLoader::class);
        $filesystemLoader
            ->method('getAllDynamicParentsByThemeSlug')
            ->with('foo', $this->anything())
            ->willThrowException(new \LogicException('Template not found in hierarchy.'))
        ;

        $environment = new Environment($this->createMock(LoaderInterface::class));
        $environment->addTokenParser(new DynamicExtendsTokenParser($filesystemLoader));

        // Use a conditional expression here, so that we can test rethrowing exceptions
        // in case the parent node is not an ArrayExpression
        $source = new Source("{% extends true ? '@Contao/foo' : '' %}", 'template.html.twig');
        $tokenStream = (new Lexer($environment))->tokenize($source);
        $parser = new Parser($environment);

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('Template not found in hierarchy.');

        $parser->parse($tokenStream);
    }

    #[DataProvider('provideSourcesWithErrors')]
    public function testValidatesTokenStream(string $code, string $expectedException): void
    {
        $environment = new Environment($this->createMock(LoaderInterface::class));

        $environment->addTokenParser(new DynamicExtendsTokenParser(
            $this->createMock(ContaoFilesystemLoader::class),
        ));

        $source = new Source(
            $code,
            'template.html.twig',
            '/path/to/the/template.html.twig',
        );

        $tokenStream = (new Lexer($environment))->tokenize($source);
        $parser = new Parser($environment);

        $this->expectException(SyntaxError::class);
        $this->expectExceptionMessageMatches($expectedException);

        $parser->parse($tokenStream);
    }

    public static function provideSourcesWithErrors(): iterable
    {
        yield 'extend from within a block' => [
            "{% block b %}{% extends '@Foo/bar.html.twig' %}{% endblock %}",
            '/^Cannot use "extends" in a block/',
        ];

        yield 'extend from within macro' => [
            "{% macro m() %}{% extends '@Foo/bar.html.twig' %}{% endmacro %}",
            '/^Cannot use "extends" in a macro/',
        ];
    }
}

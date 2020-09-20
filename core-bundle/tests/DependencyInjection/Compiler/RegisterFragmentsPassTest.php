<?php

declare(strict_types=1);

/*
 * This file is part of Contao.
 *
 * (c) Leo Feyer
 *
 * @license LGPL-3.0-or-later
 */

namespace Contao\CoreBundle\Tests\DependencyInjection\Compiler;

use Contao\CoreBundle\DependencyInjection\Compiler\RegisterFragmentsPass;
use Contao\CoreBundle\Fragment\FragmentPreHandlerInterface;
use Contao\CoreBundle\Fragment\FragmentRegistry;
use Contao\CoreBundle\Fragment\Reference\ContentElementReference;
use Contao\CoreBundle\Fragment\Reference\FrontendModuleReference;
use Contao\CoreBundle\Tests\TestCase;
use Symfony\Component\DependencyInjection\ChildDefinition;
use Symfony\Component\DependencyInjection\Compiler\ResolveClassPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\ServiceLocator;

class RegisterFragmentsPassTest extends TestCase
{
    public function testCreatesChildDefinitionForFragments(): void
    {
        $elementController = new Definition('App\Fragments\Text');
        $elementController->addTag('contao.content_element');

        $moduleController = new Definition('App\Fragments\LoginController');
        $moduleController->addTag('contao.frontend_module', ['renderer' => 'esi']);

        $container = $this->getContainerWithFragmentServices();
        $container->setDefinition('app.fragments.content_controller', $elementController);
        $container->setDefinition('app.fragments.module_controller', $moduleController);

        (new ResolveClassPass())->process($container);

        $pass = new RegisterFragmentsPass(ContentElementReference::TAG_NAME);
        $pass->process($container);

        $pass = new RegisterFragmentsPass(FrontendModuleReference::TAG_NAME);
        $pass->process($container);

        $methodCalls = $container->getDefinition('contao.fragment.registry')->getMethodCalls();
        [$element, $module] = $methodCalls;

        /*
         * Test Content Element
         */
        $this->assertSame('add', $element[0]);
        $this->assertSame('contao.content_element.text', $element[1][0]);
        $this->assertRegExp('/^contao.fragment._config_/', (string) $element[1][1]);

        $arguments = $container->getDefinition((string) $element[1][1])->getArguments();
        $this->assertSame('forward', $arguments[1]);

        /** @var ChildDefinition $definition */
        $definition = $container->getDefinition($arguments[0]);
        $this->assertInstanceOf(ChildDefinition::class, $definition);
        $this->assertSame('app.fragments.content_controller', $definition->getParent());

        /*
         * Test Frontend Module
         */
        $this->assertSame('add', $module[0]);
        $this->assertSame('contao.frontend_module.login', $module[1][0]);
        $this->assertRegExp('/^contao.fragment._config_/', (string) $module[1][1]);

        $arguments = $container->getDefinition((string) $module[1][1])->getArguments();
        $this->assertSame('esi', $arguments[1]);

        /** @var ChildDefinition $definition */
        $definition = $container->getDefinition($arguments[0]);
        $this->assertInstanceOf(ChildDefinition::class, $definition);
        $this->assertSame('app.fragments.module_controller', $definition->getParent());
    }

    public function testUsesTheGivenAttributes(): void
    {
        $attributes = [
            'type' => 'foo',
            'renderer' => 'esi',
            'method' => 'bar',
        ];

        $contentController = new Definition('App\Fragments\Text');
        $contentController->addTag('contao.content_element', $attributes);

        $container = $this->getContainerWithFragmentServices();
        $container->setDefinition('app.fragments.content_controller', $contentController);

        (new ResolveClassPass())->process($container);
        $pass = new RegisterFragmentsPass(ContentElementReference::TAG_NAME);
        $pass->process($container);

        $methodCalls = $container->getDefinition('contao.fragment.registry')->getMethodCalls();

        $this->assertSame('add', $methodCalls[0][0]);
        $this->assertSame('contao.content_element.foo', $methodCalls[0][1][0]);
        $this->assertStringMatchesFormat('contao.fragment._config_%S', (string) $methodCalls[0][1][1]);

        $arguments = $container->getDefinition((string) $methodCalls[0][1][1])->getArguments();

        $this->assertSame('contao.fragment._contao.content_element.foo:bar', $arguments[0]);
        $this->assertSame('esi', $arguments[1]);
    }

    public function testMakesFragmentServicesPublic(): void
    {
        $contentController = new Definition('App\Fragments\Text');
        $contentController->setPublic(false);
        $contentController->addTag('contao.content_element');

        $container = $this->getContainerWithFragmentServices();
        $container->setDefinition('app.fragments.content_controller', $contentController);

        (new ResolveClassPass())->process($container);

        $this->assertFalse($container->findDefinition('app.fragments.content_controller')->isPublic());

        $pass = new RegisterFragmentsPass(ContentElementReference::TAG_NAME);
        $pass->process($container);

        /** @var ChildDefinition $definition */
        $definition = $container->findDefinition('contao.fragment._contao.content_element.text');

        $this->assertInstanceOf(ChildDefinition::class, $definition);
        $this->assertSame('app.fragments.content_controller', $definition->getParent());
        $this->assertTrue($definition->isPublic());
    }

    public function testRegistersThePreHandlers(): void
    {
        $contentController = new Definition(FragmentPreHandlerInterface::class);
        $contentController->addTag('contao.content_element');

        $container = $this->getContainerWithFragmentServices();
        $container->setDefinition('app.fragments.content_controller', $contentController);

        (new ResolveClassPass())->process($container);

        $pass = new RegisterFragmentsPass(ContentElementReference::TAG_NAME);
        $pass->process($container);

        $arguments = $container->getDefinition('contao.fragment.pre_handlers')->getArguments();

        $this->assertArrayHasKey('contao.content_element.fragment_pre_handler_interface', $arguments[0]);

        $this->assertSame(
            'contao.fragment._contao.content_element.fragment_pre_handler_interface',
            (string) $arguments[0]['contao.content_element.fragment_pre_handler_interface']
        );
    }

    public function testFailsIfThereIsNoPreHandlersDefinition(): void
    {
        $contentController = new Definition('App\Fragments\Text');
        $contentController->addTag('contao.content_element');

        $container = new ContainerBuilder();
        $container->setDefinition('contao.fragment.registry', new Definition());
        $container->setDefinition('contao.command.debug_fragments', new Definition());
        $container->setDefinition('app.fragments.content_controller', $contentController);

        (new ResolveClassPass())->process($container);

        $pass = new RegisterFragmentsPass(ContentElementReference::TAG_NAME);

        $this->expectException('RuntimeException');
        $this->expectExceptionMessage('Missing service definition for "contao.fragment.pre_handlers"');

        $pass->process($container);
    }

    public function testDoesNothingIfThereIsNoFragmentRegistry(): void
    {
        $container = $this->createMock(ContainerBuilder::class);
        $container
            ->expects($this->never())
            ->method('findDefinition')
        ;

        $pass = new RegisterFragmentsPass(ContentElementReference::TAG_NAME);
        $pass->process($container);
    }

    private function getContainerWithFragmentServices(): ContainerBuilder
    {
        $container = new ContainerBuilder();
        $container->setDefinition('contao.fragment.registry', new Definition(FragmentRegistry::class));
        $container->setDefinition('contao.fragment.pre_handlers', new Definition(ServiceLocator::class, [[]]));

        return $container;
    }
}

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

use Contao\CoreBundle\Command\UserListCommand;
use Contao\CoreBundle\Tests\TestCase;
use Contao\Model\Collection;
use Contao\UserModel;
use PHPUnit\Framework\MockObject\MockObject;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;

class UserListCommandTest extends TestCase
{
    public function testDefinition(): void
    {
        $command = $this->getCommand();

        $this->assertNotEmpty($command->getDescription());

        $definition = $command->getDefinition();

        $this->assertTrue($definition->hasOption('column'));
        $this->assertTrue($definition->hasOption('admins'));
    }

    public function testTakesAdminsFlagAsArgument(): void
    {
        $command = $this->getCommand();

        $input = [
            '--admins' => true,
        ];

        $code = (new CommandTester($command))->execute($input);

        $this->assertSame(0, $code);
    }

    public function testReturnsValidJson(): void
    {
        $command = $this->getCommand();

        $input = [
            '--format' => 'json',
        ];

        $commandTester = new CommandTester($command);

        $code = $commandTester->execute($input);
        $output = $commandTester->getDisplay();

        $this->assertSame(0, $code);
        $this->assertNotNull(json_decode($output, true));
    }

    public function testReturnsValidJsonWithSubset(): void
    {
        $command = $this->getCommand();

        $input = [
            '--format' => 'json',
            '--column' => ['name', 'username'],
        ];

        $commandTester = new CommandTester($command);

        $code = $commandTester->execute($input);
        $output = $commandTester->getDisplay();

        $this->assertSame(0, $code);
        $this->assertNotNull(json_decode($output, true));
    }

    public function testTakesColumnAsArgument(): void
    {
        $command = $this->getCommand();

        $input = [
            '--column' => ['username', 'name'],
        ];

        $code = (new CommandTester($command))->execute($input);

        $this->assertSame(0, $code);
    }

    private function getCommand(bool $noResult = false): UserListCommand
    {
        $collection = new Collection([$this->mockAdminUser(), $this->mockContaoUser()], 'tl_user');

        $userModelAdapter = $this->mockAdapter(['findBy', 'findAll']);
        $userModelAdapter
            ->method('findAll')
            ->willReturn($noResult ? null : $collection, null)
        ;

        $collection = new Collection([$this->mockAdminUser()], 'tl_user');

        $userModelAdapter
            ->method('findBy')
            ->with('admin', '1')
            ->willReturn($noResult ? null : $collection, null)
        ;

        $command = new UserListCommand($this->mockContaoFramework([UserModel::class => $userModelAdapter]));
        $command->setApplication(new Application());

        return $command;
    }

    private function mockContaoUser(): UserModel
    {
        /** @var UserModel&MockObject $userModel */
        $userModel = $this->mockClassWithProperties(UserModel::class);
        $userModel->id = 2;
        $userModel->username = 'j.doe';
        $userModel->name = 'John Doe';

        $userModel
            ->method('row')
            ->willReturn((array) $userModel)
        ;

        return $userModel;
    }

    private function mockAdminUser(): UserModel
    {
        /** @var UserModel&MockObject $userModel */
        $userModel = $this->mockClassWithProperties(UserModel::class);
        $userModel->id = 1;
        $userModel->username = 'j.doe';
        $userModel->name = 'John Doe';
        $userModel->admin = '1';

        $userModel
            ->method('row')
            ->willReturn((array) $userModel)
        ;

        return $userModel;
    }
}

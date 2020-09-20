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

use Contao\BackendUser;
use Contao\CoreBundle\Command\UserCreateCommand;
use Contao\CoreBundle\Tests\TestCase;
use Contao\UserGroupModel;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\MockObject\MockObject;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Security\Core\Encoder\EncoderFactoryInterface;
use Symfony\Component\Security\Core\Encoder\PasswordEncoderInterface;

class UserCreateCommandTest extends TestCase
{
    public function testDefinition(): void
    {
        $command = $this->getCommand();

        $this->assertNotEmpty($command->getDescription());

        $definition = $command->getDefinition();

        $this->assertTrue($definition->hasOption('username'));
        $this->assertTrue($definition->hasOption('name'));
        $this->assertTrue($definition->hasOption('email'));
        $this->assertTrue($definition->hasOption('password'));
        $this->assertTrue($definition->hasOption('language'));
        $this->assertTrue($definition->hasOption('admin'));
        $this->assertTrue($definition->hasOption('group'));
        $this->assertTrue($definition->hasOption('change-password'));
    }

    public function testAsksForTheUsernameIfNotGiven(): void
    {
        $command = $this->getCommand();

        $question = $this->createMock(QuestionHelper::class);
        $question
            ->method('ask')
            ->willReturn('j.doe')
        ;

        $command->getHelperSet()->set($question, 'question');

        $code = (new CommandTester($command))->execute(['--name' => 'John Doe', '--password' => '12345678']);

        $this->assertSame(0, $code);
    }

    public function testAsksForTheNameIfNotGiven(): void
    {
        $command = $this->getCommand();

        $question = $this->createMock(QuestionHelper::class);
        $question
            ->method('ask')
            ->willReturn('John Doe')
        ;

        $command->getHelperSet()->set($question, 'question');

        $code = (new CommandTester($command))->execute(['--username' => 'j.doe', '--password' => '12345678']);

        $this->assertSame(0, $code);
    }

    public function testAsksForThePasswordIfNotGiven(): void
    {
        $command = $this->getCommand();

        $question = $this->createMock(QuestionHelper::class);
        $question
            ->method('ask')
            ->willReturn('12345678')
        ;

        $command->getHelperSet()->set($question, 'question');

        $code = (new CommandTester($command))->execute(['--username' => 'j.doe', '--name' => 'John Doe']);

        $this->assertSame(0, $code);
    }

    public function testFailsWithInvalidEmail(): void
    {
        $command = $this->getCommand();

        $this->expectException(\InvalidArgumentException::class);

        (new CommandTester($command))->execute(['--username' => 'j.doe', '--name' => 'John Doe', '--password' => '12345678', '--email' => 'test@example']);
    }

    public function testFailsWithoutParametersIfNotInteractive(): void
    {
        $command = $this->getCommand();
        $code = (new CommandTester($command))->execute(['--username' => 'foobar'], ['interactive' => false]);

        $this->assertSame(1, $code);
    }

    /**
     * @dataProvider usernamePasswordProvider
     */
    public function testUpdatesTheDatabaseOnSuccess(string $username, string $name, string $email, string $password): void
    {
        /** @var Connection&MockObject $connection */
        $connection = $this->createMock(Connection::class);
        $connection
            ->expects($this->once())
            ->method('insert')
            ->with('tl_user')
            ->willReturn(1)
        ;

        $input = [
            '--username' => $username,
            '--name' => $name,
            '--email' => $email,
            '--password' => $password,
        ];

        $command = $this->getCommand($connection, $password);

        (new CommandTester($command))->execute($input, ['interactive' => false]);
    }

    public function usernamePasswordProvider(): \Generator
    {
        yield ['foobar', 'Foo Bar', 'foobar@example.org', '12345678'];
        yield ['k.jones', 'Kevin Jones', 'k.jones@example.org', 'kevinjones'];
    }

    /**
     * @param Connection&MockObject $connection
     */
    private function getCommand(Connection $connection = null, string $password = null): UserCreateCommand
    {
        if (null === $connection) {
            $connection = $this->createMock(Connection::class);
        }

        if (null === $password) {
            $password = '12345678';
        }

        $encoder = $this->createMock(PasswordEncoderInterface::class);
        $encoder
            ->method('encodePassword')
            ->with($password, null)
            ->willReturn('$argon2id$v=19$m=65536,t=6,p=1$T+WK0xPOk21CQ2dX9AFplw$2uCrfvt7Tby81Dhc8Y7wHQQGP1HnPC3nDEb4FtXsfrQ')
        ;

        $encoderFactory = $this->createMock(EncoderFactoryInterface::class);
        $encoderFactory
            ->method('getEncoder')
            ->with(BackendUser::class)
            ->willReturn($encoder)
        ;

        $userGroupModelAdapter = $this->mockAdapter(['findAll']);
        $userGroupModelAdapter
            ->method('findAll')
            ->willReturn(null, null)
        ;

        $command = new UserCreateCommand($this->mockContaoFramework([UserGroupModel::class => $userGroupModelAdapter]), $connection, $encoderFactory, ['en', 'de', 'ru']);
        $command->setApplication(new Application());

        return $command;
    }
}

<?php

declare(strict_types=1);

/*
 * This file is part of Contao.
 *
 * (c) Leo Feyer
 *
 * @license LGPL-3.0-or-later
 */

namespace Contao\CoreBundle\Tests\Security\Authentication;

use Contao\BackendUser;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\CoreBundle\Routing\ContentUrlGenerator;
use Contao\CoreBundle\Security\Authentication\AuthenticationSuccessHandler;
use Contao\CoreBundle\Tests\TestCase;
use Contao\FrontendUser;
use Contao\PageModel;
use Psr\Log\LoggerInterface;
use Scheb\TwoFactorBundle\Security\Authentication\Token\TwoFactorToken;
use Scheb\TwoFactorBundle\Security\Http\Authenticator\TwoFactorAuthenticator;
use Scheb\TwoFactorBundle\Security\TwoFactor\Trusted\TrustedDeviceManagerInterface;
use Symfony\Bundle\SecurityBundle\Security\FirewallMap;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\HttpFoundation\UriSigner;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\User\UserInterface;

class AuthenticationSuccessHandlerTest extends TestCase
{
    public function testUpdatesTheUserAndAlwaysRedirectsToTargetPathInBackend(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger
            ->expects($this->once())
            ->method('info')
            ->with('User "foobar" has logged in')
        ;

        $parameters = [
            '_always_use_target_path' => '0',
            '_target_path' => base64_encode('http://localhost/target'),
        ];

        $request = new Request([], $parameters);

        $user = $this->createPartialMock(BackendUser::class, ['save']);
        $user->username = 'foobar';
        $user->lastLogin = time() - 3600;
        $user->currentLogin = time() - 1800;

        $user
            ->expects($this->once())
            ->method('save')
        ;

        $token = $this->createMock(TokenInterface::class);
        $token
            ->expects($this->once())
            ->method('getUser')
            ->willReturn($user)
        ;

        $handler = $this->getHandler(null, $logger);
        $response = $handler->onAuthenticationSuccess($request, $token);

        $this->assertSame('http://localhost/target', $response->getTargetUrl());
    }

    public function testThrowsExceptionIfTargetPathParameterIsMissing(): void
    {
        $parameters = [
            '_always_use_target_path' => '0',
        ];

        $request = new Request([], $parameters);

        $user = $this->createPartialMock(BackendUser::class, ['save']);
        $user->username = 'foobar';
        $user->lastLogin = time() - 3600;
        $user->currentLogin = time() - 1800;

        $user
            ->expects($this->once())
            ->method('save')
        ;

        $token = $this->createMock(TokenInterface::class);
        $token
            ->expects($this->once())
            ->method('getUser')
            ->willReturn($user)
        ;

        $this->expectException(BadRequestHttpException::class);

        $handler = $this->getHandler();
        $handler->onAuthenticationSuccess($request, $token);
    }

    public function testDoesNotUpdateTheUserIfNotAContaoUser(): void
    {
        $parameters = [
            '_always_use_target_path' => '1',
            '_target_path' => base64_encode('http://localhost/target'),
        ];

        $request = new Request([], $parameters);

        $token = $this->createMock(TokenInterface::class);
        $token
            ->expects($this->once())
            ->method('getUser')
            ->willReturn($this->createMock(UserInterface::class))
        ;

        $handler = $this->getHandler();
        $response = $handler->onAuthenticationSuccess($request, $token);

        $this->assertSame('http://localhost/target', $response->getTargetUrl());
    }

    public function testUsesTheUrlOfThePage(): void
    {
        $model = $this->createMock(PageModel::class);

        $adapter = $this->mockAdapter(['findFirstActiveByMemberGroups']);
        $adapter
            ->expects($this->once())
            ->method('findFirstActiveByMemberGroups')
            ->with([2, 3])
            ->willReturn($model)
        ;

        $framework = $this->mockContaoFramework([PageModel::class => $adapter]);

        $urlGenerator = $this->createMock(ContentUrlGenerator::class);
        $urlGenerator
            ->expects($this->once())
            ->method('generate')
            ->with($model, [], UrlGeneratorInterface::ABSOLUTE_URL)
            ->willReturn('http://localhost/page')
        ;

        $user = $this->createPartialMock(FrontendUser::class, ['save']);
        $user->lastLogin = time() - 3600;
        $user->currentLogin = time() - 1800;
        $user->groups = [2, 3];

        $user
            ->expects($this->once())
            ->method('save')
        ;

        $token = $this->createMock(TokenInterface::class);
        $token
            ->method('getUser')
            ->willReturn($user)
        ;

        $handler = $this->getHandler($framework, null, false, $urlGenerator);
        $response = $handler->onAuthenticationSuccess(new Request(), $token);

        $this->assertSame('http://localhost/page', $response->getTargetUrl());
    }

    public function testUsesTheDefaultUrlIfNotAPageModel(): void
    {
        $adapter = $this->mockAdapter(['findFirstActiveByMemberGroups']);
        $adapter
            ->expects($this->once())
            ->method('findFirstActiveByMemberGroups')
            ->with([2, 3])
            ->willReturn(null)
        ;

        $framework = $this->mockContaoFramework([PageModel::class => $adapter]);

        $parameters = [
            '_always_use_target_path' => '0',
            '_target_path' => base64_encode('http://localhost/target'),
        ];

        $request = new Request([], $parameters);

        $user = $this->createPartialMock(FrontendUser::class, ['save']);
        $user->lastLogin = time() - 3600;
        $user->currentLogin = time() - 1800;
        $user->groups = [2, 3];

        $user
            ->expects($this->once())
            ->method('save')
        ;

        $token = $this->createMock(TokenInterface::class);
        $token
            ->method('getUser')
            ->willReturn($user)
        ;

        $handler = $this->getHandler($framework);
        $response = $handler->onAuthenticationSuccess($request, $token);

        $this->assertSame('http://localhost/target', $response->getTargetUrl());
    }

    public function testUsesTheTargetPath(): void
    {
        $adapter = $this->mockAdapter(['findFirstActiveByMemberGroups']);
        $adapter
            ->expects($this->never())
            ->method('findFirstActiveByMemberGroups')
        ;

        $framework = $this->mockContaoFramework([PageModel::class => $adapter]);

        $parameters = [
            '_always_use_target_path' => '1',
            '_target_path' => base64_encode('http://localhost/target'),
        ];

        $request = new Request([], $parameters);

        $user = $this->createPartialMock(FrontendUser::class, ['save']);
        $user->lastLogin = time() - 3600;
        $user->currentLogin = time() - 1800;
        $user->groups = [2, 3];

        $user
            ->expects($this->once())
            ->method('save')
        ;

        $token = $this->createMock(TokenInterface::class);
        $token
            ->method('getUser')
            ->willReturn($user)
        ;

        $handler = $this->getHandler($framework);
        $response = $handler->onAuthenticationSuccess($request, $token);

        $this->assertSame('http://localhost/target', $response->getTargetUrl());
    }

    public function testUsesTheTargetPathFromQueryIfTheUrlIsSigned(): void
    {
        $adapter = $this->mockAdapter(['findFirstActiveByMemberGroups']);
        $adapter
            ->expects($this->never())
            ->method('findFirstActiveByMemberGroups')
        ;

        $framework = $this->mockContaoFramework([PageModel::class => $adapter]);
        $request = new Request(['_target_path' => base64_encode('http://localhost/target')]);

        $user = $this->createPartialMock(BackendUser::class, ['save']);
        $user->lastLogin = time() - 3600;
        $user->currentLogin = time() - 1800;
        $user->groups = [2, 3];

        $user
            ->expects($this->once())
            ->method('save')
        ;

        $token = $this->createMock(TokenInterface::class);
        $token
            ->method('getUser')
            ->willReturn($user)
        ;

        $handler = $this->getHandler($framework, null, true);
        $response = $handler->onAuthenticationSuccess($request, $token);

        $this->assertSame('http://localhost/target', $response->getTargetUrl());
    }

    public function testReloadsIfTwoFactorAuthenticationIsEnabled(): void
    {
        $request = $this->createMock(Request::class);
        $request
            ->expects($this->once())
            ->method('getUri')
            ->willReturn('http://localhost/failure')
        ;

        $user = $this->createPartialMock(FrontendUser::class, ['save']);
        $user
            ->expects($this->once())
            ->method('save')
        ;

        $token = $this->createMock(TwoFactorToken::class);
        $token
            ->expects($this->once())
            ->method('getUser')
            ->willReturn($user)
        ;

        $response = $this->getHandler()->onAuthenticationSuccess($request, $token);

        $this->assertSame('http://localhost/failure', $response->getTargetUrl());
    }

    public function testUnwrapsTwoFactorTokenIfSignedUrlParameterExists(): void
    {
        $request = Request::create('http://localhost/contao/login-link');
        $request->query->set(TwoFactorAuthenticator::FLAG_2FA_COMPLETE, '1');
        $request->query->set('_target_path', base64_encode('http://localhost/target/path'));

        $user = $this->createPartialMock(BackendUser::class, ['save']);
        $user
            ->expects($this->once())
            ->method('save')
        ;

        $authenticatedToken = $this->createMock(UsernamePasswordToken::class);
        $authenticatedToken
            ->expects($this->once())
            ->method('setAttribute')
            ->with(TwoFactorAuthenticator::FLAG_2FA_COMPLETE, true)
        ;

        $twoFactorToken = $this->createMock(TwoFactorToken::class);
        $twoFactorToken
            ->expects($this->once())
            ->method('getUser')
            ->willReturn($user)
        ;

        $twoFactorToken
            ->expects($this->once())
            ->method('getAuthenticatedToken')
            ->willReturn($authenticatedToken)
        ;

        $response = $this->getHandler(null, null, true)->onAuthenticationSuccess($request, $twoFactorToken);

        $this->assertSame('http://localhost/target/path', $response->getTargetUrl());
    }

    public function testStoresTheTargetPathInSessionOnTwoFactorAuthentication(): void
    {
        $session = $this->createMock(SessionInterface::class);
        $session
            ->expects($this->once())
            ->method('set')
            ->with('_security.contao_frontend.target_path')
        ;

        $request = $this->createMock(Request::class);
        $request
            ->expects($this->atLeastOnce())
            ->method('getUri')
            ->willReturn('http://localhost/failure')
        ;

        $request
            ->method('getSession')
            ->willReturn($session)
        ;

        $request
            ->method('hasSession')
            ->willReturn(true)
        ;

        $request
            ->method('isMethodSafe')
            ->willReturn(true)
        ;

        $request
            ->method('isXmlHttpRequest')
            ->willReturn(false)
        ;

        $user = $this->createPartialMock(FrontendUser::class, ['save']);

        $token = $this->createMock(TwoFactorToken::class);
        $token
            ->expects($this->once())
            ->method('getUser')
            ->willReturn($user)
        ;

        $token
            ->expects($this->once())
            ->method('getFirewallName')
            ->willReturn('contao_frontend')
        ;

        $response = $this->getHandler()->onAuthenticationSuccess($request, $token);

        $this->assertSame('http://localhost/failure', $response->getTargetUrl());
    }

    public function testRemovesTheTargetPathInTheSessionOnLogin(): void
    {
        $session = $this->createMock(SessionInterface::class);
        $session
            ->expects($this->once())
            ->method('remove')
            ->with('_security.contao_frontend.target_path')
        ;

        $request = new Request([], ['_target_path' => base64_encode('/')]);
        $request->setSession($session);

        $token = $this->createMock(UsernamePasswordToken::class);
        $token
            ->expects($this->once())
            ->method('getUser')
            ->willReturn($this->createPartialMock(BackendUser::class, ['save']))
        ;

        $token
            ->expects($this->once())
            ->method('getFirewallName')
            ->willReturn('contao_frontend')
        ;

        $this->getHandler()->onAuthenticationSuccess($request, $token);
    }

    private function getHandler(ContaoFramework|null $framework = null, LoggerInterface|null $logger = null, bool $checkRequest = false, ContentUrlGenerator|null $urlGenerator = null): AuthenticationSuccessHandler
    {
        $framework ??= $this->mockContaoFramework();
        $trustedDeviceManager = $this->createMock(TrustedDeviceManagerInterface::class);
        $firewallMap = $this->createMock(FirewallMap::class);
        $tokenStorage = $this->createMock(TokenStorageInterface::class);
        $urlGenerator ??= $this->createMock(ContentUrlGenerator::class);
        $logger ??= $this->createMock(LoggerInterface::class);

        $uriSigner = $this->createMock(UriSigner::class);
        $uriSigner
            ->method('checkRequest')
            ->willReturn($checkRequest)
        ;

        return new AuthenticationSuccessHandler($framework, $trustedDeviceManager, $firewallMap, $urlGenerator, $uriSigner, $tokenStorage, $logger);
    }
}

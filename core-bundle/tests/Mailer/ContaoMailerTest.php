<?php

declare(strict_types=1);

/*
 * This file is part of Contao.
 *
 * (c) Leo Feyer
 *
 * @license LGPL-3.0-or-later
 */

namespace Contao\CoreBundle\Tests\Mailer;

use Contao\CoreBundle\Mailer\AvailableTransports;
use Contao\CoreBundle\Mailer\ContaoMailer;
use Contao\CoreBundle\Mailer\TransportConfig;
use Contao\CoreBundle\Tests\TestCase;
use Contao\PageModel;
use PHPUnit\Framework\MockObject\MockObject;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Mailer\Mailer;
use Symfony\Component\Mailer\Transport\TransportInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\Header\Headers;
use Symfony\Component\Mime\Header\UnstructuredHeader;

class ContaoMailerTest extends TestCase
{
    public function testSetsTransportForRequest(): void
    {
        /** @var PageModel&MockObject $pageModel */
        $pageModel = $this->mockClassWithProperties(PageModel::class);
        $pageModel->mailerTransport = 'foobar';

        $request = new Request();
        $request->attributes->set('pageModel', $pageModel);

        $requestStack = new RequestStack();
        $requestStack->push($request);

        $transport = $this->createMock(TransportInterface::class);
        $mailer = new Mailer($transport);

        $availableTransports = new AvailableTransports();
        $availableTransports->addTransport(new TransportConfig('foobar', 'Lorem Ipsum <foo@example.org>'));

        $contaoMailer = new ContaoMailer($mailer, $availableTransports, $requestStack);

        $email = new Email();
        $contaoMailer->send($email);

        $this->assertTrue($email->getHeaders()->has('X-Transport'));
        $this->assertSame('foobar', $email->getHeaders()->get('X-Transport')->getBodyAsString());
    }

    public function testSetsFromForTransport(): void
    {
        $transport = $this->createMock(TransportInterface::class);
        $mailer = new Mailer($transport);

        $availableTransports = new AvailableTransports();
        $availableTransports->addTransport(new TransportConfig('foobar', 'Lorem Ipsum <foo@example.org>'));

        $contaoMailer = new ContaoMailer($mailer, $availableTransports, new RequestStack());

        $email = new Email(new Headers(new UnstructuredHeader('X-Transport', 'foobar')));
        $contaoMailer->send($email);

        $from = $email->getFrom();

        $this->assertCount(1, $from);
        $this->assertSame('Lorem Ipsum', $from[0]->getName());
        $this->assertSame('foo@example.org', $from[0]->getAddress());
    }
}

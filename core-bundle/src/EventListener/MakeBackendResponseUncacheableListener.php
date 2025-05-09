<?php

declare(strict_types=1);

/*
 * This file is part of Contao.
 *
 * (c) Leo Feyer
 *
 * @license LGPL-3.0-or-later
 */

namespace Contao\CoreBundle\EventListener;

use Contao\CoreBundle\Routing\ScopeMatcher;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\ResponseEvent;

#[AsEventListener]
class MakeBackendResponseUncacheableListener
{
    public function __construct(private readonly ScopeMatcher $scopeMatcher)
    {
    }

    public function __invoke(ResponseEvent $event): void
    {
        if (!$this->scopeMatcher->isBackendMainRequest($event)) {
            return;
        }

        $event->getResponse()->headers->set('Cache-Control', 'no-cache, no-store, must-revalidate');
    }
}

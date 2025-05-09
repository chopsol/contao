<?php

declare(strict_types=1);

/*
 * This file is part of Contao.
 *
 * (c) Leo Feyer
 *
 * @license LGPL-3.0-or-later
 */

namespace Contao\CoreBundle\Tests\Messenger\Message\BackendSearch;

use Contao\CoreBundle\Messenger\Message\BackendSearch\DeleteDocumentsMessage;
use Contao\CoreBundle\Search\Backend\GroupedDocumentIds;
use PHPUnit\Framework\TestCase;

class DeleteDocumentsMessageTest extends TestCase
{
    public function testGetter(): void
    {
        $message = new DeleteDocumentsMessage(new GroupedDocumentIds(['foobar' => ['42']]));
        $this->assertSame(['foobar' => ['42']], $message->getGroupedDocumentIds()->toArray());
    }
}

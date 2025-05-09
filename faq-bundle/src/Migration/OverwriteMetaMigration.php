<?php

declare(strict_types=1);

/*
 * This file is part of Contao.
 *
 * (c) Leo Feyer
 *
 * @license LGPL-3.0-or-later
 */

namespace Contao\FaqBundle\Migration;

use Contao\CoreBundle\Migration\Version505\OverwriteMetaMigration as BaseMigration;

class OverwriteMetaMigration extends BaseMigration
{
    protected const TABLE_NAME = 'tl_faq';
}

<?php

declare(strict_types=1);

/*
 * This file is part of Contao.
 *
 * (c) Leo Feyer
 *
 * @license LGPL-3.0-or-later
 */

namespace Contao\CoreBundle\Migration\Version500;

/**
 * @internal
 */
class OrderFieldMigration extends AbstractOrderFieldMigration
{
    protected function getTableFields(): array
    {
        return [
            'tl_content' => [
                'orderSRC' => 'multiSRC',
            ],
            'tl_module' => [
                'orderSRC' => 'multiSRC',
            ],
        ];
    }
}

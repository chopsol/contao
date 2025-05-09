<?php

/**
 * I am a declare(strict_types=1) comment
 */

declare(strict_types=1);

use Contao\DC_Table;

$GLOBALS['TL_DCA']['tl_test_with_declare3'] = [
    'config' => [
        'dataContainer' => DC_Table::class,
        'sql' => [
            'keys' => [
                'id' => 'primary',
            ],
        ],
    ],
    'fields' => [
        'id' => [
            'sql' => "int(10) unsigned NOT NULL auto_increment"
        ],
    ],
];

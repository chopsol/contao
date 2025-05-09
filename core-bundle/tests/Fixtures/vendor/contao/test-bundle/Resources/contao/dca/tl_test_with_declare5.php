<?php

declare(ticks=1,strict_types=1);

use Contao\DC_Table;

$GLOBALS['TL_DCA']['tl_test_with_declare5'] = [
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


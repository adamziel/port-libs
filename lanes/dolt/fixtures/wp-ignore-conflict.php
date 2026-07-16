<?php

declare(strict_types=1);

use PortLibs\Dolt\TableSchema;

$scratchSchema = TableSchema::fromColumns([
    ['name' => 'cache_key', 'tag' => 1, 'type' => 'varchar(191)', 'primaryKey' => true],
    ['name' => 'payload', 'tag' => 2, 'type' => 'longtext'],
]);

return [
    'ignorePatterns' => [
        ['pattern' => 'wp_tmp_*', 'ignore' => true],
        ['pattern' => '*_cache', 'ignore' => false],
    ],
    'fromTables' => [],
    'toTables' => [
        [
            'name' => 'wp_tmp_import_cache',
            'schema' => $scratchSchema,
            'rowHash' => 'cache',
            'rowCount' => 12,
        ],
    ],
    'expectedErrorFragments' => [
        'the table wp_tmp_import_cache matches conflicting patterns in dolt_ignore:',
        'ignored:     wp_tmp_*',
        'not ignored: *_cache',
    ],
];

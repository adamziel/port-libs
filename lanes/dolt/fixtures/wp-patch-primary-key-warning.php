<?php

declare(strict_types=1);

use PortLibs\Dolt\TableSchema;

$stagedSchema = TableSchema::fromColumns([
    ['name' => 'meta_id', 'tag' => 10, 'type' => 'bigint', 'primaryKey' => true],
    ['name' => 'post_id', 'tag' => 11, 'type' => 'bigint'],
    ['name' => 'meta_key', 'tag' => 12, 'type' => 'varchar(255)'],
    ['name' => 'meta_value', 'tag' => 13, 'type' => 'longtext'],
]);

$workingSchema = TableSchema::fromColumns([
    ['name' => 'meta_id', 'tag' => 10, 'type' => 'bigint'],
    ['name' => 'post_id', 'tag' => 11, 'type' => 'bigint', 'primaryKey' => true],
    ['name' => 'meta_key', 'tag' => 12, 'type' => 'varchar(255)', 'primaryKey' => true],
    ['name' => 'meta_value', 'tag' => 13, 'type' => 'longtext'],
]);

return [
    'options' => [
        'knownTables' => ['wp_postmeta'],
        'revisionSnapshots' => [
            'STAGED' => [
                [
                    'name' => 'wp_postmeta',
                    'schema' => $stagedSchema,
                    'rows' => [
                        [
                            'meta_id' => 1001,
                            'post_id' => 501,
                            'meta_key' => '_thumbnail_id',
                            'meta_value' => '7001',
                        ],
                    ],
                ],
            ],
            'WORKING' => [
                [
                    'name' => 'wp_postmeta',
                    'schema' => $workingSchema,
                    'rows' => [
                        [
                            'meta_id' => 1001,
                            'post_id' => 501,
                            'meta_key' => '_thumbnail_id',
                            'meta_value' => '7002',
                        ],
                    ],
                ],
            ],
        ],
    ],
];

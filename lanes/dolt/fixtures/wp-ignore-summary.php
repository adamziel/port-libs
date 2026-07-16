<?php

declare(strict_types=1);

use PortLibs\Dolt\TableSchema;

$postSchema = TableSchema::fromColumns([
    ['name' => 'ID', 'tag' => 1, 'type' => 'bigint', 'primaryKey' => true],
    ['name' => 'post_title', 'tag' => 2, 'type' => 'longtext'],
]);

$scratchSchema = TableSchema::fromColumns([
    ['name' => 'cache_key', 'tag' => 10, 'type' => 'varchar(191)', 'primaryKey' => true],
    ['name' => 'payload', 'tag' => 11, 'type' => 'longtext'],
]);

$oldScratchSchema = TableSchema::fromColumns([
    ['name' => 'old_key', 'tag' => 20, 'type' => 'varchar(191)', 'primaryKey' => true],
    ['name' => 'old_payload', 'tag' => 21, 'type' => 'longtext'],
]);

return [
    'ignorePatterns' => [
        ['pattern' => 'wp_tmp_%', 'ignore' => true],
        ['pattern' => 'wp_tmp_keep', 'ignore' => false],
        ['pattern' => 'wp_migration_tmp_*', 'ignore' => true],
    ],
    'fromTables' => [
        [
            'name' => 'wp_posts',
            'schema' => $postSchema,
            'rowHash' => 'posts-same',
            'rowCount' => 20,
        ],
        [
            'name' => 'wp_migration_tmp_old',
            'schema' => $oldScratchSchema,
            'rowHash' => 'old-scratch',
            'rowCount' => 5,
        ],
    ],
    'toTables' => [
        [
            'name' => 'wp_posts',
            'schema' => $postSchema,
            'rowHash' => 'posts-same',
            'rowCount' => 20,
        ],
        [
            'name' => 'dolt_ignore',
            'schema' => TableSchema::fromColumns([
                ['name' => 'pattern', 'tag' => 100, 'type' => 'varchar(255)', 'primaryKey' => true],
                ['name' => 'ignored', 'tag' => 101, 'type' => 'boolean'],
            ]),
            'rowHash' => 'ignore-patterns',
            'rowCount' => 3,
        ],
        [
            'name' => 'wp_import_review',
            'schema' => $postSchema,
            'rowHash' => 'review',
            'rowCount' => 2,
        ],
        [
            'name' => 'wp_tmp_import_cache',
            'schema' => $scratchSchema,
            'rowHash' => 'tmp-cache',
            'rowCount' => 30,
        ],
        [
            'name' => 'wp_tmp_keep',
            'schema' => $scratchSchema,
            'rowHash' => 'tmp-keep',
            'rowCount' => 1,
        ],
    ],
    'expectedToTables' => ['dolt_ignore', 'wp_import_review', 'wp_tmp_keep'],
];

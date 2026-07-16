<?php

declare(strict_types=1);

use PortLibs\Dolt\TableSchema;

$fromSchema = TableSchema::fromColumns([
    ['name' => 'ID', 'tag' => 1, 'type' => 'bigint', 'primaryKey' => true],
    ['name' => 'post_title', 'tag' => 2, 'type' => 'longtext'],
    ['name' => 'post_status', 'tag' => 3, 'type' => 'varchar(20)'],
    ['name' => 'guid', 'tag' => 4, 'type' => 'varchar(255)'],
    ['name' => 'menu_order', 'tag' => 5, 'type' => 'int'],
    ['name' => 'comment_count', 'tag' => 6, 'type' => 'bigint'],
]);

$toSchema = TableSchema::fromColumns([
    ['name' => 'ID', 'tag' => 1, 'type' => 'bigint', 'primaryKey' => true],
    ['name' => 'post_title', 'tag' => 2, 'type' => 'longtext'],
    ['name' => 'post_status', 'tag' => 3, 'type' => 'varchar(20)'],
    ['name' => 'guid', 'tag' => 4, 'type' => 'varchar(255)'],
    ['name' => 'menu_order', 'tag' => 5, 'type' => 'int'],
    ['name' => 'comment_count', 'tag' => 6, 'type' => 'bigint'],
    ['name' => 'import_batch', 'tag' => 7, 'type' => 'varchar(40)'],
]);

return [
    'fromCommit' => 'wp-before-data-liberation-import',
    'toCommit' => 'wp-after-data-liberation-import',
    'fromSchema' => $fromSchema,
    'toSchema' => $toSchema,
    'includeColumns' => ['post_status'],
    'expectedDataColumns' => ['ID', 'post_title', 'post_status', 'import_batch'],
    'fromRows' => [
        [
            'ID' => 301,
            'post_title' => 'Legacy launch page',
            'post_status' => 'publish',
            'guid' => 'https://example.test/?p=301',
            'menu_order' => 0,
            'comment_count' => 0,
        ],
    ],
    'toRows' => [
        [
            'ID' => 301,
            'post_title' => 'Liberated launch page',
            'post_status' => 'publish',
            'guid' => 'https://example.test/?p=301',
            'menu_order' => 0,
            'comment_count' => 0,
            'import_batch' => 'batch-2026-05-22',
        ],
    ],
];

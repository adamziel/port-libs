<?php

declare(strict_types=1);

use PortLibs\Dolt\TableSchema;

$postSchema = TableSchema::fromColumns([
    ['name' => 'ID', 'tag' => 1, 'type' => 'bigint', 'primaryKey' => true],
    ['name' => 'post_title', 'tag' => 2, 'type' => 'longtext'],
]);

$fromSchema = TableSchema::fromColumns([
    ['name' => 'meta_id', 'tag' => 10, 'type' => 'bigint', 'primaryKey' => true],
    ['name' => 'post_id', 'tag' => 11, 'type' => 'bigint'],
    ['name' => 'meta_key', 'tag' => 12, 'type' => 'varchar(255)'],
    ['name' => 'meta_value', 'tag' => 13, 'type' => 'longtext'],
]);

$toSchema = TableSchema::fromColumns([
    ['name' => 'meta_id', 'tag' => 10, 'type' => 'bigint'],
    ['name' => 'post_id', 'tag' => 11, 'type' => 'bigint', 'primaryKey' => true],
    ['name' => 'meta_key', 'tag' => 12, 'type' => 'varchar(255)', 'primaryKey' => true],
    ['name' => 'meta_value', 'tag' => 13, 'type' => 'longtext'],
]);

return [
    'tableName' => 'wp_postmeta',
    'primaryKey' => 'meta_id',
    'fromCommit' => 'HEAD~',
    'toCommit' => 'HEAD',
    'fromSchema' => $fromSchema,
    'toSchema' => $toSchema,
    'fromRows' => [
        ['meta_id' => 1001, 'post_id' => 501, 'meta_key' => '_thumbnail_id', 'meta_value' => '7001'],
    ],
    'toRows' => [
        ['meta_id' => 1001, 'post_id' => 501, 'meta_key' => '_thumbnail_id', 'meta_value' => '7001'],
    ],
    'fromTables' => [
        ['name' => 'wp_posts', 'schema' => $postSchema, 'rowHash' => 'posts-before', 'rowCount' => 1],
        ['name' => 'wp_postmeta', 'schema' => $fromSchema, 'rowHash' => 'postmeta-same', 'rowCount' => 1],
    ],
    'toTables' => [
        ['name' => 'wp_posts', 'schema' => $postSchema, 'rowHash' => 'posts-after', 'rowCount' => 2],
        ['name' => 'wp_postmeta', 'schema' => $toSchema, 'rowHash' => 'postmeta-same', 'rowCount' => 1],
    ],
    'expectedSummaryRows' => [
        [
            'from_table_name' => 'wp_posts',
            'to_table_name' => 'wp_posts',
            'diff_type' => 'modified',
            'data_change' => true,
            'schema_change' => false,
        ],
    ],
    'expectedStatRow' => ['wp_postmeta', 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0],
];

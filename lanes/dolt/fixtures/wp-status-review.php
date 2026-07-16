<?php

declare(strict_types=1);

use PortLibs\Dolt\TableSchema;

$postSchema = TableSchema::fromColumns([
    ['name' => 'ID', 'tag' => 1, 'type' => 'bigint', 'primaryKey' => true],
    ['name' => 'post_title', 'tag' => 2, 'type' => 'longtext'],
]);

$optionSchema = TableSchema::fromColumns([
    ['name' => 'option_id', 'tag' => 10, 'type' => 'bigint', 'primaryKey' => true],
    ['name' => 'option_name', 'tag' => 11, 'type' => 'varchar(191)'],
    ['name' => 'option_value', 'tag' => 12, 'type' => 'longtext'],
]);

$scratchSchema = TableSchema::fromColumns([
    ['name' => 'cache_key', 'tag' => 20, 'type' => 'varchar(191)', 'primaryKey' => true],
    ['name' => 'payload', 'tag' => 21, 'type' => 'longtext'],
]);

$termSchema = TableSchema::fromColumns([
    ['name' => 'object_id', 'tag' => 30, 'type' => 'bigint', 'primaryKey' => true],
    ['name' => 'term_taxonomy_id', 'tag' => 31, 'type' => 'bigint', 'primaryKey' => true],
]);

return [
    'headTables' => [
        ['name' => 'wp_posts', 'schema' => $postSchema, 'rowHash' => 'posts-head', 'rowCount' => 4],
        ['name' => 'wp_options', 'schema' => $optionSchema, 'rowHash' => 'options-head', 'rowCount' => 20],
        ['name' => 'wp_term_relationships', 'schema' => $termSchema, 'rowHash' => 'terms-head', 'rowCount' => 5],
    ],
    'stagedTables' => [
        ['name' => 'wp_posts', 'schema' => $postSchema, 'rowHash' => 'posts-staged', 'rowCount' => 5],
        ['name' => 'wp_options', 'schema' => $optionSchema, 'rowHash' => 'options-head', 'rowCount' => 20],
        ['name' => 'wp_term_relationships', 'schema' => $termSchema, 'rowHash' => 'terms-head', 'rowCount' => 5],
    ],
    'workingTables' => [
        ['name' => 'wp_posts', 'schema' => $postSchema, 'rowHash' => 'posts-staged', 'rowCount' => 5],
        ['name' => 'wp_options', 'schema' => $optionSchema, 'rowHash' => 'options-working', 'rowCount' => 20],
        ['name' => 'wp_term_relationships', 'schema' => $termSchema, 'rowHash' => 'terms-working', 'rowCount' => 5],
        ['name' => 'wp_import_review', 'schema' => $postSchema, 'rowHash' => 'review-working', 'rowCount' => 2],
        ['name' => 'wp_tmp_import_cache', 'schema' => $scratchSchema, 'rowHash' => 'cache-working', 'rowCount' => 50],
    ],
    'dataConflictTables' => ['wp_term_relationships'],
    'ignorePatterns' => [
        ['pattern' => 'wp_tmp_*', 'ignore' => true],
    ],
    'expectedStatusRows' => [
        ['table_name' => 'wp_term_relationships', 'staged' => 0, 'status' => 'conflict'],
        ['table_name' => 'wp_posts', 'staged' => 1, 'status' => 'modified'],
        ['table_name' => 'wp_import_review', 'staged' => 0, 'status' => 'new table'],
        ['table_name' => 'wp_options', 'staged' => 0, 'status' => 'modified'],
        ['table_name' => 'wp_term_relationships', 'staged' => 0, 'status' => 'modified'],
    ],
];

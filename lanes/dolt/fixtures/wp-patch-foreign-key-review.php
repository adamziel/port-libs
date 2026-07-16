<?php

declare(strict_types=1);

use PortLibs\Dolt\TableSchema;

$postsHead = TableSchema::fromColumns([
    ['name' => 'ID', 'tag' => 1, 'type' => 'bigint', 'primaryKey' => true],
    ['name' => 'import_site_id', 'tag' => 2, 'type' => 'bigint'],
    ['name' => 'post_title', 'tag' => 3, 'type' => 'text'],
]);

$postsStaged = TableSchema::fromColumns([
    ['name' => 'ID', 'tag' => 1, 'type' => 'bigint', 'primaryKey' => true],
    ['name' => 'import_site_id', 'tag' => 2, 'type' => 'bigint', 'primaryKey' => true],
    ['name' => 'post_title', 'tag' => 3, 'type' => 'text'],
]);

$metaHead = TableSchema::fromColumns([
    ['name' => 'meta_id', 'tag' => 10, 'type' => 'bigint', 'primaryKey' => true],
    ['name' => 'post_id', 'tag' => 11, 'type' => 'bigint'],
    ['name' => 'meta_key', 'tag' => 12, 'type' => 'varchar(255)'],
    ['name' => 'meta_value', 'tag' => 13, 'type' => 'longtext'],
]);

$metaStaged = TableSchema::fromColumns([
    ['name' => 'meta_id', 'tag' => 10, 'type' => 'bigint', 'primaryKey' => true],
    ['name' => 'post_id', 'tag' => 11, 'type' => 'bigint'],
    ['name' => 'meta_key', 'tag' => 12, 'type' => 'varchar(255)'],
    ['name' => 'meta_value', 'tag' => 13, 'type' => 'longtext'],
], [
    'indexes' => [['name' => 'fk_post_id', 'columns' => ['post_id']]],
    'foreignKeys' => [[
        'name' => 'fk_post_id',
        'columns' => ['post_id'],
        'referencedTable' => 'wp_posts',
        'referencedColumns' => ['ID'],
    ]],
]);

$postRows = [
    ['ID' => 501, 'import_site_id' => 7, 'post_title' => 'Imported attachment'],
];
$metaRows = [
    ['meta_id' => 9001, 'post_id' => 501, 'meta_key' => '_thumbnail_id', 'meta_value' => '7001'],
];

return [
    'arguments' => ['HEAD', 'STAGED'],
    'options' => [
        'revisionGraph' => [
            ['commit_hash' => 'review-head-hash', 'parents' => [], 'refs' => ['refs/heads/main']],
        ],
        'headHash' => 'review-head-hash',
        'knownTables' => ['wp_posts', 'wp_postmeta'],
        'revisionSnapshots' => [
            'review-head-hash' => [
                ['name' => 'wp_posts', 'schema' => $postsHead, 'rows' => $postRows],
                ['name' => 'wp_postmeta', 'schema' => $metaHead, 'rows' => $metaRows],
            ],
            'STAGED' => [
                ['name' => 'wp_posts', 'schema' => $postsStaged, 'rows' => $postRows],
                ['name' => 'wp_postmeta', 'schema' => $metaStaged, 'rows' => $metaRows],
            ],
        ],
    ],
];

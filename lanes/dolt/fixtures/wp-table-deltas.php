<?php

declare(strict_types=1);

use PortLibs\Dolt\TableSchema;

$postSchema = TableSchema::fromColumns([
    ['name' => 'ID', 'tag' => 1, 'type' => 'int', 'primaryKey' => true],
    ['name' => 'post_title', 'tag' => 2, 'type' => 'longtext'],
    ['name' => 'post_status', 'tag' => 3, 'type' => 'varchar(20)'],
    ['name' => 'post_modified_gmt', 'tag' => 4, 'type' => 'datetime'],
]);

return [
    'fromTables' => [
        [
            'name' => 'wp_posts',
            'schema' => $postSchema,
            'rowHash' => 'posts-before',
            'rowCount' => 2,
        ],
        [
            'name' => 'wp_legacy_links',
            'schema' => TableSchema::fromColumns([
                ['name' => 'link_id', 'tag' => 10, 'type' => 'int', 'primaryKey' => true],
                ['name' => 'link_url', 'tag' => 11, 'type' => 'varchar(255)'],
            ]),
            'rowHash' => 'links-before',
            'rowCount' => 12,
        ],
    ],
    'toTables' => [
        [
            'name' => 'wp_content_posts',
            'schema' => $postSchema,
            'rowHash' => 'posts-after-import',
            'rowCount' => 3,
        ],
        [
            'name' => 'wp_import_audit',
            'schema' => TableSchema::fromColumns([
                ['name' => 'event_id', 'tag' => 20, 'type' => 'int', 'primaryKey' => true],
                ['name' => 'message', 'tag' => 21, 'type' => 'text'],
            ]),
            'rowHash' => 'audit-after',
            'rowCount' => 1,
        ],
    ],
];

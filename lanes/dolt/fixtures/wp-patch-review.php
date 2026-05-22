<?php

declare(strict_types=1);

use PortLibs\Dolt\TableSchema;

$postsBefore = TableSchema::fromColumns([
    ['name' => 'ID', 'tag' => 1, 'type' => 'int', 'primaryKey' => true],
    ['name' => 'post_title', 'tag' => 2, 'type' => 'text'],
    ['name' => 'post_status', 'tag' => 3, 'type' => 'varchar(20)'],
    ['name' => 'post_content', 'tag' => 4, 'type' => 'longtext'],
]);

$postsAfter = TableSchema::fromColumns([
    ['name' => 'ID', 'tag' => 1, 'type' => 'int', 'primaryKey' => true],
    ['name' => 'post_title', 'tag' => 2, 'type' => 'text'],
    ['name' => 'post_state', 'tag' => 3, 'type' => 'varchar(20)'],
    ['name' => 'post_content', 'tag' => 4, 'type' => 'longtext'],
    ['name' => 'import_batch', 'tag' => 5, 'type' => 'varchar(40)'],
]);

$importLog = TableSchema::fromColumns([
    ['name' => 'event_type', 'tag' => 1, 'type' => 'varchar(40)'],
    ['name' => 'message', 'tag' => 2, 'type' => 'text'],
    ['name' => 'created_gmt', 'tag' => 3, 'type' => 'datetime'],
]);

return [
    'fromCommit' => 'review-base',
    'toCommit' => 'review-working',
    'tables' => [
        [
            'tableName' => 'wp_posts',
            'fromSchema' => $postsBefore,
            'toSchema' => $postsAfter,
            'primaryKey' => 'ID',
            'columns' => ['ID', 'post_title', 'post_state', 'post_content', 'import_batch'],
            'fromRows' => [
                [
                    'ID' => 501,
                    'post_title' => 'Draft launch',
                    'post_status' => 'draft',
                    'post_content' => '<!-- wp:paragraph -->Old copy',
                ],
            ],
            'toRows' => [
                [
                    'ID' => 501,
                    'post_title' => 'Published launch',
                    'post_state' => 'publish',
                    'post_content' => '<!-- wp:paragraph -->Old copy',
                    'import_batch' => 'batch-2026-05-22',
                ],
                [
                    'ID' => 502,
                    'post_title' => 'Imported guide',
                    'post_state' => 'draft',
                    'post_content' => '<!-- wp:paragraph -->Imported guide',
                    'import_batch' => 'batch-2026-05-22',
                ],
            ],
        ],
        [
            'tableName' => 'wp_import_log',
            'fromSchema' => $importLog,
            'toSchema' => $importLog,
            'keyless' => true,
            'columns' => ['event_type', 'message', 'created_gmt'],
            'fromRows' => [
                [
                    'event_type' => 'post',
                    'message' => 'queued post 501',
                    'created_gmt' => '2026-05-22 09:01:00',
                ],
            ],
            'toRows' => [
                [
                    'event_type' => 'post',
                    'message' => 'queued post 501',
                    'created_gmt' => '2026-05-22 09:01:00',
                ],
                [
                    'event_type' => 'post',
                    'message' => 'queued post 501',
                    'created_gmt' => '2026-05-22 09:01:00',
                ],
            ],
        ],
    ],
];

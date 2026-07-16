<?php

declare(strict_types=1);

use PortLibs\Dolt\TableSchema;

$schema = TableSchema::fromColumns([
    ['name' => 'ID', 'tag' => 1, 'type' => 'bigint', 'primaryKey' => true],
    ['name' => 'post_title', 'tag' => 2, 'type' => 'longtext'],
    ['name' => 'post_status', 'tag' => 3, 'type' => 'varchar(20)'],
    ['name' => 'post_modified_gmt', 'tag' => 4, 'type' => 'datetime'],
]);

return [
    'tableName' => 'wp_posts',
    'primaryKey' => 'ID',
    'fromSchema' => $schema,
    'toSchema' => $schema,
    'fromRows' => [
        [
            'ID' => 501,
            'post_title' => 'Published resource',
            'post_status' => 'publish',
            'post_modified_gmt' => '2026-05-20 12:00:00',
        ],
        [
            'ID' => 502,
            'post_title' => 'Draft import note',
            'post_status' => 'draft',
            'post_modified_gmt' => '2026-05-20 13:00:00',
        ],
        [
            'ID' => 503,
            'post_title' => 'Retired public page',
            'post_status' => 'publish',
            'post_modified_gmt' => '2026-05-19 14:00:00',
        ],
    ],
    'toRows' => [
        [
            'ID' => 501,
            'post_title' => 'Published resource refresh',
            'post_status' => 'publish',
            'post_modified_gmt' => '2026-05-22 10:00:00',
        ],
        [
            'ID' => 502,
            'post_title' => 'Draft import note',
            'post_status' => 'draft',
            'post_modified_gmt' => '2026-05-20 13:00:00',
        ],
        [
            'ID' => 504,
            'post_title' => 'Imported public page',
            'post_status' => 'publish',
            'post_modified_gmt' => '2026-05-22 11:00:00',
        ],
    ],
    'expectedStatRow' => [
        'wp_posts',
        1,
        1,
        1,
        1,
        4,
        4,
        2,
        3,
        3,
        12,
        12,
    ],
];

<?php

declare(strict_types=1);

return [
    'fromCommit' => 'wp-main-before-import',
    'toCommit' => 'wp-migration-review',
    'columns' => ['ID', 'post_title', 'post_status', 'post_modified_gmt'],
    'fromRows' => [
        [
            'ID' => 101,
            'post_title' => 'Draft landing',
            'post_status' => 'draft',
            'post_modified_gmt' => '2026-05-21 10:00:00',
        ],
        [
            'ID' => 102,
            'post_title' => 'Legacy page',
            'post_status' => 'publish',
            'post_modified_gmt' => '2026-05-20 09:30:00',
        ],
    ],
    'toRows' => [
        [
            'ID' => 101,
            'post_title' => 'Published landing',
            'post_status' => 'publish',
            'post_modified_gmt' => '2026-05-22 08:00:00',
        ],
        [
            'ID' => 103,
            'post_title' => 'Imported resource',
            'post_status' => 'publish',
            'post_modified_gmt' => '2026-05-22 08:15:00',
        ],
    ],
    'expectedDiffTypes' => ['modified', 'removed', 'added'],
    'expectedChangedIds' => [101, 102, 103],
];

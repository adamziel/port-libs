<?php

declare(strict_types=1);

return [
    'fromCommit' => 'wp-before-publish-review',
    'toCommit' => 'wp-after-publish-review',
    'columns' => ['ID', 'post_title', 'post_status', 'post_modified_gmt'],
    'where' => "to_post_status = 'publish' OR from_post_status = 'publish'",
    'limit' => 1,
    'fromRows' => [
        [
            'ID' => 401,
            'post_title' => 'Draft import note',
            'post_status' => 'draft',
            'post_modified_gmt' => '2026-05-21 09:00:00',
        ],
        [
            'ID' => 402,
            'post_title' => 'Published resource',
            'post_status' => 'publish',
            'post_modified_gmt' => '2026-05-20 12:00:00',
        ],
        [
            'ID' => 403,
            'post_title' => 'Private staging note',
            'post_status' => 'private',
            'post_modified_gmt' => '2026-05-20 13:00:00',
        ],
    ],
    'toRows' => [
        [
            'ID' => 401,
            'post_title' => 'Draft import note updated',
            'post_status' => 'draft',
            'post_modified_gmt' => '2026-05-22 09:00:00',
        ],
        [
            'ID' => 402,
            'post_title' => 'Published resource refresh',
            'post_status' => 'publish',
            'post_modified_gmt' => '2026-05-22 10:00:00',
        ],
        [
            'ID' => 404,
            'post_title' => 'New published guide',
            'post_status' => 'publish',
            'post_modified_gmt' => '2026-05-22 11:00:00',
        ],
    ],
    'expectedChangedIds' => [402],
];

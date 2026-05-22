<?php

declare(strict_types=1);

return [
    'columns' => ['ID', 'post_title', 'post_status', 'import_batch'],
    'primaryKey' => 'ID',
    'filters' => [
        'from_commit' => 'wp-import-base',
        'to_commit' => 'wp-import-review',
    ],
    'where' => "to_ID > 900 AND to_ID < 950",
    'snapshots' => [
        [
            'commit_hash' => 'wp-import-base',
            'commit_date' => '2026-05-22 15:00:00',
            'rows' => [
                [
                    'ID' => 901,
                    'post_title' => 'Imported homepage draft',
                    'post_status' => 'draft',
                    'import_batch' => 'batch-a',
                ],
                [
                    'ID' => 910,
                    'post_title' => 'Legacy sidebar note',
                    'post_status' => 'publish',
                    'import_batch' => 'batch-a',
                ],
                [
                    'ID' => 960,
                    'post_title' => 'Out-of-window page',
                    'post_status' => 'publish',
                    'import_batch' => 'batch-a',
                ],
            ],
        ],
        [
            'commit_hash' => 'wp-import-review',
            'commit_date' => '2026-05-22 15:10:00',
            'rows' => [
                [
                    'ID' => 901,
                    'post_title' => 'Imported homepage',
                    'post_status' => 'publish',
                    'import_batch' => 'batch-a',
                ],
                [
                    'ID' => 920,
                    'post_title' => 'Imported resource hub',
                    'post_status' => 'publish',
                    'import_batch' => 'batch-a',
                ],
                [
                    'ID' => 960,
                    'post_title' => 'Out-of-window page updated',
                    'post_status' => 'publish',
                    'import_batch' => 'batch-a',
                ],
            ],
        ],
    ],
    'expectedChangedIds' => [901, 920],
    'expectedDiffTypes' => ['modified', 'added'],
];

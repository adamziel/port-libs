<?php

declare(strict_types=1);

use PortLibs\Dolt\TableSchema;

$schema = TableSchema::fromColumns([
    ['name' => 'event_type', 'tag' => 1, 'type' => 'varchar(40)'],
    ['name' => 'message', 'tag' => 2, 'type' => 'text'],
    ['name' => 'created_gmt', 'tag' => 3, 'type' => 'datetime'],
]);

return [
    'tableName' => 'wp_import_log',
    'schema' => $schema,
    'columns' => ['event_type', 'message', 'created_gmt'],
    'fromCommit' => 'from-import-log',
    'toCommit' => 'working',
    'fromRows' => [
        [
            'event_type' => 'scan',
            'message' => 'started media scan',
            'created_gmt' => '2026-05-22 09:00:00',
        ],
        [
            'event_type' => 'scan',
            'message' => 'started media scan',
            'created_gmt' => '2026-05-22 09:00:00',
        ],
        [
            'event_type' => 'post',
            'message' => 'queued post 501',
            'created_gmt' => '2026-05-22 09:01:00',
        ],
    ],
    'toRows' => [
        [
            'event_type' => 'scan',
            'message' => 'started media scan',
            'created_gmt' => '2026-05-22 09:00:00',
        ],
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
        [
            'event_type' => 'media',
            'message' => 'finished media scan',
            'created_gmt' => '2026-05-22 09:05:00',
        ],
    ],
    'expectedDiffTypeCounts' => [
        'added' => 2,
        'removed' => 1,
        'modified' => 0,
    ],
];

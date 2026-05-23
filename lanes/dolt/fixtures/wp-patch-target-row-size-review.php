<?php

declare(strict_types=1);

use PortLibs\Dolt\TableSchema;

$headSchema = TableSchema::fromColumns([
    ['name' => 'meta_id', 'tag' => 1, 'type' => 'bigint', 'primaryKey' => true],
    ['name' => 'post_id', 'tag' => 2, 'type' => 'bigint'],
    ['name' => 'meta_key', 'tag' => 3, 'type' => 'varchar(255)'],
    ['name' => 'meta_value', 'tag' => 4, 'type' => 'longtext'],
]);

$workingSchema = TableSchema::fromColumns([
    ['name' => 'meta_id', 'tag' => 1, 'type' => 'bigint', 'primaryKey' => true],
    ['name' => 'post_id', 'tag' => 2, 'type' => 'bigint'],
    ['name' => 'meta_key', 'tag' => 3, 'type' => 'varchar(255)'],
    ['name' => 'meta_value', 'tag' => 4, 'type' => 'longtext'],
], [
    'targetRowSize' => 4096,
]);

$headRows = [
    [
        'meta_id' => 7001,
        'post_id' => 501,
        'meta_key' => '_elementor_data',
        'meta_value' => '{"widgets":["legacy"]}',
    ],
];

$workingRows = [
    [
        'meta_id' => 7001,
        'post_id' => 501,
        'meta_key' => '_elementor_data',
        'meta_value' => '{"widgets":["legacy","reviewed"],"layout":"wide"}',
    ],
];

return [
    'arguments' => ['HEAD', 'WORKING', 'wp_postmeta'],
    'options' => [
        'revisionGraph' => [
            ['commit_hash' => 'review-head-hash', 'parents' => [], 'refs' => ['refs/heads/main']],
        ],
        'headHash' => 'review-head-hash',
        'knownTables' => ['wp_postmeta'],
        'revisionSnapshots' => [
            'review-head-hash' => [
                ['name' => 'wp_postmeta', 'schema' => $headSchema, 'rows' => $headRows],
            ],
            'WORKING' => [
                ['name' => 'wp_postmeta', 'schema' => $workingSchema, 'rows' => $workingRows],
            ],
        ],
    ],
];

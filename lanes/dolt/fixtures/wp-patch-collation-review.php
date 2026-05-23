<?php

declare(strict_types=1);

use PortLibs\Dolt\TableSchema;

$legacySchema = TableSchema::fromColumns([
    ['name' => 'option_id', 'tag' => 1, 'type' => 'bigint', 'primaryKey' => true],
    ['name' => 'option_name', 'tag' => 2, 'type' => 'varchar(191)'],
    ['name' => 'option_value', 'tag' => 3, 'type' => 'longtext'],
    ['name' => 'autoload', 'tag' => 4, 'type' => 'varchar(20)'],
], [
    'collation' => 'utf8mb4_unicode_ci',
]);

$reviewSchema = TableSchema::fromColumns([
    ['name' => 'option_id', 'tag' => 1, 'type' => 'bigint', 'primaryKey' => true],
    ['name' => 'option_name', 'tag' => 2, 'type' => 'varchar(191)'],
    ['name' => 'option_value', 'tag' => 3, 'type' => 'longtext'],
    ['name' => 'autoload', 'tag' => 4, 'type' => 'varchar(20)'],
]);

$headRows = [
    ['option_id' => 1, 'option_name' => 'home', 'option_value' => 'http://legacy.example.test', 'autoload' => 'yes'],
    ['option_id' => 2, 'option_name' => 'blogname', 'option_value' => 'Legacy Site', 'autoload' => 'yes'],
];

$workingRows = [
    ['option_id' => 1, 'option_name' => 'home', 'option_value' => 'https://review.example.test', 'autoload' => 'yes'],
    ['option_id' => 2, 'option_name' => 'blogname', 'option_value' => 'Legacy Site', 'autoload' => 'yes'],
];

return [
    'arguments' => ['HEAD', 'WORKING', 'wp_options'],
    'options' => [
        'revisionGraph' => [
            ['commit_hash' => 'review-head-hash', 'parents' => [], 'refs' => ['refs/heads/main']],
        ],
        'headHash' => 'review-head-hash',
        'knownTables' => ['wp_options'],
        'revisionSnapshots' => [
            'review-head-hash' => [
                ['name' => 'wp_options', 'schema' => $legacySchema, 'rows' => $headRows],
            ],
            'WORKING' => [
                ['name' => 'wp_options', 'schema' => $reviewSchema, 'rows' => $workingRows],
            ],
        ],
    ],
];

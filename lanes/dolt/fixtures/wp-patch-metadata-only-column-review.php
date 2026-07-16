<?php

declare(strict_types=1);

use PortLibs\Dolt\TableSchema;

$headSchema = TableSchema::fromColumns([
    ['name' => 'queue_id', 'tag' => 1, 'type' => 'bigint', 'primaryKey' => true],
    ['name' => 'post_name', 'tag' => 2, 'type' => 'varchar(200)', 'default' => 'pending'],
    ['name' => 'import_slug', 'tag' => 3, 'type' => 'varchar(255)', 'generated' => "(concat('wp-',queue_id))"],
    ['name' => 'review_status', 'tag' => 4, 'type' => 'varchar(20)'],
]);

$workingSchema = TableSchema::fromColumns([
    ['name' => 'queue_id', 'tag' => 1, 'type' => 'bigint', 'primaryKey' => true],
    ['name' => 'post_name', 'tag' => 2, 'type' => 'varchar(200)', 'default' => 'reviewed'],
    ['name' => 'import_slug', 'tag' => 3, 'type' => 'varchar(255)', 'generated' => "(concat('import-',queue_id))", 'generatedStored' => true],
    ['name' => 'review_status', 'tag' => 4, 'type' => 'varchar(20)', 'constraints' => ['not_null']],
]);

$rows = [
    [
        'queue_id' => 501,
        'post_name' => 'hello-world',
        'import_slug' => 'wp-501',
        'review_status' => 'pending',
    ],
];

return [
    'arguments' => ['HEAD', 'WORKING', 'wp_import_queue'],
    'options' => [
        'revisionGraph' => [
            ['commit_hash' => 'review-head-hash', 'parents' => [], 'refs' => ['refs/heads/main']],
        ],
        'headHash' => 'review-head-hash',
        'knownTables' => ['wp_import_queue'],
        'revisionSnapshots' => [
            'review-head-hash' => [
                ['name' => 'wp_import_queue', 'schema' => $headSchema, 'rows' => $rows],
            ],
            'WORKING' => [
                ['name' => 'wp_import_queue', 'schema' => $workingSchema, 'rows' => $rows],
            ],
        ],
    ],
    'expectedStatements' => [],
];

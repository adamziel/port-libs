<?php

declare(strict_types=1);

use PortLibs\Dolt\TableSchema;

$headSchema = TableSchema::fromColumns([
    ['name' => 'queue_id', 'tag' => 1, 'type' => 'bigint', 'primaryKey' => true],
    ['name' => 'source_post_id', 'tag' => 2, 'type' => 'bigint'],
    ['name' => 'post_name', 'tag' => 3, 'type' => 'varchar(200)', 'default' => 'pending'],
    ['name' => 'import_slug', 'tag' => 4, 'type' => 'varchar(255)', 'generated' => "(concat('wp-',source_post_id))"],
    ['name' => 'touched', 'tag' => 5, 'type' => 'timestamp', 'default' => 'CURRENT_TIMESTAMP', 'onUpdate' => 'CURRENT_TIMESTAMP'],
]);

$workingSchema = TableSchema::fromColumns([
    ['name' => 'queue_id', 'tag' => 1, 'type' => 'bigint', 'primaryKey' => true],
    ['name' => 'source_post_id', 'tag' => 2, 'type' => 'bigint'],
    ['name' => 'post_name', 'tag' => 3, 'type' => 'varchar(240)', 'default' => 'reviewed'],
    ['name' => 'import_slug', 'tag' => 4, 'type' => 'varchar(320)', 'generated' => "(concat('import-',source_post_id,'-',post_name))", 'generatedStored' => true],
    ['name' => 'touched', 'tag' => 5, 'type' => 'timestamp', 'default' => 'CURRENT_TIMESTAMP', 'onUpdate' => 'CURRENT_TIMESTAMP'],
    ['name' => 'review_status', 'tag' => 6, 'type' => 'varchar(20)', 'default' => 'draft'],
]);

$headRows = [
    [
        'queue_id' => 1,
        'source_post_id' => 501,
        'post_name' => 'hello-world',
        'import_slug' => 'wp-501',
        'touched' => '2026-05-23 02:00:00',
    ],
];
$workingRows = [
    [
        'queue_id' => 1,
        'source_post_id' => 501,
        'post_name' => 'hello-world-reviewed',
        'import_slug' => 'import-501-hello-world-reviewed',
        'touched' => '2026-05-23 02:00:00',
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
                ['name' => 'wp_import_queue', 'schema' => $headSchema, 'rows' => $headRows],
            ],
            'WORKING' => [
                ['name' => 'wp_import_queue', 'schema' => $workingSchema, 'rows' => $workingRows],
            ],
        ],
    ],
    'expectedStatements' => [
        "ALTER TABLE `wp_import_queue` MODIFY COLUMN `post_name` varchar(240) DEFAULT 'reviewed';",
        "ALTER TABLE `wp_import_queue` MODIFY COLUMN `import_slug` varchar(320) GENERATED ALWAYS AS ((concat('import-',source_post_id,'-',post_name))) STORED;",
        "ALTER TABLE `wp_import_queue` ADD `review_status` varchar(20) DEFAULT 'draft';",
        "UPDATE `wp_import_queue` SET `post_name`='hello-world-reviewed',`import_slug`='import-501-hello-world-reviewed' WHERE `queue_id`=1;",
    ],
];

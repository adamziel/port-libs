<?php

declare(strict_types=1);

use PortLibs\Dolt\TableSchema;

$stagedSchema = TableSchema::fromColumns([
    ['name' => 'ID', 'tag' => 1, 'type' => 'bigint', 'primaryKey' => true],
    ['name' => 'post_title', 'tag' => 2, 'type' => 'longtext'],
    ['name' => 'post_status', 'tag' => 3, 'type' => 'varchar(20)'],
    ['name' => 'post_content', 'tag' => 4, 'type' => 'longtext'],
    ['name' => 'legacy_checksum', 'tag' => 5, 'type' => 'varchar(64)'],
]);

$workingSchema = TableSchema::fromColumns([
    ['name' => 'ID', 'tag' => 1, 'type' => 'bigint', 'primaryKey' => true],
    ['name' => 'post_title', 'tag' => 2, 'type' => 'longtext'],
    ['name' => 'post_state', 'tag' => 3, 'type' => 'varchar(20)'],
    ['name' => 'post_content', 'tag' => 4, 'type' => 'longtext'],
    ['name' => 'import_batch', 'tag' => 6, 'type' => 'varchar(40)'],
]);

return [
    'options' => [
        'revisionGraph' => [
            ['commit_hash' => 'wp-review-head-hash', 'parents' => [], 'refs' => ['refs/heads/main']],
        ],
        'headHash' => 'wp-review-head-hash',
        'knownTables' => ['wp_posts', 'wp_options'],
        'revisionSnapshots' => [
            'wp-review-head-hash' => [
                [
                    'name' => 'wp_posts',
                    'schema' => $stagedSchema,
                    'rows' => [
                        [
                            'ID' => 501,
                            'post_title' => 'Draft launch',
                            'post_status' => 'draft',
                            'post_content' => '<!-- wp:paragraph -->Old copy',
                            'legacy_checksum' => 'old-copy',
                        ],
                    ],
                ],
            ],
            'STAGED' => [
                [
                    'name' => 'wp_posts',
                    'schema' => $stagedSchema,
                    'rows' => [
                        [
                            'ID' => 501,
                            'post_title' => 'Published launch',
                            'post_status' => 'publish',
                            'post_content' => '<!-- wp:paragraph -->Old copy',
                            'legacy_checksum' => 'old-copy',
                        ],
                        [
                            'ID' => 502,
                            'post_title' => 'Imported guide',
                            'post_status' => 'draft',
                            'post_content' => '<!-- wp:paragraph -->Imported guide',
                            'legacy_checksum' => 'imported-guide',
                        ],
                    ],
                ],
            ],
            'WORKING' => [
                [
                    'name' => 'wp_posts',
                    'schema' => $workingSchema,
                    'rows' => [
                        [
                            'ID' => 501,
                            'post_title' => 'Published launch',
                            'post_state' => 'publish',
                            'post_content' => '<!-- wp:paragraph -->Reviewed copy',
                            'import_batch' => 'batch-2026-05-23',
                        ],
                        [
                            'ID' => 502,
                            'post_title' => 'Imported guide',
                            'post_state' => 'draft',
                            'post_content' => '<!-- wp:paragraph -->Imported guide',
                            'import_batch' => 'batch-2026-05-23',
                        ],
                        [
                            'ID' => 503,
                            'post_title' => 'Media attachment notes',
                            'post_state' => 'draft',
                            'post_content' => '<!-- wp:paragraph -->Attachment metadata reviewed',
                            'import_batch' => 'batch-2026-05-23',
                        ],
                    ],
                ],
            ],
        ],
    ],
];

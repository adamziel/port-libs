<?php

declare(strict_types=1);

use PortLibs\Dolt\TableSchema;

$headSchema = TableSchema::fromColumns([
    ['name' => 'edge_id', 'tag' => 1, 'type' => 'bigint', 'primaryKey' => true],
    ['name' => 'legacy_post_id', 'tag' => 2, 'type' => 'bigint'],
    ['name' => 'import_post_id', 'tag' => 3, 'type' => 'bigint'],
    ['name' => 'term_taxonomy_id', 'tag' => 4, 'type' => 'bigint'],
    ['name' => 'edge_kind', 'tag' => 5, 'type' => 'varchar(40)'],
], [
    'indexes' => [
        ['name' => 'fk_import_post', 'columns' => ['legacy_post_id']],
        ['name' => 'fk_import_term', 'columns' => ['term_taxonomy_id']],
    ],
    'foreignKeys' => [
        [
            'name' => 'fk_import_post',
            'columns' => ['legacy_post_id'],
            'referencedTable' => 'wp_posts',
            'referencedColumns' => ['ID'],
            'onDelete' => 'CASCADE',
        ],
        [
            'name' => 'fk_import_term',
            'columns' => ['term_taxonomy_id'],
            'referencedTable' => 'wp_term_taxonomy',
            'referencedColumns' => ['term_taxonomy_id'],
            'onDelete' => 'RESTRICT',
        ],
    ],
]);

$workingSchema = TableSchema::fromColumns([
    ['name' => 'edge_id', 'tag' => 1, 'type' => 'bigint', 'primaryKey' => true],
    ['name' => 'legacy_post_id', 'tag' => 2, 'type' => 'bigint'],
    ['name' => 'import_post_id', 'tag' => 3, 'type' => 'bigint'],
    ['name' => 'term_taxonomy_id', 'tag' => 4, 'type' => 'bigint'],
    ['name' => 'edge_kind', 'tag' => 5, 'type' => 'varchar(40)'],
], [
    'indexes' => [
        ['name' => 'fk_import_post', 'columns' => ['import_post_id']],
    ],
    'foreignKeys' => [
        [
            'name' => 'fk_import_post',
            'columns' => ['import_post_id'],
            'referencedTable' => 'wp_posts',
            'referencedColumns' => ['import_post_id'],
            'onUpdate' => 'CASCADE',
        ],
    ],
]);

$rows = [
    [
        'edge_id' => 1,
        'legacy_post_id' => 501,
        'import_post_id' => 9501,
        'term_taxonomy_id' => 77,
        'edge_kind' => 'post-import',
    ],
];

return [
    'arguments' => ['HEAD', 'WORKING', 'wp_import_edges'],
    'options' => [
        'revisionGraph' => [
            ['commit_hash' => 'review-head-hash', 'parents' => [], 'refs' => ['refs/heads/main']],
        ],
        'headHash' => 'review-head-hash',
        'knownTables' => ['wp_import_edges'],
        'revisionSnapshots' => [
            'review-head-hash' => [
                ['name' => 'wp_import_edges', 'schema' => $headSchema, 'rows' => $rows],
            ],
            'WORKING' => [
                ['name' => 'wp_import_edges', 'schema' => $workingSchema, 'rows' => $rows],
            ],
        ],
    ],
    'expectedStatements' => [
        'ALTER TABLE `wp_import_edges` DROP INDEX `fk_import_post`;',
        'ALTER TABLE `wp_import_edges` ADD INDEX `fk_import_post`(`import_post_id`);',
        'ALTER TABLE `wp_import_edges` DROP INDEX `fk_import_term`;',
        'ALTER TABLE `wp_import_edges` DROP FOREIGN KEY `fk_import_post`;',
        'ALTER TABLE `wp_import_edges` ADD CONSTRAINT `fk_import_post` FOREIGN KEY (`import_post_id`) REFERENCES `wp_posts` (`import_post_id`);',
        'ALTER TABLE `wp_import_edges` DROP FOREIGN KEY `fk_import_term`;',
    ],
];

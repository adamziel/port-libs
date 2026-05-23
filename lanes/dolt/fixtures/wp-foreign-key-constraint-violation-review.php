<?php

declare(strict_types=1);

use PortLibs\Dolt\ConstraintViolationsTable;
use PortLibs\Dolt\TableSchema;

$schema = TableSchema::fromColumns([
    ['name' => 'meta_id', 'tag' => 1, 'type' => 'bigint', 'primaryKey' => true],
    ['name' => 'post_id', 'tag' => 2, 'type' => 'bigint'],
    ['name' => 'meta_key', 'tag' => 3, 'type' => 'varchar(255)'],
    ['name' => 'meta_value', 'tag' => 4, 'type' => 'longtext'],
], [
    'indexes' => [
        [
            'name' => 'fk_wp_postmeta_post',
            'columns' => ['post_id'],
        ],
    ],
    'foreignKeys' => [
        [
            'name' => 'fk_wp_postmeta_post',
            'columns' => ['post_id'],
            'referencedTable' => 'wp_posts',
            'referencedColumns' => ['ID'],
            'onDelete' => 'CASCADE',
            'onUpdate' => 'RESTRICT',
        ],
    ],
]);

$violations = [
    [
        'violation_type' => ConstraintViolationsTable::TYPE_FOREIGN_KEY,
        'row' => [
            'meta_id' => 8102,
            'post_id' => 99001,
            'meta_key' => '_thumbnail_id',
            'meta_value' => '551',
        ],
        'foreign_key' => 'fk_wp_postmeta_post',
        'index_name' => 'fk_wp_postmeta_post',
        'table' => 'wp_postmeta',
        'columns' => ['post_id'],
        'on_delete' => 'cascade',
        'on_update' => 'restrict',
        'referenced_index' => '',
        'referenced_table' => 'wp_posts',
        'referenced_columns' => ['ID'],
    ],
    [
        'violation_type' => ConstraintViolationsTable::TYPE_FOREIGN_KEY,
        'row' => [
            'meta_id' => 8103,
            'post_id' => 99002,
            'meta_key' => '_wp_attached_file',
            'meta_value' => 'imports/legacy-missing.jpg',
        ],
        'foreign_key' => 'fk_wp_postmeta_post',
        'index_name' => 'fk_wp_postmeta_post',
        'table' => 'wp_postmeta',
        'columns' => ['post_id'],
        'on_delete' => 'cascade',
        'on_update' => 'restrict',
        'referenced_index' => '',
        'referenced_table' => 'wp_posts',
        'referenced_columns' => ['ID'],
    ],
];

$violationInfo = [
    'Index' => 'fk_wp_postmeta_post',
    'Table' => 'wp_postmeta',
    'Columns' => ['post_id'],
    'OnDelete' => 'CASCADE',
    'OnUpdate' => 'RESTRICT',
    'ForeignKey' => 'fk_wp_postmeta_post',
    'ReferencedIndex' => '',
    'ReferencedTable' => 'wp_posts',
    'ReferencedColumns' => ['ID'],
];

return [
    'tableName' => 'wp_postmeta',
    'fromRootIsh' => 'review-import-branch',
    'schema' => $schema,
    'violations' => $violations,
    'expectedSummaryRows' => [
        ['table' => 'wp_postmeta', 'num_violations' => 2],
    ],
    'expectedViolationRows' => [
        [
            'from_root_ish' => 'review-import-branch',
            'violation_type' => 'foreign key',
            'meta_id' => 8102,
            'post_id' => 99001,
            'meta_key' => '_thumbnail_id',
            'meta_value' => '551',
            'violation_info' => $violationInfo,
        ],
        [
            'from_root_ish' => 'review-import-branch',
            'violation_type' => 'foreign key',
            'meta_id' => 8103,
            'post_id' => 99002,
            'meta_key' => '_wp_attached_file',
            'meta_value' => 'imports/legacy-missing.jpg',
            'violation_info' => $violationInfo,
        ],
    ],
    'singleDeleteCriteria' => [
        'meta_id' => 8102,
        'violation_type' => ConstraintViolationsTable::TYPE_FOREIGN_KEY,
        'violation_info.ForeignKey' => 'fk_wp_postmeta_post',
    ],
    'expectedRemainingAfterSingleDelete' => [
        [
            'from_root_ish' => 'review-import-branch',
            'violation_type' => 'foreign key',
            'meta_id' => 8103,
            'post_id' => 99002,
            'meta_key' => '_wp_attached_file',
            'meta_value' => 'imports/legacy-missing.jpg',
            'violation_info' => $violationInfo,
        ],
    ],
];

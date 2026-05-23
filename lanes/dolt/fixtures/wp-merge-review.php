<?php

declare(strict_types=1);

use PortLibs\Dolt\ConstraintViolationsTable;

$constraintViolationsByTable = [
    'wp_postmeta' => [
        [
            'violation_type' => ConstraintViolationsTable::TYPE_FOREIGN_KEY,
            'violation_info' => [
                'Index' => 'fk_wp_postmeta_post',
                'Table' => 'wp_postmeta',
                'ReferencedTable' => 'wp_posts',
                'ForeignKey' => 'fk_wp_postmeta_post',
                'ReferencedIndex' => '',
            ],
        ],
        [
            'violation_type' => ConstraintViolationsTable::TYPE_FOREIGN_KEY,
            'violation_info' => [
                'Index' => 'fk_wp_postmeta_post',
                'Table' => 'wp_postmeta',
                'ReferencedTable' => 'wp_posts',
                'ForeignKey' => 'fk_wp_postmeta_post',
                'ReferencedIndex' => '',
            ],
        ],
    ],
    'wp_import_audit' => [
        [
            'violation_type' => ConstraintViolationsTable::TYPE_CHECK_CONSTRAINT,
            'violation_info' => [
                'Name' => 'wp_import_audit_chk_status',
                'Expression' => "(`import_status` in ('queued','ready','failed'))",
            ],
        ],
        [
            'violation_type' => ConstraintViolationsTable::TYPE_CHECK_CONSTRAINT,
            'violation_info' => [
                'Name' => 'wp_import_audit_chk_status',
                'Expression' => "(`import_status` in ('queued','ready','failed'))",
            ],
        ],
    ],
    'wp_posts' => [
        [
            'violation_type' => ConstraintViolationsTable::TYPE_NOT_NULL,
            'violation_info' => [
                'Columns' => ['post_title'],
            ],
        ],
        [
            'violation_type' => ConstraintViolationsTable::TYPE_NOT_NULL,
            'violation_info' => [
                'Columns' => ['post_title'],
            ],
        ],
    ],
    'wp_options' => [
        [
            'violation_type' => ConstraintViolationsTable::TYPE_UNIQUE_INDEX,
            'violation_info' => [
                'Name' => 'option_name',
                'Columns' => ['option_name'],
            ],
        ],
        [
            'violation_type' => ConstraintViolationsTable::TYPE_UNIQUE_INDEX,
            'violation_info' => [
                'Name' => 'option_name',
                'Columns' => ['option_name'],
            ],
        ],
    ],
];

return [
    'isMerging' => true,
    'source' => 'migration/import-branch',
    'sourceCommit' => 'b2274926e0dcd84aab000ee242df5b5e75689eef',
    'target' => 'refs/heads/main',
    'dataConflictTables' => ['wp_posts'],
    'constraintViolationTables' => ['wp_postmeta', 'wp_import_audit', 'wp_posts', 'wp_options'],
    'constraintViolationsByTable' => $constraintViolationsByTable,
    'schemaConflictTables' => ['wp_options'],
    'conflictTables' => [
        ['name' => 'wp_posts', 'numConflicts' => 2],
    ],
    'schemaConflictRows' => [
        ['name' => 'wp_options'],
    ],
    'rootObjectConflicts' => [
        ['name' => 'wp_import_preview_view', 'numConflicts' => 1],
    ],
    'expectedMergeStatusRow' => [
        'is_merging' => true,
        'source' => 'migration/import-branch',
        'source_commit' => 'b2274926e0dcd84aab000ee242df5b5e75689eef',
        'target' => 'refs/heads/main',
        'unmerged_tables' => 'wp_posts, wp_postmeta, wp_import_audit, wp_options',
    ],
    'expectedConflictRows' => [
        ['table' => 'wp_posts', 'num_conflicts' => 2],
        ['table' => 'wp_options', 'num_conflicts' => 0],
        ['table' => 'wp_import_preview_view', 'num_conflicts' => 1],
    ],
    'expectedStatusGuidance' => "You have unmerged tables.\n"
        . "  (fix conflicts and constraint violations and run \"dolt commit\")\n"
        . "  (use \"dolt merge --abort\" to abort the merge)\n\n"
        . "Unmerged paths:\n"
        . "  (use \"dolt add <table>...\" to mark resolution)\n"
        . "\tschema conflict:  wp_options\n"
        . "\tboth modified:    wp_posts\n"
        . "\tmodified          wp_import_audit\n"
        . "\tmodified          wp_postmeta",
    'expectedCommitGuidance' => "Unmerged paths:\n"
        . "  (use \"dolt add <table>...\" to mark resolution)\n"
        . "\tschema conflict:  wp_options\n"
        . "\tboth modified:    wp_posts\n"
        . "\tmodified          wp_import_audit\n"
        . "\tmodified          wp_postmeta",
    'expectedMergeConstraintError' => ConstraintViolationsTable::UNRESOLVED_CONSTRAINT_VIOLATIONS_ERROR
        . ConstraintViolationsTable::CONSTRAINT_VIOLATIONS_LIST_PREFIX
        . "\nType: Foreign Key Constraint Violation\n"
        . "\tForeignKey: fk_wp_postmeta_post,\n"
        . "\tTable: wp_postmeta,\n"
        . "\tReferencedTable: wp_posts,\n"
        . "\tIndex: fk_wp_postmeta_post,\n"
        . "\tReferencedIndex:  (2 row(s)), "
        . "\nType: Check Constraint Violation,\n"
        . "\tName: wp_import_audit_chk_status,\n"
        . "\tExpression: (`import_status` in ('queued','ready','failed')) (2 row(s)), "
        . "\nType: Null Constraint Violation,\n"
        . "\tColumns: [post_title] (2 row(s)), "
        . "\nType: Unique Key Constraint Violation,\n"
        . "\tName: option_name,\n"
        . "\tColumns: [option_name] (2 row(s))",
];

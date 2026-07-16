<?php

declare(strict_types=1);

use PortLibs\Dolt\ConstraintViolationsTable;
use PortLibs\Dolt\MergeStatusTable;
use PortLibs\Dolt\PreviewMergeConflictsTable;

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
    'schemaConflictResolutionTables' => ['wp_options'],
    'schemaConflictSideResolutionTable' => 'wp_postmeta',
    'schemaConflictSideResolution' => 'theirs',
    'partialResolution' => [
        'data' => [],
        'schema' => ['wp_options'],
        'constraints' => ['wp_posts', 'wp_options'],
        'rootObjects' => ['wp_import_preview_view', 'wp_prepare_import_batch'],
    ],
    'rootObjectConflicts' => [
        ['name' => 'wp_import_preview_view', 'numConflicts' => 1],
        ['name' => 'wp_prepare_import_batch', 'numConflicts' => 1],
    ],
    'rootObjectResolutionObjects' => ['wp_import_preview_view', 'wp_prepare_import_batch'],
    'rootObjectConflictDetails' => [
        [
            'object_type' => 'view',
            'name' => 'wp_import_preview_view',
            'base_definition' => "CREATE VIEW wp_import_preview_view AS SELECT ID, post_title FROM wp_posts",
            'our_definition' => "CREATE VIEW wp_import_preview_view AS SELECT ID, post_title, post_status FROM wp_posts WHERE post_status <> 'trash'",
            'their_definition' => "CREATE VIEW wp_import_preview_view AS SELECT ID, post_title, import_batch FROM wp_posts JOIN wp_import_audit USING (ID)",
            'description' => 'root object view:wp_import_preview_view conflicts between branches',
        ],
        [
            'object_type' => 'procedure',
            'name' => 'wp_prepare_import_batch',
            'base_definition' => "CREATE PROCEDURE wp_prepare_import_batch() SELECT 'base'",
            'our_definition' => null,
            'their_definition' => "CREATE PROCEDURE wp_prepare_import_batch() SELECT 'their import queue'",
        ],
    ],
    'previewDataConflictTables' => [
        ['table' => 'wp_posts', 'num_data_conflicts' => 2],
    ],
    'previewSchemaConflictTables' => [
        ['table' => 'wp_options', 'num_schema_conflicts' => 1],
    ],
    'previewSchemaConflictDescriptions' => [
        [
            'table' => 'wp_options',
            'base_schema' => "CREATE TABLE `wp_options` (\n"
                . "  `option_id` bigint NOT NULL,\n"
                . "  `option_name` varchar(191),\n"
                . "  `autoload` varchar(20),\n"
                . "  PRIMARY KEY (`option_id`)\n"
                . ")",
            'our_schema' => "CREATE TABLE `wp_options` (\n"
                . "  `option_id` bigint NOT NULL,\n"
                . "  `option_name` varchar(191),\n"
                . "  `autoload` varchar(20) DEFAULT 'yes',\n"
                . "  CONSTRAINT `wp_options_chk_autoload` CHECK (`autoload` in ('yes','no')),\n"
                . "  PRIMARY KEY (`option_id`)\n"
                . ")",
            'their_schema' => "CREATE TABLE `wp_options` (\n"
                . "  `option_id` bigint NOT NULL,\n"
                . "  `option_name` varchar(191),\n"
                . "  `autoload` text,\n"
                . "  CONSTRAINT `wp_options_chk_autoload` CHECK (`autoload` <> ''),\n"
                . "  PRIMARY KEY (`option_id`)\n"
                . ")",
            'column_conflicts' => [
                [
                    'kind' => 'tag_collision',
                    'ours' => ['name' => 'autoload', 'type' => 'varchar(20)'],
                    'theirs' => ['name' => 'autoload', 'type' => 'text'],
                ],
            ],
            'check_conflicts' => [
                [
                    'kind' => 'name_collision',
                    'ours' => ['name' => 'wp_options_chk_autoload'],
                    'theirs' => ['name' => 'wp_options_chk_autoload'],
                ],
            ],
        ],
        [
            'table' => 'wp_postmeta',
            'base_schema' => "CREATE TABLE `wp_postmeta` (\n"
                . "  `meta_id` bigint NOT NULL,\n"
                . "  `post_id` bigint,\n"
                . "  `meta_key` varchar(255),\n"
                . "  PRIMARY KEY (`meta_id`)\n"
                . ")",
            'our_schema' => "CREATE TABLE `wp_postmeta` (\n"
                . "  `meta_id` bigint NOT NULL,\n"
                . "  `post_id` bigint,\n"
                . "  `meta_key` varchar(255),\n"
                . "  KEY `idx_post_meta_key` (`post_id`,`meta_key`),\n"
                . "  PRIMARY KEY (`meta_id`)\n"
                . ")",
            'their_schema' => "CREATE TABLE `wp_postmeta` (\n"
                . "  `meta_id` bigint NOT NULL,\n"
                . "  `post_id` bigint,\n"
                . "  `meta_key` varchar(255),\n"
                . "  KEY `idx_meta_review` (`post_id`,`meta_key`),\n"
                . "  PRIMARY KEY (`meta_id`)\n"
                . ")",
            'index_conflicts' => [
                [
                    'kind' => 'duplicate_index_column_set',
                    'ours' => ['name' => 'idx_post_meta_key'],
                    'theirs' => ['name' => 'idx_meta_review'],
                ],
            ],
        ],
        [
            'table' => 'wp_import_queue',
            'base_schema' => "CREATE TABLE `wp_import_queue` (\n"
                . "  `id` bigint NOT NULL,\n"
                . "  `status` varchar(20),\n"
                . "  `review_state` varchar(20),\n"
                . "  `legacy_flag` varchar(20),\n"
                . "  PRIMARY KEY (`id`)\n"
                . ")",
            'our_schema' => "CREATE TABLE `wp_import_queue` (\n"
                . "  `id` bigint NOT NULL,\n"
                . "  `status` varchar(20),\n"
                . "  `review_state` varchar(20),\n"
                . "  `legacy_flag` varchar(20),\n"
                . "  CONSTRAINT `chk_status_allowed` CHECK (`status` in ('queued','ready')),\n"
                . "  CONSTRAINT `chk_review_state_present` CHECK (`review_state` <> ''),\n"
                . "  CONSTRAINT `chk_legacy_flag` CHECK (`legacy_flag` <> ''),\n"
                . "  PRIMARY KEY (`id`)\n"
                . ")",
            'their_schema' => "CREATE TABLE `wp_import_queue` (\n"
                . "  `id` bigint NOT NULL,\n"
                . "  `status` varchar(20),\n"
                . "  `review_state` varchar(20),\n"
                . "  CONSTRAINT `chk_status_not_failed` CHECK (`status` <> 'failed'),\n"
                . "  CONSTRAINT `chk_review_state_values` CHECK (`review_state` in ('open','done')),\n"
                . "  PRIMARY KEY (`id`)\n"
                . ")",
            'check_conflicts' => [
                [
                    'kind' => 'column_check_collision',
                    'ours' => ['name' => 'chk_status_allowed'],
                    'theirs' => ['name' => 'chk_status_not_failed'],
                ],
                [
                    'kind' => 'invalid_check_collision',
                    'ours' => ['name' => 'chk_legacy_flag'],
                    'theirs' => [],
                ],
                [
                    'kind' => 'deleted_check_collision',
                    'ours' => ['name' => 'chk_review_state_present'],
                    'theirs' => [],
                ],
                [
                    'kind' => 'deleted_check_collision',
                    'ours' => [],
                    'theirs' => ['name' => 'chk_review_state_values'],
                ],
            ],
        ],
    ],
    'previewMergeBaseRows' => [
        ['ID' => 42, 'post_title' => 'Imported draft', 'post_status' => 'draft'],
    ],
    'previewMergeOurRows' => [
        ['ID' => 42, 'post_title' => 'Reviewed local title', 'post_status' => 'publish'],
        ['ID' => 951, 'post_title' => 'Local media import', 'post_status' => 'inherit'],
    ],
    'previewMergeTheirRows' => [
        ['ID' => 42, 'post_title' => 'Imported branch title', 'post_status' => 'future'],
        ['ID' => 951, 'post_title' => 'Remote media import', 'post_status' => 'inherit'],
    ],
    'previewMergePrimaryKey' => 'ID',
    'previewMergeColumns' => ['ID', 'post_title', 'post_status'],
    'previewMergeRightRootish' => 'wp-import-branch-head',
    'previewKeylessBaseRows' => [
        ['event' => 'media-scan', 'object_id' => 42, 'status' => 'queued'],
        ['event' => 'media-scan', 'object_id' => 42, 'status' => 'queued'],
    ],
    'previewKeylessOurRows' => [
        ['event' => 'media-scan', 'object_id' => 42, 'status' => 'queued'],
        ['event' => 'media-scan', 'object_id' => 42, 'status' => 'queued'],
        ['event' => 'media-scan', 'object_id' => 42, 'status' => 'queued'],
    ],
    'previewKeylessTheirRows' => [
        ['event' => 'media-scan', 'object_id' => 42, 'status' => 'queued'],
    ],
    'previewKeylessColumns' => ['event', 'object_id', 'status'],
    'previewSchemaConflictCount' => 1,
    'successfulMergeStats' => [
        [
            'table' => 'wp_posts',
            'operation' => 'modified',
            'rows_added' => 1,
            'rows_modified' => 1,
            'rows_deleted' => 0,
        ],
        [
            'table' => 'wp_import_audit',
            'operation' => 'added',
        ],
        [
            'table' => 'wp_terms',
            'operation' => 'deleted',
        ],
    ],
    'noCommitMergeOptions' => [
        'headHash' => 'wp-main-before-import',
        'mergeHash' => 'wp-import-branch-head',
        'noCommit' => true,
    ],
    'squashMergeOptions' => [
        'headHash' => 'wp-main-before-import',
        'mergeHash' => 'wp-import-branch-head',
        'squash' => true,
        'noCommit' => true,
    ],
    'fastForwardMergeOptions' => [
        'headHash' => 'wp-main-before-import',
        'mergeHash' => 'wp-import-branch-head',
        'ffOnly' => true,
        'canFastForward' => true,
    ],
    'noFfMergeOptions' => [
        'headHash' => 'wp-main-before-import',
        'mergeHash' => 'wp-import-branch-head',
        'noFf' => true,
    ],
    'ffOnlyFailureOptions' => [
        'ffOnly' => true,
        'canFastForward' => false,
    ],
    'ffOnlyNoFfOptions' => [
        'ffOnly' => true,
        'noFf' => true,
    ],
    'ffOnlySquashOptions' => [
        'ffOnly' => true,
        'squash' => true,
    ],
    'abortPreservedWorkingTables' => ['wp_import_scratch'],
    'fastForwardProcedureOptions' => [
        'commitHash' => 'wp-import-branch-head',
        'fastForward' => true,
    ],
    'noFfProcedureOptions' => [
        'commitHash' => 'wp-no-ff-merge-commit',
        'noFf' => true,
    ],
    'noCommitProcedureOptions' => [
        'noCommit' => true,
    ],
    'conflictProcedureOptions' => [
        'hasConflicts' => true,
    ],
    'upToDateProcedureOptions' => [
        'upToDate' => true,
    ],
    'aheadProcedureOptions' => [
        'ahead' => true,
    ],
    'abortProcedureOptions' => [
        'abort' => true,
    ],
    'sqlConflictTransaction' => [
        'hasUnresolvedConflicts' => true,
        'autocommit' => true,
        'allowCommitConflicts' => false,
    ],
    'sqlRollbackOptions' => [
        'source' => 'migration/import-branch',
        'sourceCommit' => 'b2274926e0dcd84aab000ee242df5b5e75689eef',
        'target' => 'refs/heads/main',
        'autocommit' => true,
        'allowCommitConflicts' => false,
    ],
    'sqlQueryableConflictOptions' => [
        'source' => 'migration/import-branch',
        'sourceCommit' => 'b2274926e0dcd84aab000ee242df5b5e75689eef',
        'target' => 'refs/heads/main',
        'autocommit' => false,
        'allowCommitConflicts' => false,
    ],
    'constraintOnlyViolationTables' => ['wp_postmeta', 'wp_import_audit'],
    'constraintOnlyRollbackOptions' => [
        'source' => 'migration/import-branch',
        'sourceCommit' => 'b2274926e0dcd84aab000ee242df5b5e75689eef',
        'target' => 'refs/heads/main',
        'autocommit' => true,
        'allowCommitConflicts' => false,
    ],
    'constraintOnlyQueryableOptions' => [
        'source' => 'migration/import-branch',
        'sourceCommit' => 'b2274926e0dcd84aab000ee242df5b5e75689eef',
        'target' => 'refs/heads/main',
        'autocommit' => false,
        'allowCommitConflicts' => false,
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
        ['table' => 'wp_prepare_import_batch', 'num_conflicts' => 1],
    ],
    'expectedRootObjectConflictRows' => [
        [
            'object_type' => 'view',
            'name' => 'wp_import_preview_view',
            'base_definition' => "CREATE VIEW wp_import_preview_view AS SELECT ID, post_title FROM wp_posts",
            'our_definition' => "CREATE VIEW wp_import_preview_view AS SELECT ID, post_title, post_status FROM wp_posts WHERE post_status <> 'trash'",
            'their_definition' => "CREATE VIEW wp_import_preview_view AS SELECT ID, post_title, import_batch FROM wp_posts JOIN wp_import_audit USING (ID)",
            'description' => 'root object view:wp_import_preview_view conflicts between branches',
        ],
        [
            'object_type' => 'procedure',
            'name' => 'wp_prepare_import_batch',
            'base_definition' => "CREATE PROCEDURE wp_prepare_import_batch() SELECT 'base'",
            'our_definition' => '<deleted>',
            'their_definition' => "CREATE PROCEDURE wp_prepare_import_batch() SELECT 'their import queue'",
            'description' => 'root object procedure:wp_prepare_import_batch conflicts between branches',
        ],
    ],
    'expectedPreviewConflictSummaryRows' => [
        ['table' => 'wp_posts', 'num_data_conflicts' => 2, 'num_schema_conflicts' => 0],
        ['table' => 'wp_options', 'num_data_conflicts' => null, 'num_schema_conflicts' => 1],
    ],
    'expectedPreviewConflictRowsWithoutIds' => [
        [
            'from_root_ish' => 'wp-import-branch-head',
            'base_ID' => 42,
            'base_post_title' => 'Imported draft',
            'base_post_status' => 'draft',
            'our_ID' => 42,
            'our_post_title' => 'Reviewed local title',
            'our_post_status' => 'publish',
            'our_diff_type' => PreviewMergeConflictsTable::DIFF_MODIFIED,
            'their_ID' => 42,
            'their_post_title' => 'Imported branch title',
            'their_post_status' => 'future',
            'their_diff_type' => PreviewMergeConflictsTable::DIFF_MODIFIED,
        ],
        [
            'from_root_ish' => 'wp-import-branch-head',
            'base_ID' => null,
            'base_post_title' => null,
            'base_post_status' => null,
            'our_ID' => 951,
            'our_post_title' => 'Local media import',
            'our_post_status' => 'inherit',
            'our_diff_type' => PreviewMergeConflictsTable::DIFF_ADDED,
            'their_ID' => 951,
            'their_post_title' => 'Remote media import',
            'their_post_status' => 'inherit',
            'their_diff_type' => PreviewMergeConflictsTable::DIFF_ADDED,
        ],
    ],
    'expectedPreviewSchemaConflictError' => 'schema conflicts found: 1',
    'expectedPreviewSchemaConflictDescriptionRows' => [
        [
            'table_name' => 'wp_options',
            'base_schema' => "CREATE TABLE `wp_options` (\n"
                . "  `option_id` bigint NOT NULL,\n"
                . "  `option_name` varchar(191),\n"
                . "  `autoload` varchar(20),\n"
                . "  PRIMARY KEY (`option_id`)\n"
                . ")",
            'our_schema' => "CREATE TABLE `wp_options` (\n"
                . "  `option_id` bigint NOT NULL,\n"
                . "  `option_name` varchar(191),\n"
                . "  `autoload` varchar(20) DEFAULT 'yes',\n"
                . "  CONSTRAINT `wp_options_chk_autoload` CHECK (`autoload` in ('yes','no')),\n"
                . "  PRIMARY KEY (`option_id`)\n"
                . ")",
            'their_schema' => "CREATE TABLE `wp_options` (\n"
                . "  `option_id` bigint NOT NULL,\n"
                . "  `option_name` varchar(191),\n"
                . "  `autoload` text,\n"
                . "  CONSTRAINT `wp_options_chk_autoload` CHECK (`autoload` <> ''),\n"
                . "  PRIMARY KEY (`option_id`)\n"
                . ")",
            'description' => "different column definitions for our column autoload and their column autoload\n"
                . "two checks with the name 'wp_options_chk_autoload' but different definitions",
        ],
        [
            'table_name' => 'wp_postmeta',
            'base_schema' => "CREATE TABLE `wp_postmeta` (\n"
                . "  `meta_id` bigint NOT NULL,\n"
                . "  `post_id` bigint,\n"
                . "  `meta_key` varchar(255),\n"
                . "  PRIMARY KEY (`meta_id`)\n"
                . ")",
            'our_schema' => "CREATE TABLE `wp_postmeta` (\n"
                . "  `meta_id` bigint NOT NULL,\n"
                . "  `post_id` bigint,\n"
                . "  `meta_key` varchar(255),\n"
                . "  KEY `idx_post_meta_key` (`post_id`,`meta_key`),\n"
                . "  PRIMARY KEY (`meta_id`)\n"
                . ")",
            'their_schema' => "CREATE TABLE `wp_postmeta` (\n"
                . "  `meta_id` bigint NOT NULL,\n"
                . "  `post_id` bigint,\n"
                . "  `meta_key` varchar(255),\n"
                . "  KEY `idx_meta_review` (`post_id`,`meta_key`),\n"
                . "  PRIMARY KEY (`meta_id`)\n"
                . ")",
            'description' => "multiple indexes covering the same column set cannot be merged: 'idx_post_meta_key' and 'idx_meta_review'",
        ],
        [
            'table_name' => 'wp_import_queue',
            'base_schema' => "CREATE TABLE `wp_import_queue` (\n"
                . "  `id` bigint NOT NULL,\n"
                . "  `status` varchar(20),\n"
                . "  `review_state` varchar(20),\n"
                . "  `legacy_flag` varchar(20),\n"
                . "  PRIMARY KEY (`id`)\n"
                . ")",
            'our_schema' => "CREATE TABLE `wp_import_queue` (\n"
                . "  `id` bigint NOT NULL,\n"
                . "  `status` varchar(20),\n"
                . "  `review_state` varchar(20),\n"
                . "  `legacy_flag` varchar(20),\n"
                . "  CONSTRAINT `chk_status_allowed` CHECK (`status` in ('queued','ready')),\n"
                . "  CONSTRAINT `chk_review_state_present` CHECK (`review_state` <> ''),\n"
                . "  CONSTRAINT `chk_legacy_flag` CHECK (`legacy_flag` <> ''),\n"
                . "  PRIMARY KEY (`id`)\n"
                . ")",
            'their_schema' => "CREATE TABLE `wp_import_queue` (\n"
                . "  `id` bigint NOT NULL,\n"
                . "  `status` varchar(20),\n"
                . "  `review_state` varchar(20),\n"
                . "  CONSTRAINT `chk_status_not_failed` CHECK (`status` <> 'failed'),\n"
                . "  CONSTRAINT `chk_review_state_values` CHECK (`review_state` in ('open','done')),\n"
                . "  PRIMARY KEY (`id`)\n"
                . ")",
            'description' => "our check 'chk_status_allowed' and their check 'chk_status_not_failed' both reference the same column(s)\n"
                . "check 'chk_legacy_flag' references a column that will be deleted after merge\n"
                . "check 'chk_review_state_present' was deleted in theirs but modified in ours\n"
                . "check 'chk_review_state_values' was deleted in ours but modified in theirs",
        ],
    ],
    'expectedResolvedSchemaConflictState' => [
        'remaining_schema_conflicts' => [],
        'status_guidance' => MergeStatusTable::ALL_MERGED_HEADER,
        'commit_guidance' => null,
    ],
    'expectedResolvedRootObjectConflictState' => [
        'remaining_root_object_conflicts' => [],
        'conflict_rows' => [],
        'merge_failure_summary' => null,
    ],
    'expectedPartiallyResolvedMergeState' => [
        'remaining_data_conflicts' => [
            ['table' => 'wp_posts', 'num_conflicts' => 2],
        ],
        'remaining_schema_conflicts' => [],
        'remaining_constraint_violations' => ['wp_postmeta', 'wp_import_audit'],
        'remaining_root_object_conflicts' => [],
        'conflict_rows' => [
            ['table' => 'wp_posts', 'num_conflicts' => 2],
        ],
        'status_guidance' => "You have unmerged tables.\n"
            . "  (fix conflicts and constraint violations and run \"dolt commit\")\n"
            . "  (use \"dolt merge --abort\" to abort the merge)\n\n"
            . "Unmerged paths:\n"
            . "  (use \"dolt add <table>...\" to mark resolution)\n"
            . "\tboth modified:    wp_posts\n"
            . "\tmodified          wp_import_audit\n"
            . "\tmodified          wp_postmeta",
        'commit_guidance' => "Unmerged paths:\n"
            . "  (use \"dolt add <table>...\" to mark resolution)\n"
            . "\tboth modified:    wp_posts\n"
            . "\tmodified          wp_import_audit\n"
            . "\tmodified          wp_postmeta",
        'merge_failure_summary' => "Automatic merge failed; 3 table(s) are unmerged.\n"
            . "Fix conflicts and constraint violations and then commit the result.\n"
            . "Use 'dolt conflicts' to investigate and resolve conflicts.",
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
    'expectedMergeArtifactPrelude' => "Auto-merging wp_posts\n"
        . "CONFLICT (content): Merge conflict in wp_posts\n"
        . "CONSTRAINT VIOLATION (content): Merge created constraint violation in wp_posts\n"
        . "Auto-merging wp_options\n"
        . "CONFLICT (schema): Merge conflict in wp_options\n"
        . "CONSTRAINT VIOLATION (content): Merge created constraint violation in wp_options\n"
        . "Auto-merging wp_postmeta\n"
        . "CONSTRAINT VIOLATION (content): Merge created constraint violation in wp_postmeta\n"
        . "Auto-merging wp_import_audit\n"
        . "CONSTRAINT VIOLATION (content): Merge created constraint violation in wp_import_audit\n"
        . "Auto-merging wp_import_preview_view\n"
        . "CONFLICT (content): Merge conflict in wp_import_preview_view\n"
        . "Auto-merging wp_prepare_import_batch\n"
        . "CONFLICT (content): Merge conflict in wp_prepare_import_batch",
    'expectedMergeFailureSummary' => "Automatic merge failed; 4 table(s) are unmerged.\n"
        . "Fix conflicts and constraint violations and then commit the result.\n"
        . "Use 'dolt conflicts' to investigate and resolve conflicts.",
    'expectedSuccessfulMergeStats' => "wp_posts | 2 +*\n"
        . "1 tables changed, 1 rows added(+), 1 rows modified(*), 0 rows deleted(-)\n"
        . "wp_import_audit added\n"
        . "wp_terms deleted",
    'expectedNoCommitTranscript' => "Updating wp-main-before-import..wp-import-branch-head\n"
        . MergeStatusTable::MERGE_NO_COMMIT_MESSAGE . "\n"
        . "wp_posts | 2 +*\n"
        . "1 tables changed, 1 rows added(+), 1 rows modified(*), 0 rows deleted(-)\n"
        . "wp_import_audit added\n"
        . "wp_terms deleted",
    'expectedSquashTranscript' => "Updating wp-main-before-import..wp-import-branch-head\n"
        . MergeStatusTable::MERGE_SQUASH_MESSAGE . "\n"
        . MergeStatusTable::MERGE_NO_COMMIT_MESSAGE . "\n"
        . "wp_posts | 2 +*\n"
        . "1 tables changed, 1 rows added(+), 1 rows modified(*), 0 rows deleted(-)\n"
        . "wp_import_audit added\n"
        . "wp_terms deleted",
    'expectedFastForwardTranscript' => MergeStatusTable::MERGE_FAST_FORWARD_MESSAGE . "\n"
        . "Updating wp-main-before-import..wp-import-branch-head\n"
        . "wp_posts | 2 +*\n"
        . "1 tables changed, 1 rows added(+), 1 rows modified(*), 0 rows deleted(-)\n"
        . "wp_import_audit added\n"
        . "wp_terms deleted",
    'expectedNoFfTranscript' => "Updating wp-main-before-import..wp-import-branch-head\n"
        . "wp_posts | 2 +*\n"
        . "1 tables changed, 1 rows added(+), 1 rows modified(*), 0 rows deleted(-)\n"
        . "wp_import_audit added\n"
        . "wp_terms deleted",
    'expectedFfOnlyFailure' => MergeStatusTable::MERGE_FF_ONLY_NOT_POSSIBLE_ERROR,
    'expectedFfOnlyNoFfError' => "error: Flags '--ff-only' and '--no-ff' cannot be used together",
    'expectedFfOnlySquashError' => "error: Flags '--ff-only' and '--squash' cannot be used together",
    'expectedAbortState' => [
        'output' => '',
        'merge_status' => [
            'is_merging' => false,
            'source' => null,
            'source_commit' => null,
            'target' => null,
            'unmerged_tables' => null,
        ],
        'preserved_working_tables' => ['wp_import_scratch'],
    ],
    'expectedFastForwardProcedureRow' => [
        'hash' => 'wp-import-branch-head',
        'fast_forward' => 1,
        'conflicts' => 0,
        'message' => MergeStatusTable::MERGE_SUCCESS_MESSAGE,
    ],
    'expectedNoFfProcedureRow' => [
        'hash' => 'wp-no-ff-merge-commit',
        'fast_forward' => 0,
        'conflicts' => 0,
        'message' => MergeStatusTable::MERGE_SUCCESS_MESSAGE,
    ],
    'expectedNoCommitProcedureRow' => [
        'hash' => '',
        'fast_forward' => 0,
        'conflicts' => 0,
        'message' => MergeStatusTable::MERGE_SUCCESS_MESSAGE,
    ],
    'expectedConflictProcedureRow' => [
        'hash' => '',
        'fast_forward' => 0,
        'conflicts' => 1,
        'message' => MergeStatusTable::MERGE_CONFLICTS_FOUND_MESSAGE,
    ],
    'expectedUpToDateProcedureRow' => [
        'hash' => '',
        'fast_forward' => 0,
        'conflicts' => 0,
        'message' => MergeStatusTable::MERGE_UP_TO_DATE_MESSAGE,
    ],
    'expectedAheadProcedureRow' => [
        'hash' => '',
        'fast_forward' => 0,
        'conflicts' => 0,
        'message' => MergeStatusTable::MERGE_AHEAD_MESSAGE,
    ],
    'expectedAbortProcedureRow' => [
        'hash' => '',
        'fast_forward' => 0,
        'conflicts' => 0,
        'message' => MergeStatusTable::MERGE_ABORTED_MESSAGE,
    ],
    'expectedSqlAutocommitConflictError' => MergeStatusTable::UNRESOLVED_CONFLICTS_AUTOCOMMIT_ERROR,
    'expectedSqlRollbackState' => [
        'error' => MergeStatusTable::UNRESOLVED_CONFLICTS_AUTOCOMMIT_ERROR,
        'rolled_back' => true,
        'merge_status' => [
            'is_merging' => false,
            'source' => null,
            'source_commit' => null,
            'target' => null,
            'unmerged_tables' => null,
        ],
        'conflict_rows' => [],
        'status_guidance' => null,
        'commit_guidance' => null,
        'merge_failure_summary' => null,
    ],
    'expectedSqlQueryableConflictState' => [
        'error' => MergeStatusTable::UNRESOLVED_CONFLICTS_TRANSACTION_ERROR,
        'rolled_back' => false,
        'merge_status' => [
            'is_merging' => true,
            'source' => 'migration/import-branch',
            'source_commit' => 'b2274926e0dcd84aab000ee242df5b5e75689eef',
            'target' => 'refs/heads/main',
            'unmerged_tables' => 'wp_posts, wp_postmeta, wp_import_audit, wp_options',
        ],
        'conflict_rows' => [
            ['table' => 'wp_posts', 'num_conflicts' => 2],
            ['table' => 'wp_options', 'num_conflicts' => 0],
            ['table' => 'wp_import_preview_view', 'num_conflicts' => 1],
            ['table' => 'wp_prepare_import_batch', 'num_conflicts' => 1],
        ],
        'status_guidance' => "You have unmerged tables.\n"
            . "  (fix conflicts and constraint violations and run \"dolt commit\")\n"
            . "  (use \"dolt merge --abort\" to abort the merge)\n\n"
            . "Unmerged paths:\n"
            . "  (use \"dolt add <table>...\" to mark resolution)\n"
            . "\tschema conflict:  wp_options\n"
            . "\tboth modified:    wp_posts\n"
            . "\tmodified          wp_import_audit\n"
            . "\tmodified          wp_postmeta",
        'commit_guidance' => "Unmerged paths:\n"
            . "  (use \"dolt add <table>...\" to mark resolution)\n"
            . "\tschema conflict:  wp_options\n"
            . "\tboth modified:    wp_posts\n"
            . "\tmodified          wp_import_audit\n"
            . "\tmodified          wp_postmeta",
        'merge_failure_summary' => "Automatic merge failed; 4 table(s) are unmerged.\n"
            . "Fix conflicts and constraint violations and then commit the result.\n"
            . "Use 'dolt conflicts' to investigate and resolve conflicts.",
    ],
    'expectedConstraintOnlyRollbackState' => [
        'error' => MergeStatusTable::UNRESOLVED_CONFLICTS_AUTOCOMMIT_ERROR,
        'rolled_back' => true,
        'merge_status' => [
            'is_merging' => false,
            'source' => null,
            'source_commit' => null,
            'target' => null,
            'unmerged_tables' => null,
        ],
        'conflict_rows' => [],
        'status_guidance' => null,
        'commit_guidance' => null,
        'merge_failure_summary' => null,
    ],
    'expectedConstraintOnlyQueryableState' => [
        'error' => MergeStatusTable::UNRESOLVED_CONFLICTS_TRANSACTION_ERROR,
        'rolled_back' => false,
        'merge_status' => [
            'is_merging' => true,
            'source' => 'migration/import-branch',
            'source_commit' => 'b2274926e0dcd84aab000ee242df5b5e75689eef',
            'target' => 'refs/heads/main',
            'unmerged_tables' => 'wp_postmeta, wp_import_audit',
        ],
        'conflict_rows' => [],
        'status_guidance' => "You have unmerged tables.\n"
            . "  (fix constraint violations and run \"dolt commit\")\n"
            . "  (use \"dolt merge --abort\" to abort the merge)\n\n"
            . "Unmerged paths:\n"
            . "  (use \"dolt add <table>...\" to mark resolution)\n"
            . "\tmodified          wp_import_audit\n"
            . "\tmodified          wp_postmeta",
        'commit_guidance' => "Unmerged paths:\n"
            . "  (use \"dolt add <table>...\" to mark resolution)\n"
            . "\tmodified          wp_import_audit\n"
            . "\tmodified          wp_postmeta",
        'merge_failure_summary' => "Automatic merge failed; 2 table(s) are unmerged.\n"
            . "Fix constraint violations and then commit the result.\n"
            . "Constraint violations for the working set may be viewed using the 'dolt_constraint_violations' system table.\n"
            . "They may be queried and removed per-table using the 'dolt_constraint_violations_TABLENAME' system table.",
    ],
    'expectedConstraintOnlyAllowedCommitState' => [
        'error' => null,
        'committed' => true,
        'merge_status' => [
            'is_merging' => false,
            'source' => null,
            'source_commit' => null,
            'target' => null,
            'unmerged_tables' => null,
        ],
        'conflict_rows' => [],
        'constraint_violation_tables' => ['wp_postmeta', 'wp_import_audit'],
        'post_commit_review_summary' => "Automatic merge failed; 2 table(s) are unmerged.\n"
            . "Fix constraint violations and then commit the result.\n"
            . "Constraint violations for the working set may be viewed using the 'dolt_constraint_violations' system table.\n"
            . "They may be queried and removed per-table using the 'dolt_constraint_violations_TABLENAME' system table.",
    ],
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

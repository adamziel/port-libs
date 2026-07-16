<?php

declare(strict_types=1);

use PortLibs\Dolt\PreviewMergeConflictsTable;

return [
    'preview merge conflicts summary matches upstream row shape' => static function (TestRunner $t): void {
        $table = new PreviewMergeConflictsTable();

        $rows = $table->summaryRows(
            [
                ['table' => 'test', 'num_data_conflicts' => 1],
                ['name' => 'test', 'numConflicts' => 2],
                ['table' => 'schema_and_data', 'count' => 4],
            ],
            [
                ['table' => 'schema_only', 'num_schema_conflicts' => 1],
                ['table' => 'schema_and_data', 'numSchemaConflicts' => 3],
            ],
        );

        $t->same([
            ['table' => 'test', 'num_data_conflicts' => 3, 'num_schema_conflicts' => 0],
            ['table' => 'schema_and_data', 'num_data_conflicts' => null, 'num_schema_conflicts' => 3],
            ['table' => 'schema_only', 'num_data_conflicts' => null, 'num_schema_conflicts' => 1],
        ], $rows);
    },
    'preview merge conflicts projects divergent keyed rows' => static function (TestRunner $t): void {
        $table = new PreviewMergeConflictsTable();

        $rows = $table->conflictRows(
            [
                ['pk' => 0, 'val' => 0],
                ['pk' => 2, 'val' => 'keep'],
                ['pk' => 3, 'val' => 'base'],
            ],
            [
                ['pk' => 0, 'val' => 1001],
                ['pk' => 1, 'val' => 2],
                ['pk' => 2, 'val' => 'ours only'],
            ],
            [
                ['pk' => 0, 'val' => 1000],
                ['pk' => 1, 'val' => 1],
                ['pk' => 2, 'val' => 'keep'],
                ['pk' => 3, 'val' => 'theirs'],
            ],
            'pk',
            ['pk', 'val'],
            'right-root-hash',
        );

        foreach ($rows as $row) {
            $t->same(22, strlen((string) $row['dolt_conflict_id']));
        }

        $withoutIds = array_map(static function (array $row): array {
            unset($row['dolt_conflict_id']);
            return $row;
        }, $rows);

        $t->same([
            [
                'from_root_ish' => 'right-root-hash',
                'base_pk' => 0,
                'base_val' => 0,
                'our_pk' => 0,
                'our_val' => 1001,
                'our_diff_type' => PreviewMergeConflictsTable::DIFF_MODIFIED,
                'their_pk' => 0,
                'their_val' => 1000,
                'their_diff_type' => PreviewMergeConflictsTable::DIFF_MODIFIED,
            ],
            [
                'from_root_ish' => 'right-root-hash',
                'base_pk' => null,
                'base_val' => null,
                'our_pk' => 1,
                'our_val' => 2,
                'our_diff_type' => PreviewMergeConflictsTable::DIFF_ADDED,
                'their_pk' => 1,
                'their_val' => 1,
                'their_diff_type' => PreviewMergeConflictsTable::DIFF_ADDED,
            ],
            [
                'from_root_ish' => 'right-root-hash',
                'base_pk' => 3,
                'base_val' => 'base',
                'our_pk' => null,
                'our_val' => null,
                'our_diff_type' => PreviewMergeConflictsTable::DIFF_REMOVED,
                'their_pk' => 3,
                'their_val' => 'theirs',
                'their_diff_type' => PreviewMergeConflictsTable::DIFF_MODIFIED,
            ],
        ], $withoutIds);
    },
    'preview merge conflicts skips non-conflicting branch changes' => static function (TestRunner $t): void {
        $table = new PreviewMergeConflictsTable();

        $rows = $table->conflictRows(
            [
                ['pk' => 1, 'val' => 'base'],
                ['pk' => 2, 'val' => 'base'],
            ],
            [
                ['pk' => 1, 'val' => 'same change'],
                ['pk' => 2, 'val' => 'ours only'],
                ['pk' => 3, 'val' => 'new same'],
            ],
            [
                ['pk' => 1, 'val' => 'same change'],
                ['pk' => 2, 'val' => 'base'],
                ['pk' => 3, 'val' => 'new same'],
            ],
            'pk',
            ['pk', 'val'],
        );

        $t->same([], $rows);
    },
    'preview merge conflicts projects keyless cardinality rows' => static function (TestRunner $t): void {
        $table = new PreviewMergeConflictsTable();

        $rows = $table->keylessConflictRows(
            [
                ['event' => 'scan', 'object_id' => 42, 'status' => 'queued'],
                ['event' => 'scan', 'object_id' => 42, 'status' => 'queued'],
                ['event' => 'delete', 'object_id' => 77, 'status' => 'old'],
            ],
            [
                ['event' => 'scan', 'object_id' => 42, 'status' => 'queued'],
                ['event' => 'scan', 'object_id' => 42, 'status' => 'queued'],
                ['event' => 'scan', 'object_id' => 42, 'status' => 'queued'],
            ],
            [
                ['event' => 'scan', 'object_id' => 42, 'status' => 'queued'],
                ['event' => 'delete', 'object_id' => 77, 'status' => 'old'],
                ['event' => 'delete', 'object_id' => 77, 'status' => 'old'],
            ],
            ['event', 'object_id', 'status'],
            'right-root-hash',
        );

        $t->same(2, count($rows));
        foreach ($rows as $row) {
            $t->same(32, strlen((string) $row['dolt_row_hash']));
            $t->same(22, strlen((string) $row['dolt_conflict_id']));
        }

        $withoutIds = array_map(static function (array $row): array {
            unset($row['dolt_row_hash'], $row['dolt_conflict_id']);
            return $row;
        }, $rows);

        $t->same([
            [
                'from_root_ish' => 'right-root-hash',
                'base_event' => 'delete',
                'base_object_id' => 77,
                'base_status' => 'old',
                'base_cardinality' => 1,
                'our_event' => null,
                'our_object_id' => null,
                'our_status' => null,
                'our_cardinality' => 0,
                'our_diff_type' => PreviewMergeConflictsTable::DIFF_REMOVED,
                'their_event' => 'delete',
                'their_object_id' => 77,
                'their_status' => 'old',
                'their_cardinality' => 2,
                'their_diff_type' => PreviewMergeConflictsTable::DIFF_MODIFIED,
            ],
            [
                'from_root_ish' => 'right-root-hash',
                'base_event' => 'scan',
                'base_object_id' => 42,
                'base_status' => 'queued',
                'base_cardinality' => 2,
                'our_event' => 'scan',
                'our_object_id' => 42,
                'our_status' => 'queued',
                'our_cardinality' => 3,
                'our_diff_type' => PreviewMergeConflictsTable::DIFF_MODIFIED,
                'their_event' => 'scan',
                'their_object_id' => 42,
                'their_status' => 'queued',
                'their_cardinality' => 1,
                'their_diff_type' => PreviewMergeConflictsTable::DIFF_MODIFIED,
            ],
        ], $withoutIds);
    },
    'preview merge conflicts maps upstream schema-conflict error boundary' => static function (TestRunner $t): void {
        $table = new PreviewMergeConflictsTable();

        $t->same('schema conflicts found: 2', $table->schemaConflictError(2));
        $t->throws(InvalidArgumentException::class, static fn () => $table->conflictRows(
            [['pk' => 1, 'val' => 'base']],
            [['pk' => 1, 'val' => 'ours']],
            [['pk' => 1, 'val' => 'theirs']],
            'pk',
            ['pk', 'val'],
            'right-root-hash',
            1,
        ));
        $t->throws(InvalidArgumentException::class, static fn () => $table->summaryRows(['']));
        $t->throws(InvalidArgumentException::class, static fn () => $table->conflictRows(
            [['pk' => 1]],
            [['pk' => 1]],
            [['pk' => 1]],
            'pk',
            [],
        ));
    },
    'schema conflict rows render upstream description strings' => static function (TestRunner $t): void {
        $table = new PreviewMergeConflictsTable();

        $baseSchema = "CREATE TABLE `wp_options` (\n  `option_id` bigint NOT NULL,\n  `option_name` varchar(191),\n  `autoload` varchar(20),\n  PRIMARY KEY (`option_id`)\n)";
        $ourSchema = "CREATE TABLE `wp_options` (\n  `option_id` bigint NOT NULL,\n  `option_name` varchar(191),\n  `autoload` varchar(20) DEFAULT 'yes',\n  PRIMARY KEY (`option_id`)\n)";
        $theirSchema = "CREATE TABLE `wp_options` (\n  `option_id` bigint NOT NULL,\n  `option_name` varchar(191),\n  `autoload` text,\n  PRIMARY KEY (`option_id`)\n)";

        $rows = $table->schemaConflictRows([
            [
                'table' => 'wp_options',
                'base_schema' => $baseSchema,
                'our_schema' => $ourSchema,
                'their_schema' => $theirSchema,
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
                'table_name' => 'wp_import_queue',
                'base_schema' => 'CREATE TABLE `wp_import_queue` (`id` int PRIMARY KEY)',
                'our_schema' => null,
                'their_schema' => 'CREATE TABLE `wp_import_queue` (`id` int PRIMARY KEY, `status` varchar(20))',
                'modify_delete_conflict' => true,
            ],
        ]);

        $t->same([
            [
                'table_name' => 'wp_options',
                'base_schema' => $baseSchema,
                'our_schema' => $ourSchema,
                'their_schema' => $theirSchema,
                'description' => "different column definitions for our column autoload and their column autoload\n"
                    . "two checks with the name 'wp_options_chk_autoload' but different definitions",
            ],
            [
                'table_name' => 'wp_import_queue',
                'base_schema' => 'CREATE TABLE `wp_import_queue` (`id` int PRIMARY KEY)',
                'our_schema' => '<deleted>',
                'their_schema' => 'CREATE TABLE `wp_import_queue` (`id` int PRIMARY KEY, `status` varchar(20))',
                'description' => 'table was modified in one branch and deleted in the other',
            ],
        ], $rows);

        $t->throws(InvalidArgumentException::class, static fn () => $table->schemaConflictRows([[
            'table' => 'wp_bad',
            'base_schema' => 'CREATE TABLE `wp_bad` (`id` int)',
            'our_schema' => 'CREATE TABLE `wp_bad` (`id` int)',
            'their_schema' => 'CREATE TABLE `wp_bad` (`id` int)',
        ]]));
    },
    'schema conflict rows render index and check collision descriptions' => static function (TestRunner $t): void {
        $table = new PreviewMergeConflictsTable();

        $rows = $table->schemaConflictRows([
            [
                'table' => 'wp_postmeta',
                'base_schema' => 'CREATE TABLE `wp_postmeta` (`meta_id` bigint PRIMARY KEY, `post_id` bigint, `meta_key` varchar(255))',
                'our_schema' => 'CREATE TABLE `wp_postmeta` (`meta_id` bigint PRIMARY KEY, `post_id` bigint, `meta_key` varchar(255), INDEX `idx_post_meta_key` (`post_id`,`meta_key`))',
                'their_schema' => 'CREATE TABLE `wp_postmeta` (`meta_id` bigint PRIMARY KEY, `post_id` bigint, `meta_key` varchar(255), INDEX `idx_meta_review` (`post_id`,`meta_key`))',
                'indexConflicts' => [
                    [
                        'kind' => 'duplicateIndexColumnSet',
                        'ours' => ['name' => 'idx_post_meta_key'],
                        'theirs' => ['name' => 'idx_meta_review'],
                    ],
                ],
            ],
            [
                'table' => 'wp_import_queue',
                'base_schema' => 'CREATE TABLE `wp_import_queue` (`id` int PRIMARY KEY, `status` varchar(20), `review_state` varchar(20), `legacy_flag` varchar(20))',
                'our_schema' => 'CREATE TABLE `wp_import_queue` (`id` int PRIMARY KEY, `status` varchar(20), `review_state` varchar(20), CHECK (`status` in (\'queued\',\'ready\')), CHECK (`review_state` <> \'\'), CHECK (`legacy_flag` <> \'\'))',
                'their_schema' => 'CREATE TABLE `wp_import_queue` (`id` int PRIMARY KEY, `status` varchar(20), `review_state` varchar(20), CHECK (`status` <> \'failed\'), CHECK (`review_state` in (\'open\',\'done\')))',
                'checkConflicts' => [
                    [
                        'kind' => 'columnCheckCollision',
                        'ours' => ['name' => 'chk_status_allowed'],
                        'theirs' => ['name' => 'chk_status_not_failed'],
                    ],
                    [
                        'kind' => 'invalidCheckCollision',
                        'ours' => ['name' => 'chk_legacy_flag'],
                        'theirs' => [],
                    ],
                    [
                        'kind' => 'deletedCheckCollision',
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
        ]);

        $t->same([
            [
                'table_name' => 'wp_postmeta',
                'base_schema' => 'CREATE TABLE `wp_postmeta` (`meta_id` bigint PRIMARY KEY, `post_id` bigint, `meta_key` varchar(255))',
                'our_schema' => 'CREATE TABLE `wp_postmeta` (`meta_id` bigint PRIMARY KEY, `post_id` bigint, `meta_key` varchar(255), INDEX `idx_post_meta_key` (`post_id`,`meta_key`))',
                'their_schema' => 'CREATE TABLE `wp_postmeta` (`meta_id` bigint PRIMARY KEY, `post_id` bigint, `meta_key` varchar(255), INDEX `idx_meta_review` (`post_id`,`meta_key`))',
                'description' => "multiple indexes covering the same column set cannot be merged: 'idx_post_meta_key' and 'idx_meta_review'",
            ],
            [
                'table_name' => 'wp_import_queue',
                'base_schema' => 'CREATE TABLE `wp_import_queue` (`id` int PRIMARY KEY, `status` varchar(20), `review_state` varchar(20), `legacy_flag` varchar(20))',
                'our_schema' => 'CREATE TABLE `wp_import_queue` (`id` int PRIMARY KEY, `status` varchar(20), `review_state` varchar(20), CHECK (`status` in (\'queued\',\'ready\')), CHECK (`review_state` <> \'\'), CHECK (`legacy_flag` <> \'\'))',
                'their_schema' => 'CREATE TABLE `wp_import_queue` (`id` int PRIMARY KEY, `status` varchar(20), `review_state` varchar(20), CHECK (`status` <> \'failed\'), CHECK (`review_state` in (\'open\',\'done\')))',
                'description' => "our check 'chk_status_allowed' and their check 'chk_status_not_failed' both reference the same column(s)\n"
                    . "check 'chk_legacy_flag' references a column that will be deleted after merge\n"
                    . "check 'chk_review_state_present' was deleted in theirs but modified in ours\n"
                    . "check 'chk_review_state_values' was deleted in ours but modified in theirs",
            ],
        ], $rows);
    },
];

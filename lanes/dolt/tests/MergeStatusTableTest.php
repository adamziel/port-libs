<?php

declare(strict_types=1);

use PortLibs\Dolt\MergeStatusTable;
use PortLibs\Dolt\ConstraintViolationsTable;
use PortLibs\Dolt\PreviewMergeConflictsTable;

return [
    'dolt merge status emits inactive row with null merge metadata' => static function (TestRunner $t): void {
        $row = (new MergeStatusTable())->statusRow(false, 'ignored', 'ignored', 'ignored', ['ignored']);

        $t->same([
            'is_merging' => false,
            'source' => null,
            'source_commit' => null,
            'target' => null,
            'unmerged_tables' => null,
        ], $row);
    },
    'dolt merge status emits active source target and unmerged table set' => static function (TestRunner $t): void {
        $row = (new MergeStatusTable())->statusRow(
            true,
            'feature_branch',
            'abc123',
            'refs/heads/main',
            ['test1', 'test2'],
            ['test3'],
            ['test2', 'test4'],
        );

        $t->same(true, $row['is_merging']);
        $t->same('feature_branch', $row['source']);
        $t->same('abc123', $row['source_commit']);
        $t->same('refs/heads/main', $row['target']);
        $t->same('test1, test2, test3, test4', $row['unmerged_tables']);
    },
    'dolt conflicts table projects table names and conflict counts' => static function (TestRunner $t): void {
        $rows = (new MergeStatusTable())->conflictRows(
            [
                ['name' => 'test1', 'numConflicts' => 3],
                ['name' => 'test1', 'numConflicts' => 1],
            ],
            ['test_schema_only'],
            [
                ['name' => 'stored_procedure_conflict', 'numConflicts' => 2],
            ],
        );

        $t->same([
            ['table' => 'test1', 'num_conflicts' => 3],
            ['table' => 'test_schema_only', 'num_conflicts' => 0],
            ['table' => 'stored_procedure_conflict', 'num_conflicts' => 2],
        ], $rows);
    },
    'dolt add schema conflict resolution clears commit-blocking schema paths' => static function (TestRunner $t): void {
        $table = new MergeStatusTable();

        $partial = $table->resolveSchemaConflicts(
            [
                ['name' => 'wp_options'],
                ['name' => 'wp_postmeta'],
            ],
            ['wp_options'],
        );
        $t->same([
            'remaining_schema_conflicts' => [
                ['table' => 'wp_postmeta', 'num_conflicts' => 0],
            ],
            'status_guidance' => "You have unmerged tables.\n"
                . "  (fix conflicts and run \"dolt commit\")\n"
                . "  (use \"dolt merge --abort\" to abort the merge)\n\n"
                . "Unmerged paths:\n"
                . "  (use \"dolt add <table>...\" to mark resolution)\n"
                . "\tschema conflict:  wp_postmeta",
            'commit_guidance' => "Unmerged paths:\n"
                . "  (use \"dolt add <table>...\" to mark resolution)\n"
                . "\tschema conflict:  wp_postmeta",
        ], $partial);

        $resolved = $table->resolveSchemaConflicts(
            [
                ['name' => 'wp_options'],
                ['name' => 'wp_postmeta'],
            ],
            ['wp_options', 'wp_postmeta'],
        );
        $t->same([
            'remaining_schema_conflicts' => [],
            'status_guidance' => MergeStatusTable::ALL_MERGED_HEADER,
            'commit_guidance' => null,
        ], $resolved);
        $t->throws(InvalidArgumentException::class, static fn () => $table->resolveSchemaConflicts(['wp_options'], ['']));
    },
    'dolt conflicts resolve chooses schema side and clears selected table' => static function (TestRunner $t): void {
        $table = new MergeStatusTable();
        $conflicts = [
            [
                'table_name' => 'wp_options',
                'our_schema' => "CREATE TABLE `wp_options` (`autoload` varchar(20))",
                'their_schema' => "CREATE TABLE `wp_options` (`autoload` text)",
            ],
            [
                'table_name' => 'wp_postmeta',
                'our_schema' => "CREATE TABLE `wp_postmeta` (`meta_key` varchar(255), KEY `idx_post_meta_key` (`meta_key`))",
                'their_schema' => "CREATE TABLE `wp_postmeta` (`meta_key` varchar(255), KEY `idx_meta_review` (`meta_key`))",
            ],
        ];

        $ours = $table->resolveSchemaConflictSide($conflicts, 'wp_options', 'ours');
        $t->same([
            'table' => 'wp_options',
            'resolution' => 'ours',
            'selected_schema' => "CREATE TABLE `wp_options` (`autoload` varchar(20))",
            'remaining_schema_conflicts' => [
                ['table' => 'wp_postmeta', 'num_conflicts' => 0],
            ],
            'status_guidance' => "You have unmerged tables.\n"
                . "  (fix conflicts and run \"dolt commit\")\n"
                . "  (use \"dolt merge --abort\" to abort the merge)\n\n"
                . "Unmerged paths:\n"
                . "  (use \"dolt add <table>...\" to mark resolution)\n"
                . "\tschema conflict:  wp_postmeta",
            'commit_guidance' => "Unmerged paths:\n"
                . "  (use \"dolt add <table>...\" to mark resolution)\n"
                . "\tschema conflict:  wp_postmeta",
        ], $ours);

        $theirs = $table->resolveSchemaConflictSide($conflicts, 'wp_postmeta', 'theirs');
        $t->same("CREATE TABLE `wp_postmeta` (`meta_key` varchar(255), KEY `idx_meta_review` (`meta_key`))", $theirs['selected_schema']);
        $t->same([
            ['table' => 'wp_options', 'num_conflicts' => 0],
        ], $theirs['remaining_schema_conflicts']);

        $t->throws(InvalidArgumentException::class, static fn () => $table->resolveSchemaConflictSide($conflicts, 'wp_options', 'base'));
        $t->throws(InvalidArgumentException::class, static fn () => $table->resolveSchemaConflictSide($conflicts, 'wp_terms', 'ours'));
    },
    'dolt add root object conflict resolution clears conflict rows' => static function (TestRunner $t): void {
        $table = new MergeStatusTable();

        $partial = $table->resolveRootObjectConflicts(
            [
                ['name' => 'wp_import_preview_view', 'numConflicts' => 1],
                ['name' => 'wp_prepare_import_batch', 'numConflicts' => 1],
            ],
            ['wp_import_preview_view'],
        );
        $t->same([
            'remaining_root_object_conflicts' => [
                ['table' => 'wp_prepare_import_batch', 'num_conflicts' => 1],
            ],
            'conflict_rows' => [
                ['table' => 'wp_prepare_import_batch', 'num_conflicts' => 1],
            ],
            'merge_failure_summary' => "Automatic merge failed; 0 table(s) are unmerged.\n"
                . MergeStatusTable::MERGE_CONFLICTS_HELP,
        ], $partial);

        $resolved = $table->resolveRootObjectConflicts(
            [
                ['name' => 'wp_import_preview_view', 'numConflicts' => 1],
                ['name' => 'wp_prepare_import_batch', 'numConflicts' => 1],
            ],
            ['wp_import_preview_view', 'wp_prepare_import_batch'],
        );
        $t->same([
            'remaining_root_object_conflicts' => [],
            'conflict_rows' => [],
            'merge_failure_summary' => null,
        ], $resolved);
        $t->throws(InvalidArgumentException::class, static fn () => $table->resolveRootObjectConflicts(['wp_import_preview_view'], ['']));
    },
    'partial merge artifact resolution keeps only unresolved blockers visible' => static function (TestRunner $t): void {
        $state = (new MergeStatusTable())->resolveMergeArtifacts(
            [
                ['name' => 'wp_posts', 'numConflicts' => 2],
                ['name' => 'wp_terms', 'numConflicts' => 1],
            ],
            [
                ['name' => 'wp_options'],
                ['name' => 'wp_postmeta'],
            ],
            ['wp_posts', 'wp_postmeta', 'wp_import_audit'],
            [
                ['name' => 'wp_import_preview_view', 'numConflicts' => 1],
            ],
            [
                'data' => ['wp_terms'],
                'schema' => ['wp_options'],
                'constraints' => ['wp_posts'],
                'rootObjects' => ['wp_import_preview_view'],
            ],
        );

        $t->same([
            'remaining_data_conflicts' => [
                ['table' => 'wp_posts', 'num_conflicts' => 2],
            ],
            'remaining_schema_conflicts' => [
                ['table' => 'wp_postmeta', 'num_conflicts' => 0],
            ],
            'remaining_constraint_violations' => ['wp_postmeta', 'wp_import_audit'],
            'remaining_root_object_conflicts' => [],
            'conflict_rows' => [
                ['table' => 'wp_posts', 'num_conflicts' => 2],
                ['table' => 'wp_postmeta', 'num_conflicts' => 0],
            ],
            'status_guidance' => "You have unmerged tables.\n"
                . "  (fix conflicts and constraint violations and run \"dolt commit\")\n"
                . "  (use \"dolt merge --abort\" to abort the merge)\n\n"
                . "Unmerged paths:\n"
                . "  (use \"dolt add <table>...\" to mark resolution)\n"
                . "\tschema conflict:  wp_postmeta\n"
                . "\tboth modified:    wp_posts\n"
                . "\tmodified          wp_import_audit",
            'commit_guidance' => "Unmerged paths:\n"
                . "  (use \"dolt add <table>...\" to mark resolution)\n"
                . "\tschema conflict:  wp_postmeta\n"
                . "\tboth modified:    wp_posts\n"
                . "\tmodified          wp_import_audit",
            'merge_failure_summary' => "Automatic merge failed; 3 table(s) are unmerged.\n"
                . "Fix conflicts and constraint violations and then commit the result.\n"
                . MergeStatusTable::MERGE_CONFLICTS_HELP,
        ], $state);

        $resolved = (new MergeStatusTable())->resolveMergeArtifacts(
            [['name' => 'wp_posts', 'numConflicts' => 2]],
            [['name' => 'wp_options']],
            ['wp_postmeta'],
            [['name' => 'wp_import_preview_view', 'numConflicts' => 1]],
            [
                'data' => ['wp_posts'],
                'schema' => ['wp_options'],
                'constraints' => ['wp_postmeta'],
                'rootObjects' => ['wp_import_preview_view'],
            ],
        );
        $t->same(MergeStatusTable::ALL_MERGED_HEADER, $resolved['status_guidance']);
        $t->same(null, $resolved['commit_guidance']);
        $t->same(null, $resolved['merge_failure_summary']);
        $t->throws(InvalidArgumentException::class, static fn () => (new MergeStatusTable())->resolveMergeArtifacts([], [], [''], [], []));
    },
    'dolt status guidance maps unresolved constraint violation merge text' => static function (TestRunner $t): void {
        $guidance = (new MergeStatusTable())->statusGuidance(
            true,
            ['wp_posts'],
            ['wp_options'],
            ['wp_postmeta', 'wp_import_audit', 'wp_posts'],
        );

        $t->same(
            "You have unmerged tables.\n"
                . "  (fix conflicts and constraint violations and run \"dolt commit\")\n"
                . "  (use \"dolt merge --abort\" to abort the merge)\n\n"
                . "Unmerged paths:\n"
                . "  (use \"dolt add <table>...\" to mark resolution)\n"
                . "\tschema conflict:  wp_options\n"
                . "\tboth modified:    wp_posts\n"
                . "\tmodified          wp_import_audit\n"
                . "\tmodified          wp_postmeta",
            $guidance
        );
    },
    'dolt status guidance reports fixed merge while commit unresolved block is absent' => static function (TestRunner $t): void {
        $table = new MergeStatusTable();

        $t->same(MergeStatusTable::ALL_MERGED_HEADER, $table->statusGuidance(true));
        $t->same(null, $table->statusGuidance(false, ['ignored'], [], ['ignored']));
        $t->same(null, $table->commitUnmergedPaths());
    },
    'dolt commit unresolved block prints constraint-only tables as modified' => static function (TestRunner $t): void {
        $block = (new MergeStatusTable())->commitUnmergedPaths(
            [['name' => 'wp_posts', 'numConflicts' => 2]],
            [['name' => 'wp_options']],
            ['wp_postmeta', 'wp_options', 'wp_import_audit'],
        );

        $t->same(
            "Unmerged paths:\n"
                . "  (use \"dolt add <table>...\" to mark resolution)\n"
                . "\tschema conflict:  wp_options\n"
                . "\tboth modified:    wp_posts\n"
                . "\tmodified          wp_import_audit\n"
                . "\tmodified          wp_postmeta",
            $block
        );
    },
    'dolt merge failure summary maps upstream conflict and violation hints' => static function (TestRunner $t): void {
        $table = new MergeStatusTable();

        $t->same(
            "Automatic merge failed; 1 table(s) are unmerged.\n"
                . "Use 'dolt conflicts' to investigate and resolve conflicts.",
            $table->mergeFailureSummary([['name' => 'parent', 'numConflicts' => 1]])
        );
        $t->same(
            "Automatic merge failed; 1 table(s) are unmerged.\n"
                . "Fix constraint violations and then commit the result.\n"
                . "Constraint violations for the working set may be viewed using the 'dolt_constraint_violations' system table.\n"
                . "They may be queried and removed per-table using the 'dolt_constraint_violations_TABLENAME' system table.",
            $table->mergeFailureSummary([], [], ['child'])
        );
        $t->same(
            "Automatic merge failed; 2 table(s) are unmerged.\n"
                . "Fix conflicts and constraint violations and then commit the result.\n"
                . "Use 'dolt conflicts' to investigate and resolve conflicts.",
            $table->mergeFailureSummary([['name' => 'parent', 'numConflicts' => 1]], [], ['child'])
        );
        $t->same(
            "Automatic merge failed; 1 table(s) are unmerged.\n"
                . "Fix conflicts and constraint violations and then commit the result.\n"
                . "Use 'dolt conflicts' to investigate and resolve conflicts.",
            $table->mergeFailureSummary([['name' => 'parent', 'numConflicts' => 1]], [], ['parent'])
        );
        $t->same(null, $table->mergeFailureSummary());
    },
    'dolt merge artifact prelude and success stats match upstream cli shape' => static function (TestRunner $t): void {
        $table = new MergeStatusTable();

        $t->same(
            "Auto-merging parent\n"
                . "CONFLICT (content): Merge conflict in parent\n"
                . "CONSTRAINT VIOLATION (content): Merge created constraint violation in parent\n"
                . "Auto-merging child\n"
                . "CONFLICT (schema): Merge conflict in child\n"
                . "Auto-merging audit_view\n"
                . "CONFLICT (content): Merge conflict in audit_view",
            $table->mergeArtifactPrelude(
                [['name' => 'parent', 'numConflicts' => 2]],
                ['child'],
                ['parent'],
                [['name' => 'audit_view', 'numConflicts' => 1]],
            )
        );
        $t->same(null, $table->mergeArtifactPrelude());

        $t->same(
            "wp_options | 31 +++++++++++++++++++++++++++++-\n"
                . "wp_posts   | 2  +*\n"
                . "2 tables changed, 31 rows added(+), 1 rows modified(*), 1 rows deleted(-)\n"
                . "wp_import_audit added\n"
                . "wp_terms deleted",
            $table->mergeSuccessStats([
                [
                    'table' => 'wp_posts',
                    'operation' => 'modified',
                    'rows_added' => 1,
                    'rows_modified' => 1,
                ],
                [
                    'table' => 'wp_import_audit',
                    'operation' => 'added',
                ],
                [
                    'table' => 'wp_options',
                    'operation' => 'modified',
                    'rows_added' => 30,
                    'rows_deleted' => 1,
                ],
                [
                    'table' => 'wp_terms',
                    'operation' => 'deleted',
                ],
                [
                    'table' => 'wp_block_conflicts',
                    'operation' => 'modified',
                    'rows_modified' => 5,
                    'data_conflicts' => 1,
                ],
            ])
        );
        $t->same(null, $table->mergeSuccessStats([[
            'table' => 'wp_noop',
            'operation' => 'unmodified',
        ]]));
        $t->throws(InvalidArgumentException::class, static fn () => $table->mergeSuccessStats([[
            'table' => 'wp_bad',
            'operation' => 'modified',
            'rows_added' => -1,
        ]]));
    },
    'dolt merge success transcript maps up to date squash no commit and abort boundaries' => static function (TestRunner $t): void {
        $table = new MergeStatusTable();

        $t->same(MergeStatusTable::MERGE_UP_TO_DATE_MESSAGE, $table->mergeSuccessTranscript([], [
            'upToDate' => true,
            'squash' => true,
            'noCommit' => true,
        ]));
        $t->same(MergeStatusTable::MERGE_UP_TO_DATE_MESSAGE, $table->mergeCliTranscript([], [
            'ffOnly' => true,
            'upToDate' => true,
        ]));

        $t->same(
            "Updating headabc..mergeabc\n"
                . "Automatic merge went well; stopped before committing as requested\n"
                . "wp_posts | 1 +\n"
                . "1 tables changed, 1 rows added(+), 0 rows modified(*), 0 rows deleted(-)",
            $table->mergeSuccessTranscript([
                ['table' => 'wp_posts', 'operation' => 'modified', 'rows_added' => 1],
            ], [
                'headHash' => 'headabc',
                'mergeHash' => 'mergeabc',
                'noCommit' => true,
            ])
        );

        $t->same(
            "Updating headabc..mergeabc\n"
                . "Squash commit -- not updating HEAD\n"
                . "Automatic merge went well; stopped before committing as requested\n"
                . "wp_posts | 1 +\n"
                . "1 tables changed, 1 rows added(+), 0 rows modified(*), 0 rows deleted(-)",
            $table->mergeSuccessTranscript([
                ['table' => 'wp_posts', 'operation' => 'modified', 'rows_added' => 1],
            ], [
                'headHash' => 'headabc',
                'mergeHash' => 'mergeabc',
                'squash' => true,
                'noCommit' => true,
            ])
        );

        $t->same(
            "Fast-forward\n"
                . "Updating headabc..mergeabc\n"
                . "wp_posts | 1 +\n"
                . "1 tables changed, 1 rows added(+), 0 rows modified(*), 0 rows deleted(-)",
            $table->mergeCliTranscript([
                ['table' => 'wp_posts', 'operation' => 'modified', 'rows_added' => 1],
            ], [
                'ffOnly' => true,
                'canFastForward' => true,
                'headHash' => 'headabc',
                'mergeHash' => 'mergeabc',
            ])
        );
        $t->same(
            "Updating headabc..mergeabc\n"
                . "wp_posts | 1 +\n"
                . "1 tables changed, 1 rows added(+), 0 rows modified(*), 0 rows deleted(-)",
            $table->mergeCliTranscript([
                ['table' => 'wp_posts', 'operation' => 'modified', 'rows_added' => 1],
            ], [
                'noFf' => true,
                'headHash' => 'headabc',
                'mergeHash' => 'mergeabc',
            ])
        );
        $t->same(MergeStatusTable::MERGE_FF_ONLY_NOT_POSSIBLE_ERROR, $table->mergeCliTranscript([], [
            'ffOnly' => true,
            'canFastForward' => false,
        ]));
        $t->same("error: Flags '--ff-only' and '--no-ff' cannot be used together", $table->mergeCliTranscript([], [
            'ffOnly' => true,
            'noFf' => true,
        ]));
        $t->same("error: Flags '--ff-only' and '--squash' cannot be used together", $table->mergeFlagError([
            'ffOnly' => true,
            'squash' => true,
        ]));
        $t->same("error: Flags '--squash' and '--no-ff' cannot be used together", $table->mergeFlagError([
            'squash' => true,
            'noFf' => true,
        ]));
        $t->same("error: Flags '--commit' and '--no-commit' cannot be used together", $table->mergeFlagError([
            'commit' => true,
            'noCommit' => true,
        ]));
        $t->same(null, $table->mergeFlagError([
            'ffOnly' => true,
            'canFastForward' => true,
        ]));

        $abort = $table->abortMergeState(['wp_import_scratch']);
        $t->same('', $abort['output']);
        $t->same([
            'is_merging' => false,
            'source' => null,
            'source_commit' => null,
            'target' => null,
            'unmerged_tables' => null,
        ], $abort['merge_status']);
        $t->same(['wp_import_scratch'], $abort['preserved_working_tables']);
        $t->throws(InvalidArgumentException::class, static fn () => $table->abortMergeState([], false));
        $t->throws(InvalidArgumentException::class, static fn () => $table->mergeSuccessTranscript([], ['headHash' => 'headabc']));
        $t->throws(InvalidArgumentException::class, static fn () => $table->mergeSuccessTranscript([], ['noCommit' => 'yes']));
        $t->throws(InvalidArgumentException::class, static fn () => $table->mergeSuccessTranscript([], ['ffOnly' => true, 'noFf' => true]));
        $t->throws(InvalidArgumentException::class, static fn () => $table->mergeSuccessTranscript([], ['noFf' => true, 'fastForward' => true]));
    },
    'call dolt_merge result row maps upstream procedure schema' => static function (TestRunner $t): void {
        $table = new MergeStatusTable();

        $t->same([
            'hash' => 'featurehash000000000000000000000',
            'fast_forward' => 1,
            'conflicts' => 0,
            'message' => MergeStatusTable::MERGE_SUCCESS_MESSAGE,
        ], $table->mergeProcedureRow([
            'commitHash' => 'featurehash000000000000000000000',
            'fastForward' => true,
        ]));
        $t->same([
            'hash' => 'mergehash0000000000000000000000',
            'fast_forward' => 0,
            'conflicts' => 0,
            'message' => MergeStatusTable::MERGE_SUCCESS_MESSAGE,
        ], $table->mergeProcedureRow([
            'commitHash' => 'mergehash0000000000000000000000',
            'noFf' => true,
        ]));
        $t->same([
            'hash' => '',
            'fast_forward' => 0,
            'conflicts' => 0,
            'message' => MergeStatusTable::MERGE_SUCCESS_MESSAGE,
        ], $table->mergeProcedureRow([
            'noCommit' => true,
        ]));
        $t->same([
            'hash' => 'featurehash000000000000000000000',
            'fast_forward' => 1,
            'conflicts' => 0,
            'message' => MergeStatusTable::MERGE_SUCCESS_MESSAGE,
        ], $table->mergeProcedureRow([
            'commitHash' => 'featurehash000000000000000000000',
            'ffOnly' => true,
            'noCommit' => true,
            'canFastForward' => true,
        ]));
        $t->same([
            'hash' => '',
            'fast_forward' => 0,
            'conflicts' => 1,
            'message' => MergeStatusTable::MERGE_CONFLICTS_FOUND_MESSAGE,
        ], $table->mergeProcedureRow([
            'hasConflicts' => true,
        ]));
        $t->same([
            'hash' => '',
            'fast_forward' => 0,
            'conflicts' => 0,
            'message' => MergeStatusTable::MERGE_UP_TO_DATE_MESSAGE,
        ], $table->mergeProcedureRow([
            'upToDate' => true,
        ]));
        $t->same([
            'hash' => '',
            'fast_forward' => 0,
            'conflicts' => 0,
            'message' => MergeStatusTable::MERGE_AHEAD_MESSAGE,
        ], $table->mergeProcedureRow([
            'ahead' => true,
        ]));
        $t->same([
            'hash' => '',
            'fast_forward' => 0,
            'conflicts' => 0,
            'message' => MergeStatusTable::MERGE_ABORTED_MESSAGE,
        ], $table->mergeProcedureRow([
            'abort' => true,
        ]));

        $t->throws(InvalidArgumentException::class, static fn () => $table->mergeProcedureRow([
            'ffOnly' => true,
            'canFastForward' => false,
        ]));
        $t->throws(InvalidArgumentException::class, static fn () => $table->mergeProcedureRow([
            'commit' => true,
            'noCommit' => true,
        ]));
        $t->throws(InvalidArgumentException::class, static fn () => $table->mergeProcedureRow([
            'noFf' => true,
            'fastForward' => true,
            'commitHash' => 'featurehash000000000000000000000',
        ]));
        $t->throws(InvalidArgumentException::class, static fn () => $table->mergeProcedureRow([
            'conflicts' => 2,
        ]));
        $t->throws(InvalidArgumentException::class, static fn () => $table->mergeProcedureRow([
            'noCommit' => true,
            'commitHash' => 'mergehash0000000000000000000000',
        ]));
    },
    'sql merge transaction conflict errors follow autocommit and allow-conflict gates' => static function (TestRunner $t): void {
        $table = new MergeStatusTable();

        $t->same(
            MergeStatusTable::UNRESOLVED_CONFLICTS_AUTOCOMMIT_ERROR,
            $table->mergeTransactionConflictError(true, true)
        );
        $t->same(
            MergeStatusTable::UNRESOLVED_CONFLICTS_TRANSACTION_ERROR,
            $table->mergeTransactionConflictError(true, false)
        );
        $t->same(null, $table->mergeTransactionConflictError(true, true, true));
        $t->same(null, $table->mergeTransactionConflictError(false, true));
        $t->contains('dolt_conflicts and dolt_schema_conflicts', $table->mergeTransactionConflictError(true, false));
        $t->contains('@@dolt_allow_commit_conflicts = 1', $table->mergeTransactionConflictError(true, true));
    },
    'sql merge rollback state clears artifacts only for autocommit rollback' => static function (TestRunner $t): void {
        $table = new MergeStatusTable();
        $conflicts = [['name' => 'wp_posts', 'numConflicts' => 2]];
        $schemaConflicts = ['wp_options'];
        $constraintTables = ['wp_posts', 'wp_import_audit'];

        $rolledBack = $table->mergeRollbackState($conflicts, $schemaConflicts, $constraintTables, [], [
            'source' => 'migration/import-branch',
            'sourceCommit' => 'featurehash',
            'target' => 'refs/heads/main',
            'autocommit' => true,
        ]);
        $t->same(MergeStatusTable::UNRESOLVED_CONFLICTS_AUTOCOMMIT_ERROR, $rolledBack['error']);
        $t->same(true, $rolledBack['rolled_back']);
        $t->same(false, $rolledBack['merge_status']['is_merging']);
        $t->same([], $rolledBack['conflict_rows']);
        $t->same(null, $rolledBack['status_guidance']);
        $t->same(null, $rolledBack['merge_failure_summary']);

        $queryable = $table->mergeRollbackState($conflicts, $schemaConflicts, $constraintTables, [], [
            'source' => 'migration/import-branch',
            'sourceCommit' => 'featurehash',
            'target' => 'refs/heads/main',
            'autocommit' => false,
        ]);
        $t->same(MergeStatusTable::UNRESOLVED_CONFLICTS_TRANSACTION_ERROR, $queryable['error']);
        $t->same(false, $queryable['rolled_back']);
        $t->same(true, $queryable['merge_status']['is_merging']);
        $t->same('wp_posts, wp_import_audit, wp_options', $queryable['merge_status']['unmerged_tables']);
        $t->same([
            ['table' => 'wp_posts', 'num_conflicts' => 2],
            ['table' => 'wp_options', 'num_conflicts' => 0],
        ], $queryable['conflict_rows']);
        $t->contains('wp_import_audit', $queryable['status_guidance']);

        $allowed = $table->mergeRollbackState($conflicts, [], [], [], [
            'source' => 'migration/import-branch',
            'sourceCommit' => 'featurehash',
            'target' => 'refs/heads/main',
            'autocommit' => true,
            'allowCommitConflicts' => true,
        ]);
        $t->same(null, $allowed['error']);
        $t->same(false, $allowed['rolled_back']);
        $t->same(true, $allowed['merge_status']['is_merging']);
    },
    'sql merge rollback state handles constraint-only violations without conflict rows' => static function (TestRunner $t): void {
        $table = new MergeStatusTable();
        $constraintTables = ['wp_postmeta', 'wp_import_audit'];

        $rolledBack = $table->mergeRollbackState([], [], $constraintTables, [], [
            'source' => 'migration/import-branch',
            'sourceCommit' => 'featurehash',
            'target' => 'refs/heads/main',
            'autocommit' => true,
        ]);
        $t->same(MergeStatusTable::UNRESOLVED_CONFLICTS_AUTOCOMMIT_ERROR, $rolledBack['error']);
        $t->same(true, $rolledBack['rolled_back']);
        $t->same(false, $rolledBack['merge_status']['is_merging']);
        $t->same([], $rolledBack['conflict_rows']);
        $t->same(null, $rolledBack['status_guidance']);
        $t->same(null, $rolledBack['commit_guidance']);

        $queryable = $table->mergeRollbackState([], [], $constraintTables, [], [
            'source' => 'migration/import-branch',
            'sourceCommit' => 'featurehash',
            'target' => 'refs/heads/main',
            'autocommit' => false,
        ]);
        $t->same(MergeStatusTable::UNRESOLVED_CONFLICTS_TRANSACTION_ERROR, $queryable['error']);
        $t->same(false, $queryable['rolled_back']);
        $t->same(true, $queryable['merge_status']['is_merging']);
        $t->same('wp_postmeta, wp_import_audit', $queryable['merge_status']['unmerged_tables']);
        $t->same([], $queryable['conflict_rows']);
        $t->contains('fix constraint violations', $queryable['status_guidance']);
        $t->contains('dolt_constraint_violations', $queryable['merge_failure_summary']);
    },
    'sql merge allow commit keeps artifacts reviewable after constraint-only commit' => static function (TestRunner $t): void {
        $table = new MergeStatusTable();

        $state = $table->mergeAllowedCommitState([], [], ['wp_postmeta', 'wp_import_audit']);
        $t->same(null, $state['error']);
        $t->same(true, $state['committed']);
        $t->same(false, $state['merge_status']['is_merging']);
        $t->same([], $state['conflict_rows']);
        $t->same(['wp_postmeta', 'wp_import_audit'], $state['constraint_violation_tables']);
        $t->contains('Automatic merge failed; 2 table(s) are unmerged.', $state['post_commit_review_summary']);
        $t->contains('dolt_constraint_violations', $state['post_commit_review_summary']);

        $withDataConflict = $table->mergeAllowedCommitState([['name' => 'wp_posts', 'numConflicts' => 2]], [], ['wp_postmeta']);
        $t->same([
            ['table' => 'wp_posts', 'num_conflicts' => 2],
        ], $withDataConflict['conflict_rows']);
        $t->same(['wp_postmeta'], $withDataConflict['constraint_violation_tables']);
        $t->contains("Use 'dolt conflicts'", $withDataConflict['post_commit_review_summary']);

        $clean = $table->mergeAllowedCommitState();
        $t->same(false, $clean['committed']);
        $t->same(null, $clean['post_commit_review_summary']);
    },
    'dolt merge status validates active merge fields and conflict counts' => static function (TestRunner $t): void {
        $table = new MergeStatusTable();

        $t->throws(InvalidArgumentException::class, static fn () => $table->statusRow(true, '', 'abc123', 'refs/heads/main'));
        $t->throws(InvalidArgumentException::class, static fn () => $table->statusRow(true, 'feature', 'abc123', 'refs/heads/main', ['']));
        $t->throws(InvalidArgumentException::class, static fn () => $table->conflictRows([['name' => 'test1', 'numConflicts' => -1]]));
        $t->throws(InvalidArgumentException::class, static fn () => $table->rootObjectConflictRows([[
            'object_type' => 'view',
            'name' => '',
            'base_definition' => 'CREATE VIEW broken AS SELECT 1',
        ]]));
    },
    'wordpress merge status fixture surfaces unresolved migration tables' => static function (TestRunner $t): void {
        $fixture = require __DIR__ . '/../fixtures/wp-merge-review.php';
        $table = new MergeStatusTable();
        $previewConflicts = new PreviewMergeConflictsTable();

        $mergeStatus = $table->statusRow(
            $fixture['isMerging'],
            $fixture['source'],
            $fixture['sourceCommit'],
            $fixture['target'],
            $fixture['dataConflictTables'],
            $fixture['constraintViolationTables'],
            $fixture['schemaConflictTables'],
        );
        $conflictRows = $table->conflictRows(
            $fixture['conflictTables'],
            $fixture['schemaConflictRows'],
            $fixture['rootObjectConflicts'],
        );
        $rootObjectConflictRows = $table->rootObjectConflictRows(
            $fixture['rootObjectConflictDetails'],
        );
        $previewConflictSummaryRows = $previewConflicts->summaryRows(
            $fixture['previewDataConflictTables'],
            $fixture['previewSchemaConflictTables'],
        );
        $previewConflictRows = $previewConflicts->conflictRows(
            $fixture['previewMergeBaseRows'],
            $fixture['previewMergeOurRows'],
            $fixture['previewMergeTheirRows'],
            $fixture['previewMergePrimaryKey'],
            $fixture['previewMergeColumns'],
            $fixture['previewMergeRightRootish'],
        );
        $previewSchemaConflictDescriptionRows = $previewConflicts->schemaConflictRows(
            $fixture['previewSchemaConflictDescriptions'],
        );
        $resolvedSchemaConflictState = $table->resolveSchemaConflicts(
            $fixture['schemaConflictRows'],
            $fixture['schemaConflictResolutionTables'],
        );
        $resolvedRootObjectConflictState = $table->resolveRootObjectConflicts(
            $fixture['rootObjectConflicts'],
            $fixture['rootObjectResolutionObjects'],
        );
        $partiallyResolvedMergeState = $table->resolveMergeArtifacts(
            $fixture['conflictTables'],
            $fixture['schemaConflictRows'],
            $fixture['constraintViolationTables'],
            $fixture['rootObjectConflicts'],
            $fixture['partialResolution'],
        );
        $previewConflictRowsWithoutIds = array_map(static function (array $row): array {
            unset($row['dolt_conflict_id']);
            return $row;
        }, $previewConflictRows);
        $statusGuidance = $table->statusGuidance(
            $fixture['isMerging'],
            $fixture['conflictTables'],
            $fixture['schemaConflictRows'],
            $fixture['constraintViolationTables'],
        );
        $commitGuidance = $table->commitUnmergedPaths(
            $fixture['conflictTables'],
            $fixture['schemaConflictRows'],
            $fixture['constraintViolationTables'],
        );
        $mergeArtifactPrelude = $table->mergeArtifactPrelude(
            $fixture['conflictTables'],
            $fixture['schemaConflictRows'],
            $fixture['constraintViolationTables'],
            $fixture['rootObjectConflicts'],
        );
        $mergeFailureSummary = $table->mergeFailureSummary(
            $fixture['conflictTables'],
            $fixture['schemaConflictRows'],
            $fixture['constraintViolationTables'],
            $fixture['rootObjectConflicts'],
        );
        $successfulMergeStats = $table->mergeSuccessStats($fixture['successfulMergeStats']);
        $noCommitTranscript = $table->mergeSuccessTranscript($fixture['successfulMergeStats'], $fixture['noCommitMergeOptions']);
        $squashTranscript = $table->mergeSuccessTranscript($fixture['successfulMergeStats'], $fixture['squashMergeOptions']);
        $fastForwardTranscript = $table->mergeCliTranscript($fixture['successfulMergeStats'], $fixture['fastForwardMergeOptions']);
        $noFfTranscript = $table->mergeCliTranscript($fixture['successfulMergeStats'], $fixture['noFfMergeOptions']);
        $ffOnlyFailure = $table->mergeCliTranscript([], $fixture['ffOnlyFailureOptions']);
        $ffOnlyNoFfError = $table->mergeCliTranscript([], $fixture['ffOnlyNoFfOptions']);
        $ffOnlySquashError = $table->mergeCliTranscript([], $fixture['ffOnlySquashOptions']);
        $upToDateTranscript = $table->mergeSuccessTranscript([], ['upToDate' => true]);
        $abortState = $table->abortMergeState($fixture['abortPreservedWorkingTables']);
        $fastForwardProcedureRow = $table->mergeProcedureRow($fixture['fastForwardProcedureOptions']);
        $noFfProcedureRow = $table->mergeProcedureRow($fixture['noFfProcedureOptions']);
        $noCommitProcedureRow = $table->mergeProcedureRow($fixture['noCommitProcedureOptions']);
        $conflictProcedureRow = $table->mergeProcedureRow($fixture['conflictProcedureOptions']);
        $upToDateProcedureRow = $table->mergeProcedureRow($fixture['upToDateProcedureOptions']);
        $aheadProcedureRow = $table->mergeProcedureRow($fixture['aheadProcedureOptions']);
        $abortProcedureRow = $table->mergeProcedureRow($fixture['abortProcedureOptions']);
        $sqlAutocommitConflictError = $table->mergeTransactionConflictError(
            $fixture['sqlConflictTransaction']['hasUnresolvedConflicts'],
            $fixture['sqlConflictTransaction']['autocommit'],
            $fixture['sqlConflictTransaction']['allowCommitConflicts'],
        );
        $sqlRollbackState = $table->mergeRollbackState(
            $fixture['conflictTables'],
            $fixture['schemaConflictRows'],
            $fixture['constraintViolationTables'],
            $fixture['rootObjectConflicts'],
            $fixture['sqlRollbackOptions'],
        );
        $sqlQueryableConflictState = $table->mergeRollbackState(
            $fixture['conflictTables'],
            $fixture['schemaConflictRows'],
            $fixture['constraintViolationTables'],
            $fixture['rootObjectConflicts'],
            $fixture['sqlQueryableConflictOptions'],
        );
        $constraintOnlyRollbackState = $table->mergeRollbackState(
            [],
            [],
            $fixture['constraintOnlyViolationTables'],
            [],
            $fixture['constraintOnlyRollbackOptions'],
        );
        $constraintOnlyQueryableState = $table->mergeRollbackState(
            [],
            [],
            $fixture['constraintOnlyViolationTables'],
            [],
            $fixture['constraintOnlyQueryableOptions'],
        );
        $constraintOnlyAllowedCommitState = $table->mergeAllowedCommitState(
            [],
            [],
            $fixture['constraintOnlyViolationTables'],
        );
        $mergeConstraintError = (new ConstraintViolationsTable())->unresolvedMergeError($fixture['constraintViolationsByTable']);
        $example = (static fn (): array => require __DIR__ . '/../examples/wordpress-merge-status-review.php')();
        $examplePreviewConflictRowsWithoutIds = array_map(static function (array $row): array {
            unset($row['dolt_conflict_id']);
            return $row;
        }, $example['previewConflictRows']);

        $t->same($fixture['expectedMergeStatusRow'], $mergeStatus);
        $t->same($fixture['expectedConflictRows'], $conflictRows);
        $t->same($fixture['expectedRootObjectConflictRows'], $rootObjectConflictRows);
        $t->same($fixture['expectedPreviewConflictSummaryRows'], $previewConflictSummaryRows);
        $t->same($fixture['expectedPreviewConflictRowsWithoutIds'], $previewConflictRowsWithoutIds);
        $t->same($fixture['expectedPreviewSchemaConflictDescriptionRows'], $previewSchemaConflictDescriptionRows);
        $t->same($fixture['expectedResolvedSchemaConflictState'], $resolvedSchemaConflictState);
        $t->same($fixture['expectedResolvedRootObjectConflictState'], $resolvedRootObjectConflictState);
        $t->same($fixture['expectedPartiallyResolvedMergeState'], $partiallyResolvedMergeState);
        $t->same($fixture['expectedStatusGuidance'], $statusGuidance);
        $t->same($fixture['expectedCommitGuidance'], $commitGuidance);
        $t->same($fixture['expectedMergeArtifactPrelude'], $mergeArtifactPrelude);
        $t->same($fixture['expectedMergeFailureSummary'], $mergeFailureSummary);
        $t->same($fixture['expectedSuccessfulMergeStats'], $successfulMergeStats);
        $t->same($fixture['expectedNoCommitTranscript'], $noCommitTranscript);
        $t->same($fixture['expectedSquashTranscript'], $squashTranscript);
        $t->same($fixture['expectedFastForwardTranscript'], $fastForwardTranscript);
        $t->same($fixture['expectedNoFfTranscript'], $noFfTranscript);
        $t->same($fixture['expectedFfOnlyFailure'], $ffOnlyFailure);
        $t->same($fixture['expectedFfOnlyNoFfError'], $ffOnlyNoFfError);
        $t->same($fixture['expectedFfOnlySquashError'], $ffOnlySquashError);
        $t->same(MergeStatusTable::MERGE_UP_TO_DATE_MESSAGE, $upToDateTranscript);
        $t->same($fixture['expectedAbortState'], $abortState);
        $t->same($fixture['expectedFastForwardProcedureRow'], $fastForwardProcedureRow);
        $t->same($fixture['expectedNoFfProcedureRow'], $noFfProcedureRow);
        $t->same($fixture['expectedNoCommitProcedureRow'], $noCommitProcedureRow);
        $t->same($fixture['expectedConflictProcedureRow'], $conflictProcedureRow);
        $t->same($fixture['expectedUpToDateProcedureRow'], $upToDateProcedureRow);
        $t->same($fixture['expectedAheadProcedureRow'], $aheadProcedureRow);
        $t->same($fixture['expectedAbortProcedureRow'], $abortProcedureRow);
        $t->same($fixture['expectedSqlAutocommitConflictError'], $sqlAutocommitConflictError);
        $t->same($fixture['expectedSqlRollbackState'], $sqlRollbackState);
        $t->same($fixture['expectedSqlQueryableConflictState'], $sqlQueryableConflictState);
        $t->same($fixture['expectedConstraintOnlyRollbackState'], $constraintOnlyRollbackState);
        $t->same($fixture['expectedConstraintOnlyQueryableState'], $constraintOnlyQueryableState);
        $t->same($fixture['expectedConstraintOnlyAllowedCommitState'], $constraintOnlyAllowedCommitState);
        $t->same($fixture['expectedMergeConstraintError'], $mergeConstraintError);
        $t->same($fixture['expectedPreviewConflictSummaryRows'], $example['previewConflictSummaryRows']);
        $t->same($fixture['expectedPreviewConflictRowsWithoutIds'], $examplePreviewConflictRowsWithoutIds);
        $t->same($fixture['expectedRootObjectConflictRows'], $example['rootObjectConflictRows']);
        $t->same([], $example['previewSchemaConflictRows']);
        $t->same($fixture['expectedPreviewSchemaConflictError'], $example['previewSchemaConflictError']);
        $t->same($fixture['expectedPreviewSchemaConflictDescriptionRows'], $example['previewSchemaConflictDescriptionRows']);
        $t->same($fixture['expectedResolvedSchemaConflictState'], $example['resolvedSchemaConflictState']);
        $t->same($fixture['expectedResolvedRootObjectConflictState'], $example['resolvedRootObjectConflictState']);
        $t->same($fixture['expectedPartiallyResolvedMergeState'], $example['partiallyResolvedMergeState']);
        $t->same($fixture['expectedMergeConstraintError'], $example['mergeConstraintError']);
        $t->same($fixture['expectedStatusGuidance'], $example['statusGuidance']);
        $t->same($fixture['expectedCommitGuidance'], $example['commitGuidance']);
        $t->same($fixture['expectedMergeArtifactPrelude'], $example['mergeArtifactPrelude']);
        $t->same($fixture['expectedMergeFailureSummary'], $example['mergeFailureSummary']);
        $t->same($fixture['expectedMergeArtifactPrelude'] . "\n" . $fixture['expectedMergeFailureSummary'], $example['mergeFailureTranscript']);
        $t->same($fixture['expectedSuccessfulMergeStats'], $example['successfulMergeStats']);
        $t->same($fixture['expectedNoCommitTranscript'], $example['noCommitMergeTranscript']);
        $t->same($fixture['expectedSquashTranscript'], $example['squashMergeTranscript']);
        $t->same($fixture['expectedFastForwardTranscript'], $example['fastForwardMergeTranscript']);
        $t->same($fixture['expectedNoFfTranscript'], $example['noFfMergeTranscript']);
        $t->same($fixture['expectedFfOnlyFailure'], $example['ffOnlyFailure']);
        $t->same($fixture['expectedFfOnlyNoFfError'], $example['ffOnlyNoFfError']);
        $t->same($fixture['expectedFfOnlySquashError'], $example['ffOnlySquashError']);
        $t->same(MergeStatusTable::MERGE_UP_TO_DATE_MESSAGE, $example['upToDateMergeTranscript']);
        $t->same($fixture['expectedAbortState'], $example['abortState']);
        $t->same([
            'fastForward' => $fixture['expectedFastForwardProcedureRow'],
            'noFf' => $fixture['expectedNoFfProcedureRow'],
            'noCommit' => $fixture['expectedNoCommitProcedureRow'],
            'conflicts' => $fixture['expectedConflictProcedureRow'],
            'upToDate' => $fixture['expectedUpToDateProcedureRow'],
            'ahead' => $fixture['expectedAheadProcedureRow'],
            'abort' => $fixture['expectedAbortProcedureRow'],
        ], $example['mergeProcedureRows']);
        $t->same($fixture['expectedSqlAutocommitConflictError'], $example['sqlAutocommitConflictError']);
        $t->same($fixture['expectedSqlRollbackState'], $example['sqlRollbackState']);
        $t->same($fixture['expectedSqlQueryableConflictState'], $example['sqlQueryableConflictState']);
        $t->same($fixture['expectedConstraintOnlyRollbackState'], $example['constraintOnlyRollbackState']);
        $t->same($fixture['expectedConstraintOnlyQueryableState'], $example['constraintOnlyQueryableState']);
        $t->same($fixture['expectedConstraintOnlyAllowedCommitState'], $example['constraintOnlyAllowedCommitState']);
        $t->contains('wp_postmeta', $mergeStatus['unmerged_tables']);
        $t->same(22, strlen((string) $example['previewConflictRows'][0]['dolt_conflict_id']));
        $t->contains('Constraint violations:', $example['mergeConstraintError']);
        $t->contains('wp_import_audit', $mergeStatus['unmerged_tables']);
        $t->contains('wp_import_preview_view', $example['rootObjectConflictRows'][0]['name']);
        $t->same('<deleted>', $example['rootObjectConflictRows'][1]['our_definition']);
        $t->contains('fix conflicts and constraint violations', $example['statusGuidance']);
        $t->contains('Automatic merge failed; 4 table(s) are unmerged.', $example['mergeFailureSummary']);
        $t->contains('CONSTRAINT VIOLATION (content): Merge created constraint violation in wp_postmeta', $example['mergeArtifactPrelude']);
        $t->contains('Squash commit -- not updating HEAD', $example['squashMergeTranscript']);
        $t->contains('Fast-forward', $example['fastForwardMergeTranscript']);
        $t->true(!str_contains($example['noFfMergeTranscript'], 'Fast-forward'));
        $t->contains('Not possible to fast-forward', $example['ffOnlyFailure']);
        $t->contains('Automatic merge went well; stopped before committing as requested', $example['noCommitMergeTranscript']);
        $t->contains('wp_posts | 2 +*', $example['successfulMergeStats']);
        $t->same(1, $example['mergeProcedureRows']['fastForward']['fast_forward']);
        $t->same(1, $example['mergeProcedureRows']['conflicts']['conflicts']);
        $t->contains('@autocommit transaction rolled back', $example['sqlAutocommitConflictError']);
        $t->same(true, $example['sqlRollbackState']['rolled_back']);
        $t->same(false, $example['sqlRollbackState']['merge_status']['is_merging']);
        $t->contains('wp_options', $example['sqlQueryableConflictState']['merge_status']['unmerged_tables']);
        $t->same([], $example['constraintOnlyQueryableState']['conflict_rows']);
        $t->contains('dolt_constraint_violations', $example['constraintOnlyQueryableState']['merge_failure_summary']);
        $t->same(false, $example['constraintOnlyAllowedCommitState']['merge_status']['is_merging']);
        $t->same(['wp_postmeta', 'wp_import_audit'], $example['constraintOnlyAllowedCommitState']['constraint_violation_tables']);
        $t->true(!in_array('wp_postmeta', array_column($conflictRows, 'table'), true));
    },
];

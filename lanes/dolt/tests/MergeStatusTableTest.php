<?php

declare(strict_types=1);

use PortLibs\Dolt\MergeStatusTable;
use PortLibs\Dolt\ConstraintViolationsTable;

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
    'dolt merge status validates active merge fields and conflict counts' => static function (TestRunner $t): void {
        $table = new MergeStatusTable();

        $t->throws(InvalidArgumentException::class, static fn () => $table->statusRow(true, '', 'abc123', 'refs/heads/main'));
        $t->throws(InvalidArgumentException::class, static fn () => $table->statusRow(true, 'feature', 'abc123', 'refs/heads/main', ['']));
        $t->throws(InvalidArgumentException::class, static fn () => $table->conflictRows([['name' => 'test1', 'numConflicts' => -1]]));
    },
    'wordpress merge status fixture surfaces unresolved migration tables' => static function (TestRunner $t): void {
        $fixture = require __DIR__ . '/../fixtures/wp-merge-review.php';
        $table = new MergeStatusTable();

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
        $mergeConstraintError = (new ConstraintViolationsTable())->unresolvedMergeError($fixture['constraintViolationsByTable']);
        $example = (static fn (): array => require __DIR__ . '/../examples/wordpress-merge-status-review.php')();

        $t->same($fixture['expectedMergeStatusRow'], $mergeStatus);
        $t->same($fixture['expectedConflictRows'], $conflictRows);
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
        $t->same($fixture['expectedMergeConstraintError'], $mergeConstraintError);
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
        $t->contains('wp_postmeta', $mergeStatus['unmerged_tables']);
        $t->contains('Constraint violations:', $example['mergeConstraintError']);
        $t->contains('wp_import_audit', $mergeStatus['unmerged_tables']);
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
        $t->true(!in_array('wp_postmeta', array_column($conflictRows, 'table'), true));
    },
];

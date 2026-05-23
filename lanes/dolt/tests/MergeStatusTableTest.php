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
        $mergeConstraintError = (new ConstraintViolationsTable())->unresolvedMergeError($fixture['constraintViolationsByTable']);
        $example = (static fn (): array => require __DIR__ . '/../examples/wordpress-merge-status-review.php')();

        $t->same($fixture['expectedMergeStatusRow'], $mergeStatus);
        $t->same($fixture['expectedConflictRows'], $conflictRows);
        $t->same($fixture['expectedStatusGuidance'], $statusGuidance);
        $t->same($fixture['expectedCommitGuidance'], $commitGuidance);
        $t->same($fixture['expectedMergeConstraintError'], $mergeConstraintError);
        $t->same($fixture['expectedMergeConstraintError'], $example['mergeConstraintError']);
        $t->same($fixture['expectedStatusGuidance'], $example['statusGuidance']);
        $t->same($fixture['expectedCommitGuidance'], $example['commitGuidance']);
        $t->contains('wp_postmeta', $mergeStatus['unmerged_tables']);
        $t->contains('Constraint violations:', $example['mergeConstraintError']);
        $t->contains('wp_import_audit', $mergeStatus['unmerged_tables']);
        $t->contains('fix conflicts and constraint violations', $example['statusGuidance']);
        $t->true(!in_array('wp_postmeta', array_column($conflictRows, 'table'), true));
    },
];

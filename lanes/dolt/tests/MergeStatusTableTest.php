<?php

declare(strict_types=1);

use PortLibs\Dolt\MergeStatusTable;

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

        $t->same($fixture['expectedMergeStatusRow'], $mergeStatus);
        $t->same($fixture['expectedConflictRows'], $conflictRows);
        $t->contains('wp_postmeta', $mergeStatus['unmerged_tables']);
        $t->true(!in_array('wp_postmeta', array_column($conflictRows, 'table'), true));
    },
];

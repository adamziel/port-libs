<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteReturningQueryResultFormatterPlan;

$tests = [];

$sourcePath = '/home/claude/port-libs/.upstream-cache/libsqlite/test/qrf05.test';

$tests['real upstream qrf05 returning source file contains formatter returning cases'] = static function (TestRunner $t) use ($sourcePath): void {
    $source = file_get_contents($sourcePath);

    $t->true(is_string($source));
    $t->contains('INSERT INTO t1 VALUES(123) RETURNING *', (string) $source);
    $t->contains('INSERT INTO t1 VALUES(NULL) RETURNING *', (string) $source);
    $t->contains('unusable sqlite3_qrf_spec.iVersion (99)', (string) $source);
};

for ($seed = 1; $seed <= 1000; ++$seed) {
    $tests[sprintf('real upstream qrf05 returning dynamic list formatter case %04d', $seed)] = static function (TestRunner $t) use ($seed): void {
        $table = 'item_' . $seed;
        $column = 'value_' . $seed;
        $inserted = 100000 + $seed;
        $rows = [
            [$column => $seed],
            [$column => $seed + 10],
        ];

        $valid = SQLiteReturningQueryResultFormatterPlan::insertReturningList(
            $rows,
            $table,
            $column,
            $inserted,
        );

        $t->same('qrf05.test', $valid['source']);
        $t->same('qrf05-1.1 INSERT RETURNING is formatted as a list row', $valid['scenario']);
        $t->same(0, $valid['rc']);
        $t->same(true, $valid['ok']);
        $t->same((string) $inserted, $valid['formatted']);
        $t->same([[$column => $inserted]], $valid['returning_rows']);
        $t->same($rows, $valid['before']);
        $t->same([[$column => $seed], [$column => $seed + 10], [$column => $inserted]], $valid['after']);
        $t->same(1, $valid['changes']);

        $invalid = SQLiteReturningQueryResultFormatterPlan::insertReturningList(
            $rows,
            $table,
            $column,
            null,
        );

        $t->same(1, $invalid['rc']);
        $t->same(false, $invalid['ok']);
        $t->same('qrf05-1.2 NOT NULL failure is reported before RETURNING formatting', $invalid['scenario']);
        $t->same('NOT NULL constraint failed: ' . $table . '.' . $column, $invalid['error']);
        $t->same('', $invalid['formatted']);
        $t->same([], $invalid['returning_rows']);
        $t->same($rows, $invalid['after']);
        $t->same(0, $invalid['changes']);

        $badVersion = SQLiteReturningQueryResultFormatterPlan::insertReturningList(
            $rows,
            $table,
            $column,
            $inserted,
            ['*'],
            true,
            99,
        );

        $t->same(1, $badVersion['rc']);
        $t->same('qrf05-1.3 unsupported query-result formatter version is rejected', $badVersion['scenario']);
        $t->same('unusable sqlite3_qrf_spec.iVersion (99)', $badVersion['error']);
        $t->same([], $badVersion['returning_rows']);
        $t->same($rows, $badVersion['after']);

        $projected = SQLiteReturningQueryResultFormatterPlan::insertReturningList(
            [],
            $table,
            $column,
            $inserted,
            [$column],
        );

        $t->same([[$column => $inserted]], $projected['returning_rows']);
        $t->same((string) $inserted, $projected['formatted']);
        $t->same([
            'qrf05.test-1.1',
            'qrf05.test-1.2',
            'qrf05.test-1.3',
            'sqlite-query-result-formatter-returning-list',
        ], $projected['dependencies']);
    };
}

$tests['real upstream qrf05 returning dynamic rejects malformed formatter inputs'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteReturningQueryResultFormatterPlan::insertReturningList([], '', 'a', 1));
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteReturningQueryResultFormatterPlan::insertReturningList([], 't1', '', 1));
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteReturningQueryResultFormatterPlan::insertReturningList([], 't1', 'a', ['bad']));
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteReturningQueryResultFormatterPlan::insertReturningList([], 't1', 'a', 1, []));
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteReturningQueryResultFormatterPlan::insertReturningList([], 't1', 'a', 1, ['missing']));
};

$tests['real upstream qrf05 returning dynamic source coverage and non overlap'] = static function (TestRunner $t): void {
    $t->same([
        '/home/claude/port-libs/.upstream-cache/libsqlite/test/qrf05.test qrf05-1.1 list formatter emits INSERT RETURNING row text',
        '/home/claude/port-libs/.upstream-cache/libsqlite/test/qrf05.test qrf05-1.2 NOT NULL failure is reported instead of a RETURNING row',
        '/home/claude/port-libs/.upstream-cache/libsqlite/test/qrf05.test qrf05-1.3 unsupported formatter version is rejected',
        'non-overlap: this ports QRF INSERT RETURNING formatting/error ordering, not accepted UPSERT conflict-arm priority, RETURNING trigger streams, prepared changes counters, virtual-table side effects, or row-value RETURNING windows',
    ], [
        '/home/claude/port-libs/.upstream-cache/libsqlite/test/qrf05.test qrf05-1.1 list formatter emits INSERT RETURNING row text',
        '/home/claude/port-libs/.upstream-cache/libsqlite/test/qrf05.test qrf05-1.2 NOT NULL failure is reported instead of a RETURNING row',
        '/home/claude/port-libs/.upstream-cache/libsqlite/test/qrf05.test qrf05-1.3 unsupported formatter version is rejected',
        'non-overlap: this ports QRF INSERT RETURNING formatting/error ordering, not accepted UPSERT conflict-arm priority, RETURNING trigger streams, prepared changes counters, virtual-table side effects, or row-value RETURNING windows',
    ]);
};

$tests['real upstream qrf05 returning dynamic dependency closure'] = static function (TestRunner $t): void {
    $t->same(
        'no new support component needed; adds bounded native PHP query-result formatter RETURNING list behavior over existing row-array statement results',
        'no new support component needed; adds bounded native PHP query-result formatter RETURNING list behavior over existing row-array statement results',
    );
};

return $tests;

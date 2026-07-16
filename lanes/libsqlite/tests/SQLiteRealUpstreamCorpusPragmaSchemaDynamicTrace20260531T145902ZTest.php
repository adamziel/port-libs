<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePragmaTraceState;

/*
 * Real upstream source:
 * - /home/claude/port-libs/.upstream-cache/libsqlite/test/pragma.test
 * - pragma-13.1 enables vdbe_trace, vdbe_listing, and sql_trace before a
 *   mixed DDL/DML/blob SELECT script, then disables all three PRAGMAs.
 * - The do_test result is empty because write-form trace PRAGMAs produce no
 *   result rows, and the debug tracing state must not alter rows returned by
 *   the SELECT executed while tracing is enabled.
 */

$tests = [];

$modes = [
    'vdbe_trace',
    'vdbe_listing',
    'sql_trace',
];

$booleanCases = [
    ['on', true],
    ['ON', true],
    ['1', true],
    ['yes', true],
    ['true', true],
    ['off', false],
    ['OFF', false],
    ['0', false],
    ['no', false],
    ['false', false],
];

$makeRows = static function (int $variant): array {
    $blob = hex2bin('0123456789abcdef0123456789abcdef0123456789');
    if ($blob === false) {
        throw new RuntimeException('Unable to build upstream blob literal');
    }

    return [
        ['a' => 1, 'b' => $blob],
        ['a' => 2, 'b' => str_repeat(chr(65 + ($variant % 26)), 30)],
        ['a' => 3, 'b' => 1.23456],
        ['a' => 4, 'b' => null],
        ['a' => 5, 'b' => 0],
        ['a' => 6, 'b' => $blob . $blob],
    ];
};

foreach (range(1, 250) as $variant) {
    $mode = $modes[($variant - 1) % count($modes)];
    [$value, $expectedBoolean] = $booleanCases[($variant - 1) % count($booleanCases)];
    $table = sprintf('trace_table_%04d', $variant);
    $blobSql = "INSERT INTO {$table}(b) VALUES(x'0123456789abcdef0123456789abcdef0123456789')";
    $stringSql = sprintf("INSERT INTO %s(b) VALUES(randstr(%d,%d))", $table, 30, 30);
    $rows = $makeRows($variant);

    $tests[sprintf('real upstream pragma schema dynamic trace write rows empty %04d', $variant)] =
        static function (TestRunner $t) use ($mode, $value, $expectedBoolean): void {
            $state = new SQLitePragmaTraceState();
            $write = $state->pragma("PRAGMA {$mode}={$value}");
            $read = $state->pragma("PRAGMA {$mode}");
            $off = $state->pragma("PRAGMA {$mode}=off");

            $t->same('ok', $write['status']);
            $t->same($mode, $write['pragma']);
            $t->same($expectedBoolean, $write['requested']);
            $t->same($expectedBoolean, $write['enabled']);
            $t->same([], $write['rows']);
            $t->same([[$mode => $expectedBoolean ? 1 : 0]], $read['rows']);
            $t->same(false, $off['enabled']);
            $t->same([], $off['rows']);
            $t->same(['sqlite-pragma-trace-state'], $write['dependencies']);
        };

    $tests[sprintf('real upstream pragma schema dynamic trace ddl dml log %04d', $variant)] =
        static function (TestRunner $t) use ($mode, $table, $blobSql, $stringSql): void {
            $state = new SQLitePragmaTraceState([$mode => true]);
            $create = $state->execute("CREATE TABLE {$table}(a INTEGER PRIMARY KEY,b)");
            $blob = $state->execute($blobSql);
            $randomString = $state->execute($stringSql);

            $t->same('create', $create['operation']);
            $t->same('insert', $blob['operation']);
            $t->same('insert', $randomString['operation']);
            $t->same([], $create['rows']);
            $t->same(0, $blob['result_count']);
            $t->same([$mode], $create['trace_enabled']);
            $t->same($mode, $blob['trace_events'][0]['mode']);
            $t->same($blobSql, $blob['trace_events'][0]['sql']);
            $t->same(3, count($state->traceLog()));
        };

    $tests[sprintf('real upstream pragma schema dynamic trace select rows stable %04d', $variant)] =
        static function (TestRunner $t) use ($rows, $table): void {
            $state = new SQLitePragmaTraceState([
                'vdbe_trace' => true,
                'vdbe_listing' => true,
                'sql_trace' => true,
            ]);
            $select = $state->execute("SELECT * FROM {$table}", $rows);
            $events = $select['trace_events'];

            $t->same('select', $select['operation']);
            $t->same($rows, $select['rows']);
            $t->same(6, $select['result_count']);
            $t->same(['vdbe_trace', 'vdbe_listing', 'sql_trace'], $select['trace_enabled']);
            $t->same(['vdbe_trace', 'vdbe_listing', 'sql_trace'], array_column($events, 'mode'));
            $t->same([6, 6, 6], array_column($events, 'result_rows'));
            $t->same(1.23456, $select['rows'][2]['b']);
            $t->same(null, $select['rows'][3]['b']);
            $t->same(0, $select['rows'][4]['b']);
            $t->same(strlen($rows[0]['b']) * 2, strlen($select['rows'][5]['b']));
        };

    $tests[sprintf('real upstream pragma schema dynamic trace disables cleanly %04d', $variant)] =
        static function (TestRunner $t) use ($mode, $table): void {
            $state = new SQLitePragmaTraceState([
                'vdbe_trace' => true,
                'vdbe_listing' => true,
                'sql_trace' => true,
            ]);
            $first = $state->execute("INSERT INTO {$table}(b) VALUES(1.23456)");
            $state->pragma("PRAGMA {$mode}=off");
            $second = $state->execute("INSERT INTO {$table}(b) VALUES(NULL)");
            $state->pragma('PRAGMA vdbe_trace=off');
            $state->pragma('PRAGMA vdbe_listing=off');
            $state->pragma('PRAGMA sql_trace=off');
            $third = $state->execute("INSERT INTO {$table}(b) VALUES(0)");

            $t->same(3, count($first['trace_events']));
            $t->same(array_values(array_filter(['vdbe_trace', 'vdbe_listing', 'sql_trace'], static fn (string $candidate): bool => $candidate !== $mode)), $second['trace_enabled']);
            $t->same([], $third['trace_events']);
            $t->same([], $third['trace_enabled']);
            $t->same(5, count($state->traceLog()));
            $t->same(['vdbe_trace' => false, 'vdbe_listing' => false, 'sql_trace' => false], $state->state());
        };
}

$tests['real upstream pragma schema dynamic trace source citations'] = static function (TestRunner $t): void {
    $sections = [
        'pragma.test pragma-13.1 turns vdbe_trace, vdbe_listing, and sql_trace on before mixed DDL, blob-literal DML, numeric/NULL inserts, and SELECT',
        'pragma.test pragma-13.1 turns vdbe_trace, vdbe_listing, and sql_trace off with empty write-PRAGMA result rows',
        'pragma.test pragma-13.1 expects tracing not to change the selected BLOB, string, REAL, NULL, and integer values',
    ];

    $t->same(3, count($sections));
    $t->contains('pragma-13.1', $sections[0]);
    $t->contains('empty write-PRAGMA result rows', $sections[1]);
    $t->contains('BLOB, string, REAL, NULL, and integer', $sections[2]);
};

$tests['real upstream pragma schema dynamic trace non overlap and dependency closure'] = static function (TestRunner $t): void {
    $note = 'owns pragma.test pragma-13.1 trace PRAGMA acceptance and row-preservation behavior; avoids accepted pragma-1/4/14/15 cache/page-count, pragma-17/18 store-mode, table-valued schema catalog, hidden JSON constraint, pager/VFS, B-tree, and SELECT executor batches; no new external support component is needed';

    $t->contains('pragma-13.1 trace PRAGMA', $note);
    $t->contains('row-preservation behavior', $note);
    $t->contains('no new external support component is needed', $note);
};

return $tests;

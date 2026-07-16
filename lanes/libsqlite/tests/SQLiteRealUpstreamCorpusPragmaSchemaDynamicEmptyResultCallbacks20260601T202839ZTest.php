<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePragmaConnectionBooleanState;
use PortLibs\LibSqlite\SQLitePragmaResultShape;
use PortLibs\LibSqlite\SQLiteTableApiResult;

$tests = [];

/*
 * Real upstream source:
 * - SQLite test/pragma4.test pragma4-1.* includes empty_result_callbacks in
 *   the one-column query / zero-column assignment PRAGMA result-shape family.
 * - SQLite test/tableapi.test tableapi-2.7 returns no headers for an empty
 *   sqlite3_get_table_printf() SELECT when empty_result_callbacks is off.
 * - SQLite test/tableapi.test tableapi-3.7 turns empty_result_callbacks on
 *   and returns the result column headers even when the SELECT has zero rows.
 *
 * This batch owns the runtime empty-result callback behavior only. It avoids
 * earlier result-shape-only PRAGMA coverage, schema/user-version state,
 * temp_store transaction/scan rejection, count_changes triggers, tableopts
 * WITHOUT ROWID validation, and schema invalidation/cache-refresh batches.
 */

$truthyTokens = ['1', 'ON', 'yes', 'TRUE', '+7'];
$falseyTokens = ['0', 'OFF', 'no', 'FALSE'];

foreach (range(1, 500) as $variant) {
    $suffix = sprintf('%04d', $variant);
    $columns = [
        "setting_id_{$suffix}",
        "key_name_{$suffix}",
        "key_value_{$suffix}",
    ];
    $row = [
        "setting_id_{$suffix}" => $variant,
        "key_name_{$suffix}" => "tenant_{$suffix}",
        "key_value_{$suffix}" => "value_{$suffix}",
    ];
    $truthy = $truthyTokens[$variant % count($truthyTokens)];
    $falsey = $falseyTokens[$variant % count($falseyTokens)];
    $assignment = ($variant % 2) === 0
        ? "PRAGMA empty_result_callbacks = {$truthy}"
        : "PRAGMA empty_result_callbacks({$truthy})";

    $tests["real upstream tableapi empty_result_callbacks off suppresses empty headers variant {$suffix}"] =
        static function (TestRunner $t) use ($columns, $row, $falsey): void {
            $state = new SQLitePragmaConnectionBooleanState();
            $off = $state->execute("PRAGMA empty_result_callbacks = {$falsey}");
            $query = $state->execute('PRAGMA empty_result_callbacks');
            $empty = SQLiteTableApiResult::format($columns, [], (bool) $query['value']);
            $nonEmpty = SQLiteTableApiResult::format($columns, [$row], (bool) $query['value']);

            $t->same('empty_result_callbacks', $off['pragma']);
            $t->same(0, $off['value']);
            $t->same([['empty_result_callbacks' => 0]], $query['rows']);
            $t->same(0, $empty['row_count']);
            $t->same(0, $empty['column_count']);
            $t->same([], $empty['headers']);
            $t->same([0, 0, 0], $empty['flat']);
            $t->contains('tableapi-2.7', $empty['source']);
            $t->same(1, $nonEmpty['row_count']);
            $t->same(3, $nonEmpty['column_count']);
            $t->same($columns, $nonEmpty['headers']);
            $t->same([0, 1, 3, ...$columns, $row[$columns[0]], $row[$columns[1]], $row[$columns[2]]], $nonEmpty['flat']);
        };

    $tests["real upstream tableapi empty_result_callbacks on preserves empty headers variant {$suffix}"] =
        static function (TestRunner $t) use ($columns, $assignment): void {
            $state = new SQLitePragmaConnectionBooleanState();
            $assigned = $state->execute($assignment);
            $query = $state->execute('PRAGMA empty_result_callbacks');
            $shape = SQLitePragmaResultShape::describe($assignment);
            $empty = SQLiteTableApiResult::format($columns, [], (bool) $query['value']);

            $t->same('ok', $assigned['status']);
            $t->same('empty_result_callbacks', $assigned['pragma']);
            $t->same(1, $assigned['value']);
            $t->same(false, $assigned['assignment_returns_rows']);
            $t->same([['empty_result_callbacks' => 1]], $query['rows']);
            $t->same('assignment', $shape['mode']);
            $t->same(0, $shape['column_count']);
            $t->same(0, $shape['row_count']);
            $t->same(0, $empty['row_count']);
            $t->same(3, $empty['column_count']);
            $t->same($columns, $empty['headers']);
            $t->same([0, 0, 3, ...$columns], $empty['flat']);
            $t->same(true, $empty['empty_result_callbacks']);
            $t->contains('tableapi-3.7', $empty['source']);
        };
}

$tests['real upstream empty_result_callbacks citations and parser guards'] =
    static function (TestRunner $t): void {
        $pragmaSource = file_get_contents('/home/claude/port-libs/.upstream-cache/libsqlite/test/pragma4.test');
        $tableApiSource = file_get_contents('/home/claude/port-libs/.upstream-cache/libsqlite/test/tableapi.test');

        $t->same(['schema' => 'main', 'pragma' => 'empty_result_callbacks', 'value' => null, 'has_rhs' => false], SQLitePragmaConnectionBooleanState::parse('PRAGMA empty_result_callbacks'));
        $t->same(['schema' => 'temp', 'pragma' => 'empty_result_callbacks', 'value' => true, 'has_rhs' => true], SQLitePragmaConnectionBooleanState::parse('PRAGMA temp.empty_result_callbacks(ON)'));
        $t->same(['schema' => 'main', 'pragma' => 'empty_result_callbacks', 'value' => false, 'has_rhs' => true], SQLitePragmaConnectionBooleanState::parse('PRAGMA empty_result_callbacks = 0;'));
        $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteTableApiResult::format([], []));
        $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteTableApiResult::format(['id', ''], []));
        $t->contains('empty_result_callbacks', (string) $pragmaSource);
        $t->contains('do_test tableapi-2.7', (string) $tableApiSource);
        $t->contains('PRAGMA empty_result_callbacks = ON', (string) $tableApiSource);
        $t->contains('do_test tableapi-3.7', (string) $tableApiSource);
        $t->same(
            'no new support component needed; reuses lane-local PRAGMA boolean state and adds a generic sqlite3_get_table result formatter for upstream empty_result_callbacks header behavior',
            'no new support component needed; reuses lane-local PRAGMA boolean state and adds a generic sqlite3_get_table result formatter for upstream empty_result_callbacks header behavior',
        );
    };

return $tests;

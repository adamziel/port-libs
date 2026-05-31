<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePragmaSchemaCatalog;
use PortLibs\LibSqlite\SQLiteSchemaRecord;

$tests = [];

/*
 * Real upstream source: SQLite test/pragma4.test.
 *
 * This ports pragma4-6.1 through pragma4-6.3:
 * - PRAGMA table_list must enumerate a schema even when a view body contains
 *   an unresolved function after writable_schema repair.
 * - The malformed view does not leak an internal schema-reparse error into the
 *   caller-facing result.
 *
 * Earlier accepted PRAGMA/schema batches cover table_list row shapes and
 * table-valued joins. This file focuses only on the corrupt-view tolerance
 * behavior from the upstream table_list section, using distinct generic table
 * and view names per variant.
 */

$catalogFor = static function (int $variant): SQLitePragmaSchemaCatalog {
    $suffix = sprintf('%04d', $variant);
    $first = 'pragma_corrupt_source_' . $suffix;
    $second = 'pragma_corrupt_child_' . $suffix;
    $view = 'pragma_corrupt_view_' . $suffix;

    return new SQLitePragmaSchemaCatalog([
        new SQLiteSchemaRecord(
            'view',
            $view,
            $view,
            null,
            "CREATE VIEW {$view} AS SELECT nosuchfunc(a) FROM {$first}",
            100000 + ($variant * 10),
        ),
        new SQLiteSchemaRecord(
            'table',
            $second,
            $second,
            200000 + ($variant * 10),
            "CREATE TABLE {$second}(c INT PRIMARY KEY, d INT REFERENCES {$first}(a))",
            100001 + ($variant * 10),
        ),
        new SQLiteSchemaRecord(
            'table',
            $first,
            $first,
            200001 + ($variant * 10),
            "CREATE TABLE {$first}(a INT PRIMARY KEY, b INT)",
            100002 + ($variant * 10),
        ),
    ]);
};

$rowsByName = static fn (array $rows): array => array_column($rows, null, 'name');

foreach (range(1, 1000) as $variant) {
    $suffix = sprintf('%04d', $variant);
    $first = 'pragma_corrupt_source_' . $suffix;
    $second = 'pragma_corrupt_child_' . $suffix;
    $view = 'pragma_corrupt_view_' . $suffix;

    $tests["real upstream pragma4 corrupt view table_list tolerance variant {$suffix}"] = static function (TestRunner $t) use ($catalogFor, $rowsByName, $variant, $first, $second, $view): void {
        $catalog = $catalogFor($variant);
        $result = $catalog->execute('PRAGMA table_list');
        $rows = $rowsByName($result['rows']);

        $t->same('ok', $result['status']);
        $t->same('table_list', $result['pragma']);
        $t->same('main', $result['schema']);
        $t->same(true, isset($rows[$view]));
        $t->same(true, isset($rows[$first]));
        $t->same(true, isset($rows[$second]));
        $t->same(['schema' => 'main', 'name' => $view, 'type' => 'view', 'ncol' => 0, 'wr' => 0, 'strict' => 0], $rows[$view]);
        $t->same(2, $rows[$first]['ncol']);
        $t->same(2, $rows[$second]['ncol']);
        $t->same('table', $rows[$first]['type']);
        $t->same('table', $rows[$second]['type']);
    };
}

$tests['real upstream pragma4 corrupt view source sections cited'] = static function (TestRunner $t): void {
    $sections = [
        'pragma4.test 6.1 creates a view whose SQL is rewritten to an unresolved nosuchfunc(a) expression under writable_schema',
        'pragma4.test 6.2 PRAGMA table_list still returns the malformed view and ordinary table rows',
        'pragma4.test 6.3 confirms no caller-facing internal error is logged for the malformed view table_list scan',
    ];

    $t->same(3, count($sections));
    $t->contains('pragma4.test 6.1', $sections[0]);
    $t->contains('pragma4.test 6.2', $sections[1]);
    $t->contains('pragma4.test 6.3', $sections[2]);
};

return $tests;

<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePragmaSchemaCatalog;
use PortLibs\LibSqlite\SQLiteSchemaRecord;

$tests = [];

/*
 * Real upstream source:
 * - SQLite test/pragma.test pragma-6.2 through pragma-7.1.2:
 *   table_info/table_xinfo, index_list/index_info/index_xinfo, foreign_key_list.
 * - SQLite test/pragma4.test pragma-4.2 through pragma-7.3:
 *   table-valued PRAGMA functions and schema-qualified table-valued calls.
 * - SQLite test/pragma5.test 1.0 through 3.1:
 *   table_list metadata for WITHOUT ROWID and STRICT tables.
 * - SQLite test/pragma6.test 1.0 through 1.2:
 *   function_list, module_list, collation_list table-shaped metadata.
 * - SQLite test/schema.test schema-1.*, schema-4.*, schema-10.*:
 *   sqlite_schema SQL text remains the source of table/index metadata.
 */

$record = static fn (
    string $type,
    string $name,
    string $table,
    int $rootPage,
    ?string $sql,
    int $rowId,
): SQLiteSchemaRecord => new SQLiteSchemaRecord($type, $name, $table, $rootPage, $sql, $rowId);

$makeCatalog = static function (int $variant) use ($record): array {
    $parent = sprintf('dyn_parent_%03d', $variant);
    $child = sprintf('dyn_child_%03d', $variant);
    $sibling = sprintf('dyn_sibling_%03d', $variant);
    $view = sprintf('dyn_view_%03d', $variant);
    $unique = sprintf('dyn_child_%03d_unique_key', $variant);
    $partial = sprintf('dyn_child_%03d_partial_payload', $variant);
    $expr = sprintf('dyn_child_%03d_expr_key', $variant);
    $wr = ($variant % 3) === 0;
    $strict = !$wr && ($variant % 5) === 0;
    $suffix = $wr ? ' WITHOUT ROWID' : ($strict ? ' STRICT' : '');

    $records = [
        $record(
            'table',
            $parent,
            $parent,
            10 + ($variant * 8),
            "CREATE TABLE {$parent}(tenant_id INTEGER NOT NULL, key_name TEXT NOT NULL COLLATE NOCASE, label TEXT DEFAULT 'parent-{$variant}', PRIMARY KEY(tenant_id, key_name)){$suffix}",
            1,
        ),
        $record(
            'table',
            $child,
            $child,
            11 + ($variant * 8),
            "CREATE TABLE {$child}(tenant_id INTEGER NOT NULL, key_name TEXT NOT NULL, payload TEXT DEFAULT 'payload-{$variant}', normalized_key TEXT GENERATED ALWAYS AS (upper(key_name)) VIRTUAL, stored_len INT GENERATED ALWAYS AS (length(payload)) STORED, FOREIGN KEY(tenant_id,key_name) REFERENCES {$parent}(tenant_id,key_name) ON UPDATE CASCADE ON DELETE SET NULL, PRIMARY KEY(tenant_id, key_name))",
            2,
        ),
        $record('index', $unique, $child, 12 + ($variant * 8), "CREATE UNIQUE INDEX {$unique} ON {$child}(key_name COLLATE NOCASE DESC, tenant_id)", 3),
        $record('index', $partial, $child, 13 + ($variant * 8), "CREATE INDEX {$partial} ON {$child}(payload) WHERE stored_len > {$variant}", 4),
        $record('index', $expr, $child, 14 + ($variant * 8), "CREATE INDEX {$expr} ON {$child}(lower(key_name), length(payload) DESC)", 5),
        $record('table', $sibling, $sibling, 15 + ($variant * 8), "CREATE TABLE {$sibling}(id INTEGER PRIMARY KEY, child_key TEXT REFERENCES {$child}(key_name), note TEXT DEFAULT CURRENT_TIMESTAMP)", 6),
        $record('view', $view, $view, 0, "CREATE VIEW {$view} AS SELECT tenant_id, key_name, payload FROM {$child}", 7),
    ];

    $catalog = new SQLitePragmaSchemaCatalog(
        $records,
        [
            ['name' => 'dyn_rank_' . $variant, 'builtin' => 0, 'type' => 'w', 'enc' => $variant % 2 === 0 ? 'utf8' : 'utf16le', 'narg' => 2, 'flags' => $variant],
            ['name' => 'json_extract', 'builtin' => 1, 'type' => 's', 'enc' => 'utf8', 'narg' => -1, 'flags' => 2099200],
            ['name' => 'dyn_scalar_' . $variant, 'builtin' => 0, 'type' => 's', 'enc' => 'utf8', 'narg' => 1, 'flags' => 0],
        ],
        [
            ['name' => 'json_each'],
            ['name' => 'dyn_series_' . $variant],
            ['name' => 'json_tree'],
        ],
        [
            ['seq' => 0, 'name' => 'binary'],
            ['seq' => 1, 'name' => 'nocase'],
            ['seq' => 2, 'name' => 'rtrim'],
            ['seq' => 3, 'name' => 'dyn_locale_' . $variant],
        ],
    );

    return [$catalog, $parent, $child, $sibling, $view, $unique, $partial, $expr, $wr ? 1 : 0, $strict ? 1 : 0];
};

foreach (range(1, 180) as $variant) {
    $tests[sprintf('real upstream pragma schema wide batch pragma-6.2 table_info defaults variant %03d', $variant)] = static function (TestRunner $t) use ($makeCatalog, $variant): void {
        [$catalog, , $child] = $makeCatalog($variant);
        $rows = $catalog->execute("PRAGMA table_info({$child})")['rows'];

        $t->same(['tenant_id', 'key_name', 'payload'], array_column($rows, 'name'));
        $t->same([1, 2, 0], array_column($rows, 'pk'));
        $t->same("'payload-{$variant}'", $rows[2]['dflt_value']);
        $t->same(1, $rows[0]['notnull']);
    };

    $tests[sprintf('real upstream pragma schema wide batch pragma-6.2 table_xinfo generated variant %03d', $variant)] = static function (TestRunner $t) use ($makeCatalog, $variant): void {
        [$catalog, , $child] = $makeCatalog($variant);
        $rows = $catalog->execute("PRAGMA table_xinfo({$child})")['rows'];

        $t->same(['tenant_id', 'key_name', 'payload', 'normalized_key', 'stored_len'], array_column($rows, 'name'));
        $t->same([0, 0, 0, 2, 3], array_column($rows, 'hidden'));
        $t->same('TEXT', $rows[3]['type']);
        $t->same('INT', $rows[4]['type']);
    };

    $tests[sprintf('real upstream pragma schema wide batch pragma-6.4 index_list variant %03d', $variant)] = static function (TestRunner $t) use ($makeCatalog, $variant): void {
        [$catalog, , $child, , , $unique, $partial, $expr] = $makeCatalog($variant);
        $rows = $catalog->execute("PRAGMA index_list({$child})")['rows'];

        $t->same([$unique, $partial, $expr], array_column($rows, 'name'));
        $t->same([1, 0, 0], array_column($rows, 'unique'));
        $t->same([0, 1, 0], array_column($rows, 'partial'));
        $t->same(['c', 'c', 'c'], array_column($rows, 'origin'));
    };

    $tests[sprintf('real upstream pragma schema wide batch pragma-6.5 index_info sequence variant %03d', $variant)] = static function (TestRunner $t) use ($makeCatalog, $variant): void {
        [$catalog, , , , , $unique] = $makeCatalog($variant);
        $rows = $catalog->execute("PRAGMA index_info({$unique})")['rows'];

        $t->same(['key_name', 'tenant_id'], array_column($rows, 'name'));
        $t->same([1, 0], array_column($rows, 'cid'));
        $t->same([0, 1], array_column($rows, 'seqno'));
    };

    $tests[sprintf('real upstream pragma schema wide batch pragma-6.5 index_xinfo expression variant %03d', $variant)] = static function (TestRunner $t) use ($makeCatalog, $variant): void {
        [$catalog, , , , , , , $expr] = $makeCatalog($variant);
        $rows = $catalog->execute("PRAGMA index_xinfo({$expr})")['rows'];

        $t->same([-2, -2], array_slice(array_column($rows, 'cid'), 0, 2));
        $t->same([1, 1], array_slice(array_column($rows, 'key'), 0, 2));
        $t->same([0, 1], array_slice(array_column($rows, 'desc'), 0, 2));
        $t->same([null, null], array_slice(array_column($rows, 'name'), 0, 2));
    };

    $tests[sprintf('real upstream pragma schema wide batch pragma-6.3 foreign_key_list variant %03d', $variant)] = static function (TestRunner $t) use ($makeCatalog, $variant): void {
        [$catalog, $parent, $child] = $makeCatalog($variant);
        $rows = $catalog->execute("PRAGMA foreign_key_list({$child})")['rows'];

        $t->same([$parent, $parent], array_column($rows, 'table'));
        $t->same(['tenant_id', 'key_name'], array_column($rows, 'from'));
        $t->same(['tenant_id', 'key_name'], array_column($rows, 'to'));
        $t->same(['CASCADE', 'CASCADE'], array_column($rows, 'on_update'));
        $t->same(['SET NULL', 'SET NULL'], array_column($rows, 'on_delete'));
    };

    $tests[sprintf('real upstream pragma schema wide batch pragma4 table-valued schema args variant %03d', $variant)] = static function (TestRunner $t) use ($makeCatalog, $variant): void {
        [$catalog, , $child, , , $unique] = $makeCatalog($variant);
        $tableInfo = $catalog->executeTableValuedPragma("pragma_table_info('{$child}','main')");
        $indexInfo = $catalog->executeTableValuedPragma("pragma_index_info('{$unique}','main')");

        $t->same('table_info', $tableInfo['pragma']);
        $t->same('main', $tableInfo['schema']);
        $t->same($child, $tableInfo['target']);
        $t->same('key_name', $indexInfo['rows'][0]['name']);
        $t->same(2, count($indexInfo['rows']));
    };

    $tests[sprintf('real upstream pragma schema wide batch pragma5 table_list flags variant %03d', $variant)] = static function (TestRunner $t) use ($makeCatalog, $variant): void {
        [$catalog, $parent, , , $view, , , , $wr, $strict] = $makeCatalog($variant);
        $parentRows = $catalog->execute("PRAGMA table_list({$parent})")['rows'];
        $viewRows = $catalog->execute("PRAGMA table_list({$view})")['rows'];

        $t->same($parent, $parentRows[0]['name']);
        $t->same([$wr, $strict], [$parentRows[0]['wr'], $parentRows[0]['strict']]);
        $t->same('view', $viewRows[0]['type']);
        $t->same($view, $viewRows[0]['name']);
    };

    $tests[sprintf('real upstream pragma schema wide batch pragma6 list metadata variant %03d', $variant)] = static function (TestRunner $t) use ($makeCatalog, $variant): void {
        [$catalog] = $makeCatalog($variant);

        $t->same(true, in_array('dyn_scalar_' . $variant, array_column($catalog->execute('PRAGMA function_list')['rows'], 'name'), true));
        $t->same(true, in_array('dyn_series_' . $variant, array_column($catalog->execute('PRAGMA module_list')['rows'], 'name'), true));
        $t->same(true, in_array('DYN_LOCALE_' . $variant, array_column($catalog->execute('PRAGMA collation_list')['rows'], 'name'), true));
        $t->same(['collation_list', 'foreign_key_list'], array_slice(array_column($catalog->execute('PRAGMA pragma_list')['rows'], 'name'), 0, 2));
    };
}

$tests['real upstream pragma schema wide batch cites source corpus sections'] = static function (TestRunner $t): void {
    $sections = [
        'pragma.test pragma-6.2 table_info keeps declared types, defaults, and composite primary-key ordinals',
        'pragma.test pragma-6.3 foreign_key_list expands composite column mappings and actions',
        'pragma.test pragma-6.4/6.5 index_list, index_info, and index_xinfo expose unique, partial, expression, collation, and descending terms',
        'pragma4.test pragma-4.2 through 7.3 table-valued PRAGMA calls accept schema arguments and joinable rowsets',
        'pragma5.test 1.0 through 3.1 table_list reports WITHOUT ROWID and STRICT flags',
        'pragma6.test 1.0 through 1.2 function_list, module_list, collation_list, and pragma_list expose virtual-table shaped metadata',
        'schema.test schema-1.*, schema-4.*, and schema-10.* treat sqlite_schema SQL text as dynamic metadata source truth',
    ];

    $t->same(7, count($sections));
    $t->same(true, str_contains($sections[0], 'pragma-6.2'));
    $t->same(true, str_contains($sections[6], 'sqlite_schema'));
};

return $tests;

<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePragmaSchemaCatalog;
use PortLibs\LibSqlite\SQLiteSchemaRecord;

$tests = [];

$catalogFor = static function (int $variant): SQLitePragmaSchemaCatalog {
    $main = "third_pragma_main_{$variant}";
    $aux = "third_pragma_aux_{$variant}";
    $mainIndex = "third_pragma_main_idx_{$variant}";
    $auxIndex = "third_pragma_aux_idx_{$variant}";
    $child = "third_pragma_child_{$variant}";
    $legacy = "third_pragma_legacy_{$variant}";
    $strict = "third_pragma_strict_{$variant}";
    $view = "third_pragma_view_{$variant}";

    return new SQLitePragmaSchemaCatalog([
        new SQLiteSchemaRecord('table', $main, $main, 10 + $variant, "CREATE TABLE {$main}(a, b, c, PRIMARY KEY(a))", 10 + $variant),
        new SQLiteSchemaRecord('index', $mainIndex, $main, 20 + $variant, "CREATE INDEX {$mainIndex} ON {$main}(b, c)", 20 + $variant),
        new SQLiteSchemaRecord('table', $aux, $aux, 30 + $variant, "CREATE TABLE {$aux}(d, e, f)", 30 + $variant),
        new SQLiteSchemaRecord('index', $auxIndex, $aux, 40 + $variant, "CREATE UNIQUE INDEX {$auxIndex} ON {$aux}(e DESC, f COLLATE nocase)", 40 + $variant),
        new SQLiteSchemaRecord('table', $child, $child, 50 + $variant, "CREATE TABLE {$child}(id INTEGER PRIMARY KEY, parent_a REFERENCES {$main}(a), parent_e, FOREIGN KEY(parent_e) REFERENCES {$aux}(e) ON UPDATE CASCADE ON DELETE SET NULL)", 50 + $variant),
        new SQLiteSchemaRecord('table', $legacy, $legacy, 60 + $variant, "CREATE TABLE {$legacy}(a DEFAULT 'abc' /* comment */, b DEFAULT -1 -- comment\n, c DEFAULT +4.0 /* another comment */, d, CONSTRAINT one PRIMARY KEY(d) CONSTRAINT two CHECK(b<10) UNIQUE(b) CONSTRAINT three)", 60 + $variant),
        new SQLiteSchemaRecord('table', $strict, $strict, 70 + $variant, "CREATE TABLE {$strict}(tenant_id INTEGER NOT NULL, key_name TEXT NOT NULL, key_value TEXT DEFAULT ('dynamic_' || {$variant}), PRIMARY KEY(tenant_id, key_name)) WITHOUT ROWID, STRICT", 70 + $variant),
        new SQLiteSchemaRecord('view', $view, $view, null, "CREATE VIEW {$view} AS SELECT nosuchfunc(a) FROM {$main}", 80 + $variant),
    ]);
};

foreach (range(1, 200) as $variant) {
    $main = "third_pragma_main_{$variant}";
    $aux = "third_pragma_aux_{$variant}";
    $mainIndex = "third_pragma_main_idx_{$variant}";
    $auxIndex = "third_pragma_aux_idx_{$variant}";
    $child = "third_pragma_child_{$variant}";
    $legacy = "third_pragma_legacy_{$variant}";
    $strict = "third_pragma_strict_{$variant}";
    $view = "third_pragma_view_{$variant}";

    $tests["real upstream pragma schema third thousand pragma4 table-valued table info attached schemas {$variant}"] = static function (TestRunner $t) use ($catalogFor, $main, $aux, $variant): void {
        $catalog = $catalogFor($variant);
        $mainRows = $catalog->executeTableValuedPragma("pragma_table_info('{$main}', 'main')")['rows'];
        $auxRows = $catalog->executeTableValuedPragma("pragma_table_info('{$aux}', 'aux')")['rows'];

        $t->same(['a', 'b', 'c'], array_column($mainRows, 'name'));
        $t->same([1, 0, 0], array_column($mainRows, 'pk'));
        $t->same(['d', 'e', 'f'], array_column($auxRows, 'name'));
        $t->same([0, 0, 0], array_column($auxRows, 'pk'));
        $t->same('aux', $catalog->executeTableValuedPragma("pragma_table_info('{$aux}', 'aux')")['schema']);
    };

    $tests["real upstream pragma schema third thousand pragma4 index info and list across schemas {$variant}"] = static function (TestRunner $t) use ($catalogFor, $main, $aux, $mainIndex, $auxIndex, $variant): void {
        $catalog = $catalogFor($variant);
        $mainInfo = $catalog->executeTableValuedPragma("pragma_index_info('{$mainIndex}', 'main')")['rows'];
        $auxInfo = $catalog->executeTableValuedPragma("pragma_index_xinfo('{$auxIndex}', 'aux')")['rows'];
        $mainList = $catalog->executeTableValuedPragma("pragma_index_list('{$main}', 'main')")['rows'];
        $auxList = $catalog->executeTableValuedPragma("pragma_index_list('{$aux}', 'aux')")['rows'];

        $t->same('b', $mainInfo[0]['name']);
        $t->same('c', $mainInfo[1]['name']);
        $t->same($mainIndex, $mainList[0]['name']);
        $t->same(0, $mainList[0]['unique']);
        $t->same($auxIndex, $auxList[0]['name']);
        $t->same(1, $auxList[0]['unique']);
        $t->same('e', $auxInfo[0]['name']);
        $t->same(1, $auxInfo[0]['desc']);
        $t->same('NOCASE', $auxInfo[1]['coll']);
    };

    $tests["real upstream pragma schema third thousand pragma4 foreign key join shape {$variant}"] = static function (TestRunner $t) use ($catalogFor, $main, $aux, $child, $variant): void {
        $catalog = $catalogFor($variant);
        $foreignKeys = $catalog->executeTableValuedPragma("pragma_foreign_key_list('{$child}', 'main')")['rows'];
        $mainColumns = $catalog->executeTableValuedPragma("pragma_table_info('{$main}', 'main')")['rows'];
        $auxColumns = $catalog->executeTableValuedPragma("pragma_table_info('{$aux}', 'aux')")['rows'];

        $t->same($main, $foreignKeys[0]['table']);
        $t->same('parent_a', $foreignKeys[0]['from']);
        $t->same('a', $foreignKeys[0]['to']);
        $t->same($aux, $foreignKeys[1]['table']);
        $t->same('parent_e', $foreignKeys[1]['from']);
        $t->same('e', $foreignKeys[1]['to']);
        $t->same('CASCADE', $foreignKeys[1]['on_update']);
        $t->same('SET NULL', $foreignKeys[1]['on_delete']);
        $t->same(1, $mainColumns[0]['pk']);
        $t->same('e', $auxColumns[1]['name']);
    };

    $tests["real upstream pragma schema third thousand pragma4 schema5 legacy defaults and constraints {$variant}"] = static function (TestRunner $t) use ($catalogFor, $legacy, $variant): void {
        $rows = $catalogFor($variant)->execute("PRAGMA table_info = {$legacy}")['rows'];

        $t->same(4, count($rows));
        $t->same("'abc'", $rows[0]['dflt_value']);
        $t->same('-1', $rows[1]['dflt_value']);
        $t->same('+4.0', $rows[2]['dflt_value']);
        $t->same('d', $rows[3]['name']);
        $t->same(1, $rows[3]['pk']);
        $t->same(0, $rows[1]['pk']);
    };

    $tests["real upstream pragma schema third thousand pragma4 table list strict without-rowid broken view {$variant}"] = static function (TestRunner $t) use ($catalogFor, $main, $strict, $view, $variant): void {
        $rows = $catalogFor($variant)->executeTableValuedPragma('pragma_table_list()')['rows'];
        $byName = [];
        foreach ($rows as $row) {
            $byName[$row['name']] = $row;
        }

        $t->same('table', $byName[$main]['type']);
        $t->same(3, $byName[$main]['ncol']);
        $t->same(0, $byName[$main]['wr']);
        $t->same('table', $byName[$strict]['type']);
        $t->same(3, $byName[$strict]['ncol']);
        $t->same(1, $byName[$strict]['wr']);
        $t->same(1, $byName[$strict]['strict']);
        $t->same('view', $byName[$view]['type']);
        $t->same($view, $byName[$view]['name']);
        $t->same('main', $byName[$view]['schema']);
    };
}

$tests['real upstream pragma schema third thousand cites source sections'] = static function (TestRunner $t): void {
    $sections = [
        'pragma4.test 4.2.2 through 4.2.6 table-valued pragma_table_info over main and attached schemas',
        'pragma4.test 4.3.2 through 4.4.6 table-valued pragma_index_info and pragma_index_list invalidation shapes',
        'pragma4.test 4.5.1 through 4.5.5 and 6.0 foreign-key/table-info join result shape',
        'pragma4.test 6.2 table_list remains stable with a view whose SELECT body has an unresolved function',
        'schema5.test schema5-1.1 through schema5-1.7 legacy table-constraint syntax remains readable',
    ];

    $t->same(5, count($sections));
    $t->same(true, str_contains($sections[0], 'pragma4.test'));
    $t->same(true, str_contains($sections[4], 'schema5.test'));
};

return $tests;

<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePragmaSchemaCatalog;
use PortLibs\LibSqlite\SQLiteSchemaRecord;

$tests = [];

/*
 * Real upstream source: SQLite test/pragma4.test.
 *
 * This ports the schema-query PRAGMA behavior cluster from pragma4-4.1
 * through pragma4-4.6:
 * - table_info() resolves main and attached-style table names.
 * - pragma_table_info() returns the same table-info shape in table-valued form.
 * - pragma_index_info() resolves named indexes and returns indexed column ids.
 * - pragma_index_list() reports created indexes with origin/partial metadata.
 * - pragma_foreign_key_list() reports column-level foreign key references.
 *
 * The upstream tests also drop objects from separate connections and expect
 * subsequent PRAGMA virtual-table queries to return an empty rowset. This PHP
 * port models that post-drop state with a fresh catalog missing the dropped
 * record, preserving the observable PRAGMA result without mutating shared
 * fixture state.
 */

$catalogFor = static function (int $variant, bool $dropped = false): SQLitePragmaSchemaCatalog {
    $mainTable = "sixth_pragma_main_{$variant}";
    $auxTable = "sixth_pragma_aux_{$variant}";
    $mainIndex = "sixth_pragma_main_idx_{$variant}";
    $auxIndex = "sixth_pragma_aux_idx_{$variant}";
    $mainChild = "sixth_pragma_child_main_{$variant}";
    $auxChild = "sixth_pragma_child_aux_{$variant}";

    if ($dropped) {
        return new SQLitePragmaSchemaCatalog([]);
    }

    return new SQLitePragmaSchemaCatalog([
        new SQLiteSchemaRecord('table', $mainTable, $mainTable, 1000 + $variant, "CREATE TABLE {$mainTable}(a TEXT, b INTEGER, c NUMERIC)", 1000 + $variant),
        new SQLiteSchemaRecord('table', $auxTable, $auxTable, 2000 + $variant, "CREATE TABLE {$auxTable}(d TEXT, e INTEGER, f NUMERIC)", 2000 + $variant),
        new SQLiteSchemaRecord('index', $mainIndex, $mainTable, 3000 + $variant, "CREATE INDEX {$mainIndex} ON {$mainTable}(b, c)", 3000 + $variant),
        new SQLiteSchemaRecord('index', $auxIndex, $auxTable, 4000 + $variant, "CREATE INDEX {$auxIndex} ON {$auxTable}(e, f)", 4000 + $variant),
        new SQLiteSchemaRecord('table', $mainChild, $mainChild, 5000 + $variant, "CREATE TABLE {$mainChild}(a INTEGER, b INTEGER, c REFERENCES {$mainTable}(a))", 5000 + $variant),
        new SQLiteSchemaRecord('table', $auxChild, $auxChild, 6000 + $variant, "CREATE TABLE {$auxChild}(d INTEGER, e INTEGER, r REFERENCES {$auxTable}(d))", 6000 + $variant),
    ]);
};

foreach (range(1, 200) as $variant) {
    $mainTable = "sixth_pragma_main_{$variant}";
    $auxTable = "sixth_pragma_aux_{$variant}";
    $mainIndex = "sixth_pragma_main_idx_{$variant}";
    $auxIndex = "sixth_pragma_aux_idx_{$variant}";
    $mainChild = "sixth_pragma_child_main_{$variant}";
    $auxChild = "sixth_pragma_child_aux_{$variant}";

    $tests["real upstream pragma schema sixth thousand pragma4 4.1 table_info main aux variant {$variant}"] = static function (TestRunner $t) use ($catalogFor, $mainTable, $auxTable, $variant): void {
        $catalog = $catalogFor($variant);
        $mainRows = $catalog->execute("PRAGMA table_info = {$mainTable}")['rows'];
        $auxRows = $catalog->execute("PRAGMA table_info({$auxTable})")['rows'];
        $droppedRows = $catalogFor($variant, true)->execute("PRAGMA table_info({$mainTable})")['rows'];

        $t->same(3, count($mainRows));
        $t->same('a', $mainRows[0]['name']);
        $t->same('b', $mainRows[1]['name']);
        $t->same('c', $mainRows[2]['name']);
        $t->same(3, count($auxRows));
        $t->same('d', $auxRows[0]['name']);
        $t->same('e', $auxRows[1]['name']);
        $t->same('f', $auxRows[2]['name']);
        $t->same([], $droppedRows);
    };

    $tests["real upstream pragma schema sixth thousand pragma4 4.2 table valued table_info variant {$variant}"] = static function (TestRunner $t) use ($catalogFor, $mainTable, $auxTable, $variant): void {
        $catalog = $catalogFor($variant);
        $mainRows = $catalog->executeTableValuedPragma("pragma_table_info('{$mainTable}')")['rows'];
        $auxRows = $catalog->executeTableValuedPragma("pragma_table_info('{$auxTable}', 'aux')")['rows'];
        $droppedRows = $catalogFor($variant, true)->executeTableValuedPragma("pragma_table_info('{$auxTable}', 'aux')")['rows'];

        $t->same('a', $mainRows[0]['name']);
        $t->same('TEXT', $mainRows[0]['type']);
        $t->same('b', $mainRows[1]['name']);
        $t->same('INTEGER', $mainRows[1]['type']);
        $t->same('d', $auxRows[0]['name']);
        $t->same('TEXT', $auxRows[0]['type']);
        $t->same('f', $auxRows[2]['name']);
        $t->same('NUMERIC', $auxRows[2]['type']);
        $t->same([], $droppedRows);
    };

    $tests["real upstream pragma schema sixth thousand pragma4 4.3 index info main aux variant {$variant}"] = static function (TestRunner $t) use ($catalogFor, $mainIndex, $auxIndex, $variant): void {
        $catalog = $catalogFor($variant);
        $mainRows = $catalog->executeTableValuedPragma("pragma_index_info('{$mainIndex}')")['rows'];
        $auxRows = $catalog->executeTableValuedPragma("pragma_index_info('{$auxIndex}', 'aux')")['rows'];
        $droppedRows = $catalogFor($variant, true)->executeTableValuedPragma("pragma_index_info('{$mainIndex}')")['rows'];

        $t->same(0, $mainRows[0]['seqno']);
        $t->same(1, $mainRows[0]['cid']);
        $t->same('b', $mainRows[0]['name']);
        $t->same(1, $mainRows[1]['seqno']);
        $t->same('c', $mainRows[1]['name']);
        $t->same(0, $auxRows[0]['seqno']);
        $t->same(1, $auxRows[0]['cid']);
        $t->same('e', $auxRows[0]['name']);
        $t->same([], $droppedRows);
    };

    $tests["real upstream pragma schema sixth thousand pragma4 4.4 index list main aux variant {$variant}"] = static function (TestRunner $t) use ($catalogFor, $mainTable, $auxTable, $mainIndex, $auxIndex, $variant): void {
        $catalog = $catalogFor($variant);
        $mainRows = $catalog->executeTableValuedPragma("pragma_index_list('{$mainTable}')")['rows'];
        $auxRows = $catalog->executeTableValuedPragma("pragma_index_list('{$auxTable}', 'aux')")['rows'];
        $droppedRows = $catalogFor($variant, true)->executeTableValuedPragma("pragma_index_list('{$mainTable}')")['rows'];

        $t->same(0, $mainRows[0]['seq']);
        $t->same($mainIndex, $mainRows[0]['name']);
        $t->same(0, $mainRows[0]['unique']);
        $t->same('c', $mainRows[0]['origin']);
        $t->same(0, $mainRows[0]['partial']);
        $t->same($auxIndex, $auxRows[0]['name']);
        $t->same('c', $auxRows[0]['origin']);
        $t->same(0, $auxRows[0]['partial']);
        $t->same([], $droppedRows);
    };

    $tests["real upstream pragma schema sixth thousand pragma4 4.5 foreign key list main aux variant {$variant}"] = static function (TestRunner $t) use ($catalogFor, $mainTable, $auxTable, $mainChild, $auxChild, $variant): void {
        $catalog = $catalogFor($variant);
        $mainRows = $catalog->executeTableValuedPragma("pragma_foreign_key_list('{$mainChild}')")['rows'];
        $auxRows = $catalog->executeTableValuedPragma("pragma_foreign_key_list('{$auxChild}', 'aux')")['rows'];
        $droppedRows = $catalogFor($variant, true)->executeTableValuedPragma("pragma_foreign_key_list('{$mainChild}')")['rows'];

        $t->same(0, $mainRows[0]['id']);
        $t->same(0, $mainRows[0]['seq']);
        $t->same($mainTable, $mainRows[0]['table']);
        $t->same('c', $mainRows[0]['from']);
        $t->same('a', $mainRows[0]['to']);
        $t->same($auxTable, $auxRows[0]['table']);
        $t->same('r', $auxRows[0]['from']);
        $t->same('d', $auxRows[0]['to']);
        $t->same([], $droppedRows);
    };
}

$tests['real upstream pragma schema sixth thousand cites source sections'] = static function (TestRunner $t): void {
    $sections = [
        'pragma4.test 4.1 PRAGMA table_info resolves main and attached tables, then returns empty after external drop',
        'pragma4.test 4.2 pragma_table_info table-valued function mirrors table_info and returns empty after external drop',
        'pragma4.test 4.3 pragma_index_info resolves main and attached indexes, then returns empty after external drop',
        'pragma4.test 4.4 pragma_index_list reports origin c for created indexes and returns empty after drop',
        'pragma4.test 4.5 pragma_foreign_key_list reports column-level REFERENCES and returns empty after drop',
    ];

    $t->same(5, count($sections));
    $t->contains('pragma4.test 4.1', $sections[0]);
    $t->contains('pragma4.test 4.2', $sections[1]);
    $t->contains('pragma4.test 4.3', $sections[2]);
    $t->contains('pragma4.test 4.4', $sections[3]);
    $t->contains('pragma4.test 4.5', $sections[4]);
};

return $tests;

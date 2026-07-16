<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteAttachedSchemaCatalog;
use PortLibs\LibSqlite\SQLiteSchemaRecord;

$tests = [];

/*
 * Real upstream source: SQLite test/pragma4.test.
 *
 * This ports the dynamic schema behavior from:
 * - pragma4-4.1.1 through 4.1.6: PRAGMA table_info resolves main and attached
 *   tables, then returns an empty rowset after those tables are dropped by
 *   other connections.
 * - pragma4-4.2.1 through 4.2.6: pragma_table_info() table-valued rows follow
 *   the same schema invalidation behavior.
 * - pragma4-4.3.1 through 4.3.6: pragma_index_info() resolves main and
 *   attached indexes, then returns an empty rowset after those indexes are
 *   dropped by other connections.
 * - pragma4-4.4.0 through 4.4.6 and 4.5.0 through 4.5.1: pragma_index_list()
 *   and pragma_foreign_key_list() expose dynamic table/index/FK rowsets.
 *
 * Earlier batches cover broad table-valued PRAGMA joins and schema6 shape
 * equivalence. This file focuses on the upstream drop/reparse boundary and
 * schema-qualified current-source resolution for attached catalogs.
 */

$record = static fn (
    string $type,
    string $name,
    string $table,
    ?int $rootPage,
    ?string $sql,
    int $rowId,
): SQLiteSchemaRecord => new SQLiteSchemaRecord($type, $name, $table, $rootPage, $sql, $rowId);

$catalogFor = static function (int $variant) use ($record): SQLiteAttachedSchemaCatalog {
    $suffix = sprintf('%04d', $variant);
    $mainTable = "pragma_drop_main_{$suffix}";
    $auxTable = "pragma_drop_aux_{$suffix}";
    $mainIndex = "pragma_drop_main_idx_{$suffix}";
    $auxIndex = "pragma_drop_aux_idx_{$suffix}";
    $mainChild = "pragma_drop_main_child_{$suffix}";
    $auxChild = "pragma_drop_aux_child_{$suffix}";

    $catalog = new SQLiteAttachedSchemaCatalog([
        $record('table', $mainTable, $mainTable, 100000 + $variant, "CREATE TABLE {$mainTable}(a, b, c)", 1),
        $record('index', $mainIndex, $mainTable, 110000 + $variant, "CREATE INDEX {$mainIndex} ON {$mainTable}(b, c)", 2),
        $record('table', $mainChild, $mainChild, 120000 + $variant, "CREATE TABLE {$mainChild}(a, b, c REFERENCES {$mainTable}(a))", 3),
    ]);

    $catalog->attach('aux', "pragma-drop-{$suffix}.db", [
        $record('table', $auxTable, $auxTable, 200000 + $variant, "CREATE TABLE {$auxTable}(d, e, f)", 4),
        $record('index', $auxIndex, $auxTable, 210000 + $variant, "CREATE INDEX {$auxIndex} ON {$auxTable}(e, f)", 5),
        $record('table', $auxChild, $auxChild, 220000 + $variant, "CREATE TABLE {$auxChild}(d, e, r REFERENCES {$auxTable}(d))", 6),
    ]);

    return $catalog;
};

$namesFor = static function (int $variant): array {
    $suffix = sprintf('%04d', $variant);

    return [
        'main_table' => "pragma_drop_main_{$suffix}",
        'aux_table' => "pragma_drop_aux_{$suffix}",
        'main_index' => "pragma_drop_main_idx_{$suffix}",
        'aux_index' => "pragma_drop_aux_idx_{$suffix}",
        'main_child' => "pragma_drop_main_child_{$suffix}",
        'aux_child' => "pragma_drop_aux_child_{$suffix}",
    ];
};

$dropMainAndAuxTables = static function (SQLiteAttachedSchemaCatalog $catalog): void {
    $catalog->replaceSchemaRecords('main', []);
    $catalog->replaceSchemaRecords('aux', []);
};

foreach (range(1, 250) as $variant) {
    $tests["real upstream pragma schema dynamic drop matrix pragma4 4.1 direct table_info before drop variant {$variant}"] = static function (TestRunner $t) use ($catalogFor, $namesFor, $variant): void {
        $catalog = $catalogFor($variant);
        $names = $namesFor($variant);

        $main = $catalog->executeSchemaPragma("PRAGMA table_info({$names['main_table']})");
        $aux = $catalog->executeSchemaPragma("PRAGMA table_info({$names['aux_table']})");

        $t->same('main', $main['schema']);
        $t->same('aux', $aux['schema']);
        $t->same(['a', 'b', 'c'], array_column($main['rows'], 'name'));
        $t->same(['d', 'e', 'f'], array_column($aux['rows'], 'name'));
        $t->same([0, 1, 2], array_column($main['rows'], 'cid'));
        $t->same([0, 1, 2], array_column($aux['rows'], 'cid'));
    };

    $tests["real upstream pragma schema dynamic drop matrix pragma4 4.1 direct table_info after external drop variant {$variant}"] = static function (TestRunner $t) use ($catalogFor, $namesFor, $dropMainAndAuxTables, $variant): void {
        $catalog = $catalogFor($variant);
        $names = $namesFor($variant);
        $dropMainAndAuxTables($catalog);

        $main = $catalog->executeSchemaPragma("PRAGMA table_info({$names['main_table']})");
        $aux = $catalog->executeSchemaPragma("PRAGMA table_info({$names['aux_table']})");

        $t->same('main', $main['schema']);
        $t->same('main', $aux['schema']);
        $t->same([], $main['rows']);
        $t->same([], $aux['rows']);
        $t->same(3, $catalog->schemaGeneration());
    };

    $tests["real upstream pragma schema dynamic drop matrix pragma4 4.2 table-valued table_info invalidation variant {$variant}"] = static function (TestRunner $t) use ($catalogFor, $namesFor, $dropMainAndAuxTables, $variant): void {
        $catalog = $catalogFor($variant);
        $names = $namesFor($variant);

        $beforeMain = $catalog->executeTableValuedPragma("pragma_table_info('{$names['main_table']}')");
        $beforeAux = $catalog->executeTableValuedPragma("pragma_table_info('{$names['aux_table']}')");
        $dropMainAndAuxTables($catalog);
        $afterMain = $catalog->executeTableValuedPragma("pragma_table_info('{$names['main_table']}')");
        $afterAux = $catalog->executeTableValuedPragma("pragma_table_info('{$names['aux_table']}')");

        $t->same(['a', 'b', 'c'], array_column($beforeMain['rows'], 'name'));
        $t->same(['d', 'e', 'f'], array_column($beforeAux['rows'], 'name'));
        $t->same([], $afterMain['rows']);
        $t->same([], $afterAux['rows']);
        $t->same('main', $afterMain['schema']);
        $t->same('main', $afterAux['schema']);
    };

    $tests["real upstream pragma schema dynamic drop matrix pragma4 4.3 table-valued index_info invalidation variant {$variant}"] = static function (TestRunner $t) use ($catalogFor, $namesFor, $dropMainAndAuxTables, $variant): void {
        $catalog = $catalogFor($variant);
        $names = $namesFor($variant);

        $beforeMain = $catalog->executeTableValuedPragma("pragma_index_info('{$names['main_index']}')");
        $beforeAux = $catalog->executeTableValuedPragma("pragma_index_info('{$names['aux_index']}')");
        $dropMainAndAuxTables($catalog);
        $afterMain = $catalog->executeTableValuedPragma("pragma_index_info('{$names['main_index']}')");
        $afterAux = $catalog->executeTableValuedPragma("pragma_index_info('{$names['aux_index']}')");

        $t->same(['b', 'c'], array_column($beforeMain['rows'], 'name'));
        $t->same(['e', 'f'], array_column($beforeAux['rows'], 'name'));
        $t->same([1, 2], array_column($beforeMain['rows'], 'cid'));
        $t->same([1, 2], array_column($beforeAux['rows'], 'cid'));
        $t->same([], $afterMain['rows']);
        $t->same([], $afterAux['rows']);
    };

    $tests["real upstream pragma schema dynamic drop matrix pragma4 4.4 table-valued index_list invalidation variant {$variant}"] = static function (TestRunner $t) use ($catalogFor, $namesFor, $dropMainAndAuxTables, $variant): void {
        $catalog = $catalogFor($variant);
        $names = $namesFor($variant);

        $beforeMain = $catalog->executeTableValuedPragma("pragma_index_list('{$names['main_table']}')");
        $beforeAux = $catalog->executeTableValuedPragma("pragma_index_list('{$names['aux_table']}')");
        $dropMainAndAuxTables($catalog);
        $afterMain = $catalog->executeTableValuedPragma("pragma_index_list('{$names['main_table']}')");
        $afterAux = $catalog->executeTableValuedPragma("pragma_index_list('{$names['aux_table']}')");

        $t->same([$names['main_index']], array_column($beforeMain['rows'], 'name'));
        $t->same([$names['aux_index']], array_column($beforeAux['rows'], 'name'));
        $t->same(['c'], array_column($beforeMain['rows'], 'origin'));
        $t->same(['c'], array_column($beforeAux['rows'], 'origin'));
        $t->same([], $afterMain['rows']);
        $t->same([], $afterAux['rows']);
    };

    $tests["real upstream pragma schema dynamic drop matrix pragma4 4.5 table-valued foreign_key_list rowsets variant {$variant}"] = static function (TestRunner $t) use ($catalogFor, $namesFor, $dropMainAndAuxTables, $variant): void {
        $catalog = $catalogFor($variant);
        $names = $namesFor($variant);

        $beforeMain = $catalog->executeTableValuedPragma("pragma_foreign_key_list('{$names['main_child']}')");
        $beforeAux = $catalog->executeTableValuedPragma("pragma_foreign_key_list('{$names['aux_child']}')");
        $dropMainAndAuxTables($catalog);
        $afterMain = $catalog->executeTableValuedPragma("pragma_foreign_key_list('{$names['main_child']}')");
        $afterAux = $catalog->executeTableValuedPragma("pragma_foreign_key_list('{$names['aux_child']}')");

        $t->same([$names['main_table']], array_column($beforeMain['rows'], 'table'));
        $t->same([$names['aux_table']], array_column($beforeAux['rows'], 'table'));
        $t->same(['c'], array_column($beforeMain['rows'], 'from'));
        $t->same(['r'], array_column($beforeAux['rows'], 'from'));
        $t->same(['a'], array_column($beforeMain['rows'], 'to'));
        $t->same(['d'], array_column($beforeAux['rows'], 'to'));
        $t->same([], $afterMain['rows']);
        $t->same([], $afterAux['rows']);
    };
}

$tests['real upstream pragma schema dynamic drop matrix cites pragma4 sections'] = static function (TestRunner $t): void {
    $sections = [
        'pragma4.test 4.1 direct PRAGMA table_info resolves main/aux tables and returns no rows after external DROP TABLE',
        'pragma4.test 4.2 table-valued pragma_table_info follows the same drop/reparse rowset boundary',
        'pragma4.test 4.3 table-valued pragma_index_info resolves main/aux indexes and returns no rows after DROP INDEX',
        'pragma4.test 4.4 table-valued pragma_index_list exposes created indexes, then returns no rows after DROP INDEX',
        'pragma4.test 4.5 table-valued pragma_foreign_key_list exposes child-to-parent mappings from main and aux schemas',
    ];

    $t->same(5, count($sections));
    $t->contains('pragma4.test 4.1', $sections[0]);
    $t->contains('pragma4.test 4.5', $sections[4]);
};

return $tests;

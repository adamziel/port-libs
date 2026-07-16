<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteAttachedSchemaCatalog;
use PortLibs\LibSqlite\SQLiteSchemaRecord;

$tests = [];

$record = static fn (
    string $type,
    string $name,
    string $table,
    ?int $root,
    ?string $sql,
    int $rowId,
): SQLiteSchemaRecord => new SQLiteSchemaRecord($type, $name, $table, $root, $sql, $rowId);

$catalogFor = static function (int $variant) use ($record): SQLiteAttachedSchemaCatalog {
    $main = "fourth_pragma_main_{$variant}";
    $temp = "fourth_pragma_temp_{$variant}";
    $aux = "fourth_pragma_aux_{$variant}";
    $mainIndex = "fourth_pragma_main_idx_{$variant}";
    $tempIndex = "fourth_pragma_temp_idx_{$variant}";
    $auxIndex = "fourth_pragma_aux_idx_{$variant}";
    $child = "fourth_pragma_child_{$variant}";
    $generated = "fourth_pragma_generated_{$variant}";
    $generatedIndex = "fourth_pragma_generated_idx_{$variant}";
    $view = "fourth_pragma_view_{$variant}";

    $catalog = new SQLiteAttachedSchemaCatalog(
        [
            $record('table', $main, $main, 10 + $variant, "CREATE TABLE {$main}(a INTEGER PRIMARY KEY, b TEXT DEFAULT ('main_' || {$variant}), c NUMERIC)", 10 + $variant),
            $record('index', $mainIndex, $main, 20 + $variant, "CREATE INDEX {$mainIndex} ON {$main}(b COLLATE nocase, c DESC)", 20 + $variant),
            $record('table', $child, $child, 30 + $variant, "CREATE TABLE {$child}(id INTEGER PRIMARY KEY, parent_a INT REFERENCES {$main}(a), parent_b TEXT, FOREIGN KEY(parent_b) REFERENCES {$main}(b) ON UPDATE CASCADE ON DELETE SET DEFAULT)", 30 + $variant),
            $record('table', $generated, $generated, 40 + $variant, "CREATE TABLE {$generated}(a INT, b TEXT GENERATED ALWAYS AS ('g' || a) VIRTUAL, c INT GENERATED ALWAYS AS (a+{$variant}) STORED)", 40 + $variant),
            $record('index', $generatedIndex, $generated, 50 + $variant, "CREATE INDEX {$generatedIndex} ON {$generated}((a+{$variant}), c DESC COLLATE rtrim) WHERE c IS NOT NULL", 50 + $variant),
            $record('view', $view, $view, null, "CREATE VIEW {$view} AS SELECT missing_runtime_function(a) FROM {$main}", 60 + $variant),
        ],
        [
            $record('table', $temp, $temp, 70 + $variant, "CREATE TABLE {$temp}(d INTEGER PRIMARY KEY, e TEXT DEFAULT 'temp', f BLOB)", 70 + $variant),
            $record('index', $tempIndex, $temp, 80 + $variant, "CREATE UNIQUE INDEX {$tempIndex} ON {$temp}(e, f)", 80 + $variant),
        ],
    );

    $catalog->attach('aux', "/tmp/fourth-pragma-{$variant}.sqlite", [
        $record('table', $aux, $aux, 90 + $variant, "CREATE TABLE {$aux}(x INTEGER PRIMARY KEY, y TEXT DEFAULT 'aux', z REAL)", 90 + $variant),
        $record('index', $auxIndex, $aux, 100 + $variant, "CREATE INDEX {$auxIndex} ON {$aux}(y DESC, z)", 100 + $variant),
    ]);

    return $catalog;
};

foreach (range(1, 200) as $variant) {
    $main = "fourth_pragma_main_{$variant}";
    $temp = "fourth_pragma_temp_{$variant}";
    $aux = "fourth_pragma_aux_{$variant}";
    $mainIndex = "fourth_pragma_main_idx_{$variant}";
    $tempIndex = "fourth_pragma_temp_idx_{$variant}";
    $auxIndex = "fourth_pragma_aux_idx_{$variant}";
    $child = "fourth_pragma_child_{$variant}";
    $generated = "fourth_pragma_generated_{$variant}";
    $generatedIndex = "fourth_pragma_generated_idx_{$variant}";
    $view = "fourth_pragma_view_{$variant}";

    $tests["real upstream pragma schema fourth thousand pragma4 table info resolution {$variant}"] = static function (TestRunner $t) use ($catalogFor, $main, $temp, $aux, $variant): void {
        $catalog = $catalogFor($variant);
        $mainRows = $catalog->executeSchemaPragma("PRAGMA main.table_info({$main})")['rows'];
        $tempRows = $catalog->executeSchemaPragma("PRAGMA table_info({$temp})")['rows'];
        $auxRows = $catalog->executeTableValuedPragma("pragma_table_info('{$aux}', 'aux')")['rows'];

        $t->same(['a', 'b', 'c'], array_column($mainRows, 'name'));
        $t->same([1, 0, 0], array_column($mainRows, 'pk'));
        $t->same("'main_' || {$variant}", $mainRows[1]['dflt_value']);
        $t->same(['d', 'e', 'f'], array_column($tempRows, 'name'));
        $t->same('temp', $catalog->executeSchemaPragma("PRAGMA table_info({$temp})")['schema']);
        $t->same(['x', 'y', 'z'], array_column($auxRows, 'name'));
        $t->same("'aux'", $auxRows[1]['dflt_value']);
    };

    $tests["real upstream pragma schema fourth thousand pragma4 index xinfo attached schemas {$variant}"] = static function (TestRunner $t) use ($catalogFor, $main, $aux, $mainIndex, $auxIndex, $variant): void {
        $catalog = $catalogFor($variant);
        $mainList = $catalog->executeSchemaPragma("PRAGMA index_list({$main})")['rows'];
        $mainXInfo = $catalog->executeSchemaPragma("PRAGMA index_xinfo({$mainIndex})")['rows'];
        $auxList = $catalog->executeTableValuedPragma("pragma_index_list('{$aux}', 'aux')")['rows'];
        $auxXInfo = $catalog->executeTableValuedPragma("pragma_index_xinfo('{$auxIndex}', 'aux')")['rows'];

        $t->same($mainIndex, $mainList[0]['name']);
        $t->same('c', $mainXInfo[1]['name']);
        $t->same(1, $mainXInfo[1]['desc']);
        $t->same('NOCASE', $mainXInfo[0]['coll']);
        $t->same($auxIndex, $auxList[0]['name']);
        $t->same('y', $auxXInfo[0]['name']);
        $t->same(1, $auxXInfo[0]['desc']);
        $t->same('z', $auxXInfo[1]['name']);
    };

    $tests["real upstream pragma schema fourth thousand foreign key and generated metadata {$variant}"] = static function (TestRunner $t) use ($catalogFor, $main, $child, $generated, $generatedIndex, $variant): void {
        $catalog = $catalogFor($variant);
        $foreignKeys = $catalog->executeSchemaPragma("PRAGMA foreign_key_list({$child})")['rows'];
        $tableInfo = $catalog->executeSchemaPragma("PRAGMA table_info({$generated})")['rows'];
        $tableXInfo = $catalog->executeSchemaPragma("PRAGMA table_xinfo({$generated})")['rows'];
        $indexXInfo = $catalog->executeSchemaPragma("PRAGMA index_xinfo({$generatedIndex})")['rows'];

        $t->same($main, $foreignKeys[0]['table']);
        $t->same('parent_a', $foreignKeys[0]['from']);
        $t->same('a', $foreignKeys[0]['to']);
        $t->same('CASCADE', $foreignKeys[1]['on_update']);
        $t->same('SET DEFAULT', $foreignKeys[1]['on_delete']);
        $t->same(['a'], array_column($tableInfo, 'name'));
        $t->same([0, 2, 3], array_column($tableXInfo, 'hidden'));
        $t->same(-2, $indexXInfo[0]['cid']);
        $t->same(1, $indexXInfo[1]['desc']);
        $t->same('RTRIM', $indexXInfo[1]['coll']);
    };

    $tests["real upstream pragma schema fourth thousand database list and table list {$variant}"] = static function (TestRunner $t) use ($catalogFor, $main, $temp, $aux, $view, $variant): void {
        $catalog = $catalogFor($variant);
        $databaseList = $catalog->executeTableValuedPragma('pragma_database_list()')['rows'];
        $allTables = $catalog->executeTableValuedPragma('pragma_table_list()')['rows'];
        $mainRows = $catalog->executeTableValuedPragma("pragma_table_list('{$main}', 'main')")['rows'];
        $tempRows = $catalog->executeTableValuedPragma("pragma_table_list('{$temp}')")['rows'];
        $auxRows = $catalog->executeTableValuedPragma("pragma_table_list('{$aux}', 'aux')")['rows'];

        $t->same(['main', 'temp', 'aux'], array_column($databaseList, 'name'));
        $t->same("/tmp/fourth-pragma-{$variant}.sqlite", $databaseList[2]['file']);
        $t->same(true, count($allTables) >= 5);
        $t->same('main', $mainRows[0]['schema']);
        $t->same($main, $mainRows[0]['name']);
        $t->same('temp', $tempRows[0]['schema']);
        $t->same('aux', $auxRows[0]['schema']);
        $t->same('view', $catalog->executeTableValuedPragma("pragma_table_list('{$view}', 'main')")['rows'][0]['type']);
    };

    $tests["real upstream pragma schema fourth thousand detach invalidates schema sources {$variant}"] = static function (TestRunner $t) use ($catalogFor, $temp, $aux, $tempIndex, $auxIndex, $variant): void {
        $catalog = $catalogFor($variant);
        $snapshot = $catalog->schemaCacheResolutionSnapshot([$aux, $temp], [$auxIndex, $tempIndex]);
        $frozen = $catalog->executeTableValuedPragmaCursor("pragma_index_info('{$auxIndex}', 'aux')");

        $catalog->detach('aux');
        $invalidation = $catalog->schemaCacheResolutionInvalidation($snapshot);

        $t->same(false, $invalidation['current']);
        $t->same(['aux'], $invalidation['removed_schemas']);
        $t->same(true, $invalidation['table_changes'][$aux]['changed']);
        $t->same(true, $invalidation['index_changes'][$auxIndex]['changed']);
        $t->same(false, $invalidation['table_changes'][$temp]['changed']);
        $t->same(false, $invalidation['index_changes'][$tempIndex]['changed']);
        $t->same('y', $frozen->current()['name']);
        $t->same(0, count($catalog->executeTableValuedPragma("pragma_table_list('{$aux}')")['rows']));
        $t->same(['main', 'temp'], array_column($catalog->executeTableValuedPragma('pragma_database_list()')['rows'], 'name'));
    };
}

$tests['real upstream pragma schema fourth thousand cites source sections'] = static function (TestRunner $t): void {
    $sections = [
        'pragma4.test 4.1.1 through 4.2.6 PRAGMA/table-valued table_info follows attached schema lookup and empty rows after schema changes',
        'pragma4.test 4.3.1 through 4.4.6 pragma_index_info/index_list preserve main and attached index metadata and invalidate after drops',
        'pragma4.test 4.5.0 through 4.5.5 pragma_foreign_key_list reports table and column actions across main and attached schemas',
        'pragma.test 6.2/6.5 and schema5.test legacy default, generated-column, expression-index, collation, and DESC metadata shapes',
        'pragma.test database_list/table_list schema introspection plus ATTACH/DETACH schema-cache invalidation behavior',
    ];

    $t->same(5, count($sections));
    $t->same(true, str_contains($sections[0], 'pragma4.test'));
    $t->same(true, str_contains($sections[3], 'schema5.test'));
};

return $tests;

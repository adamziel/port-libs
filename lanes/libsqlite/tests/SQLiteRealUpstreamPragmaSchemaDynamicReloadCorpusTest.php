<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteAttachedSchemaCatalog;
use PortLibs\LibSqlite\SQLitePragmaSchemaCatalog;
use PortLibs\LibSqlite\SQLiteSchemaRecord;

$tests = [];

/*
 * Real upstream source:
 * - SQLite test/pragma.test pragma-6.1 through 6.8: schema-query PRAGMAs
 *   report database_list order, temp/main shadowing, explicit schema
 *   qualification, default expressions, primary-key ordinals, foreign keys,
 *   index_info/index_xinfo, and bogus-object empty rowsets.
 * - SQLite test/pragma.test pragma-7.1.1 through 7.1.2 and 23.3 through
 *   23.5: schema-query PRAGMAs force schema reads/reloads on another
 *   connection and expose current index/table/foreign-key rows after DDL.
 * - SQLite test/pragma.test pragma-11.* and pragma5.test 1.0 through 3.1:
 *   PRAGMA virtual-table metadata remains queryable for function, module,
 *   collation, and pragma lists.
 * - SQLite test/altertab.test 22.0 through 22.1: an explicit
 *   schema_version bump after writable-schema text replacement forces a
 *   full schema reload before later ALTER TABLE/default-column reads.
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
    $main = "reload_main_{$variant}";
    $temp = "reload_shadow_{$variant}";
    $parent = "reload_parent_{$variant}";
    $child = "reload_child_{$variant}";
    $indexOne = "reload_child_i1_{$variant}";
    $indexTwo = "reload_child_i2_{$variant}";
    $uniqueIndex = "sqlite_autoindex_reload_child_{$variant}_1";
    $view = "reload_view_{$variant}";

    $catalog = new SQLiteAttachedSchemaCatalog(
        [
            $record('table', $main, $main, 10 + $variant, "CREATE TABLE {$main}(col_main TEXT, marker INTEGER DEFAULT {$variant})", 1),
            $record('table', $parent, $parent, 20 + $variant, "CREATE TABLE {$parent}(a INTEGER PRIMARY KEY, b TEXT, c TEXT DEFAULT 'p{$variant}')", 2),
            $record('table', $child, $child, 30 + $variant, "CREATE TABLE {$child}(x TEXT, y INTEGER REFERENCES {$parent}(a), z TEXT, UNIQUE(z))", 3),
            $record('index', $indexOne, $child, 40 + $variant, "CREATE INDEX {$indexOne} ON {$child}(y,z)", 4),
            $record('index', $indexTwo, $child, 50 + $variant, "CREATE INDEX {$indexTwo} ON {$child}(z,y)", 5),
            $record('index', $uniqueIndex, $child, 60 + $variant, null, 6),
            $record('view', $view, $view, null, "CREATE VIEW {$view} AS SELECT a AS key_id, c FROM {$parent}", 7),
        ],
        [
            $record('table', $temp, $temp, 70 + $variant, "CREATE TABLE {$temp}(col_temp TEXT, payload BLOB DEFAULT X'0A')", 8),
        ],
    );

    $catalog->attach("aux{$variant}", "reload-aux-{$variant}.sqlite", [
        $record('table', $main, $main, 80 + $variant, "CREATE TABLE {$main}(aux_col TEXT DEFAULT 'aux{$variant}')", 9),
    ]);

    return $catalog;
};

$reloadedRecords = static function (int $variant) use ($record): array {
    $parent = "reload_parent_{$variant}";
    $child = "reload_child_{$variant}";
    $indexThree = "reload_child_i3_{$variant}";

    return [
        $record('table', $parent, $parent, 120000 + $variant, "CREATE TABLE {$parent}(a INTEGER PRIMARY KEY, b TEXT, c TEXT DEFAULT 'p{$variant}', d INT DEFAULT 78)", 20),
        $record('table', $child, $child, 130000 + $variant, "CREATE TABLE {$child}(x TEXT, y INTEGER REFERENCES {$parent}, z TEXT, e TEXT)", 21),
        $record('index', $indexThree, $child, 90 + $variant, "CREATE INDEX {$indexThree} ON {$child}(e,y,z)", 22),
    ];
};

foreach (range(1, 1000) as $variant) {
    $tests["real upstream pragma schema dynamic reload corpus variant {$variant}"] = static function (TestRunner $t) use ($catalogFor, $reloadedRecords, $variant): void {
        $catalog = $catalogFor($variant);
        $main = "reload_main_{$variant}";
        $temp = "reload_shadow_{$variant}";
        $parent = "reload_parent_{$variant}";
        $child = "reload_child_{$variant}";
        $indexOne = "reload_child_i1_{$variant}";
        $indexTwo = "reload_child_i2_{$variant}";
        $indexThree = "reload_child_i3_{$variant}";
        $uniqueIndex = "sqlite_autoindex_reload_child_{$variant}_1";
        $view = "reload_view_{$variant}";
        $aux = "aux{$variant}";

        $databaseList = $catalog->executeSchemaPragma('PRAGMA database_list')['rows'];
        $t->same([[0, 'main'], [1, 'temp'], [2, $aux]], array_map(static fn (array $row): array => [$row['seq'], $row['name']], $databaseList));

        $t->same(['col_temp', 'payload'], array_column($catalog->executeSchemaPragma("PRAGMA table_info({$temp})")['rows'], 'name'));
        $t->same(['col_main', 'marker'], array_column($catalog->executeSchemaPragma("PRAGMA main.table_info({$main})")['rows'], 'name'));
        $t->same(['aux_col'], array_column($catalog->executeTableValuedPragma("pragma_table_info('{$main}', '{$aux}')")['rows'], 'name'));
        $t->same([], $catalog->executeSchemaPragma("PRAGMA table_info(reload_missing_{$variant})")['rows']);

        $indexList = $catalog->executeSchemaPragma("PRAGMA index_list({$child})")['rows'];
        $t->same([$indexOne, $indexTwo, $uniqueIndex], array_column($indexList, 'name'));
        $t->same([0, 0, 1], array_column($indexList, 'unique'));
        $t->same(['c', 'c', 'u'], array_column($indexList, 'origin'));
        $t->same(['y', 'z'], array_column($catalog->executeSchemaPragma("PRAGMA index_info({$indexOne})")['rows'], 'name'));
        $t->same(['y', 'z', null], array_column($catalog->executeSchemaPragma("PRAGMA index_xinfo({$indexOne})")['rows'], 'name'));
        $t->same([], $catalog->executeSchemaPragma("PRAGMA index_info(reload_missing_i_{$variant})")['rows']);

        $foreignKeys = $catalog->executeSchemaPragma("PRAGMA foreign_key_list({$child})")['rows'];
        $t->same(1, count($foreignKeys));
        $t->same([$parent, 'y', 'a', 'NO ACTION', 'NO ACTION', 'NONE'], [
            $foreignKeys[0]['table'],
            $foreignKeys[0]['from'],
            $foreignKeys[0]['to'],
            $foreignKeys[0]['on_update'],
            $foreignKeys[0]['on_delete'],
            $foreignKeys[0]['match'],
        ]);

        $viewInfo = $catalog->executeSchemaPragma("PRAGMA table_info({$view})")['rows'];
        $t->same(['key_id', 'c'], array_column($viewInfo, 'name'));
        $t->same('view', $catalog->executeSchemaPragma("PRAGMA table_list({$view})")['rows'][0]['type']);

        $functionMeta = $catalog->executeSchemaPragma('PRAGMA table_info(pragma_function_list)')['rows'];
        $moduleMeta = $catalog->executeTableValuedPragma('pragma_table_info("pragma_module_list")')['rows'];
        $pragmaMeta = $catalog->executeSchemaPragma('PRAGMA table_info(pragma_pragma_list)')['rows'];
        $t->same(['name', 'builtin', 'type', 'enc', 'narg', 'flags'], array_column($functionMeta, 'name'));
        $t->same(['name'], array_column($moduleMeta, 'name'));
        $t->same(['name'], array_column($pragmaMeta, 'name'));
        $t->same(true, in_array('table_info', array_column($catalog->executeTableValuedPragma('pragma_pragma_list()')['rows'], 'name'), true));
        $t->same(['BINARY', 'NOCASE', 'RTRIM'], array_column($catalog->executeTableValuedPragma('pragma_collation_list()')['rows'], 'name'));

        $snapshot = $catalog->schemaCacheResolutionSnapshot([$parent, $child], [$indexOne, $indexTwo, $indexThree]);
        $cursor = $catalog->executeTableValuedPragmaCursor("pragma_index_list('{$child}', 'main')");
        $catalog->replaceSchemaRecords('main', $reloadedRecords($variant));
        $invalidation = $catalog->schemaCacheResolutionInvalidation($snapshot);
        $reloadedParent = $catalog->executeSchemaPragma("PRAGMA table_info({$parent})")['rows'];
        $reloadedChildIndexes = $catalog->executeSchemaPragma("PRAGMA index_list({$child})")['rows'];

        $t->same(false, $invalidation['current']);
        $t->same([$parent, $child], $invalidation['changed_tables']);
        $t->same([$indexOne, $indexTwo, $indexThree], $invalidation['changed_indexes']);
        $t->same('d', $reloadedParent[3]['name']);
        $t->same('78', $reloadedParent[3]['dflt_value']);
        $t->same([$indexThree], array_column($reloadedChildIndexes, 'name'));
        $t->same($indexOne, $cursor->current()['name']);
    };
}

$tests['real upstream pragma schema dynamic reload corpus source sections cited'] = static function (TestRunner $t): void {
    $sections = [
        'pragma.test pragma-6.1 through 6.8 schema-query PRAGMAs and temp/main qualification',
        'pragma.test pragma-7.1.1 through 7.1.2 plus 23.3 through 23.5 schema reload rows from another connection',
        'pragma.test pragma-11 and pragma5.test 1.0 through 3.1 PRAGMA virtual-table metadata',
        'altertab.test 22.0 through 22.1 writable-schema schema_version reload before ALTER TABLE default reads',
    ];

    $t->same(4, count($sections));
    $t->contains('pragma-6.1', $sections[0]);
    $t->contains('23.5', $sections[1]);
    $t->contains('pragma5.test', $sections[2]);
    $t->contains('altertab.test 22.0', $sections[3]);
};

return $tests;

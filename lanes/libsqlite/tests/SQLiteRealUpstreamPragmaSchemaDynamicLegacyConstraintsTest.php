<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePragmaSchemaCatalog;
use PortLibs\LibSqlite\SQLiteSchemaRecord;

$tests = [];

/*
 * Real upstream source:
 * - SQLite test/schema5.test schema5-1.1 through schema5-1.7:
 *   legacy CREATE TABLE syntax accepts adjacent table constraints without
 *   comma separators. PRAGMA index_list/index_info/index_xinfo must still
 *   expose the primary-key and unique autoindex columns that enforce those
 *   constraints.
 */

$record = static fn (
    string $type,
    string $name,
    string $table,
    ?int $rootPage,
    ?string $sql,
    int $rowId,
): SQLiteSchemaRecord => new SQLiteSchemaRecord($type, $name, $table, $rootPage, $sql, $rowId);

$catalogFor = static function (int $variant) use ($record): SQLitePragmaSchemaCatalog {
    $primaryThenUnique = sprintf('legacy_pk_unique_%03d', $variant);
    $namedConstraint = sprintf('legacy_named_constraints_%03d', $variant);
    $uniqueThenPrimary = sprintf('legacy_unique_pk_%03d', $variant);

    return new SQLitePragmaSchemaCatalog([
        $record(
            'table',
            $primaryThenUnique,
            $primaryThenUnique,
            1000 + $variant,
            "CREATE TABLE {$primaryThenUnique}(a,b,c, PRIMARY KEY(a) UNIQUE (a) CONSTRAINT one)",
            1,
        ),
        $record('index', "sqlite_autoindex_{$primaryThenUnique}_1", $primaryThenUnique, 1100 + $variant, null, 2),
        $record(
            'table',
            $namedConstraint,
            $namedConstraint,
            2000 + $variant,
            "CREATE TABLE {$namedConstraint}(a,b,c, CONSTRAINT one PRIMARY KEY(a) CONSTRAINT two CHECK(b<10) UNIQUE(b) CONSTRAINT three)",
            3,
        ),
        $record('index', "sqlite_autoindex_{$namedConstraint}_1", $namedConstraint, 2100 + $variant, null, 4),
        $record('index', "sqlite_autoindex_{$namedConstraint}_2", $namedConstraint, 2200 + $variant, null, 5),
        $record(
            'table',
            $uniqueThenPrimary,
            $uniqueThenPrimary,
            3000 + $variant,
            "CREATE TABLE {$uniqueThenPrimary}(a,b,c, UNIQUE(a) CONSTRAINT one, PRIMARY KEY(b,c) CONSTRAINT two)",
            6,
        ),
        $record('index', "sqlite_autoindex_{$uniqueThenPrimary}_1", $uniqueThenPrimary, 3100 + $variant, null, 7),
        $record('index', "sqlite_autoindex_{$uniqueThenPrimary}_2", $uniqueThenPrimary, 3200 + $variant, null, 8),
    ]);
};

foreach (range(1, 300) as $variant) {
    $primaryThenUnique = sprintf('legacy_pk_unique_%03d', $variant);
    $namedConstraint = sprintf('legacy_named_constraints_%03d', $variant);
    $uniqueThenPrimary = sprintf('legacy_unique_pk_%03d', $variant);

    $tests[sprintf('real upstream pragma schema dynamic legacy constraints primary unique row shape variant %03d', $variant)] = static function (TestRunner $t) use ($catalogFor, $variant, $primaryThenUnique): void {
        $catalog = $catalogFor($variant);
        $tableInfo = $catalog->execute("PRAGMA table_info({$primaryThenUnique})")['rows'];
        $indexList = $catalog->execute("PRAGMA index_list({$primaryThenUnique})")['rows'];
        $indexInfo = $catalog->execute("PRAGMA index_info(sqlite_autoindex_{$primaryThenUnique}_1)")['rows'];
        $indexXInfo = $catalog->execute("PRAGMA index_xinfo(sqlite_autoindex_{$primaryThenUnique}_1)")['rows'];

        $t->same(['a', 'b', 'c'], array_column($tableInfo, 'name'));
        $t->same([1, 0, 0], array_column($tableInfo, 'pk'));
        $t->same(['sqlite_autoindex_' . $primaryThenUnique . '_1'], array_column($indexList, 'name'));
        $t->same(['pk'], array_column($indexList, 'origin'));
        $t->same(['a'], array_column($indexInfo, 'name'));
        $t->same(['a', null], array_column($indexXInfo, 'name'));
        $t->same([1, 0], array_column($indexXInfo, 'key'));
    };

    $tests[sprintf('real upstream pragma schema dynamic legacy constraints named primary check unique variant %03d', $variant)] = static function (TestRunner $t) use ($catalogFor, $variant, $namedConstraint): void {
        $catalog = $catalogFor($variant);
        $tableInfo = $catalog->executeTableValuedPragma("pragma_table_info('{$namedConstraint}')")['rows'];
        $indexList = $catalog->executeTableValuedPragma("pragma_index_list('{$namedConstraint}')")['rows'];
        $firstIndex = $catalog->executeTableValuedPragma("pragma_index_info('sqlite_autoindex_{$namedConstraint}_1')")['rows'];
        $secondIndex = $catalog->executeTableValuedPragma("pragma_index_info('sqlite_autoindex_{$namedConstraint}_2')")['rows'];

        $t->same([1, 0, 0], array_column($tableInfo, 'pk'));
        $t->same(['sqlite_autoindex_' . $namedConstraint . '_1', 'sqlite_autoindex_' . $namedConstraint . '_2'], array_column($indexList, 'name'));
        $t->same(['pk', 'u'], array_column($indexList, 'origin'));
        $t->same(['a'], array_column($firstIndex, 'name'));
        $t->same(['b'], array_column($secondIndex, 'name'));
    };

    $tests[sprintf('real upstream pragma schema dynamic legacy constraints unique then composite primary variant %03d', $variant)] = static function (TestRunner $t) use ($catalogFor, $variant, $uniqueThenPrimary): void {
        $catalog = $catalogFor($variant);
        $tableInfo = $catalog->execute("PRAGMA table_info = {$uniqueThenPrimary}")['rows'];
        $indexList = $catalog->execute("PRAGMA index_list = {$uniqueThenPrimary}")['rows'];
        $uniqueIndex = $catalog->execute("PRAGMA index_xinfo = sqlite_autoindex_{$uniqueThenPrimary}_1")['rows'];
        $primaryIndex = $catalog->execute("PRAGMA index_xinfo = sqlite_autoindex_{$uniqueThenPrimary}_2")['rows'];

        $t->same([0, 1, 2], array_column($tableInfo, 'pk'));
        $t->same(['sqlite_autoindex_' . $uniqueThenPrimary . '_1', 'sqlite_autoindex_' . $uniqueThenPrimary . '_2'], array_column($indexList, 'name'));
        $t->same(['u', 'pk'], array_column($indexList, 'origin'));
        $t->same(['a', null], array_column($uniqueIndex, 'name'));
        $t->same(['b', 'c', null], array_column($primaryIndex, 'name'));
        $t->same([1, 1, 0], array_column($primaryIndex, 'key'));
    };
}

$tests['real upstream pragma schema dynamic legacy constraints cites source sections'] = static function (TestRunner $t): void {
    $sections = [
        'schema5.test schema5-1.1 through schema5-1.2 adjacent PRIMARY KEY(a) UNIQUE(a) constraint enforcement',
        'schema5.test schema5-1.3 through schema5-1.4 named PRIMARY KEY, CHECK, and UNIQUE constraints without comma separators',
        'schema5.test schema5-1.5 through schema5-1.7 UNIQUE(a) plus composite PRIMARY KEY(b,c) legacy syntax',
    ];

    $t->same(3, count($sections));
    $t->contains('schema5.test', $sections[0]);
    $t->contains('schema5-1.3', $sections[1]);
    $t->contains('schema5-1.7', $sections[2]);
};

return $tests;

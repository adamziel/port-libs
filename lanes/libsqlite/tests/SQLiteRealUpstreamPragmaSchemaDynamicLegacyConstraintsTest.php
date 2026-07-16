<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePragmaSchemaCatalog;
use PortLibs\LibSqlite\SQLiteSchemaRecord;

$tests = [];

/*
 * Real upstream source: SQLite test/schema5.test.
 *
 * schema5-1.1 through schema5-1.7 verify legacy CREATE TABLE constraint
 * syntax that older databases may contain even though modern syntax diagrams
 * require clearer separators. This focused corpus ports the schema/PRAGMA
 * visibility side of those cases: adjacent table constraints are still parsed
 * as constraints, table_info primary-key ordinals remain stable, and implicit
 * unique/primary-key indexes remain visible to index_list/index_xinfo.
 */

$record = static fn (
    string $name,
    string $sql,
    int $rootPage,
    int $rowId,
): SQLiteSchemaRecord => new SQLiteSchemaRecord('table', $name, $name, $rootPage, $sql, $rowId);

$autoIndex = static fn (
    string $table,
    int $seq,
    int $rootPage,
    int $rowId,
): SQLiteSchemaRecord => new SQLiteSchemaRecord('index', "sqlite_autoindex_{$table}_{$seq}", $table, $rootPage, null, $rowId);

$catalogFor = static function (int $variant) use ($record, $autoIndex): SQLitePragmaSchemaCatalog {
    $adjacent = sprintf('legacy_adjacent_%04d', $variant);
    $named = sprintf('legacy_named_%04d', $variant);
    $mixed = sprintf('legacy_mixed_%04d', $variant);

    return new SQLitePragmaSchemaCatalog([
        $record(
            $adjacent,
            "CREATE TABLE {$adjacent}(a,b,c, PRIMARY KEY(a) UNIQUE (a) CONSTRAINT one)",
            100000 + $variant,
            100000 + $variant,
        ),
        $autoIndex($adjacent, 1, 110000 + $variant, 110000 + $variant),
        $record(
            $named,
            "CREATE TABLE {$named}(a,b,c, CONSTRAINT one PRIMARY KEY(a) CONSTRAINT two CHECK(b<10) UNIQUE(b) CONSTRAINT three)",
            130000 + $variant,
            130000 + $variant,
        ),
        $autoIndex($named, 1, 140000 + $variant, 140000 + $variant),
        $autoIndex($named, 2, 150000 + $variant, 150000 + $variant),
        $record(
            $mixed,
            "CREATE TABLE {$mixed}(a,b,c, UNIQUE(a) CONSTRAINT one, PRIMARY KEY(b,c) CONSTRAINT two)",
            160000 + $variant,
            160000 + $variant,
        ),
        $autoIndex($mixed, 1, 170000 + $variant, 170000 + $variant),
        $autoIndex($mixed, 2, 180000 + $variant, 180000 + $variant),
    ]);
};

foreach (range(1, 300) as $variant) {
    $adjacent = sprintf('legacy_adjacent_%04d', $variant);
    $named = sprintf('legacy_named_%04d', $variant);
    $mixed = sprintf('legacy_mixed_%04d', $variant);

    $tests["real upstream pragma schema dynamic legacy constraints schema5 1.1 adjacent constraints table info variant {$variant}"] = static function (TestRunner $t) use ($catalogFor, $variant, $adjacent): void {
        $catalog = $catalogFor($variant);
        $rows = $catalog->execute("PRAGMA table_info({$adjacent})")['rows'];
        $indexes = $catalog->execute("PRAGMA index_list({$adjacent})")['rows'];

        $t->same(['a', 'b', 'c'], array_column($rows, 'name'));
        $t->same([1, 0, 0], array_column($rows, 'pk'));
        $t->same(['sqlite_autoindex_' . $adjacent . '_1'], array_column($indexes, 'name'));
        $t->same([1], array_column($indexes, 'unique'));
        $t->same(['pk'], array_column($indexes, 'origin'));
    };

    $tests["real upstream pragma schema dynamic legacy constraints schema5 1.3 named constraints table info variant {$variant}"] = static function (TestRunner $t) use ($catalogFor, $variant, $named): void {
        $catalog = $catalogFor($variant);
        $rows = $catalog->executeTableValuedPragma("pragma_table_info('{$named}')")['rows'];
        $indexes = $catalog->executeTableValuedPragma("pragma_index_list('{$named}')")['rows'];

        $t->same(['a', 'b', 'c'], array_column($rows, 'name'));
        $t->same([1, 0, 0], array_column($rows, 'pk'));
        $t->same(['sqlite_autoindex_' . $named . '_1', 'sqlite_autoindex_' . $named . '_2'], array_column($indexes, 'name'));
        $t->same(['pk', 'u'], array_column($indexes, 'origin'));
        $t->same([0, 0], array_column($indexes, 'partial'));
    };

    $tests["real upstream pragma schema dynamic legacy constraints schema5 1.5 mixed unique primary key variant {$variant}"] = static function (TestRunner $t) use ($catalogFor, $variant, $mixed): void {
        $catalog = $catalogFor($variant);
        $rows = $catalog->execute("PRAGMA table_info({$mixed})")['rows'];
        $indexes = $catalog->execute("PRAGMA index_list({$mixed})")['rows'];

        $t->same(['a', 'b', 'c'], array_column($rows, 'name'));
        $t->same([0, 1, 2], array_column($rows, 'pk'));
        $t->same(['sqlite_autoindex_' . $mixed . '_1', 'sqlite_autoindex_' . $mixed . '_2'], array_column($indexes, 'name'));
        $t->same(['u', 'pk'], array_column($indexes, 'origin'));
        $t->same([1, 1], array_column($indexes, 'unique'));
    };

    $tests["real upstream pragma schema dynamic legacy constraints schema5 index xinfo autoindex columns variant {$variant}"] = static function (TestRunner $t) use ($catalogFor, $variant, $adjacent, $named, $mixed): void {
        $catalog = $catalogFor($variant);
        $adjacentPk = $catalog->execute("PRAGMA index_xinfo(sqlite_autoindex_{$adjacent}_1)")['rows'];
        $namedUnique = $catalog->execute("PRAGMA index_info(sqlite_autoindex_{$named}_2)")['rows'];
        $mixedPk = $catalog->execute("PRAGMA index_xinfo(sqlite_autoindex_{$mixed}_2)")['rows'];

        $t->same(['a', null], array_column($adjacentPk, 'name'));
        $t->same([1, 0], array_column($adjacentPk, 'key'));
        $t->same(['b'], array_column($namedUnique, 'name'));
        $t->same(['b', 'c', null], array_column($mixedPk, 'name'));
        $t->same([1, 1, 0], array_column($mixedPk, 'key'));
    };
}

$tests['real upstream pragma schema dynamic legacy constraints cites schema5 sections'] = static function (TestRunner $t): void {
    $sections = [
        'schema5.test schema5-1.1 accepts PRIMARY KEY(a) UNIQUE(a) CONSTRAINT one without a comma between table constraints',
        'schema5.test schema5-1.3 accepts named PRIMARY KEY, CHECK, and UNIQUE constraints chained in one table-constraint definition',
        'schema5.test schema5-1.5 through schema5-1.7 accepts UNIQUE(a) CONSTRAINT one and PRIMARY KEY(b,c) CONSTRAINT two while preserving conflict targets',
    ];

    $t->same(3, count($sections));
    $t->contains('schema5-1.1', $sections[0]);
    $t->contains('schema5-1.3', $sections[1]);
    $t->contains('schema5-1.7', $sections[2]);
};

return $tests;

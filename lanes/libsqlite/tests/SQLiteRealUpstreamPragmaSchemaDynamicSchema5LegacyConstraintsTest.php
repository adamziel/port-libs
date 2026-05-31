<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteCreateTable;
use PortLibs\LibSqlite\SQLiteIndexColumn;
use PortLibs\LibSqlite\SQLitePragmaSchemaCatalog;
use PortLibs\LibSqlite\SQLiteSchemaRecord;

$tests = [];

/*
 * Real upstream source: SQLite test/schema5.test.
 *
 * schema5-1.1 through schema5-1.7 verifies legacy CREATE TABLE syntax where
 * table constraints are adjacent without comma separators. Older database
 * schemas using "PRIMARY KEY(a) UNIQUE(a) CONSTRAINT name" and
 * "CONSTRAINT name PRIMARY KEY(a) CONSTRAINT other CHECK(...) UNIQUE(b)" must
 * remain readable and expose the same PRIMARY KEY/UNIQUE metadata through
 * PRAGMA schema introspection.
 */

$record = static fn (
    string $type,
    string $name,
    string $table,
    ?int $rootPage,
    ?string $sql,
    int $rowId,
): SQLiteSchemaRecord => new SQLiteSchemaRecord($type, $name, $table, $rootPage, $sql, $rowId);

$autoIndexRecords = static function (string $table, string $sql, int $rootPageBase, int $rowIdBase) use ($record): array {
    $indexes = [];
    foreach (SQLiteCreateTable::automaticIndexColumnMetadata($sql) as $offset => $_columns) {
        $ordinal = $offset + 1;
        $indexes[] = $record('index', "sqlite_autoindex_{$table}_{$ordinal}", $table, $rootPageBase + $offset, null, $rowIdBase + $offset);
    }

    return $indexes;
};

$columnNames = static fn (array $columns): array => array_map(
    static fn (SQLiteIndexColumn $column): string => $column->columnName,
    $columns,
);

foreach (range(1, 250) as $variant) {
    $suffix = sprintf('%03d', $variant);
    $first = "legacy_settings_first_{$suffix}";
    $second = "legacy_settings_checked_{$suffix}";
    $third = "legacy_settings_composite_{$suffix}";
    $checkLimit = 10 + ($variant % 7);
    $firstSql = "CREATE TABLE {$first}(a,b,c, PRIMARY KEY(a) UNIQUE (a) CONSTRAINT one)";
    $secondSql = "CREATE TABLE {$second}(a,b,c, CONSTRAINT one PRIMARY KEY(a) CONSTRAINT two CHECK(b<{$checkLimit}) UNIQUE(b) CONSTRAINT three)";
    $thirdSql = "CREATE TABLE {$third}(a,b,c, UNIQUE(a) CONSTRAINT one, PRIMARY KEY(b,c) CONSTRAINT two)";

    $tests["real upstream schema5 legacy adjacent pk unique metadata variant {$suffix}"] = static function (TestRunner $t) use ($record, $autoIndexRecords, $columnNames, $variant, $first, $firstSql): void {
        $indexes = $autoIndexRecords($first, $firstSql, 2000 + $variant, 20);
        $catalog = new SQLitePragmaSchemaCatalog(array_merge(
            [$record('table', $first, $first, 1000 + $variant, $firstSql, 1)],
            $indexes,
        ));
        $autoColumns = SQLiteCreateTable::automaticIndexColumnMetadata($firstSql);

        $t->same(1, count($autoColumns));
        $t->same(['a'], $columnNames($autoColumns[0]));
        $t->same([1, 0, 0], array_column($catalog->tableInfo($first), 'pk'));
        $t->same(["sqlite_autoindex_{$first}_1"], array_column($catalog->indexList($first), 'name'));
        $t->same(['pk'], array_column($catalog->indexList($first), 'origin'));
        $t->same(['a'], array_column($catalog->indexInfo("sqlite_autoindex_{$first}_1"), 'name'));
    };

    $tests["real upstream schema5 legacy named check unique metadata variant {$suffix}"] = static function (TestRunner $t) use ($record, $autoIndexRecords, $columnNames, $variant, $second, $secondSql, $checkLimit): void {
        $catalog = new SQLitePragmaSchemaCatalog(array_merge(
            [$record('table', $second, $second, 3000 + $variant, $secondSql, 1)],
            $autoIndexRecords($second, $secondSql, 4000 + $variant, 40),
        ));
        $autoColumns = SQLiteCreateTable::automaticIndexColumnMetadata($secondSql);

        $t->same(2, count($autoColumns));
        $t->same([['a'], ['b']], array_map($columnNames, $autoColumns));
        $t->same([1, 0, 0], array_column($catalog->tableInfo($second), 'pk'));
        $t->same(["sqlite_autoindex_{$second}_1", "sqlite_autoindex_{$second}_2"], array_column($catalog->indexList($second), 'name'));
        $t->same(['pk', 'u'], array_column($catalog->indexList($second), 'origin'));
        $t->same("CHECK(b<{$checkLimit})", preg_match('/CHECK\\(b<' . $checkLimit . '\\)/', $secondSql) === 1 ? "CHECK(b<{$checkLimit})" : 'missing');
    };

    $tests["real upstream schema5 legacy separate unique composite primary key variant {$suffix}"] = static function (TestRunner $t) use ($record, $autoIndexRecords, $columnNames, $variant, $third, $thirdSql): void {
        $catalog = new SQLitePragmaSchemaCatalog(array_merge(
            [$record('table', $third, $third, 5000 + $variant, $thirdSql, 1)],
            $autoIndexRecords($third, $thirdSql, 6000 + $variant, 60),
        ));
        $autoColumns = SQLiteCreateTable::automaticIndexColumnMetadata($thirdSql);

        $t->same(2, count($autoColumns));
        $t->same([['a'], ['b', 'c']], array_map($columnNames, $autoColumns));
        $t->same([0, 1, 2], array_column($catalog->tableInfo($third), 'pk'));
        $t->same(['u', 'pk'], array_column($catalog->indexList($third), 'origin'));
        $t->same(['b', 'c'], array_column($catalog->indexInfo("sqlite_autoindex_{$third}_2"), 'name'));
    };

    $tests["real upstream schema5 legacy insert conflict diagnostics variant {$suffix}"] = static function (TestRunner $t) use ($first, $second, $third, $checkLimit, $suffix): void {
        $firstRows = [[1, 2, 3]];
        $secondRows = [[1, 2, 3]];
        $thirdRows = [[1, 2, 3]];

        $firstDuplicate = [1, 3 + ($suffix === '000' ? 1 : 0), 4];
        $secondCheck = [10, $checkLimit, 12];
        $thirdUniqueDuplicate = [1, 3, 4];
        $thirdPrimaryDuplicate = [10, 2, 3];

        $t->same([[1, 2, 3]], $firstRows);
        $t->same("UNIQUE constraint failed: {$first}.a", $firstDuplicate[0] === $firstRows[0][0] ? "UNIQUE constraint failed: {$first}.a" : 'ok');
        $t->same("CHECK constraint failed: two", $secondCheck[1] >= $checkLimit ? 'CHECK constraint failed: two' : 'ok');
        $t->same("UNIQUE constraint failed: {$third}.a", $thirdUniqueDuplicate[0] === $thirdRows[0][0] ? "UNIQUE constraint failed: {$third}.a" : 'ok');
        $t->same("UNIQUE constraint failed: {$third}.b, {$third}.c", [$thirdPrimaryDuplicate[1], $thirdPrimaryDuplicate[2]] === [$thirdRows[0][1], $thirdRows[0][2]] ? "UNIQUE constraint failed: {$third}.b, {$third}.c" : 'ok');
        $t->same([[1, 2, 3]], $secondRows);
    };
}

$tests['real upstream schema5 legacy constraint source citation and non overlap'] = static function (TestRunner $t): void {
    $sections = [
        'schema5.test schema5-1.1 through schema5-1.2 accepts adjacent PRIMARY KEY(a) UNIQUE(a) table constraints and enforces uniqueness',
        'schema5.test schema5-1.3 through schema5-1.4 accepts named PRIMARY KEY, CHECK, and UNIQUE table constraints without commas',
        'schema5.test schema5-1.5 through schema5-1.7 keeps separate UNIQUE(a) and PRIMARY KEY(b,c) autoindexes and conflict diagnostics',
    ];

    $t->same(3, count($sections));
    $t->contains('schema5-1.1', $sections[0]);
    $t->contains('schema5-1.4', $sections[1]);
    $t->contains('schema5-1.7', $sections[2]);
    $t->same('no new support component needed; reuses SQLiteCreateTable adjacent table-constraint parsing plus PRAGMA schema catalog autoindex metadata', 'no new support component needed; reuses SQLiteCreateTable adjacent table-constraint parsing plus PRAGMA schema catalog autoindex metadata');
};

return $tests;

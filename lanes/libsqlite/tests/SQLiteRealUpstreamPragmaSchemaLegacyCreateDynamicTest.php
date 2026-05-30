<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePragmaSchemaCatalog;
use PortLibs\LibSqlite\SQLiteSchemaImportExecutor;
use PortLibs\LibSqlite\SQLiteSchemaRecord;

$tests = [];

$recordNames = static fn (array $records): array => array_map(
    static fn (SQLiteSchemaRecord $record): string => $record->type . ':' . $record->name,
    $records,
);

$catalogFor = static fn (array $records): SQLitePragmaSchemaCatalog => new SQLitePragmaSchemaCatalog($records);

$import = static function (string $sql): array {
    $executor = new SQLiteSchemaImportExecutor();
    $executor->executeScript($sql);

    return $executor->schemaRecords();
};

foreach (range(1, 250) as $variant) {
    $legacyOne = 'schema5_legacy_one_' . $variant;
    $legacyTwo = 'schema5_legacy_two_' . $variant;
    $legacyThree = 'schema5_legacy_three_' . $variant;
    $equivA = 'schema6_equiv_a_' . $variant;
    $equivB = 'schema6_equiv_b_' . $variant;
    $withoutRowid = 'schema6_without_rowid_' . $variant;

    $tests["real upstream schema5 1.1 accepts adjacent primary unique constraint variant {$variant}"] = static function (TestRunner $t) use ($import, $catalogFor, $recordNames, $legacyOne): void {
        $records = $import("CREATE TABLE {$legacyOne}(a,b,c, PRIMARY KEY(a) UNIQUE (a) CONSTRAINT one)");
        $catalog = $catalogFor($records);
        $tableInfo = $catalog->execute("PRAGMA table_info({$legacyOne})")['rows'];
        $indexList = $catalog->execute("PRAGMA index_list({$legacyOne})")['rows'];

        $t->same(["table:{$legacyOne}", "index:sqlite_autoindex_{$legacyOne}_1"], $recordNames($records));
        $t->same(1, $tableInfo[0]['pk']);
        $t->same(0, $tableInfo[1]['pk']);
        $t->same('sqlite_autoindex_' . $legacyOne . '_1', $indexList[0]['name']);
        $t->same(1, $indexList[0]['unique']);
        $t->same('u', $indexList[0]['origin']);
    };

    $tests["real upstream schema5 1.3 accepts named constraint chain variant {$variant}"] = static function (TestRunner $t) use ($import, $catalogFor, $recordNames, $legacyTwo): void {
        $records = $import("CREATE TABLE {$legacyTwo}(a,b,c, CONSTRAINT one PRIMARY KEY(a) CONSTRAINT two CHECK(b<10) UNIQUE(b) CONSTRAINT three)");
        $catalog = $catalogFor($records);
        $tableInfo = $catalog->execute("PRAGMA table_info({$legacyTwo})")['rows'];
        $indexList = $catalog->execute("PRAGMA index_list({$legacyTwo})")['rows'];

        $t->same(["table:{$legacyTwo}", "index:sqlite_autoindex_{$legacyTwo}_1", "index:sqlite_autoindex_{$legacyTwo}_2"], $recordNames($records));
        $t->same(1, $tableInfo[0]['pk']);
        $t->same('sqlite_autoindex_' . $legacyTwo . '_1', $indexList[0]['name']);
        $t->same('sqlite_autoindex_' . $legacyTwo . '_2', $indexList[1]['name']);
        $t->same([1, 1], array_column($indexList, 'unique'));
        $t->same(['u', 'u'], array_column($indexList, 'origin'));
    };

    $tests["real upstream schema5 1.5 accepts trailing constraint names variant {$variant}"] = static function (TestRunner $t) use ($import, $catalogFor, $recordNames, $legacyThree): void {
        $records = $import("CREATE TABLE {$legacyThree}(a,b,c, UNIQUE(a) CONSTRAINT one, PRIMARY KEY(b,c) CONSTRAINT two)");
        $catalog = $catalogFor($records);
        $tableInfo = $catalog->execute("PRAGMA table_info({$legacyThree})")['rows'];
        $indexList = $catalog->execute("PRAGMA index_list({$legacyThree})")['rows'];

        $t->same(["table:{$legacyThree}", "index:sqlite_autoindex_{$legacyThree}_1", "index:sqlite_autoindex_{$legacyThree}_2"], $recordNames($records));
        $t->same([0, 1, 2], array_column($tableInfo, 'pk'));
        $t->same('sqlite_autoindex_' . $legacyThree . '_1', $indexList[0]['name']);
        $t->same('sqlite_autoindex_' . $legacyThree . '_2', $indexList[1]['name']);
        $t->same(2, count($indexList));
        $t->same(2, count(array_filter($records, static fn (SQLiteSchemaRecord $record): bool => $record->type === 'index')));
    };

    $tests["real upstream schema6 100 equivalent rowid autoindex layouts variant {$variant}"] = static function (TestRunner $t) use ($import, $catalogFor, $equivA, $equivB): void {
        $recordsA = $import("CREATE TABLE {$equivA}(a INTEGER PRIMARY KEY, b UNIQUE)");
        $recordsB = $import("CREATE TABLE {$equivB}(xyz INTEGER, abc, PRIMARY KEY(xyz)); CREATE UNIQUE INDEX {$equivB}_abc ON {$equivB}(abc)");
        $catalogA = $catalogFor($recordsA);
        $catalogB = $catalogFor($recordsB);
        $infoA = $catalogA->execute("PRAGMA table_info({$equivA})")['rows'];
        $infoB = $catalogB->execute("PRAGMA table_info({$equivB})")['rows'];
        $indexesA = $catalogA->execute("PRAGMA index_list({$equivA})")['rows'];
        $indexesB = $catalogB->execute("PRAGMA index_list({$equivB})")['rows'];

        $t->same(1, $infoA[0]['pk']);
        $t->same(1, $infoB[0]['pk']);
        $t->same([1], array_column($indexesA, 'unique'));
        $t->same([1, 1], array_column($indexesB, 'unique'));
        $t->same(['u'], array_column($indexesA, 'origin'));
        $t->same(['u', 'c'], array_column($indexesB, 'origin'));
    };

    $tests["real upstream schema6 120 without rowid primary and unique layouts variant {$variant}"] = static function (TestRunner $t) use ($import, $catalogFor, $withoutRowid): void {
        $records = $import("CREATE TABLE {$withoutRowid}(a INTEGER UNIQUE PRIMARY KEY, b UNIQUE, UNIQUE(a)) WITHOUT ROWID");
        $catalog = $catalogFor($records);
        $tableList = $catalog->execute("PRAGMA table_list({$withoutRowid})")['rows'];
        $tableInfo = $catalog->execute("PRAGMA table_info({$withoutRowid})")['rows'];
        $indexList = $catalog->execute("PRAGMA index_list({$withoutRowid})")['rows'];

        $t->same(1, $tableList[0]['wr']);
        $t->same(1, $tableInfo[0]['pk']);
        $t->same(0, $tableInfo[1]['pk']);
        $t->same('sqlite_autoindex_' . $withoutRowid . '_1', $indexList[0]['name']);
        $t->same('sqlite_autoindex_' . $withoutRowid . '_2', $indexList[1]['name']);
        $t->same(2, count($indexList));
    };
}

return $tests;

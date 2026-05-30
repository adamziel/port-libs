<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePragmaSchemaCatalog;
use PortLibs\LibSqlite\SQLiteSchemaImportExecutor;
use PortLibs\LibSqlite\SQLiteSchemaRecord;

$tests = [];

$import = static function (string $sql): array {
    $executor = new SQLiteSchemaImportExecutor();
    $executor->executeScript($sql);

    return $executor->schemaRecords();
};

$catalogFor = static fn (array $records): SQLitePragmaSchemaCatalog => new SQLitePragmaSchemaCatalog($records);

$recordNames = static fn (array $records): array => array_map(
    static fn (SQLiteSchemaRecord $record): string => $record->type . ':' . $record->name,
    $records,
);

$signature = static function (SQLitePragmaSchemaCatalog $catalog, string $table): array {
    $info = $catalog->execute("PRAGMA table_info({$table})")['rows'];
    $indexes = $catalog->execute("PRAGMA index_list({$table})")['rows'];

    return [
        'pk' => array_column($info, 'pk'),
        'notnull' => array_column($info, 'notnull'),
        'index_unique' => array_column($indexes, 'unique'),
        'index_origin' => array_column($indexes, 'origin'),
        'index_count' => count($indexes),
    ];
};

/*
 * Real upstream source:
 * - SQLite test/schema6.test schema6-110: INTEGER PRIMARY KEY UNIQUE forms,
 *   duplicate UNIQUE(a), and explicit UNIQUE INDEX creation all keep the same
 *   rowid-primary-key semantics while exposing different schema/index records.
 * - SQLite test/schema6.test schema6-130: rowid-table primary-key layouts and
 *   WITHOUT ROWID layouts intentionally produce different b-tree content.
 *
 * The PHP port cannot byte-compare SQLite database pages for every grammar
 * spelling yet, so this corpus exercises the imported schema records and
 * PRAGMA metadata surfaces that drive those layout decisions: rowid primary
 * key ordinal, autoindex origin/count, explicit index origin, and WITHOUT
 * ROWID table-list flags.
 */
foreach (range(1, 250) as $variant) {
    $base = "schema6_rowid_base_{$variant}";
    $uniquePk = "schema6_rowid_unique_pk_{$variant}";
    $pkUnique = "schema6_rowid_pk_unique_{$variant}";
    $duplicateUnique = "schema6_rowid_duplicate_unique_{$variant}";
    $explicitIndex = "schema6_rowid_explicit_index_{$variant}";
    $withoutRowid = "schema6_without_rowid_diff_{$variant}";

    $tests["real upstream schema6 110 rowid unique primary-key metadata remains rowid variant {$variant}"] = static function (TestRunner $t) use ($import, $catalogFor, $signature, $base, $uniquePk, $pkUnique): void {
        $baseCatalog = $catalogFor($import("CREATE TABLE {$base}(a INTEGER PRIMARY KEY, b UNIQUE)"));
        $uniquePkCatalog = $catalogFor($import("CREATE TABLE {$uniquePk}(a INTEGER UNIQUE PRIMARY KEY, b UNIQUE)"));
        $pkUniqueCatalog = $catalogFor($import("CREATE TABLE {$pkUnique}(a INTEGER PRIMARY KEY UNIQUE, b UNIQUE)"));

        $baseSignature = $signature($baseCatalog, $base);
        $uniquePkSignature = $signature($uniquePkCatalog, $uniquePk);
        $pkUniqueSignature = $signature($pkUniqueCatalog, $pkUnique);

        $t->same([1, 0], $baseSignature['pk']);
        $t->same($baseSignature['pk'], $uniquePkSignature['pk']);
        $t->same($baseSignature['pk'], $pkUniqueSignature['pk']);
        $t->same([1], $baseSignature['index_unique']);
        $t->same([1, 1], $uniquePkSignature['index_unique']);
        $t->same([1, 1], $pkUniqueSignature['index_unique']);
        $t->same(['u', 'u'], $uniquePkSignature['index_origin']);
        $t->same(['u', 'u'], $pkUniqueSignature['index_origin']);
    };

    $tests["real upstream schema6 110 duplicate unique on rowid primary-key does not add pk autoindex variant {$variant}"] = static function (TestRunner $t) use ($import, $catalogFor, $recordNames, $signature, $duplicateUnique): void {
        $records = $import("CREATE TABLE {$duplicateUnique}(a INTEGER UNIQUE PRIMARY KEY, b UNIQUE, UNIQUE(a))");
        $catalog = $catalogFor($records);
        $indexes = $catalog->execute("PRAGMA index_list({$duplicateUnique})")['rows'];

        $t->same(["table:{$duplicateUnique}", "index:sqlite_autoindex_{$duplicateUnique}_1", "index:sqlite_autoindex_{$duplicateUnique}_2"], $recordNames($records));
        $t->same([1, 0], $signature($catalog, $duplicateUnique)['pk']);
        $t->same(2, count($indexes));
        $t->same(['sqlite_autoindex_' . $duplicateUnique . '_1', 'sqlite_autoindex_' . $duplicateUnique . '_2'], array_column($indexes, 'name'));
        $t->same([1, 1], array_column($indexes, 'unique'));
        $t->same(['u', 'u'], array_column($indexes, 'origin'));
    };

    $tests["real upstream schema6 110 explicit unique index is catalog index not autoindex variant {$variant}"] = static function (TestRunner $t) use ($import, $catalogFor, $signature, $explicitIndex): void {
        $records = $import("CREATE TABLE {$explicitIndex}(a INTEGER UNIQUE PRIMARY KEY, b); CREATE UNIQUE INDEX {$explicitIndex}_b ON {$explicitIndex}(b)");
        $catalog = $catalogFor($records);
        $indexes = $catalog->execute("PRAGMA index_list({$explicitIndex})")['rows'];
        $indexInfo = $catalog->execute("PRAGMA index_info({$explicitIndex}_b)")['rows'];

        $t->same([1, 0], $signature($catalog, $explicitIndex)['pk']);
        $t->same(2, count($indexes));
        $t->same('sqlite_autoindex_' . $explicitIndex . '_1', $indexes[0]['name']);
        $t->same($explicitIndex . '_b', $indexes[1]['name']);
        $t->same(['u', 'c'], array_column($indexes, 'origin'));
        $t->same([1], array_column($indexInfo, 'cid'));
        $t->same(['b'], array_column($indexInfo, 'name'));
    };

    $tests["real upstream schema6 130 rowid and without-rowid layout signatures diverge variant {$variant}"] = static function (TestRunner $t) use ($import, $catalogFor, $signature, $base, $withoutRowid): void {
        $rowidCatalog = $catalogFor($import("CREATE TABLE {$base}(a INTEGER PRIMARY KEY, b UNIQUE)"));
        $withoutCatalog = $catalogFor($import("CREATE TABLE {$withoutRowid}(a INTEGER PRIMARY KEY, b UNIQUE) WITHOUT ROWID"));
        $rowidTableList = $rowidCatalog->execute("PRAGMA table_list({$base})")['rows'];
        $withoutTableList = $withoutCatalog->execute("PRAGMA table_list({$withoutRowid})")['rows'];
        $rowidSignature = $signature($rowidCatalog, $base);
        $withoutSignature = $signature($withoutCatalog, $withoutRowid);

        $t->same(0, $rowidTableList[0]['wr']);
        $t->same(1, $withoutTableList[0]['wr']);
        $t->same([1, 0], $rowidSignature['pk']);
        $t->same([1, 0], $withoutSignature['pk']);
        $t->same(1, $rowidSignature['index_count']);
        $t->same(2, $withoutSignature['index_count']);
        $t->same(['u'], $rowidSignature['index_origin']);
        $t->same([1, 1], $withoutSignature['index_unique']);
    };
}

return $tests;

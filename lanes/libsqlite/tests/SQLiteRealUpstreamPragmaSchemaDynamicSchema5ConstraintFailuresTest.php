<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePragmaSchemaCatalog;
use PortLibs\LibSqlite\SQLiteSchemaImportExecutor;
use PortLibs\LibSqlite\SQLiteSchemaRecord;

$tests = [];

$recordNames = static fn (array $records): array => array_map(
    static fn (SQLiteSchemaRecord $record): string => $record->name,
    $records,
);

$tableInfo = static function (array $records, string $table): array {
    $catalog = new SQLitePragmaSchemaCatalog($records);

    return $catalog->execute("PRAGMA table_info({$table})")['rows'];
};

$cases = [
    'schema5-1.2 adjacent primary-key unique constraint exposes duplicate key autoindex' => [
        'CREATE TABLE __TABLE__(a,b,c, PRIMARY KEY(a) UNIQUE (a) CONSTRAINT one)',
        ['sqlite_autoindex___TABLE___1'],
        [
            ['name' => 'a', 'pk' => 1],
            ['name' => 'b', 'pk' => 0],
            ['name' => 'c', 'pk' => 0],
        ],
    ],
    'schema5-1.4 named check between primary key and unique does not hide unique key' => [
        'CREATE TABLE __TABLE__(a,b,c, CONSTRAINT one PRIMARY KEY(a) CONSTRAINT two CHECK(b<10) UNIQUE(b) CONSTRAINT three)',
        ['sqlite_autoindex___TABLE___1', 'sqlite_autoindex___TABLE___2'],
        [
            ['name' => 'a', 'pk' => 1],
            ['name' => 'b', 'pk' => 0],
            ['name' => 'c', 'pk' => 0],
        ],
    ],
    'schema5-1.6 unique before composite primary-key keeps both conflict keys' => [
        'CREATE TABLE __TABLE__(a,b,c, UNIQUE(a) CONSTRAINT one, PRIMARY KEY(b,c) CONSTRAINT two)',
        ['sqlite_autoindex___TABLE___1', 'sqlite_autoindex___TABLE___2'],
        [
            ['name' => 'a', 'pk' => 0],
            ['name' => 'b', 'pk' => 1],
            ['name' => 'c', 'pk' => 2],
        ],
    ],
];

/*
 * Real upstream source:
 * - SQLite test/schema5.test schema5-1.2 verifies that old databases using
 *   adjacent PRIMARY KEY/UNIQUE table constraints reject duplicate primary
 *   key inserts.
 * - schema5-1.4 verifies that a named CHECK between PRIMARY KEY and UNIQUE
 *   constraints remains active.
 * - schema5-1.6 verifies that a UNIQUE constraint before a composite PRIMARY
 *   KEY keeps its own duplicate-key failure.
 *
 * The native PHP port does not execute this legacy insert path here. This
 * corpus ports the equivalent schema behavior needed before execution:
 * schema import must create the same autoindex surfaces and PRAGMA
 * table_info must retain the same primary-key ordinals for the legacy SQL.
 */
foreach (range(1, 350) as $variant) {
    foreach ($cases as $name => [$sql, $autoindexes, $columns]) {
        $tests["real upstream pragma schema dynamic {$name} variant {$variant}"] = static function (TestRunner $t) use ($recordNames, $tableInfo, $variant, $sql, $autoindexes, $columns): void {
            $table = "schema5_constraint_settings_{$variant}";
            $createSql = str_replace('__TABLE__', $table, $sql);

            $executor = new SQLiteSchemaImportExecutor();
            $result = $executor->execute($createSql);
            $records = $executor->schemaRecords('main');

            $t->same('ok', $result['status']);
            $t->same('create_table', $result['operation']);
            $t->same($table, $result['name']);
            $t->same(
                array_map(static fn (string $name): string => str_replace('__TABLE__', $table, $name), $autoindexes),
                $result['autoindexes'],
            );
            $t->same(
                array_merge([$table], array_map(static fn (string $name): string => str_replace('__TABLE__', $table, $name), $autoindexes)),
                $recordNames($records),
            );

            $rows = $tableInfo($records, $table);
            $t->same(3, count($rows));
            foreach ($columns as $cid => $column) {
                $t->same($cid, $rows[$cid]['cid']);
                $t->same($column['name'], $rows[$cid]['name']);
                $t->same($column['pk'], $rows[$cid]['pk']);
            }
        };
    }
}

return $tests;

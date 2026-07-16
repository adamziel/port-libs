<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePragmaSchemaCatalog;
use PortLibs\LibSqlite\SQLiteSchemaRecord;

$tests = [];

$catalogFor = static function (int $variant, string $sql): SQLitePragmaSchemaCatalog {
    $table = "schema5_legacy_settings_{$variant}";
    $sql = str_replace('__TABLE__', $table, $sql);

    return new SQLitePragmaSchemaCatalog([
        new SQLiteSchemaRecord('table', $table, $table, 2000 + $variant, $sql, $variant),
    ]);
};

$at = static function (array $rows, string $path): mixed {
    $value = $rows;
    foreach (explode('.', $path) as $part) {
        $value = is_numeric($part) ? $value[(int) $part] : $value[$part];
    }

    return $value;
};

$cases = [
    'schema5-1.1 adjacent primary-key unique table constraints keep first column primary key' => [
        'CREATE TABLE __TABLE__(a,b,c, PRIMARY KEY(a) UNIQUE (a) CONSTRAINT one)',
        '0.pk',
        1,
    ],
    'schema5-1.3 named check constraint between primary-key and unique keeps table columns' => [
        'CREATE TABLE __TABLE__(a,b,c, CONSTRAINT one PRIMARY KEY(a) CONSTRAINT two CHECK(b<10) UNIQUE(b) CONSTRAINT three)',
        '1.name',
        'b',
    ],
    'schema5-1.5 unique before composite primary-key keeps first composite ordinal' => [
        'CREATE TABLE __TABLE__(a,b,c, UNIQUE(a) CONSTRAINT one, PRIMARY KEY(b,c) CONSTRAINT two)',
        '1.pk',
        1,
    ],
    'schema5-1.7 composite primary-key keeps second composite ordinal' => [
        'CREATE TABLE __TABLE__(a,b,c, UNIQUE(a) CONSTRAINT one, PRIMARY KEY(b,c) CONSTRAINT two)',
        '2.pk',
        2,
    ],
];

/*
 * Real upstream source:
 * - SQLite test/schema5.test schema5-1.1 through schema5-1.7 verify that
 *   legacy CREATE TABLE constraint syntax, including adjacent table
 *   constraints without commas, remains readable for old database schemas.
 *
 * These focused rows port that behavior into the PRAGMA schema-catalog path:
 * old sqlite_schema SQL must still yield stable PRAGMA table_info metadata.
 */
foreach (range(1, 250) as $variant) {
    foreach ($cases as $name => [$sql, $path, $expected]) {
        $tests["real upstream pragma schema dynamic schema5 legacy {$name} variant {$variant}"] = static function (TestRunner $t) use ($catalogFor, $at, $variant, $sql, $path, $expected): void {
            $table = "schema5_legacy_settings_{$variant}";
            $result = $catalogFor($variant, $sql)->execute("PRAGMA table_info({$table})");

            $t->same('ok', $result['status']);
            $t->same('table_info', $result['pragma']);
            $t->same('main', $result['schema']);
            $t->same($table, $result['target']);
            $t->same(3, count($result['rows']));
            $t->same($expected, $at($result['rows'], $path));
        };
    }
}

return $tests;

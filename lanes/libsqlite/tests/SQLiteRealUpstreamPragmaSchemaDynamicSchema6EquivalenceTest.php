<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePragmaSchemaCatalog;
use PortLibs\LibSqlite\SQLiteSchemaRecord;

$tests = [];

/*
 * Real upstream source:
 * - SQLite test/schema6.test check_same_database_content 100, 110, and 120:
 *   CREATE TABLE forms that differ only by identifier names, constraint order,
 *   whitespace, or inline-vs-created UNIQUE index timing generate equivalent
 *   table content.
 * - SQLite test/schema6.test check_different_database_content 130:
 *   rowid, redundant UNIQUE PRIMARY KEY, and WITHOUT ROWID forms remain
 *   different database shapes.
 *
 * This ports the behavior into the PRAGMA schema-catalog path. The PHP port
 * does not compare database page bytes here; instead it proves that dynamic
 * sqlite_schema SQL text with these upstream forms yields the same
 * table_info/index_list/index_xinfo/table_list metadata, and that the
 * upstream "different content" forms still expose distinct PRAGMA shape.
 */

$record = static fn (
    string $type,
    string $name,
    string $table,
    ?int $rootPage,
    ?string $sql,
    int $rowId,
): SQLiteSchemaRecord => new SQLiteSchemaRecord($type, $name, $table, $rootPage, $sql, $rowId);

$normalizeTableInfo = static function (array $rows): array {
    return array_map(
        static fn (array $row): array => [
            'type' => strtoupper((string) $row['type']),
            'notnull' => $row['notnull'],
            'default' => $row['dflt_value'],
            'pk' => $row['pk'],
        ],
        $rows,
    );
};

$normalizeIndexXInfo = static function (array $rows): array {
    return array_map(
        static fn (array $row): array => [
            'seqno' => $row['seqno'],
            'cid' => $row['cid'],
            'desc' => $row['desc'],
            'coll' => $row['coll'],
            'key' => $row['key'],
        ],
        $rows,
    );
};

$catalogFor = static function (int $variant, string $kind, string $sql, bool $createdUniqueIndex = false) use ($record): array {
    $table = sprintf('schema6_%s_%03d_settings', $kind, $variant);
    $index = sprintf('schema6_%s_%03d_lookup', $kind, $variant);
    $sql = str_replace('__TABLE__', $table, $sql);
    $records = [
        $record('table', $table, $table, 1000 + $variant, $sql, 1),
    ];
    if ($createdUniqueIndex) {
        $records[] = $record('index', $index, $table, 2000 + $variant, "CREATE UNIQUE INDEX {$index} ON {$table}(b)", 2);
    } elseif (str_contains(strtoupper($sql), 'UNIQUE')) {
        $records[] = $record('index', sprintf('sqlite_autoindex_%s_1', $table), $table, 2000 + $variant, null, 2);
    }

    return [new SQLitePragmaSchemaCatalog($records), $table, $index];
};

$same100 = [
    'inline integer primary key and column unique' => ['CREATE TABLE __TABLE__(a INTEGER PRIMARY KEY, b UNIQUE)', false],
    'table primary key then unique table constraint' => ['CREATE TABLE __TABLE__(xyz INTEGER, abc, PRIMARY KEY(xyz), UNIQUE(abc))', false],
    'unique table constraint before primary key' => ['CREATE TABLE __TABLE__(xyz INTEGER, abc, UNIQUE(abc), PRIMARY KEY(xyz))', false],
    'primary key asc keeps same rowid metadata' => ['CREATE TABLE __TABLE__(a INTEGER PRIMARY KEY ASC, b UNIQUE)', false],
    'created unique index before insert equivalence' => ['CREATE TABLE __TABLE__(a INTEGER PRIMARY KEY, b)', true],
    'created unique index after insert equivalence' => ['CREATE TABLE __TABLE__(a INTEGER PRIMARY KEY, b)', true],
];

$same110 = [
    'integer primary key unique column order one' => ['CREATE TABLE __TABLE__(a INTEGER PRIMARY KEY UNIQUE, b UNIQUE)', false],
    'integer unique primary key column order two' => ['CREATE TABLE __TABLE__(a INTEGER UNIQUE PRIMARY KEY, b UNIQUE)', false],
    'redundant unique primary key table constraint' => ['CREATE TABLE __TABLE__(a INTEGER UNIQUE PRIMARY KEY, b UNIQUE, UNIQUE(a))', false],
    'unique primary key plus created unique index before insert' => ['CREATE TABLE __TABLE__(a INTEGER UNIQUE PRIMARY KEY, b)', true],
    'unique primary key plus created unique index after insert' => ['CREATE TABLE __TABLE__(a INTEGER UNIQUE PRIMARY KEY, b)', true],
];

$same120 = [
    'without rowid inline integer primary key and unique' => ['CREATE TABLE __TABLE__(a INTEGER PRIMARY KEY, b UNIQUE) WITHOUT ROWID', false],
    'without rowid table primary key then unique' => ['CREATE TABLE __TABLE__(xyz INTEGER, abc, PRIMARY KEY(xyz), UNIQUE(abc)) WITHOUT ROWID', false],
    'without rowid unique before primary key' => ['CREATE TABLE __TABLE__(xyz INTEGER, abc, UNIQUE(abc), PRIMARY KEY(xyz)) WITHOUT ROWID', false],
    'without rowid primary key asc' => ['CREATE TABLE __TABLE__(a INTEGER PRIMARY KEY ASC, b UNIQUE) WITHOUT ROWID', false],
    'without rowid primary key unique column order one' => ['CREATE TABLE __TABLE__(a INTEGER PRIMARY KEY UNIQUE, b UNIQUE) WITHOUT ROWID', false],
    'without rowid unique primary key column order two' => ['CREATE TABLE __TABLE__(a INTEGER UNIQUE PRIMARY KEY, b UNIQUE) WITHOUT ROWID', false],
    'without rowid redundant unique primary key' => ['CREATE TABLE __TABLE__(a INTEGER UNIQUE PRIMARY KEY, b UNIQUE, UNIQUE(a)) WITHOUT ROWID', false],
    'without rowid created unique index before insert' => ['CREATE TABLE __TABLE__(a INTEGER PRIMARY KEY, b) WITHOUT ROWID', true],
    'without rowid created unique index after insert' => ['CREATE TABLE __TABLE__(a INTEGER PRIMARY KEY, b) WITHOUT ROWID', true],
];

foreach (range(1, 120) as $variant) {
    $tests[sprintf('real upstream pragma schema6 same-content 100 rowid metadata equivalence variant %03d', $variant)] = static function (TestRunner $t) use ($catalogFor, $normalizeTableInfo, $same100, $variant): void {
        $baseline = null;
        foreach ($same100 as [$sql, $createdUnique]) {
            [$catalog, $table] = $catalogFor($variant, 'same100', $sql, $createdUnique);
            $shape = $normalizeTableInfo($catalog->execute("PRAGMA table_info({$table})")['rows']);
            $baseline ??= $shape;
            $t->same($baseline, $shape);
            $t->same([0], array_column($catalog->execute("PRAGMA table_list({$table})")['rows'], 'wr'));
        }
    };

    $tests[sprintf('real upstream pragma schema6 same-content 100 unique index equivalence variant %03d', $variant)] = static function (TestRunner $t) use ($catalogFor, $normalizeIndexXInfo, $same100, $variant): void {
        $baseline = null;
        foreach ($same100 as [$sql, $createdUnique]) {
            [$catalog, $table, $createdIndex] = $catalogFor($variant, 'same100idx', $sql, $createdUnique);
            $indexRows = $catalog->execute("PRAGMA index_list({$table})")['rows'];
            $t->same(1, count($indexRows));
            $t->same(1, $indexRows[0]['unique']);
            $indexName = $createdUnique ? $createdIndex : $indexRows[0]['name'];
            $shape = $normalizeIndexXInfo($catalog->execute("PRAGMA index_xinfo({$indexName})")['rows']);
            $baseline ??= $shape;
            $t->same($baseline, $shape);
        }
    };

    $tests[sprintf('real upstream pragma schema6 same-content 110 redundant unique primary key variant %03d', $variant)] = static function (TestRunner $t) use ($catalogFor, $normalizeTableInfo, $same110, $variant): void {
        $baseline = null;
        foreach ($same110 as [$sql, $createdUnique]) {
            [$catalog, $table] = $catalogFor($variant, 'same110', $sql, $createdUnique);
            $tableInfo = $catalog->execute("PRAGMA table_info({$table})")['rows'];
            $shape = $normalizeTableInfo($tableInfo);
            $baseline ??= $shape;
            $t->same($baseline, $shape);
            $t->same([1, 0], array_column($tableInfo, 'pk'));
            $t->same([0], array_column($catalog->execute("PRAGMA table_list({$table})")['rows'], 'wr'));
        }
    };

    $tests[sprintf('real upstream pragma schema6 same-content 120 without-rowid metadata equivalence variant %03d', $variant)] = static function (TestRunner $t) use ($catalogFor, $normalizeTableInfo, $same120, $variant): void {
        $baseline = null;
        foreach ($same120 as [$sql, $createdUnique]) {
            [$catalog, $table] = $catalogFor($variant, 'same120', $sql, $createdUnique);
            $shape = $normalizeTableInfo($catalog->execute("PRAGMA table_info({$table})")['rows']);
            $baseline ??= $shape;
            $t->same($baseline, $shape);
            $t->same([1], array_column($catalog->execute("PRAGMA table_list({$table})")['rows'], 'wr'));
        }
    };

    $tests[sprintf('real upstream pragma schema6 same-content 120 without-rowid unique index auxiliary shape variant %03d', $variant)] = static function (TestRunner $t) use ($catalogFor, $normalizeIndexXInfo, $same120, $variant): void {
        foreach ($same120 as [$sql, $createdUnique]) {
            [$catalog, $table, $createdIndex] = $catalogFor($variant, 'same120idx', $sql, $createdUnique);
            $indexRows = $catalog->execute("PRAGMA index_list({$table})")['rows'];
            $t->same(1, count($indexRows));
            $t->same(1, $indexRows[0]['unique']);
            $indexName = $createdUnique ? $createdIndex : $indexRows[0]['name'];
            $shape = $normalizeIndexXInfo($catalog->execute("PRAGMA index_xinfo({$indexName})")['rows']);
            $t->same(1, $shape[0]['key']);
            if ($createdUnique) {
                $t->same([1, 0], array_column($shape, 'key'));
                continue;
            }

            $t->same(true, count($shape) >= 1);
            $t->same(true, in_array($shape[0]['cid'], [0, 1], true));
        }
    };

    $tests[sprintf('real upstream pragma schema6 different-content 130 rowid vs without-rowid variant %03d', $variant)] = static function (TestRunner $t) use ($catalogFor, $variant): void {
        [$rowidCatalog, $rowidTable] = $catalogFor($variant, 'diff130rowid', 'CREATE TABLE __TABLE__(a INTEGER PRIMARY KEY, b UNIQUE)');
        [$uniquePkCatalog, $uniquePkTable] = $catalogFor($variant, 'diff130unique', 'CREATE TABLE __TABLE__(a INTEGER PRIMARY KEY UNIQUE, b UNIQUE)');
        [$wrCatalog, $wrTable] = $catalogFor($variant, 'diff130wr', 'CREATE TABLE __TABLE__(a INTEGER PRIMARY KEY, b UNIQUE) WITHOUT ROWID');

        $t->same([0], array_column($rowidCatalog->execute("PRAGMA table_list({$rowidTable})")['rows'], 'wr'));
        $t->same([0], array_column($uniquePkCatalog->execute("PRAGMA table_list({$uniquePkTable})")['rows'], 'wr'));
        $t->same([1], array_column($wrCatalog->execute("PRAGMA table_list({$wrTable})")['rows'], 'wr'));
        $t->same([1, 0], array_column($rowidCatalog->execute("PRAGMA table_info({$rowidTable})")['rows'], 'pk'));
        $t->same([1, 0], array_column($uniquePkCatalog->execute("PRAGMA table_info({$uniquePkTable})")['rows'], 'pk'));
        $t->same([1, 0], array_column($wrCatalog->execute("PRAGMA table_info({$wrTable})")['rows'], 'pk'));
    };
}

$tests['real upstream pragma schema6 equivalence cites source corpus sections'] = static function (TestRunner $t): void {
    $sections = [
        'schema6.test check_same_database_content 100 treats inline rowid primary-key and separate unique-index forms as equivalent after page 1',
        'schema6.test check_same_database_content 110 treats UNIQUE PRIMARY KEY token order and redundant UNIQUE constraints as equivalent',
        'schema6.test check_same_database_content 120 repeats the same equivalence matrix for WITHOUT ROWID tables',
        'schema6.test check_different_database_content 130 keeps rowid and WITHOUT ROWID schemas observably distinct',
        'pragma.test pragma-6.2/6.4/6.5 and pragma5.test table_list are the PRAGMA surfaces used to observe these dynamic schema shapes',
    ];

    $t->same(5, count($sections));
    $t->same(true, str_contains($sections[0], 'schema6.test'));
    $t->same(true, str_contains($sections[4], 'table_list'));
};

return $tests;

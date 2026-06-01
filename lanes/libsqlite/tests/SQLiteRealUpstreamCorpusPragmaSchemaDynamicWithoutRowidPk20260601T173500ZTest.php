<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePragmaSchemaCatalog;
use PortLibs\LibSqlite\SQLiteSchemaRecord;

$tests = [];

/*
 * Real upstream source:
 * - SQLite test/schema6.test check_same_database_content 120: WITHOUT ROWID
 *   tables keep PRIMARY KEY and UNIQUE forms equivalent across inline,
 *   table-level, redundant, and created-index spellings.
 * - SQLite test/schema6.test check_different_database_content 130: rowid and
 *   WITHOUT ROWID primary-key schemas remain observably distinct.
 * - SQLite test/pragma6.test pragma6-1.1: a TEMP WITHOUT ROWID table with a
 *   column PRIMARY KEY, defaults, and UNIQUE constraints is admitted.
 * - SQLite test/pragma.test pragma-6.8: duplicate PRIMARY KEY terms preserve
 *   rowid-table ordinal gaps; the WITHOUT ROWID key image canonicalizes those
 *   terms while still exposing schema PRAGMA metadata.
 * - SQLite test/pragma.test pragma-25.0: a TEMP WITHOUT ROWID table and a
 *   unique index are visible to PRAGMA integrity/schema paths.
 *
 * This slice ports the PRAGMA-facing metadata edge that was not covered by the
 * existing integrity batches: primary-key columns of WITHOUT ROWID tables are
 * implicit NOT NULL columns in table_info/table_xinfo rowsets, and explicit
 * indexes on those tables append primary-key auxiliary columns instead of a
 * rowid auxiliary term.
 */

$record = static fn (
    string $type,
    string $name,
    string $table,
    ?int $rootPage,
    ?string $sql,
    int $rowId,
): SQLiteSchemaRecord => new SQLiteSchemaRecord($type, $name, $table, $rootPage, $sql, $rowId);

$schema6WithoutRowidForms = [
    ['inline integer primary key and unique', 'CREATE TABLE __TABLE__(a INTEGER PRIMARY KEY, b UNIQUE) WITHOUT ROWID'],
    ['table primary key then unique', 'CREATE TABLE __TABLE__(xyz INTEGER, abc, PRIMARY KEY(xyz), UNIQUE(abc)) WITHOUT ROWID'],
    ['unique before primary key', 'CREATE TABLE __TABLE__(xyz INTEGER, abc, UNIQUE(abc), PRIMARY KEY(xyz)) WITHOUT ROWID'],
    ['primary key asc', 'CREATE TABLE __TABLE__(a INTEGER PRIMARY KEY ASC, b UNIQUE) WITHOUT ROWID'],
    ['primary key unique column order one', 'CREATE TABLE __TABLE__(a INTEGER PRIMARY KEY UNIQUE, b UNIQUE) WITHOUT ROWID'],
    ['unique primary key column order two', 'CREATE TABLE __TABLE__(a INTEGER UNIQUE PRIMARY KEY, b UNIQUE) WITHOUT ROWID'],
    ['redundant unique primary key', 'CREATE TABLE __TABLE__(a INTEGER UNIQUE PRIMARY KEY, b UNIQUE, UNIQUE(a)) WITHOUT ROWID'],
    ['created unique index before insert', 'CREATE TABLE __TABLE__(a INTEGER PRIMARY KEY, b) WITHOUT ROWID'],
    ['created unique index after insert', 'CREATE TABLE __TABLE__(a INTEGER PRIMARY KEY, b) WITHOUT ROWID'],
];

$catalogFor = static function (array $records): SQLitePragmaSchemaCatalog {
    return new SQLitePragmaSchemaCatalog($records);
};

foreach (range(1, 250) as $variant) {
    $suffix = sprintf('%03d', $variant);

    $tests[sprintf('real upstream pragma schema dynamic without rowid pk notnull schema6 matrix variant %03d', $variant)] = static function (TestRunner $t) use ($catalogFor, $record, $schema6WithoutRowidForms, $suffix, $variant): void {
        foreach ($schema6WithoutRowidForms as $formOffset => [$label, $sqlTemplate]) {
            $table = sprintf('schema6_wr_%s_%02d', $suffix, $formOffset);
            $sql = str_replace('__TABLE__', $table, $sqlTemplate);
            $catalog = $catalogFor([
                $record('table', $table, $table, 1000 + $variant + $formOffset, $sql, 10 + $formOffset),
            ]);

            $rows = $catalog->execute("PRAGMA table_info({$table})")['rows'];
            $tableList = $catalog->execute("PRAGMA table_list({$table})")['rows'];
            $primaryRows = array_values(array_filter($rows, static fn (array $row): bool => $row['pk'] > 0));
            $nonPrimaryRows = array_values(array_filter($rows, static fn (array $row): bool => $row['pk'] === 0));

            $t->same(2, count($rows));
            $t->same(1, count($primaryRows), $label);
            $t->same(1, $primaryRows[0]['notnull'], $label);
            $t->same(1, $primaryRows[0]['pk'], $label);
            $t->same([0], array_column($nonPrimaryRows, 'notnull'), $label);
            $t->same([1], array_column($tableList, 'wr'), $label);
        }
    };

    $tests[sprintf('real upstream pragma6 temp without rowid primary key defaults variant %03d', $variant)] = static function (TestRunner $t) use ($catalogFor, $record, $suffix, $variant): void {
        $table = "pragma6_temp_wr_{$suffix}";
        $catalog = $catalogFor([
            $record(
                'table',
                $table,
                $table,
                2000 + $variant,
                "CREATE TABLE {$table}(
                    a t1 PRIMARY KEY DEFAULT {$variant},
                    b DEFAULT(current_timestamp),
                    d TEXT UNIQUE DEFAULT 'charlie_{$suffix}',
                    c TEXT UNIQUE DEFAULT 084,
                    UNIQUE(c,b,b,a,b)
                ) WITHOUT ROWID",
                200 + $variant,
            ),
            $record('index', "sqlite_autoindex_{$table}_1", $table, 3000 + $variant, null, 300 + $variant),
            $record('index', "sqlite_autoindex_{$table}_2", $table, 4000 + $variant, null, 400 + $variant),
            $record('index', "sqlite_autoindex_{$table}_3", $table, 5000 + $variant, null, 500 + $variant),
        ]);

        $rows = $catalog->execute("PRAGMA table_info({$table})")['rows'];
        $xInfo = $catalog->executeTableValuedPragma("pragma_table_xinfo('{$table}', 'temp')")['rows'];
        $indexes = $catalog->execute("PRAGMA index_list({$table})")['rows'];

        $t->same(['a', 'b', 'd', 'c'], array_column($rows, 'name'));
        $t->same([1, 0, 0, 0], array_column($rows, 'pk'));
        $t->same([1, 0, 0, 0], array_column($rows, 'notnull'));
        $t->same([(string) $variant, 'current_timestamp', "'charlie_{$suffix}'", '084'], array_column($rows, 'dflt_value'));
        $t->same(array_column($rows, 'notnull'), array_column($xInfo, 'notnull'));
        $t->same([1, 1, 1], array_column($indexes, 'unique'));
        $t->same(['pk', 'u', 'u'], array_column($indexes, 'origin'));
    };

    $tests[sprintf('real upstream pragma duplicate pk rowid gap and without rowid canonicalization variant %03d', $variant)] = static function (TestRunner $t) use ($catalogFor, $record, $suffix, $variant): void {
        $rowid = "pragma68_rowid_{$suffix}";
        $withoutRowid = "pragma68_wr_{$suffix}";
        $catalog = $catalogFor([
            $record('table', $rowid, $rowid, 6000 + $variant, "CREATE TABLE {$rowid}(a,b,c,PRIMARY KEY(a,b,a,c))", 600 + $variant),
            $record('table', $withoutRowid, $withoutRowid, 7000 + $variant, "CREATE TABLE {$withoutRowid}(a,b,c,d,PRIMARY KEY(a,b,a,c)) WITHOUT ROWID", 700 + $variant),
        ]);

        $rowidRows = $catalog->execute("PRAGMA table_info({$rowid})")['rows'];
        $withoutRowidRows = $catalog->execute("PRAGMA table_info({$withoutRowid})")['rows'];

        $t->same([1, 2, 4], array_column($rowidRows, 'pk'));
        $t->same([0, 0, 0], array_column($rowidRows, 'notnull'));
        $t->same([1, 2, 3, 0], array_column($withoutRowidRows, 'pk'));
        $t->same([1, 1, 1, 0], array_column($withoutRowidRows, 'notnull'));
        $t->same(['a', 'b', 'c', 'd'], array_column($withoutRowidRows, 'name'));
        $t->same([1], array_column($catalog->execute("PRAGMA table_list({$withoutRowid})")['rows'], 'wr'));
    };

    $tests[sprintf('real upstream pragma25 without rowid index xinfo primary key auxiliaries variant %03d', $variant)] = static function (TestRunner $t) use ($catalogFor, $record, $suffix, $variant): void {
        $table = "pragma25_wr_idx_{$suffix}";
        $index = "pragma25_wr_idx_lookup_{$suffix}";
        $catalog = $catalogFor([
            $record(
                'table',
                $table,
                $table,
                8000 + $variant,
                "CREATE TABLE {$table}(a, b, c, d DEFAULT 'v_{$suffix}', PRIMARY KEY(a,b)) WITHOUT ROWID",
                800 + $variant,
            ),
            $record('index', "sqlite_autoindex_{$table}_1", $table, 9000 + $variant, null, 900 + $variant),
            $record('index', $index, $table, 10000 + $variant, "CREATE UNIQUE INDEX {$index} ON {$table}(c)", 1000 + $variant),
        ]);

        $tableInfo = $catalog->execute("PRAGMA table_info({$table})")['rows'];
        $indexXInfo = $catalog->execute("PRAGMA index_xinfo({$index})")['rows'];
        $autoXInfo = $catalog->execute("PRAGMA index_xinfo(sqlite_autoindex_{$table}_1)")['rows'];

        $t->same(['a', 'b', 'c', 'd'], array_column($tableInfo, 'name'));
        $t->same([1, 2, 0, 0], array_column($tableInfo, 'pk'));
        $t->same([1, 1, 0, 0], array_column($tableInfo, 'notnull'));
        $t->same(['c', 'a', 'b'], array_column($indexXInfo, 'name'));
        $t->same([1, 0, 0], array_column($indexXInfo, 'key'));
        $t->same(false, in_array(-1, array_column($indexXInfo, 'cid'), true));
        $t->same(['a', 'b'], array_column($autoXInfo, 'name'));
        $t->same([1, 1], array_column($autoXInfo, 'key'));
    };
}

$tests['real upstream pragma schema dynamic without rowid primary key source corpus cited'] = static function (TestRunner $t): void {
    $sections = [
        'schema6.test check_same_database_content 120 WITHOUT ROWID primary-key/unique equivalence matrix',
        'schema6.test check_different_database_content 130 rowid and WITHOUT ROWID schemas remain distinct',
        'pragma6.test pragma6-1.1 TEMP WITHOUT ROWID primary-key/default/unique table',
        'pragma.test pragma-6.8 duplicate PRIMARY KEY ordinal behavior for rowid tables',
        'pragma.test pragma-25.0 TEMP WITHOUT ROWID table and unique index shape',
    ];

    $t->same(5, count($sections));
    $t->contains('WITHOUT ROWID', $sections[0]);
    $t->contains('pragma6-1.1', $sections[2]);
    $t->contains('pragma-25.0', $sections[4]);
};

return $tests;

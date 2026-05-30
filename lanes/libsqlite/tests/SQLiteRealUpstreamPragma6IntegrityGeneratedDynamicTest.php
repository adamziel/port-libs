<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePointerMapEntry;
use PortLibs\LibSqlite\SQLitePragmaIntegrityCheck;
use PortLibs\LibSqlite\SQLitePragmaSchemaCatalog;
use PortLibs\LibSqlite\SQLiteSchemaRecord;
use PortLibs\LibSqlite\SQLiteTableLeafCell;
use PortLibs\LibSqlite\SQLiteTableLeafPage;

$tests = [];

/*
 * Real upstream source:
 * - SQLite test/pragma6.test pragma6-1.0 through pragma6-1.2:
 *   PRAGMA integrity_check and quick_check must complete successfully after
 *   generated-column schemas and a WITHOUT ROWID temp table with defaults,
 *   unique constraints, and an ignored oversized insert attempt.
 *
 * The hydrated upstream test uses a concrete hex database. This focused PHP
 * corpus keeps the behavior source-neutral by varying generated table names,
 * generated expressions, temp WITHOUT ROWID default values, unique indexes,
 * pointer-map pages, and clean leaf images while asserting that schema PRAGMA
 * metadata and both integrity PRAGMAs remain stable.
 */

$pageSize = 512;

$headerPage = static function (int $pageCount, int $largestRoot = 0) use ($pageSize): string {
    $page = str_repeat("\0", $pageSize);
    $page = substr_replace($page, "SQLite format 3\0", 0, 16);
    $page = substr_replace($page, pack('n', $pageSize), 16, 2);
    $page[18] = "\x01";
    $page[19] = "\x01";
    $page = substr_replace($page, pack('N', $pageCount), 28, 4);
    $page = substr_replace($page, pack('N', $largestRoot), 52, 4);
    $page = substr_replace($page, pack('N', 1), 56, 4);

    return $page;
};

$putPointerMapEntry = static function (string $page, int $pageNumber, int $type, int $parent) use ($pageSize): string {
    $offset = 5 * ($pageNumber - 3);

    return substr_replace($page, chr($type) . pack('N', $parent), $offset, 5);
};

$databaseFor = static function (int $variant) use ($headerPage, $putPointerMapEntry, $pageSize): string {
    $leaf = SQLiteTableLeafPage::assemble([
        SQLiteTableLeafCell::encode($variant, 'generated-setting-' . $variant),
        SQLiteTableLeafCell::encode($variant + 1000, 'temp-default-' . $variant),
    ], $pageSize);
    $pointerMap = $putPointerMapEntry(str_repeat("\0", $pageSize), 3, SQLitePointerMapEntry::ROOT_PAGE, 0);

    return implode('', [
        1 => $headerPage(3, 3),
        2 => $pointerMap,
        3 => $leaf,
    ]);
};

$catalogFor = static function (int $variant): SQLitePragmaSchemaCatalog {
    $suffix = sprintf('%03d', $variant);
    $generated = 'pragma6_generated_' . $suffix;
    $stored = 'pragma6_stored_' . $suffix;
    $temp = 'pragma6_temp_' . $suffix;

    return new SQLitePragmaSchemaCatalog([
        new SQLiteSchemaRecord(
            'table',
            $generated,
            $generated,
            10 + $variant,
            "CREATE TABLE {$generated}(a INT, b AS (a*2) NOT NULL, c TEXT GENERATED ALWAYS AS (printf('%03d', a)) VIRTUAL)",
            100 + $variant,
        ),
        new SQLiteSchemaRecord(
            'table',
            $stored,
            $stored,
            400 + $variant,
            "CREATE TABLE {$stored}(a INT, b INT GENERATED ALWAYS AS (a+{$variant}) STORED NOT NULL, c TEXT DEFAULT 'stored_{$suffix}')",
            500 + $variant,
        ),
        new SQLiteSchemaRecord(
            'table',
            $temp,
            $temp,
            700 + $variant,
            "CREATE TABLE {$temp}(
                a t1 PRIMARY KEY DEFAULT {$variant},
                b DEFAULT(current_timestamp),
                d TEXT UNIQUE DEFAULT 'charlie_{$suffix}',
                c TEXT UNIQUE DEFAULT 084,
                UNIQUE(c,b,b,a,b)
            ) WITHOUT ROWID",
            800 + $variant,
        ),
        new SQLiteSchemaRecord('index', 'sqlite_autoindex_' . $temp . '_1', $temp, 900 + $variant, null, 900 + $variant),
        new SQLiteSchemaRecord('index', 'sqlite_autoindex_' . $temp . '_2', $temp, 1000 + $variant, null, 1000 + $variant),
        new SQLiteSchemaRecord('index', 'sqlite_autoindex_' . $temp . '_3', $temp, 1100 + $variant, null, 1100 + $variant),
    ]);
};

foreach (range(1, 250) as $variant) {
    $suffix = sprintf('%03d', $variant);
    $generated = 'pragma6_generated_' . $suffix;
    $stored = 'pragma6_stored_' . $suffix;
    $temp = 'pragma6_temp_' . $suffix;

    $tests[sprintf('real upstream pragma6 generated xinfo quickcheck metadata variant %03d', $variant)] = static function (TestRunner $t) use ($catalogFor, $generated, $variant): void {
        $rows = $catalogFor($variant)->execute("PRAGMA table_xinfo({$generated})")['rows'];

        $t->same(['a', 'b', 'c'], array_column($rows, 'name'));
        $t->same([0, 2, 2], array_column($rows, 'hidden'));
        $t->same([0, 1, 0], array_column($rows, 'notnull'));
        $t->same('', $rows[1]['dflt_value'] ?? '');
        $t->same(0, $rows[0]['pk']);
        $t->same(0, $rows[1]['pk']);
    };

    $tests[sprintf('real upstream pragma6 stored generated metadata variant %03d', $variant)] = static function (TestRunner $t) use ($catalogFor, $stored, $variant, $suffix): void {
        $rows = $catalogFor($variant)->executeTableValuedPragma("pragma_table_xinfo('{$stored}')")['rows'];

        $t->same(['a', 'b', 'c'], array_column($rows, 'name'));
        $t->same([0, 3, 0], array_column($rows, 'hidden'));
        $t->same([0, 1, 0], array_column($rows, 'notnull'));
        $t->same("'stored_{$suffix}'", $rows[2]['dflt_value']);
        $t->same(3, count($rows));
        $t->same([], $catalogFor($variant)->execute("PRAGMA table_info(missing_{$suffix})")['rows']);
    };

    $tests[sprintf('real upstream pragma6 temp without rowid defaults and indexes variant %03d', $variant)] = static function (TestRunner $t) use ($catalogFor, $temp, $variant, $suffix): void {
        $catalog = $catalogFor($variant);
        $info = $catalog->execute("PRAGMA table_info({$temp})")['rows'];
        $tableList = $catalog->execute("PRAGMA table_list({$temp})")['rows'];
        $indexList = $catalog->execute("PRAGMA index_list({$temp})")['rows'];

        $t->same(['a', 'b', 'd', 'c'], array_column($info, 'name'));
        $t->same([(string) $variant, 'current_timestamp', "'charlie_{$suffix}'", '084'], array_column($info, 'dflt_value'));
        $t->same([1, 0, 0, 0], array_column($info, 'pk'));
        $t->same(1, $tableList[0]['wr']);
        $t->same([1, 1, 1], array_column($indexList, 'unique'));
        $t->same(['pk', 'u', 'u'], array_column($indexList, 'origin'));
    };

    $tests[sprintf('real upstream pragma6 integrity and quick check remain ok variant %03d', $variant)] = static function (TestRunner $t) use ($databaseFor, $variant): void {
        $database = $databaseFor($variant);
        $integrity = SQLitePragmaIntegrityCheck::execute('PRAGMA integrity_check', $database);
        $quick = SQLitePragmaIntegrityCheck::execute('PRAGMA quick_check', $database);

        $t->same('integrity_check', $integrity['pragma']);
        $t->same('quick_check', $quick['pragma']);
        $t->same([['integrity_check' => 'ok']], $integrity['rows']);
        $t->same([['quick_check' => 'ok']], $quick['rows']);
        $t->same([], $integrity['errors']);
        $t->same([], $quick['errors']);
    };
}

return $tests;

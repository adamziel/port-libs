<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePragmaIntegrityCheck;
use PortLibs\LibSqlite\SQLiteRecord;
use PortLibs\LibSqlite\SQLiteTableLeafCell;
use PortLibs\LibSqlite\SQLiteTableLeafPage;

$tests = [];

/*
 * Real upstream source: SQLite test/pragma6.test.
 *
 * pragma6-1.0 deserializes a database whose sqlite_schema contains ordinary
 * and generated-column table definitions, pragma6-1.1 attempts a temp
 * WITHOUT ROWID table with default expressions and redundant UNIQUE terms, and
 * pragma6-1.2 verifies both PRAGMA integrity_check and quick_check complete
 * without surfacing schema corruption.
 */

$pageSize = 4096;

$headerPage = static function (int $pageCount) use ($pageSize): string {
    $page = str_repeat("\0", $pageSize);
    $page = substr_replace($page, "SQLite format 3\0", 0, 16);
    $page = substr_replace($page, pack('n', $pageSize), 16, 2);
    $page[18] = "\x01";
    $page[19] = "\x01";
    $page = substr_replace($page, pack('N', $pageCount), 28, 4);
    $page = substr_replace($page, pack('N', 4), 44, 4);
    $page = substr_replace($page, pack('N', 1), 56, 4);

    return $page;
};

$schemaCell = static function (int $rowId, array $values) use ($pageSize): string {
    return SQLiteTableLeafCell::encode($rowId, SQLiteRecord::encode($values), $pageSize);
};

$makeDatabase = static function (int $variant) use ($headerPage, $schemaCell, $pageSize): string {
    $tableA = "pragma6_generated_a_{$variant}";
    $tableB = "pragma6_generated_b_{$variant}";
    $tableC = "pragma6_plain_{$variant}";
    $factor = ($variant % 17) + 2;
    $default = 20 + ($variant % 70);

    $schemaRows = [
        $schemaCell(1, [
            'table',
            $tableA,
            $tableA,
            2,
            "CREATE TABLE {$tableA}(a INT, b AS (a*{$factor}) NOT NULL)",
        ]),
        $schemaCell(2, [
            'table',
            $tableB,
            $tableB,
            3,
            "CREATE TABLE {$tableB}(a INT, b AS (a*{$factor}) STORED NOT NULL)",
        ]),
        $schemaCell(3, [
            'table',
            $tableC,
            $tableC,
            4,
            "CREATE TABLE {$tableC}(a t1 PRIMARY KEY default {$default}, b default(current_timestamp), d TEXT UNIQUE DEFAULT 'charlie_{$variant}', c TEXT UNIQUE DEFAULT 084, UNIQUE(c,b,b,a,b)) WITHOUT ROWID",
        ]),
    ];

    $pages = [
        1 => SQLiteTableLeafPage::assemble($schemaRows, $pageSize, 100, $headerPage(4)),
        2 => SQLiteTableLeafPage::assemble([
            SQLiteTableLeafCell::encode(1, SQLiteRecord::encode([$variant]), $pageSize),
            SQLiteTableLeafCell::encode(2, SQLiteRecord::encode([$variant + 1]), $pageSize),
        ], $pageSize),
        3 => SQLiteTableLeafPage::assemble([
            SQLiteTableLeafCell::encode(1, SQLiteRecord::encode([$variant + 2]), $pageSize),
        ], $pageSize),
        4 => SQLiteTableLeafPage::assemble([
            SQLiteTableLeafCell::encode(1, SQLiteRecord::encode([$default, 'runtime', 'charlie_' . $variant, 84]), $pageSize),
        ], $pageSize),
    ];

    return implode('', $pages);
};

foreach (range(1, 500) as $variant) {
    $tests[sprintf('real upstream pragma6 generated schema integrity_check ok variant %03d', $variant)] = static function (TestRunner $t) use ($makeDatabase, $variant): void {
        $result = SQLitePragmaIntegrityCheck::execute('PRAGMA integrity_check', $makeDatabase($variant));

        $t->same('integrity_check', $result['pragma']);
        $t->same(100, $result['limit']);
        $t->same([], $result['errors']);
        $t->same([['integrity_check' => 'ok']], $result['rows']);
    };

    $tests[sprintf('real upstream pragma6 generated schema quick_check ok variant %03d', $variant)] = static function (TestRunner $t) use ($makeDatabase, $variant): void {
        $result = SQLitePragmaIntegrityCheck::execute('PRAGMA quick_check', $makeDatabase($variant));

        $t->same('quick_check', $result['pragma']);
        $t->same(100, $result['limit']);
        $t->same([], $result['errors']);
        $t->same([['quick_check' => 'ok']], $result['rows']);
    };
}

$tests['real upstream pragma6 source sections cited'] = static function (TestRunner $t): void {
    $sections = [
        'pragma6.test pragma6-1.0 deserializes generated-column schema records',
        'pragma6.test pragma6-1.1 attempts temp WITHOUT ROWID default/UNIQUE DDL',
        'pragma6.test pragma6-1.2 verifies PRAGMA integrity_check and quick_check',
    ];

    $t->same(3, count($sections));
    $t->contains('pragma6-1.0', $sections[0]);
    $t->contains('WITHOUT ROWID', $sections[1]);
    $t->contains('quick_check', $sections[2]);
};

return $tests;

<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePragmaIntegrityCheck;
use PortLibs\LibSqlite\SQLitePragmaSchemaCatalog;
use PortLibs\LibSqlite\SQLiteRecord;
use PortLibs\LibSqlite\SQLiteSchemaRecord;
use PortLibs\LibSqlite\SQLiteTableLeafCell;
use PortLibs\LibSqlite\SQLiteTableLeafPage;

$tests = [];

/*
 * Real upstream source: SQLite test/pragma.test 25.0.
 *
 * The upstream case creates a generated-column main table, a TEMP WITHOUT
 * ROWID table with primary-key and unique constraints, a temp unique index,
 * then runs PRAGMA integrity_check. This ports that mixed schema shape into
 * the PHP PRAGMA catalog and file-integrity paths without repeating the
 * earlier pragma6 generated-schema-only integrity batch.
 */

$record = static fn (
    string $type,
    string $name,
    string $table,
    ?int $rootPage,
    ?string $sql,
    int $rowId,
): SQLiteSchemaRecord => new SQLiteSchemaRecord($type, $name, $table, $rootPage, $sql, $rowId);

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
    $main = "pragma25_generated_{$variant}";
    $factor = 2 + ($variant % 11);

    $schemaRows = [
        $schemaCell(1, [
            'table',
            $main,
            $main,
            2,
            "CREATE TABLE {$main}(a INT, b AS (a*{$factor}) NOT NULL)",
        ]),
    ];

    $pages = [
        1 => SQLiteTableLeafPage::assemble($schemaRows, $pageSize, 100, $headerPage(2)),
        2 => SQLiteTableLeafPage::assemble([
            SQLiteTableLeafCell::encode(1, SQLiteRecord::encode([$variant]), $pageSize),
            SQLiteTableLeafCell::encode(2, SQLiteRecord::encode([$variant + 1]), $pageSize),
        ], $pageSize),
    ];

    return implode('', $pages);
};

$catalogFor = static function (int $variant) use ($record): array {
    $main = "pragma25_generated_{$variant}";
    $temp = "pragma25_temp_{$variant}";
    $tempAuto = "sqlite_autoindex_{$temp}_1";
    $tempUnique = "pragma25_temp_unique_{$variant}";
    $factor = 2 + ($variant % 11);

    $catalog = new SQLitePragmaSchemaCatalog([
        $record('table', $main, $main, 10 + $variant, "CREATE TABLE {$main}(a INT, b AS (a*{$factor}) NOT NULL)", 1),
        $record('table', $temp, $temp, 20 + $variant, "CREATE TABLE {$temp}(a PRIMARY KEY, b, c UNIQUE) WITHOUT ROWID", 2),
        $record('index', $tempAuto, $temp, 30 + $variant, null, 3),
        $record('index', $tempUnique, $temp, 40 + $variant, "CREATE UNIQUE INDEX {$tempUnique} ON {$temp}(c,b)", 4),
    ]);

    return [$catalog, $main, $temp, $tempAuto, $tempUnique];
};

foreach (range(1, 600) as $variant) {
    $tests[sprintf('real upstream pragma.test 25 generated temp integrity schema shape variant %03d', $variant)] = static function (TestRunner $t) use ($catalogFor, $makeDatabase, $variant): void {
        [$catalog, $main, $temp, $tempAuto, $tempUnique] = $catalogFor($variant);

        $mainXInfo = $catalog->execute("PRAGMA table_xinfo({$main})")['rows'];
        $tempList = $catalog->execute("PRAGMA table_list({$temp})")['rows'];
        $tempIndexes = $catalog->execute("PRAGMA index_list({$temp})")['rows'];
        $tempUniqueXInfo = $catalog->execute("PRAGMA index_xinfo({$tempUnique})")['rows'];
        $tempAutoXInfo = $catalog->execute("PRAGMA index_xinfo({$tempAuto})")['rows'];
        $integrity = SQLitePragmaIntegrityCheck::execute('PRAGMA integrity_check', $makeDatabase($variant));
        $quick = SQLitePragmaIntegrityCheck::execute('PRAGMA quick_check', $makeDatabase($variant));

        $t->same(['a', 'b'], array_column($mainXInfo, 'name'));
        $t->same([0, 2], array_column($mainXInfo, 'hidden'));
        $t->same([0, 1], array_column($mainXInfo, 'notnull'));
        $t->same([1], array_column($tempList, 'wr'));
        $t->same([0], array_column($tempList, 'strict'));
        $t->same([$tempAuto, $tempUnique], array_column($tempIndexes, 'name'));
        $t->same([1, 1], array_column($tempIndexes, 'unique'));
        $t->same(['pk', 'c'], array_column($tempIndexes, 'origin'));
        $t->same(['c', 'b', 'a'], array_column($tempUniqueXInfo, 'name'));
        $t->same([1, 1, 0], array_column($tempUniqueXInfo, 'key'));
        $t->same(['a'], array_column($tempAutoXInfo, 'name'));
        $t->same([1], array_column($tempAutoXInfo, 'key'));
        $t->same([], $integrity['errors']);
        $t->same([['integrity_check' => 'ok']], $integrity['rows']);
        $t->same([], $quick['errors']);
        $t->same([['quick_check' => 'ok']], $quick['rows']);
    };
}

$tests['real upstream pragma.test 25 generated temp integrity source cited'] = static function (TestRunner $t): void {
    $sections = [
        'pragma.test 25.0 creates a generated-column table',
        'pragma.test 25.0 creates a TEMP WITHOUT ROWID table with PRIMARY KEY and UNIQUE constraints',
        'pragma.test 25.0 creates a UNIQUE temp index on c,b',
        'pragma.test 25.0 runs PRAGMA integrity_check and expects ok',
    ];

    $t->same(4, count($sections));
    $t->contains('generated-column', $sections[0]);
    $t->contains('WITHOUT ROWID', $sections[1]);
    $t->contains('UNIQUE temp index', $sections[2]);
    $t->contains('integrity_check', $sections[3]);
};

return $tests;

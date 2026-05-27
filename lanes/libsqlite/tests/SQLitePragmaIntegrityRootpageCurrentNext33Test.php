<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteFreelistTrunkPage;
use PortLibs\LibSqlite\SQLitePointerMapEntry;
use PortLibs\LibSqlite\SQLitePragmaIntegrityCheck;
use PortLibs\LibSqlite\SQLiteRecord;
use PortLibs\LibSqlite\SQLiteTableLeafCell;
use PortLibs\LibSqlite\SQLiteTableLeafPage;

$tests = [];

$pageSize = 1024;

$headerPage = static function (int $pageCount, int $largestRootPage) use ($pageSize): string {
    $page = str_repeat("\0", $pageSize);
    $page = substr_replace($page, "SQLite format 3\0", 0, 16);
    $page = substr_replace($page, pack('n', $pageSize), 16, 2);
    $page[18] = "\x01";
    $page[19] = "\x01";
    $page = substr_replace($page, pack('N', $pageCount), 28, 4);
    $page = substr_replace($page, pack('N', $largestRootPage), 52, 4);
    $page = substr_replace($page, pack('N', 1), 56, 4);

    return $page;
};

$putPointerMapEntry = static function (string $page, int $pageNumber, int $type, int $parent) use ($pageSize): string {
    $offset = 5 * ($pageNumber - 3);

    return substr_replace($page, chr($type) . pack('N', $parent), $offset, 5);
};

$schemaCell = static function (array $values, int $rowId): string {
    return SQLiteTableLeafCell::encode($rowId, SQLiteRecord::encode($values));
};

$schemaDatabase = static function (
    array $schemaRows,
    int $pageCount,
    int $largestRootPage,
    array $pointerMapEntries,
    array $pageImages = [],
) use ($headerPage, $putPointerMapEntry, $schemaCell, $pageSize): string {
    $pages = [
        1 => SQLiteTableLeafPage::assemble(
            array_map(static fn (array $row, int $index): string => $schemaCell($row, $index + 1), $schemaRows, array_keys($schemaRows)),
            $pageSize,
            100,
            $headerPage($pageCount, $largestRootPage),
        ),
    ];
    if ($pageCount >= 2) {
        $pointerMap = str_repeat("\0", $pageSize);
        foreach ($pointerMapEntries as $entry) {
            $pointerMap = $putPointerMapEntry($pointerMap, $entry[0], $entry[1], $entry[2]);
        }
        $pages[2] = $pointerMap;
    }
    for ($pageNumber = 3; $pageNumber <= $pageCount; $pageNumber++) {
        $pages[$pageNumber] = $pageImages[$pageNumber] ?? SQLiteTableLeafPage::assemble([], $pageSize);
    }
    ksort($pages);

    return implode('', $pages);
};

$rows = [
    ['table', 'wp_options', 'wp_options', 4, 'CREATE TABLE wp_options(option_id integer primary key, option_name text, option_value text, autoload text)'],
    ['index', 'wp_options_name', 'wp_options', 5, 'CREATE UNIQUE INDEX wp_options_name ON wp_options(option_name)'],
    ['view', 'wp_active_options', 'wp_active_options', 0, "CREATE VIEW wp_active_options AS SELECT * FROM wp_options WHERE autoload = 'yes'"],
    ['trigger', 'wp_options_ai', 'wp_options', 0, 'CREATE TRIGGER wp_options_ai AFTER INSERT ON wp_options BEGIN SELECT 1; END'],
];

$valid = $schemaDatabase($rows, 5, 5, [
    [3, SQLitePointerMapEntry::BTREE_PAGE, 4],
    [4, SQLitePointerMapEntry::ROOT_PAGE, 0],
    [5, SQLitePointerMapEntry::ROOT_PAGE, 0],
]);

$firstError = static function (string $sql, string $database): string {
    return SQLitePragmaIntegrityCheck::execute($sql, $database)['errors'][0] ?? 'ok';
};

$cases = [
    'current schema roots accept nonroot page below largest root' => ['PRAGMA integrity_check', $valid, 'ok'],
    'quick accepts current schema root metadata' => ['PRAGMA quick_check', $valid, 'ok'],
    'schema qualified current roots accepted' => ['PRAGMA main.integrity_check', $valid, 'ok'],
    'equals limit preserves root metadata' => ['PRAGMA integrity_check=3', $valid, 'ok'],
    'parenthesized quick limit preserves root metadata' => ['PRAGMA quick_check(2)', $valid, 'ok'],
    'root page beyond image reports schema row name' => ['PRAGMA integrity_check', $schemaDatabase([
        $rows[0],
        ['index', 'wp_options_name', 'wp_options', 9, $rows[1][4]],
    ], 5, 5, [
        [4, SQLitePointerMapEntry::ROOT_PAGE, 0],
        [5, SQLitePointerMapEntry::ROOT_PAGE, 0],
    ]), 'sqlite_schema index wp_options_name rootpage 9 is beyond the database image'],
    'quick reports root page beyond image' => ['PRAGMA quick_check', $schemaDatabase([
        ['table', 'wp_options', 'wp_options', 8, $rows[0][4]],
    ], 5, 5, [
        [4, SQLitePointerMapEntry::ROOT_PAGE, 0],
        [5, SQLitePointerMapEntry::ROOT_PAGE, 0],
    ]), 'sqlite_schema table wp_options rootpage 8 is beyond the database image'],
    'largest root page above current schema max reports mismatch' => ['PRAGMA integrity_check', $schemaDatabase([$rows[0]], 5, 5, [
        [3, SQLitePointerMapEntry::BTREE_PAGE, 4],
        [4, SQLitePointerMapEntry::ROOT_PAGE, 0],
        [5, SQLitePointerMapEntry::BTREE_PAGE, 4],
    ]), 'largest root btree page 5 does not match sqlite_schema max rootpage 4'],
    'largest root page below current schema max reports mismatch' => ['PRAGMA integrity_check', $schemaDatabase([$rows[0], $rows[1]], 5, 4, [
        [4, SQLitePointerMapEntry::ROOT_PAGE, 0],
        [5, SQLitePointerMapEntry::ROOT_PAGE, 0],
    ]), 'largest root btree page 4 does not match sqlite_schema max rootpage 5'],
    'root pointer map type mismatch reports current root page' => ['PRAGMA integrity_check', $schemaDatabase([$rows[0]], 4, 4, [
        [3, SQLitePointerMapEntry::BTREE_PAGE, 4],
        [4, SQLitePointerMapEntry::BTREE_PAGE, 3],
    ]), 'pointer-map type btree-page for page 4 does not match expected root-page'],
    'stale pointer map root below largest is btree mismatch' => ['PRAGMA integrity_check', $schemaDatabase([$rows[0]], 5, 4, [
        [3, SQLitePointerMapEntry::ROOT_PAGE, 0],
        [4, SQLitePointerMapEntry::ROOT_PAGE, 0],
        [5, SQLitePointerMapEntry::BTREE_PAGE, 4],
    ]), 'pointer-map type root-page for page 3 does not match expected btree-page'],
    'view root page zero ignored for largest root comparison' => ['PRAGMA integrity_check', $schemaDatabase([$rows[0], $rows[2]], 4, 4, [
        [3, SQLitePointerMapEntry::BTREE_PAGE, 4],
        [4, SQLitePointerMapEntry::ROOT_PAGE, 0],
    ]), 'ok'],
    'trigger root page zero ignored for largest root comparison' => ['PRAGMA integrity_check', $schemaDatabase([$rows[0], $rows[3]], 4, 4, [
        [3, SQLitePointerMapEntry::BTREE_PAGE, 4],
        [4, SQLitePointerMapEntry::ROOT_PAGE, 0],
    ]), 'ok'],
    'root page on freelist reports schema root conflict' => ['PRAGMA integrity_check', (static function () use ($schemaDatabase, $rows, $pageSize): string {
        $database = $schemaDatabase([$rows[0]], 4, 4, [
            [3, SQLitePointerMapEntry::BTREE_PAGE, 4],
            [4, SQLitePointerMapEntry::ROOT_PAGE, 0],
        ], [3 => SQLiteFreelistTrunkPage::assemble(null, [4], $pageSize)]);
        $database = substr_replace($database, pack('N', 3), 32, 4);
        return substr_replace($database, pack('N', 2), 36, 4);
    })(), 'sqlite_schema table wp_options rootpage 4 is on the freelist'],
    'limit returns first schema root error only' => ['PRAGMA integrity_check(1)', $schemaDatabase([
        ['table', 'wp_options', 'wp_options', 8, $rows[0][4]],
        ['index', 'wp_options_name', 'wp_options', 9, $rows[1][4]],
    ], 5, 5, []), 'sqlite_schema table wp_options rootpage 8 is beyond the database image'],
];

foreach ($cases as $name => [$sql, $database, $expected]) {
    $tests['pragma integrity rootpage current next33 ' . $name] = static function (TestRunner $t) use ($firstError, $sql, $database, $expected): void {
        $t->same($expected, $firstError($sql, $database));
    };
}

$metadataCases = [
    'result keeps integrity pragma name' => [static fn (): mixed => SQLitePragmaIntegrityCheck::execute('PRAGMA integrity_check', $valid)['pragma'], 'integrity_check'],
    'result keeps quick pragma name' => [static fn (): mixed => SQLitePragmaIntegrityCheck::execute('PRAGMA quick_check', $valid)['pragma'], 'quick_check'],
    'integrity result keeps parsed limit' => [static fn (): mixed => SQLitePragmaIntegrityCheck::execute('PRAGMA integrity_check(9)', $valid)['limit'], 9],
    'quick result keeps parsed limit' => [static fn (): mixed => SQLitePragmaIntegrityCheck::execute('PRAGMA quick_check=7', $valid)['limit'], 7],
    'ok result keeps one row' => [static fn (): mixed => count(SQLitePragmaIntegrityCheck::execute('PRAGMA integrity_check', $valid)['rows']), 1],
    'ok result keeps zero errors' => [static fn (): mixed => count(SQLitePragmaIntegrityCheck::execute('PRAGMA integrity_check', $valid)['errors']), 0],
    'quick ok row uses quick column' => [static fn (): mixed => SQLitePragmaIntegrityCheck::execute('PRAGMA quick_check', $valid)['rows'][0]['quick_check'], 'ok'],
    'integrity ok row uses integrity column' => [static fn (): mixed => SQLitePragmaIntegrityCheck::execute('PRAGMA integrity_check', $valid)['rows'][0]['integrity_check'], 'ok'],
];

foreach ($metadataCases as $name => [$callback, $expected]) {
    $tests['pragma integrity rootpage current next33 ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        $t->same($expected, $callback());
    };
}

foreach (range(1, 27) as $index) {
    $rootPage = $index % 2 === 0 ? 4 : 5;
    $record = $index % 2 === 0 ? $rows[0] : $rows[1];
    $expectedName = $record[1];
    $tests['pragma integrity rootpage current next33 repeated current-root case ' . $index] = static function (TestRunner $t) use ($schemaDatabase, $record, $rootPage, $expectedName, $pageSize): void {
        $database = $schemaDatabase([$record], 5, $rootPage, [
            [3, SQLitePointerMapEntry::BTREE_PAGE, $rootPage],
            [4, $rootPage === 4 ? SQLitePointerMapEntry::ROOT_PAGE : SQLitePointerMapEntry::BTREE_PAGE, $rootPage === 4 ? 0 : $rootPage],
            [$rootPage, SQLitePointerMapEntry::ROOT_PAGE, 0],
            [5, $rootPage === 5 ? SQLitePointerMapEntry::ROOT_PAGE : SQLitePointerMapEntry::BTREE_PAGE, $rootPage === 5 ? 0 : $rootPage],
        ]);
        $result = SQLitePragmaIntegrityCheck::execute('PRAGMA integrity_check', $database);

        $t->same([], $result['errors']);
        $firstPage = substr($database, 0, $pageSize);
        $t->same($expectedName, SQLiteRecord::parse(SQLiteTableLeafCell::parsePageCells($firstPage, PortLibs\LibSqlite\SQLiteBTreePageHeader::parseFirstPage($firstPage))[0]->payload)->values[1]);
    };
}

return $tests;

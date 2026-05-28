<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteIndexCell;
use PortLibs\LibSqlite\SQLiteIndexInteriorPage;
use PortLibs\LibSqlite\SQLiteIndexLeafPage;
use PortLibs\LibSqlite\SQLitePointerMapEntry;
use PortLibs\LibSqlite\SQLitePragmaIntegrityCheck;
use PortLibs\LibSqlite\SQLiteRecord;
use PortLibs\LibSqlite\SQLiteTableInteriorCell;
use PortLibs\LibSqlite\SQLiteTableInteriorPage;
use PortLibs\LibSqlite\SQLiteTableLeafCell;
use PortLibs\LibSqlite\SQLiteTableLeafPage;

$tests = [];
$pageSize = 512;

$headerPage = static function (int $pageCount, int $largestRootPage = 3) use ($pageSize): string {
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

$putPointerMapEntry = static function (string $page, int $pageNumber, int $type, int $parent): string {
    return substr_replace($page, chr($type) . pack('N', $parent), 5 * ($pageNumber - 3), 5);
};

$database = static function (array $pages, array $pointerMapEntries, int $largestRootPage = 3) use ($headerPage, $putPointerMapEntry, $pageSize): string {
    $pageCount = max(array_keys($pages + [3 => true]));
    $pointerMap = str_repeat("\0", $pageSize);
    foreach ($pointerMapEntries as [$pageNumber, $type, $parent]) {
        $pointerMap = $putPointerMapEntry($pointerMap, $pageNumber, $type, $parent);
    }
    $images = [
        1 => $headerPage($pageCount, $largestRootPage),
        2 => $pointerMap,
    ];
    for ($pageNumber = 3; $pageNumber <= $pageCount; $pageNumber++) {
        $images[$pageNumber] = $pages[$pageNumber] ?? SQLiteTableLeafPage::assemble([], $pageSize);
    }
    ksort($images);

    return implode('', $images);
};

$tableLeafDatabase = static function (array $rowIds) use ($database, $pageSize): string {
    return $database([
        3 => SQLiteTableLeafPage::assemble(array_map(
            static fn (int $rowId): string => SQLiteTableLeafCell::encode($rowId, "option-{$rowId}"),
            $rowIds,
        ), $pageSize),
    ], [
        [3, SQLitePointerMapEntry::ROOT_PAGE, 0],
    ]);
};

$tableInteriorDatabase = static function (array $keys, int $rightMost = 6) use ($database, $pageSize): string {
    $children = [];
    $cells = [];
    foreach ($keys as $index => $key) {
        $childPage = 4 + $index;
        $children[$childPage] = SQLiteTableLeafPage::assemble([SQLiteTableLeafCell::encode($key, "child-{$key}")], $pageSize);
        $cells[] = SQLiteTableInteriorCell::encode($childPage, $key);
    }
    $children[$rightMost] = SQLiteTableLeafPage::assemble([SQLiteTableLeafCell::encode(999, 'right')], $pageSize);
    $entries = [[3, SQLitePointerMapEntry::ROOT_PAGE, 0]];
    foreach (array_keys($children) as $pageNumber) {
        $entries[] = [$pageNumber, SQLitePointerMapEntry::BTREE_PAGE, 3];
    }

    return $database([3 => SQLiteTableInteriorPage::assemble($cells, $rightMost, $pageSize)] + $children, $entries);
};

$indexLeafDatabase = static function (array $records) use ($database, $pageSize): string {
    return $database([
        3 => SQLiteIndexLeafPage::assemble(array_map(
            static fn (array $record): string => SQLiteIndexCell::encode(SQLiteRecord::encode($record), $pageSize),
            $records,
        ), $pageSize),
    ], [
        [3, SQLitePointerMapEntry::ROOT_PAGE, 0],
    ]);
};

$indexInteriorDatabase = static function (array $records, int $rightMost = 6) use ($database, $pageSize): string {
    $children = [];
    $cells = [];
    foreach ($records as $index => $record) {
        $childPage = 4 + $index;
        $children[$childPage] = SQLiteIndexLeafPage::assemble([SQLiteIndexCell::encode(SQLiteRecord::encode($record), $pageSize)], $pageSize);
        $cells[] = SQLiteIndexCell::encode(SQLiteRecord::encode($record), $pageSize, null, $childPage);
    }
    $children[$rightMost] = SQLiteIndexLeafPage::assemble([SQLiteIndexCell::encode(SQLiteRecord::encode(['zz', 999]), $pageSize)], $pageSize);
    $entries = [[3, SQLitePointerMapEntry::ROOT_PAGE, 0]];
    foreach (array_keys($children) as $pageNumber) {
        $entries[] = [$pageNumber, SQLitePointerMapEntry::BTREE_PAGE, 3];
    }

    return $database([3 => SQLiteIndexInteriorPage::assemble($cells, $rightMost, $pageSize)] + $children, $entries);
};

$run = static fn (string $sql, string $bytes): array => SQLitePragmaIntegrityCheck::execute($sql, $bytes);
$firstError = static fn (string $sql, string $bytes): string => $run($sql, $bytes)['errors'][0] ?? 'ok';
$errorCount = static fn (string $sql, string $bytes): int => count($run($sql, $bytes)['errors']);
$rowValue = static fn (string $sql, string $bytes): string => array_values($run($sql, $bytes)['rows'][0])[0];

$validTableLeaf = $tableLeafDatabase([1, 2, 9, 15]);
$badTableLeaf = $tableLeafDatabase([1, 9, 2, 15]);
$duplicateTableLeaf = $tableLeafDatabase([1, 2, 2, 15]);
$validTableInterior = $tableInteriorDatabase([10, 20], 6);
$badTableInterior = $tableInteriorDatabase([20, 10], 6);
$duplicateTableInterior = $tableInteriorDatabase([10, 10], 6);
$validIndexLeaf = $indexLeafDatabase([['autoload', 1], ['siteurl', 2], ['template', 3]]);
$badIndexLeaf = $indexLeafDatabase([['siteurl', 2], ['autoload', 1], ['template', 3]]);
$duplicateIndexLeaf = $indexLeafDatabase([['autoload', 1], ['autoload', 1], ['template', 3]]);
$validIndexInterior = $indexInteriorDatabase([['autoload', 1], ['siteurl', 2]], 6);
$badIndexInterior = $indexInteriorDatabase([['siteurl', 2], ['autoload', 1]], 6);
$duplicateIndexInterior = $indexInteriorDatabase([['autoload', 1], ['autoload', 1]], 6);

$cases = [
    'valid table leaf has no errors' => [static fn (): mixed => $errorCount('PRAGMA integrity_check', $validTableLeaf), 0],
    'valid table leaf row ok' => [static fn (): mixed => $rowValue('PRAGMA integrity_check', $validTableLeaf), 'ok'],
    'valid table leaf quick ok' => [static fn (): mixed => $rowValue('PRAGMA quick_check', $validTableLeaf), 'ok'],
    'table leaf out of order message' => [static fn (): mixed => $firstError('PRAGMA integrity_check', $badTableLeaf), 'btree page 3: table leaf rowid 2 is not greater than previous rowid 9'],
    'table leaf out of order rowset' => [static fn (): mixed => $rowValue('PRAGMA integrity_check', $badTableLeaf), 'btree page 3: table leaf rowid 2 is not greater than previous rowid 9'],
    'table leaf duplicate message' => [static fn (): mixed => $firstError('PRAGMA integrity_check', $duplicateTableLeaf), 'btree page 3: table leaf rowid 2 is not greater than previous rowid 2'],
    'table leaf duplicate count' => [static fn (): mixed => $errorCount('PRAGMA integrity_check', $duplicateTableLeaf), 1],
    'table leaf quick skips order' => [static fn (): mixed => $firstError('PRAGMA quick_check', $badTableLeaf), 'ok'],
    'table leaf limit keeps first order error' => [static fn (): mixed => $firstError('PRAGMA integrity_check(1)', $badTableLeaf), 'btree page 3: table leaf rowid 2 is not greater than previous rowid 9'],
    'table leaf parsed pragma metadata' => [static fn (): mixed => $run('PRAGMA main.integrity_check(7)', $badTableLeaf)['pragma'], 'integrity_check'],
    'table leaf parsed limit metadata' => [static fn (): mixed => $run('PRAGMA main.integrity_check(7)', $badTableLeaf)['limit'], 7],
    'valid table interior has no errors' => [static fn (): mixed => $errorCount('PRAGMA integrity_check', $validTableInterior), 0],
    'valid table interior row ok' => [static fn (): mixed => $rowValue('PRAGMA integrity_check', $validTableInterior), 'ok'],
    'table interior out of order message' => [static fn (): mixed => $firstError('PRAGMA integrity_check', $badTableInterior), 'btree page 3: table interior divider key 10 is not greater than previous divider key 20'],
    'table interior out of order rowset' => [static fn (): mixed => $rowValue('PRAGMA integrity_check', $badTableInterior), 'btree page 3: table interior divider key 10 is not greater than previous divider key 20'],
    'table interior duplicate message' => [static fn (): mixed => $firstError('PRAGMA integrity_check', $duplicateTableInterior), 'btree page 3: table interior divider key 10 is not greater than previous divider key 10'],
    'table interior duplicate count' => [static fn (): mixed => $errorCount('PRAGMA integrity_check', $duplicateTableInterior), 1],
    'table interior quick skips order' => [static fn (): mixed => $firstError('PRAGMA quick_check', $badTableInterior), 'ok'],
    'table interior keeps column name' => [static fn (): mixed => array_key_first($run('PRAGMA integrity_check', $badTableInterior)['rows'][0]), 'integrity_check'],
    'table interior quick metadata' => [static fn (): mixed => $run('PRAGMA quick_check', $badTableInterior)['pragma'], 'quick_check'],
    'valid index leaf has no errors' => [static fn (): mixed => $errorCount('PRAGMA integrity_check', $validIndexLeaf), 0],
    'valid index leaf row ok' => [static fn (): mixed => $rowValue('PRAGMA integrity_check', $validIndexLeaf), 'ok'],
    'valid index leaf quick ok' => [static fn (): mixed => $rowValue('PRAGMA quick_check', $validIndexLeaf), 'ok'],
    'index leaf out of order message' => [static fn (): mixed => $firstError('PRAGMA integrity_check', $badIndexLeaf), 'btree page 3: index record is not greater than previous record'],
    'index leaf out of order rowset' => [static fn (): mixed => $rowValue('PRAGMA integrity_check', $badIndexLeaf), 'btree page 3: index record is not greater than previous record'],
    'index leaf duplicate message' => [static fn (): mixed => $firstError('PRAGMA integrity_check', $duplicateIndexLeaf), 'btree page 3: index record is not greater than previous record'],
    'index leaf duplicate count' => [static fn (): mixed => $errorCount('PRAGMA integrity_check', $duplicateIndexLeaf), 1],
    'index leaf quick skips order' => [static fn (): mixed => $firstError('PRAGMA quick_check', $badIndexLeaf), 'ok'],
    'index leaf limit keeps order error' => [static fn (): mixed => $firstError('PRAGMA integrity_check=1', $badIndexLeaf), 'btree page 3: index record is not greater than previous record'],
    'index leaf keeps row count one' => [static fn (): mixed => count($run('PRAGMA integrity_check', $badIndexLeaf)['rows']), 1],
    'valid index interior has no errors' => [static fn (): mixed => $errorCount('PRAGMA integrity_check', $validIndexInterior), 0],
    'valid index interior row ok' => [static fn (): mixed => $rowValue('PRAGMA integrity_check', $validIndexInterior), 'ok'],
    'index interior out of order message' => [static fn (): mixed => $firstError('PRAGMA integrity_check', $badIndexInterior), 'btree page 3: index record is not greater than previous record'],
    'index interior out of order rowset' => [static fn (): mixed => $rowValue('PRAGMA integrity_check', $badIndexInterior), 'btree page 3: index record is not greater than previous record'],
    'index interior duplicate message' => [static fn (): mixed => $firstError('PRAGMA integrity_check', $duplicateIndexInterior), 'btree page 3: index record is not greater than previous record'],
    'index interior duplicate count' => [static fn (): mixed => $errorCount('PRAGMA integrity_check', $duplicateIndexInterior), 1],
    'index interior quick skips order' => [static fn (): mixed => $firstError('PRAGMA quick_check', $badIndexInterior), 'ok'],
    'index interior parsed equals limit' => [static fn (): mixed => $run('PRAGMA integrity_check=9', $badIndexInterior)['limit'], 9],
    'index interior keeps integrity column' => [static fn (): mixed => array_key_first($run('PRAGMA integrity_check', $badIndexInterior)['rows'][0]), 'integrity_check'],
    'mixed valid roots stay ok' => [static function () use ($database, $pageSize, $errorCount): mixed {
        return $errorCount('PRAGMA integrity_check', $database([
        3 => SQLiteTableLeafPage::assemble([SQLiteTableLeafCell::encode(1, 'a'), SQLiteTableLeafCell::encode(2, 'b')], $pageSize),
        4 => SQLiteIndexLeafPage::assemble([SQLiteIndexCell::encode(SQLiteRecord::encode(['a', 1]), $pageSize), SQLiteIndexCell::encode(SQLiteRecord::encode(['b', 2]), $pageSize)], $pageSize),
    ], [
        [3, SQLitePointerMapEntry::ROOT_PAGE, 0],
        [4, SQLitePointerMapEntry::ROOT_PAGE, 0],
    ], 4));
    }, 0],
    'mixed roots report table order before later index root' => [static function () use ($database, $pageSize, $firstError): mixed {
        return $firstError('PRAGMA integrity_check', $database([
        3 => SQLiteTableLeafPage::assemble([SQLiteTableLeafCell::encode(4, 'd'), SQLiteTableLeafCell::encode(3, 'c')], $pageSize),
        4 => SQLiteIndexLeafPage::assemble([SQLiteIndexCell::encode(SQLiteRecord::encode(['a', 1]), $pageSize), SQLiteIndexCell::encode(SQLiteRecord::encode(['b', 2]), $pageSize)], $pageSize),
    ], [
        [3, SQLitePointerMapEntry::ROOT_PAGE, 0],
        [4, SQLitePointerMapEntry::ROOT_PAGE, 0],
    ], 4));
    }, 'btree page 3: table leaf rowid 3 is not greater than previous rowid 4'],
    'mixed roots report index order after valid table root' => [static function () use ($database, $pageSize, $firstError): mixed {
        return $firstError('PRAGMA integrity_check', $database([
        3 => SQLiteTableLeafPage::assemble([SQLiteTableLeafCell::encode(1, 'a'), SQLiteTableLeafCell::encode(2, 'b')], $pageSize),
        4 => SQLiteIndexLeafPage::assemble([SQLiteIndexCell::encode(SQLiteRecord::encode(['b', 2]), $pageSize), SQLiteIndexCell::encode(SQLiteRecord::encode(['a', 1]), $pageSize)], $pageSize),
    ], [
        [3, SQLitePointerMapEntry::ROOT_PAGE, 0],
        [4, SQLitePointerMapEntry::ROOT_PAGE, 0],
    ], 4));
    }, 'btree page 4: index record is not greater than previous record'],
    'header error still precedes table order when limit room' => [static fn (): mixed => $run('PRAGMA integrity_check(2)', substr_replace($badTableLeaf, "\x09", 18, 1))['errors'][0], 'invalid schema write version 9'],
    'table order follows header when limit room' => [static fn (): mixed => $run('PRAGMA integrity_check(2)', substr_replace($badTableLeaf, "\x09", 18, 1))['errors'][1], 'btree page 3: table leaf rowid 2 is not greater than previous rowid 9'],
    'limit one keeps header before order' => [static fn (): mixed => count($run('PRAGMA integrity_check(1)', substr_replace($badTableLeaf, "\x09", 18, 1))['errors']), 1],
    'schema qualified quick skips index order' => [static fn (): mixed => $firstError('PRAGMA temp.quick_check', $badIndexLeaf), 'ok'],
    'schema qualified integrity reports index order' => [static fn (): mixed => $firstError('PRAGMA temp.integrity_check', $badIndexLeaf), 'btree page 3: index record is not greater than previous record'],
    'table leaf descending pair reports second rowid' => [static fn (): mixed => $firstError('PRAGMA integrity_check', $tableLeafDatabase([99, 1])), 'btree page 3: table leaf rowid 1 is not greater than previous rowid 99'],
    'table interior descending pair reports second key' => [static fn (): mixed => $firstError('PRAGMA integrity_check', $tableInteriorDatabase([30, 11], 6)), 'btree page 3: table interior divider key 11 is not greater than previous divider key 30'],
    'index text collation binary order accepts uppercase before lowercase' => [static fn (): mixed => $errorCount('PRAGMA integrity_check', $indexLeafDatabase([['A', 1], ['a', 2]])), 0],
    'index text collation binary order rejects lowercase before uppercase' => [static fn (): mixed => $firstError('PRAGMA integrity_check', $indexLeafDatabase([['a', 2], ['A', 1]])), 'btree page 3: index record is not greater than previous record'],
    'index numeric order accepts integer sequence' => [static fn (): mixed => $errorCount('PRAGMA integrity_check', $indexLeafDatabase([[1, 'a'], [2, 'b'], [10, 'c']])), 0],
    'index numeric order rejects integer regression' => [static fn (): mixed => $firstError('PRAGMA integrity_check', $indexLeafDatabase([[1, 'a'], [10, 'c'], [2, 'b']])), 'btree page 3: index record is not greater than previous record'],
    'index null order accepts null before text' => [static fn (): mixed => $errorCount('PRAGMA integrity_check', $indexLeafDatabase([[null, 1], ['a', 2]])), 0],
    'index null order rejects text before null' => [static fn (): mixed => $firstError('PRAGMA integrity_check', $indexLeafDatabase([['a', 2], [null, 1]])), 'btree page 3: index record is not greater than previous record'],
    'order checks do not change unsupported pragma guard' => [static fn (): mixed => (static function () use ($validTableLeaf): string {
        try {
            SQLitePragmaIntegrityCheck::execute('PRAGMA table_info(wp_options)', $validTableLeaf);
        } catch (InvalidArgumentException $exception) {
            return $exception->getMessage();
        }
        return 'no error';
    })(), 'Unsupported SQLite integrity PRAGMA SQL'],
];

foreach ($cases as $name => [$callback, $expected]) {
    $tests['pragma integrity btree order current next68 ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        $t->same($expected, $callback());
    };
}

return $tests;

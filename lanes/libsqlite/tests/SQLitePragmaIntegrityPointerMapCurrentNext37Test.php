<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteFreelistTrunkPage;
use PortLibs\LibSqlite\SQLitePointerMapEntry;
use PortLibs\LibSqlite\SQLitePragmaIntegrityCheck;
use PortLibs\LibSqlite\SQLiteTableLeafCell;
use PortLibs\LibSqlite\SQLiteTableLeafPage;

$pageSize = 512;

$headerPage = static function (int $pageCount, int $firstFreelist, int $freelistCount, int $largestRoot) use ($pageSize): string {
    $page = str_repeat("\0", $pageSize);
    $page = substr_replace($page, "SQLite format 3\0", 0, 16);
    $page = substr_replace($page, pack('n', $pageSize), 16, 2);
    $page[18] = "\x01";
    $page[19] = "\x01";
    $page = substr_replace($page, pack('N', $pageCount), 28, 4);
    $page = substr_replace($page, pack('N', $firstFreelist), 32, 4);
    $page = substr_replace($page, pack('N', $freelistCount), 36, 4);
    $page = substr_replace($page, pack('N', $largestRoot), 52, 4);
    $page = substr_replace($page, pack('N', 1), 56, 4);

    return $page;
};

$putPointerMapEntry = static function (string $page, int $pageNumber, int $type, int $parent): string {
    return substr_replace($page, chr($type) . pack('N', $parent), 5 * ($pageNumber - 3), 5);
};

$makeDatabase = static function (?callable $mutatePointerMap = null, ?callable $mutatePages = null) use ($headerPage, $putPointerMapEntry, $pageSize): string {
    $pointerMap = str_repeat("\0", $pageSize);
    $pointerMap = $putPointerMapEntry($pointerMap, 3, SQLitePointerMapEntry::ROOT_PAGE, 0);
    $pointerMap = $putPointerMapEntry($pointerMap, 4, SQLitePointerMapEntry::BTREE_PAGE, 3);
    $pointerMap = $putPointerMapEntry($pointerMap, 5, SQLitePointerMapEntry::FREE_PAGE, 0);
    $pointerMap = $putPointerMapEntry($pointerMap, 6, SQLitePointerMapEntry::FREE_PAGE, 0);
    if ($mutatePointerMap !== null) {
        $pointerMap = $mutatePointerMap($pointerMap, $putPointerMapEntry);
    }

    $pages = [
        1 => $headerPage(6, 5, 2, 3),
        2 => $pointerMap,
        3 => SQLiteTableLeafPage::assemble([
            SQLiteTableLeafCell::encode(1, 'siteurl'),
            SQLiteTableLeafCell::encode(2, 'home'),
        ], $pageSize),
        4 => SQLiteTableLeafPage::assemble([
            SQLiteTableLeafCell::encode(3, 'blogname'),
        ], $pageSize),
        5 => SQLiteFreelistTrunkPage::assemble(null, [6], $pageSize),
        6 => str_repeat("\0", $pageSize),
    ];

    if ($mutatePages !== null) {
        $pages = $mutatePages($pages);
    }

    return implode('', $pages);
};

$errors = static function (string $database, string $sql = 'PRAGMA integrity_check'): array {
    return SQLitePragmaIntegrityCheck::execute($sql, $database)['errors'];
};

$cases = [
    'accepts current auto-vacuum pointer-map parents' => static fn () => SQLitePragmaIntegrityCheck::execute('PRAGMA integrity_check', $makeDatabase())['rows'],
    'quick check stays shallow for invalid pointer-map type' => static fn () => SQLitePragmaIntegrityCheck::execute('PRAGMA quick_check', $makeDatabase(static fn (string $pm, callable $put): string => $put($pm, 4, 9, 3)))['rows'],
    'reports unknown pointer-map type before btree walk' => static fn () => $errors($makeDatabase(static fn (string $pm, callable $put): string => $put($pm, 4, 9, 3)))[0],
    'reports root-page parent mismatch' => static fn () => $errors($makeDatabase(static fn (string $pm, callable $put): string => $put($pm, 3, SQLitePointerMapEntry::ROOT_PAGE, 4)))[0],
    'reports free-page parent mismatch outside freelist' => static fn () => $errors($makeDatabase(static fn (string $pm, callable $put): string => $put($pm, 4, SQLitePointerMapEntry::FREE_PAGE, 3)))[0],
    'reports btree-page zero parent mismatch' => static fn () => $errors($makeDatabase(static fn (string $pm, callable $put): string => $put($pm, 4, SQLitePointerMapEntry::BTREE_PAGE, 0)))[0],
    'reports first-overflow-page zero parent mismatch' => static fn () => $errors($makeDatabase(static fn (string $pm, callable $put): string => $put($pm, 4, SQLitePointerMapEntry::FIRST_OVERFLOW_PAGE, 0)))[0],
    'reports overflow-page zero parent mismatch' => static fn () => $errors($makeDatabase(static fn (string $pm, callable $put): string => $put($pm, 4, SQLitePointerMapEntry::OVERFLOW_PAGE, 0)))[0],
    'limit returns the first malformed pointer-map parent only' => static fn () => $errors($makeDatabase(static function (string $pm, callable $put): string {
        $pm = $put($pm, 3, SQLitePointerMapEntry::ROOT_PAGE, 4);
        return $put($pm, 4, SQLitePointerMapEntry::BTREE_PAGE, 0);
    }), 'PRAGMA integrity_check(1)'),
    'schema-qualified integrity reports malformed free-page parent' => static fn () => $errors($makeDatabase(static fn (string $pm, callable $put): string => $put($pm, 4, SQLitePointerMapEntry::FREE_PAGE, 3)), 'PRAGMA main.integrity_check')[0],
];

$expected = [
    'accepts current auto-vacuum pointer-map parents' => [['integrity_check' => 'ok']],
    'quick check stays shallow for invalid pointer-map type' => [['quick_check' => 'ok']],
    'reports unknown pointer-map type before btree walk' => 'Invalid SQLite pointer-map entry type: 9',
    'reports root-page parent mismatch' => 'pointer-map parent page 4 for root-page page 3 does not match expected parent 0',
    'reports free-page parent mismatch outside freelist' => 'pointer-map parent page 3 for free-page page 4 does not match expected parent 0',
    'reports btree-page zero parent mismatch' => 'pointer-map parent page 0 for btree-page page 4 is not valid',
    'reports first-overflow-page zero parent mismatch' => 'pointer-map parent page 0 for first-overflow-page page 4 is not valid',
    'reports overflow-page zero parent mismatch' => 'pointer-map parent page 0 for overflow-page page 4 is not valid',
    'limit returns the first malformed pointer-map parent only' => ['pointer-map parent page 4 for root-page page 3 does not match expected parent 0'],
    'schema-qualified integrity reports malformed free-page parent' => 'pointer-map parent page 3 for free-page page 4 does not match expected parent 0',
];

$tests = [];
foreach ($cases as $name => $callback) {
    $tests['pragma integrity pointer-map current next37 ' . $name] = static function (TestRunner $t) use ($callback, $expected, $name): void {
        $t->same($expected[$name], $callback());
    };
}

$invalidTypes = [0, 6, 7, 8, 9, 15, 63, 127, 128, 200, 255];
foreach ($invalidTypes as $type) {
    $tests['pragma integrity pointer-map current next37 invalid type ' . $type] = static function (TestRunner $t) use ($makeDatabase, $errors, $type): void {
        $t->same("Invalid SQLite pointer-map entry type: {$type}", $errors($makeDatabase(static fn (string $pm, callable $put): string => $put($pm, 4, $type, 3)))[0]);
    };
}

foreach ([1, 2, 3, 4, 5, 6] as $parent) {
    $tests['pragma integrity pointer-map current next37 root parent mismatch ' . $parent] = static function (TestRunner $t) use ($makeDatabase, $errors, $parent): void {
        $t->same("pointer-map parent page {$parent} for root-page page 3 does not match expected parent 0", $errors($makeDatabase(static fn (string $pm, callable $put): string => $put($pm, 3, SQLitePointerMapEntry::ROOT_PAGE, $parent)))[0]);
    };
}

foreach ([1, 2, 3, 4, 5, 6] as $parent) {
    $tests['pragma integrity pointer-map current next37 free parent mismatch ' . $parent] = static function (TestRunner $t) use ($makeDatabase, $errors, $parent): void {
        $t->same("pointer-map parent page {$parent} for free-page page 4 does not match expected parent 0", $errors($makeDatabase(static fn (string $pm, callable $put): string => $put($pm, 4, SQLitePointerMapEntry::FREE_PAGE, $parent)))[0]);
    };
}

foreach ([SQLitePointerMapEntry::BTREE_PAGE => 'btree-page', SQLitePointerMapEntry::FIRST_OVERFLOW_PAGE => 'first-overflow-page', SQLitePointerMapEntry::OVERFLOW_PAGE => 'overflow-page'] as $type => $typeName) {
    foreach ([0, 7, 8, 99] as $parent) {
        $tests['pragma integrity pointer-map current next37 non-root parent guard ' . $typeName . ' ' . $parent] = static function (TestRunner $t) use ($makeDatabase, $errors, $type, $typeName, $parent): void {
            $message = $parent === 0
                ? "pointer-map parent page 0 for {$typeName} page 4 is not valid"
                : "pointer-map parent page {$parent} for page 4 is beyond the database image";
            $t->same($message, $errors($makeDatabase(static fn (string $pm, callable $put): string => $put($pm, 4, $type, $parent)))[0]);
        };
    }
}

return $tests;

<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteFreelistTrunkPage;
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
    $offset = 5 * ($pageNumber - 3);

    return substr_replace($page, chr($type) . pack('N', $parent), $offset, 5);
};

$leafPages = static function (string $kind) use ($pageSize): array {
    if ($kind === 'index') {
        return [
            4 => SQLiteIndexLeafPage::assemble([SQLiteIndexCell::encode(SQLiteRecord::encode(['autoload', 'no', 4]))], $pageSize),
            5 => SQLiteIndexLeafPage::assemble([SQLiteIndexCell::encode(SQLiteRecord::encode(['autoload', 'yes', 5]))], $pageSize),
            6 => SQLiteIndexLeafPage::assemble([SQLiteIndexCell::encode(SQLiteRecord::encode(['transient', 'yes', 6]))], $pageSize),
        ];
    }

    return [
        4 => SQLiteTableLeafPage::assemble([SQLiteTableLeafCell::encode(4, 'siteurl')], $pageSize),
        5 => SQLiteTableLeafPage::assemble([SQLiteTableLeafCell::encode(5, 'home')], $pageSize),
        6 => SQLiteTableLeafPage::assemble([SQLiteTableLeafCell::encode(6, 'blogname')], $pageSize),
    ];
};

$interiorPage = static function (string $kind) use ($pageSize): string {
    if ($kind === 'index') {
        return SQLiteIndexInteriorPage::assemble([
            SQLiteIndexCell::encode(SQLiteRecord::encode(['autoload', 'no', 4]), leftChildPage: 4),
            SQLiteIndexCell::encode(SQLiteRecord::encode(['autoload', 'yes', 5]), leftChildPage: 5),
        ], 6, $pageSize);
    }

    return SQLiteTableInteriorPage::assemble([
        SQLiteTableInteriorCell::encode(4, 4),
        SQLiteTableInteriorCell::encode(5, 5),
    ], 6, $pageSize);
};

$makeDatabase = static function (
    string $kind,
    ?callable $mutatePointerMap = null,
    ?callable $mutatePages = null,
) use ($headerPage, $putPointerMapEntry, $leafPages, $interiorPage, $pageSize): string {
    $pageCount = 8;
    $pointerMap = str_repeat("\0", $pageSize);
    foreach ([3 => SQLitePointerMapEntry::ROOT_PAGE, 4 => SQLitePointerMapEntry::BTREE_PAGE, 5 => SQLitePointerMapEntry::BTREE_PAGE, 6 => SQLitePointerMapEntry::BTREE_PAGE] as $pageNumber => $type) {
        $pointerMap = $putPointerMapEntry($pointerMap, $pageNumber, $type, $pageNumber === 3 ? 0 : 3);
    }
    $pointerMap = $putPointerMapEntry($pointerMap, 7, SQLitePointerMapEntry::FREE_PAGE, 0);
    $pointerMap = $putPointerMapEntry($pointerMap, 8, SQLitePointerMapEntry::FREE_PAGE, 0);

    if ($mutatePointerMap !== null) {
        $pointerMap = $mutatePointerMap($pointerMap, $putPointerMapEntry);
    }

    $pages = [
        1 => $headerPage($pageCount, 7, 2, 3),
        2 => $pointerMap,
        3 => $interiorPage($kind),
        7 => SQLiteFreelistTrunkPage::assemble(null, [8], $pageSize),
        8 => str_repeat("\0", $pageSize),
    ] + $leafPages($kind);
    ksort($pages);

    if ($mutatePages !== null) {
        $pages = $mutatePages($pages);
    }

    return implode('', $pages);
};

$firstError = static function (string $database, string $sql = 'PRAGMA integrity_check'): string {
    $result = SQLitePragmaIntegrityCheck::execute($sql, $database);

    return $result['errors'][0] ?? 'ok';
};

foreach (['table', 'index'] as $kind) {
    $tests["pragma integrity index freelist current next38 accepts {$kind} interior child pointer-map parents"] = static function (TestRunner $t) use ($makeDatabase, $kind): void {
        $result = SQLitePragmaIntegrityCheck::execute('PRAGMA integrity_check', $makeDatabase($kind));

        $t->same([['integrity_check' => 'ok']], $result['rows']);
    };

    foreach ([4 => 'left child', 5 => 'left child', 6 => 'right-most child'] as $childPage => $role) {
        $tests["pragma integrity index freelist current next38 reports {$kind} {$role} {$childPage} parent mismatch"] = static function (TestRunner $t) use ($makeDatabase, $firstError, $kind, $childPage, $role): void {
            $database = $makeDatabase($kind, static function (string $pointerMap, callable $put) use ($childPage): string {
                return $put($pointerMap, $childPage, SQLitePointerMapEntry::BTREE_PAGE, 7);
            });

            $t->same("btree page 3 {$role} page {$childPage} pointer-map parent 7 does not match expected parent 3", $firstError($database));
        };

        $tests["pragma integrity index freelist current next38 reports {$kind} {$role} {$childPage} type mismatch"] = static function (TestRunner $t) use ($makeDatabase, $firstError, $kind, $childPage, $role): void {
            $database = $makeDatabase($kind, static function (string $pointerMap, callable $put) use ($childPage): string {
                return $put($pointerMap, $childPage, SQLitePointerMapEntry::FREE_PAGE, 0);
            });

            $t->same("btree page 3 {$role} page {$childPage} pointer-map type free-page does not match expected btree-page", $firstError($database));
        };
    }

    $tests["pragma integrity index freelist current next38 {$kind} child errors precede freelist pointer-map leaf errors"] = static function (TestRunner $t) use ($makeDatabase, $kind): void {
        $database = $makeDatabase($kind, static function (string $pointerMap, callable $put): string {
            $pointerMap = $put($pointerMap, 4, SQLitePointerMapEntry::BTREE_PAGE, 7);
            return $put($pointerMap, 8, SQLitePointerMapEntry::BTREE_PAGE, 3);
        });
        $result = SQLitePragmaIntegrityCheck::execute('PRAGMA integrity_check(2)', $database);

        $t->same([
            'freelist page 8 pointer-map type btree-page does not match expected free-page',
            'btree page 3 left child page 4 pointer-map parent 7 does not match expected parent 3',
        ], $result['errors']);
    };

    $tests["pragma integrity index freelist current next38 {$kind} quick check skips interior child pointer-map scan"] = static function (TestRunner $t) use ($makeDatabase, $kind): void {
        $database = $makeDatabase($kind, static function (string $pointerMap, callable $put): string {
            return $put($pointerMap, 4, SQLitePointerMapEntry::BTREE_PAGE, 7);
        });

        $t->same('ok', SQLitePragmaIntegrityCheck::execute('PRAGMA quick_check', $database)['rows'][0]['quick_check']);
    };

    $tests["pragma integrity index freelist current next38 {$kind} limited to first child mismatch"] = static function (TestRunner $t) use ($makeDatabase, $kind): void {
        $database = $makeDatabase($kind, static function (string $pointerMap, callable $put): string {
            $pointerMap = $put($pointerMap, 4, SQLitePointerMapEntry::BTREE_PAGE, 7);
            return $put($pointerMap, 5, SQLitePointerMapEntry::BTREE_PAGE, 8);
        });

        $t->same(['btree page 3 left child page 4 pointer-map parent 7 does not match expected parent 3'], SQLitePragmaIntegrityCheck::execute('PRAGMA integrity_check(1)', $database)['errors']);
    };
}

foreach ([
    'root-page' => [SQLitePointerMapEntry::ROOT_PAGE, 0],
    'free-page' => [SQLitePointerMapEntry::FREE_PAGE, 0],
    'first-overflow-page' => [SQLitePointerMapEntry::FIRST_OVERFLOW_PAGE, 3],
    'overflow-page' => [SQLitePointerMapEntry::OVERFLOW_PAGE, 4],
] as $typeName => [$type, $parent]) {
    foreach (['table', 'index'] as $kind) {
        $tests["pragma integrity index freelist current next38 {$kind} left child rejects {$typeName}"] = static function (TestRunner $t) use ($makeDatabase, $firstError, $kind, $type, $parent, $typeName): void {
            $database = $makeDatabase($kind, static function (string $pointerMap, callable $put) use ($type, $parent): string {
                return $put($pointerMap, 4, $type, $parent);
            });

            $t->same("btree page 3 left child page 4 pointer-map type {$typeName} does not match expected btree-page", $firstError($database));
        };
    }
}

for ($parent = 0; $parent <= 8; $parent++) {
    if ($parent === 3) {
        continue;
    }
    foreach (['table', 'index'] as $kind) {
        $tests["pragma integrity index freelist current next38 {$kind} right-most child parent {$parent}"] = static function (TestRunner $t) use ($makeDatabase, $firstError, $kind, $parent): void {
            $database = $makeDatabase($kind, static function (string $pointerMap, callable $put) use ($parent): string {
                return $put($pointerMap, 6, SQLitePointerMapEntry::BTREE_PAGE, $parent);
            });
            $expected = $parent === 0
                ? 'pointer-map parent page 0 for btree-page page 6 is not valid'
                : "btree page 3 right-most child page 6 pointer-map parent {$parent} does not match expected parent 3";

            $t->same($expected, $firstError($database));
        };
    }
}

foreach (['table', 'index'] as $kind) {
    $tests["pragma integrity index freelist current next38 {$kind} reports child beyond database image"] = static function (TestRunner $t) use ($makeDatabase, $firstError, $kind): void {
        $database = $makeDatabase($kind, null, static function (array $pages) use ($kind): array {
            $pages[3] = $kind === 'index'
                ? SQLiteIndexInteriorPage::assemble([SQLiteIndexCell::encode(SQLiteRecord::encode(['missing', 9]), leftChildPage: 9)], 6)
                : SQLiteTableInteriorPage::assemble([SQLiteTableInteriorCell::encode(9, 9)], 6);

            return $pages;
        });

        $t->same('btree page 3 left child page 9 is beyond the database image', $firstError($database));
    };

    $tests["pragma integrity index freelist current next38 {$kind} preserves ok after freelist trunk parsing"] = static function (TestRunner $t) use ($makeDatabase, $kind): void {
        $result = SQLitePragmaIntegrityCheck::execute('PRAGMA main.integrity_check = 5', $makeDatabase($kind));

        $t->same([], $result['errors']);
        $t->same(5, $result['limit']);
    };
}

return $tests;

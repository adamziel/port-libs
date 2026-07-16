<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteFreelistTrunkPage;
use PortLibs\LibSqlite\SQLitePointerMapEntry;
use PortLibs\LibSqlite\SQLitePragmaIntegrityCheck;
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

$makeDatabase = static function (
    array $freeLeafPages = [4],
    ?callable $mutatePointerMap = null,
    ?callable $mutatePages = null,
) use ($headerPage, $putPointerMapEntry, $pageSize): string {
    $pageCount = 6;
    $pointerMap = str_repeat("\0", $pageSize);
    $pointerMap = $putPointerMapEntry($pointerMap, 3, SQLitePointerMapEntry::FREE_PAGE, 0);
    $pointerMap = $putPointerMapEntry($pointerMap, 4, SQLitePointerMapEntry::FREE_PAGE, 0);
    $pointerMap = $putPointerMapEntry($pointerMap, 5, SQLitePointerMapEntry::ROOT_PAGE, 0);
    $pointerMap = $putPointerMapEntry($pointerMap, 6, SQLitePointerMapEntry::BTREE_PAGE, 5);
    if ($mutatePointerMap !== null) {
        $pointerMap = $mutatePointerMap($pointerMap, $putPointerMapEntry);
    }

    $pages = [
        1 => $headerPage($pageCount, 3, 1 + count($freeLeafPages), 5),
        2 => $pointerMap,
        3 => SQLiteFreelistTrunkPage::assemble(null, $freeLeafPages, $pageSize),
        4 => str_repeat("\0", $pageSize),
        5 => SQLiteTableLeafPage::assemble([
            SQLiteTableLeafCell::encode(1, 'siteurl'),
            SQLiteTableLeafCell::encode(2, 'home'),
        ], $pageSize),
        6 => SQLiteTableLeafPage::assemble([
            SQLiteTableLeafCell::encode(3, 'blogname'),
        ], $pageSize),
    ];

    if ($mutatePages !== null) {
        $pages = $mutatePages($pages);
    }

    return implode('', $pages);
};

$firstError = static function (string $database, string $sql = 'PRAGMA integrity_check'): string {
    $result = SQLitePragmaIntegrityCheck::execute($sql, $database);

    return $result['errors'][0] ?? 'ok';
};

$tests['pragma integrity current next29 accepts pointer-map free pages reachable from freelist'] = static function (TestRunner $t) use ($makeDatabase): void {
    $t->same(['ok'], array_map(static fn (array $row): string => $row['integrity_check'], SQLitePragmaIntegrityCheck::execute('PRAGMA integrity_check', $makeDatabase())['rows']));
};

$tests['pragma integrity current next29 quick check preserves shallow freelist behavior'] = static function (TestRunner $t) use ($makeDatabase): void {
    $t->same('ok', SQLitePragmaIntegrityCheck::execute('PRAGMA quick_check', $makeDatabase([], static function (string $pointerMap, callable $put): string {
        return $put($pointerMap, 4, SQLitePointerMapEntry::FREE_PAGE, 0);
    }))['rows'][0]['quick_check']);
};

$tests['pragma integrity current next29 reports free pointer-map page missing from freelist'] = static function (TestRunner $t) use ($makeDatabase, $firstError): void {
    $database = $makeDatabase([], static function (string $pointerMap, callable $put): string {
        return $put($pointerMap, 4, SQLitePointerMapEntry::FREE_PAGE, 0);
    });

    $t->same('pointer-map marks page 4 free but the page is not reachable from the freelist', $firstError($database));
};

$tests['pragma integrity current next29 reports freelist trunk pointer-map type mismatch'] = static function (TestRunner $t) use ($makeDatabase, $firstError): void {
    $database = $makeDatabase([4], static function (string $pointerMap, callable $put): string {
        return $put($pointerMap, 3, SQLitePointerMapEntry::BTREE_PAGE, 5);
    });

    $t->same('freelist page 3 pointer-map type btree-page does not match expected free-page', $firstError($database));
};

$tests['pragma integrity current next29 reports freelist leaf pointer-map type mismatch'] = static function (TestRunner $t) use ($makeDatabase, $firstError): void {
    $database = $makeDatabase([4], static function (string $pointerMap, callable $put): string {
        return $put($pointerMap, 4, SQLitePointerMapEntry::ROOT_PAGE, 0);
    });

    $t->same('freelist page 4 pointer-map type root-page does not match expected free-page', $firstError($database));
};

$tests['pragma integrity current next29 reports freelist parent mismatch'] = static function (TestRunner $t) use ($makeDatabase, $firstError): void {
    $database = $makeDatabase([4], static function (string $pointerMap, callable $put): string {
        return $put($pointerMap, 4, SQLitePointerMapEntry::FREE_PAGE, 5);
    });

    $t->same('freelist page 4 pointer-map parent 5 does not match expected parent 0', $firstError($database));
};

$tests['pragma integrity current next29 limit keeps first free-page mismatch'] = static function (TestRunner $t) use ($makeDatabase): void {
    $database = $makeDatabase([], static function (string $pointerMap, callable $put): string {
        $pointerMap = $put($pointerMap, 4, SQLitePointerMapEntry::FREE_PAGE, 0);
        return $put($pointerMap, 6, SQLitePointerMapEntry::FREE_PAGE, 0);
    });
    $result = SQLitePragmaIntegrityCheck::execute('PRAGMA integrity_check(1)', $database);

    $t->same(['pointer-map marks page 4 free but the page is not reachable from the freelist'], $result['errors']);
};

$typeMismatchCases = [
    'trunk root-page' => [3, SQLitePointerMapEntry::ROOT_PAGE, 0, 'root-page'],
    'trunk first-overflow-page' => [3, SQLitePointerMapEntry::FIRST_OVERFLOW_PAGE, 5, 'first-overflow-page'],
    'trunk overflow-page' => [3, SQLitePointerMapEntry::OVERFLOW_PAGE, 4, 'overflow-page'],
    'trunk btree-page' => [3, SQLitePointerMapEntry::BTREE_PAGE, 5, 'btree-page'],
    'leaf root-page' => [4, SQLitePointerMapEntry::ROOT_PAGE, 0, 'root-page'],
    'leaf first-overflow-page' => [4, SQLitePointerMapEntry::FIRST_OVERFLOW_PAGE, 5, 'first-overflow-page'],
    'leaf overflow-page' => [4, SQLitePointerMapEntry::OVERFLOW_PAGE, 3, 'overflow-page'],
    'leaf btree-page' => [4, SQLitePointerMapEntry::BTREE_PAGE, 5, 'btree-page'],
];

foreach ($typeMismatchCases as $name => [$pageNumber, $type, $parent, $typeName]) {
    $tests['pragma integrity current next29 freelist pointer-map type mismatch ' . $name] = static function (TestRunner $t) use ($makeDatabase, $firstError, $pageNumber, $type, $parent, $typeName): void {
        $database = $makeDatabase([4], static function (string $pointerMap, callable $put) use ($pageNumber, $type, $parent): string {
            return $put($pointerMap, $pageNumber, $type, $parent);
        });

        $t->same("freelist page {$pageNumber} pointer-map type {$typeName} does not match expected free-page", $firstError($database));
    };
}

for ($parent = 1; $parent <= 14; $parent++) {
    $tests['pragma integrity current next29 freelist leaf parent mismatch ' . $parent] = static function (TestRunner $t) use ($makeDatabase, $firstError, $parent): void {
        $database = $makeDatabase([4], static function (string $pointerMap, callable $put) use ($parent): string {
            return $put($pointerMap, 4, SQLitePointerMapEntry::FREE_PAGE, $parent);
        });

        $t->same("freelist page 4 pointer-map parent {$parent} does not match expected parent 0", $firstError($database));
    };
}

for ($parent = 1; $parent <= 14; $parent++) {
    $tests['pragma integrity current next29 freelist trunk parent mismatch ' . $parent] = static function (TestRunner $t) use ($makeDatabase, $firstError, $parent): void {
        $database = $makeDatabase([4], static function (string $pointerMap, callable $put) use ($parent): string {
            return $put($pointerMap, 3, SQLitePointerMapEntry::FREE_PAGE, $parent);
        });

        $t->same("freelist page 3 pointer-map parent {$parent} does not match expected parent 0", $firstError($database));
    };
}

for ($pageNumber = 4; $pageNumber <= 6; $pageNumber++) {
    $tests['pragma integrity current next29 pointer-map free page not on freelist ' . $pageNumber] = static function (TestRunner $t) use ($makeDatabase, $firstError, $pageNumber): void {
        $database = $makeDatabase($pageNumber === 4 ? [] : [4], static function (string $pointerMap, callable $put) use ($pageNumber): string {
            return $put($pointerMap, $pageNumber, SQLitePointerMapEntry::FREE_PAGE, 0);
        }, static function (array $pages) use ($pageNumber): array {
            $pages[$pageNumber] = str_repeat("\0", 512);
            return $pages;
        });

        $t->same("pointer-map marks page {$pageNumber} free but the page is not reachable from the freelist", $firstError($database));
    };
}

$tests['pragma integrity current next29 multiple mismatches preserve scan order'] = static function (TestRunner $t) use ($makeDatabase): void {
    $database = $makeDatabase([], static function (string $pointerMap, callable $put): string {
        $pointerMap = $put($pointerMap, 4, SQLitePointerMapEntry::FREE_PAGE, 0);
        return $put($pointerMap, 6, SQLitePointerMapEntry::FREE_PAGE, 0);
    }, static function (array $pages): array {
        $pages[6] = str_repeat("\0", 512);
        return $pages;
    });
    $result = SQLitePragmaIntegrityCheck::execute('PRAGMA integrity_check(2)', $database);

    $t->same([
        'pointer-map marks page 4 free but the page is not reachable from the freelist',
        'pointer-map marks page 6 free but the page is not reachable from the freelist',
    ], $result['errors']);
};

$tests['pragma integrity current next29 accepts parenthesized high limit'] = static function (TestRunner $t) use ($makeDatabase): void {
    $result = SQLitePragmaIntegrityCheck::execute('PRAGMA integrity_check(25)', $makeDatabase());

    $t->same([['integrity_check' => 'ok']], $result['rows']);
};

$tests['pragma integrity current next29 accepts trailing semicolon'] = static function (TestRunner $t) use ($makeDatabase): void {
    $result = SQLitePragmaIntegrityCheck::execute('PRAGMA integrity_check;', $makeDatabase());

    $t->same([], $result['errors']);
};

$tests['pragma integrity current next29 rowset uses integrity column for pointer-map mismatch'] = static function (TestRunner $t) use ($makeDatabase): void {
    $database = $makeDatabase([4], static function (string $pointerMap, callable $put): string {
        return $put($pointerMap, 4, SQLitePointerMapEntry::BTREE_PAGE, 5);
    });
    $result = SQLitePragmaIntegrityCheck::execute('PRAGMA integrity_check', $database);

    $t->same($result['errors'][0], $result['rows'][0]['integrity_check']);
};

$tests['pragma integrity current next29 schema qualified pragma reports pointer-map mismatch'] = static function (TestRunner $t) use ($makeDatabase, $firstError): void {
    $database = $makeDatabase([], static function (string $pointerMap, callable $put): string {
        return $put($pointerMap, 4, SQLitePointerMapEntry::FREE_PAGE, 0);
    });

    $t->same('pointer-map marks page 4 free but the page is not reachable from the freelist', $firstError($database, 'PRAGMA main.integrity_check'));
};

$tests['pragma integrity current next29 equals limit truncates pointer-map errors'] = static function (TestRunner $t) use ($makeDatabase): void {
    $database = $makeDatabase([], static function (string $pointerMap, callable $put): string {
        $pointerMap = $put($pointerMap, 4, SQLitePointerMapEntry::FREE_PAGE, 0);
        return $put($pointerMap, 6, SQLitePointerMapEntry::FREE_PAGE, 0);
    });
    $result = SQLitePragmaIntegrityCheck::execute('PRAGMA integrity_check = 1', $database);

    $t->same(['pointer-map marks page 4 free but the page is not reachable from the freelist'], $result['errors']);
};

$tests['pragma integrity current next29 quick check still reports freelist count before pointer-map skip'] = static function (TestRunner $t) use ($makeDatabase): void {
    $database = $makeDatabase([4], null, static function (array $pages): array {
        $pages[1] = substr_replace($pages[1], pack('N', 9), 36, 4);
        return $pages;
    });

    $t->same('freelist header count 9 does not match reachable freelist page count 2', SQLitePragmaIntegrityCheck::execute('PRAGMA quick_check', $database)['errors'][0]);
};

return $tests;

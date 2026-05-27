<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteIndexCell;
use PortLibs\LibSqlite\SQLiteIndexLeafPage;
use PortLibs\LibSqlite\SQLiteOverflowPage;
use PortLibs\LibSqlite\SQLitePointerMapEntry;
use PortLibs\LibSqlite\SQLitePragmaIntegrityCheck;
use PortLibs\LibSqlite\SQLiteTableInteriorCell;
use PortLibs\LibSqlite\SQLiteTableInteriorPage;
use PortLibs\LibSqlite\SQLiteTableLeafCell;
use PortLibs\LibSqlite\SQLiteTableLeafPage;

$tests = [];

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

$autoVacuumWithTableLeaf = static function (?callable $mutate = null) use ($headerPage, $putPointerMapEntry, $pageSize): string {
    $leaf = SQLiteTableLeafPage::assemble([
        SQLiteTableLeafCell::encode(1, 'siteurl'),
        SQLiteTableLeafCell::encode(2, 'home'),
    ], $pageSize);
    $pointerMap = $putPointerMapEntry(str_repeat("\0", $pageSize), 3, SQLitePointerMapEntry::ROOT_PAGE, 0);
    $pages = [
        1 => $headerPage(3, 3),
        2 => $pointerMap,
        3 => $leaf,
    ];

    if ($mutate !== null) {
        $pages = $mutate($pages);
    }

    return implode('', $pages);
};

$autoVacuumWithOverflow = static function (?callable $mutate = null) use ($headerPage, $putPointerMapEntry, $pageSize): string {
    $payload = str_repeat('x', 800);
    $encoded = SQLiteTableLeafCell::encodeWithOverflowPages(7, $payload, 4, $pageSize);
    $leaf = SQLiteTableLeafPage::assemble([$encoded['cell']], $pageSize);
    $pointerMap = str_repeat("\0", $pageSize);
    $pointerMap = $putPointerMapEntry($pointerMap, 3, SQLitePointerMapEntry::ROOT_PAGE, 0);
    $pointerMap = $putPointerMapEntry($pointerMap, 4, SQLitePointerMapEntry::FIRST_OVERFLOW_PAGE, 3);
    $pages = [
        1 => $headerPage(4, 3),
        2 => $pointerMap,
        3 => $leaf,
        4 => $encoded['overflowPages'][0],
    ];

    if ($mutate !== null) {
        $pages = $mutate($pages);
    }

    return implode('', $pages);
};

$tableInterior = static function (?callable $mutate = null) use ($headerPage, $putPointerMapEntry, $pageSize): string {
    $root = SQLiteTableInteriorPage::assemble([SQLiteTableInteriorCell::encode(4, 5)], 5, $pageSize);
    $pointerMap = str_repeat("\0", $pageSize);
    $pointerMap = $putPointerMapEntry($pointerMap, 3, SQLitePointerMapEntry::ROOT_PAGE, 0);
    $pointerMap = $putPointerMapEntry($pointerMap, 4, SQLitePointerMapEntry::BTREE_PAGE, 3);
    $pointerMap = $putPointerMapEntry($pointerMap, 5, SQLitePointerMapEntry::BTREE_PAGE, 3);
    $pages = [
        1 => $headerPage(5, 3),
        2 => $pointerMap,
        3 => $root,
        4 => SQLiteTableLeafPage::assemble([SQLiteTableLeafCell::encode(1, 'a')], $pageSize),
        5 => SQLiteTableLeafPage::assemble([SQLiteTableLeafCell::encode(9, 'z')], $pageSize),
    ];

    if ($mutate !== null) {
        $pages = $mutate($pages);
    }

    return implode('', $pages);
};

$indexLeaf = static function (?callable $mutate = null) use ($headerPage, $putPointerMapEntry, $pageSize): string {
    $leaf = SQLiteIndexLeafPage::assemble([
        SQLiteIndexCell::encode("\x03\x0fidx"),
    ], $pageSize);
    $pointerMap = $putPointerMapEntry(str_repeat("\0", $pageSize), 3, SQLitePointerMapEntry::ROOT_PAGE, 0);
    $pages = [
        1 => $headerPage(3, 3),
        2 => $pointerMap,
        3 => $leaf,
    ];

    if ($mutate !== null) {
        $pages = $mutate($pages);
    }

    return implode('', $pages);
};

$firstError = static function (string $sql, string $database): string {
    $result = SQLitePragmaIntegrityCheck::execute($sql, $database);

    return $result['errors'][0] ?? 'ok';
};

$okCases = [
    'deep table leaf btree ok' => $autoVacuumWithTableLeaf(),
    'deep table interior btree ok' => $tableInterior(),
    'deep table overflow chain ok' => $autoVacuumWithOverflow(),
    'deep index leaf btree ok' => $indexLeaf(),
];

foreach ($okCases as $name => $database) {
    $tests['pragma integrity deep current next19 ' . $name] = static function (TestRunner $t) use ($database): void {
        $t->same(['ok'], array_map(static fn (array $row): string => $row['integrity_check'], SQLitePragmaIntegrityCheck::execute('PRAGMA integrity_check', $database)['rows']));
    };
}

$cases = [
    'quick check skips malformed btree page' => ['PRAGMA quick_check', $autoVacuumWithTableLeaf(static function (array $pages): array {
        $pages[3] = substr_replace($pages[3], "\xff", 0, 1);
        return $pages;
    }), 'ok'],
    'deep catches malformed btree page type' => ['PRAGMA integrity_check', $autoVacuumWithTableLeaf(static function (array $pages): array {
        $pages[3] = substr_replace($pages[3], "\xff", 0, 1);
        return $pages;
    }), 'btree page 3: Invalid SQLite b-tree page type flag: 0xff'],
    'deep catches freeblock before content' => ['PRAGMA integrity_check', $autoVacuumWithTableLeaf(static function (array $pages): array {
        $pages[3] = substr_replace($pages[3], pack('n', 8), 1, 2);
        return $pages;
    }), 'btree page 3: SQLite b-tree first freeblock offset is outside the cell content area'],
    'deep catches freeblock too small' => ['PRAGMA integrity_check', $autoVacuumWithTableLeaf(static function (array $pages): array {
        $pages[3] = substr_replace($pages[3], pack('n', 500), 1, 2);
        $pages[3] = substr_replace($pages[3], pack('n', 0) . pack('n', 3), 500, 4);
        return $pages;
    }), 'btree page 3: SQLite b-tree freeblock size is too small'],
    'deep catches fragmented bytes over sqlite limit' => ['PRAGMA integrity_check', $autoVacuumWithTableLeaf(static function (array $pages): array {
        $pages[3][7] = "\x3d";
        return $pages;
    }), 'btree page 3: SQLite b-tree fragmented free bytes cannot exceed 60'],
    'deep catches cell pointer outside content' => ['PRAGMA integrity_check', $autoVacuumWithTableLeaf(static function (array $pages): array {
        $pages[3] = substr_replace($pages[3], pack('n', 20), 8, 2);
        return $pages;
    }), 'btree page 3: SQLite b-tree cell pointer is outside the cell content area'],
    'deep catches cell local payload beyond page' => ['PRAGMA integrity_check', $autoVacuumWithTableLeaf(static function (array $pages): array {
        $pages[3] = substr_replace($pages[3], pack('n', 511), 8, 2);
        $pages[3][511] = "\x7f";
        return $pages;
    }), 'btree page 3: Truncated SQLite varint'],
    'deep catches pointer array overlap' => ['PRAGMA integrity_check', $autoVacuumWithTableLeaf(static function (array $pages): array {
        $pages[3] = substr_replace($pages[3], pack('n', 240), 3, 2);
        $pages[3] = substr_replace($pages[3], pack('n', 490), 5, 2);
        return $pages;
    }), 'btree page 3: SQLite b-tree cell pointer is outside the cell content area'],
    'deep catches btree pointer-map type mismatch' => ['PRAGMA integrity_check', $autoVacuumWithTableLeaf(static function (array $pages) use ($putPointerMapEntry): array {
        $pages[2] = $putPointerMapEntry($pages[2], 3, SQLitePointerMapEntry::FREE_PAGE, 0);
        return $pages;
    }), 'pointer-map type free-page for page 3 does not match expected root-page'],
    'deep catches overflow pointer-map type mismatch' => ['PRAGMA integrity_check', $autoVacuumWithOverflow(static function (array $pages) use ($putPointerMapEntry): array {
        $pages[2] = $putPointerMapEntry($pages[2], 4, SQLitePointerMapEntry::BTREE_PAGE, 3);
        return $pages;
    }), 'pointer-map type btree-page for page 4 does not match expected first-overflow-page'],
    'deep catches overflow pointer-map parent mismatch' => ['PRAGMA integrity_check', $autoVacuumWithOverflow(static function (array $pages) use ($putPointerMapEntry): array {
        $pages[2] = $putPointerMapEntry($pages[2], 4, SQLitePointerMapEntry::FIRST_OVERFLOW_PAGE, 99);
        return $pages;
    }), 'pointer-map parent page 99 for page 4 is beyond the database image'],
    'deep catches overflow next page outside image' => ['PRAGMA integrity_check', $autoVacuumWithOverflow(static function (array $pages): array {
        $pages[4] = substr_replace($pages[4], pack('N', 9), 0, 4);
        return $pages;
    }), 'btree page 3: SQLite overflow chain has trailing pages beyond the expected payload length'],
    'deep catches index cell local payload beyond page' => ['PRAGMA integrity_check', $indexLeaf(static function (array $pages): array {
        $pages[3] = substr_replace($pages[3], pack('n', 511), 8, 2);
        $pages[3][511] = "\x7f";
        return $pages;
    }), 'btree page 3: SQLite index cell local payload extends beyond the page'],
    'deep catches interior child pointer zero' => ['PRAGMA integrity_check', $tableInterior(static function (array $pages): array {
        $pages[3] = substr_replace($pages[3], pack('N', 0), 507, 4);
        return $pages;
    }), 'btree page 3: SQLite table interior cell child page must be positive'],
    'deep catches interior right pointer map mismatch' => ['PRAGMA integrity_check', $tableInterior(static function (array $pages) use ($putPointerMapEntry): array {
        $pages[2] = $putPointerMapEntry($pages[2], 5, SQLitePointerMapEntry::FREE_PAGE, 0);
        return $pages;
    }), 'btree page 3 right-most child page 5 pointer-map type free-page does not match expected btree-page'],
    'integrity limit truncates deep errors' => ['PRAGMA integrity_check(1)', $autoVacuumWithTableLeaf(static function (array $pages) use ($putPointerMapEntry): array {
        $pages[2] = $putPointerMapEntry($pages[2], 3, SQLitePointerMapEntry::FREE_PAGE, 0);
        $pages[3][7] = "\x3d";
        return $pages;
    }), 'btree page 3: SQLite b-tree fragmented free bytes cannot exceed 60'],
];

foreach ($cases as $name => [$sql, $database, $expected]) {
    $tests['pragma integrity deep current next19 ' . $name] = static function (TestRunner $t) use ($firstError, $sql, $database, $expected): void {
        $t->same($expected, $firstError($sql, $database));
    };
}

$tests['pragma integrity deep current next19 rowset keeps btree error column'] = static function (TestRunner $t) use ($autoVacuumWithTableLeaf, $putPointerMapEntry): void {
    $result = SQLitePragmaIntegrityCheck::execute('PRAGMA integrity_check', $autoVacuumWithTableLeaf(static function (array $pages) use ($putPointerMapEntry): array {
        $pages[2] = $putPointerMapEntry($pages[2], 3, SQLitePointerMapEntry::FREE_PAGE, 0);
        return $pages;
    }));

    $t->same('integrity_check', $result['pragma']);
    $t->same($result['errors'][0], $result['rows'][0]['integrity_check']);
};

$tests['pragma integrity deep current next19 stops current btree page after structural error'] = static function (TestRunner $t) use ($autoVacuumWithTableLeaf, $putPointerMapEntry): void {
    $result = SQLitePragmaIntegrityCheck::execute('PRAGMA integrity_check(3)', $autoVacuumWithTableLeaf(static function (array $pages) use ($putPointerMapEntry): array {
        $pages[2] = $putPointerMapEntry($pages[2], 3, SQLitePointerMapEntry::FREE_PAGE, 0);
        $pages[3][7] = "\x3d";
        return $pages;
    }));

    $t->same([
        'btree page 3: SQLite b-tree fragmented free bytes cannot exceed 60',
    ], $result['errors']);
};

$tests['pragma integrity deep current next19 quick check still reports header before deep skip'] = static function (TestRunner $t) use ($autoVacuumWithTableLeaf): void {
    $result = SQLitePragmaIntegrityCheck::execute('PRAGMA quick_check', substr_replace($autoVacuumWithTableLeaf(), "\x09", 18, 1));

    $t->same(['invalid schema write version 9'], $result['errors']);
};

$tests['pragma integrity deep current next19 deep preserves parsed limit metadata'] = static function (TestRunner $t) use ($autoVacuumWithOverflow): void {
    $result = SQLitePragmaIntegrityCheck::execute('PRAGMA main.integrity_check=4', $autoVacuumWithOverflow());

    $t->same(['integrity_check', 4, []], [$result['pragma'], $result['limit'], $result['errors']]);
};

$tests['pragma integrity deep current next19 quick rowset uses quick column after deep skip'] = static function (TestRunner $t) use ($autoVacuumWithTableLeaf): void {
    $result = SQLitePragmaIntegrityCheck::execute('PRAGMA quick_check', $autoVacuumWithTableLeaf(static function (array $pages): array {
        $pages[3] = substr_replace($pages[3], "\xff", 0, 1);
        return $pages;
    }));

    $t->same('ok', $result['rows'][0]['quick_check']);
};

$tests['pragma integrity deep current next19 detects second overflow pointer map parent after first page'] = static function (TestRunner $t) use ($headerPage, $putPointerMapEntry, $pageSize, $firstError): void {
    $payload = str_repeat('z', 1200);
    $encoded = SQLiteTableLeafCell::encodeWithOverflowPages(10, $payload, 4, $pageSize);
    $pointerMap = str_repeat("\0", $pageSize);
    $pointerMap = $putPointerMapEntry($pointerMap, 3, SQLitePointerMapEntry::ROOT_PAGE, 0);
    $pointerMap = $putPointerMapEntry($pointerMap, 4, SQLitePointerMapEntry::FIRST_OVERFLOW_PAGE, 3);
    $pointerMap = $putPointerMapEntry($pointerMap, 5, SQLitePointerMapEntry::OVERFLOW_PAGE, 99);
    $database = implode('', [
        $headerPage(5, 3),
        $pointerMap,
        SQLiteTableLeafPage::assemble([$encoded['cell']], $pageSize),
        $encoded['overflowPages'][0],
        $encoded['overflowPages'][1],
    ]);

    $t->same('pointer-map parent page 99 for page 5 is beyond the database image', $firstError('PRAGMA integrity_check', $database));
};

$tests['pragma integrity deep current next19 accepts second overflow pointer map parent chain'] = static function (TestRunner $t) use ($headerPage, $putPointerMapEntry, $pageSize): void {
    $payload = str_repeat('z', 1200);
    $encoded = SQLiteTableLeafCell::encodeWithOverflowPages(10, $payload, 4, $pageSize);
    $pointerMap = str_repeat("\0", $pageSize);
    $pointerMap = $putPointerMapEntry($pointerMap, 3, SQLitePointerMapEntry::ROOT_PAGE, 0);
    $pointerMap = $putPointerMapEntry($pointerMap, 4, SQLitePointerMapEntry::FIRST_OVERFLOW_PAGE, 3);
    $pointerMap = $putPointerMapEntry($pointerMap, 5, SQLitePointerMapEntry::OVERFLOW_PAGE, 4);
    $database = implode('', [
        $headerPage(5, 3),
        $pointerMap,
        SQLiteTableLeafPage::assemble([$encoded['cell']], $pageSize),
        $encoded['overflowPages'][0],
        $encoded['overflowPages'][1],
    ]);

    $t->same([], SQLitePragmaIntegrityCheck::execute('PRAGMA integrity_check', $database)['errors']);
};

$tests['pragma integrity deep current next19 skips freelist trunk page image during btree pass'] = static function (TestRunner $t) use ($headerPage, $pageSize): void {
    $first = $headerPage(2, 0);
    $first = substr_replace($first, pack('N', 2), 32, 4);
    $first = substr_replace($first, pack('N', 1), 36, 4);
    $database = $first . str_repeat("\xff", $pageSize);

    $t->same('SQLite freelist next trunk page is outside the database image', SQLitePragmaIntegrityCheck::execute('PRAGMA integrity_check', $database)['errors'][0]);
};

$tests['pragma integrity deep current next19 btree errors follow header errors after limit room'] = static function (TestRunner $t) use ($autoVacuumWithTableLeaf, $putPointerMapEntry): void {
    $database = substr_replace($autoVacuumWithTableLeaf(static function (array $pages) use ($putPointerMapEntry): array {
        $pages[2] = $putPointerMapEntry($pages[2], 3, SQLitePointerMapEntry::FREE_PAGE, 0);
        return $pages;
    }), "\x09", 18, 1);
    $result = SQLitePragmaIntegrityCheck::execute('PRAGMA integrity_check(2)', $database);

    $t->same('invalid schema write version 9', $result['errors'][0]);
    $t->same('pointer-map type free-page for page 3 does not match expected root-page', $result['errors'][1]);
};

return $tests;

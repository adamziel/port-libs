<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePointerMapEntry;
use PortLibs\LibSqlite\SQLitePragmaIntegrityPointerMapFreelistYield;

$pageSize = 512;
$pageCount = 60;

$database = static function () use ($pageSize, $pageCount): string {
    $first = str_repeat("\0", $pageSize);
    $first = substr_replace($first, "SQLite format 3\0", 0, 16);
    $first = substr_replace($first, pack('n', $pageSize), 16, 2);
    $first[18] = "\x01";
    $first[19] = "\x01";
    $first = substr_replace($first, pack('N', $pageCount), 28, 4);
    $first = substr_replace($first, pack('N', 3), 52, 4);
    $first = substr_replace($first, pack('N', 1), 56, 4);

    $pointerMap = str_repeat("\0", $pageSize);
    $put = static function (int $pageNumber, int $type, int $parent) use (&$pointerMap): void {
        $pointerMap = substr_replace($pointerMap, chr($type) . pack('N', $parent), 5 * ($pageNumber - 3), 5);
    };
    $put(3, SQLitePointerMapEntry::ROOT_PAGE, 0);
    for ($pageNumber = 4; $pageNumber <= $pageCount; $pageNumber++) {
        $put($pageNumber, SQLitePointerMapEntry::BTREE_PAGE, 0);
    }

    $pages = [$first, $pointerMap];
    for ($pageNumber = 3; $pageNumber <= $pageCount; $pageNumber++) {
        $pages[] = str_repeat("\0", $pageSize);
    }

    return implode('', $pages);
};

$bytes = $database();
$page0 = static fn (): array => SQLitePragmaIntegrityPointerMapFreelistYield::page($bytes, 0, 48);
$page1 = static fn (): array => SQLitePragmaIntegrityPointerMapFreelistYield::page($bytes, 48, 48);
$quick = static fn (): array => SQLitePragmaIntegrityPointerMapFreelistYield::page($bytes, 0, 48, 'PRAGMA quick_check');
$small = static fn (): array => SQLitePragmaIntegrityPointerMapFreelistYield::page($bytes, 3, 5);
$valueAt = static function (mixed $value, string $path): mixed {
    foreach (explode('.', $path) as $part) {
        if (is_array($value) && array_key_exists($part, $value)) {
            $value = $value[$part];
            continue;
        }
        $value = is_numeric($part) ? $value[(int) $part] : $value[$part];
    }

    return $value;
};

$cases = [
    'page0 status' => [$page0, 'status', 'ok'],
    'page0 offset' => [$page0, 'offset', 0],
    'page0 limit' => [$page0, 'limit', 48],
    'page0 count uses current next48' => [$page0, 'count', 48],
    'page0 total stable' => [$page0, 'total', 57],
    'page0 next offset' => [$page0, 'next_offset', 48],
    'page0 incomplete' => [$page0, 'complete', false],
    'page0 first kind' => [$page0, 'rows.0.kind', 'integrity_check'],
    'page0 first source' => [$page0, 'rows.0.source', 'pointer_map'],
    'page0 first page' => [$page0, 'rows.0.page', 4],
    'page0 first pointer map page' => [$page0, 'rows.0.pointer_map_page', 2],
    'page0 first message' => [$page0, 'rows.0.message', 'pointer-map parent page 0 for btree-page page 4 is not valid'],
    'page0 second page' => [$page0, 'rows.1.page', 5],
    'page0 fifth page' => [$page0, 'rows.4.page', 8],
    'page0 tenth page' => [$page0, 'rows.9.page', 13],
    'page0 twentieth page' => [$page0, 'rows.19.page', 23],
    'page0 midpoint page' => [$page0, 'rows.23.page', 27],
    'page0 thirtieth page' => [$page0, 'rows.29.page', 33],
    'page0 fortieth page' => [$page0, 'rows.39.page', 43],
    'page0 last page before next' => [$page0, 'rows.47.page', 51],
    'page0 last message before next' => [$page0, 'rows.47.message', 'pointer-map parent page 0 for btree-page page 51 is not valid'],
    'page1 status' => [$page1, 'status', 'ok'],
    'page1 offset' => [$page1, 'offset', 48],
    'page1 limit' => [$page1, 'limit', 48],
    'page1 count remaining' => [$page1, 'count', 9],
    'page1 total stable' => [$page1, 'total', 57],
    'page1 complete' => [$page1, 'complete', true],
    'page1 next offset null' => [$page1, 'next_offset', null],
    'page1 first page continues' => [$page1, 'rows.0.page', 52],
    'page1 first pointer map page' => [$page1, 'rows.0.pointer_map_page', 2],
    'page1 last page' => [$page1, 'rows.8.page', 60],
    'page1 last source' => [$page1, 'rows.8.source', 'pointer_map'],
    'page1 last message' => [$page1, 'rows.8.message', 'pointer-map parent page 0 for btree-page page 60 is not valid'],
    'quick status' => [$quick, 'status', 'ok'],
    'quick count skips deep pointer map' => [$quick, 'count', 0],
    'quick total skips deep pointer map' => [$quick, 'total', 0],
    'quick complete' => [$quick, 'complete', true],
    'quick next offset null' => [$quick, 'next_offset', null],
    'small offset' => [$small, 'offset', 3],
    'small limit' => [$small, 'limit', 5],
    'small count' => [$small, 'count', 5],
    'small total stable' => [$small, 'total', 57],
    'small next offset advances by count' => [$small, 'next_offset', 8],
    'small incomplete' => [$small, 'complete', false],
    'small first page' => [$small, 'rows.0.page', 7],
    'small last page' => [$small, 'rows.4.page', 11],
    'small first pointer map page' => [$small, 'rows.0.pointer_map_page', 2],
    'small last pointer map page' => [$small, 'rows.4.pointer_map_page', 2],
];

$tests = [];
foreach ($cases as $name => [$callback, $path, $expected]) {
    $tests['pragma integrity pointermap freelist current next48 ' . $name] = static function (TestRunner $t) use ($callback, $valueAt, $path, $expected): void {
        $t->same($expected, $valueAt($callback(), $path));
    };
}

$tests['pragma integrity pointermap freelist current next48 collect matches paged rows'] = static function (TestRunner $t) use ($bytes, $page0, $page1): void {
    $t->same(SQLitePragmaIntegrityPointerMapFreelistYield::collect($bytes), array_merge($page0()['rows'], $page1()['rows']));
};

$tests['pragma integrity pointermap freelist current next48 tail offset returns empty complete page'] = static function (TestRunner $t) use ($bytes): void {
    $page = SQLitePragmaIntegrityPointerMapFreelistYield::page($bytes, 57, 48);
    $t->same(['count' => 0, 'total' => 57, 'next_offset' => null, 'complete' => true], ['count' => $page['count'], 'total' => $page['total'], 'next_offset' => $page['next_offset'], 'complete' => $page['complete']]);
};

$tests['pragma integrity pointermap freelist current next48 rejects negative offset'] = static function (TestRunner $t) use ($bytes): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLitePragmaIntegrityPointerMapFreelistYield::page($bytes, -1, 48));
};

$tests['pragma integrity pointermap freelist current next48 rejects zero limit'] = static function (TestRunner $t) use ($bytes): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLitePragmaIntegrityPointerMapFreelistYield::page($bytes, 0, 0));
};

$tests['pragma integrity pointermap freelist current next48 propagates pragma parser guard'] = static function (TestRunner $t) use ($bytes): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLitePragmaIntegrityPointerMapFreelistYield::page($bytes, 0, 48, 'PRAGMA table_info(wp_options)'));
};

return $tests;

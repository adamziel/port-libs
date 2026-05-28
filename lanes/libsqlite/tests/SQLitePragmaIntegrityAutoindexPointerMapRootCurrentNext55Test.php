<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteIndexLeafPage;
use PortLibs\LibSqlite\SQLitePointerMapEntry;
use PortLibs\LibSqlite\SQLitePragmaIntegrityAutoindexYield;
use PortLibs\LibSqlite\SQLiteRecord;
use PortLibs\LibSqlite\SQLiteTableLeafCell;
use PortLibs\LibSqlite\SQLiteTableLeafPage;

$tests = [];
$pageSize = 4096;

$headerPage = static function (int $pageCount, int $largestRoot) use ($pageSize): string {
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

$putPointerMapEntry = static fn (string $page, int $pageNumber, int $type, int $parent): string => substr_replace($page, chr($type) . pack('N', $parent), 5 * ($pageNumber - 3), 5);
$schemaCell = static fn (array $values, int $rowId): string => SQLiteTableLeafCell::encode($rowId, SQLiteRecord::encode($values), $pageSize);

$makeDatabase = static function (
    int $autoindexCount = 64,
    ?callable $mutatePointerMap = null,
    ?callable $mutatePages = null,
    ?callable $mutateSchemaRecords = null,
) use ($headerPage, $putPointerMapEntry, $schemaCell, $pageSize): string {
    $pageCount = $autoindexCount + 3;
    $schemaRecords = [
        $schemaCell(['table', 'wp_options', 'wp_options', 3, 'CREATE TABLE wp_options(option_id INTEGER PRIMARY KEY, option_name TEXT UNIQUE, autoload TEXT, UNIQUE(autoload, option_name))'], 1),
    ];
    for ($i = 1; $i <= $autoindexCount; $i++) {
        $schemaRecords[] = $schemaCell(['index', 'sqlite_autoindex_wp_options_' . $i, 'wp_options', $i + 3, null], $i + 1);
    }
    if ($mutateSchemaRecords !== null) {
        $schemaRecords = $mutateSchemaRecords($schemaRecords, $schemaCell);
    }

    $pointerMap = str_repeat("\0", $pageSize);
    for ($pageNumber = 3; $pageNumber <= $pageCount; $pageNumber++) {
        $pointerMap = $putPointerMapEntry($pointerMap, $pageNumber, SQLitePointerMapEntry::ROOT_PAGE, 0);
    }
    if ($mutatePointerMap !== null) {
        $pointerMap = $mutatePointerMap($pointerMap, $putPointerMapEntry);
    }

    $pages = [
        1 => SQLiteTableLeafPage::assemble($schemaRecords, $pageSize, 100, $headerPage($pageCount, $pageCount)),
        2 => $pointerMap,
        3 => SQLiteTableLeafPage::assemble([], $pageSize),
    ];
    for ($pageNumber = 4; $pageNumber <= $pageCount; $pageNumber++) {
        $pages[$pageNumber] = SQLiteIndexLeafPage::assemble([], $pageSize);
    }
    if ($mutatePages !== null) {
        $pages = $mutatePages($pages);
    }
    ksort($pages);

    return implode('', $pages);
};

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

$page0 = static fn (): array => SQLitePragmaIntegrityAutoindexYield::page($makeDatabase(), 0, 55);
$page1 = static fn (): array => SQLitePragmaIntegrityAutoindexYield::page($makeDatabase(), 55, 55);
$collect = static fn (): array => SQLitePragmaIntegrityAutoindexYield::collect($makeDatabase());
$slice = static fn (): array => SQLitePragmaIntegrityAutoindexYield::page($makeDatabase(), 29, 7);

$cases = [
    'page0 limit current next55' => [$page0, 'limit', 55],
    'page0 count current next55' => [$page0, 'count', 55],
    'page0 total' => [$page0, 'total', 64],
    'page0 next offset' => [$page0, 'next_offset', 55],
    'page0 incomplete' => [$page0, 'complete', false],
    'page0 first rootpage' => [$page0, 'rows.0.rootpage', 4],
    'page0 first previous root null' => [$page0, 'rows.0.previous_rootpage', null],
    'page0 first current root' => [$page0, 'rows.0.current_rootpage', 4],
    'page0 first next root' => [$page0, 'rows.0.next_rootpage', 5],
    'page0 first page type' => [$page0, 'rows.0.rootpage_page_type', 'index-leaf'],
    'page0 first pointer map page' => [$page0, 'rows.0.pointer_map.pointer_map_page', 2],
    'page0 first pointer map offset' => [$page0, 'rows.0.pointer_map.offset', 5],
    'page0 first pointer map type' => [$page0, 'rows.0.pointer_map.type_name', 'root-page'],
    'page0 first pointer map parent' => [$page0, 'rows.0.pointer_map.parent_page_number', 0],
    'page0 first not largest root' => [$page0, 'rows.0.rootpage_is_largest_root', false],
    'page0 second previous root' => [$page0, 'rows.1.previous_rootpage', 4],
    'page0 second current root' => [$page0, 'rows.1.current_rootpage', 5],
    'page0 second next root' => [$page0, 'rows.1.next_rootpage', 6],
    'page0 midpoint name' => [$page0, 'rows.27.name', 'sqlite_autoindex_wp_options_28'],
    'page0 midpoint previous root' => [$page0, 'rows.27.previous_rootpage', 30],
    'page0 midpoint current root' => [$page0, 'rows.27.current_rootpage', 31],
    'page0 midpoint next root' => [$page0, 'rows.27.next_rootpage', 32],
    'page0 midpoint pointer map offset' => [$page0, 'rows.27.pointer_map.offset', 140],
    'page0 last name' => [$page0, 'rows.54.name', 'sqlite_autoindex_wp_options_55'],
    'page0 last previous root' => [$page0, 'rows.54.previous_rootpage', 57],
    'page0 last current root' => [$page0, 'rows.54.current_rootpage', 58],
    'page0 last next root' => [$page0, 'rows.54.next_rootpage', 59],
    'page0 last pointer map page' => [$page0, 'rows.54.pointer_map.pointer_map_page', 2],
    'page0 last pointer map offset' => [$page0, 'rows.54.pointer_map.offset', 275],
    'page1 offset' => [$page1, 'offset', 55],
    'page1 count tail' => [$page1, 'count', 9],
    'page1 total stable' => [$page1, 'total', 64],
    'page1 complete' => [$page1, 'complete', true],
    'page1 next offset null' => [$page1, 'next_offset', null],
    'page1 first tail previous root' => [$page1, 'rows.0.previous_rootpage', 58],
    'page1 first tail current root' => [$page1, 'rows.0.current_rootpage', 59],
    'page1 first tail next root' => [$page1, 'rows.0.next_rootpage', 60],
    'page1 first tail pointer offset' => [$page1, 'rows.0.pointer_map.offset', 280],
    'page1 last tail name' => [$page1, 'rows.8.name', 'sqlite_autoindex_wp_options_64'],
    'page1 last tail previous root' => [$page1, 'rows.8.previous_rootpage', 66],
    'page1 last tail current root' => [$page1, 'rows.8.current_rootpage', 67],
    'page1 last tail next root null' => [$page1, 'rows.8.next_rootpage', null],
    'page1 last tail largest root' => [$page1, 'rows.8.rootpage_is_largest_root', true],
    'page1 last tail pointer offset' => [$page1, 'rows.8.pointer_map.offset', 320],
    'slice offset' => [$slice, 'offset', 29],
    'slice count' => [$slice, 'count', 7],
    'slice first name' => [$slice, 'rows.0.name', 'sqlite_autoindex_wp_options_30'],
    'slice first previous root' => [$slice, 'rows.0.previous_rootpage', 32],
    'slice first current root' => [$slice, 'rows.0.current_rootpage', 33],
    'slice last name' => [$slice, 'rows.6.name', 'sqlite_autoindex_wp_options_36'],
    'slice last next root' => [$slice, 'rows.6.next_rootpage', 40],
    'collect first pointer map page number' => [$collect, '0.pointer_map.page_number', 4],
    'collect last pointer map page number' => [$collect, '63.pointer_map.page_number', 67],
    'collect last pointer map type numeric' => [$collect, '63.pointer_map.type', SQLitePointerMapEntry::ROOT_PAGE],
    'collect last status' => [$collect, '63.status', 'ok'],
];

foreach ($cases as $name => [$callback, $path, $expected]) {
    $tests['pragma integrity autoindex pointermap root current next55 ' . $name] = static function (TestRunner $t) use ($callback, $valueAt, $path, $expected): void {
        $t->same($expected, $valueAt($callback(), $path));
    };
}

$tests['pragma integrity autoindex pointermap root current next55 pointer map mismatch keeps metadata'] = static function (TestRunner $t) use ($makeDatabase): void {
    $database = $makeDatabase(64, static function (string $pointerMap, callable $put): string {
        return $put($pointerMap, 59, SQLitePointerMapEntry::BTREE_PAGE, 3);
    });
    $row = SQLitePragmaIntegrityAutoindexYield::collect($database)[55];

    $t->same('error', $row['status']);
    $t->same('btree-page', $row['pointer_map']['type_name']);
    $t->same(3, $row['pointer_map']['parent_page_number']);
    $t->same('index-leaf', $row['rootpage_page_type']);
    $t->same(58, $row['previous_rootpage']);
    $t->same(59, $row['current_rootpage']);
    $t->same(60, $row['next_rootpage']);
};

$tests['pragma integrity autoindex pointermap root current next55 pointer map parent mismatch keeps root cursor'] = static function (TestRunner $t) use ($makeDatabase): void {
    $database = $makeDatabase(64, static function (string $pointerMap, callable $put): string {
        return $put($pointerMap, 67, SQLitePointerMapEntry::ROOT_PAGE, 3);
    });
    $row = SQLitePragmaIntegrityAutoindexYield::collect($database)[63];

    $t->same('error', $row['status']);
    $t->same('root-page', $row['pointer_map']['type_name']);
    $t->same(3, $row['pointer_map']['parent_page_number']);
    $t->same(66, $row['previous_rootpage']);
    $t->same(67, $row['current_rootpage']);
    $t->same(null, $row['next_rootpage']);
    $t->same(true, $row['rootpage_is_largest_root']);
};

$tests['pragma integrity autoindex pointermap root current next55 non index page reports page type without pointer map'] = static function (TestRunner $t) use ($makeDatabase, $pageSize): void {
    $database = $makeDatabase(64, null, static function (array $pages) use ($pageSize): array {
        $page = str_repeat("\0", $pageSize);
        $page[0] = "\x0d";
        $pages[7] = $page;
        return $pages;
    });
    $row = SQLitePragmaIntegrityAutoindexYield::collect($database)[3];

    $t->same('error', $row['status']);
    $t->same('table-leaf', $row['rootpage_page_type']);
    $t->same(null, $row['pointer_map']);
    $t->same(6, $row['previous_rootpage']);
    $t->same(7, $row['current_rootpage']);
    $t->same(8, $row['next_rootpage']);
};

$tests['pragma integrity autoindex pointermap root current next55 pointer-map page root reports role'] = static function (TestRunner $t) use ($makeDatabase, $schemaCell): void {
    $database = $makeDatabase(2, null, null, static fn (array $records): array => [
        $records[0],
        $schemaCell(['index', 'sqlite_autoindex_wp_options_1', 'wp_options', 2, null], 2),
        $schemaCell(['index', 'sqlite_autoindex_wp_options_2', 'wp_options', 5, null], 3),
    ]);
    $row = SQLitePragmaIntegrityAutoindexYield::collect($database)[0];

    $t->same('error', $row['status']);
    $t->same('pointer-map', $row['rootpage_page_type']);
    $t->same(null, $row['pointer_map']);
    $t->same(2, $row['current_rootpage']);
    $t->same(5, $row['next_rootpage']);
};

return $tests;

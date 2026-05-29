<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteFreelistTrunkPage;
use PortLibs\LibSqlite\SQLiteIndexLeafPage;
use PortLibs\LibSqlite\SQLitePointerMapEntry;
use PortLibs\LibSqlite\SQLitePragmaRootpageIntegrityAnalysisCurrentSourceNext;
use PortLibs\LibSqlite\SQLiteRecord;
use PortLibs\LibSqlite\SQLiteTableLeafCell;
use PortLibs\LibSqlite\SQLiteTableLeafPage;

$tests = [];
$pageSize = 1024;

$headerPage = static function (int $pageCount, int $largestRootPage, int $firstFreelist = 0, int $freelistCount = 0) use ($pageSize): string {
    $page = str_repeat("\0", $pageSize);
    $page = substr_replace($page, "SQLite format 3\0", 0, 16);
    $page = substr_replace($page, pack('n', $pageSize), 16, 2);
    $page[18] = "\x01";
    $page[19] = "\x01";
    $page = substr_replace($page, pack('N', $pageCount), 28, 4);
    $page = substr_replace($page, pack('N', $firstFreelist), 32, 4);
    $page = substr_replace($page, pack('N', $freelistCount), 36, 4);
    $page = substr_replace($page, pack('N', $largestRootPage), 52, 4);
    $page = substr_replace($page, pack('N', 1), 56, 4);

    return $page;
};

$putPointerMapEntry = static function (string $page, int $pageNumber, int $type, int $parent) use ($pageSize): string {
    $offset = 5 * ($pageNumber - 3);
    if ($offset < 0 || $offset + 5 > $pageSize) {
        throw new RuntimeException('test pointer-map entry offset is out of range');
    }

    return substr_replace($page, chr($type) . pack('N', $parent), $offset, 5);
};

$schemaCell = static fn (array $values, int $rowId): string => SQLiteTableLeafCell::encode($rowId, SQLiteRecord::encode($values));

$schemaDatabase = static function (
    array $schemaRows,
    int $pageCount,
    int $largestRootPage,
    array $pointerMapEntries,
    array $pageImages = [],
    int $firstFreelist = 0,
    int $freelistCount = 0,
) use ($headerPage, $putPointerMapEntry, $schemaCell, $pageSize): string {
    $pages = [
        1 => SQLiteTableLeafPage::assemble(
            array_map(static fn (array $row, int $index): string => $schemaCell($row, $index + 1), $schemaRows, array_keys($schemaRows)),
            $pageSize,
            100,
            $headerPage($pageCount, $largestRootPage, $firstFreelist, $freelistCount),
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
    'table' => ['table', 'wp_options', 'wp_options', 4, 'CREATE TABLE wp_options(option_id integer primary key, option_name text, option_value text, autoload text)'],
    'index' => ['index', 'wp_options_name', 'wp_options', 5, 'CREATE UNIQUE INDEX wp_options_name ON wp_options(option_name)'],
    'view' => ['view', 'wp_active_options', 'wp_active_options', 0, "CREATE VIEW wp_active_options AS SELECT * FROM wp_options WHERE autoload = 'yes'"],
    'trigger' => ['trigger', 'wp_options_ai', 'wp_options', 0, 'CREATE TRIGGER wp_options_ai AFTER INSERT ON wp_options BEGIN SELECT 1; END'],
];

$validDatabase = $schemaDatabase([$rows['table'], $rows['index'], $rows['view'], $rows['trigger']], 5, 5, [
    [3, SQLitePointerMapEntry::BTREE_PAGE, 4],
    [4, SQLitePointerMapEntry::ROOT_PAGE, 0],
    [5, SQLitePointerMapEntry::ROOT_PAGE, 0],
], [
    5 => SQLiteIndexLeafPage::assemble([], $pageSize),
]);
$valid = SQLitePragmaRootpageIntegrityAnalysisCurrentSourceNext::analyze($validDatabase);

$tests['pragma rootpage integrity analysis current source next111 valid source summary'] = static function (TestRunner $t) use ($valid): void {
    $t->same('ok', $valid['status']);
    $t->same('pragma-rootpage-integrity-analysis-current-source-next111', $valid['source']);
    $t->same(5, $valid['page_count']);
    $t->same(5, $valid['largest_root_btree_page']);
    $t->same(5, $valid['max_schema_rootpage']);
    $t->same(true, $valid['auto_vacuum']);
    $t->same(4, $valid['ok_count']);
    $t->same(0, $valid['problem_count']);
    $t->same(true, $valid['next']['ready']);
    $t->same([], $valid['next']['blocking']);
};

$tests['pragma rootpage integrity analysis current source next111 valid current counters'] = static function (TestRunner $t) use ($valid): void {
    $t->same(4, $valid['current']['schema_records']);
    $t->same(2, $valid['current']['root_records']);
    $t->same(0, $valid['current']['duplicate_rootpages']);
    $t->same(0, $valid['current']['freelist_conflicts']);
    $t->same(0, $valid['current']['pointer_map_conflicts']);
    $t->same(0, $valid['current']['largest_root_mismatch']);
};

$tests['pragma rootpage integrity analysis current source next111 valid table and index rows'] = static function (TestRunner $t) use ($valid): void {
    $byName = [];
    foreach ($valid['rows'] as $row) {
        $byName[$row['name']] = $row;
    }

    $t->same('ok', $byName['wp_options']['status']);
    $t->same('table-leaf', $byName['wp_options']['page_type']);
    $t->same(0x0d, $byName['wp_options']['page_flag']);
    $t->same('root-page', $byName['wp_options']['pointer_map_type']);
    $t->same(0, $byName['wp_options']['pointer_map_parent']);
    $t->same(2, $byName['wp_options']['pointer_map_page']);
    $t->same('index-leaf', $byName['wp_options_name']['page_type']);
    $t->same(0x0a, $byName['wp_options_name']['page_flag']);
    $t->same('ignored', $byName['wp_active_options']['status']);
    $t->same('ignored', $byName['wp_options_ai']['status']);
};

$duplicateDatabase = $schemaDatabase([
    $rows['table'],
    ['index', 'wp_options_alias', 'wp_options', 4, 'CREATE INDEX wp_options_alias ON wp_options(autoload)'],
], 4, 4, [
    [3, SQLitePointerMapEntry::BTREE_PAGE, 4],
    [4, SQLitePointerMapEntry::ROOT_PAGE, 0],
]);
$duplicate = SQLitePragmaRootpageIntegrityAnalysisCurrentSourceNext::analyze($duplicateDatabase);

$tests['pragma rootpage integrity analysis current source next111 duplicate rootpages are blocking'] = static function (TestRunner $t) use ($duplicate): void {
    $t->same('error', $duplicate['status']);
    $t->same(2, $duplicate['problem_count']);
    $t->same(1, $duplicate['current']['duplicate_rootpages']);
    $t->same(false, $duplicate['next']['ready']);
    $t->contains('rootpage 4 is also used by index:wp_options_alias', $duplicate['rows'][0]['message']);
    $t->contains('rootpage 4 is also used by table:wp_options', $duplicate['rows'][1]['message']);
    $t->same(['index:wp_options_alias'], $duplicate['rows'][0]['duplicate_names']);
    $t->same(['table:wp_options'], $duplicate['rows'][1]['duplicate_names']);
};

$freelistDatabase = $schemaDatabase([$rows['table']], 4, 4, [
    [3, SQLitePointerMapEntry::BTREE_PAGE, 4],
    [4, SQLitePointerMapEntry::ROOT_PAGE, 0],
], [
    3 => SQLiteFreelistTrunkPage::assemble(null, [4], $pageSize),
], 3, 2);
$freelist = SQLitePragmaRootpageIntegrityAnalysisCurrentSourceNext::analyze($freelistDatabase);

$tests['pragma rootpage integrity analysis current source next111 rootpage freelist conflicts'] = static function (TestRunner $t) use ($freelist): void {
    $t->same('error', $freelist['status']);
    $t->same(1, $freelist['problem_count']);
    $t->same(1, $freelist['current']['freelist_conflicts']);
    $t->same('freelist_conflict', $freelist['rows'][0]['kind']);
    $t->same('freelist', $freelist['rows'][0]['page_status']);
    $t->same('sqlite_schema table wp_options rootpage 4 is on the freelist', $freelist['rows'][0]['message']);
};

$wrongTypeDatabase = $schemaDatabase([$rows['table']], 4, 4, [
    [3, SQLitePointerMapEntry::BTREE_PAGE, 4],
    [4, SQLitePointerMapEntry::ROOT_PAGE, 0],
], [
    4 => SQLiteIndexLeafPage::assemble([], $pageSize),
]);
$wrongType = SQLitePragmaRootpageIntegrityAnalysisCurrentSourceNext::analyze($wrongTypeDatabase);

$tests['pragma rootpage integrity analysis current source next111 wrong btree page type'] = static function (TestRunner $t) use ($wrongType): void {
    $t->same('error', $wrongType['status']);
    $t->same('wrong_btree_type', $wrongType['rows'][0]['page_status']);
    $t->same('index-leaf', $wrongType['rows'][0]['page_type']);
    $t->same(0x0a, $wrongType['rows'][0]['page_flag']);
    $t->same('sqlite_schema table wp_options rootpage 4 points at index-leaf page, expected table b-tree', $wrongType['rows'][0]['message']);
};

$pointerMapDatabase = $schemaDatabase([$rows['index']], 5, 5, [
    [3, SQLitePointerMapEntry::BTREE_PAGE, 5],
    [4, SQLitePointerMapEntry::ROOT_PAGE, 0],
    [5, SQLitePointerMapEntry::BTREE_PAGE, 3],
], [
    5 => SQLiteIndexLeafPage::assemble([], $pageSize),
]);
$pointerMap = SQLitePragmaRootpageIntegrityAnalysisCurrentSourceNext::analyze($pointerMapDatabase);

$tests['pragma rootpage integrity analysis current source next111 pointer map root conflict'] = static function (TestRunner $t) use ($pointerMap): void {
    $t->same('error', $pointerMap['status']);
    $t->same(1, $pointerMap['problem_count']);
    $t->same(1, $pointerMap['current']['pointer_map_conflicts']);
    $t->same('pointer_map_conflict', $pointerMap['rows'][0]['kind']);
    $t->same('pointer_map', $pointerMap['rows'][0]['page_status']);
    $t->same('btree-page', $pointerMap['rows'][0]['pointer_map_type']);
    $t->same(3, $pointerMap['rows'][0]['pointer_map_parent']);
    $t->same('sqlite_schema index wp_options_name rootpage 5 pointer-map btree-page parent 3 does not match expected root-page parent 0', $pointerMap['rows'][0]['message']);
};

$largestRootDatabase = $schemaDatabase([$rows['table']], 5, 5, [
    [3, SQLitePointerMapEntry::BTREE_PAGE, 4],
    [4, SQLitePointerMapEntry::ROOT_PAGE, 0],
    [5, SQLitePointerMapEntry::BTREE_PAGE, 4],
]);
$largestRoot = SQLitePragmaRootpageIntegrityAnalysisCurrentSourceNext::analyze($largestRootDatabase);

$tests['pragma rootpage integrity analysis current source next111 largest root mismatch row'] = static function (TestRunner $t) use ($largestRoot): void {
    $t->same('error', $largestRoot['status']);
    $t->same(1, $largestRoot['problem_count']);
    $t->same(1, $largestRoot['current']['largest_root_mismatch']);
    $t->same('largest_root_mismatch', $largestRoot['rows'][1]['kind']);
    $t->same('header_mismatch', $largestRoot['rows'][1]['page_status']);
    $t->same('largest root btree page 5 does not match sqlite_schema max rootpage 4', $largestRoot['rows'][1]['message']);
    $t->same([$largestRoot['rows'][1]['message']], $largestRoot['next']['blocking']);
};

$beyondDatabase = $schemaDatabase([
    ['table', 'wp_options', 'wp_options', 9, $rows['table'][4]],
    ['index', 'wp_options_name', 'wp_options', -2, $rows['index'][4]],
    ['table', 'wp_comments', 'wp_comments', null, 'CREATE TABLE wp_comments(comment_id integer primary key)'],
], 5, 5, [
    [3, SQLitePointerMapEntry::BTREE_PAGE, 4],
    [4, SQLitePointerMapEntry::ROOT_PAGE, 0],
    [5, SQLitePointerMapEntry::ROOT_PAGE, 0],
]);
$beyond = SQLitePragmaRootpageIntegrityAnalysisCurrentSourceNext::analyze($beyondDatabase);

$tests['pragma rootpage integrity analysis current source next111 nonpositive and beyond rootpages'] = static function (TestRunner $t) use ($beyond): void {
    $t->same('error', $beyond['status']);
    $t->same(4, $beyond['problem_count']);
    $t->same('negative', $beyond['rows'][0]['page_status']);
    $t->same('header_mismatch', $beyond['rows'][1]['page_status']);
    $t->same('beyond_image', $beyond['rows'][2]['page_status']);
    $t->same('no_rootpage', $beyond['rows'][3]['page_status']);
    $t->contains('rootpage -2 is negative', $beyond['rows'][0]['message']);
    $t->contains('rootpage 9 is beyond the database image', $beyond['rows'][2]['message']);
    $t->contains('rootpage is empty', $beyond['rows'][3]['message']);
};

foreach (range(1, 28) as $index) {
    $tests['pragma rootpage integrity analysis current source next111 repeated valid wp option case ' . $index] = static function (TestRunner $t) use ($schemaDatabase, $rows, $index): void {
        $rootPage = $index % 2 === 0 ? 4 : 5;
        $record = $rootPage === 4 ? $rows['table'] : $rows['index'];
        $pageImage = $rootPage === 4 ? SQLiteTableLeafPage::assemble([], 1024) : SQLiteIndexLeafPage::assemble([], 1024);
        $database = $schemaDatabase([$record], 5, $rootPage, [
            [3, SQLitePointerMapEntry::BTREE_PAGE, $rootPage],
            [4, $rootPage === 4 ? SQLitePointerMapEntry::ROOT_PAGE : SQLitePointerMapEntry::BTREE_PAGE, $rootPage === 4 ? 0 : $rootPage],
            [5, $rootPage === 5 ? SQLitePointerMapEntry::ROOT_PAGE : SQLitePointerMapEntry::BTREE_PAGE, $rootPage === 5 ? 0 : $rootPage],
        ], [
            $rootPage => $pageImage,
        ]);
        $result = SQLitePragmaRootpageIntegrityAnalysisCurrentSourceNext::analyze($database);

        $t->same('ok', $result['status']);
        $t->same(1, $result['current']['root_records']);
        $t->same($rootPage, $result['rows'][0]['rootpage']);
        $t->same($record[1], $result['rows'][0]['name']);
    };
}

return $tests;

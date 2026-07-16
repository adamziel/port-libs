<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteAutoVacuumPointerMapApplyPlan;
use PortLibs\LibSqlite\SQLiteDatabase;
use PortLibs\LibSqlite\SQLitePointerMapEntry;
use PortLibs\LibSqlite\SQLiteRecord;
use PortLibs\LibSqlite\SQLiteTableLeafCell;
use PortLibs\LibSqlite\SQLiteTableLeafPage;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$pageSize = 512;
$firstPage = str_repeat("\0", $pageSize);
$firstPage = substr_replace($firstPage, "SQLite format 3\0", 0, 16);
$firstPage = substr_replace($firstPage, pack('n', $pageSize), 16, 2);
$firstPage[18] = "\x01";
$firstPage[19] = "\x01";
$firstPage[21] = "\x40";
$firstPage[22] = "\x20";
$firstPage[23] = "\x20";
$firstPage = substr_replace($firstPage, pack('N', 107), 28, 4);
$firstPage = substr_replace($firstPage, pack('N', 3), 52, 4);
$firstPage = substr_replace($firstPage, pack('N', 1), 56, 4);
$firstPage = substr_replace($firstPage, pack('N', 1), 64, 4);

$schemaCell = SQLiteTableLeafCell::encode(
    1,
    SQLiteRecord::encode([
        'table',
        'wp_options',
        'wp_options',
        3,
        'CREATE TABLE wp_options(option_id integer primary key, option_name text, option_value text, autoload text)',
    ]),
);
$schemaPage = SQLiteTableLeafPage::assemble([$schemaCell], $pageSize, 100, $firstPage);

$pointerMapPageTwo = str_repeat("\0", $pageSize);
foreach ([
    3 => [SQLitePointerMapEntry::ROOT_PAGE, 0],
    4 => [SQLitePointerMapEntry::BTREE_PAGE, 3],
    5 => [SQLitePointerMapEntry::FIRST_OVERFLOW_PAGE, 4],
    6 => [SQLitePointerMapEntry::OVERFLOW_PAGE, 5],
] as $pageNumber => [$type, $parentPageNumber]) {
    $pointerMapPageTwo = substr_replace(
        $pointerMapPageTwo,
        chr($type) . pack('N', $parentPageNumber),
        5 * ($pageNumber - 3),
        5,
    );
}

$pointerMapPageOneHundredFive = str_repeat("\0", $pageSize);
foreach ([
    106 => [SQLitePointerMapEntry::FREE_PAGE, 0],
    107 => [SQLitePointerMapEntry::FREE_PAGE, 0],
] as $pageNumber => [$type, $parentPageNumber]) {
    $pointerMapPageOneHundredFive = substr_replace(
        $pointerMapPageOneHundredFive,
        chr($type) . pack('N', $parentPageNumber),
        5 * ($pageNumber - 106),
        5,
    );
}

$pages = array_fill(1, 107, str_repeat("\0", $pageSize));
$pages[1] = $schemaPage;
$pages[2] = $pointerMapPageTwo;
$pages[3] = SQLiteTableLeafPage::assemble([
    SQLiteTableLeafCell::encode(1, SQLiteRecord::encode([null, 'siteurl', 'https://example.test', 'yes'])),
], $pageSize);
$pages[105] = $pointerMapPageOneHundredFive;

$database = SQLiteDatabase::fromBytes(implode('', $pages));
$replacementLeaf = SQLiteTableLeafPage::assemble([
    SQLiteTableLeafCell::encode(2, SQLiteRecord::encode([null, '_transient_rewrite_rules', 'rebuilt', 'no'])),
], $pageSize);

$plan = SQLiteAutoVacuumPointerMapApplyPlan::apply(
    $database,
    [
        4 => ['type' => SQLitePointerMapEntry::FREE_PAGE, 'parent_page_number' => 0],
        5 => ['type' => SQLitePointerMapEntry::OVERFLOW_PAGE, 'parent_page_number' => 106],
        106 => ['type' => SQLitePointerMapEntry::BTREE_PAGE, 'parent_page_number' => 3],
        107 => ['type' => SQLitePointerMapEntry::FIRST_OVERFLOW_PAGE, 'parent_page_number' => 106],
    ],
    [106 => $replacementLeaf],
);

echo json_encode([
    'scenario' => 'application-autovacuum-pointer-map-apply',
    'applicationUse' => 'Apply copied wp_options auto-vacuum pointer-map page images after a repair moves a transient leaf and retargets overflow ownership without ext/sqlite.',
    'summary' => $plan->toArray(),
    'postApplyPointerMap' => [
        4 => $plan->database->pointerMapEntryForPage(4)->toArray(),
        5 => $plan->database->pointerMapEntryForPage(5)->toArray(),
        106 => $plan->database->pointerMapEntryForPage(106)->toArray(),
        107 => $plan->database->pointerMapEntryForPage(107)->toArray(),
    ],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;

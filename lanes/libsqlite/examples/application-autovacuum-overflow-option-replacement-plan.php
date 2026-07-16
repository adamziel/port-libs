<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteDatabase;
use PortLibs\LibSqlite\SQLiteOverflowPage;
use PortLibs\LibSqlite\SQLitePointerMapEntry;
use PortLibs\LibSqlite\SQLiteRecord;
use PortLibs\LibSqlite\SQLiteTableLeafCell;
use PortLibs\LibSqlite\SQLiteTableLeafPage;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$pageSize = 512;
$emptyPage = str_repeat("\0", $pageSize);

$firstPage = str_repeat("\0", $pageSize);
$firstPage = substr_replace($firstPage, "SQLite format 3\0", 0, 16);
$firstPage = substr_replace($firstPage, pack('n', $pageSize), 16, 2);
$firstPage[18] = "\x01";
$firstPage[19] = "\x01";
$firstPage[20] = "\x00";
$firstPage[21] = "\x40";
$firstPage[22] = "\x20";
$firstPage[23] = "\x20";
$firstPage = substr_replace($firstPage, pack('N', 5), 28, 4);
$firstPage = substr_replace($firstPage, pack('N', 3), 52, 4);
$firstPage = substr_replace($firstPage, pack('N', 1), 56, 4);

$schemaPage = SQLiteTableLeafPage::assemble([
    SQLiteTableLeafCell::encode(1, SQLiteRecord::encode([
        'table',
        'wp_options',
        'wp_options',
        3,
        'CREATE TABLE wp_options(option_id integer primary key, option_name text, option_value text, autoload text)',
    ])),
], $pageSize, 100, $firstPage);

$oldValue = str_repeat('old-theme-mod-fragment:', 48) . 'done';
$oldPayload = SQLiteRecord::encode([null, 'theme_mods_twentyfive', $oldValue, 'yes']);
$oldLocalLength = SQLiteTableLeafCell::localPayloadLength(strlen($oldPayload), $pageSize);
$oldOverflowPayload = substr($oldPayload, $oldLocalLength);
$oldOverflowPages = SQLiteOverflowPage::encodeChainAtPages($oldOverflowPayload, [4, 5], $pageSize);

$pointerMapPage = str_repeat("\0", $pageSize);
foreach ([
    3 => [SQLitePointerMapEntry::ROOT_PAGE, 0],
    4 => [SQLitePointerMapEntry::FIRST_OVERFLOW_PAGE, 3],
    5 => [SQLitePointerMapEntry::OVERFLOW_PAGE, 4],
] as $pageNumber => [$type, $parentPageNumber]) {
    $pointerMapPage = substr_replace(
        $pointerMapPage,
        chr($type) . pack('N', $parentPageNumber),
        ($pageNumber - 3) * 5,
        5,
    );
}

$wpOptionsPage = SQLiteTableLeafPage::assemble([
    SQLiteTableLeafCell::encode(1, SQLiteRecord::encode([null, 'siteurl', 'https://example.test', 'yes'])),
    SQLiteTableLeafCell::encode(2, $oldPayload, $pageSize, 4),
], $pageSize);

$database = SQLiteDatabase::fromBytes(
    $schemaPage
    . $pointerMapPage
    . $wpOptionsPage
    . $oldOverflowPages[4]
    . $oldOverflowPages[5],
);

$newThemeMods = $argv[1] ?? str_repeat('new-theme-mod-fragment:', 86) . 'done';
$plan = $database->planKeyValueRowReplace('theme_mods_twentyfive', $newThemeMods, 'no');

$pages = [];
for ($pageNumber = 1; $pageNumber <= $plan->databasePageCount; $pageNumber++) {
    $pages[$pageNumber] = $pageNumber <= $database->pageCount()
        ? $database->page($pageNumber)
        : $emptyPage;
}
foreach ($plan->pageImages() as $pageNumber => $page) {
    $pages[$pageNumber] = $page;
}

$postDatabase = SQLiteDatabase::fromBytes(implode('', $pages));
$newOverflowEntries = [];
foreach ($plan->overflowPageNumbers as $pageNumber) {
    $newOverflowEntries[] = $postDatabase->pointerMapEntryForPage($pageNumber)->toArray();
}
$freedOverflowEntries = [];
foreach ($plan->obsoleteOverflowPageNumbers as $pageNumber) {
    $freedOverflowEntries[] = $postDatabase->pointerMapEntryForPage($pageNumber)->toArray();
}

echo json_encode([
    'applicationUse' => 'Preflight a large wp_options theme_mods replacement in an auto-vacuum SQLite database while marking obsolete overflow pages free and assigning pointer-map owners for the new overflow chain.',
    'plan' => [
        'updatedPageNumbers' => array_keys($plan->pageImages()),
        'overflowPageNumbers' => $plan->overflowPageNumbers,
        'obsoleteOverflowPageNumbers' => $plan->obsoleteOverflowPageNumbers,
        'databasePageCount' => $plan->databasePageCount,
    ],
    'freedOverflowPointerMapEntries' => $freedOverflowEntries,
    'newOverflowPointerMapEntries' => $newOverflowEntries,
    'replacedOption' => $postDatabase->tableRowByRowIdByName('wp_options', 2)?->values(),
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";

<?php

declare(strict_types=1);

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
$firstPage[20] = "\x00";
$firstPage[21] = "\x40";
$firstPage[22] = "\x20";
$firstPage[23] = "\x20";
$firstPage = substr_replace($firstPage, pack('N', 7), 28, 4);
$firstPage = substr_replace($firstPage, pack('N', 3), 52, 4);
$firstPage = substr_replace($firstPage, pack('N', 1), 56, 4);
$firstPage = substr_replace($firstPage, pack('N', 1), 64, 4);

$schemaPage = SQLiteTableLeafPage::assemble([
    SQLiteTableLeafCell::encode(1, SQLiteRecord::encode([
        'table',
        'wp_options',
        'wp_options',
        3,
        'CREATE TABLE wp_options(option_id integer primary key, option_name text, option_value text, autoload text)',
    ])),
], $pageSize, 100, $firstPage);

$pointerMapPage = str_repeat("\0", $pageSize);
foreach ([
    3 => [SQLitePointerMapEntry::ROOT_PAGE, 0],
    4 => [SQLitePointerMapEntry::BTREE_PAGE, 3],
    5 => [SQLitePointerMapEntry::FIRST_OVERFLOW_PAGE, 4],
    6 => [SQLitePointerMapEntry::OVERFLOW_PAGE, 5],
    7 => [SQLitePointerMapEntry::FREE_PAGE, 0],
] as $pageNumber => [$type, $parentPageNumber]) {
    $pointerMapPage = substr_replace(
        $pointerMapPage,
        chr($type) . pack('N', $parentPageNumber),
        5 * ($pageNumber - 3),
        5,
    );
}

$wpOptionsPage = SQLiteTableLeafPage::assemble([
    SQLiteTableLeafCell::encode(1, SQLiteRecord::encode([null, 'siteurl', 'https://example.test', 'yes'])),
], $pageSize);
$childPage = SQLiteTableLeafPage::assemble([
    SQLiteTableLeafCell::encode(2, SQLiteRecord::encode([null, 'blogname', 'Ported SQLite', 'yes'])),
], $pageSize);
$firstOverflowPage = substr_replace(str_repeat("\0", $pageSize), pack('N', 6) . 'theme mod payload', 0, 21);
$secondOverflowPage = substr_replace(str_repeat("\0", $pageSize), pack('N', 0) . 'tail', 0, 8);

$database = SQLiteDatabase::fromBytes(
    $schemaPage
    . $pointerMapPage
    . $wpOptionsPage
    . $childPage
    . $firstOverflowPage
    . $secondOverflowPage
    . str_repeat("\0", $pageSize),
);

echo json_encode([
    'applicationUse' => 'Inspect SQLite auto-vacuum pointer-map metadata before wp_options repair planning so page moves, free pages, and overflow chains are not treated as ordinary b-tree pages.',
    'autoVacuum' => $database->isAutoVacuum(),
    'incrementalVacuum' => $database->isIncrementalVacuum(),
    'entriesPerPointerMapPage' => $database->pointerMapEntriesPerPage(),
    'pointerMapPages' => array_values(array_filter(
        range(1, $database->pageCount()),
        static fn (int $pageNumber): bool => $database->isPointerMapPage($pageNumber),
    )),
    'pointerMapEntries' => array_map(
        static fn (SQLitePointerMapEntry $entry): array => $entry->toArray(),
        $database->pointerMapEntries(),
    ),
    'wpOptions' => array_map(
        static fn ($option): array => $option->toArray(),
        $database->optionRows(),
    ),
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";

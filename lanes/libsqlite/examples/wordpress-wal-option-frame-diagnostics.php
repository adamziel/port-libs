<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteDatabase;
use PortLibs\LibSqlite\SQLiteRecord;
use PortLibs\LibSqlite\SQLiteSchemaRecord;
use PortLibs\LibSqlite\SQLiteTableLeafCell;
use PortLibs\LibSqlite\SQLiteTableLeafPage;
use PortLibs\LibSqlite\SQLiteWal;
use PortLibs\LibSqlite\SQLiteWalHeader;
use PortLibs\LibSqlite\SQLiteWordPressOption;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$pageSize = 512;
$salt1 = 0x10203040;
$salt2 = 0x50607080;

$makeFirstPage = static function (int $databaseSizePages) use ($pageSize): string {
    $page = str_repeat("\0", $pageSize);
    $page = substr_replace($page, "SQLite format 3\0", 0, 16);
    $page = substr_replace($page, pack('n', $pageSize), 16, 2);
    $page[18] = "\x01";
    $page[19] = "\x01";
    $page[20] = "\x00";
    $page[21] = "\x40";
    $page[22] = "\x20";
    $page[23] = "\x20";
    $page = substr_replace($page, pack('N', $databaseSizePages), 28, 4);
    $page = substr_replace($page, pack('N', 1), 56, 4);

    return $page;
};

$schemaPayload = SQLiteRecord::encode([
    'table',
    'wp_options',
    'wp_options',
    2,
    'CREATE TABLE wp_options(option_id integer primary key, option_name text, option_value text, autoload text)',
]);
$schemaCell = SQLiteTableLeafCell::encode(1, $schemaPayload, $pageSize);
$schemaPage = SQLiteTableLeafPage::assemble([$schemaCell], $pageSize, 100, $makeFirstPage(2));

$optionPayload = SQLiteRecord::encode([1, 'siteurl', 'https://example.test/from-wal', 'yes']);
$optionCell = SQLiteTableLeafCell::encode(1, $optionPayload, $pageSize);
$optionPage = SQLiteTableLeafPage::assemble([$optionCell], $pageSize);

$walBytes = pack('N*', SQLiteWalHeader::MAGIC_BIG_ENDIAN, 3007000, $pageSize, 0, $salt1, $salt2, 0, 0)
    . pack('N*', 1, 0, $salt1, $salt2, 0, 0) . $schemaPage
    . pack('N*', 2, 2, $salt1, $salt2, 0, 0) . $optionPage
    . pack('N*', 2, 0, $salt1, $salt2, 0, 0) . str_repeat('P', $pageSize);

$wal = SQLiteWal::parse($walBytes);
$database = SQLiteDatabase::fromBytes(implode('', $wal->pageImagesThroughLastCommit()));

echo json_encode([
    'wal' => $wal->toArray(),
    'schema' => array_map(
        static fn (SQLiteSchemaRecord $record): array => [
            'type' => $record->type,
            'name' => $record->name,
            'table_name' => $record->tableName,
            'root_page' => $record->rootPage,
            'sql' => $record->sql,
            'rowid' => $record->rowId,
        ],
        $database->schemaRecords(),
    ),
    'options' => array_map(
        static fn (SQLiteWordPressOption $option): array => $option->toArray(),
        $database->wordpressOptions(),
    ),
    'wordpressUse' => 'Read committed wp_options page images from a SQLite WAL fixture without the SQLite extension so repair/import tooling can inspect pending WordPress option writes before checkpointing.',
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n";

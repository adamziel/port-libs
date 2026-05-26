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

$baseOptionPayload = SQLiteRecord::encode([1, 'siteurl', 'https://example.test/from-base', 'yes']);
$baseOptionCell = SQLiteTableLeafCell::encode(1, $baseOptionPayload, $pageSize);
$baseOptionPage = SQLiteTableLeafPage::assemble([$baseOptionCell], $pageSize);

$siteUrlPayload = SQLiteRecord::encode([1, 'siteurl', 'https://example.test/from-wal', 'yes']);
$blogNamePayload = SQLiteRecord::encode([2, 'blogname', 'WAL imported site', 'yes']);
$siteUrlCell = SQLiteTableLeafCell::encode(1, $siteUrlPayload, $pageSize);
$blogNameCell = SQLiteTableLeafCell::encode(2, $blogNamePayload, $pageSize);
$optionPage = SQLiteTableLeafPage::assemble([$siteUrlCell, $blogNameCell], $pageSize);
$baseDatabaseBytes = $schemaPage . $baseOptionPage;

$walHeaderPrefix = pack('N*', SQLiteWalHeader::MAGIC_BIG_ENDIAN, 3007000, $pageSize, 0, $salt1, $salt2);
$checksumSeed = SQLiteWal::checksumPair($walHeaderPrefix, false);
$walBytes = $walHeaderPrefix . pack('N*', $checksumSeed[0], $checksumSeed[1]);
$appendFrame = static function (string $walBytes, array &$checksumSeed, int $pageNumber, int $commit, string $pageImage) use ($salt1, $salt2): string {
    $framePrefix = pack('N*', $pageNumber, $commit, $salt1, $salt2);
    $checksumSeed = SQLiteWal::checksumPair(substr($framePrefix, 0, 8) . $pageImage, false, $checksumSeed[0], $checksumSeed[1]);

    return $walBytes . $framePrefix . pack('N*', $checksumSeed[0], $checksumSeed[1]) . $pageImage;
};

$walBytes = $appendFrame($walBytes, $checksumSeed, 1, 0, $schemaPage);
$walBytes = $appendFrame($walBytes, $checksumSeed, 2, 2, $optionPage);
$walBytes = $appendFrame($walBytes, $checksumSeed, 2, 0, str_repeat('P', $pageSize));

$wal = SQLiteWal::parse($walBytes, null, true);
$database = SQLiteDatabase::fromBytes($wal->checkpointDatabaseImage($baseDatabaseBytes));
$readerPageMap = $wal->readerPageMap($baseDatabaseBytes);
$readerOptionPage = $wal->readerPageImage($baseDatabaseBytes, 2);
$checkpointPlan = $wal->checkpointPlan($baseDatabaseBytes);

echo json_encode([
    'wal' => $wal->toArray(),
    'committedTransactions' => $wal->committedTransactions(),
    'uncommittedFrameCount' => $wal->uncommittedFrameCount(),
    'checkpointPlan' => $checkpointPlan,
    'readerPageMap' => $readerPageMap,
    'readerOptionPage' => [
        'page_number' => $readerOptionPage['page_number'],
        'source' => $readerOptionPage['source'],
        'frame_index' => $readerOptionPage['frame_index'],
        'database_offset' => $readerOptionPage['database_offset'],
        'containsUncommittedTail' => str_contains($readerOptionPage['image'], 'P'),
    ],
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
    'checkpointImageBytes' => strlen($wal->checkpointDatabaseImage($baseDatabaseBytes)),
    'wordpressUse' => 'Read committed wp_options page images from a SQLite WAL fixture without the SQLite extension so repair/import tooling can inspect reader-visible WordPress option writes while ignoring uncommitted WAL tail frames.',
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n";

<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteRecord;
use PortLibs\LibSqlite\SQLiteDatabase;
use PortLibs\LibSqlite\SQLiteSchemaRecord;
use PortLibs\LibSqlite\SQLiteTableLeafCell;
use PortLibs\LibSqlite\SQLiteTableLeafPage;
use PortLibs\LibSqlite\SQLiteWalHeader;
use PortLibs\LibSqlite\SQLiteWalOpenView;
use PortLibs\LibSqlite\SQLiteOptionRow;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$pageSize = 512;
$salt1 = 0x51515151;
$salt2 = 0x62626262;

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
$schemaPage = SQLiteTableLeafPage::assemble([
    SQLiteTableLeafCell::encode(1, $schemaPayload, $pageSize),
], $pageSize, 100, $makeFirstPage(2));

$baseOptionPage = SQLiteTableLeafPage::assemble([
    SQLiteTableLeafCell::encode(1, SQLiteRecord::encode([1, 'siteurl', 'https://example.test/base', 'yes']), $pageSize),
], $pageSize);
$walOptionPage = SQLiteTableLeafPage::assemble([
    SQLiteTableLeafCell::encode(1, SQLiteRecord::encode([1, 'siteurl', 'https://example.test/from-wal', 'yes']), $pageSize),
    SQLiteTableLeafCell::encode(2, SQLiteRecord::encode([2, 'blog_public', '1', 'yes']), $pageSize),
], $pageSize);
$draftOptionPage = SQLiteTableLeafPage::assemble([
    SQLiteTableLeafCell::encode(1, SQLiteRecord::encode([1, 'siteurl', 'https://example.test/draft', 'yes']), $pageSize),
], $pageSize);

$databaseBytes = $schemaPage . $baseOptionPage;
$walHeader = pack('N*', SQLiteWalHeader::MAGIC_BIG_ENDIAN, 3007000, $pageSize, 19, $salt1, $salt2, 0, 0);
$walBytes = $walHeader
    . pack('N*', 2, 2, $salt1, $salt2, 0, 0) . $walOptionPage
    . pack('N*', 2, 0, $salt1, $salt2, 0, 0) . $draftOptionPage;

$view = SQLiteWalOpenView::fromBytes($databaseBytes, $walBytes);
$effectiveDatabase = SQLiteDatabase::fromBytes($view->databaseImage());
$checkpoint = $view->checkpointResult('passive');
$databaseOnly = SQLiteWalOpenView::fromBytes($databaseBytes);

echo json_encode([
    'openView' => $view->toArray(),
    'databaseOnly' => $databaseOnly->toArray(),
    'checkpoint' => [
        'reason' => $checkpoint['reason'],
        'wal_action' => $checkpoint['wal_action'],
        'checkpointed_frame_count' => $checkpoint['checkpointed_frame_count'],
        'uncommitted_frame_count' => $checkpoint['uncommitted_frame_count'],
        'containsCommittedSiteUrl' => str_contains($checkpoint['database_bytes'], 'from-wal'),
        'containsDraftSiteUrl' => str_contains($checkpoint['database_bytes'], 'draft'),
    ],
    'schema' => array_map(
        static fn (SQLiteSchemaRecord $record): array => [
            'type' => $record->type,
            'name' => $record->name,
            'table_name' => $record->tableName,
            'root_page' => $record->rootPage,
        ],
        $effectiveDatabase->schemaRecords(),
    ),
    'options' => array_map(
        static fn (SQLiteOptionRow $option): array => $option->toArray(),
        $effectiveDatabase->optionRows(),
    ),
    'applicationUse' => 'Open a copied wp_options database with its sidecar -wal bytes in pure PHP, read committed option rows through the WAL overlay, preserve uncommitted draft frames, and preview checkpoint output before import or repair tooling accepts the database image.',
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n";

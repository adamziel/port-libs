<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteDatabase;
use PortLibs\LibSqlite\SQLiteRecord;
use PortLibs\LibSqlite\SQLiteTableLeafCell;
use PortLibs\LibSqlite\SQLiteTableLeafPage;
use PortLibs\LibSqlite\SQLiteWordPressOption;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$pageSize = 512;
$textEncoding = 2;

$firstPage = str_repeat("\0", $pageSize);
$firstPage = substr_replace($firstPage, "SQLite format 3\0", 0, 16);
$firstPage = substr_replace($firstPage, pack('n', $pageSize), 16, 2);
$firstPage[18] = "\x01";
$firstPage[19] = "\x01";
$firstPage[20] = "\x00";
$firstPage[21] = "\x40";
$firstPage[22] = "\x20";
$firstPage[23] = "\x20";
$firstPage = substr_replace($firstPage, pack('N', 2), 28, 4);
$firstPage = substr_replace($firstPage, pack('N', $textEncoding), 56, 4);

$schemaPage = SQLiteTableLeafPage::assemble([
    SQLiteTableLeafCell::encode(1, SQLiteRecord::encode([
        'table',
        'wp_options',
        'wp_options',
        2,
        'CREATE TABLE wp_options(option_id integer primary key, option_name text, option_value text, autoload text)',
    ], $textEncoding)),
], $pageSize, 100, $firstPage);
$tablePage = SQLiteTableLeafPage::assemble([
    SQLiteTableLeafCell::encode(1, SQLiteRecord::encode([null, 'siteurl', 'https://example.test', 'yes'], $textEncoding)),
], $pageSize);

$database = SQLiteDatabase::fromBytes($schemaPage . $tablePage);
$optionValue = $argv[1] ?? 'Ported ' . "\u{1234}" . ' option';
$plan = $database->planWordPressOptionInsert(2, 'blogdescription', $optionValue, 'yes');

$pages = [
    1 => $database->page(1),
    2 => $database->page(2),
];
foreach ($plan->pageImages() as $pageNumber => $page) {
    $pages[$pageNumber] = $page;
}

$postDatabase = SQLiteDatabase::fromBytes(implode('', $pages));
$options = array_map(
    static fn (SQLiteWordPressOption $option): array => $option->toArray(),
    $postDatabase->wordpressOptions(),
);

echo json_encode([
    'wordpressUse' => 'Plan a bounded generated wp_options row insert in a UTF-16LE SQLite image without the SQLite extension.',
    'textEncoding' => $postDatabase->header->textEncoding,
    'plan' => $plan->toArray(),
    'updatedPageNumbers' => array_keys($plan->pageImages()),
    'options' => $options,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";

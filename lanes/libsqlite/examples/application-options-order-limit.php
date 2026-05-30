<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteDatabase;
use PortLibs\LibSqlite\SQLiteRecord;
use PortLibs\LibSqlite\SQLiteSelectResult;
use PortLibs\LibSqlite\SQLiteTableLeafCell;
use PortLibs\LibSqlite\SQLiteTableLeafPage;
use PortLibs\LibSqlite\SQLiteKeyValueRow;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$makeFirstPage = static function (int $pageSize = 512, int $databaseSizePages = 2): string {
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

$cell = static fn (int $rowId, array $values): string => SQLiteTableLeafCell::encode($rowId, SQLiteRecord::encode($values));

$schemaPage = SQLiteTableLeafPage::assemble([
    $cell(1, [
        'table',
        'wp_options',
        'wp_options',
        2,
        'CREATE TABLE wp_options(option_id integer primary key, option_name text, option_value text, autoload text)',
    ]),
], 512, 100, $makeFirstPage());
$optionPage = SQLiteTableLeafPage::assemble([
    $cell(1, [null, 'siteurl', 'https://example.test', 'yes']),
    $cell(2, [null, 'rewrite_rules', 'a:0:{}', 'no']),
    $cell(3, [null, 'blogname', 'Ported SQLite', 'yes']),
    $cell(4, [null, 'template', 'twentytwentysix', null]),
]);

$database = SQLiteDatabase::fromBytes($schemaPage . $optionPage);
$options = array_map(
    static fn (SQLiteKeyValueRow $option): array => $option->toArray(),
    $database->keyValueRowsOrdered('option_name', false, 2, 1),
);
$resultRows = [
    ['autoload' => 'yes', 'option_name' => 'siteurl', 'bytes' => 20],
    ['autoload' => 'yes', 'option_name' => 'home', 'bytes' => 20],
    ['autoload' => 'yes', 'option_name' => 'home', 'bytes' => 20],
    ['autoload' => 'no', 'option_name' => '_transient_feed', 'bytes' => 12],
    ['autoload' => 'no', 'option_name' => 'empty_cache_key', 'bytes' => 0],
    ['autoload' => null, 'option_name' => 'orphaned', 'bytes' => null],
];
$selectPreview = SQLiteSelectResult::execute(
    $resultRows,
    ['autoload', 'option_name'],
    [
        ['column' => 'autoload', 'direction' => 'DESC'],
        ['column' => 'option_name'],
    ],
    3,
    1
);

echo json_encode([
    'plannerBehavior' => 'Decoded wp_options rows are sorted by option_name before OFFSET/LIMIT is applied, and bounded SELECT result previews can apply DISTINCT, multi-term ORDER BY, LIMIT, and OFFSET before local Application SQLite imports.',
    'orderBy' => 'option_name ASC',
    'limit' => 2,
    'offset' => 1,
    'options' => $options,
    'distinctOrderLimitPreview' => $selectPreview,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";

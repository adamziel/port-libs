<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteDatabase;
use PortLibs\LibSqlite\SQLiteRecord;
use PortLibs\LibSqlite\SQLiteTableLeafCell;
use PortLibs\LibSqlite\SQLiteTableLeafPage;
use PortLibs\LibSqlite\SQLiteVarint;
use PortLibs\LibSqlite\SQLiteOptionRow;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$pageSize = 512;
$optionName = $argv[1] ?? 'blogname';
$replacementValue = $argv[2] ?? 'Fixed Site';

$firstPage = str_repeat("\0", $pageSize);
$firstPage = substr_replace($firstPage, "SQLite format 3\0", 0, 16);
$firstPage = substr_replace($firstPage, pack('n', $pageSize), 16, 2);
$firstPage[18] = "\x01";
$firstPage[19] = "\x01";
$firstPage[20] = "\x00";
$firstPage[21] = "\x40";
$firstPage[22] = "\x20";
$firstPage[23] = "\x20";
$firstPage = substr_replace($firstPage, pack('N', 4), 28, 4);
$firstPage = substr_replace($firstPage, pack('N', 1), 56, 4);

$tableInteriorPage = static function (array $cells, int $rightMostPage) use ($pageSize): string {
    $page = str_repeat("\0", $pageSize);
    $cellCount = count($cells);
    $offset = $pageSize;
    $pointers = [];

    foreach ($cells as [$leftChildPage, $key]) {
        $cell = pack('N', $leftChildPage) . SQLiteVarint::encode($key);
        $offset -= strlen($cell);
        if ($offset < 12 + ($cellCount * 2)) {
            throw new RuntimeException('Fixture table interior page has overlapping cells');
        }
        $page = substr_replace($page, $cell, $offset, strlen($cell));
        $pointers[] = $offset;
    }

    $page[0] = "\x05";
    $page = substr_replace($page, pack('n', 0), 1, 2);
    $page = substr_replace($page, pack('n', $cellCount), 3, 2);
    $page = substr_replace($page, pack('n', $cellCount === 0 ? $pageSize : min($pointers)), 5, 2);
    $page[7] = "\x00";
    $page = substr_replace($page, pack('N', $rightMostPage), 8, 4);

    foreach ($pointers as $index => $pointer) {
        $page = substr_replace($page, pack('n', $pointer), 12 + ($index * 2), 2);
    }

    return $page;
};

$schemaPage = SQLiteTableLeafPage::assemble([
    SQLiteTableLeafCell::encode(1, SQLiteRecord::encode([
        'table',
        'wp_options',
        'wp_options',
        2,
        'CREATE TABLE wp_options(option_id integer primary key, option_name text, option_value text, autoload text)',
    ])),
], $pageSize, 100, $firstPage);

$tableRootPage = $tableInteriorPage([[3, 2]], 4);
$leftTableLeafPage = SQLiteTableLeafPage::assemble([
    SQLiteTableLeafCell::encode(1, SQLiteRecord::encode([null, 'siteurl', 'https://example.test', 'yes'])),
    SQLiteTableLeafCell::encode(2, SQLiteRecord::encode([null, 'home', 'https://example.test/blog', 'yes'])),
], $pageSize);
$rightTableLeafPage = SQLiteTableLeafPage::assemble([
    SQLiteTableLeafCell::encode(3, SQLiteRecord::encode([null, 'blogname', 'Stale Site', 'yes'])),
    SQLiteTableLeafCell::encode(4, SQLiteRecord::encode([null, 'template', 'twentytwentyfive', 'yes'])),
], $pageSize);

$database = SQLiteDatabase::fromBytes(
    $schemaPage
    . $tableRootPage
    . $leftTableLeafPage
    . $rightTableLeafPage,
);

$plan = $database->planOptionRowReplace($optionName, $replacementValue, 'no');

$pages = [];
for ($pageNumber = 1; $pageNumber <= $plan->databasePageCount; $pageNumber++) {
    $pages[$pageNumber] = $database->page($pageNumber);
}
foreach ($plan->pageImages() as $pageNumber => $page) {
    $pages[$pageNumber] = $page;
}

$postDatabase = SQLiteDatabase::fromBytes(implode('', $pages));
$options = array_map(
    static fn (SQLiteOptionRow $option): array => $option->toArray(),
    $postDatabase->optionRows(),
);

echo json_encode([
    'applicationUse' => 'Plan a wp_options replacement inside a table leaf below an interior table root, without the SQLite extension.',
    'plan' => $plan->toArray(),
    'tableRootPageType' => $postDatabase->pageHeader(2)->pageType,
    'updatedPageNumbers' => array_keys($plan->pageImages()),
    'options' => $options,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";

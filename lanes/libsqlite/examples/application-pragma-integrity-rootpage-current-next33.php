<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\LibSqlite\SQLitePointerMapEntry;
use PortLibs\LibSqlite\SQLitePragmaIntegrityCheck;
use PortLibs\LibSqlite\SQLiteRecord;
use PortLibs\LibSqlite\SQLiteTableLeafCell;
use PortLibs\LibSqlite\SQLiteTableLeafPage;

$pageSize = 1024;

$headerPage = static function (int $pageCount, int $largestRootPage) use ($pageSize): string {
    $page = str_repeat("\0", $pageSize);
    $page = substr_replace($page, "SQLite format 3\0", 0, 16);
    $page = substr_replace($page, pack('n', $pageSize), 16, 2);
    $page[18] = "\x01";
    $page[19] = "\x01";
    $page = substr_replace($page, pack('N', $pageCount), 28, 4);
    $page = substr_replace($page, pack('N', $largestRootPage), 52, 4);
    $page = substr_replace($page, pack('N', 1), 56, 4);

    return $page;
};

$schemaCell = static fn (array $values, int $rowId): string => SQLiteTableLeafCell::encode($rowId, SQLiteRecord::encode($values));

$putPointerMapEntry = static function (string $page, int $pageNumber, int $type, int $parent) use ($pageSize): string {
    $offset = 5 * ($pageNumber - 3);

    return substr_replace($page, chr($type) . pack('N', $parent), $offset, 5);
};

$schemaRows = [
    ['table', 'wp_options', 'wp_options', 4, 'CREATE TABLE wp_options(option_id integer primary key, option_name text, option_value text, autoload text)'],
    ['index', 'wp_options_name', 'wp_options', 5, 'CREATE UNIQUE INDEX wp_options_name ON wp_options(option_name)'],
    ['view', 'wp_autoloaded_options', 'wp_autoloaded_options', 0, "CREATE VIEW wp_autoloaded_options AS SELECT * FROM wp_options WHERE autoload = 'yes'"],
];

$build = static function (array $pointerEntries, int $largestRootPage = 5) use ($headerPage, $pageSize, $putPointerMapEntry, $schemaCell, $schemaRows): string {
    $pointerMap = str_repeat("\0", $pageSize);
    foreach ($pointerEntries as $entry) {
        $pointerMap = $putPointerMapEntry($pointerMap, $entry[0], $entry[1], $entry[2]);
    }

    return implode('', [
        SQLiteTableLeafPage::assemble(
            array_map(static fn (array $row, int $index): string => $schemaCell($row, $index + 1), $schemaRows, array_keys($schemaRows)),
            $pageSize,
            100,
            $headerPage(5, $largestRootPage),
        ),
        $pointerMap,
        SQLiteTableLeafPage::assemble([], $pageSize),
        SQLiteTableLeafPage::assemble([], $pageSize),
        SQLiteTableLeafPage::assemble([], $pageSize),
    ]);
};

$valid = SQLitePragmaIntegrityCheck::execute('PRAGMA integrity_check', $build([
    [3, SQLitePointerMapEntry::BTREE_PAGE, 4],
    [4, SQLitePointerMapEntry::ROOT_PAGE, 0],
    [5, SQLitePointerMapEntry::ROOT_PAGE, 0],
]));
$stalePointerMap = SQLitePragmaIntegrityCheck::execute('PRAGMA integrity_check', $build([
    [3, SQLitePointerMapEntry::ROOT_PAGE, 0],
    [4, SQLitePointerMapEntry::ROOT_PAGE, 0],
    [5, SQLitePointerMapEntry::ROOT_PAGE, 0],
]));

echo json_encode([
    'scenario' => 'copied wp_options PRAGMA integrity_check rootpage current metadata',
    'valid' => [
        'rows' => $valid['rows'],
        'errors' => $valid['errors'],
    ],
    'stalePointerMapRoot' => [
        'firstError' => $stalePointerMap['errors'][0] ?? 'ok',
    ],
    'dependencyTags' => [
        'sqlite-pragma-integrity-check',
        'sqlite-schema-rootpage-current',
        'sqlite-auto-vacuum-pointer-map',
    ],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";

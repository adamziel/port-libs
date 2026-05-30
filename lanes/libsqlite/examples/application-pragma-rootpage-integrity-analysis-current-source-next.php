<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/SQLiteAffinityComparison.php';
require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\LibSqlite\SQLitePointerMapEntry;
use PortLibs\LibSqlite\SQLitePragmaRootpageIntegrityAnalysisCurrentSourceNext;
use PortLibs\LibSqlite\SQLiteRecord;
use PortLibs\LibSqlite\SQLiteIndexLeafPage;
use PortLibs\LibSqlite\SQLiteTableLeafCell;
use PortLibs\LibSqlite\SQLiteTableLeafPage;

$pageSize = 1024;

$headerPage = static function (int $pageCount, int $largestRootPage): string {
    $page = str_repeat("\0", 1024);
    $page = substr_replace($page, "SQLite format 3\0", 0, 16);
    $page = substr_replace($page, pack('n', 1024), 16, 2);
    $page[18] = "\x01";
    $page[19] = "\x01";
    $page = substr_replace($page, pack('N', $pageCount), 28, 4);
    $page = substr_replace($page, pack('N', $largestRootPage), 52, 4);
    $page = substr_replace($page, pack('N', 1), 56, 4);

    return $page;
};

$schemaCell = static fn (array $values, int $rowId): string => SQLiteTableLeafCell::encode($rowId, SQLiteRecord::encode($values));
$putPointerMapEntry = static function (string $page, int $pageNumber, int $type, int $parent): string {
    $offset = 5 * ($pageNumber - 3);

    return substr_replace($page, chr($type) . pack('N', $parent), $offset, 5);
};

$build = static function (array $schemaRows, array $pointerEntries, int $largestRootPage = 5) use ($headerPage, $schemaCell, $putPointerMapEntry, $pageSize): string {
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
        SQLiteIndexLeafPage::assemble([], $pageSize),
    ]);
};

$schemaRows = [
    ['table', 'wp_options', 'wp_options', 4, 'CREATE TABLE wp_options(option_id integer primary key, option_name text, option_value text, autoload text)'],
    ['index', 'wp_options_name', 'wp_options', 5, 'CREATE UNIQUE INDEX wp_options_name ON wp_options(option_name)'],
    ['view', 'wp_autoloaded_options', 'wp_autoloaded_options', 0, "CREATE VIEW wp_autoloaded_options AS SELECT * FROM wp_options WHERE autoload = 'yes'"],
];

$valid = SQLitePragmaRootpageIntegrityAnalysisCurrentSourceNext::analyze($build($schemaRows, [
    [3, SQLitePointerMapEntry::BTREE_PAGE, 4],
    [4, SQLitePointerMapEntry::ROOT_PAGE, 0],
    [5, SQLitePointerMapEntry::ROOT_PAGE, 0],
]));

$duplicateRoot = SQLitePragmaRootpageIntegrityAnalysisCurrentSourceNext::analyze($build([
    $schemaRows[0],
    ['index', 'wp_options_alias', 'wp_options', 4, 'CREATE INDEX wp_options_alias ON wp_options(autoload)'],
], [
    [3, SQLitePointerMapEntry::BTREE_PAGE, 4],
    [4, SQLitePointerMapEntry::ROOT_PAGE, 0],
    [5, SQLitePointerMapEntry::BTREE_PAGE, 4],
], 4));

echo json_encode([
    'scenario' => 'copied wp_options PRAGMA rootpage integrity analysis current source next111',
    'valid' => [
        'status' => $valid['status'],
        'rootRecords' => $valid['current']['root_records'],
        'ready' => $valid['next']['ready'],
    ],
    'duplicateRoot' => [
        'status' => $duplicateRoot['status'],
        'problemCount' => $duplicateRoot['problem_count'],
        'blocking' => $duplicateRoot['next']['blocking'],
    ],
    'dependencyTags' => [
        'sqlite-pragma-integrity-check',
        'sqlite-schema-rootpage-current',
        'sqlite-auto-vacuum-pointer-map',
        'application-sqlite-import-repair',
    ],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";

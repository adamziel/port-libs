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

$putPointerMapEntry = static function (string $page, int $pageNumber, int $type, int $parent): string {
    return substr_replace($page, chr($type) . pack('N', $parent), 5 * ($pageNumber - 3), 5);
};

$schemaCell = static function (array $values, int $rowId): string {
    return SQLiteTableLeafCell::encode($rowId, SQLiteRecord::encode($values));
};

$rows = [
    ['table', 'wp_options', 'wp_options', 4, 'CREATE TABLE wp_options(option_id integer primary key, option_name text, option_value text, autoload text)'],
    ['index', 'wp_options_name', 'wp_options', 9, 'CREATE UNIQUE INDEX wp_options_name ON wp_options(option_name)'],
    ['table', 'wp_posts', 'wp_posts', 5, 'CREATE TABLE wp_posts(ID integer primary key, post_title text)'],
];

$pointerMap = str_repeat("\0", $pageSize);
$pointerMap = $putPointerMapEntry($pointerMap, 4, SQLitePointerMapEntry::ROOT_PAGE, 0);
$pointerMap = $putPointerMapEntry($pointerMap, 5, SQLitePointerMapEntry::ROOT_PAGE, 0);

$schemaPage = SQLiteTableLeafPage::assemble(
    array_map(static fn (array $row, int $index): string => $schemaCell($row, $index + 1), $rows, array_keys($rows)),
    $pageSize,
    100,
    $headerPage(5, 5),
);

$database = $schemaPage
    . $pointerMap
    . SQLiteTableLeafPage::assemble([], $pageSize)
    . SQLiteTableLeafPage::assemble([], $pageSize)
    . SQLiteTableLeafPage::assemble([], $pageSize);

$optionsScoped = SQLitePragmaIntegrityCheck::execute('PRAGMA integrity_check(wp_options)', $database);
$postsScoped = SQLitePragmaIntegrityCheck::execute('PRAGMA quick_check(wp_posts)', $database);
$global = SQLitePragmaIntegrityCheck::execute('PRAGMA integrity_check', $database);

$payload = [
    'scenario' => 'copied wp_options table-scoped PRAGMA integrity_check executor',
    'wp_options_scoped' => $optionsScoped['rows'],
    'wp_posts_quick_check' => $postsScoped['rows'],
    'global_first_error' => $global['errors'][0] ?? 'ok',
];

if (in_array('--self-test', $argv, true)) {
    if (($optionsScoped['errors'][0] ?? null) !== 'sqlite_schema index wp_options_name rootpage 9 is beyond the database image') {
        fwrite(STDERR, "Expected scoped wp_options rootpage error\n");
        exit(1);
    }
    if (($postsScoped['rows'][0]['quick_check'] ?? null) !== 'ok') {
        fwrite(STDERR, "Expected scoped wp_posts quick_check ok\n");
        exit(1);
    }
    if (($global['errors'][0] ?? null) !== 'sqlite_schema index wp_options_name rootpage 9 is beyond the database image') {
        fwrite(STDERR, "Expected global integrity rootpage error\n");
        exit(1);
    }

    echo "wordpress-pragma-integrity-check-table-execute self-test passed\n";
    exit(0);
}

echo json_encode($payload, JSON_PRETTY_PRINT) . "\n";

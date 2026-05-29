<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/SQLiteAffinityComparison.php';
require_once __DIR__ . '/../src/SQLiteSchemaRecord.php';
require_once __DIR__ . '/../src/SQLiteVarint.php';
require_once __DIR__ . '/../src/SQLiteBlobValue.php';
require_once __DIR__ . '/../src/SQLiteRecord.php';
require_once __DIR__ . '/../src/SQLiteHeader.php';
require_once __DIR__ . '/../src/SQLiteDatabase.php';
require_once __DIR__ . '/../src/SQLiteBTreePageHeader.php';
require_once __DIR__ . '/../src/SQLiteTableLeafCell.php';
require_once __DIR__ . '/../src/SQLiteTableLeafPage.php';
require_once __DIR__ . '/../src/SQLiteTableInteriorCell.php';
require_once __DIR__ . '/../src/SQLiteTableInteriorPage.php';
require_once __DIR__ . '/../src/SQLiteIndexCell.php';
require_once __DIR__ . '/../src/SQLiteIndexLeafPage.php';
require_once __DIR__ . '/../src/SQLiteIndexInteriorPage.php';
require_once __DIR__ . '/../src/SQLiteOverflowPage.php';
require_once __DIR__ . '/../src/SQLitePointerMapEntry.php';
require_once __DIR__ . '/../src/SQLitePragmaRowCursor.php';
require_once __DIR__ . '/../src/SQLitePragmaSchemaCatalog.php';
require_once __DIR__ . '/../src/SQLiteAttachedSchemaCatalog.php';
require_once __DIR__ . '/../src/SQLitePragmaIntegrityCheck.php';
require_once __DIR__ . '/../src/SQLitePragmaForeignKeyCheck.php';
require_once __DIR__ . '/../src/SQLitePragmaForeignKeyIntegrity.php';
require_once __DIR__ . '/../src/SQLitePragmaIndexXinfoIntegrityRootYield.php';
require_once __DIR__ . '/../src/SQLitePragmaRootpageIntegrityAnalysisCurrentSourceNext.php';
require_once __DIR__ . '/../src/SQLitePragmaRootpagePointerMapForeignKeyCurrentSourceNext.php';
require_once __DIR__ . '/../src/SQLitePragmaForeignKeyIndexRootCurrentSourceNext.php';
require_once __DIR__ . '/../src/SQLitePragmaForeignKeyIndexRootpageCurrentSourceNext.php';

use PortLibs\LibSqlite\SQLiteAttachedSchemaCatalog;
use PortLibs\LibSqlite\SQLiteIndexLeafPage;
use PortLibs\LibSqlite\SQLitePointerMapEntry;
use PortLibs\LibSqlite\SQLitePragmaForeignKeyIndexRootpageCurrentSourceNext;
use PortLibs\LibSqlite\SQLiteRecord;
use PortLibs\LibSqlite\SQLiteSchemaRecord;
use PortLibs\LibSqlite\SQLiteTableLeafCell;
use PortLibs\LibSqlite\SQLiteTableLeafPage;

$pageSize = 1024;
$record = static fn (string $type, string $name, string $table, int $root, ?string $sql = null, int $rowid = 1): SQLiteSchemaRecord => new SQLiteSchemaRecord($type, $name, $table, $root, $sql, $rowid);
$catalog = new SQLiteAttachedSchemaCatalog([
    $record('table', 'wp_options', 'wp_options', 4, 'CREATE TABLE wp_options(option_id INTEGER PRIMARY KEY, option_name TEXT, autoload TEXT)', 1),
    $record('table', 'wp_option_names', 'wp_option_names', 5, 'CREATE TABLE wp_option_names(name TEXT PRIMARY KEY)', 2),
    $record('table', 'wp_terms', 'wp_terms', 6, 'CREATE TABLE wp_terms(term_id INTEGER PRIMARY KEY)', 3),
    $record('table', 'wp_term_taxonomy', 'wp_term_taxonomy', 7, 'CREATE TABLE wp_term_taxonomy(term_taxonomy_id INTEGER PRIMARY KEY, term_id INTEGER)', 4),
    $record('index', 'wp_options_name', 'wp_options', 8, 'CREATE UNIQUE INDEX wp_options_name ON wp_options(option_name COLLATE nocase DESC, autoload)', 5),
]);

$headerPage = static function (int $largestRootPage) use ($pageSize): string {
    $page = str_repeat("\0", $pageSize);
    $page = substr_replace($page, "SQLite format 3\0", 0, 16);
    $page = substr_replace($page, pack('n', $pageSize), 16, 2);
    $page[18] = "\x01";
    $page[19] = "\x01";
    $page = substr_replace($page, pack('N', 8), 28, 4);
    $page = substr_replace($page, pack('N', $largestRootPage), 52, 4);
    $page = substr_replace($page, pack('N', 1), 56, 4);

    return $page;
};
$putPointer = static fn (string $page, int $pageNumber, int $type, int $parent): string => substr_replace($page, chr($type) . pack('N', $parent), 5 * ($pageNumber - 3), 5);
$schemaCell = static fn (array $values, int $rowid): string => SQLiteTableLeafCell::encode($rowid, SQLiteRecord::encode($values));
$database = static function (bool $clean) use ($headerPage, $putPointer, $schemaCell, $pageSize): string {
    $schemaRows = [
        ['table', 'wp_options', 'wp_options', 4, 'CREATE TABLE wp_options(option_id INTEGER PRIMARY KEY, option_name TEXT, autoload TEXT)'],
        ['table', 'wp_option_names', 'wp_option_names', 5, 'CREATE TABLE wp_option_names(name TEXT PRIMARY KEY)'],
        ['table', 'wp_terms', 'wp_terms', 6, 'CREATE TABLE wp_terms(term_id INTEGER PRIMARY KEY)'],
        ['table', 'wp_term_taxonomy', 'wp_term_taxonomy', 7, 'CREATE TABLE wp_term_taxonomy(term_taxonomy_id INTEGER PRIMARY KEY, term_id INTEGER)'],
        ['index', 'wp_options_name', 'wp_options', 8, 'CREATE UNIQUE INDEX wp_options_name ON wp_options(option_name COLLATE nocase DESC, autoload)'],
    ];
    $pages = [
        1 => SQLiteTableLeafPage::assemble(
            array_map(static fn (array $row, int $index): string => $schemaCell($row, $index + 1), $schemaRows, array_keys($schemaRows)),
            $pageSize,
            100,
            $headerPage($clean ? 8 : 7),
        ),
        2 => str_repeat("\0", $pageSize),
    ];
    foreach ([[3, 5, 4], [4, 1, 0], [5, 1, 0], [6, 1, 0], [7, $clean ? 1 : 5, $clean ? 0 : 6], [8, 1, 0]] as $entry) {
        $pages[2] = $putPointer($pages[2], $entry[0], $entry[1], $entry[2]);
    }
    for ($pageNumber = 3; $pageNumber <= 8; $pageNumber++) {
        $pages[$pageNumber] = $pageNumber === 8 ? SQLiteIndexLeafPage::assemble([], $pageSize) : SQLiteTableLeafPage::assemble([], $pageSize);
    }
    ksort($pages);

    return implode('', $pages);
};
$schemas = static function (bool $clean): array {
    return [
        'main' => [
            'tables' => [
                'wp_option_names' => [['rowid' => 1, 'name' => 'siteurl']],
                'wp_options' => $clean
                    ? [['rowid' => 1, 'option_id' => 1, 'option_name' => 'siteurl', 'autoload' => 'yes']]
                    : [
                        ['rowid' => 1, 'option_id' => 1, 'option_name' => 'siteurl', 'autoload' => 'yes'],
                        ['rowid' => 'option-2', 'option_id' => 2, 'option_name' => 'missing_plugin', 'autoload' => 'no'],
                    ],
                'wp_terms' => [['rowid' => 1, 'term_id' => 1]],
                'wp_term_taxonomy' => $clean
                    ? [['rowid' => 11, 'term_taxonomy_id' => 11, 'term_id' => 1]]
                    : [
                        ['rowid' => 11, 'term_taxonomy_id' => 11, 'term_id' => 1],
                        ['rowid' => 12, 'term_taxonomy_id' => 12, 'term_id' => 404],
                    ],
            ],
            'foreignKeys' => [
                ['id' => 1, 'table' => 'wp_options', 'parent' => 'wp_option_names', 'columns' => [['child' => 'option_name', 'parent' => 'name', 'affinity' => 'text', 'collation' => 'nocase']]],
                ['id' => 2, 'table' => 'wp_term_taxonomy', 'parent' => 'wp_terms', 'columns' => [['child' => 'term_id', 'parent' => 'term_id', 'affinity' => 'integer']]],
            ],
        ],
    ];
};

$result = SQLitePragmaForeignKeyIndexRootpageCurrentSourceNext::page(
    $catalog,
    $catalog,
    'PRAGMA main.index_xinfo(wp_options_name)',
    $database(false),
    $schemas(false),
    $database(true),
    $schemas(true),
    'PRAGMA foreign_key_check',
    0,
    6,
);
$second = SQLitePragmaForeignKeyIndexRootpageCurrentSourceNext::page(
    $catalog,
    $catalog,
    'PRAGMA main.index_xinfo(wp_options_name)',
    $database(false),
    $schemas(false),
    $database(true),
    $schemas(true),
    'PRAGMA foreign_key_check',
    6,
    144,
    'PRAGMA integrity_check',
    false,
    $result['next'],
);

if (($argv[1] ?? null) === '--self-test') {
    assert($result['next'] === ['source_id' => $result['source_id'], 'offset' => 6]);
    assert($second['status'] === 'ok');
    assert($second['delta']['cleared'] === true);
    assert($second['next_counts']['foreign_key_rootpage'] === 0);
    echo "wordpress-pragma-foreignkey-index-rootpage-current-source-next self-test passed\n";
    return;
}

echo json_encode([
    'scenario' => 'copied wp_options foreign_key_check plus single-index rootpage current-source next repair',
    'source_id' => $result['source_id'],
    'current' => $second['current'],
    'next_counts' => $second['next_counts'],
    'delta' => $second['delta'],
    'next_state' => $second['next_state'],
    'first_page' => [
        'count' => $result['count'],
        'next' => $result['next'],
        'rows' => $result['rows'],
    ],
    'second_page' => [
        'count' => $second['count'],
        'complete' => $second['complete'],
        'rows' => $second['rows'],
    ],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";

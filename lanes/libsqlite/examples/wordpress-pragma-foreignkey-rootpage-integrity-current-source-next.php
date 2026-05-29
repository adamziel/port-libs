<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/SQLiteAffinityComparison.php';
require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteAttachedSchemaCatalog;
use PortLibs\LibSqlite\SQLiteFreelistTrunkPage;
use PortLibs\LibSqlite\SQLiteIndexLeafPage;
use PortLibs\LibSqlite\SQLitePointerMapEntry;
use PortLibs\LibSqlite\SQLitePragmaForeignKeyRootpageIntegrityCurrentSourceNext;
use PortLibs\LibSqlite\SQLiteRecord;
use PortLibs\LibSqlite\SQLiteSchemaRecord;
use PortLibs\LibSqlite\SQLiteTableLeafCell;
use PortLibs\LibSqlite\SQLiteTableLeafPage;

$pageSize = 1024;
$header = static function (int $pageCount, int $largestRootPage, int $firstFreelist, int $freelistCount) use ($pageSize): string {
    $page = str_repeat("\0", $pageSize);
    $page = substr_replace($page, "SQLite format 3\0", 0, 16);
    $page = substr_replace($page, pack('n', $pageSize), 16, 2);
    $page[18] = "\x01";
    $page[19] = "\x01";
    $page = substr_replace($page, pack('N', $pageCount), 28, 4);
    $page = substr_replace($page, pack('N', $firstFreelist), 32, 4);
    $page = substr_replace($page, pack('N', $freelistCount), 36, 4);
    $page = substr_replace($page, pack('N', $largestRootPage), 52, 4);
    $page = substr_replace($page, pack('N', 1), 56, 4);

    return $page;
};
$schemaCell = static fn (array $row, int $rowid): string => SQLiteTableLeafCell::encode($rowid, SQLiteRecord::encode($row));
$putPointer = static fn (string $page, int $pageNumber, int $type, int $parent): string => substr_replace($page, chr($type) . pack('N', $parent), 5 * ($pageNumber - 3), 5);
$database = static function (array $schemaRows, array $pointerRows, int $firstFreelist, int $freelistCount, array $overrides = []) use ($header, $schemaCell, $putPointer, $pageSize): string {
    $pages = [
        1 => SQLiteTableLeafPage::assemble(
            array_map(static fn (array $row, int $index): string => $schemaCell($row, $index + 1), $schemaRows, array_keys($schemaRows)),
            $pageSize,
            100,
            $header(8, 6, $firstFreelist, $freelistCount),
        ),
        2 => str_repeat("\0", $pageSize),
    ];
    foreach ($pointerRows as $row) {
        $pages[2] = $putPointer($pages[2], $row[0], $row[1], $row[2]);
    }
    for ($page = 3; $page <= 8; $page++) {
        $pages[$page] = $overrides[$page] ?? SQLiteTableLeafPage::assemble([], $pageSize);
    }
    ksort($pages);

    return implode('', $pages);
};

$currentDatabase = $database([
    ['table', 'wp_options', 'wp_options', 4, 'CREATE TABLE wp_options(option_id integer primary key, option_name text, autoload text)'],
    ['index', 'wp_options_alias', 'wp_options', 4, 'CREATE INDEX wp_options_alias ON wp_options(autoload)'],
    ['table', 'wp_option_names', 'wp_option_names', 6, 'CREATE TABLE wp_option_names(name text primary key)'],
], [
    [3, SQLitePointerMapEntry::BTREE_PAGE, 4],
    [4, SQLitePointerMapEntry::BTREE_PAGE, 3],
    [6, SQLitePointerMapEntry::BTREE_PAGE, 4],
], 3, 2, [
    3 => SQLiteFreelistTrunkPage::assemble(null, [6], $pageSize),
]);
$nextDatabase = $database([
    ['table', 'wp_options', 'wp_options', 4, 'CREATE TABLE wp_options(option_id integer primary key, option_name text, autoload text)'],
    ['index', 'wp_options_alias', 'wp_options', 5, 'CREATE INDEX wp_options_alias ON wp_options(autoload)'],
    ['table', 'wp_option_names', 'wp_option_names', 6, 'CREATE TABLE wp_option_names(name text primary key)'],
], [
    [4, SQLitePointerMapEntry::ROOT_PAGE, 0],
    [5, SQLitePointerMapEntry::ROOT_PAGE, 0],
    [6, SQLitePointerMapEntry::ROOT_PAGE, 0],
], 0, 0, [
    5 => SQLiteIndexLeafPage::assemble([], $pageSize),
]);

$record = static fn (string $type, string $name, string $table, int $root, int $rowid): SQLiteSchemaRecord => new SQLiteSchemaRecord($type, $name, $table, $root, 'CREATE ' . strtoupper($type) . ' ' . $name, $rowid);
$catalog = new SQLiteAttachedSchemaCatalog([
    $record('table', 'wp_options', 'wp_options', 4, 1),
    $record('table', 'wp_option_names', 'wp_option_names', 6, 2),
]);
$schemas = [
    'main' => [
        'tables' => [
            'wp_option_names' => [['rowid' => 1, 'name' => 'siteurl']],
            'wp_options' => [
                ['rowid' => 1, 'option_name' => 'siteurl'],
                ['rowid' => 'orphaned-option', 'option_name' => 'missing_option'],
            ],
        ],
        'foreignKeys' => [
            ['id' => 147, 'table' => 'wp_options', 'parent' => 'wp_option_names', 'columns' => [
                ['child' => 'option_name', 'parent' => 'name', 'affinity' => 'text', 'collation' => 'nocase'],
            ]],
        ],
    ],
];
$cleanSchemas = $schemas;
$cleanSchemas['main']['tables']['wp_options'] = [['rowid' => 1, 'option_name' => 'siteurl']];

$result = SQLitePragmaForeignKeyRootpageIntegrityCurrentSourceNext::currentNextPage(
    $currentDatabase,
    $schemas,
    $catalog,
    $nextDatabase,
    $cleanSchemas,
    $catalog,
    'PRAGMA foreign_key_check(wp_options)',
    'PRAGMA integrity_check',
);

if (($argv[1] ?? null) === '--self-test') {
    assert($result['status'] === 'ok');
    assert($result['current']['integrity_root'] === 3);
    assert($result['current']['foreign_key_rootpage'] === 1);
    assert($result['next_counts']['total_blockers'] === 0);
    echo "wordpress-pragma-foreignkey-rootpage-integrity-current-source-next self-test passed\n";
    return;
}

echo json_encode([
    'scenario' => 'copied wp_options FK plus rootpage integrity current-source next147',
    'status' => $result['status'],
    'current' => $result['current'],
    'next_counts' => $result['next_counts'],
    'delta' => $result['delta'],
    'first_message' => $result['rows'][0]['message'] ?? null,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;

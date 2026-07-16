<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/SQLiteSchemaRecord.php';
require_once __DIR__ . '/../src/SQLiteVarint.php';
require_once __DIR__ . '/../src/SQLiteBlobValue.php';
require_once __DIR__ . '/../src/SQLiteRecord.php';
require_once __DIR__ . '/../src/SQLiteHeader.php';
require_once __DIR__ . '/../src/SQLiteDatabase.php';
require_once __DIR__ . '/../src/SQLiteBTreePageHeader.php';
require_once __DIR__ . '/../src/SQLiteTableLeafCell.php';
require_once __DIR__ . '/../src/SQLiteTableLeafPage.php';
require_once __DIR__ . '/../src/SQLitePragmaRowCursor.php';
require_once __DIR__ . '/../src/SQLitePragmaSchemaCatalog.php';
require_once __DIR__ . '/../src/SQLiteAttachedSchemaCatalog.php';
require_once __DIR__ . '/../src/SQLitePragmaIntegrityCheck.php';
require_once __DIR__ . '/../src/SQLitePointerMapEntry.php';
require_once __DIR__ . '/../src/SQLitePragmaIndexXinfoIntegrityRootYield.php';

use PortLibs\LibSqlite\SQLiteAttachedSchemaCatalog;
use PortLibs\LibSqlite\SQLitePointerMapEntry;
use PortLibs\LibSqlite\SQLitePragmaIndexXinfoIntegrityRootYield;
use PortLibs\LibSqlite\SQLiteRecord;
use PortLibs\LibSqlite\SQLiteSchemaRecord;
use PortLibs\LibSqlite\SQLiteTableLeafCell;
use PortLibs\LibSqlite\SQLiteTableLeafPage;

$record = static fn (string $type, string $name, string $table, ?int $root, ?string $sql = null, int $rowid = 1): SQLiteSchemaRecord => new SQLiteSchemaRecord(
    $type,
    $name,
    $table,
    $root,
    $sql,
    $rowid,
);

$catalog = new SQLiteAttachedSchemaCatalog([
    $record('table', 'wp_options', 'wp_options', 4, 'CREATE TABLE wp_options(option_id INTEGER PRIMARY KEY, option_name TEXT, option_value TEXT, autoload TEXT, updated_at TEXT)', 1),
    $record('index', 'wp_options_json_expr', 'wp_options', 6, "CREATE INDEX wp_options_json_expr ON wp_options(json_extract(option_value, '$.plugin'), lower(option_name) COLLATE nocase, updated_at DESC)", 2),
]);
$catalog->attach('archive', '/srv/wp/archive.sqlite', [
    $record('table', 'wp_options', 'wp_options', 9, 'CREATE TABLE wp_options(option_id INTEGER PRIMARY KEY, option_name TEXT, option_value TEXT, autoload TEXT)', 1),
    $record('index', 'wp_options_archive_expr', 'wp_options', 10, "CREATE INDEX wp_options_archive_expr ON wp_options(json_extract(option_value, '$.legacy'), option_name COLLATE rtrim DESC)", 2),
]);

$pageSize = 1024;
$header = str_repeat("\0", $pageSize);
$header = substr_replace($header, "SQLite format 3\0", 0, 16);
$header = substr_replace($header, pack('n', $pageSize), 16, 2);
$header[18] = "\x01";
$header[19] = "\x01";
$header = substr_replace($header, pack('N', 7), 28, 4);
$header = substr_replace($header, pack('N', 4), 52, 4);
$header = substr_replace($header, pack('N', 1), 56, 4);

$schemaRows = [
    ['table', 'wp_options', 'wp_options', 4, 'CREATE TABLE wp_options(option_id integer primary key, option_name text, option_value text, autoload text)'],
    ['index', 'wp_options_json_expr', 'wp_options', 6, "CREATE INDEX wp_options_json_expr ON wp_options(json_extract(option_value, '$.plugin'), option_name)"],
];
$schemaCell = static fn (array $values, int $rowid): string => SQLiteTableLeafCell::encode($rowid, SQLiteRecord::encode($values));
$pages = [
    1 => SQLiteTableLeafPage::assemble(
        array_map(static fn (array $row, int $index): string => $schemaCell($row, $index + 1), $schemaRows, array_keys($schemaRows)),
        $pageSize,
        100,
        $header,
    ),
    2 => str_repeat("\0", $pageSize),
];
$putPointerMapEntry = static function (string $page, int $pageNumber, int $type, int $parent): string {
    $offset = 5 * ($pageNumber - 3);

    return substr_replace($page, chr($type) . pack('N', $parent), $offset, 5);
};
foreach ([[3, SQLitePointerMapEntry::BTREE_PAGE, 4], [4, SQLitePointerMapEntry::ROOT_PAGE, 0], [5, SQLitePointerMapEntry::BTREE_PAGE, 4], [6, SQLitePointerMapEntry::BTREE_PAGE, 4], [7, SQLitePointerMapEntry::BTREE_PAGE, 6]] as $entry) {
    $pages[2] = $putPointerMapEntry($pages[2], $entry[0], $entry[1], $entry[2]);
}
for ($pageNumber = 3; $pageNumber <= 7; $pageNumber++) {
    $pages[$pageNumber] = SQLiteTableLeafPage::assemble([], $pageSize);
}
ksort($pages);
$database = implode('', $pages);

$first = SQLitePragmaIndexXinfoIntegrityRootYield::pageWithSourceCursor(
    $catalog,
    'PRAGMA main.index_xinfo(wp_options_json_expr)',
    $database,
    0,
    2,
    'PRAGMA quick_check',
);
$second = SQLitePragmaIndexXinfoIntegrityRootYield::pageWithSourceCursor(
    $catalog,
    'PRAGMA main.index_xinfo(wp_options_json_expr)',
    $database,
    2,
    3,
    'PRAGMA quick_check',
    false,
    $first['next'],
);

$result = [
    'scenario' => 'copied wp_options PRAGMA quick_check plus index_xinfo current-source pagination',
    'source_id' => $first['source_id'],
    'source' => $first['current_source'],
    'first_page' => [
        'count' => $first['count'],
        'next' => $first['next'],
        'rows' => $first['rows'],
    ],
    'second_page' => [
        'count' => $second['count'],
        'complete' => $second['complete'],
        'rows' => $second['rows'],
    ],
];

if (($argv[1] ?? null) === '--self-test') {
    assert($first['next'] !== null);
    assert($second['complete'] === true);
    assert($second['rows'][2]['kind'] === 'quick_check');
    assert($second['rows'][2]['message'] === 'largest root btree page 4 does not match sqlite_schema max rootpage 6');
    echo "application-pragma-quickcheck-index-xinfo-current-source-next103 self-test passed\n";
    return;
}

echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";

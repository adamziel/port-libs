<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\LibSqlite\SQLitePointerMapEntry;
use PortLibs\LibSqlite\SQLitePragmaIntegrityAutoindexPointerMapCurrentSourceYield;
use PortLibs\LibSqlite\SQLiteRecord;
use PortLibs\LibSqlite\SQLiteTableLeafCell;
use PortLibs\LibSqlite\SQLiteTableLeafPage;

$pageSize = 1024;
$pageCount = 12;

$header = str_repeat("\0", $pageSize);
$header = substr_replace($header, "SQLite format 3\0", 0, 16);
$header = substr_replace($header, pack('n', $pageSize), 16, 2);
$header[18] = "\x01";
$header[19] = "\x01";
$header = substr_replace($header, pack('N', $pageCount), 28, 4);
$header = substr_replace($header, pack('N', 9), 52, 4);
$header = substr_replace($header, pack('N', 1), 56, 4);

$putPointerMapEntry = static function (string $page, int $pageNumber, int $type, int $parent): string {
    return substr_replace($page, chr($type) . pack('N', $parent), 5 * ($pageNumber - 3), 5);
};

$pointerMap = str_repeat("\0", $pageSize);
for ($pageNumber = 3; $pageNumber <= $pageCount; $pageNumber++) {
    $isRoot = in_array($pageNumber, [4, 5, 6, 7], true);
    $pointerMap = $putPointerMapEntry($pointerMap, $pageNumber, $isRoot ? SQLitePointerMapEntry::ROOT_PAGE : SQLitePointerMapEntry::BTREE_PAGE, $isRoot ? 0 : 4);
}
$pointerMap = $putPointerMapEntry($pointerMap, 5, SQLitePointerMapEntry::BTREE_PAGE, 4);
$pointerMap = $putPointerMapEntry($pointerMap, 7, SQLitePointerMapEntry::ROOT_PAGE, 3);

$schemaSql = <<<'SQL'
CREATE TABLE wp_options(
    option_id INTEGER PRIMARY KEY,
    option_name TEXT NOT NULL UNIQUE,
    autoload TEXT NOT NULL,
    option_value TEXT,
    option_hash TEXT GENERATED ALWAYS AS (lower(option_name)) STORED UNIQUE,
    CONSTRAINT autoload_option UNIQUE(autoload, option_name)
)
SQL;

$schemaRows = [
    ['table', 'wp_options', 'wp_options', 4, $schemaSql],
    ['index', 'sqlite_autoindex_wp_options_1', 'wp_options', 5, null],
    ['index', 'sqlite_autoindex_wp_options_3', 'wp_options', 12, null],
    ['index', 'sqlite_autoindex_wp_options_5', 'wp_options', 8, null],
    ['index', 'sqlite_autoindex_wp_postmeta_1', 'wp_postmeta', 9, null],
];

$cells = [];
foreach ($schemaRows as $rowId => $values) {
    $cells[] = SQLiteTableLeafCell::encode($rowId + 1, SQLiteRecord::encode($values));
}

$pages = [
    1 => SQLiteTableLeafPage::assemble($cells, $pageSize, 100, $header),
    2 => $pointerMap,
];
for ($pageNumber = 3; $pageNumber <= $pageCount; $pageNumber++) {
    $pages[$pageNumber] = SQLiteTableLeafPage::assemble([], $pageSize);
}
ksort($pages);

$page = SQLitePragmaIntegrityAutoindexPointerMapCurrentSourceYield::page(
    implode('', $pages),
    '7b41e2554952baf690549d17891ca56b9a508ae3',
    'pragma-integrity-autoindex-pointermap-current-source-next89',
    0,
    89,
);

$payload = [
    'scenario' => 'application-pragma-integrity-autoindex-pointermap-current-source-next89',
    'status' => $page['status'],
    'current_source' => $page['current']['source'],
    'next_source' => $page['next']['source'],
    'total_rows' => $page['total'],
    'pointer_map_errors' => $page['current']['pointer_map_errors'],
    'missing_autoindexes' => $page['current']['missing_autoindexes'],
    'unexpected_autoindexes' => $page['current']['unexpected_autoindexes'],
    'orphan_autoindexes' => $page['current']['orphan_autoindexes'],
    'blocking' => $page['next']['blocking'],
    'root_pointer_map' => array_values(array_map(
        static fn (array $row): array => [
            'index' => $row['index'],
            'rootpage' => $row['rootpage'],
            'source' => $row['source'],
            'pointer_map_type' => $row['rootpage_pointer_map_type'],
            'pointer_map_parent' => $row['rootpage_pointer_map_parent'],
        ],
        $page['rows'],
    )),
];

if (($argv[1] ?? null) === '--self-test') {
    if ($payload['status'] !== 'blocked' || $payload['total_rows'] !== 5 || $payload['pointer_map_errors'] !== 2) {
        fwrite(STDERR, "application-pragma-integrity-autoindex-pointermap-current-source-next89 self-test failed\n");
        exit(1);
    }

    echo "application-pragma-integrity-autoindex-pointermap-current-source-next89 self-test passed\n";
    exit(0);
}

echo json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";

<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\LibSqlite\SQLitePointerMapEntry;
use PortLibs\LibSqlite\SQLitePragmaIntegrityCurrentNextYield;

$pageSize = 512;
$pageCount = 75;
$putPointerMapEntry = static function (string $page, int $pageNumber, int $type, int $parent): string {
    return substr_replace($page, chr($type) . pack('N', $parent), 5 * ($pageNumber - 3), 5);
};

$header = str_repeat("\0", $pageSize);
$header = substr_replace($header, "SQLite format 3\0", 0, 16);
$header = substr_replace($header, pack('n', $pageSize), 16, 2);
$header[18] = "\x01";
$header[19] = "\x01";
$header = substr_replace($header, pack('N', $pageCount), 28, 4);
$header = substr_replace($header, pack('N', 3), 52, 4);
$header = substr_replace($header, pack('N', 1), 56, 4);

$pointerMap = str_repeat("\0", $pageSize);
$pointerMap = $putPointerMapEntry($pointerMap, 3, SQLitePointerMapEntry::ROOT_PAGE, 0);
for ($pageNumber = 4; $pageNumber <= $pageCount; $pageNumber++) {
    $pointerMap = $putPointerMapEntry($pointerMap, $pageNumber, SQLitePointerMapEntry::BTREE_PAGE, 0);
}

$pages = [$header, $pointerMap];
for ($pageNumber = 3; $pageNumber <= $pageCount; $pageNumber++) {
    $pages[] = str_repeat("\0", $pageSize);
}
$database = implode('', $pages);

$optionRows = [];
for ($i = 1; $i <= 9; $i++) {
    $optionRows[] = ['rowid' => 'autoload-' . $i, 'option_name' => 'missing_' . $i];
}

$schemas = [
    'temp' => [
        'tables' => [
            'wp_option_names' => [['rowid' => 1, 'name' => 'siteurl']],
            'wp_options' => $optionRows,
        ],
        'foreignKeys' => [
            ['id' => 7, 'table' => 'wp_options', 'parent' => 'wp_option_names', 'columns' => [['child' => 'option_name', 'parent' => 'name', 'affinity' => 'text', 'collation' => 'nocase']]],
        ],
    ],
];

$page0 = SQLitePragmaIntegrityCurrentNextYield::pageForForeignKeyPragma(
    $database,
    $schemas,
    'PRAGMA temp.foreign_key_check(wp_options)',
    0,
    73,
);
$page1 = SQLitePragmaIntegrityCurrentNextYield::pageForForeignKeyPragma(
    $database,
    $schemas,
    'PRAGMA temp.foreign_key_check(wp_options)',
    73,
    73,
);

if (($argv[1] ?? null) === '--self-test') {
    if (
        $page0['status'] !== 'ok'
        || $page0['count'] !== 73
        || $page0['next_offset'] !== 73
        || $page1['count'] !== 8
        || $page1['rows'][7]['rowid'] !== 'autoload-9'
        || $page0['current']['pointer_map'] !== 72
        || $page0['current']['foreign_key'] !== 9
    ) {
        fwrite(STDERR, "application-pragma-integrity-foreign-key-current-next73 self-test failed\n");
        exit(1);
    }

    fwrite(STDOUT, "application-pragma-integrity-foreign-key-current-next73 self-test passed\n");
    exit(0);
}

echo json_encode([
    'scenario' => 'application temp wp_options targeted foreign_key_check current/next pagination',
    'page0' => [
        'status' => $page0['status'],
        'count' => $page0['count'],
        'total' => $page0['total'],
        'next_offset' => $page0['next_offset'],
        'current' => $page0['current'],
        'first_foreign_key_row' => $page0['rows'][72],
    ],
    'page1' => [
        'count' => $page1['count'],
        'complete' => $page1['complete'],
        'last_foreign_key_row' => $page1['rows'][7],
    ],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;

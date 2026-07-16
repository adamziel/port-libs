<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteFreelistTrunkPage;
use PortLibs\LibSqlite\SQLitePointerMapEntry;
use PortLibs\LibSqlite\SQLitePragmaIntegrityFreelistForeignKeyPreflight;

$pageSize = 512;
$header = str_repeat("\0", $pageSize);
$header = substr_replace($header, "SQLite format 3\0", 0, 16);
$header = substr_replace($header, pack('n', $pageSize), 16, 2);
$header[18] = "\x01";
$header[19] = "\x01";
$header = substr_replace($header, pack('N', 4), 28, 4);
$header = substr_replace($header, pack('N', 3), 32, 4);
$header = substr_replace($header, pack('N', 2), 36, 4);
$header = substr_replace($header, pack('N', 1), 52, 4);
$header = substr_replace($header, pack('N', 1), 56, 4);

$pointerMap = str_repeat("\0", $pageSize);
$pointerMap = substr_replace($pointerMap, chr(SQLitePointerMapEntry::FREE_PAGE) . pack('N', 0), 0, 5);
$pointerMap = substr_replace($pointerMap, chr(SQLitePointerMapEntry::FREE_PAGE) . pack('N', 0), 5, 5);

$database = implode('', [
    $header,
    $pointerMap,
    SQLiteFreelistTrunkPage::assemble(null, [4], $pageSize),
    str_repeat("\0", $pageSize),
]);

$schemas = [
    'main' => [
        'tables' => [
            'wp_posts' => [
                ['rowid' => 1, 'ID' => 1],
            ],
            'wp_postmeta' => [
                ['rowid' => 10, 'post_id' => 1],
                ['rowid' => 11, 'post_id' => 99],
            ],
        ],
        'foreignKeys' => [
            ['id' => 0, 'table' => 'wp_postmeta', 'parent' => 'wp_posts', 'columns' => ['post_id' => 'ID']],
        ],
    ],
];

$summary = SQLitePragmaIntegrityFreelistForeignKeyPreflight::plan('PRAGMA integrity_check', $database, $schemas);

if (($argv[1] ?? null) === '--self-test') {
    if ($summary['status'] !== 'blocked' || $summary['current']['integrity_errors'] !== 0 || $summary['current']['foreign_key_violations'] !== 1) {
        fwrite(STDERR, "application-pragma-integrity-freelist-foreignkey-current-next49 self-test failed\n");
        exit(1);
    }

    fwrite(STDOUT, "application-pragma-integrity-freelist-foreignkey-current-next49 self-test passed\n");
    exit(0);
}

echo json_encode([
    'pragma' => 'integrity_check + foreign_key_check',
    'status' => $summary['status'],
    'current' => $summary['current'],
    'next' => $summary['next'],
    'freelist' => $summary['freelist'],
    'foreign_key_rows' => $summary['foreign_keys']['rows'],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;

<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\LibSqlite\SQLitePragmaQuickcheckStatForeignKeyCurrentSourceYield;
use PortLibs\LibSqlite\SQLiteSchemaRecord;

$record = static fn (string $type, string $name, string $table, int $root, string $sql, int $rowid): SQLiteSchemaRecord => new SQLiteSchemaRecord($type, $name, $table, $root, $sql, $rowid);

$database = str_repeat("\0", 512);
$database = substr_replace($database, "SQLite format 3\0", 0, 16);
$database = substr_replace($database, pack('n', 512), 16, 2);
$database[18] = "\x01";
$database[19] = "\x01";
$database = substr_replace($database, pack('N', 1), 28, 4);
$database = substr_replace($database, pack('N', 1), 56, 4);

$records = [
    $record('table', 'wp_options', 'wp_options', 2, 'CREATE TABLE wp_options(option_id INTEGER PRIMARY KEY, option_name TEXT)', 1),
    $record('table', 'wp_option_names', 'wp_option_names', 3, 'CREATE TABLE wp_option_names(name TEXT PRIMARY KEY)', 2),
    $record('table', 'sqlite_stat1', 'sqlite_stat1', 4, 'CREATE TABLE sqlite_stat1(tbl,idx,stat)', 3),
];

$schemas = [
    'main' => [
        'tables' => [
            'wp_option_names' => [['rowid' => 1, 'name' => 'siteurl']],
            'wp_options' => [
                ['rowid' => 1, 'option_id' => 1, 'option_name' => 'siteurl'],
                ['rowid' => 2, 'option_id' => 2, 'option_name' => 'missing_autoload'],
            ],
        ],
        'foreignKeys' => [
            ['id' => 11, 'table' => 'wp_options', 'parent' => 'wp_option_names', 'columns' => [
                ['child' => 'option_name', 'parent' => 'name', 'affinity' => 'text', 'collation' => 'nocase'],
            ]],
        ],
    ],
];

$page = SQLitePragmaQuickcheckStatForeignKeyCurrentSourceYield::page($database, $schemas, $records);

if ($page['status'] !== 'blocked' || $page['current']['foreign_key_violations'] !== 1 || $page['current']['stat_rows'] !== 1) {
    fwrite(STDERR, "application pragma quickcheck/stat/FK smoke failed\n");
    exit(1);
}

echo json_encode([
    'status' => $page['status'],
    'quickcheck_errors' => $page['current']['quickcheck_errors'],
    'stat_rows' => $page['current']['stat_rows'],
    'foreign_key_violations' => $page['current']['foreign_key_violations'],
    'first_message' => $page['rows'][0]['message'],
    'last_message' => $page['rows'][1]['message'],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";

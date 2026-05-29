<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\LibSqlite\SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext;
use PortLibs\LibSqlite\SQLiteSchemaRecord;

$record = static fn (string $type, string $name, string $table, int $root, ?string $sql, int $rowid): SQLiteSchemaRecord => new SQLiteSchemaRecord($type, $name, $table, $root, $sql, $rowid);

$currentRecords = [
    $record('table', 'wp_sites', 'wp_sites', 2, "CREATE TABLE wp_sites(site_id INTEGER PRIMARY KEY, domain TEXT NOT NULL DEFAULT 'example.test')", 1),
    $record('table', 'wp_option_defaults', 'wp_option_defaults', 3, "CREATE TABLE wp_option_defaults(option_name TEXT PRIMARY KEY, option_value TEXT NOT NULL DEFAULT '')", 2),
    $record('index', 'wp_option_defaults_name_unique', 'wp_option_defaults', 4, 'CREATE UNIQUE INDEX wp_option_defaults_name_unique ON wp_option_defaults(option_name)', 3),
    $record('table', 'wp_imported_options', 'wp_imported_options', 5, "CREATE TABLE wp_imported_options(
        option_id INTEGER PRIMARY KEY,
        site_id INTEGER NOT NULL,
        option_name TEXT NOT NULL,
        option_value TEXT,
        fallback_name TEXT NOT NULL DEFAULT 'home',
        fallback_value TEXT DEFAULT NULL,
        FOREIGN KEY(site_id) REFERENCES wp_sites(site_id) ON DELETE SET DEFAULT,
        FOREIGN KEY(option_name) REFERENCES wp_option_defaults(option_name) ON UPDATE SET DEFAULT,
        FOREIGN KEY(fallback_name) REFERENCES wp_option_defaults(option_name) ON DELETE SET DEFAULT ON UPDATE CASCADE,
        FOREIGN KEY(fallback_value) REFERENCES wp_option_defaults(option_value) ON UPDATE SET DEFAULT
    )", 4),
];

$nextRecords = [
    $currentRecords[0],
    $currentRecords[1],
    $currentRecords[2],
    $record('table', 'wp_imported_options', 'wp_imported_options', 5, "CREATE TABLE wp_imported_options(
        option_id INTEGER PRIMARY KEY,
        site_id INTEGER NOT NULL DEFAULT 1,
        option_name TEXT NOT NULL DEFAULT 'home',
        option_value TEXT,
        fallback_name TEXT NOT NULL DEFAULT 'home',
        fallback_value TEXT DEFAULT NULL,
        FOREIGN KEY(site_id) REFERENCES wp_sites(site_id) ON DELETE SET DEFAULT,
        FOREIGN KEY(option_name) REFERENCES wp_option_defaults(option_name) ON UPDATE SET DEFAULT,
        FOREIGN KEY(fallback_name) REFERENCES wp_option_defaults(option_name) ON DELETE SET DEFAULT ON UPDATE CASCADE,
        FOREIGN KEY(fallback_value) REFERENCES wp_option_defaults(option_value) ON UPDATE SET DEFAULT
    )", 4),
];

$page = SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::page247(
    $currentRecords,
    $nextRecords,
    'PRAGMA main.index_xinfo(wp_option_defaults_name_unique)',
    'PRAGMA main.foreign_key_list(wp_imported_options)',
);

$summary = [
    'scenario' => 'wordpress-pragma-index-xinfo-foreignkey-set-default-current-source-next247',
    'wordpressUse' => 'Copied WordPress option imports can reject SET DEFAULT foreign-key actions that would write NULL into NOT NULL child columns until explicit non-NULL defaults are present.',
    'status' => $page['status'],
    'current_set_default_rows' => $page['current']['foreign_key_set_default']['rows'],
    'current_blockers' => $page['current']['foreign_key_set_default']['blocked'],
    'next_blockers' => $page['next_counts']['foreign_key_set_default']['blocked'],
    'set_default_repaired' => $page['delta']['foreign_key_set_default_repaired'],
    'source' => $page['current_source']['foreign_key_set_default_source'],
    'pragmas' => [
        'PRAGMA main.index_xinfo(wp_option_defaults_name_unique)',
        'PRAGMA main.foreign_key_list(wp_imported_options)',
        'PRAGMA main.table_info(wp_imported_options)',
    ],
];

if (($argv[1] ?? null) === '--self-test') {
    if (
        $summary['status'] !== 'ok'
        || $summary['current_set_default_rows'] !== 4
        || $summary['current_blockers'] !== 2
        || $summary['next_blockers'] !== 0
        || $summary['set_default_repaired'] !== true
    ) {
        fwrite(STDERR, "wordpress-pragma-index-xinfo-foreignkey-set-default-current-source-next247 self-test failed\n");
        exit(1);
    }

    fwrite(STDOUT, "wordpress-pragma-index-xinfo-foreignkey-set-default-current-source-next247 self-test passed\n");
    exit(0);
}

echo json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;

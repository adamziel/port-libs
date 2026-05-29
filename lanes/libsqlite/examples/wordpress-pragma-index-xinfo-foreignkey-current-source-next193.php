<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext;
use PortLibs\LibSqlite\SQLiteSchemaRecord;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$record = static fn (string $type, string $name, string $table, int $root, string $sql, int $rowid): SQLiteSchemaRecord => new SQLiteSchemaRecord($type, $name, $table, $root, $sql, $rowid);

$current = [
    $record('table', 'wp_option_groups', 'wp_option_groups', 4, 'CREATE TABLE wp_option_groups(site_id INTEGER NOT NULL, option_name TEXT COLLATE NOCASE NOT NULL, label TEXT, PRIMARY KEY(site_id, option_name))', 1),
    $record('table', 'wp_options_import', 'wp_options_import', 5, 'CREATE TABLE wp_options_import(import_id INTEGER PRIMARY KEY, site_id INTEGER NOT NULL, option_name TEXT COLLATE NOCASE NOT NULL, option_value TEXT, FOREIGN KEY(site_id, option_name) REFERENCES wp_option_groups(site_id, option_name))', 2),
    $record('index', 'wp_option_groups_name_site_unique', 'wp_option_groups', 6, 'CREATE UNIQUE INDEX wp_option_groups_name_site_unique ON wp_option_groups(option_name COLLATE NOCASE, site_id)', 3),
    $record('index', 'wp_options_import_fk_lookup', 'wp_options_import', 7, 'CREATE INDEX wp_options_import_fk_lookup ON wp_options_import(site_id, option_name COLLATE NOCASE)', 4),
];
$next = [
    $current[0],
    $current[1],
    $record('index', 'wp_option_groups_site_name_unique', 'wp_option_groups', 8, 'CREATE UNIQUE INDEX wp_option_groups_site_name_unique ON wp_option_groups(site_id, option_name COLLATE NOCASE)', 5),
    $current[3],
];
$tables = [
    'wp_option_groups' => [
        ['rowid' => 1, 'site_id' => 1, 'option_name' => 'active_plugins', 'label' => 'plugins'],
    ],
    'wp_options_import' => [
        ['rowid' => 10, 'import_id' => 10, 'site_id' => 1, 'option_name' => 'active_plugins', 'option_value' => 'a:0:{}'],
    ],
];

$page = SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::currentNextPageFromCatalog193(
    $current,
    $tables,
    $next,
    $tables,
    'PRAGMA index_xinfo(wp_option_groups_site_name_unique)',
);

$summary = [
    'scenario' => 'wordpress-pragma-index-xinfo-foreignkey-current-source-next193',
    'wordpressUse' => 'Copied wp_options imports can block parent-key repair when PRAGMA index_xinfo shows that a UNIQUE parent key has the right columns in the wrong order.',
    'current_order_mismatches' => $page['current']['foreign_key_parent_unique_order']['order_mismatch'],
    'next_order_mismatches' => $page['next_counts']['foreign_key_parent_unique_order']['order_mismatch'],
    'order_repaired' => $page['delta']['foreign_key_parent_unique_order_repaired'],
    'decorated_parent_key_reason' => $page['rows'][9]['rejected_parent_unique_reason'] ?? null,
    'pragmas' => [
        'PRAGMA index_xinfo(wp_option_groups_name_site_unique)',
        'PRAGMA foreign_key_list(wp_options_import)',
        'PRAGMA foreign_key_check',
    ],
];

if (in_array('--self-test', $argv, true)) {
    if (
        $page['status'] !== 'ok'
        || $summary['current_order_mismatches'] !== 1
        || $summary['next_order_mismatches'] !== 0
        || $summary['order_repaired'] !== true
        || $summary['decorated_parent_key_reason'] !== 'column_order_mismatch'
    ) {
        fwrite(STDERR, "wordpress-pragma-index-xinfo-foreignkey-current-source-next193 self-test failed\n");
        exit(1);
    }

    fwrite(STDOUT, "wordpress-pragma-index-xinfo-foreignkey-current-source-next193 self-test passed\n");
    exit(0);
}

echo json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;

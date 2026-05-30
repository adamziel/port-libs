<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext;
use PortLibs\LibSqlite\SQLiteSchemaRecord;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$record = static fn (string $type, string $name, string $table, int $root, ?string $sql, int $rowid): SQLiteSchemaRecord => new SQLiteSchemaRecord($type, $name, $table, $root, $sql, $rowid);

$records = [
    $record('table', 'wp_plugin_slugs', 'wp_plugin_slugs', 4, 'CREATE TABLE wp_plugin_slugs(slug TEXT, locale TEXT, active INTEGER)', 1),
    $record('table', 'wp_defaults', 'wp_defaults', 5, 'CREATE TABLE wp_defaults(default_name TEXT PRIMARY KEY, enabled INTEGER)', 2),
    $record('table', 'wp_options', 'wp_options', 6, 'CREATE TABLE wp_options(option_id INTEGER PRIMARY KEY, plugin_slug TEXT, locale TEXT, fallback_name TEXT, FOREIGN KEY(plugin_slug, locale) REFERENCES wp_plugin_slugs(slug, locale), FOREIGN KEY(fallback_name) REFERENCES wp_defaults(default_name))', 3),
    $record('index', 'wp_plugin_slugs_active_unique', 'wp_plugin_slugs', 7, 'CREATE UNIQUE INDEX wp_plugin_slugs_active_unique ON wp_plugin_slugs(slug, locale) WHERE active = 1', 4),
    $record('index', 'sqlite_autoindex_wp_defaults_1', 'wp_defaults', 8, 'CREATE UNIQUE INDEX sqlite_autoindex_wp_defaults_1 ON wp_defaults(default_name)', 5),
    $record('index', 'wp_options_lookup', 'wp_options', 9, 'CREATE INDEX wp_options_lookup ON wp_options(plugin_slug, locale, fallback_name)', 6),
];
$nextRecords = $records;
$nextRecords[] = $record('index', 'wp_plugin_slugs_full_unique', 'wp_plugin_slugs', 10, 'CREATE UNIQUE INDEX wp_plugin_slugs_full_unique ON wp_plugin_slugs(slug, locale)', 7);

$tables = [
    'wp_plugin_slugs' => [
        ['rowid' => 1, 'slug' => 'akismet', 'locale' => 'en_US', 'active' => 1],
        ['rowid' => 2, 'slug' => 'hello-dolly', 'locale' => 'en_US', 'active' => 0],
    ],
    'wp_defaults' => [
        ['rowid' => 1, 'default_name' => 'akismet', 'enabled' => 1],
    ],
    'wp_options' => [
        ['rowid' => 1, 'option_id' => 1, 'plugin_slug' => 'akismet', 'locale' => 'en_US', 'fallback_name' => 'akismet'],
        ['rowid' => 2, 'option_id' => 2, 'plugin_slug' => 'hello-dolly', 'locale' => 'en_US', 'fallback_name' => null],
        ['rowid' => 3, 'option_id' => 3, 'plugin_slug' => null, 'locale' => null, 'fallback_name' => null],
    ],
];

$page = SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::currentNextPageFromCatalog188(
    $records,
    $tables,
    $nextRecords,
    $tables,
    'PRAGMA index_xinfo(wp_options_lookup)',
);

$summary = [
    'scenario' => 'application-pragma-index-xinfo-foreignkey-current-source-next188',
    'applicationUse' => 'Copied wp_options imports can block a parent-key repair when PRAGMA index_xinfo shows that the only matching UNIQUE parent key is partial and therefore unusable for FK enforcement.',
    'status' => $page['status'],
    'current_partial_unique_parent_rows' => $page['current']['foreign_key_parent_partial']['partial_unique_only'],
    'next_partial_unique_parent_rows' => $page['next_counts']['foreign_key_parent_partial']['partial_unique_only'],
    'partial_parent_repaired' => $page['delta']['foreign_key_parent_partial_repaired'],
    'next_ready' => $page['next_state']['ready'],
    'pragmas' => [
        'PRAGMA index_xinfo(wp_options_lookup)',
        'PRAGMA foreign_key_list(wp_options)',
        'PRAGMA foreign_key_check',
    ],
];

if (($argv[1] ?? '') === '--self-test') {
    if (
        $summary['status'] !== 'ok'
        || $summary['current_partial_unique_parent_rows'] !== 2
        || $summary['next_partial_unique_parent_rows'] !== 0
        || $summary['partial_parent_repaired'] !== true
        || $summary['next_ready'] !== true
    ) {
        fwrite(STDERR, "application-pragma-index-xinfo-foreignkey-current-source-next188 self-test failed\n");
        exit(1);
    }

    fwrite(STDOUT, "application-pragma-index-xinfo-foreignkey-current-source-next188 self-test passed\n");
    exit(0);
}

echo json_encode($summary, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR) . "\n";

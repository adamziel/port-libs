<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\LibSqlite\SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext;
use PortLibs\LibSqlite\SQLiteSchemaRecord;

$record = static fn (string $type, string $name, string $table, ?int $root, ?string $sql, int $rowId): SQLiteSchemaRecord => new SQLiteSchemaRecord($type, $name, $table, $root, $sql, $rowId);

$current = [
    $record('table', 'wp_sites', 'wp_sites', 2, 'CREATE TABLE wp_sites(site_id INTEGER PRIMARY KEY, domain TEXT NOT NULL)', 1),
    $record('table', 'wp_terms', 'wp_terms', 3, 'CREATE TABLE wp_terms(term_id INTEGER PRIMARY KEY, slug TEXT NOT NULL)', 2),
    $record('table', 'wp_option_stage', 'wp_option_stage', 4, "CREATE TABLE wp_option_stage(
        option_id INTEGER PRIMARY KEY,
        site_id INTEGER DEFAULT 1 REFERENCES wp_sites(site_id) ON DELETE SET DEFAULT,
        term_id INTEGER REFERENCES wp_terms(term_id) ON UPDATE SET DEFAULT,
        fallback_site INTEGER DEFAULT 1,
        fallback_term INTEGER,
        option_name TEXT NOT NULL,
        FOREIGN KEY(fallback_site, fallback_term) REFERENCES wp_terms(site_id, term_id) ON UPDATE SET DEFAULT ON DELETE SET DEFAULT
    )", 3),
    $record('index', 'wp_option_stage_lookup', 'wp_option_stage', 5, 'CREATE INDEX wp_option_stage_lookup ON wp_option_stage(site_id, term_id, option_name)', 4),
];

$next = [
    $current[0],
    $record('table', 'wp_terms', 'wp_terms', 3, 'CREATE TABLE wp_terms(site_id INTEGER DEFAULT 1, term_id INTEGER DEFAULT 0, slug TEXT NOT NULL, PRIMARY KEY(site_id, term_id)) WITHOUT ROWID', 2),
    $record('table', 'wp_option_stage', 'wp_option_stage', 4, "CREATE TABLE wp_option_stage(
        option_id INTEGER PRIMARY KEY,
        site_id INTEGER DEFAULT 1 REFERENCES wp_sites(site_id) ON DELETE SET DEFAULT,
        term_id INTEGER DEFAULT 0 REFERENCES wp_terms(term_id) ON UPDATE SET DEFAULT,
        fallback_site INTEGER DEFAULT 1,
        fallback_term INTEGER DEFAULT 0,
        option_name TEXT NOT NULL,
        FOREIGN KEY(fallback_site, fallback_term) REFERENCES wp_terms(site_id, term_id) ON UPDATE SET DEFAULT ON DELETE SET DEFAULT
    )", 3),
    $current[3],
];

$page = SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::page210(
    $current,
    $next,
    'PRAGMA main.index_xinfo(wp_option_stage_lookup)',
    'PRAGMA main.foreign_key_list(wp_option_stage)',
    28,
    6,
);

if (($argv[1] ?? null) === '--self-test') {
    if (
        $page['operation'] !== 'pragma-index-xinfo-foreignkey-current-source-next210'
        || $page['current']['foreign_key_set_default_child_columns']['missing_child_default'] !== 2
        || $page['next_counts']['foreign_key_set_default_child_columns']['missing_child_default'] !== 0
        || $page['delta']['foreign_key_set_default_child_repaired'] !== true
        || $page['rows'][1]['status'] !== 'missing_child_default'
        || $page['rows'][5]['status'] !== 'set_default_child_defaults_present'
    ) {
        fwrite(STDERR, "application-pragma-index-xinfo-foreignkey-current-source-next210 self-test failed\n");
        exit(1);
    }

    fwrite(STDOUT, "application-pragma-index-xinfo-foreignkey-current-source-next210 self-test passed\n");
    exit(0);
}

echo json_encode([
    'scenario' => 'application-pragma-index-xinfo-foreignkey-current-source-next210',
    'applicationUse' => 'Before applying copied wp_options staging tables, verify that PRAGMA foreign_key_list SET DEFAULT actions have concrete child-column defaults exposed by PRAGMA table_info beside PRAGMA index_xinfo pagination.',
    'status' => $page['status'],
    'source_id' => $page['source_id'],
    'current_set_default_child_columns' => $page['current']['foreign_key_set_default_child_columns'],
    'next_set_default_child_columns' => $page['next_counts']['foreign_key_set_default_child_columns'],
    'delta' => [
        'rows' => $page['delta']['foreign_key_set_default_child_rows'],
        'missing_defaults' => $page['delta']['foreign_key_set_default_child_missing_defaults'],
        'repaired' => $page['delta']['foreign_key_set_default_child_repaired'],
    ],
    'set_default_rows' => $page['rows'],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;

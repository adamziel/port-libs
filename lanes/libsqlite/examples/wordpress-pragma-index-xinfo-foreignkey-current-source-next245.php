<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\LibSqlite\SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext245;
use PortLibs\LibSqlite\SQLiteSchemaRecord;

$record = static fn (string $type, string $name, string $table, ?int $root, ?string $sql, int $rowId): SQLiteSchemaRecord => new SQLiteSchemaRecord($type, $name, $table, $root, $sql, $rowId);

$current = [
    $record('table', 'wp_terms_stage', 'wp_terms_stage', 2, 'CREATE TABLE wp_terms_stage(term_id INTEGER PRIMARY KEY, slug TEXT NOT NULL, slug_norm TEXT GENERATED ALWAYS AS (lower(slug)) STORED UNIQUE)', 1),
    $record('index', 'sqlite_autoindex_wp_terms_stage_1', 'wp_terms_stage', 3, null, 2),
    $record('table', 'wp_termmeta_stage', 'wp_termmeta_stage', 4, 'CREATE TABLE wp_termmeta_stage(meta_id INTEGER PRIMARY KEY, term_slug TEXT NOT NULL, FOREIGN KEY(term_slug) REFERENCES wp_terms_stage(slug_norm))', 3),
];

$next = [
    $record('table', 'wp_terms_stage', 'wp_terms_stage', 2, 'CREATE TABLE wp_terms_stage(term_id INTEGER PRIMARY KEY, slug TEXT NOT NULL UNIQUE)', 1),
    $record('index', 'sqlite_autoindex_wp_terms_stage_1', 'wp_terms_stage', 3, null, 2),
    $record('table', 'wp_termmeta_stage', 'wp_termmeta_stage', 4, 'CREATE TABLE wp_termmeta_stage(meta_id INTEGER PRIMARY KEY, term_slug TEXT NOT NULL, FOREIGN KEY(term_slug) REFERENCES wp_terms_stage(slug))', 3),
];

$page = SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext245::page(
    $current,
    $next,
    'PRAGMA main.index_xinfo(sqlite_autoindex_wp_terms_stage_1)',
    'PRAGMA main.foreign_key_list(wp_termmeta_stage)',
    0,
    260,
);

$summary = [
    'scenario' => 'wordpress-pragma-index-xinfo-foreignkey-current-source-next245',
    'wordpressUse' => 'Copied WordPress term imports can keep generated-column parent keys countable by reading PRAGMA table_xinfo hidden columns alongside PRAGMA index_xinfo.',
    'current_generated_rows' => $page['current']['foreign_key_parent_generated_key']['rows'],
    'current_generated_blockers' => $page['current']['foreign_key_parent_generated_key']['hidden_parent_key_requires_table_xinfo'],
    'current_hidden_columns' => $page['current']['foreign_key_parent_generated_key']['hidden_columns'],
    'next_generated_rows' => $page['next_counts']['foreign_key_parent_generated_key']['rows'],
    'generated_parent_key_repaired' => $page['delta']['foreign_key_parent_generated_key_repaired'],
    'source' => $page['current_source']['foreign_key_parent_generated_key_source'],
    'pragmas' => [
        'PRAGMA main.index_xinfo(sqlite_autoindex_wp_terms_stage_1)',
        'PRAGMA main.foreign_key_list(wp_termmeta_stage)',
    ],
];

if (PHP_SAPI === 'cli' && realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) {
    if (
        $summary['current_generated_rows'] !== 1
        || $summary['current_generated_blockers'] !== 1
        || $summary['current_hidden_columns'] !== 1
        || $summary['next_generated_rows'] !== 0
        || $summary['generated_parent_key_repaired'] !== true
    ) {
        fwrite(STDERR, "wordpress-pragma-index-xinfo-foreignkey-current-source-next245 self-test failed\n");
        exit(1);
    }

    fwrite(STDOUT, json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");
    fwrite(STDOUT, "wordpress-pragma-index-xinfo-foreignkey-current-source-next245 self-test passed\n");
}

return $summary;

<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\LibSqlite\SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext;
use PortLibs\LibSqlite\SQLiteSchemaRecord;

$record = static fn (string $type, string $name, string $table, int $root, ?string $sql, int $rowid): SQLiteSchemaRecord => new SQLiteSchemaRecord($type, $name, $table, $root, $sql, $rowid);

$currentRecords = [
    $record('table', 'wp_terms', 'wp_terms', 2, 'CREATE TABLE wp_terms(term_id INTEGER PRIMARY KEY, slug TEXT COLLATE NOCASE NOT NULL)', 1),
    $record('table', 'wp_option_defaults', 'wp_option_defaults', 3, 'CREATE TABLE wp_option_defaults(blog_id INTEGER NOT NULL, option_name TEXT NOT NULL, PRIMARY KEY(blog_id, option_name)) WITHOUT ROWID', 2),
    $record('index', 'sqlite_autoindex_wp_option_defaults_1', 'wp_option_defaults', 4, null, 3),
    $record('table', 'wp_postmeta_import', 'wp_postmeta_import', 5, "CREATE TABLE wp_postmeta_import(
        meta_id INTEGER PRIMARY KEY,
        term_id INTEGER NOT NULL,
        blog_id INTEGER NOT NULL,
        option_name TEXT NOT NULL,
        nullable_term_id INTEGER,
        default_blog_id INTEGER DEFAULT 1,
        default_option_name TEXT DEFAULT 'autoload',
        FOREIGN KEY(term_id) REFERENCES wp_terms(term_id) ON DELETE SET NULL,
        FOREIGN KEY(blog_id, option_name) REFERENCES wp_option_defaults(blog_id, option_name) ON UPDATE SET DEFAULT,
        FOREIGN KEY(nullable_term_id) REFERENCES wp_terms(term_id) ON DELETE SET NULL,
        FOREIGN KEY(default_blog_id, default_option_name) REFERENCES wp_option_defaults(blog_id, option_name) ON UPDATE SET DEFAULT
    )", 4),
    $record('index', 'wp_postmeta_option_action', 'wp_postmeta_import', 6, 'CREATE INDEX wp_postmeta_option_action ON wp_postmeta_import(blog_id, option_name)', 5),
];

$nextRecords = [
    $currentRecords[0],
    $currentRecords[1],
    $currentRecords[2],
    $record('table', 'wp_postmeta_import', 'wp_postmeta_import', 5, "CREATE TABLE wp_postmeta_import(
        meta_id INTEGER PRIMARY KEY,
        term_id INTEGER DEFAULT NULL,
        blog_id INTEGER NOT NULL DEFAULT 1,
        option_name TEXT NOT NULL DEFAULT 'autoload',
        nullable_term_id INTEGER,
        default_blog_id INTEGER DEFAULT 1,
        default_option_name TEXT DEFAULT 'autoload',
        FOREIGN KEY(term_id) REFERENCES wp_terms(term_id) ON DELETE SET NULL,
        FOREIGN KEY(blog_id, option_name) REFERENCES wp_option_defaults(blog_id, option_name) ON UPDATE SET DEFAULT,
        FOREIGN KEY(nullable_term_id) REFERENCES wp_terms(term_id) ON DELETE SET NULL,
        FOREIGN KEY(default_blog_id, default_option_name) REFERENCES wp_option_defaults(blog_id, option_name) ON UPDATE SET DEFAULT
    )", 4),
    $currentRecords[4],
];

$page = SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::page213(
    $currentRecords,
    $nextRecords,
    'PRAGMA main.index_xinfo(wp_postmeta_option_action)',
    'PRAGMA main.foreign_key_list(wp_postmeta_import)',
);

$summary = [
    'scenario' => 'wordpress-pragma-index-xinfo-foreignkey-current-source-next213',
    'wordpressUse' => 'Copied WordPress postmeta imports can reject FK action repairs that would SET NULL into NOT NULL columns or SET DEFAULT into columns without usable defaults.',
    'status' => $page['status'],
    'current_action_rows' => $page['current']['foreign_key_action_columns']['rows'],
    'current_action_blockers' => $page['current']['foreign_key_action_columns']['blocked'],
    'next_action_blockers' => $page['next_counts']['foreign_key_action_columns']['blocked'],
    'action_columns_repaired' => $page['delta']['foreign_key_action_column_repaired'],
    'source' => $page['current_source']['foreign_key_action_column_source'],
];

if (($argv[1] ?? null) === '--self-test') {
    if (
        $summary['status'] !== 'ok'
        || $summary['current_action_rows'] !== 6
        || $summary['current_action_blockers'] !== 3
        || $summary['next_action_blockers'] !== 0
        || $summary['action_columns_repaired'] !== true
    ) {
        fwrite(STDERR, "wordpress-pragma-index-xinfo-foreignkey-current-source-next213 self-test failed\n");
        exit(1);
    }

    fwrite(STDOUT, "wordpress-pragma-index-xinfo-foreignkey-current-source-next213 self-test passed\n");
    exit(0);
}

echo json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;

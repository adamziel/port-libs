<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\LibSqlite\SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext;
use PortLibs\LibSqlite\SQLiteSchemaRecord;

$record = static fn (string $type, string $name, string $table, ?int $root, ?string $sql, int $rowId): SQLiteSchemaRecord => new SQLiteSchemaRecord($type, $name, $table, $root, $sql, $rowId);

$current = [
    $record('table', 'wp_parent_options', 'wp_parent_options', 2, 'CREATE TABLE wp_parent_options(blog_id INTEGER NOT NULL, option_name TEXT NOT NULL, autoload TEXT NOT NULL)', 1),
    $record('index', 'wp_parent_options_blog_name_autoload_unique', 'wp_parent_options', 3, 'CREATE UNIQUE INDEX wp_parent_options_blog_name_autoload_unique ON wp_parent_options(blog_id, option_name, autoload)', 2),
    $record('table', 'wp_child_option_refs', 'wp_child_option_refs', 4, "CREATE TABLE wp_child_option_refs(
        ref_id INTEGER PRIMARY KEY,
        blog_id INTEGER NOT NULL,
        option_name TEXT NOT NULL,
        FOREIGN KEY(blog_id, option_name) REFERENCES wp_parent_options(blog_id, option_name)
    )", 3),
];

$next = [
    $current[0],
    $record('index', 'wp_parent_options_blog_name_unique', 'wp_parent_options', 5, 'CREATE UNIQUE INDEX wp_parent_options_blog_name_unique ON wp_parent_options(blog_id, option_name)', 4),
    $current[2],
];

$page = SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::page237(
    $current,
    $next,
    'PRAGMA main.index_xinfo(wp_parent_options_blog_name_autoload_unique)',
    'PRAGMA main.foreign_key_list(wp_child_option_refs)',
    0,
    180,
);

$summary = [
    'scenario' => 'application-pragma-index-xinfo-foreignkey-current-source-next237',
    'applicationUse' => 'Copied Application import schemas can reject a parent UNIQUE index whose PRAGMA index_xinfo key prefix matches the foreign key but whose extra key columns make it unusable for SQLite FK enforcement.',
    'current_prefix_parent_key_rows' => $page['current']['foreign_key_parent_prefix_unique']['rows'],
    'current_prefix_parent_key_blockers' => $page['current']['foreign_key_parent_prefix_unique']['prefix_only_parent_unique_index'],
    'current_extra_columns' => $page['current']['foreign_key_parent_prefix_unique']['extra_columns'],
    'next_prefix_parent_key_ok' => $page['next_counts']['foreign_key_parent_prefix_unique']['ok'],
    'prefix_parent_key_repaired' => $page['delta']['foreign_key_parent_prefix_unique_repaired'],
    'source' => $page['current_source']['foreign_key_parent_prefix_unique_source'],
    'pragmas' => [
        'PRAGMA main.index_xinfo(wp_parent_options_blog_name_autoload_unique)',
        'PRAGMA main.foreign_key_list(wp_child_option_refs)',
    ],
];

if (PHP_SAPI === 'cli' && realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) {
    if (
        $summary['current_prefix_parent_key_rows'] !== 2
        || $summary['current_prefix_parent_key_blockers'] !== 2
        || $summary['current_extra_columns'] !== 2
        || $summary['next_prefix_parent_key_ok'] !== 2
        || $summary['prefix_parent_key_repaired'] !== true
    ) {
        fwrite(STDERR, "application-pragma-index-xinfo-foreignkey-current-source-next237 self-test failed\n");
        exit(1);
    }

    fwrite(STDOUT, json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");
    fwrite(STDOUT, "application-pragma-index-xinfo-foreignkey-current-source-next237 self-test passed\n");
}

return $summary;

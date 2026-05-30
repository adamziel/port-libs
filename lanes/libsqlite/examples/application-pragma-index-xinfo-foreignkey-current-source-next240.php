<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\LibSqlite\SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext;
use PortLibs\LibSqlite\SQLiteSchemaRecord;

$record = static fn (string $type, string $name, string $table, ?int $root, ?string $sql, int $rowId): SQLiteSchemaRecord => new SQLiteSchemaRecord($type, $name, $table, $root, $sql, $rowId);

$current = [
    $record('table', 'wp_parent_posts', 'wp_parent_posts', 2, 'CREATE TABLE wp_parent_posts(ID INTEGER PRIMARY KEY, post_name TEXT NOT NULL)', 1),
    $record('index', 'wp_parent_posts_name_unique', 'wp_parent_posts', 3, 'CREATE UNIQUE INDEX wp_parent_posts_name_unique ON wp_parent_posts(post_name)', 2),
    $record('table', 'wp_import_meta', 'wp_import_meta', 4, "CREATE TABLE wp_import_meta(
        meta_id INTEGER PRIMARY KEY,
        post_id INTEGER NOT NULL,
        FOREIGN KEY(post_id) REFERENCES wp_parent_posts
    )", 3),
];

$next = [
    $current[0],
    $current[1],
    $record('table', 'wp_import_meta', 'wp_import_meta', 4, "CREATE TABLE wp_import_meta(
        meta_id INTEGER PRIMARY KEY,
        post_id INTEGER NOT NULL,
        FOREIGN KEY(post_id) REFERENCES wp_parent_posts(ID)
    )", 3),
];

$page = SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::page240(
    $current,
    $next,
    'PRAGMA main.index_xinfo(wp_parent_posts_name_unique)',
    'PRAGMA main.foreign_key_list(wp_import_meta)',
    0,
    220,
);

$summary = [
    'scenario' => 'application-pragma-index-xinfo-foreignkey-current-source-next240',
    'applicationUse' => 'Copied Application import schemas sometimes use shorthand REFERENCES parent clauses; PRAGMA foreign_key_list reports an empty parent column that must resolve to the parent primary key before schema comparison.',
    'current_implicit_parent_pk_rows' => $page['current']['foreign_key_implicit_parent_primary_key']['rows'],
    'current_implicit_parent_pk_ok' => $page['current']['foreign_key_implicit_parent_primary_key']['ok'],
    'next_implicit_parent_pk_rows' => $page['next_counts']['foreign_key_implicit_parent_primary_key']['rows'],
    'implicit_parent_pk_repaired' => $page['delta']['foreign_key_implicit_parent_primary_key_repaired'],
    'source' => $page['current_source']['foreign_key_implicit_parent_primary_key_source'],
    'pragmas' => [
        'PRAGMA main.index_xinfo(wp_parent_posts_name_unique)',
        'PRAGMA main.foreign_key_list(wp_import_meta)',
    ],
];

if (PHP_SAPI === 'cli' && realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) {
    if (
        $summary['current_implicit_parent_pk_rows'] !== 1
        || $summary['current_implicit_parent_pk_ok'] !== 1
        || $summary['next_implicit_parent_pk_rows'] !== 0
        || $summary['implicit_parent_pk_repaired'] !== false
    ) {
        fwrite(STDERR, "application-pragma-index-xinfo-foreignkey-current-source-next240 self-test failed\n");
        exit(1);
    }

    fwrite(STDOUT, json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");
    fwrite(STDOUT, "application-pragma-index-xinfo-foreignkey-current-source-next240 self-test passed\n");
}

return $summary;

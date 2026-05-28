<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/SQLiteSchemaRecord.php';
require_once __DIR__ . '/../src/SQLitePragmaSchemaCatalog.php';
require_once __DIR__ . '/../src/SQLitePragmaForeignKeyCheck.php';
require_once __DIR__ . '/../src/SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext156.php';
require_once __DIR__ . '/../src/SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext157.php';
require_once __DIR__ . '/../src/SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext159.php';
require_once __DIR__ . '/../src/SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext161.php';
require_once __DIR__ . '/../src/SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext163.php';
require_once __DIR__ . '/../src/SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext164.php';
require_once __DIR__ . '/../src/SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext165.php';
require_once __DIR__ . '/../src/SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext167.php';
require_once __DIR__ . '/../src/SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext169.php';
require_once __DIR__ . '/../src/SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext171.php';
require_once __DIR__ . '/../src/SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext173.php';
require_once __DIR__ . '/../src/SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext175.php';
require_once __DIR__ . '/../src/SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext177.php';
require_once __DIR__ . '/../src/SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext178.php';
require_once __DIR__ . '/../src/SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext181.php';
require_once __DIR__ . '/../src/SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext182.php';
require_once __DIR__ . '/../src/SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext183.php';
require_once __DIR__ . '/../src/SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext184.php';
require_once __DIR__ . '/../src/SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext185.php';
require_once __DIR__ . '/../src/SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext186.php';
require_once __DIR__ . '/../src/SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext187.php';
require_once __DIR__ . '/../src/SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext188.php';
require_once __DIR__ . '/../src/SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext189.php';
require_once __DIR__ . '/../src/SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext190.php';
require_once __DIR__ . '/../src/SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext191.php';
require_once __DIR__ . '/../src/SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext192.php';
require_once __DIR__ . '/../src/SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext193.php';
require_once __DIR__ . '/../src/SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext194.php';
require_once __DIR__ . '/../src/SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext195.php';
require_once __DIR__ . '/../src/SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext196.php';
require_once __DIR__ . '/../src/SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext200.php';
require_once __DIR__ . '/../src/SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext202.php';
require_once __DIR__ . '/../src/SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext203.php';
require_once __DIR__ . '/../src/SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext205.php';
require_once __DIR__ . '/../src/SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext206.php';
require_once __DIR__ . '/../src/SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext207.php';
require_once __DIR__ . '/../src/SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext208.php';
require_once __DIR__ . '/../src/SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext209.php';
require_once __DIR__ . '/../src/SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext211.php';
require_once __DIR__ . '/../src/SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext212.php';
require_once __DIR__ . '/../src/SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext217.php';
require_once __DIR__ . '/../src/SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext219.php';
require_once __DIR__ . '/../src/SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext227.php';
require_once __DIR__ . '/../src/SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext230.php';

use PortLibs\LibSqlite\SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext230;
use PortLibs\LibSqlite\SQLiteSchemaRecord;

$record = static fn (string $type, string $name, string $table, ?int $root, ?string $sql, int $rowId): SQLiteSchemaRecord => new SQLiteSchemaRecord($type, $name, $table, $root, $sql, $rowId);

$current = [
    $record('table', 'wp_posts_stage', 'wp_posts_stage', 2, 'CREATE TABLE wp_posts_stage(post_id INTEGER PRIMARY KEY, post_title TEXT NOT NULL)', 1),
    $record('table', 'wp_users_stage', 'wp_users_stage', 3, 'CREATE TABLE wp_users_stage(user_id INTEGER PRIMARY KEY, user_login TEXT NOT NULL)', 2),
    $record('table', 'wp_postmeta_import', 'wp_postmeta_import', 4, "CREATE TABLE wp_postmeta_import(
        meta_id INTEGER PRIMARY KEY,
        post_id INTEGER NOT NULL,
        user_id INTEGER,
        FOREIGN KEY(post_id) REFERENCES wp_posts_stage(rowid),
        FOREIGN KEY(user_id) REFERENCES wp_users_stage(_rowid_) ON UPDATE CASCADE
    )", 3),
    $record('index', 'wp_postmeta_import_post_id_idx', 'wp_postmeta_import', 5, 'CREATE INDEX wp_postmeta_import_post_id_idx ON wp_postmeta_import(post_id)', 4),
];

$next = [
    $record('table', 'wp_posts_stage', 'wp_posts_stage', 2, 'CREATE TABLE wp_posts_stage(post_id INTEGER PRIMARY KEY, post_title TEXT NOT NULL)', 1),
    $record('table', 'wp_users_stage', 'wp_users_stage', 3, 'CREATE TABLE wp_users_stage(user_id INTEGER PRIMARY KEY, user_login TEXT NOT NULL)', 2),
    $record('table', 'wp_postmeta_import', 'wp_postmeta_import', 4, "CREATE TABLE wp_postmeta_import(
        meta_id INTEGER PRIMARY KEY,
        post_id INTEGER NOT NULL,
        user_id INTEGER,
        FOREIGN KEY(post_id) REFERENCES wp_posts_stage(post_id),
        FOREIGN KEY(user_id) REFERENCES wp_users_stage(user_id) ON UPDATE CASCADE
    )", 3),
    $record('index', 'wp_postmeta_import_post_id_idx', 'wp_postmeta_import', 5, 'CREATE INDEX wp_postmeta_import_post_id_idx ON wp_postmeta_import(post_id)', 4),
];

$page = SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext230::page(
    $current,
    $next,
    'PRAGMA main.index_xinfo(wp_postmeta_import_post_id_idx)',
    'PRAGMA main.foreign_key_list(wp_postmeta_import)',
);

if (($argv[1] ?? null) === '--self-test') {
    if (
        $page['operation'] !== 'pragma-index-xinfo-foreignkey-current-source-next230'
        || $page['current']['foreign_key_parent_pseudo_rowid']['pseudo_rowid_parent_key'] !== 2
        || $page['next_counts']['foreign_key_parent_pseudo_rowid']['pseudo_rowid_parent_key'] !== 0
        || $page['delta']['foreign_key_parent_pseudo_rowid_repaired'] !== true
    ) {
        fwrite(STDERR, "wordpress-pragma-index-xinfo-foreignkey-current-source-next230 self-test failed\n");
        exit(1);
    }

    fwrite(STDOUT, "wordpress-pragma-index-xinfo-foreignkey-current-source-next230 self-test passed\n");
    exit(0);
}

echo json_encode([
    'scenario' => 'wordpress-pragma-index-xinfo-foreignkey-current-source-next230',
    'operation' => $page['operation'],
    'current_pseudo_rowid_parent_keys' => $page['current']['foreign_key_parent_pseudo_rowid']['pseudo_rowid_parent_key'],
    'next_pseudo_rowid_parent_keys' => $page['next_counts']['foreign_key_parent_pseudo_rowid']['pseudo_rowid_parent_key'],
    'repaired' => $page['delta']['foreign_key_parent_pseudo_rowid_repaired'],
    'rows' => $page['current_source']['foreign_key_parent_pseudo_rowid'],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;

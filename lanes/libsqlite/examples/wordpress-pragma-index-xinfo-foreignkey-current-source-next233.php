<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/SQLiteSchemaRecord.php';
require_once __DIR__ . '/../src/SQLiteCreateTable.php';
require_once __DIR__ . '/../src/SQLiteIndexColumn.php';
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
require_once __DIR__ . '/../src/SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext220.php';
require_once __DIR__ . '/../src/SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext223.php';
require_once __DIR__ . '/../src/SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext224.php';
require_once __DIR__ . '/../src/SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext227.php';
require_once __DIR__ . '/../src/SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext228.php';
require_once __DIR__ . '/../src/SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext229.php';
require_once __DIR__ . '/../src/SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext230.php';
require_once __DIR__ . '/../src/SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext233.php';

use PortLibs\LibSqlite\SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext233;
use PortLibs\LibSqlite\SQLiteSchemaRecord;

$record = static fn (string $type, string $name, string $table, ?int $root, ?string $sql, int $rowId): SQLiteSchemaRecord => new SQLiteSchemaRecord($type, $name, $table, $root, $sql, $rowId);

$current = [
    $record('table', 'wp_posts_stage', 'wp_posts_stage', 2, 'CREATE TABLE wp_posts_stage(post_id INTEGER PRIMARY KEY, blog_id INTEGER, post_name TEXT, UNIQUE(blog_id, post_name))', 1),
    $record('index', 'sqlite_autoindex_wp_posts_stage_1', 'wp_posts_stage', 3, null, 2),
    $record('table', 'wp_postmeta_import', 'wp_postmeta_import', 4, 'CREATE TABLE wp_postmeta_import(meta_id INTEGER PRIMARY KEY, post_id INTEGER, blog_id INTEGER, post_name TEXT, meta_key TEXT, FOREIGN KEY(post_id) REFERENCES wp_posts_stage(post_id), FOREIGN KEY(blog_id, post_name) REFERENCES wp_posts_stage(blog_id, post_name))', 3),
    $record('index', 'wp_postmeta_expr_post', 'wp_postmeta_import', 5, 'CREATE INDEX wp_postmeta_expr_post ON wp_postmeta_import(lower(meta_key), post_id)', 4),
    $record('index', 'wp_postmeta_expr_blog_name', 'wp_postmeta_import', 6, 'CREATE INDEX wp_postmeta_expr_blog_name ON wp_postmeta_import(coalesce(meta_key, ""), blog_id, post_name)', 5),
];

$next = [
    $current[0],
    $current[1],
    $current[2],
    $record('index', 'wp_postmeta_post_fk', 'wp_postmeta_import', 7, 'CREATE INDEX wp_postmeta_post_fk ON wp_postmeta_import(post_id, lower(meta_key))', 4),
    $record('index', 'wp_postmeta_blog_name_fk', 'wp_postmeta_import', 8, 'CREATE INDEX wp_postmeta_blog_name_fk ON wp_postmeta_import(blog_id, post_name, coalesce(meta_key, ""))', 5),
];

$page = SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext233::page(
    $current,
    $next,
    'PRAGMA main.index_xinfo(wp_postmeta_expr_post)',
    'PRAGMA main.foreign_key_list(wp_postmeta_import)',
);

if (($argv[1] ?? null) === '--self-test') {
    if (
        $page['operation'] !== 'pragma-index-xinfo-foreignkey-current-source-next233'
        || $page['current']['foreign_key_child_expression_prefix']['expression_prefix_child_index'] !== 3
        || $page['next_counts']['foreign_key_child_expression_prefix']['expression_prefix_child_index'] !== 0
        || $page['delta']['foreign_key_child_expression_prefix_repaired'] !== true
    ) {
        fwrite(STDERR, "wordpress-pragma-index-xinfo-foreignkey-current-source-next233 self-test failed\n");
        exit(1);
    }

    fwrite(STDOUT, "wordpress-pragma-index-xinfo-foreignkey-current-source-next233 self-test passed\n");
    exit(0);
}

echo json_encode([
    'scenario' => 'wordpress-pragma-index-xinfo-foreignkey-current-source-next233',
    'operation' => $page['operation'],
    'current_expression_prefix_child_indexes' => $page['current']['foreign_key_child_expression_prefix']['expression_prefix_child_index'],
    'next_expression_prefix_child_indexes' => $page['next_counts']['foreign_key_child_expression_prefix']['expression_prefix_child_index'],
    'repaired' => $page['delta']['foreign_key_child_expression_prefix_repaired'],
    'rows' => $page['current_source']['foreign_key_child_expression_prefix'],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;

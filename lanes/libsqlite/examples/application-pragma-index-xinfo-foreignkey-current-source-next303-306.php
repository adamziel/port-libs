<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\LibSqlite\SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext;
use PortLibs\LibSqlite\SQLiteSchemaRecord;

$record = static fn (string $type, string $name, string $table, ?int $root, ?string $sql, int $rowId): SQLiteSchemaRecord => new SQLiteSchemaRecord($type, $name, $table, $root, $sql, $rowId);

$records = [
    $record('table', 'wp_posts_303', 'wp_posts_303', 2, 'CREATE TABLE wp_posts_303(ID INTEGER PRIMARY KEY)', 1),
    $record('table', 'wp_postmeta_303', 'wp_postmeta_303', 3, 'CREATE TABLE wp_postmeta_303(post_id INTEGER, FOREIGN KEY(" ") REFERENCES wp_posts_303(ID))', 2),
    $record('table', 'wp_terms_304', 'wp_terms_304', 4, 'CREATE TABLE wp_terms_304(term_id INTEGER PRIMARY KEY)', 3),
    $record('table', 'wp_termmeta_304', 'wp_termmeta_304', 5, 'CREATE TABLE wp_termmeta_304(term_id INTEGER, FOREIGN KEY(term_id) REFERENCES wp_terms_304(term_id) ON DELETE CASCADE)', 4),
    $record('index', 'wp_termmeta_304_term_id', 'wp_termmeta_304', 6, 'CREATE INDEX wp_termmeta_304_term_id ON wp_termmeta_304(term_id)', 5),
    $record('table', 'wp_users_305', 'wp_users_305', 7, 'CREATE TABLE wp_users_305(user_login TEXT PRIMARY KEY)', 6),
    $record('table', 'wp_usermeta_305', 'wp_usermeta_305', 8, 'CREATE TABLE wp_usermeta_305(user_login TEXT NOT NULL DEFAULT "" REFERENCES wp_users_305(user_login))', 7),
    $record('index', 'wp_usermeta_305_login_nocase', 'wp_usermeta_305', 9, 'CREATE INDEX wp_usermeta_305_login_nocase ON wp_usermeta_305(user_login COLLATE NOCASE)', 8),
    $record('table', 'wp_blogs_306', 'wp_blogs_306', 10, 'CREATE TABLE wp_blogs_306(blog_id INTEGER PRIMARY KEY)', 9),
    $record('table', 'wp_options_306', 'wp_options_306', 11, 'CREATE TABLE wp_options_306(blog_id INTEGER NOT NULL DEFAULT 0 REFERENCES wp_blogs_306(blog_id))', 10),
    $record('index', 'wp_options_306_blog_desc', 'wp_options_306', 12, 'CREATE INDEX wp_options_306_blog_desc ON wp_options_306(blog_id DESC)', 11),
];

$summary = [
    'scenario' => 'application-pragma-index-xinfo-foreignkey-current-source-next303-306',
    'applicationUse' => 'Plugin import preflight can keep FK enforcement throughput high by rejecting child lookup shapes that PRAGMA index_xinfo would make slow or ambiguous.',
    'next303_empty_child_column' => count(SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::childKeyDiagnosticRows287($records, 'next', 'empty_child_column')),
    'next304_nullable_cascade_child_column' => count(SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::childKeyDiagnosticRows287($records, 'next', 'nullable_cascade_child_column')),
    'next305_child_lookup_collation_mismatch' => count(SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::childKeyDiagnosticRows287($records, 'next', 'child_lookup_collation_mismatch')),
    'next306_child_lookup_desc_mismatch' => count(SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::childKeyDiagnosticRows287($records, 'next', 'child_lookup_desc_mismatch')),
    'implemented_pages' => array_values(array_filter(
        range(303, 306),
        static fn (int $slice): bool => method_exists(SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::class, 'page' . $slice),
    )),
];

if (($argv[1] ?? null) === '--self-test') {
    foreach (array_keys($summary) as $key) {
        if (str_starts_with($key, 'next') && $summary[$key] !== 1) {
            fwrite(STDERR, "application-pragma-index-xinfo-foreignkey-current-source-next303-306 self-test failed\n");
            exit(1);
        }
    }
    if ($summary['implemented_pages'] !== [303, 304, 305, 306]) {
        fwrite(STDERR, "application-pragma-index-xinfo-foreignkey-current-source-next303-306 self-test failed\n");
        exit(1);
    }

    fwrite(STDOUT, "application-pragma-index-xinfo-foreignkey-current-source-next303-306 self-test passed\n");
    exit(0);
}

echo json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;

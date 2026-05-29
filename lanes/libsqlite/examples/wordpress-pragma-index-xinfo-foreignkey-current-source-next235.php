<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\LibSqlite\SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext;
use PortLibs\LibSqlite\SQLiteSchemaRecord;

$record = static fn (string $type, string $name, string $table, int $root, ?string $sql, int $rowid): SQLiteSchemaRecord => new SQLiteSchemaRecord($type, $name, $table, $root, $sql, $rowid);

$currentRecords = [
    $record('table', 'wp_taxonomy_keys', 'wp_taxonomy_keys', 2, 'CREATE TABLE wp_taxonomy_keys(slug TEXT NOT NULL, locale TEXT NOT NULL, blog_id INTEGER NOT NULL, term_id INTEGER PRIMARY KEY)', 1),
    $record('index', 'wp_taxonomy_slug_desc_unique', 'wp_taxonomy_keys', 3, 'CREATE UNIQUE INDEX wp_taxonomy_slug_desc_unique ON wp_taxonomy_keys(slug DESC)', 2),
    $record('index', 'wp_taxonomy_locale_blog_desc_unique', 'wp_taxonomy_keys', 4, 'CREATE UNIQUE INDEX wp_taxonomy_locale_blog_desc_unique ON wp_taxonomy_keys(locale COLLATE NOCASE DESC, blog_id ASC)', 3),
    $record('table', 'wp_term_import_queue', 'wp_term_import_queue', 5, "CREATE TABLE wp_term_import_queue(
        queue_id INTEGER PRIMARY KEY,
        slug TEXT NOT NULL,
        locale TEXT NOT NULL,
        blog_id INTEGER NOT NULL,
        term_id INTEGER NOT NULL,
        FOREIGN KEY(slug) REFERENCES wp_taxonomy_keys(slug),
        FOREIGN KEY(locale, blog_id) REFERENCES wp_taxonomy_keys(locale, blog_id),
        FOREIGN KEY(term_id) REFERENCES wp_taxonomy_keys(term_id)
    )", 4),
];

$nextRecords = [
    $currentRecords[0],
    $record('index', 'wp_taxonomy_slug_unique', 'wp_taxonomy_keys', 6, 'CREATE UNIQUE INDEX wp_taxonomy_slug_unique ON wp_taxonomy_keys(slug)', 2),
    $record('index', 'wp_taxonomy_locale_blog_unique', 'wp_taxonomy_keys', 7, 'CREATE UNIQUE INDEX wp_taxonomy_locale_blog_unique ON wp_taxonomy_keys(locale COLLATE NOCASE, blog_id)', 3),
    $currentRecords[3],
];

$page = SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::page235(
    $currentRecords,
    $nextRecords,
    'PRAGMA main.index_xinfo(wp_taxonomy_locale_blog_desc_unique)',
    'PRAGMA main.foreign_key_list(wp_term_import_queue)',
);

$summary = [
    'scenario' => 'wordpress-pragma-index-xinfo-foreignkey-current-source-next235',
    'wordpressUse' => 'Copied taxonomy import schemas may preserve DESC UNIQUE parent indexes; PRAGMA index_xinfo desc metadata should not make valid foreign-key parent keys look unusable.',
    'status' => $page['status'],
    'current_desc_parent_rows' => $page['current']['foreign_key_parent_desc_unique']['rows'],
    'current_desc_terms' => $page['current']['foreign_key_parent_desc_unique']['descending_terms'],
    'next_desc_parent_rows' => $page['next_counts']['foreign_key_parent_desc_unique']['rows'],
    'delta_desc_terms' => $page['delta']['foreign_key_parent_desc_unique_terms'],
    'source' => $page['current_source']['foreign_key_parent_desc_unique_source'],
];

if (($argv[1] ?? null) === '--self-test') {
    if (
        $summary['status'] !== 'ok'
        || $summary['current_desc_parent_rows'] !== 3
        || $summary['current_desc_terms'] !== 3
        || $summary['next_desc_parent_rows'] !== 0
        || $summary['delta_desc_terms'] !== -3
    ) {
        fwrite(STDERR, "wordpress-pragma-index-xinfo-foreignkey-current-source-next235 self-test failed\n");
        exit(1);
    }

    fwrite(STDOUT, "wordpress-pragma-index-xinfo-foreignkey-current-source-next235 self-test passed\n");
    exit(0);
}

echo json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;

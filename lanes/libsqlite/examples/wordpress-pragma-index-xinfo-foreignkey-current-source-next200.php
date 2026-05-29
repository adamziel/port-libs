<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext;
use PortLibs\LibSqlite\SQLiteSchemaRecord;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$record = static fn (string $type, string $name, string $table, int $root, ?string $sql, int $rowid): SQLiteSchemaRecord => new SQLiteSchemaRecord($type, $name, $table, $root, $sql, $rowid);

$currentRecords = [
    $record('table', 'wp_option_locale', 'wp_option_locale', 4, 'CREATE TABLE wp_option_locale(slug TEXT COLLATE NOCASE, locale TEXT COLLATE RTRIM, label TEXT, UNIQUE(slug, locale))', 1),
    $record('table', 'wp_blogs', 'wp_blogs', 5, 'CREATE TABLE wp_blogs(blog_id INTEGER PRIMARY KEY, domain TEXT)', 2),
    $record('table', 'wp_options', 'wp_options', 6, 'CREATE TABLE wp_options(option_id INTEGER PRIMARY KEY, slug TEXT, locale TEXT, blog_id INTEGER, autoload TEXT, option_value TEXT, FOREIGN KEY(slug, locale) REFERENCES wp_option_locale(slug, locale), FOREIGN KEY(blog_id) REFERENCES wp_blogs(blog_id))', 3),
    $record('index', 'sqlite_autoindex_wp_option_locale_1', 'wp_option_locale', 7, 'CREATE UNIQUE INDEX sqlite_autoindex_wp_option_locale_1 ON wp_option_locale(slug COLLATE NOCASE, locale COLLATE RTRIM)', 4),
    $record('index', 'wp_options_locale_slug_fk_wrong', 'wp_options', 8, 'CREATE INDEX wp_options_locale_slug_fk_wrong ON wp_options(locale, slug, option_id)', 5),
];
$nextRecords = [
    $currentRecords[0],
    $currentRecords[1],
    $currentRecords[2],
    $currentRecords[3],
    $record('index', 'wp_options_slug_locale_fk', 'wp_options', 9, 'CREATE INDEX wp_options_slug_locale_fk ON wp_options(slug, locale, option_id)', 6),
];

$currentTables = [
    'wp_option_locale' => [['rowid' => 1, 'slug' => 'home', 'locale' => 'en_US', 'label' => 'Home']],
    'wp_blogs' => [['rowid' => 1, 'blog_id' => 1, 'domain' => 'example.test']],
    'wp_options' => [
        ['rowid' => 1, 'option_id' => 1, 'slug' => 'home', 'locale' => 'en_US', 'blog_id' => 1, 'autoload' => 'yes', 'option_value' => 'https://example.test'],
        ['rowid' => 2, 'option_id' => 2, 'slug' => 'dashboard', 'locale' => 'en_US', 'blog_id' => 404, 'autoload' => 'no', 'option_value' => '1'],
    ],
];
$nextTables = [
    'wp_option_locale' => [
        ['rowid' => 1, 'slug' => 'home', 'locale' => 'en_US', 'label' => 'Home'],
        ['rowid' => 2, 'slug' => 'dashboard', 'locale' => 'en_US', 'label' => 'Dashboard'],
    ],
    'wp_blogs' => [
        ['rowid' => 1, 'blog_id' => 1, 'domain' => 'example.test'],
        ['rowid' => 404, 'blog_id' => 404, 'domain' => 'archive.example.test'],
    ],
    'wp_options' => $currentTables['wp_options'],
];

$page = SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::currentNextPageFromCatalog200(
    $currentRecords,
    $currentTables,
    $nextRecords,
    $nextTables,
    'PRAGMA index_xinfo(wp_options_locale_slug_fk_wrong)',
);

$summary = [
    'scenario' => 'wordpress-pragma-index-xinfo-foreignkey-current-source-next200',
    'wordpressUse' => 'Copied wp_options imports can carry an index on (locale, slug) while FK checks and import lookups need the child-key prefix order (slug, locale); this keeps the wrong-order index visible as diagnostic-only PRAGMA evidence.',
    'status' => $page['status'],
    'current_wrong_order_child_rows' => $page['current']['foreign_key_wrong_order_child_indexes']['wrong_order_child_index'],
    'next_wrong_order_child_rows' => $page['next_counts']['foreign_key_wrong_order_child_indexes']['wrong_order_child_index'],
    'wrong_order_repaired' => $page['delta']['foreign_key_wrong_order_child_index_repaired'],
    'diagnostic_only' => $page['delta']['foreign_key_wrong_order_child_index_diagnostic_only'],
    'next_ready' => $page['next_state']['ready'],
    'pragmas' => [
        'PRAGMA index_xinfo(wp_options_locale_slug_fk_wrong)',
        'PRAGMA foreign_key_list(wp_options)',
        'PRAGMA foreign_key_check',
    ],
];

if (($argv[1] ?? '') === '--self-test') {
    if (
        $summary['status'] !== 'ok'
        || $summary['current_wrong_order_child_rows'] !== 2
        || $summary['next_wrong_order_child_rows'] !== 0
        || $summary['wrong_order_repaired'] !== true
        || $summary['diagnostic_only'] !== true
        || $summary['next_ready'] !== true
    ) {
        fwrite(STDERR, "wordpress-pragma-index-xinfo-foreignkey-current-source-next200 self-test failed\n");
        exit(1);
    }

    fwrite(STDOUT, "wordpress-pragma-index-xinfo-foreignkey-current-source-next200 self-test passed\n");
    exit(0);
}

echo json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n";

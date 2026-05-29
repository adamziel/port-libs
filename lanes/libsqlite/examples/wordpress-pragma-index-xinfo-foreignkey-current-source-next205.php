<?php

declare(strict_types=1);

spl_autoload_register(static function (string $class): void {
    $prefix = 'PortLibs\\LibSqlite\\';
    if (!str_starts_with($class, $prefix)) {
        return;
    }
    $path = __DIR__ . '/../src/' . substr($class, strlen($prefix)) . '.php';
    if (is_file($path)) {
        require_once $path;
    }
});

use PortLibs\LibSqlite\SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext;
use PortLibs\LibSqlite\SQLiteSchemaRecord;

$record = static fn (string $type, string $name, string $table, ?int $root, ?string $sql, int $rowId): SQLiteSchemaRecord => new SQLiteSchemaRecord($type, $name, $table, $root, $sql, $rowId);

$current = [
    $record('table', 'wp_terms', 'wp_terms', 2, 'CREATE TABLE wp_terms(term_id INTEGER PRIMARY KEY, slug TEXT COLLATE NOCASE NOT NULL, locale TEXT COLLATE RTRIM NOT NULL, UNIQUE(slug, locale))', 1),
    $record('index', 'sqlite_autoindex_wp_terms_1', 'wp_terms', 3, null, 2),
    $record('table', 'wp_sites', 'wp_sites', 4, 'CREATE TABLE wp_sites(site_id INTEGER PRIMARY KEY, domain TEXT)', 3),
    $record('table', 'wp_termmeta_import', 'wp_termmeta_import', 5, 'CREATE TABLE wp_termmeta_import(meta_id INTEGER PRIMARY KEY, term_slug TEXT COLLATE NOCASE NOT NULL, locale TEXT COLLATE RTRIM NOT NULL, site_id INTEGER NOT NULL REFERENCES wp_sites(site_id), meta_key TEXT, FOREIGN KEY(term_slug, locale) REFERENCES wp_terms(slug, locale))', 4),
    $record('index', 'wp_termmeta_lookup_bad', 'wp_termmeta_import', 6, 'CREATE INDEX wp_termmeta_lookup_bad ON wp_termmeta_import(term_slug COLLATE BINARY DESC, locale COLLATE BINARY, meta_key)', 5),
];
$next = [
    $current[0],
    $current[1],
    $current[2],
    $current[3],
    $record('index', 'wp_termmeta_lookup_good', 'wp_termmeta_import', 6, 'CREATE INDEX wp_termmeta_lookup_good ON wp_termmeta_import(term_slug COLLATE NOCASE, locale COLLATE RTRIM, meta_key)', 5),
    $record('index', 'wp_termmeta_site_lookup', 'wp_termmeta_import', 7, 'CREATE INDEX wp_termmeta_site_lookup ON wp_termmeta_import(site_id)', 6),
];

$page = SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::page205(
    $current,
    $next,
    'PRAGMA main.index_xinfo(wp_termmeta_lookup_bad)',
    'PRAGMA main.foreign_key_list(wp_termmeta_import)',
);

if (($argv[1] ?? null) === '--self-test') {
    if (
        $page['delta']['foreign_key_child_prefix_quality_repaired'] !== true
        || $page['current']['foreign_key_child_prefix_quality']['mismatched'] !== 3
        || $page['next_counts']['foreign_key_child_prefix_quality']['mismatched'] !== 0
    ) {
        fwrite(STDERR, "wordpress-pragma-index-xinfo-foreignkey-current-source-next205 self-test failed\n");
        exit(1);
    }

    fwrite(STDOUT, "wordpress-pragma-index-xinfo-foreignkey-current-source-next205 self-test passed\n");
    exit(0);
}

echo json_encode([
    'scenario' => 'wordpress-pragma-index-xinfo-foreignkey-current-source-next205',
    'wordpressUse' => 'Copied wp_termmeta imports can resume FK repair only after PRAGMA index_xinfo shows child-side support indexes with matching collations and ascending prefix order.',
    'current_child_prefix_mismatches' => $page['current']['foreign_key_child_prefix_quality']['mismatched'],
    'next_child_prefix_mismatches' => $page['next_counts']['foreign_key_child_prefix_quality']['mismatched'],
    'child_prefix_repaired' => $page['delta']['foreign_key_child_prefix_quality_repaired'],
    'pragmas' => [
        'PRAGMA main.index_xinfo(wp_termmeta_lookup_bad)',
        'PRAGMA main.foreign_key_list(wp_termmeta_import)',
    ],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;

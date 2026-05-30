<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\LibSqlite\SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext;
use PortLibs\LibSqlite\SQLiteSchemaRecord;

$record = static fn (string $type, string $name, string $table, int $root, ?string $sql, int $rowid): SQLiteSchemaRecord => new SQLiteSchemaRecord($type, $name, $table, $root, $sql, $rowid);

$currentRecords = [
    $record('table', 'wp_locale_terms', 'wp_locale_terms', 2, 'CREATE TABLE wp_locale_terms(slug TEXT COLLATE NOCASE NOT NULL, locale TEXT COLLATE RTRIM NOT NULL, label TEXT, UNIQUE(slug, locale))', 1),
    $record('index', 'wp_locale_terms_slug_locale_unique', 'wp_locale_terms', 3, 'CREATE UNIQUE INDEX wp_locale_terms_slug_locale_unique ON wp_locale_terms(slug COLLATE BINARY, locale COLLATE RTRIM)', 2),
    $record('table', 'wp_options', 'wp_options', 4, "CREATE TABLE wp_options(
        option_id INTEGER PRIMARY KEY,
        slug TEXT NOT NULL,
        locale TEXT NOT NULL,
        option_name TEXT NOT NULL,
        option_value TEXT,
        FOREIGN KEY(slug, locale) REFERENCES wp_locale_terms(slug, locale)
    )", 3),
    $record('index', 'wp_options_locale_lookup', 'wp_options', 5, 'CREATE INDEX wp_options_locale_lookup ON wp_options(slug, locale, option_name)', 4),
];

$nextRecords = [
    $currentRecords[0],
    $record('index', 'wp_locale_terms_slug_locale_unique', 'wp_locale_terms', 3, 'CREATE UNIQUE INDEX wp_locale_terms_slug_locale_unique ON wp_locale_terms(slug COLLATE NOCASE, locale COLLATE RTRIM)', 2),
    $currentRecords[2],
    $currentRecords[3],
];

$page = SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::page220(
    $currentRecords,
    $nextRecords,
    'PRAGMA main.index_xinfo(wp_locale_terms_slug_locale_unique)',
    'PRAGMA main.foreign_key_list(wp_options)',
);

$summary = [
    'scenario' => 'application-pragma-index-xinfo-foreignkey-current-source-next220',
    'applicationUse' => 'Copied multilingual wp_options imports can detect parent UNIQUE indexes whose PRAGMA index_xinfo collation does not match the referenced parent column declaration before treating foreign-key repair as current-source safe.',
    'status' => $page['status'],
    'current_parent_collation_rows' => $page['current']['foreign_key_parent_collations']['rows'],
    'current_parent_collation_mismatches' => $page['current']['foreign_key_parent_collations']['mismatch'],
    'next_parent_collation_mismatches' => $page['next_counts']['foreign_key_parent_collations']['mismatch'],
    'parent_collation_repaired' => $page['delta']['foreign_key_parent_collation_repaired'],
    'source' => $page['current_source']['foreign_key_parent_collation_source'],
];

if (($argv[1] ?? null) === '--self-test') {
    if (
        $summary['status'] !== 'ok'
        || $summary['current_parent_collation_rows'] !== 2
        || $summary['current_parent_collation_mismatches'] !== 1
        || $summary['next_parent_collation_mismatches'] !== 0
        || $summary['parent_collation_repaired'] !== true
    ) {
        fwrite(STDERR, "application-pragma-index-xinfo-foreignkey-current-source-next220 self-test failed\n");
        exit(1);
    }

    fwrite(STDOUT, "application-pragma-index-xinfo-foreignkey-current-source-next220 self-test passed\n");
    exit(0);
}

echo json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;

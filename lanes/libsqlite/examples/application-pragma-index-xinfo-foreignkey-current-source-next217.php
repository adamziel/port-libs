<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\LibSqlite\SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext;
use PortLibs\LibSqlite\SQLiteSchemaRecord;

$record = static fn (string $type, string $name, string $table, int $root, ?string $sql, int $rowid): SQLiteSchemaRecord => new SQLiteSchemaRecord($type, $name, $table, $root, $sql, $rowid);

$currentRecords = [
    $record('table', 'wp_sites', 'wp_sites', 2, 'CREATE TABLE wp_sites(site_id INTEGER NOT NULL, domain TEXT COLLATE NOCASE NOT NULL, deleted INTEGER DEFAULT 0)', 1),
    $record('index', 'wp_sites_site_domain_unique', 'wp_sites', 3, 'CREATE UNIQUE INDEX wp_sites_site_domain_unique ON wp_sites(site_id, domain)', 2),
    $record('index', 'wp_sites_domain_partial_unique', 'wp_sites', 4, 'CREATE UNIQUE INDEX wp_sites_domain_partial_unique ON wp_sites(domain) WHERE deleted = 0', 3),
    $record('table', 'wp_blog_options', 'wp_blog_options', 5, "CREATE TABLE wp_blog_options(
        option_id INTEGER PRIMARY KEY,
        domain TEXT NOT NULL,
        site_id INTEGER NOT NULL,
        option_name TEXT NOT NULL,
        FOREIGN KEY(domain) REFERENCES wp_sites(domain) ON UPDATE CASCADE,
        FOREIGN KEY(site_id, domain) REFERENCES wp_sites(site_id, domain) ON DELETE CASCADE
    )", 4),
    $record('index', 'wp_blog_options_lookup', 'wp_blog_options', 6, 'CREATE INDEX wp_blog_options_lookup ON wp_blog_options(domain, option_name)', 5),
];

$nextRecords = [
    $currentRecords[0],
    $currentRecords[1],
    $currentRecords[2],
    $record('index', 'wp_sites_domain_unique', 'wp_sites', 7, 'CREATE UNIQUE INDEX wp_sites_domain_unique ON wp_sites(domain)', 6),
    $currentRecords[3],
    $currentRecords[4],
];

$page = SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::page217(
    $currentRecords,
    $nextRecords,
    'PRAGMA main.index_xinfo(wp_sites_site_domain_unique)',
    'PRAGMA main.foreign_key_list(wp_blog_options)',
);

$summary = [
    'scenario' => 'application-pragma-index-xinfo-foreignkey-current-source-next217',
    'applicationUse' => 'Copied multisite wp_blog_options imports can detect when a foreign key references only a suffix of a UNIQUE parent index before treating PRAGMA foreign_key_check output as repairable by the current parent catalog.',
    'status' => $page['status'],
    'current_parent_key_rows' => $page['current']['foreign_key_parent_key_prefix']['rows'],
    'current_suffix_parent_key_blockers' => $page['current']['foreign_key_parent_key_prefix']['suffix_parent_unique_index'],
    'next_parent_key_blockers' => $page['next_counts']['foreign_key_parent_key_prefix']['blocked'],
    'parent_key_prefix_repaired' => $page['delta']['foreign_key_parent_key_prefix_repaired'],
    'source' => $page['current_source']['foreign_key_parent_key_prefix_source'],
];

if (($argv[1] ?? null) === '--self-test') {
    if (
        $summary['status'] !== 'ok'
        || $summary['current_parent_key_rows'] !== 3
        || $summary['current_suffix_parent_key_blockers'] !== 1
        || $summary['next_parent_key_blockers'] !== 0
        || $summary['parent_key_prefix_repaired'] !== true
    ) {
        fwrite(STDERR, "application-pragma-index-xinfo-foreignkey-current-source-next217 self-test failed\n");
        exit(1);
    }

    fwrite(STDOUT, "application-pragma-index-xinfo-foreignkey-current-source-next217 self-test passed\n");
    exit(0);
}

echo json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;

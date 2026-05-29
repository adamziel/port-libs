<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\LibSqlite\SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext;
use PortLibs\LibSqlite\SQLiteSchemaRecord;

$record = static fn (string $type, string $name, string $table, int $root, ?string $sql, int $rowid): SQLiteSchemaRecord => new SQLiteSchemaRecord($type, $name, $table, $root, $sql, $rowid);

$currentRecords = [
    $record('table', 'wp_sites', 'wp_sites', 2, 'CREATE TABLE wp_sites(site_id INTEGER NOT NULL, domain TEXT COLLATE NOCASE NOT NULL, path TEXT COLLATE RTRIM NOT NULL)', 1),
    $record('index', 'wp_sites_domain_binary_unique', 'wp_sites', 3, 'CREATE UNIQUE INDEX wp_sites_domain_binary_unique ON wp_sites(domain COLLATE BINARY)', 2),
    $record('index', 'wp_sites_site_domain_binary_unique', 'wp_sites', 4, 'CREATE UNIQUE INDEX wp_sites_site_domain_binary_unique ON wp_sites(site_id, domain COLLATE BINARY)', 3),
    $record('table', 'wp_blog_options', 'wp_blog_options', 5, "CREATE TABLE wp_blog_options(
        option_id INTEGER PRIMARY KEY,
        site_id INTEGER NOT NULL,
        domain TEXT NOT NULL,
        path TEXT NOT NULL,
        option_name TEXT NOT NULL,
        FOREIGN KEY(domain) REFERENCES wp_sites(domain) ON UPDATE CASCADE,
        FOREIGN KEY(site_id, domain) REFERENCES wp_sites(site_id, domain) ON DELETE CASCADE
    )", 4),
];

$nextRecords = [
    $currentRecords[0],
    $currentRecords[1],
    $currentRecords[2],
    $record('index', 'wp_sites_domain_nocase_unique', 'wp_sites', 6, 'CREATE UNIQUE INDEX wp_sites_domain_nocase_unique ON wp_sites(domain COLLATE NOCASE)', 5),
    $record('index', 'wp_sites_site_domain_nocase_unique', 'wp_sites', 7, 'CREATE UNIQUE INDEX wp_sites_site_domain_nocase_unique ON wp_sites(site_id, domain COLLATE NOCASE)', 6),
    $currentRecords[3],
];

$page = SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::page224(
    $currentRecords,
    $nextRecords,
    'PRAGMA main.index_xinfo(wp_sites_site_domain_binary_unique)',
    'PRAGMA main.foreign_key_list(wp_blog_options)',
);

$summary = [
    'scenario' => 'wordpress-pragma-index-xinfo-foreignkey-current-source-next224',
    'wordpressUse' => 'Copied multisite option imports can detect parent UNIQUE indexes whose COLLATE terms do not match the parent table declaration before enabling foreign-key checks.',
    'status' => $page['status'],
    'current_collation_rows' => $page['current']['foreign_key_parent_key_collation']['rows'],
    'current_collation_blockers' => $page['current']['foreign_key_parent_key_collation']['blocked'],
    'next_collation_blockers' => $page['next_counts']['foreign_key_parent_key_collation']['blocked'],
    'collation_repaired' => $page['delta']['foreign_key_parent_key_collation_repaired'],
    'source' => $page['current_source']['foreign_key_parent_key_collation_source'],
];

if (($argv[1] ?? null) === '--self-test') {
    if (
        $summary['status'] !== 'ok'
        || $summary['current_collation_rows'] !== 3
        || $summary['current_collation_blockers'] !== 3
        || $summary['next_collation_blockers'] !== 0
        || $summary['collation_repaired'] !== true
    ) {
        fwrite(STDERR, "wordpress-pragma-index-xinfo-foreignkey-current-source-next224 self-test failed\n");
        exit(1);
    }

    fwrite(STDOUT, "wordpress-pragma-index-xinfo-foreignkey-current-source-next224 self-test passed\n");
    exit(0);
}

echo json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;

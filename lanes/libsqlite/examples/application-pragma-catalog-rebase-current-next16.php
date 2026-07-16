<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteAttachedSchemaCatalog;
use PortLibs\LibSqlite\SQLiteSchemaRecord;

$record = static fn (string $type, string $name, string $table, ?int $root, ?string $sql = null): SQLiteSchemaRecord => new SQLiteSchemaRecord($type, $name, $table, $root, $sql, 1);

$catalog = new SQLiteAttachedSchemaCatalog(
    [
        $record('table', 'wp_sites', 'wp_sites', 2, 'CREATE TABLE wp_sites(blog_id INTEGER PRIMARY KEY, domain TEXT NOT NULL UNIQUE)'),
        $record('index', 'sqlite_autoindex_wp_sites_1', 'wp_sites', 3, null),
        $record('table', 'wp_options', 'wp_options', 4, "CREATE TABLE wp_options(
            option_id INTEGER PRIMARY KEY,
            blog_id INTEGER REFERENCES wp_sites(blog_id) ON UPDATE CASCADE ON DELETE RESTRICT,
            option_name TEXT NOT NULL,
            option_value TEXT,
            UNIQUE(blog_id, option_name)
        )"),
        $record('index', 'sqlite_autoindex_wp_options_1', 'wp_options', 5, null),
    ],
    [
        $record('table', 'wp_options', 'wp_options', 6, "CREATE TABLE wp_options(
            option_name TEXT PRIMARY KEY REFERENCES wp_option_names(name) ON DELETE CASCADE,
            option_value TEXT,
            transient_timeout INTEGER
        )"),
        $record('index', 'sqlite_autoindex_wp_options_1', 'wp_options', 7, null),
    ],
);
$catalog->attach('network', '/srv/www/network.sqlite', [
    $record('table', 'wp_blogmeta', 'wp_blogmeta', 8, 'CREATE TABLE wp_blogmeta(blog_id INTEGER, meta_key TEXT, PRIMARY KEY(blog_id, meta_key)) WITHOUT ROWID'),
    $record('index', 'sqlite_autoindex_wp_blogmeta_1', 'wp_blogmeta', 9, null),
    $record('table', 'wp_sitemeta', 'wp_sitemeta', 10, "CREATE TABLE wp_sitemeta(
        meta_id INTEGER PRIMARY KEY,
        blog_id INTEGER,
        meta_key TEXT,
        meta_value TEXT,
        FOREIGN KEY(blog_id, meta_key) REFERENCES wp_blogmeta(blog_id, meta_key) ON UPDATE SET DEFAULT ON DELETE CASCADE
    )"),
]);

$databases = $catalog->executeSchemaPragma('PRAGMA database_list');
$tempOptionsForeignKeys = $catalog->executeSchemaPragma('PRAGMA foreign_key_list(wp_options)');
$mainOptionsForeignKeys = $catalog->executeSchemaPragma('PRAGMA main.foreign_key_list(wp_options)');
$networkSiteMetaForeignKeys = $catalog->executeSchemaPragma('PRAGMA network.foreign_key_list(wp_sitemeta)');

echo json_encode([
    'applicationUse' => 'Preview copied Application schema PRAGMA catalog rows for attached database order and foreign-key actions while temp import tables shadow main wp_options without ext/sqlite.',
    'database_list' => array_map(static fn (array $row): string => $row['seq'] . ':' . $row['name'] . ':' . ($row['file'] ?? ''), $databases['rows']),
    'unqualified_wp_options_schema' => $tempOptionsForeignKeys['schema'],
    'unqualified_wp_options_foreign_keys' => $tempOptionsForeignKeys['rows'],
    'main_wp_options_foreign_keys' => $mainOptionsForeignKeys['rows'],
    'network_wp_sitemeta_foreign_keys' => $networkSiteMetaForeignKeys['rows'],
], JSON_PRETTY_PRINT) . "\n";

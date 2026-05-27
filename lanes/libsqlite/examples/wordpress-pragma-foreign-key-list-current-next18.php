<?php

declare(strict_types=1);

require __DIR__ . '/../../../tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteAttachedSchemaCatalog;
use PortLibs\LibSqlite\SQLiteSchemaRecord;

$record = static fn (string $type, string $name, string $table, ?int $root, ?string $sql = null): SQLiteSchemaRecord => new SQLiteSchemaRecord(
    $type,
    $name,
    $table,
    $root,
    $sql,
    1,
);

$catalog = new SQLiteAttachedSchemaCatalog(
    [
        $record('table', 'wp_sites', 'wp_sites', 2, 'CREATE TABLE wp_sites(blog_id INTEGER PRIMARY KEY, domain TEXT NOT NULL)'),
        $record('table', 'wp_options', 'wp_options', 3, "CREATE TABLE wp_options(
            option_id INTEGER PRIMARY KEY,
            blog_id INTEGER REFERENCES wp_sites(blog_id) ON UPDATE CASCADE ON DELETE RESTRICT,
            option_name TEXT NOT NULL
        )"),
    ],
    [
        $record('table', 'wp_options', 'wp_options', 4, "CREATE TABLE wp_options(
            option_name TEXT PRIMARY KEY REFERENCES wp_option_names(name) ON DELETE CASCADE,
            option_value TEXT
        )"),
    ],
);

$catalog->attach('network', '/srv/www/network.sqlite', [
    $record('table', 'wp_blogmeta', 'wp_blogmeta', 5, 'CREATE TABLE wp_blogmeta(blog_id INTEGER, meta_key TEXT, PRIMARY KEY(blog_id, meta_key)) WITHOUT ROWID'),
    $record('table', 'wp_sitemeta', 'wp_sitemeta', 6, "CREATE TABLE wp_sitemeta(
        meta_id INTEGER PRIMARY KEY,
        blog_id INTEGER NOT NULL,
        meta_key TEXT,
        FOREIGN KEY(blog_id, meta_key) REFERENCES wp_blogmeta(blog_id, meta_key) ON UPDATE SET DEFAULT ON DELETE CASCADE MATCH custom
    )"),
]);

return [
    'currentSourceOptions' => $catalog->executeTableValuedPragma("pragma_foreign_key_list('wp_options')"),
    'mainOptions' => $catalog->executeTableValuedPragma("pragma_foreign_key_list('wp_options','main')"),
    'networkSiteMeta' => $catalog->executeTableValuedPragma("pragma_foreign_key_list('wp_sitemeta','network')"),
];

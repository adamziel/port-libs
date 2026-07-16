<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteAttachedSchemaCatalog;
use PortLibs\LibSqlite\SQLiteAttachTempCollationViewResolution;
use PortLibs\LibSqlite\SQLiteSchemaRecord;

$record = static fn (string $type, string $name, string $table, ?int $root, string $sql, int $rowid): SQLiteSchemaRecord => new SQLiteSchemaRecord(
    $type,
    $name,
    $table,
    $root,
    $sql,
    $rowid,
);

$catalog = new SQLiteAttachedSchemaCatalog(
    [
        $record('table', 'wp_options', 'wp_options', 2, 'CREATE TABLE wp_options(option_id integer primary key, option_name text, option_value text, autoload text)', 1),
        $record('view', 'active_options', 'active_options', 0, "CREATE VIEW active_options AS SELECT option_name COLLATE NOCASE FROM wp_options WHERE autoload = 'yes'", 2),
    ],
    [
        $record('table', 'wp_options', 'wp_options', 10, 'CREATE TEMP TABLE wp_options(option_id integer, option_name text, option_value text)', 3),
        $record('view', 'active_options', 'active_options', null, "CREATE TEMP VIEW active_options AS SELECT t.option_name COLLATE WPSLUG, s.option_value FROM temp.wp_options AS t JOIN site.wp_options AS s ON s.option_name COLLATE NOCASE = t.option_name COLLATE NOCASE", 4),
    ],
);
$catalog->attach('site', '/srv/www/site.sqlite', [
    $record('table', 'wp_options', 'wp_options', 20, 'CREATE TABLE site.wp_options(blog_id integer, option_name text, option_value text)', 5),
]);

$collations = ['BINARY', 'NOCASE', 'RTRIM', 'WPSLUG'];
$temp = SQLiteAttachTempCollationViewResolution::resolve($catalog, 'active_options', $collations);
$main = SQLiteAttachTempCollationViewResolution::resolve($catalog, 'main.active_options', $collations);

echo json_encode([
    'scenario' => 'application attach temp collation view current-source resolution',
    'summary' => SQLiteAttachTempCollationViewResolution::summary($catalog, $collations),
    'views' => [
        $temp['viewSchema'] . '.' . $temp['view'] => [
            'status' => $temp['status'],
            'sources' => array_map(static fn (array $source): string => $source['schema'] . '.' . $source['name'], $temp['resolvedSources']),
            'collations' => $temp['collations'],
        ],
        $main['viewSchema'] . '.' . $main['view'] => [
            'status' => $main['status'],
            'sources' => array_map(static fn (array $source): string => $source['schema'] . '.' . $source['name'], $main['resolvedSources']),
            'collations' => $main['collations'],
        ],
    ],
], JSON_PRETTY_PRINT) . PHP_EOL;

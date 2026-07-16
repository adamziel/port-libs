<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteAttachVacuumTempSchemaPlan;
use PortLibs\LibSqlite\SQLiteAttachedSchemaCatalog;
use PortLibs\LibSqlite\SQLiteDatabase;
use PortLibs\LibSqlite\SQLiteSchemaRecord;

$database = static function (int $pageSize, int $pageCount): SQLiteDatabase {
    $first = str_repeat("\0", $pageSize);
    $first = substr_replace($first, "SQLite format 3\0", 0, 16);
    $first = substr_replace($first, pack('n', $pageSize), 16, 2);
    $first[18] = "\x01";
    $first[19] = "\x01";
    $first[20] = "\x00";
    $first[21] = "\x40";
    $first[22] = "\x20";
    $first[23] = "\x20";
    $first = substr_replace($first, pack('N', $pageCount), 28, 4);
    $first = substr_replace($first, pack('N', 1), 40, 4);
    $first = substr_replace($first, pack('N', 1), 56, 4);

    $pages = [$first];
    for ($page = 2; $page <= $pageCount; $page++) {
        $pages[] = str_pad("wp-options-copy-page-{$page};", $pageSize, '.');
    }

    return SQLiteDatabase::fromBytes(implode('', $pages));
};

$record = static fn (string $name, int $root): SQLiteSchemaRecord => new SQLiteSchemaRecord(
    'table',
    $name,
    $name,
    $root,
    'CREATE TABLE ' . $name . '(option_name TEXT, option_value TEXT)',
    1,
);

$catalog = new SQLiteAttachedSchemaCatalog(
    [$record('wp_options', 2)],
    [$record('wp_options', 4)],
);
$catalog->executeAttachDetachSql("ATTACH '/srv/wp-content/cache/site.sqlite' AS site", static fn (): array => [
    $record('wp_sitemeta', 8),
]);

$plan = SQLiteAttachVacuumTempSchemaPlan::planSql(
    "VACUUM site INTO '/tmp/site-vacuum.sqlite'",
    $catalog,
    [
        'main' => $database(1024, 3),
        'site' => $database(2048, 4),
    ],
    4096,
);

echo sprintf(
    "schema=%s source=%s target=%s page_size=%d temp_preserved=%s cache_invalidated=%s ops=%s\n",
    $plan['schema'],
    $plan['source_file'],
    $plan['target_path'],
    $plan['target_page_size'],
    $plan['temp_schema_preserved'] ? 'yes' : 'no',
    $plan['cache_invalidated'] ? 'yes' : 'no',
    implode(',', array_column($plan['operations'], 'op')),
);

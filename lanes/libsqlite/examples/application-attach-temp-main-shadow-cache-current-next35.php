<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteAttachedSchemaCatalog;
use PortLibs\LibSqlite\SQLiteSchemaRecord;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

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
        $record('table', 'wp_options', 'wp_options', 2, 'CREATE TABLE wp_options(option_id INTEGER PRIMARY KEY, option_name TEXT, autoload TEXT)'),
        $record('index', 'wp_options_name', 'wp_options', 3, 'CREATE INDEX wp_options_name ON wp_options(option_name)'),
    ],
    [
        $record('table', 'wp_options', 'wp_options', 20, 'CREATE TEMP TABLE wp_options(option_name TEXT, option_value TEXT)'),
    ],
);

$snapshot = $catalog->schemaCacheResolutionSnapshot(
    ['wp_options', 'main.wp_options', 'archive.wp_options', 'sqlite_schema'],
    ['wp_options_name', 'wp_options_archive_name'],
);

$catalog->executeAttachDetachSql(
    "ATTACH '/srv/wp/archive.sqlite' AS archive",
    static fn (): array => [
        $record('table', 'wp_options', 'wp_options', 40, 'CREATE TABLE wp_options(option_name TEXT, archived_at TEXT)'),
        $record('index', 'wp_options_archive_name', 'wp_options', 41, 'CREATE INDEX wp_options_archive_name ON wp_options(option_name)'),
    ],
);

$plan = $catalog->schemaCacheResolutionInvalidation($snapshot);

if (($argv[1] ?? '') === '--self-test') {
    assert($plan['stale'] === true);
    assert($plan['table_changes']['wp_options']['changed'] === false);
    assert($plan['table_changes']['archive.wp_options']['after']['schema'] === 'archive');
    assert($plan['index_changes']['wp_options_archive_name']['changed'] === true);
    fwrite(STDOUT, "application-attach-temp-main-shadow-cache-current-next35 self-test passed\n");
    exit(0);
}

fwrite(STDOUT, sprintf(
    "stale=%s temp_wp_options=%s archive_wp_options=%s changed_tables=%s changed_indexes=%s\n",
    $plan['stale'] ? 'yes' : 'no',
    $plan['table_changes']['wp_options']['after']['schema'] ?? 'missing',
    $plan['table_changes']['archive.wp_options']['after']['schema'] ?? 'missing',
    implode(',', $plan['changed_tables']),
    implode(',', $plan['changed_indexes']),
));

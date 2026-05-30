<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteAttachedSchemaCatalog;
use PortLibs\LibSqlite\SQLiteAttachWalSchemaCachePlan;
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

$snapshot = SQLiteAttachWalSchemaCachePlan::snapshot(
    $catalog,
    ['wp_options', 'main.wp_options', 'site.wp_options', 'sqlite_schema'],
    ['wp_options_name', 'wp_options_site_name'],
    ['main' => 9, 'temp' => 2],
    'main',
    4,
);

$catalog->executeAttachDetachSql(
    "ATTACH '/srv/wp/site.sqlite' AS site",
    static fn (): array => [
        $record('table', 'wp_options', 'wp_options', 40, 'CREATE TABLE wp_options(option_name TEXT, option_value TEXT)'),
        $record('index', 'wp_options_site_name', 'wp_options', 41, 'CREATE INDEX wp_options_site_name ON wp_options(option_name)'),
    ],
);

$plan = SQLiteAttachWalSchemaCachePlan::currentNext($catalog, $snapshot, ['main' => 10, 'temp' => 2, 'site' => 1], 6);

if (($argv[1] ?? '') === '--self-test') {
    assert($plan['requires_reprepare'] === true);
    assert($plan['schema_cache']['table_changes']['wp_options']['changed'] === false);
    assert($plan['table_reprepare']['main.wp_options']['reason'] === 'schema-cookie-changed');
    assert($plan['table_reprepare']['site.wp_options']['reason'] === 'resolution-changed');
    assert($plan['index_reprepare']['wp_options_site_name']['reason'] === 'resolution-changed');
    fwrite(STDOUT, "application-attach-wal-schema-cache-current-next47 self-test passed\n");
    exit(0);
}

fwrite(STDOUT, sprintf(
    "reprepare=%s reasons=%s current_frame=%d next_frame=%d changed_cookie_schemas=%s table_reprepare=%s index_reprepare=%s\n",
    $plan['requires_reprepare'] ? 'yes' : 'no',
    implode(',', $plan['reasons']),
    $plan['current_reader']['wal_end_frame'],
    $plan['next_reader']['wal_end_frame'],
    implode(',', $plan['changed_cookie_schemas']),
    implode(',', array_keys($plan['table_reprepare'])),
    implode(',', array_keys($plan['index_reprepare'])),
));

<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteAttachedSchemaCatalog;
use PortLibs\LibSqlite\SQLiteAttachWalTempViewCachePlan;
use PortLibs\LibSqlite\SQLiteSchemaRecord;

$record = static fn (string $type, string $name, string $table, ?int $root, ?string $sql, int $rowid): SQLiteSchemaRecord => new SQLiteSchemaRecord(
    $type,
    $name,
    $table,
    $root,
    $sql,
    $rowid,
);

$catalog = new SQLiteAttachedSchemaCatalog(
    [
        $record('table', 'wp_options', 'wp_options', 2, 'CREATE TABLE main.wp_options(option_id integer primary key, option_name text, option_value text, autoload text)', 1),
        $record('view', 'autoloaded_options', 'autoloaded_options', 0, "CREATE VIEW main.autoloaded_options AS SELECT option_id, option_name, option_value FROM wp_options WHERE autoload = 'yes'", 2),
    ],
    [
        $record('table', 'wp_options', 'wp_options', 10, 'CREATE TEMP TABLE wp_options(option_id integer, option_name text, option_value text)', 3),
        $record('view', 'autoloaded_options', 'autoloaded_options', 0, 'CREATE TEMP VIEW autoloaded_options AS SELECT option_id, option_name, option_value FROM wp_options', 4),
    ],
);
$catalog->attach('site', '/srv/wp/site.sqlite', [
    $record('table', 'wp_options', 'wp_options', 20, 'CREATE TABLE site.wp_options(blog_id integer, option_name text, option_value text)', 5),
    $record('view', 'site_options', 'site_options', 0, 'CREATE VIEW site.site_options AS SELECT blog_id, option_name, option_value FROM wp_options', 6),
]);

$plan = SQLiteAttachWalTempViewCachePlan::preparedViewCacheRepreparePlan(
    $catalog,
    [
        ['name' => 'autoloaded_options', 'source' => 'temp', 'active' => true],
        ['name' => 'main.autoloaded_options', 'source' => 'main'],
        ['name' => 'site.site_options', 'source' => 'site'],
    ],
    [
        'temp' => [
            $record('table', 'wp_options', 'wp_options', 30, 'CREATE TEMP TABLE wp_options(option_id integer, option_name text, option_value text, expires integer)', 7),
            $record('view', 'autoloaded_options', 'autoloaded_options', 0, 'CREATE TEMP VIEW autoloaded_options AS SELECT option_id, option_name, option_value FROM wp_options', 8),
        ],
        'site' => [
            $record('table', 'wp_options', 'wp_options', 40, 'CREATE TABLE site.wp_options(blog_id integer, option_name text, option_value text, migrated integer)', 9),
            $record('view', 'site_options', 'site_options', 0, 'CREATE VIEW site.site_options AS SELECT blog_id, option_name, option_value FROM wp_options', 10),
        ],
    ],
    [
        'temp' => ['schema_cookie' => 3],
        'main' => ['schema_cookie' => 12, 'wal_schema_cookie' => 13],
        'site' => ['schema_cookie' => 8, 'wal_frames' => [['page' => 1, 'schema_cookie' => 9, 'commit' => true]]],
    ],
);

$summary = [
    'operation' => $plan['operation'],
    'status' => $plan['status'],
    'reprepare_views' => $plan['reprepare_views'],
    'active_current_snapshot_views' => $plan['active_current_snapshot_views'],
    'reset_schema_views' => $plan['reset_schema_views'],
    'next_step_schema_views' => $plan['next_step_schema_views'],
    'stable_views' => $plan['stable_views'],
    'temp_dependency_after_root' => $plan['views']['autoloaded_options']['dependencies_after']['wp_options']['rootpage'],
    'site_dependency_after_root' => $plan['views']['site.site_options']['dependencies_after']['site.wp_options']['rootpage'],
    'wal_schema_cookie_sources' => $plan['wal_schema_cookie_sources'],
];

if (($argv[1] ?? '') === '--self-test') {
    assert($summary['status'] === 'view_cache_expired');
    assert($summary['active_current_snapshot_views'] === ['autoloaded_options']);
    assert($summary['next_step_schema_views'] === ['site.site_options']);
    assert($summary['stable_views'] === ['main.autoloaded_options']);
    assert($summary['temp_dependency_after_root'] === 30);
    assert($summary['site_dependency_after_root'] === 40);
}

echo json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;

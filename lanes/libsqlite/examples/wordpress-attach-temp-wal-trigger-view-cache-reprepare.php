<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/SQLiteSchemaRecord.php';
require_once __DIR__ . '/../src/SQLiteAttachedSchemaCatalog.php';
require_once __DIR__ . '/../src/SQLiteAttachTempViewTriggerResolution.php';
require_once __DIR__ . '/../src/SQLiteAttachTempWalSchemaTriggerPlan.php';
require_once __DIR__ . '/../src/SQLiteAttachWalTempViewCachePlan.php';

use PortLibs\LibSqlite\SQLiteAttachedSchemaCatalog;
use PortLibs\LibSqlite\SQLiteAttachTempWalSchemaTriggerPlan;
use PortLibs\LibSqlite\SQLiteAttachWalTempViewCachePlan;
use PortLibs\LibSqlite\SQLiteSchemaRecord;

$record = static fn (string $type, string $name, string $table, ?int $root, ?string $sql, int $rowid): SQLiteSchemaRecord => new SQLiteSchemaRecord($type, $name, $table, $root, $sql, $rowid);
$catalog = new SQLiteAttachedSchemaCatalog([
    $record('table', 'wp_options', 'wp_options', 2, 'CREATE TABLE main.wp_options(option_id integer primary key, option_name text, option_value text, autoload text)', 1),
    $record('view', 'autoloaded_options', 'autoloaded_options', 0, "CREATE VIEW main.autoloaded_options AS SELECT option_id, option_name, option_value FROM wp_options WHERE autoload = 'yes'", 2),
    $record('trigger', 'autoloaded_options_io_update', 'autoloaded_options', 0, 'CREATE TRIGGER main.autoloaded_options_io_update INSTEAD OF UPDATE ON autoloaded_options BEGIN UPDATE wp_options SET option_value = new.option_value WHERE option_id = old.option_id; END', 3),
]);
$catalog->attach('site', '/srv/wp/site.sqlite', [
    $record('table', 'wp_options', 'wp_options', 20, 'CREATE TABLE site.wp_options(blog_id integer, option_name text, option_value text, autoload text)', 4),
    $record('view', 'autoloaded_options', 'autoloaded_options', 0, "CREATE VIEW site.autoloaded_options AS SELECT blog_id, option_name, option_value FROM wp_options WHERE autoload = 'yes'", 5),
    $record('trigger', 'site_autoloaded_options_io_update', 'autoloaded_options', 0, 'CREATE TRIGGER site.site_autoloaded_options_io_update INSTEAD OF UPDATE ON autoloaded_options BEGIN UPDATE wp_options SET option_value = new.option_value WHERE blog_id = old.blog_id; END', 6),
]);

$trigger = SQLiteAttachTempWalSchemaTriggerPlan::walTriggerCookieCachePlan(
    $catalog,
    [['name' => '[site].[site_autoloaded_options_io_update]', 'source' => '[site]', 'active' => true]],
    [],
    ['[site]' => ['schema_cookie' => 7, 'wal_schema_cookie' => 8]],
);

$view = SQLiteAttachWalTempViewCachePlan::preparedViewCacheRepreparePlan(
    $catalog,
    [['name' => '[site].[autoloaded_options]', 'source' => '[site]', 'active' => true]],
    [],
    ['[site]' => ['schema_cookie' => 7, 'wal_schema_cookie' => 8]],
);

$summary = [
    'scenario' => 'wordpress attach temp wal trigger view cache reprepare',
    'wordpressUse' => 'Preview bracket-quoted attached schema names from copied WordPress SQL exports so prepared view and trigger cache checks compare current and next WAL schema cookies against the same attached source.',
    'triggerSource' => $trigger['source_schema'],
    'triggerWalSources' => $trigger['wal_schema_cookie_sources'],
    'triggerRequiresReprepare' => $trigger['requires_reprepare'],
    'triggerAction' => $trigger['triggers']['[site].[site_autoloaded_options_io_update]']['next_step_action'],
    'viewSource' => $view['source_schema'],
    'viewWalSources' => $view['wal_schema_cookie_sources'],
    'viewRequiresReprepare' => $view['requires_reprepare'],
    'dependencies' => array_values(array_unique(array_merge($trigger['dependencies'], $view['dependencies']))),
];

if (($argv[1] ?? '') === '--self-test') {
    if (
        $summary['triggerSource'] !== 'site'
        || $summary['viewSource'] !== 'site'
        || $summary['triggerWalSources'] !== ['site']
        || $summary['viewWalSources'] !== ['site']
        || $summary['triggerRequiresReprepare'] !== true
        || $summary['viewRequiresReprepare'] !== false
    ) {
        fwrite(STDERR, "wordpress-attach-temp-wal-trigger-view-cache-reprepare self-test failed\n");
        exit(1);
    }

    echo "wordpress-attach-temp-wal-trigger-view-cache-reprepare self-test passed\n";
    exit(0);
}

echo json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";

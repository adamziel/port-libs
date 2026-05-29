<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteAttachedSchemaCatalog;
use PortLibs\LibSqlite\SQLiteSchemaRecord;
use PortLibs\LibSqlite\SQLiteTriggerDeferredReturningViewCurrentSourceNextPlan;

require_once dirname(__DIR__, 3) . '/tools/bootstrap.php';

$record = static fn (string $type, string $name, string $table, ?int $root, ?string $sql, int $rowid): SQLiteSchemaRecord => new SQLiteSchemaRecord($type, $name, $table, $root, $sql, $rowid);
$catalog = new SQLiteAttachedSchemaCatalog([
    $record('table', 'wp_options', 'wp_options', 2, 'CREATE TABLE wp_options(option_id integer primary key, option_name text, option_value text, autoload text)', 1),
    $record('table', 'wp_optionmeta', 'wp_optionmeta', 3, 'CREATE TABLE wp_optionmeta(meta_id integer primary key, option_id integer, meta_key text)', 2),
    $record('table', 'wp_option_audit', 'wp_option_audit', 4, 'CREATE TABLE wp_option_audit(option_id integer, label text, option_name text)', 3),
    $record('view', 'wp_option_import_view', 'wp_option_import_view', 0, "CREATE VIEW wp_option_import_view AS SELECT option_id, option_name, option_value, autoload FROM wp_options WHERE autoload = 'yes'", 4),
    $record('trigger', 'wp_option_import_view_insert', 'wp_option_import_view', 0, "CREATE TRIGGER wp_option_import_view_insert INSTEAD OF INSERT ON wp_option_import_view BEGIN INSERT INTO wp_options(option_id, option_name, option_value, autoload) VALUES(new.option_id, new.option_name, new.option_value, new.autoload); INSERT INTO wp_option_audit(option_id, label, option_name) VALUES(new.option_id, 'view-import', new.option_name); SELECT new.option_id, new.option_name; END", 5),
]);

$tables = [
    'main.wp_options' => [
        ['option_id' => 1, 'option_name' => 'siteurl', 'option_value' => 'https://old.test', 'autoload' => 'yes'],
    ],
    'main.wp_optionmeta' => [
        ['meta_id' => 10, 'option_id' => 1, 'meta_key' => '_seed'],
        ['meta_id' => 20, 'option_id' => 2, 'meta_key' => '_current'],
        ['meta_id' => 30, 'option_id' => 99, 'meta_key' => '_orphan_after_import'],
    ],
    'main.wp_option_audit' => [
        ['option_id' => 1, 'label' => 'seed', 'option_name' => 'siteurl'],
    ],
];

$plan = SQLiteTriggerDeferredReturningViewCurrentSourceNextPlan::execute(
    $catalog,
    'wp_option_import_view_insert',
    $tables,
    [
        ['option_id' => 2, 'option_name' => 'home', 'option_value' => 'https://home.test', 'autoload' => 'yes'],
    ],
    [
        ['option_id' => 3, 'option_name' => 'active_plugins', 'option_value' => 'a:0:{}', 'autoload' => 'yes'],
    ],
    [
        'parent_table' => 'main.wp_options',
        'child_table' => 'main.wp_optionmeta',
        'parent_key' => 'option_id',
        'child_key' => 'option_id',
        'deferred' => true,
    ],
    'wp_view_import_127',
    ['option_id', 'option_name'],
    [
        'current_source' => 'main@view-cookie-127',
        'next_source' => 'main@view-cookie-128',
        'rollback_on_deferred_violation' => true,
    ],
);

$summary = [
    'scenario' => 'wordpress-trigger-deferred-returning-view-current-source-next127',
    'status' => $plan['status'],
    'releaseStatus' => $plan['release_status'],
    'sourceTransition' => $plan['source_transition'],
    'attemptedReturningNames' => array_column(array_column($plan['attempted_source_stream'], 'returning'), 'option_name'),
    'visibleReturningRows' => count($plan['returning_rows']),
    'deferredViolationKeys' => array_column($plan['deferred_violations'], 'child_key'),
    'finalOptionNames' => array_column($plan['tables']['main.wp_options'], 'option_name'),
    'wordpressUse' => 'Preview a copied wp_options import through an INSTEAD OF view trigger where RETURNING rows are attempted before deferred FK release decides whether current/next source rows are admitted or rolled back, without ext/sqlite.',
];

if (($argv[1] ?? null) === '--self-test') {
    if (
        $summary['status'] !== 'deferred-view-returning-rolled-back-to-current-source'
        || $summary['attemptedReturningNames'] !== ['home', 'active_plugins']
        || $summary['visibleReturningRows'] !== 0
        || $summary['deferredViolationKeys'] !== [99]
        || $summary['finalOptionNames'] !== ['siteurl']
    ) {
        fwrite(STDERR, "wordpress-trigger-deferred-returning-view-current-source-next127 self-test failed\n");
        exit(1);
    }

    echo "wordpress-trigger-deferred-returning-view-current-source-next127 self-test passed\n";
    return;
}

echo json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";

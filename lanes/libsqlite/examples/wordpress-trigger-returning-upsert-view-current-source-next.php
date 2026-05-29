<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/SQLiteTriggerReturningUpsertViewCurrentSourceNextPlan.php';

use PortLibs\LibSqlite\SQLiteTriggerReturningUpsertViewCurrentSourceNextPlan;

$rows = [
    ['option_id' => 1, 'option_name' => 'siteurl', 'option_value' => 'https://old.test', 'autoload' => 'yes', 'revision' => 1, 'source' => 'seed'],
    ['option_id' => 2, 'option_name' => 'home', 'option_value' => 'https://home.test', 'autoload' => 'yes', 'revision' => 1, 'source' => 'seed'],
];

$currentView = [
    'name' => 'wp_option_import_view',
    'source' => 'main@view-cookie-149-current',
    'trigger' => 'wp_option_import_view_io_insert',
    'trigger_source' => 'main@trigger-cookie-149-current',
    'columns' => ['import_id', 'name', 'value', 'autoload_flag'],
    'mapping' => ['import_id' => 'option_id', 'name' => 'option_name', 'value' => 'option_value', 'autoload_flag' => 'autoload'],
    'audit_label' => 'current-trigger-body',
];
$nextView = [
    'name' => 'wp_option_import_view',
    'source' => 'main@view-cookie-149-next',
    'trigger' => 'wp_option_import_view_io_insert',
    'trigger_source' => 'main@trigger-cookie-149-next',
    'columns' => ['import_id', 'name', 'value', 'autoload_flag', 'origin'],
    'mapping' => ['import_id' => 'option_id', 'name' => 'option_name', 'value' => 'option_value', 'autoload_flag' => 'autoload', 'origin' => 'source'],
    'audit_label' => 'next-trigger-body',
];

$assignments = [
    'option_id' => static fn (array $old, array $incoming, string $phase): mixed => $incoming['option_id'],
    'option_value' => static fn (array $old, array $incoming, string $phase): mixed => $incoming['option_value'],
    'autoload' => static fn (array $old, array $incoming, string $phase): mixed => $incoming['autoload'],
    'source' => static fn (array $old, array $incoming, string $phase): string => (string) ($incoming['source'] ?? $phase . '-trigger'),
    'revision' => static fn (array $old, array $incoming, string $phase): int => (int) $old['revision'] + 1,
];

$returning = [
    ['expr' => 'new.option_name', 'as' => 'name'],
    ['expr' => 'new.option_value', 'as' => 'value'],
    ['expr' => 'old.option_value', 'as' => 'old_value'],
    ['expr' => 'trigger_source', 'as' => 'trigger_source_alias'],
];

$plan = SQLiteTriggerReturningUpsertViewCurrentSourceNextPlan::execute(
    $rows,
    [
        ['import_id' => 11, 'name' => 'siteurl', 'value' => 'https://current.test', 'autoload_flag' => 'yes'],
        ['import_id' => 12, 'name' => 'fresh_plugin', 'value' => 'enabled', 'autoload_flag' => 'no'],
    ],
    [
        ['import_id' => 21, 'name' => 'home', 'value' => 'https://next-home.test', 'autoload_flag' => 'yes', 'origin' => 'next-import'],
        ['import_id' => 22, 'name' => 'rewrite_rules', 'value' => 'cached', 'autoload_flag' => 'yes', 'origin' => 'next-import'],
    ],
    $currentView,
    $nextView,
    ['option_name'],
    $assignments,
    $returning,
    ['key' => 'option_name', 'savepoint' => 'wp_import_view_149'],
);

if (($argv[1] ?? '') === '--self-test') {
    assert($plan['status'] === 'trigger-returning-upsert-view-current-source-pinned-next149');
    assert($plan['visible_view']['trigger_source'] === 'main@trigger-cookie-149-current');
    assert($plan['trigger_source_changed'] === true);
    assert($plan['next_returning_rows'] === []);
    assert(array_column(array_column($plan['attempted_next_returning_rows'], 'returning'), 'name') === ['home', 'rewrite_rules']);
    assert(array_column($plan['after_savepoint'], 'option_name') === ['siteurl', 'home']);
    echo "wordpress-trigger-returning-upsert-view-current-source-next self-test passed\n";
    return;
}

echo json_encode($plan, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";

<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteTriggerUpsertReturningViewCurrentSourceNextPlan;

$plan = SQLiteTriggerUpsertReturningViewCurrentSourceNextPlan::execute(
    [
        ['option_id' => 1, 'option_name' => 'siteurl', 'option_value' => 'https://old.test', 'autoload' => 'yes', 'revision' => 1, 'source' => 'seed'],
        ['option_id' => 2, 'option_name' => 'home', 'option_value' => 'https://home.test', 'autoload' => 'yes', 'revision' => 1, 'source' => 'seed'],
    ],
    [
        ['import_id' => 11, 'name' => 'siteurl', 'value' => 'https://current.test', 'autoload_flag' => 'yes'],
        ['import_id' => 12, 'name' => 'home', 'value' => 'https://skip.test', 'autoload_flag' => 'skip'],
        ['import_id' => 13, 'name' => 'fresh_plugin', 'value' => 'enabled', 'autoload_flag' => 'no'],
    ],
    [
        ['import_id' => 21, 'name' => 'home', 'value' => 'https://next.test', 'autoload_flag' => 'yes', 'origin' => 'next-import'],
    ],
    [
        'name' => 'wp_option_import_view',
        'source' => 'main@view-cookie-144-current',
        'columns' => ['import_id', 'name', 'value', 'autoload_flag'],
        'mapping' => ['import_id' => 'option_id', 'name' => 'option_name', 'value' => 'option_value', 'autoload_flag' => 'autoload'],
        'where' => static fn (array $old, array $incoming): bool => ($incoming['autoload'] ?? null) !== 'skip',
    ],
    [
        'name' => 'wp_option_import_view',
        'source' => 'main@view-cookie-144-next',
        'columns' => ['import_id', 'name', 'value', 'autoload_flag', 'origin'],
        'mapping' => ['import_id' => 'option_id', 'name' => 'option_name', 'value' => 'option_value', 'autoload_flag' => 'autoload', 'origin' => 'source'],
    ],
    ['option_name'],
    [
        'option_id' => static fn (array $old, array $incoming): mixed => $incoming['option_id'],
        'option_value' => static fn (array $old, array $incoming): mixed => $incoming['option_value'],
        'autoload' => static fn (array $old, array $incoming): mixed => $incoming['autoload'],
        'source' => static fn (array $old, array $incoming): mixed => $incoming['source'] ?? 'current-import',
        'revision' => static fn (array $old, array $incoming): int => (int) $old['revision'] + 1,
    ],
    [
        ['expr' => 'new.option_name', 'as' => 'name'],
        ['expr' => 'new.option_value', 'as' => 'value'],
        ['expr' => 'old_or_null.option_value', 'as' => 'oldValue'],
        ['expr' => 'source', 'as' => 'viewSource'],
    ],
    ['savepoint' => 'wp_import_view_144', 'trigger' => 'wp_options_view_io_upsert_144'],
);

$summary = [
    'scenario' => 'application-trigger-upsert-returning-view-current-source-next144',
    'applicationUse' => 'Preview a copied wp_options import routed through an INSTEAD OF view trigger where DO UPDATE WHERE skips suppress RETURNING rows and a held savepoint keeps the next view source out of the visible stream.',
    'status' => $plan['status'],
    'nextSourceAdmitted' => $plan['next_source_admitted'],
    'currentReturningNames' => array_column(array_column($plan['current_returning_rows'], 'returning'), 'name'),
    'skippedNames' => array_column(array_column($plan['current_skipped_rows'], 'incoming_row'), 'option_name'),
    'attemptedNextReturningNames' => array_column(array_column($plan['attempted_next_returning_rows'], 'returning'), 'name'),
    'afterSavepointNames' => array_column($plan['after_savepoint'], 'option_name'),
    'dependencies' => $plan['dependencies'],
];

if (in_array('--self-test', $argv, true)) {
    assert($summary['status'] === 'trigger-upsert-returning-view-current-source-retained-next144');
    assert($summary['nextSourceAdmitted'] === false);
    assert($summary['currentReturningNames'] === ['siteurl', 'fresh_plugin']);
    assert($summary['skippedNames'] === ['home']);
    assert($summary['attemptedNextReturningNames'] === ['home']);
    assert($summary['afterSavepointNames'] === ['siteurl', 'home']);
    echo "application-trigger-upsert-returning-view-current-source-next144 self-test passed\n";
    return;
}

echo json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";

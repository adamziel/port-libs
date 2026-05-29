<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteTriggerUpsertReturningSavepointCurrentSourceNextPlan;

require_once __DIR__ . '/../src/SQLiteTriggerUpsertReturningSavepointCurrentSourceNextPlan.php';

$plan = SQLiteTriggerUpsertReturningSavepointCurrentSourceNextPlan::execute(
    [
        ['option_id' => 1, 'option_name' => 'siteurl', 'option_value' => 'https://old.test', 'autoload' => 'yes', 'revision' => 1],
        ['option_id' => 2, 'option_name' => 'active_plugins', 'option_value' => 'a:0:{}', 'autoload' => 'yes', 'revision' => 1],
    ],
    [
        ['option_id' => 11, 'option_name' => 'siteurl', 'option_value' => 'https://current.test', 'autoload' => 'yes', 'revision' => 0],
        ['option_id' => 12, 'option_name' => 'bad_plugin', 'option_value' => 'bad', 'autoload' => 'no', 'revision' => 0],
    ],
    [
        ['option_id' => 21, 'option_name' => 'siteurl', 'option_value' => 'https://retry.test', 'autoload' => 'yes', 'revision' => 0],
        ['option_id' => 22, 'option_name' => 'good_plugin', 'option_value' => 'enabled', 'autoload' => 'no', 'revision' => 0],
    ],
    ['option_name'],
    [
        'option_id' => static fn (array $old, array $incoming): mixed => $incoming['option_id'],
        'option_value' => static fn (array $old, array $incoming): mixed => $incoming['option_value'],
        'autoload' => static fn (array $old, array $incoming): mixed => $incoming['autoload'],
        'revision' => static fn (array $old, array $incoming): mixed => $old['revision'] + 1,
    ],
    [
        [
            'name' => 'wp_options_bu_siteurl_suffix',
            'timing' => 'before',
            'event' => 'update',
            'action' => 'set-new',
            'when' => ['new.option_name', '=', 'siteurl'],
            'set' => ['option_value' => 'concat:new.option_value:/wp'],
            'values' => ['name' => 'new.option_name', 'value' => 'new.option_value'],
        ],
        [
            'name' => 'wp_options_ai_abort_bad_plugin',
            'timing' => 'after',
            'event' => 'insert',
            'action' => 'raise',
            'when' => ['new.option_name', '=', 'bad_plugin'],
            'reason' => 'blocked-plugin-option-after-returning',
            'values' => ['name' => 'new.option_name'],
        ],
    ],
    [
        ['expr' => 'new.option_name', 'as' => 'name'],
        ['expr' => 'new.option_id', 'as' => 'id'],
        ['expr' => 'old_or_null.option_id', 'as' => 'old_id'],
        ['expr' => 'excluded.option_value', 'as' => 'incoming_value'],
    ],
    ['savepoint' => 'wp_import_current', 'wal_frame' => 40],
);

if (in_array('--self-test', $argv, true)) {
    $ok = $plan['status'] === 'current-rolled-back-next-source-applied'
        && $plan['current_returning_rows'] === []
        && $plan['returning_suppressed_after_rollback'] === true
        && $plan['next_started_from_savepoint'] === true
        && array_column($plan['next_rows'], 'option_name') === ['siteurl', 'active_plugins', 'good_plugin']
        && $plan['next_returning_rows'][0]['incoming_value'] === 'https://retry.test';

    if (!$ok) {
        fwrite(STDERR, "wordpress-trigger-upsert-returning-savepoint-current-source-next129 self-test failed\n");
        exit(1);
    }

    echo "wordpress-trigger-upsert-returning-savepoint-current-source-next129 self-test passed\n";
    exit(0);
}

echo json_encode([
    'scenario' => 'wordpress-trigger-upsert-returning-savepoint-current-source-next129',
    'status' => $plan['status'],
    'suppressed_current_returning' => $plan['returning_suppressed_after_rollback'],
    'next_names' => array_column($plan['next_rows'], 'option_name'),
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;

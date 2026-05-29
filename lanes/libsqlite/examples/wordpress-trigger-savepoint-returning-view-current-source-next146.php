<?php

declare(strict_types=1);

require __DIR__ . '/../../../tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteTriggerSavepointReturningViewCurrentSourceNextPlan;

$plan = SQLiteTriggerSavepointReturningViewCurrentSourceNextPlan::executeNext146(
    [
        ['option_id' => 1, 'blog_id' => 1, 'option_name' => 'siteurl', 'option_value' => 'https://old.test', 'autoload' => 'yes'],
        ['option_id' => 2, 'blog_id' => 1, 'option_name' => 'home', 'option_value' => 'https://home.test', 'autoload' => 'yes'],
    ],
    [
        ['import_id' => 11, 'blog' => 1, 'name' => 'siteurl', 'value' => 'https://current.test', 'autoload_flag' => 'yes'],
        ['import_id' => 12, 'blog' => 1, 'name' => 'plugin_seed', 'value' => 'seed', 'autoload_flag' => 'no'],
    ],
    [
        ['import_id' => 21, 'blog' => 1, 'name' => 'plugin_seed', 'value' => 'seed-next', 'autoload_flag' => 'no'],
        ['import_id' => 22, 'blog' => 1, 'name' => 'theme_mods', 'value' => 'theme', 'autoload_flag' => 'yes'],
    ],
    ['import_id' => 'option_id', 'blog' => 'blog_id', 'name' => 'option_name', 'value' => 'option_value', 'autoload_flag' => 'autoload'],
    ['blog_id', 'option_name'],
    [[
        'name' => 'wp_options_view_bi_prepare',
        'timing' => 'before',
        'event' => 'insert',
        'action' => 'set-new',
        'when' => ['new.autoload', '=', 'no'],
        'set' => ['option_value' => 'concat:new.option_value:-prepared'],
        'values' => ['name' => 'new.option_name'],
    ]],
    [
        ['expr' => 'new.option_name', 'as' => 'name'],
        ['expr' => 'new.option_value', 'as' => 'value'],
        ['expr' => 'old.option_value', 'as' => 'old_value'],
    ],
    [
        'savepoint' => 'wp_import_view_146',
        'view' => 'wp_option_import_view',
        'current_source' => 'wp-options-current146',
        'next_source' => 'wp-options-next146',
        'rollback_current' => true,
    ],
);

echo json_encode([
    'status' => $plan['status'],
    'savepoint' => $plan['savepoint'],
    'next_started_from' => $plan['source_transition']['next_started_from'],
    'returning_names' => array_column($plan['returning_rows'], 'name'),
    'final_option_names' => array_column($plan['rows'], 'option_name'),
    'committed_changes' => $plan['committed_changes'],
], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR) . PHP_EOL;

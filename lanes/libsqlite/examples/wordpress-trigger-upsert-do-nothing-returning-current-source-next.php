<?php

declare(strict_types=1);

require __DIR__ . '/../src/SQLiteTriggerUpsertDoNothingReturningSavepointCurrentSourceNextPlan.php';

use PortLibs\LibSqlite\SQLiteTriggerUpsertDoNothingReturningSavepointCurrentSourceNextPlan;

$plan = SQLiteTriggerUpsertDoNothingReturningSavepointCurrentSourceNextPlan::execute(
    [
        ['option_id' => 1, 'blog_id' => 1, 'option_name' => 'siteurl', 'option_value' => 'https://old.test', 'autoload' => 'yes'],
        ['option_id' => 2, 'blog_id' => 1, 'option_name' => 'home', 'option_value' => 'https://home.test', 'autoload' => 'yes'],
    ],
    [
        ['option_id' => 10, 'blog_id' => 1, 'option_name' => 'siteurl', 'option_value' => 'https://duplicate.test', 'autoload' => 'yes'],
        ['option_id' => 11, 'blog_id' => 1, 'option_name' => 'plugin_seed', 'option_value' => 'seed', 'autoload' => 'no'],
    ],
    [
        ['option_id' => 20, 'blog_id' => 1, 'option_name' => 'plugin_seed', 'option_value' => 'seed-next', 'autoload' => 'no'],
        ['option_id' => 21, 'blog_id' => 1, 'option_name' => 'theme_mods', 'option_value' => 'theme', 'autoload' => 'yes'],
    ],
    ['blog_id', 'option_name'],
    [
        [
            'name' => 'wp_options_bi_prepare',
            'timing' => 'before',
            'event' => 'insert',
            'action' => 'set-new',
            'when' => ['new.autoload', '=', 'no'],
            'set' => ['option_value' => 'concat:new.option_value:-prepared'],
            'values' => ['name' => 'new.option_name', 'value' => 'new.option_value'],
        ],
        [
            'name' => 'wp_options_ai_audit',
            'timing' => 'after',
            'event' => 'insert',
            'action' => 'audit',
            'values' => ['name' => 'new.option_name', 'value' => 'new.option_value'],
        ],
    ],
    [
        ['expr' => 'new.option_id', 'as' => 'id'],
        ['expr' => 'new.option_name', 'as' => 'name'],
        ['expr' => 'new.option_value', 'as' => 'value'],
    ],
    [
        'savepoint' => 'wp_import_do_nothing_142',
        'current_source' => 'wp-options-current142',
        'next_source' => 'wp-options-next142',
    ],
);

$summary = [
    'scenario' => 'wordpress-trigger-upsert-do-nothing-returning-current-source-next',
    'wordpressUse' => 'Preview copied wp_options import batches that use INSERT ... ON CONFLICT DO NOTHING RETURNING inside a savepoint: duplicate option rows fire BEFORE trigger diagnostics but yield no RETURNING row, while the next source sees either the released current insert or the savepoint image.',
    'status' => $plan['status'],
    'currentReturned' => array_column($plan['current_returning_rows'], 'name'),
    'currentSkipped' => array_column(array_column($plan['current_skipped_conflicts'], 'incoming'), 'option_name'),
    'nextReturned' => array_column($plan['next_returning_rows'], 'name'),
    'nextSkipped' => array_column(array_column($plan['next_skipped_conflicts'], 'incoming'), 'option_name'),
    'committedChanges' => $plan['committed_changes'],
    'dependencyClosure' => 'no new support component needed; this reuses native PHP trigger, UPSERT conflict, RETURNING projection, and savepoint current-source planning',
];

if (PHP_SAPI === 'cli' && in_array('--self-test', $argv, true)) {
    assert($summary['status'] === 'trigger-upsert-do-nothing-returning-current-source-next142-released');
    assert($summary['currentReturned'] === ['plugin_seed']);
    assert($summary['currentSkipped'] === ['siteurl']);
    assert($summary['nextReturned'] === ['theme_mods']);
    assert($summary['nextSkipped'] === ['plugin_seed']);
    assert($summary['committedChanges'] === 2);
    echo "wordpress-trigger-upsert-do-nothing-returning-current-source-next self-test passed\n";
    return;
}

echo json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;

<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteTriggerUpsertSavepointReturningCurrentSourceNextPlan;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$rows = [
    ['option_id' => 1, 'option_name' => 'siteurl', 'option_value' => 'https://old.test', 'autoload' => 'yes', 'revision' => 1, 'source' => 'seed'],
    ['option_id' => 2, 'option_name' => 'theme_mods', 'option_value' => 'a:0:{}', 'autoload' => 'no', 'revision' => 2, 'source' => 'seed'],
];
$incoming = [
    ['option_id' => 3, 'option_name' => 'plugin_seed', 'option_value' => '{"enabled":true}', 'autoload' => 'no', 'revision' => 1, 'source' => 'import'],
    ['option_id' => 4, 'option_name' => 'siteurl', 'option_value' => 'https://new.test', 'autoload' => 'yes', 'revision' => 2, 'source' => 'import'],
    ['option_id' => 5, 'option_name' => 'theme_mods', 'option_value' => 'broken-trigger', 'autoload' => 'no', 'revision' => 3, 'source' => 'import'],
];
$assignments = [
    'option_id' => static fn (array $old, array $incoming): int => $old['option_id'],
    'option_value' => static fn (array $old, array $incoming): string => $incoming['option_value'],
    'autoload' => static fn (array $old, array $incoming): string => $incoming['autoload'],
    'revision' => static fn (array $old, array $incoming): int => $old['revision'] + 1,
    'source' => static fn (array $old, array $incoming): string => $incoming['source'],
];
$triggers = [
    [
        'name' => 'wp_options_au_revision_stamp',
        'timing' => 'after',
        'event' => 'update',
        'mutate_target' => true,
        'set' => ['source' => 'after-trigger'],
        'values' => ['name' => 'new.option_name'],
    ],
    [
        'name' => 'wp_options_au_theme_guard',
        'timing' => 'after',
        'event' => 'update',
        'when' => ['new.option_name', '=', 'theme_mods'],
        'raise' => 'rollback',
        'reason' => 'theme mods trigger abort',
        'values' => ['name' => 'new.option_name'],
    ],
];

$plan = SQLiteTriggerUpsertSavepointReturningCurrentSourceNextPlan::executeWithinSavepoint(
    'wp_import_batch',
    $rows,
    $incoming,
    ['option_name'],
    $assignments,
    $triggers,
    ['option_id', 'option_name', 'option_value', 'source', ['expr' => 'new.revision', 'as' => 'next_revision']]
);

if (PHP_SAPI === 'cli' && ($argv[1] ?? null) === '--self-test') {
    assert($plan['rolled_back_to_savepoint'] === true);
    assert(array_column($plan['current_returning'], 'option_name') === ['plugin_seed', 'siteurl', 'theme_mods']);
    assert($plan['next_returning'] === []);
    assert(array_column($plan['after_savepoint'], 'option_name') === ['siteurl', 'theme_mods']);
    assert($plan['discarded_returning_count'] === 3);
    echo "wordpress-trigger-upsert-savepoint-returning-current-source-next132 self-test passed\n";
    return;
}

echo json_encode([
    'scenario' => 'wordpress-trigger-upsert-savepoint-returning-current-source-next132',
    'wordpressUse' => 'Preview a copied wp_options import where UPSERT RETURNING diagnostics are yielded from the current row image before AFTER-trigger side effects, then a later trigger RAISE(ROLLBACK) restores the import savepoint and clears the next statement RETURNING stream.',
    'savepoint' => $plan['savepoint'],
    'rolledBack' => $plan['rolled_back_to_savepoint'],
    'rollbackReason' => $plan['rollback_reason'],
    'currentReturned' => array_column($plan['current_returning'], 'option_name'),
    'nextReturned' => array_column($plan['next_returning'], 'option_name'),
    'restoredRows' => array_column($plan['after_savepoint'], 'option_name'),
    'discardedReturningCount' => $plan['discarded_returning_count'],
    'dependencyClosure' => 'no new support component needed; this composes native PHP UPSERT conflict routing, trigger row images, RETURNING projection, and savepoint rollback evidence',
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";

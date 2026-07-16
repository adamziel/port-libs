<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteRecursiveTriggerDepthPlan;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$trigger = [[
    'timing' => 'after',
    'event' => 'insert',
    'table' => 'target',
    'action' => 'insert',
    'when' => ['column' => 'level', 'operator' => '<', 'value' => 8],
    'insert_row' => [
        'option_id' => 'new_increment.option_id',
        'option_name' => 'concat:new.option_name::child',
        'level' => 'new_increment.level',
        'autoload' => 'new.autoload',
    ],
]];

$result = SQLiteRecursiveTriggerDepthPlan::insertRows(
    [['option_id' => 1, 'option_name' => 'siteurl', 'level' => 0, 'autoload' => 'yes']],
    [['option_id' => 10, 'option_name' => 'plugin_seed', 'level' => 1, 'autoload' => 'yes']],
    $trigger,
    ['option_name'],
    'abort',
    ['max_depth' => 3, 'on_limit' => 'rollback', 'rollback_rows' => [['option_id' => 1, 'option_name' => 'siteurl', 'level' => 0, 'autoload' => 'yes']]]
);

echo json_encode([
    'applicationUse' => 'Preview SQLite trigger recursion current-depth/next-depth enforcement for copied wp_options import triggers before entering an over-limit trigger program.',
    'optionNames' => array_column($result['rows'], 'option_name'),
    'limitHit' => $result['limit_hit'],
    'limitReason' => $result['limit_reason'],
    'rollbackScope' => $result['rollback_scope'],
    'maxObservedDepth' => $result['max_observed_depth'],
    'depthChecks' => array_map(static fn (array $check): array => [
        'current' => $check['current_depth'],
        'next' => $check['next_depth'],
        'allowed' => $check['allowed'],
        'optionName' => $check['row']['option_name'],
    ], $result['depth_checks']),
    'blockedEffects' => array_values(array_filter($result['effects'], static fn (array $effect): bool => $effect['result'] === 'depth-limit-blocked')),
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";

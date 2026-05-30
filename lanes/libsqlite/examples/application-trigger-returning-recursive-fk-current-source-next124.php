<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/SQLiteTriggerReturningRecursiveFkCurrentSourceNextPlan.php';

use PortLibs\LibSqlite\SQLiteTriggerReturningRecursiveFkCurrentSourceNextPlan;

$parents = [
    ['option_id' => 1, 'next_id' => 2, 'option_name' => 'siteurl'],
    ['option_id' => 2, 'next_id' => 3, 'option_name' => 'home'],
    ['option_id' => 3, 'next_id' => null, 'option_name' => 'blogname'],
    ['option_id' => 4, 'next_id' => null, 'option_name' => 'kept_plugin'],
];
$children = [
    ['meta_id' => 11, 'option_id' => 1],
    ['meta_id' => 12, 'option_id' => 2],
    ['meta_id' => 13, 'option_id' => 2],
    ['meta_id' => 14, 'option_id' => 3],
    ['meta_id' => 15, 'option_id' => 4],
];
$grandchildren = [
    ['detail_id' => 101, 'option_id' => 1],
    ['detail_id' => 102, 'option_id' => 2],
    ['detail_id' => 103, 'option_id' => 2],
    ['detail_id' => 104, 'option_id' => 3],
    ['detail_id' => 105, 'option_id' => 4],
];

$plan = SQLiteTriggerReturningRecursiveFkCurrentSourceNextPlan::delete(
    $parents,
    $children,
    $grandchildren,
    ['parent_key' => 'option_id', 'child_key' => 'option_id', 'grandchild_key' => 'option_id', 'deferred' => true, 'on_delete' => 'cascade'],
    [
        'savepoint' => 'wp_recursive_delete',
        'current_source' => 'main@cookie-124',
        'next_source' => 'main@cookie-125',
        'where' => static fn (array $row): bool => $row['option_id'] === 1,
        'trigger' => ['name' => 'wp_options_ad_recursive_delete', 'match_column' => 'option_id', 'match_value' => 'old.next_id'],
        'rollback_to_savepoint' => true,
        'returning' => [
            ['expr' => 'old.option_id', 'as' => 'deleted_id'],
            'option_name',
            ['expr' => 'context.source', 'as' => 'source_token'],
            ['expr' => 'context.trigger_depth', 'as' => 'depth'],
        ],
    ],
);

if (PHP_SAPI === 'cli' && basename(__FILE__) === basename($_SERVER['SCRIPT_FILENAME'] ?? '')) {
    if ($plan['status'] !== 'rolled-back-to-savepoint-after-returning-yield' || $plan['deleted_parent_keys'] !== [1, 2, 3] || $plan['next_parent_keys'] !== [1, 2, 3, 4]) {
        fwrite(STDERR, "application-trigger-returning-recursive-fk-current-source-next124 self-test failed\n");
        exit(1);
    }

    echo "application-trigger-returning-recursive-fk-current-source-next124 self-test passed\n";
}

return [
    'scenario' => 'application-trigger-returning-recursive-fk-current-source-next124',
    'applicationUse' => 'Preview copied wp_options recursive DELETE triggers whose RETURNING rows are yielded from the current source before FK CASCADE deletes child rows and before a savepoint rollback restores the next source.',
    'status' => $plan['status'],
    'deletedParentKeys' => $plan['deleted_parent_keys'],
    'cascadeActions' => count($plan['cascade_actions']),
    'attemptedReturningRows' => count($plan['attempted_returning_rows']),
    'nextParentKeys' => $plan['next_parent_keys'],
    'dependencies' => $plan['dependencies'],
];

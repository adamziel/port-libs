<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/SQLiteTriggerReturningRecursiveFkCurrentSourceNextPlan.php';

use PortLibs\LibSqlite\SQLiteTriggerReturningRecursiveFkCurrentSourceNextPlan;

$parents = [
    ['setting_id' => 1, 'next_id' => 2, 'key_name' => 'base_url'],
    ['setting_id' => 2, 'next_id' => 3, 'key_name' => 'public_url'],
    ['setting_id' => 3, 'next_id' => null, 'key_name' => 'site_title'],
    ['setting_id' => 4, 'next_id' => null, 'key_name' => 'kept_module'],
];
$children = [
    ['meta_id' => 11, 'setting_id' => 1],
    ['meta_id' => 12, 'setting_id' => 2],
    ['meta_id' => 13, 'setting_id' => 2],
    ['meta_id' => 14, 'setting_id' => 3],
    ['meta_id' => 15, 'setting_id' => 4],
];
$grandchildren = [
    ['detail_id' => 101, 'setting_id' => 1],
    ['detail_id' => 102, 'setting_id' => 2],
    ['detail_id' => 103, 'setting_id' => 2],
    ['detail_id' => 104, 'setting_id' => 3],
    ['detail_id' => 105, 'setting_id' => 4],
];

$plan = SQLiteTriggerReturningRecursiveFkCurrentSourceNextPlan::delete(
    $parents,
    $children,
    $grandchildren,
    ['parent_key' => 'setting_id', 'child_key' => 'setting_id', 'grandchild_key' => 'setting_id', 'deferred' => true, 'on_delete' => 'cascade'],
    [
        'savepoint' => 'app_recursive_delete',
        'current_source' => 'main@cookie-124',
        'next_source' => 'main@cookie-125',
        'where' => static fn (array $row): bool => $row['setting_id'] === 1,
        'trigger' => ['name' => 'app_settings_ad_recursive_delete', 'match_column' => 'setting_id', 'match_value' => 'old.next_id'],
        'rollback_to_savepoint' => true,
        'returning' => [
            ['expr' => 'old.setting_id', 'as' => 'deleted_id'],
            'key_name',
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
    'applicationUse' => 'Preview copied app_settings recursive DELETE triggers whose RETURNING rows are yielded from the current source before FK CASCADE deletes child rows and before a savepoint rollback restores the next source.',
    'status' => $plan['status'],
    'deletedParentKeys' => $plan['deleted_parent_keys'],
    'cascadeActions' => count($plan['cascade_actions']),
    'attemptedReturningRows' => count($plan['attempted_returning_rows']),
    'nextParentKeys' => $plan['next_parent_keys'],
    'dependencies' => $plan['dependencies'],
];

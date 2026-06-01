<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteDmlTriggerCurrentNextPlan;
use PortLibs\LibSqlite\SQLiteJsonUpsertMigrationPlan;
use PortLibs\LibSqlite\SQLiteRecursiveUpsertConflictYieldPlan;
use PortLibs\LibSqlite\SQLiteTriggerDeferredReturningSavepointCurrentSourceNextPlan;
use PortLibs\LibSqlite\SQLiteTriggerRecursiveDeferredReturningCurrentSourceNextPlan;
use PortLibs\LibSqlite\SQLiteTriggerRecursiveReturningDeferredFkCurrentSourceNextPlan;
use PortLibs\LibSqlite\SQLiteTriggerRecursiveUpsertReturningCurrentSourceNextPlan;
use PortLibs\LibSqlite\SQLiteTriggerReturningRecursiveDeferredViewCurrentSourceNextPlan;
use PortLibs\LibSqlite\SQLiteTriggerReturningFkDeleteSavepointCurrentSourceNextPlan;
use PortLibs\LibSqlite\SQLiteTriggerReturningFkSavepointCurrentNextPlan;
use PortLibs\LibSqlite\SQLiteTriggerReturningRecursiveFkCurrentSourceNextPlan;
use PortLibs\LibSqlite\SQLiteTriggerSavepointReturningRecursiveCurrentSourceNextPlan;
use PortLibs\LibSqlite\SQLiteTriggerUpsertRecursiveViewCurrentSourceNextPlan;
use PortLibs\LibSqlite\SQLiteTriggerUpsertReturningRecursiveCurrentSourceNextPlan;
use PortLibs\LibSqlite\SQLiteUpdateDeleteTriggerOrderPlan;
use PortLibs\LibSqlite\SQLiteUpsertReturningTriggerPlan;

$libsqliteRoot = dirname(__DIR__);
$sourceRoot = $libsqliteRoot . '/src';

$sourceFiles = [
    $sourceRoot . '/SQLiteDmlTriggerCurrentNextPlan.php',
    $sourceRoot . '/SQLiteJsonUpsertMigrationPlan.php',
    $sourceRoot . '/SQLiteRecursiveUpsertConflictYieldPlan.php',
    $sourceRoot . '/SQLiteTriggerDeferredReturningSavepointCurrentSourceNextPlan.php',
    $sourceRoot . '/SQLiteTriggerRecursiveDeferredReturningCurrentSourceNextPlan.php',
    $sourceRoot . '/SQLiteTriggerRecursiveReturningDeferredFkCurrentSourceNextPlan.php',
    $sourceRoot . '/SQLiteTriggerRecursiveUpsertReturningCurrentSourceNextPlan.php',
    $sourceRoot . '/SQLiteTriggerReturningRecursiveDeferredViewCurrentSourceNextPlan.php',
    $sourceRoot . '/SQLiteTriggerReturningFkDeleteSavepointCurrentSourceNextPlan.php',
    $sourceRoot . '/SQLiteTriggerReturningFkSavepointCurrentNextPlan.php',
    $sourceRoot . '/SQLiteTriggerReturningRecursiveFkCurrentSourceNextPlan.php',
    $sourceRoot . '/SQLiteTriggerSavepointReturningRecursiveCurrentSourceNextPlan.php',
    $sourceRoot . '/SQLiteTriggerUpsertRecursiveViewCurrentSourceNextPlan.php',
    $sourceRoot . '/SQLiteTriggerUpsertReturningRecursiveCurrentSourceNextPlan.php',
    $sourceRoot . '/SQLiteUpdateDeleteTriggerOrderPlan.php',
    $sourceRoot . '/SQLiteUpsertReturningTriggerPlan.php',
];

$legacyDomainMatches = static function () use ($sourceFiles, $libsqliteRoot): array {
    $terms = [
        'wp' . '_',
        'wp' . '_options',
        'opt' . 'ion_id',
        'opt' . 'ion_name',
        'opt' . 'ion_value',
        'auto' . 'load',
        'blog' . '_id',
        'blog' . 'Id',
        'Blog' . 'Id',
    ];
    $pattern = '/(?:' . implode('|', array_map(static fn (string $term): string => preg_quote($term, '/'), $terms)) . ')/';
    $matches = [];

    foreach ($sourceFiles as $file) {
        $contents = file_get_contents($file);
        if ($contents === false) {
            throw new RuntimeException("Unable to read {$file}");
        }
        if (preg_match_all($pattern, $contents, $fileMatches) < 1) {
            continue;
        }
        $relative = str_replace($libsqliteRoot . '/', '', $file);
        foreach ($fileMatches[0] as $match) {
            $matches[] = "{$relative}: {$match}";
        }
    }

    return $matches;
};

$settingRows = [
    ['setting_id' => 1, 'key_name' => 'base_url', 'key_value' => 'https://old.test', 'load_policy' => 'yes', 'revision' => 1],
    ['setting_id' => 2, 'key_name' => 'cache_policy', 'key_value' => 'stale', 'load_policy' => 'no', 'revision' => 2],
];
$recursiveRows = [
    ['setting_id' => 1, 'key_name' => 'base_url', 'key_value' => 'https://old.test', 'load_policy' => 'yes', 'revision' => 1, 'depth' => 0],
    ['setting_id' => 2, 'key_name' => 'module_seed', 'key_value' => 'seed-old', 'load_policy' => 'no', 'revision' => 2, 'depth' => 1],
];
$recursiveAssignments = [
    'key_value' => static fn (array $old, array $incoming): mixed => $incoming['key_value'],
    'load_policy' => static fn (array $old, array $incoming): mixed => $incoming['load_policy'],
    'revision' => static fn (array $old, array $incoming): int => (int) $old['revision'] + (int) $incoming['revision'],
    'depth' => static fn (array $old, array $incoming): mixed => $incoming['depth'],
];
$recursiveTriggers = [[
    'name' => 'app_settings_after_upsert_child',
    'timing' => 'after',
    'event' => 'update',
    'action' => 'upsert-parent',
    'when' => ['new.depth', '<', 2],
    'row' => [
        'setting_id' => 3,
        'key_name' => ['concat' => ['new.key_name', '_child']],
        'key_value' => ['concat' => ['new.key_value', ':child']],
        'load_policy' => 'new.load_policy',
        'revision' => 1,
        'depth' => ['add' => ['new.depth', 1]],
    ],
    'values' => ['name' => 'new.key_name', 'value' => 'new.key_value'],
]];
$recursiveReturning = [
    'key_name',
    ['expr' => 'new.key_value', 'as' => 'value'],
    ['expr' => 'event', 'as' => 'event_name'],
];
$trigger = [
    'name' => 'app_settings_after_update',
    'timing' => 'after',
    'event' => 'update',
    'table' => 'app_settings',
    'of' => ['key_value'],
    'values' => ['setting_id' => 'new.setting_id', 'name' => 'new.key_name', 'new_value' => 'new.key_value'],
];

return [
    'source-neutral dml trigger source files contain no legacy domain strings' => static fn (TestRunner $t) => $t->same([], $legacyDomainMatches()),
    'dml trigger default row id is setting id' => static function (TestRunner $t) use ($settingRows, $trigger): void {
        $plan = SQLiteDmlTriggerCurrentNextPlan::insertRows(
            $settingRows,
            [['setting_id' => null, 'key_name' => 'site_title', 'key_value' => 'Example Site', 'load_policy' => 'yes', 'revision' => 1]],
            [[
                'name' => 'app_settings_after_insert',
                'timing' => 'after',
                'event' => 'insert',
                'table' => 'app_settings',
                'values' => ['setting_id' => 'new.setting_id', 'name' => 'new.key_name'],
            ]],
        );

        $t->same([3], $plan['visited']);
        $t->same('site_title', $plan['audit'][0]['name']);
        $t->same(3, $plan['audit'][0]['setting_id']);

        $updated = SQLiteUpdateDeleteTriggerOrderPlan::updateRows(
            $settingRows,
            ['key_value' => 'fresh'],
            static fn (array $row): bool => $row['key_name'] === 'cache_policy',
            [$trigger],
        );

        $t->same([2], $updated['visited']);
        $t->same('cache_policy', $updated['audit'][0]['name']);
        $t->same('fresh', $updated['audit'][0]['new_value']);
    },
    'upsert returning trigger accepts application settings target' => static function (TestRunner $t) use ($settingRows): void {
        $plan = SQLiteUpsertReturningTriggerPlan::execute(
            $settingRows,
            [
                ['setting_id' => 9, 'key_name' => 'base_url', 'key_value' => 'https://new.test', 'load_policy' => 'yes', 'revision' => 4],
                ['setting_id' => 10, 'key_name' => 'module_registry', 'key_value' => 'enabled', 'load_policy' => 'no', 'revision' => 1],
            ],
            ['key_name'],
            [
                'key_value' => static fn (array $current, array $excluded): mixed => $excluded['key_value'],
                'load_policy' => static fn (array $current, array $excluded): mixed => $excluded['load_policy'],
                'revision' => static fn (array $current, array $excluded): int => (int) $current['revision'] + (int) $excluded['revision'],
            ],
            [[
                'name' => 'app_settings_after_upsert',
                'timing' => 'after',
                'event' => 'update',
                'table' => 'app_settings',
                'values' => ['name' => 'new.key_name', 'value' => 'new.key_value'],
            ], [
                'name' => 'app_settings_after_insert',
                'timing' => 'after',
                'event' => 'insert',
                'table' => 'app_settings',
                'values' => ['name' => 'new.key_name', 'value' => 'new.key_value'],
            ]],
            null,
            [['key_name']],
        );

        $t->same(2, $plan['changes']);
        $t->same(['base_url', 'module_registry'], array_column($plan['returning_rows'], 'key_name'));
        $t->same(['app_settings_after_upsert', 'app_settings_after_insert'], array_column($plan['trigger_effects'], 'trigger'));
    },
    'json upsert migration defaults use generic key value columns' => static function (TestRunner $t): void {
        $plan = SQLiteJsonUpsertMigrationPlan::execute(
            [
                [
                    'setting_id' => 1,
                    'key_name' => 'base_url',
                    'key_value' => '{"source":"current","version":1}',
                    'load_policy' => 'yes',
                    'migration_generation' => 1,
                ],
            ],
            [
                [
                    'setting_id' => 2,
                    'key_name' => 'base_url',
                    'key_value' => '{"source":"incoming","version":2}',
                    'load_policy' => 'no',
                    'migration_generation' => 3,
                ],
            ],
            [
                '$.source' => ['excluded_json' => '$.source'],
                '$.previous_source' => ['current_json' => '$.source'],
                '$.load_policy_after' => ['excluded_column' => 'load_policy'],
            ],
        );

        $t->same(['base_url'], array_column($plan['returning_rows'], 'key_name'));
        $t->same('no', $plan['returning_rows'][0]['load_policy']);
        $t->same('incoming', $plan['decoded_returning'][0]['decoded_key_value']['source']);
        $t->same('current', $plan['decoded_returning'][0]['decoded_key_value']['previous_source']);
        $t->same('no', $plan['decoded_returning'][0]['decoded_key_value']['load_policy_after']);
    },
    'recursive upsert defaults expose generic setting keys' => static function (TestRunner $t) use ($recursiveRows, $recursiveAssignments, $recursiveTriggers, $recursiveReturning): void {
        $plan = SQLiteRecursiveUpsertConflictYieldPlan::execute(
            $recursiveRows,
            [['setting_id' => 9, 'key_name' => 'module_seed', 'key_value' => 'seed-new', 'load_policy' => 'yes', 'revision' => 3, 'depth' => 1]],
            ['key_name'],
            $recursiveAssignments,
            $recursiveTriggers,
            ['returning' => $recursiveReturning],
        );

        $t->same(['module_seed', 'module_seed_child'], array_column($plan['yielded'], 'new_key'));
        $t->same(['seed-new', 'seed-new:child'], array_column($plan['yielded'], 'new_value'));
        $t->same(['module_seed', 'module_seed_child'], array_column($plan['returning'], 'key_name'));
        $t->same(['app_settings_after_upsert_child'], array_column($plan['trigger_effects'], 'trigger'));
    },
    'recursive current source handoff uses generic key column' => static function (TestRunner $t) use ($recursiveRows, $recursiveAssignments, $recursiveTriggers, $recursiveReturning): void {
        $plan = SQLiteTriggerRecursiveUpsertReturningCurrentSourceNextPlan::execute(
            $recursiveRows,
            [['setting_id' => 9, 'key_name' => 'module_seed', 'key_value' => 'seed-current', 'load_policy' => 'yes', 'revision' => 2, 'depth' => 1]],
            [['setting_id' => 10, 'key_name' => 'module_seed_child', 'key_value' => 'seed-next', 'load_policy' => 'yes', 'revision' => 4, 'depth' => 2]],
            ['key_name'],
            $recursiveAssignments,
            $recursiveTriggers,
            ['returning' => $recursiveReturning],
        );

        $t->same(['module_seed', 'module_seed_child'], $plan['handoff']['returning_keys']);
        $t->same(true, $plan['handoff']['next_source_contains_all_returning_keys']);
        $t->same(['module_seed_child'], array_column(array_column($plan['next_returning_rows'], 'returning'), 'key_name'));
    },
    'savepoint recursive returning defaults are application tokens' => static function (TestRunner $t) use ($recursiveRows, $recursiveAssignments, $recursiveTriggers, $recursiveReturning): void {
        $plan = SQLiteTriggerSavepointReturningRecursiveCurrentSourceNextPlan::execute(
            $recursiveRows,
            [['setting_id' => 9, 'key_name' => 'module_seed', 'key_value' => 'seed-current', 'load_policy' => 'yes', 'revision' => 2, 'depth' => 1]],
            [['setting_id' => 10, 'key_name' => 'module_seed_child', 'key_value' => 'seed-next', 'load_policy' => 'yes', 'revision' => 4, 'depth' => 2]],
            ['key_name'],
            $recursiveAssignments,
            $recursiveTriggers,
            ['returning' => $recursiveReturning],
        );

        $t->same('app_import_trigger_batch', $plan['savepoint']);
        $t->same(['module_seed', 'module_seed_child'], array_column($plan['attempted_current_returning_rows'], 'key_name'));
        $t->same([], $plan['current_returning_rows']);
    },
    'recursive upsert rollback barrier uses generic key column' => static function (TestRunner $t) use ($recursiveRows, $recursiveAssignments, $recursiveTriggers, $recursiveReturning): void {
        $plan = SQLiteTriggerUpsertReturningRecursiveCurrentSourceNextPlan::execute(
            $recursiveRows,
            [['setting_id' => 9, 'key_name' => 'module_seed', 'key_value' => 'seed-current', 'load_policy' => 'yes', 'revision' => 2, 'depth' => 1]],
            [['setting_id' => 10, 'key_name' => 'module_seed_child', 'key_value' => 'seed-next', 'load_policy' => 'yes', 'revision' => 4, 'depth' => 2]],
            ['key_name'],
            $recursiveAssignments,
            $recursiveTriggers,
            ['returning' => $recursiveReturning, 'rollback_on_returning_key' => ['module_seed_child']],
        );

        $t->same('app_recursive_upsert_returning_145', $plan['savepoint']);
        $t->same('module_seed_child', $plan['rollback_barrier']['returning_key']);
        $t->same(true, $plan['recursive_summary']['next_replayed_current_key']);
    },
    'recursive view upsert defaults are application settings' => static function (TestRunner $t): void {
        $plan = SQLiteTriggerUpsertRecursiveViewCurrentSourceNextPlan::execute(
            [
                ['key_name' => 'base_url', 'key_value' => 'https://old.test'],
                ['key_name' => 'landing_page', 'key_value' => 'https://old.test/landing'],
            ],
            [['name' => 'base_url', 'value' => 'https://current.test']],
            [['name' => 'module_seed', 'value' => 'enabled']],
            [
                'name' => 'app_setting_import_view',
                'source' => 'main@settings-view',
                'mapping' => ['name' => 'key_name', 'value' => 'key_value'],
            ],
            ['key_name'],
            [['name' => 'app_settings_after_base_url', 'when' => 'base_url', 'target' => 'landing_page', 'value' => '{value}/landing']],
        );

        $t->same('app_view_recursive_148', $plan['savepoint']);
        $t->same(['base_url', 'landing_page'], array_column($plan['current_returning_rows'], 'key_name'));
        $t->same(['base_url'], array_column($plan['current_trigger_effects'], 'source_key'));
        $t->same(['landing_page'], array_column($plan['current_trigger_effects'], 'target_key'));
    },
    'recursive deferred returning defaults use setting row ids' => static function (TestRunner $t) use ($settingRows): void {
        $children = [
            ['ref_id' => 10, 'setting_id' => 1],
            ['ref_id' => 11, 'setting_id' => 2],
        ];
        $plan = SQLiteTriggerRecursiveDeferredReturningCurrentSourceNextPlan::update(
            $settingRows,
            $children,
            ['parent_key' => 'setting_id', 'child_key' => 'setting_id', 'deferred' => true],
            [
                'where' => static fn (array $row): bool => $row['setting_id'] === 1,
                'assignments' => ['setting_id' => static fn (array $row): int => (int) $row['setting_id'] + 10],
                'returning' => ['setting_id', 'key_name'],
                'rollback_on_deferred_violation' => true,
            ],
        );

        $t->same([11, 2], $plan['current_rowids']);
        $t->same([1, 2], $plan['next_rowids']);
        $t->same('trigger_returning_deferred', $plan['savepoint']);
    },
    'trigger fk savepoint helpers default to settings keys' => static function (TestRunner $t) use ($settingRows): void {
        $children = [
            ['ref_id' => 10, 'setting_id' => 1, 'label' => 'base'],
            ['ref_id' => 11, 'setting_id' => 2, 'label' => 'cache'],
        ];
        $update = SQLiteTriggerReturningFkSavepointCurrentNextPlan::update(
            $settingRows,
            $children,
            ['setting_id' => static fn (array $row): int => (int) $row['setting_id'] + 10],
            static fn (array $row): bool => $row['key_name'] === 'base_url',
            ['parent_key' => 'setting_id', 'child_key' => 'setting_id', 'on_update' => 'cascade'],
            [],
            ['setting_id', 'key_name', ['expr' => 'old.setting_id', 'as' => 'old_setting_id']],
        );
        $delete = SQLiteTriggerReturningFkDeleteSavepointCurrentSourceNextPlan::execute(
            $settingRows,
            $children,
            ['parent_key' => 'setting_id', 'child_key' => 'setting_id', 'on_delete' => 'cascade'],
            [
                'where' => static fn (array $row): bool => $row['key_name'] === 'cache_policy',
                'returning' => ['setting_id', 'key_name'],
            ],
        );

        $t->same([11, 2], array_column($update['next_parent'], 'setting_id'));
        $t->same([11, 2], array_column($update['next_child'], 'setting_id'));
        $t->same([1], array_column($update['yielded'], 'old_key'));
        $t->same(['base_url'], array_column($update['yielded'], 'key_name'));
        $t->same([2], $delete['deleted_rowids']);
        $t->same([1], $delete['next_rowids']);
        $t->same([['setting_id' => 2, 'key_name' => 'cache_policy']], $delete['next_returning_rows']);
    },
    'recursive trigger fk helpers default to settings rows' => static function (TestRunner $t) use ($settingRows): void {
        $linkedRows = [
            $settingRows[0] + ['next_id' => 2],
            $settingRows[1] + ['next_id' => null],
        ];
        $children = [
            ['ref_id' => 10, 'setting_id' => 1],
            ['ref_id' => 11, 'setting_id' => 2],
        ];
        $deferred = SQLiteTriggerRecursiveReturningDeferredFkCurrentSourceNextPlan::run(
            $linkedRows,
            $children,
            ['parent_key' => 'setting_id', 'child_key' => 'setting_id', 'on_update' => 'no action', 'deferred' => true],
            [
                'savepoint' => 'app_settings_rekey',
                'where' => static fn (array $row): bool => $row['setting_id'] === 1,
                'assignments' => ['setting_id' => static fn (array $row, int $depth): int => (int) $row['setting_id'] + 10 + $depth],
                'returning' => ['setting_id', 'key_name'],
                'trigger' => ['name' => 'app_settings_after_update', 'match_column' => 'setting_id', 'match_value' => 'old.next_id'],
                'rollback_on_deferred_violation' => true,
            ],
        );
        $deleted = SQLiteTriggerReturningRecursiveFkCurrentSourceNextPlan::delete(
            $linkedRows,
            $children,
            [['detail_id' => 20, 'setting_id' => 1], ['detail_id' => 21, 'setting_id' => 2]],
            ['parent_key' => 'setting_id', 'child_key' => 'setting_id', 'grandchild_key' => 'setting_id', 'on_delete' => 'cascade'],
            [
                'where' => static fn (array $row): bool => $row['setting_id'] === 1,
                'trigger' => ['name' => 'app_settings_after_delete', 'match_column' => 'setting_id', 'match_value' => 'old.next_id'],
                'returning' => ['setting_id', 'key_name'],
            ],
        );

        $t->same([11, 13], $deferred['current_rowids']);
        $t->same([1, 2], $deferred['next_rowids']);
        $t->same(true, $deferred['yield_suppressed_by_rollback']);
        $t->same('app_recursive_delete', $deleted['savepoint']);
        $t->same([1, 2], $deleted['deleted_parent_keys']);
        $t->same([], $deleted['next_parent_keys']);
    },
];

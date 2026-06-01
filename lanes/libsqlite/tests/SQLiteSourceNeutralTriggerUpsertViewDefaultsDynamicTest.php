<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteTriggerDeferredViewReturningCurrentSourceNextPlan;
use PortLibs\LibSqlite\SQLiteTriggerDeferredUpsertReturningCurrentSourceNextPlan;
use PortLibs\LibSqlite\SQLiteTriggerRecursiveViewDeleteReturningCurrentSourceNextPlan;
use PortLibs\LibSqlite\SQLiteTriggerUpsertDeferredReturningCurrentSourceNextPlan;
use PortLibs\LibSqlite\SQLiteTriggerUpsertDoNothingReturningSavepointCurrentSourceNextPlan;
use PortLibs\LibSqlite\SQLiteTriggerUpsertReturningViewCurrentSourceNextPlan;

$libsqliteRoot = dirname(__DIR__);
$sourceRoot = $libsqliteRoot . '/src';

$sourceFiles = [
    $sourceRoot . '/SQLiteDmlTriggerReturningConflictCurrentSourceNextPlan.php',
    $sourceRoot . '/SQLiteRecursiveTriggerReturningSavepointPlan.php',
    $sourceRoot . '/SQLiteTransactionSavepointTriggerRollbackCurrentSourceNextPlan.php',
    $sourceRoot . '/SQLiteTriggerDeferredViewReturningCurrentSourceNextPlan.php',
    $sourceRoot . '/SQLiteTriggerDeferredUpsertReturningCurrentSourceNextPlan.php',
    $sourceRoot . '/SQLiteTriggerRecursiveReturningSavepointCurrentSourceNextPlan.php',
    $sourceRoot . '/SQLiteTriggerRecursiveViewDeleteReturningCurrentSourceNextPlan.php',
    $sourceRoot . '/SQLiteTriggerReturningRecursiveUpsertCurrentSourceNextPlan.php',
    $sourceRoot . '/SQLiteTriggerReturningUpsertViewCurrentNextPlan.php',
    $sourceRoot . '/SQLiteTriggerRecursiveViewReturningCurrentSourceNextPlan.php',
    $sourceRoot . '/SQLiteTriggerRecursiveViewUpsertCurrentSourceNextPlan.php',
    $sourceRoot . '/SQLiteTriggerUpsertDeferredReturningCurrentSourceNextPlan.php',
    $sourceRoot . '/SQLiteTriggerUpsertDoNothingReturningSavepointCurrentSourceNextPlan.php',
    $sourceRoot . '/SQLiteTriggerUpsertReturningViewCurrentSourceNextPlan.php',
    $sourceRoot . '/SQLiteTriggerViewReturningSavepointRecursiveCurrentSourceNextPlan.php',
    $sourceRoot . '/SQLiteUpsertTriggerForeignKeyYieldPlan.php',
    $sourceRoot . '/SQLiteViewUpsertReturningSavepointPlan.php',
];
$fixtureFiles = [
    $libsqliteRoot . '/examples/application-trigger-returning-upsert-view-current-next52.php',
    $libsqliteRoot . '/examples/application-trigger-upsert-returning-view-unique-current-source-next140.php',
    $libsqliteRoot . '/examples/application-trigger-view-returning-savepoint-recursive-current-source-next123.php',
    $libsqliteRoot . '/examples/application-view-upsert-returning-savepoint-current-next49.php',
    $libsqliteRoot . '/tests/SQLiteTriggerReturningUpsertViewCurrentNext52Test.php',
    $libsqliteRoot . '/tests/SQLiteTriggerUpsertReturningViewUniqueCurrentSourceNext140Test.php',
    $libsqliteRoot . '/tests/SQLiteTriggerViewReturningSavepointRecursiveCurrentSourceNext123Test.php',
    $libsqliteRoot . '/tests/SQLiteViewUpsertReturningSavepointCurrentNext49Test.php',
];

$legacyTriggerViewDefaultMatches = static function () use ($sourceFiles, $libsqliteRoot): array {
    $prefix = 'wp' . '_';
    $terms = [
        'wp' . '_options',
        'opt' . 'ion_id',
        'opt' . 'ion_name',
        'opt' . 'ion_value',
        'auto' . 'load',
        'blog' . '_id',
        'target_' . 'option',
        'parent_' . 'option',
    ];
    $pattern = '/(?:\b' . preg_quote($prefix, '/') . '|\b(?:' . implode('|', array_map(static fn (string $term): string => preg_quote($term, '/'), $terms)) . ')\b)/';
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

$legacyTriggerViewFixtureMatches = static function () use ($fixtureFiles, $libsqliteRoot): array {
    $terms = [
        'wp' . '_',
        'wp' . '_options',
        'opt' . 'ion_id',
        'opt' . 'ion_name',
        'opt' . 'ion_value',
        'auto' . 'load',
        'site' . 'url',
        'blog' . 'name',
        'plug' . 'in',
    ];
    $pattern = '/(?:\bhome\b|' . implode('|', array_map(static fn (string $term): string => preg_quote($term, '/'), $terms)) . ')/';
    $matches = [];

    foreach ($fixtureFiles as $file) {
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

$upsertDefaults = static fn (): array => SQLiteTriggerUpsertReturningViewCurrentSourceNextPlan::execute(
    [
        ['setting_id' => 1, 'key_name' => 'base_url', 'key_value' => 'https://old.test', 'load_policy' => 'yes', 'revision' => 1],
    ],
    [
        ['import_id' => 11, 'name' => 'base_url', 'value' => 'https://current.test', 'load_policy_flag' => 'yes'],
        ['import_id' => 12, 'name' => 'cache_rules', 'value' => 'fresh-cache', 'load_policy_flag' => 'no'],
    ],
    [
        ['import_id' => 21, 'name' => 'site_title', 'value' => 'Preview title', 'load_policy_flag' => 'yes'],
    ],
    [
        'name' => 'app_setting_import_view',
        'source' => 'main@settings-view-current',
        'columns' => ['import_id', 'name', 'value', 'load_policy_flag'],
        'mapping' => ['import_id' => 'setting_id', 'name' => 'key_name', 'value' => 'key_value', 'load_policy_flag' => 'load_policy'],
    ],
    [
        'name' => 'app_setting_import_view',
        'source' => 'main@settings-view-next',
        'columns' => ['import_id', 'name', 'value', 'load_policy_flag'],
        'mapping' => ['import_id' => 'setting_id', 'name' => 'key_name', 'value' => 'key_value', 'load_policy_flag' => 'load_policy'],
    ],
    ['key_name'],
    [
        'setting_id' => static fn (array $old, array $incoming): mixed => $incoming['setting_id'],
        'key_value' => static fn (array $old, array $incoming): mixed => $incoming['key_value'],
        'load_policy' => static fn (array $old, array $incoming): mixed => $incoming['load_policy'],
        'revision' => static fn (array $old): int => (int) $old['revision'] + 1,
    ],
    [
        ['expr' => 'new.key_name', 'as' => 'name'],
        ['expr' => 'new.key_value', 'as' => 'value'],
        ['expr' => 'source', 'as' => 'source_token'],
    ],
);

$deferredDefaults = static fn (): array => SQLiteTriggerDeferredViewReturningCurrentSourceNextPlan::execute(
    [
        ['key_name' => 'base_url', 'key_value' => 'https://old.test', 'load_policy' => 'yes', 'parent_key_name' => null],
        ['key_name' => 'theme_variant', 'key_value' => 'classic', 'load_policy' => 'yes', 'parent_key_name' => 'base_url'],
    ],
    [
        ['key_name' => 'theme_variant', 'key_value' => 'modern', 'load_policy' => 'yes', 'parent_key_name' => 'missing-parent'],
    ],
    [
        ['key_name' => 'cache_rules', 'key_value' => 'next-cache', 'load_policy' => 'yes', 'parent_key_name' => 'base_url'],
    ],
    ['parent_key' => 'key_name', 'child_key' => 'parent_key_name', 'deferred' => true],
    [
        'name' => 'app_loadable_settings',
        'columns' => ['key_name', 'key_value', 'parent_key_name', 'load_policy'],
        'where' => static fn (array $row): bool => ($row['load_policy'] ?? null) === 'yes',
        'order_by' => 'key_name',
    ],
    [
        'key_name',
        ['expr' => 'new.key_value', 'as' => 'value'],
    ],
);

$deleteDefaults = static fn (array $options = []): array => SQLiteTriggerRecursiveViewDeleteReturningCurrentSourceNextPlan::execute(
    [
        ['key_name' => 'cache_root', 'key_value' => 'root', 'load_policy' => 'yes', 'parent_key_name' => null, 'priority' => 0],
        ['key_name' => 'cache_child', 'key_value' => 'child', 'load_policy' => 'yes', 'parent_key_name' => 'cache_root', 'priority' => 10],
        ['key_name' => 'stable_setting', 'key_value' => 'stable', 'load_policy' => 'no', 'parent_key_name' => null, 'priority' => 20],
    ],
    [['root_name' => 'cache_root']],
    [['root_name' => 'stable_setting']],
    [
        'name' => 'app_recursive_setting_delete_view',
        'source' => 'main@delete-view-current',
        'trigger' => 'app_recursive_setting_delete_view_io_delete',
        'trigger_source' => 'main@delete-trigger-current',
        'root_key' => 'root_name',
        'columns' => ['key_name', 'key_value', 'load_policy', 'parent_key_name', 'priority'],
    ],
    [
        'name' => 'app_recursive_setting_delete_view',
        'source' => 'main@delete-view-next',
        'trigger' => 'app_recursive_setting_delete_view_io_delete',
        'trigger_source' => 'main@delete-trigger-next',
        'root_key' => 'root_name',
        'columns' => ['key_name', 'key_value', 'load_policy', 'parent_key_name', 'priority'],
    ],
    [
        'old.key_name',
        ['expr' => 'old.key_value', 'as' => 'value'],
    ],
    $options,
);

$doNothingDefaults = static fn (): array => SQLiteTriggerUpsertDoNothingReturningSavepointCurrentSourceNextPlan::execute(
    [
        ['setting_id' => 1, 'tenant_id' => 1, 'key_name' => 'base_url', 'key_value' => 'https://old.test', 'load_policy' => 'yes'],
    ],
    [
        ['setting_id' => 2, 'tenant_id' => 1, 'key_name' => 'module_seed', 'key_value' => 'seed', 'load_policy' => 'no'],
    ],
    [
        ['setting_id' => 3, 'tenant_id' => 1, 'key_name' => 'theme_variant', 'key_value' => 'theme', 'load_policy' => 'yes'],
    ],
    ['tenant_id', 'key_name'],
    [],
    [
        ['expr' => 'new.setting_id', 'as' => 'id'],
        ['expr' => 'new.key_name', 'as' => 'name'],
    ],
);

$deferredUpsertDefaults = static fn (): array => SQLiteTriggerDeferredUpsertReturningCurrentSourceNextPlan::executeDeferredCommit(
    [
        ['setting_id' => 1, 'parent_id' => 10, 'key_name' => 'source_marker', 'key_value' => 'old', 'revision' => 1],
    ],
    [
        ['setting_id' => 2, 'parent_id' => 10, 'key_name' => 'cache_policy', 'key_value' => 'fresh', 'revision' => 1],
    ],
    ['key_name'],
    [
        'setting_id' => static fn (array $old): int => (int) $old['setting_id'],
        'key_value' => static fn (array $old, array $incoming): mixed => $incoming['key_value'],
        'revision' => static fn (array $old): int => (int) $old['revision'] + 1,
    ],
    [],
    [
        'setting_id',
        'key_name',
        ['expr' => 'new.revision', 'as' => 'next_revision'],
    ],
    [
        ['parent_id' => 10, 'parent_title' => 'Application parent'],
    ],
    [
        'child_table' => 'app_child_settings',
        'parent_table' => 'app_parent_settings',
        'child_key' => 'parent_id',
        'parent_key' => 'parent_id',
        'deferred' => true,
    ],
);

$upsertDeferredDefaults = static fn (): array => SQLiteTriggerUpsertDeferredReturningCurrentSourceNextPlan::execute(
    [
        ['setting_id' => 1, 'key_name' => 'base_url', 'key_value' => 'https://old.test', 'load_policy' => 'yes', 'parent_setting_id' => 10, 'revision' => 1],
    ],
    [
        ['setting_id' => 11, 'key_name' => 'base_url', 'key_value' => 'https://blocked.test', 'load_policy' => 'yes', 'parent_setting_id' => 99, 'revision' => 1],
    ],
    [
        ['setting_id' => 21, 'key_name' => 'module_seed', 'key_value' => 'seed', 'load_policy' => 'no', 'parent_setting_id' => 10, 'revision' => 1],
    ],
    ['key_name'],
    [
        'setting_id' => static fn (array $old, array $incoming): mixed => $incoming['setting_id'],
        'key_value' => static fn (array $old, array $incoming): mixed => $incoming['key_value'],
        'load_policy' => static fn (array $old, array $incoming): mixed => $incoming['load_policy'],
        'parent_setting_id' => static fn (array $old, array $incoming): mixed => $incoming['parent_setting_id'],
        'revision' => static fn (array $old): int => (int) $old['revision'] + 1,
    ],
    [],
    [
        ['expr' => 'new.key_name', 'as' => 'name'],
        ['expr' => 'new.setting_id', 'as' => 'id'],
    ],
    [
        ['parent_id' => 10, 'parent_title' => 'Application parent'],
    ],
    [
        'child_table' => 'app_settings',
        'parent_table' => 'app_setting_groups',
        'child_key' => 'parent_setting_id',
        'parent_key' => 'parent_id',
        'deferred' => true,
    ],
);

return [
    'source-neutral trigger view defaults contain no legacy domain strings' => static fn (TestRunner $t) => $t->same([], $legacyTriggerViewDefaultMatches()),
    'source-neutral trigger current-next view fixtures contain no legacy domain strings' => static fn (TestRunner $t) => $t->same([], $legacyTriggerViewFixtureMatches()),
    'trigger upsert returning view defaults are application settings' => static function (TestRunner $t) use ($upsertDefaults): void {
        $plan = $upsertDefaults();

        $t->same('key_name', $plan['key']);
        $t->same('app_view_upsert', $plan['savepoint']);
        $t->same('app_settings_view_io_upsert', $plan['trigger']);
        $t->same(['base_url', 'cache_rules'], array_column($plan['current_rows'], 'key_name'));
        $t->same(['base_url'], array_column($plan['after_savepoint'], 'key_name'));
    },
    'trigger deferred view returning defaults are application settings' => static function (TestRunner $t) use ($deferredDefaults): void {
        $plan = $deferredDefaults();

        $t->same('key_name', $plan['key']);
        $t->same('app_settings_view_io_update', $plan['trigger']);
        $t->same('current', $plan['current_source']);
        $t->same('next', $plan['next_source']);
        $t->same(['base_url', 'theme_variant'], array_column($plan['children'], 'key_name'));
        $t->same('theme_variant', $plan['deferred_violations'][0]['key_name']);
    },
    'recursive view delete returning defaults are application settings' => static function (TestRunner $t) use ($deleteDefaults): void {
        $released = $deleteDefaults(['release_current' => true]);
        $blocked = $deleteDefaults(['blocked_key' => 'cache_child']);

        $t->same('app_recursive_view_delete_returning_168', $released['savepoint']);
        $t->same('key_name', $released['key']);
        $t->same('parent_key_name', $released['parent_key']);
        $t->same(['cache_root', 'cache_child'], $released['current_deleted_keys']);
        $t->same('cache_child', $blocked['current_blocked_rows'][0]['returning']['key_name']);
    },
    'trigger upsert do nothing defaults are application settings' => static function (TestRunner $t) use ($doNothingDefaults): void {
        $plan = $doNothingDefaults();

        $t->same('app_upsert_do_nothing_returning', $plan['savepoint']);
        $t->same(['module_seed'], array_column($plan['current_returning_rows'], 'name'));
        $t->same(['theme_variant'], array_column($plan['next_returning_rows'], 'name'));
        $t->same(['base_url', 'module_seed', 'theme_variant'], array_column($plan['next_rows'], 'key_name'));
    },
    'trigger deferred upsert transaction defaults are application settings' => static function (TestRunner $t) use ($deferredUpsertDefaults): void {
        $plan = $deferredUpsertDefaults();

        $t->same('app_import_txn', $plan['transaction']);
        $t->same('ok', $plan['commit_status']);
        $t->same(['source_marker', 'cache_policy'], array_column($plan['after_statement'], 'key_name'));
        $t->same([], $plan['deferred_violations']);
    },
    'trigger upsert deferred returning defaults are application settings' => static function (TestRunner $t) use ($upsertDeferredDefaults): void {
        $plan = $upsertDeferredDefaults();

        $t->same('app_import_deferred_upsert', $plan['savepoint']);
        $t->same('current-trigger-upsert-returning', $plan['current_source']);
        $t->same('next-trigger-upsert-returning', $plan['next_source']);
        $t->same('app_settings', $plan['deferred_violations'][0]['child_table']);
        $t->same('app_setting_groups', $plan['deferred_violations'][0]['parent_table']);
        $t->same(11, $plan['deferred_violations'][0]['rowid']);
        $t->same(['module_seed'], array_column($plan['next_returning_rows'], 'name'));
    },
];

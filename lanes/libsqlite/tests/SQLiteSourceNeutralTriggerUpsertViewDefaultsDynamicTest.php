<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteTriggerDeferredViewReturningCurrentSourceNextPlan;
use PortLibs\LibSqlite\SQLiteTriggerRecursiveViewDeleteReturningCurrentSourceNextPlan;
use PortLibs\LibSqlite\SQLiteTriggerUpsertReturningViewCurrentSourceNextPlan;

$libsqliteRoot = dirname(__DIR__);
$sourceRoot = $libsqliteRoot . '/src';

$sourceFiles = [
    $sourceRoot . '/SQLiteTriggerDeferredViewReturningCurrentSourceNextPlan.php',
    $sourceRoot . '/SQLiteTriggerRecursiveViewDeleteReturningCurrentSourceNextPlan.php',
    $sourceRoot . '/SQLiteTriggerUpsertReturningViewCurrentSourceNextPlan.php',
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

return [
    'source-neutral trigger view defaults contain no legacy domain strings' => static fn (TestRunner $t) => $t->same([], $legacyTriggerViewDefaultMatches()),
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
];

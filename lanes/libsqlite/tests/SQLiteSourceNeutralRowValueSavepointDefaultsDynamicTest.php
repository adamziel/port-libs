<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteRowValueDeleteReturningSavepointCurrentSourceNextPlan;
use PortLibs\LibSqlite\SQLiteRowValueReturningFailSavepointCurrentSourceNextPlan;
use PortLibs\LibSqlite\SQLiteRowValueReturningSavepointConflictCurrentSourceNextPlan;
use PortLibs\LibSqlite\SQLiteRowValueSavepointUpsertCurrentSourceNextPlan;
use PortLibs\LibSqlite\SQLiteRowValueUpdateDeleteSavepointCurrentSourceNextPlan;

$libsqliteRoot = dirname(__DIR__);
$sourceRoot = $libsqliteRoot . '/src';

$sourceFiles = [
    $sourceRoot . '/SQLiteRowValueUpdateDeleteSavepointCurrentSourceNextPlan.php',
    $sourceRoot . '/SQLiteRowValueReturningSavepointConflictCurrentSourceNextPlan.php',
    $sourceRoot . '/SQLiteRowValueReturningFailSavepointCurrentSourceNextPlan.php',
    $sourceRoot . '/SQLiteRowValueSavepointUpsertCurrentSourceNextPlan.php',
    $sourceRoot . '/SQLiteRowValueDeleteReturningSavepointCurrentSourceNextPlan.php',
];

$legacyRowValueDefaultMatches = static function () use ($sourceFiles, $libsqliteRoot): array {
    $terms = [
        'wp' . '_',
        'wp' . '_options',
        'opt' . 'ion_id',
        'opt' . 'ion_name',
        'opt' . 'ion_value',
        'auto' . 'load',
        'blog' . '_id',
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

$settings = [
    ['setting_id' => 1, 'tenant_id' => 1, 'key_name' => 'base_url', 'load_policy' => 'yes', 'status' => 'live', 'bytes' => 20, 'key_value' => 'https://old.test'],
    ['setting_id' => 2, 'tenant_id' => 1, 'key_name' => 'site_title', 'load_policy' => 'yes', 'status' => 'live', 'bytes' => 10, 'key_value' => 'Old title'],
    ['setting_id' => 3, 'tenant_id' => 2, 'key_name' => 'cache_policy', 'load_policy' => 'no', 'status' => 'queued', 'bytes' => 6, 'key_value' => 'cache'],
    ['setting_id' => 4, 'tenant_id' => 2, 'key_name' => 'retry_policy', 'load_policy' => 'no', 'status' => 'queued', 'bytes' => 7, 'key_value' => 'retry'],
];
$tables = ['app_settings' => $settings];
$uniqueTable = [['table' => 'app_settings', 'columns' => ['tenant_id', 'key_name']]];
$unique = [['tenant_id', 'key_name'], ['setting_id']];

$updateDeleteDefaults = static fn (): array => SQLiteRowValueUpdateDeleteSavepointCurrentSourceNextPlan::execute(
    $tables,
    [
        "UPDATE app_settings SET (load_policy, status, key_value, bytes) = ('yes', 'migrated', key_name || ':migrated', bytes + tenant_id) WHERE (tenant_id, key_name) IN ((1, 'base_url'), (2, 'retry_policy')) RETURNING setting_id, key_name, status ORDER BY setting_id",
        "DELETE FROM app_settings WHERE (tenant_id, key_name) IN ((2, 'cache_policy')) RETURNING setting_id, key_name ORDER BY setting_id",
    ],
    $uniqueTable,
);

$conflictDefaults = static fn (): array => SQLiteRowValueReturningSavepointConflictCurrentSourceNextPlan::execute(
    $tables,
    [
        "UPDATE OR IGNORE app_settings SET (tenant_id, key_name, status) = (1, 'site_title', 'ignored') WHERE setting_id = 3 RETURNING setting_id, tenant_id, key_name, status",
        "UPDATE OR REPLACE app_settings SET (tenant_id, key_name, status) = (1, 'site_title', 'replaced') WHERE setting_id = 4 RETURNING setting_id, tenant_id, key_name, status",
    ],
    [['tenant_id', 'key_name']],
);

$failDefaults = static fn (): array => SQLiteRowValueReturningFailSavepointCurrentSourceNextPlan::execute(
    $tables,
    [
        "UPDATE OR FAIL app_settings SET (tenant_id, key_name, status) = (1, 'base_url', 'failed') WHERE setting_id = 3 RETURNING setting_id, tenant_id, key_name, status",
    ],
    [['tenant_id', 'key_name']],
);

$upsertDefaults = static fn (): array => SQLiteRowValueSavepointUpsertCurrentSourceNextPlan::execute(
    $tables,
    [
        "INSERT INTO app_settings (setting_id, tenant_id, key_name, load_policy, status, bytes, key_value) VALUES (5, 1, 'module_registry', 'yes', 'inserted', 9, 'modules') ON CONFLICT (tenant_id, key_name) DO UPDATE SET (load_policy, status, bytes, key_value) = (excluded.load_policy, 'updated', bytes + excluded.bytes, excluded.key_value) RETURNING setting_id, tenant_id, key_name, status, bytes, key_value",
        "INSERT INTO app_settings (setting_id, tenant_id, key_name, load_policy, status, bytes, key_value) VALUES (6, 1, 'base_url', 'no', 'incoming', 3, 'https://new.test') ON CONFLICT (tenant_id, key_name) DO UPDATE SET (load_policy, status, bytes, key_value) = (excluded.load_policy, 'updated', bytes + excluded.bytes, excluded.key_value) RETURNING setting_id, tenant_id, key_name, status, bytes, key_value",
    ],
    $unique,
);

$deleteDefaults = static fn (): array => SQLiteRowValueDeleteReturningSavepointCurrentSourceNextPlan::execute(
    $tables,
    [
        "DELETE FROM app_settings WHERE (tenant_id, key_name) IN ((2, 'cache_policy')) RETURNING setting_id, key_name ORDER BY setting_id",
    ],
    [
        "DELETE FROM app_settings WHERE (tenant_id, key_name) BETWEEN (2, 'retry_policy') AND (2, 'retry_policy') RETURNING setting_id, key_name ORDER BY setting_id",
        "DELETE FROM app_settings WHERE (tenant_id, key_name) IN ((2)) RETURNING setting_id",
    ],
    [['tenant_id', 'key_name']],
);

return [
    'source-neutral row-value savepoint defaults contain no legacy domain strings' => static fn (TestRunner $t) => $t->same([], $legacyRowValueDefaultMatches()),
    'row-value update delete savepoint defaults use application settings' => static function (TestRunner $t) use ($updateDeleteDefaults): void {
        $plan = $updateDeleteDefaults();

        $t->same('app_settings_cleanup', $plan['savepoint']);
        $t->same('released', $plan['status']);
        $t->same([1, 4], $plan['executed_statements'][0]['selected_ids']);
        $t->same([3], $plan['executed_statements'][1]['selected_ids']);
        $t->same([1, 2, 4], array_column($plan['current_source_tables']['app_settings'], 'setting_id'));
    },
    'row-value conflict savepoint defaults use application settings' => static function (TestRunner $t) use ($conflictDefaults): void {
        $plan = $conflictDefaults();

        $t->same('app_settings_conflict_batch', $plan['savepoint']);
        $t->same('released', $plan['status']);
        $t->same([3], array_column(array_column($plan['ignored_rows'], 'row'), 'setting_id'));
        $t->same([2], array_column(array_column($plan['deleted_conflict_rows'], 'row'), 'setting_id'));
        $t->same([1, 3, 4], array_column($plan['current_source_tables']['app_settings'], 'setting_id'));
    },
    'row-value fail savepoint defaults use application settings' => static function (TestRunner $t) use ($failDefaults): void {
        $plan = $failDefaults();

        $t->same('app_settings_fail_batch', $plan['savepoint']);
        $t->same('failed-savepoint-preserved', $plan['status']);
        $t->same(0, $plan['failed_statement_ordinal']);
        $t->same(3, $plan['failed_conflict']['row_id']);
        $t->same([], $plan['yielded_returning'][0]['rows']);
    },
    'row-value upsert savepoint defaults use application settings' => static function (TestRunner $t) use ($upsertDefaults): void {
        $plan = $upsertDefaults();

        $t->same('app_settings_upsert_batch', $plan['savepoint']);
        $t->same('released', $plan['status']);
        $t->same(['insert', 'update'], array_column($plan['executed_statements'], 'action'));
        $t->same(1, $plan['conflicts'][0]['row_id']);
        $t->same([1, 2, 3, 4, 5], array_column($plan['current_source_tables']['app_settings'], 'setting_id'));
    },
    'row-value delete returning savepoint defaults use application settings' => static function (TestRunner $t) use ($deleteDefaults): void {
        $plan = $deleteDefaults();

        $t->same('app_settings_delete_returning_outer', $plan['outer_savepoint']);
        $t->same('app_settings_delete_returning_released', $plan['released_savepoint']);
        $t->same('app_settings_delete_returning_rollback', $plan['rollback_savepoint']);
        $t->same('rollback-savepoint-rolled-back', $plan['status']);
        $t->same([3], array_column($plan['yielded_returning'][0]['rows'], 'setting_id'));
        $t->same([1, 2, 4], array_column($plan['current_source_tables']['app_settings'], 'setting_id'));
    },
    'source-neutral row-value savepoint dependency closure' => static fn (TestRunner $t) => $t->same(
        'no new support component needed; reuses native row-value UPDATE/DELETE RETURNING, UPSERT, conflict handling, and savepoint current-source helpers with generic setting identifiers',
        'no new support component needed; reuses native row-value UPDATE/DELETE RETURNING, UPSERT, conflict handling, and savepoint current-source helpers with generic setting identifiers',
    ),
];

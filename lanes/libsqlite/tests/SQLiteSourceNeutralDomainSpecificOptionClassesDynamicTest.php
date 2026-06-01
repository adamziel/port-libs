<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteDmlTriggerCurrentNextPlan;
use PortLibs\LibSqlite\SQLiteUpdateDeleteTriggerOrderPlan;
use PortLibs\LibSqlite\SQLiteUpsertReturningTriggerPlan;

$libsqliteRoot = dirname(__DIR__);
$sourceRoot = $libsqliteRoot . '/src';

$sourceFiles = [
    $sourceRoot . '/SQLiteDmlTriggerCurrentNextPlan.php',
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
];

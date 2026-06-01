<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBlobValue;
use PortLibs\LibSqlite\SQLiteJsonB;
use PortLibs\LibSqlite\SQLiteJsonbGeneratedCheckIndexPlan;

$jsonb54 = static fn (array $value): SQLiteBlobValue => new SQLiteBlobValue(SQLiteJsonB::encode($value));

$createTable54 = <<<'SQL'
CREATE TABLE app_settings(
  setting_id INTEGER PRIMARY KEY,
  key_name TEXT NOT NULL,
  key_value BLOB,
  load_policy TEXT,
  module_slug TEXT GENERATED ALWAYS AS (jsonb_extract(key_value, '$.module.slug')) STORED CHECK(module_slug <> ''),
  module_enabled INTEGER GENERATED ALWAYS AS (jsonb_extract(key_value, '$.module.enabled')) VIRTUAL CHECK(module_enabled >= 0),
  module_rank INTEGER GENERATED ALWAYS AS (jsonb_extract(key_value, '$.module.rank')) VIRTUAL CHECK(module_rank BETWEEN 1 AND 99)
)
SQL;

$rows54 = [
    ['setting_id' => 101, 'key_name' => 'module_alpha_settings', 'key_value' => $jsonb54(['module' => ['slug' => 'alpha', 'enabled' => 1, 'rank' => 10]]), 'load_policy' => 'yes'],
    ['setting_id' => 102, 'key_name' => 'module_beta_settings', 'key_value' => $jsonb54(['module' => ['slug' => 'beta', 'enabled' => 0, 'rank' => 20]]), 'load_policy' => 'yes'],
    ['setting_id' => 103, 'key_name' => 'module_gamma_settings', 'key_value' => $jsonb54(['module' => ['slug' => 'gamma', 'enabled' => 1, 'rank' => 30]]), 'load_policy' => 'no'],
    ['setting_id' => 104, 'key_name' => 'module_delta_settings', 'key_value' => $jsonb54(['module' => ['slug' => 'delta', 'enabled' => 1, 'rank' => 40]]), 'load_policy' => 'yes'],
];

$indexes54 = [
    ['name' => 'idx_module_slug_checked54', 'rootPage' => 54, 'unique' => true, 'sql' => 'CREATE UNIQUE INDEX idx_module_slug_checked54 ON app_settings(module_slug COLLATE NOCASE) WHERE module_slug IS NOT NULL'],
    ['name' => 'idx_module_enabled_checked54', 'rootPage' => 55, 'sql' => 'CREATE INDEX idx_module_enabled_checked54 ON app_settings(module_enabled) WHERE module_enabled = 1'],
    ['name' => 'idx_module_rank_checked54', 'rootPage' => 56, 'sql' => 'CREATE INDEX idx_module_rank_checked54 ON app_settings(module_rank DESC) WHERE module_rank IS NOT NULL'],
];

$updates54 = [
    ['rowid' => 101, 'mutations' => [
        ['function' => 'jsonb_set', 'path' => '$.module.rank', 'value' => 15],
        ['function' => 'jsonb_set', 'path' => '$.module.enabled', 'value' => 0],
    ]],
    ['rowid' => 102, 'mutations' => [
        ['function' => 'jsonb_set', 'path' => '$.module.rank', 'value' => 120],
    ]],
    ['rowid' => 103, 'mutations' => [
        ['function' => 'jsonb_set', 'path' => '$.module.slug', 'value' => 'epsilon'],
        ['function' => 'jsonb_set', 'path' => '$.module.rank', 'value' => 35],
    ]],
    ['rowid' => 104, 'mutations' => [
        ['function' => 'jsonb_set', 'path' => '$.module.slug', 'value' => ''],
    ]],
];

$plan54 = static fn (): array => SQLiteJsonbGeneratedCheckIndexPlan::plan($createTable54, $rows54, $indexes54, $updates54, 512);
$valueAt54 = static function (array $value, string $path): mixed {
    foreach (explode('.', $path) as $part) {
        if ($part === 'count') {
            $value = count($value);
            continue;
        }
        $value = is_numeric($part) ? $value[(int) $part] : $value[$part];
    }

    return $value;
};
$decodeSetting54 = static function (mixed $value): array {
    if ($value instanceof SQLiteBlobValue) {
        return SQLiteJsonB::decode($value->bytes);
    }

    return json_decode((string) $value, true, 512, JSON_THROW_ON_ERROR);
};

$tests = [
    'jsonb generated check index current next54 table and generated metadata' => static function (TestRunner $t) use ($plan54): void {
        $plan = $plan54();

        $t->same('app_settings', $plan['table']);
        $t->same(3, count($plan['generated_columns']));
        $t->same(['module_slug', 'module_enabled', 'module_rank'], array_column($plan['generated_columns'], 'name'));
        $t->same(['$.module.slug', '$.module.enabled', '$.module.rank'], array_column($plan['generated_columns'], 'path'));
        $t->same(['STORED', 'VIRTUAL', 'VIRTUAL'], array_column($plan['generated_columns'], 'storage'));
    },
    'jsonb generated check index current next54 extracts check constraints' => static function (TestRunner $t) use ($plan54): void {
        $checks = $plan54()['check_constraints'];

        $t->same(3, count($checks));
        $t->same(['module_slug', 'module_enabled', 'module_rank'], array_column($checks, 'column'));
        $t->same(['<>', '>=', 'BETWEEN'], array_column($checks, 'operator'));
        $t->same('', $checks[0]['value']);
        $t->same(0, $checks[1]['value']);
        $t->same(['lower' => 1, 'upper' => 99], $checks[2]['value']);
    },
    'jsonb generated check index current next54 admits only check-clean updates' => static function (TestRunner $t) use ($plan54): void {
        $plan = $plan54();

        $t->same(2, $plan['changes']);
        $t->same([101, 103], array_column($plan['accepted_updates'], 'rowid'));
        $t->same([102, 104], array_column($plan['rejected_updates'], 'rowid'));
        $t->same([101, 102, 103, 104], array_column($plan['check_results'], 'rowid'));
        $t->same([true, false, true, false], array_column($plan['check_results'], 'ok'));
    },
    'jsonb generated check index current next54 keeps rejected row images out of final rows' => static function (TestRunner $t) use ($plan54): void {
        $after = $plan54()['after'];

        $t->same(['alpha', 'beta', 'epsilon', 'delta'], array_column($after, 'module_slug'));
        $t->same([0, 0, 1, 1], array_column($after, 'module_enabled'));
        $t->same([15, 20, 35, 40], array_column($after, 'module_rank'));
        $t->same(['module_beta_settings', 'module_delta_settings'], [$after[1]['key_name'], $after[3]['key_name']]);
    },
    'jsonb generated check index current next54 reports failed check details' => static function (TestRunner $t) use ($plan54, $valueAt54): void {
        $plan = $plan54();

        $t->same(120, $valueAt54($plan, 'rejected_updates.0.checks.2.actual'));
        $t->same(false, $valueAt54($plan, 'rejected_updates.0.checks.2.ok'));
        $t->same('CHECK(module_rank BETWEEN 1 AND 99)', $valueAt54($plan, 'rejected_updates.0.checks.2.sql'));
        $t->same('', $valueAt54($plan, 'rejected_updates.1.checks.0.actual'));
        $t->same(false, $valueAt54($plan, 'rejected_updates.1.checks.0.ok'));
        $t->same('CHECK(module_slug <> \'\')', $valueAt54($plan, 'rejected_updates.1.checks.0.sql'));
    },
    'jsonb generated check index current next54 decodes accepted JSONB payloads' => static function (TestRunner $t) use ($plan54, $decodeSetting54): void {
        $after = $plan54()['after'];
        $alpha = $decodeSetting54($after[0]['key_value']);
        $gamma = $decodeSetting54($after[2]['key_value']);
        $beta = $decodeSetting54($after[1]['key_value']);

        $t->same(15, $alpha['module']['rank']);
        $t->same(0, $alpha['module']['enabled']);
        $t->same('epsilon', $gamma['module']['slug']);
        $t->same(35, $gamma['module']['rank']);
        $t->same(20, $beta['module']['rank']);
        $t->same('beta', $beta['module']['slug']);
    },
    'jsonb generated check index current next54 emits admitted index actions only' => static function (TestRunner $t) use ($plan54): void {
        $actions = $plan54()['index_actions'];

        $t->same(7, count($actions));
        $t->same(7, $plan54()['index_action_count']);
        $t->same([101, 101, 101, 103, 103, 103, 103], array_column($actions, 'rowid'));
        $t->same([true, true, true, true, true, true, true], array_column($actions, 'admitted'));
        $t->same([], array_values(array_filter($actions, static fn (array $action): bool => in_array($action['rowid'], [102, 104], true))));
    },
    'jsonb generated check index current next54 updates partial enabled membership' => static function (TestRunner $t) use ($plan54): void {
        $plan = $plan54();

        $t->same([101, 103, 104], array_column($plan['before_indexes']['idx_module_enabled_checked54']['current_entries'], 'rowid'));
        $t->same([103, 104], array_column($plan['after_indexes']['idx_module_enabled_checked54']['current_entries'], 'rowid'));
        $t->same('delete', $plan['index_actions'][0]['action']);
        $t->same('idx_module_enabled_checked54', $plan['index_actions'][0]['index']);
        $t->same(101, $plan['index_actions'][0]['rowid']);
        $t->same(1, $plan['index_actions'][0]['key']);
    },
    'jsonb generated check index current next54 updates unique slug index image' => static function (TestRunner $t) use ($plan54): void {
        $plan = $plan54();

        $t->same(['alpha', 'beta', 'delta', 'gamma'], array_column($plan['before_indexes']['idx_module_slug_checked54']['current_entries'], 'key'));
        $t->same(['alpha', 'beta', 'delta', 'epsilon'], array_column($plan['after_indexes']['idx_module_slug_checked54']['current_entries'], 'key'));
        $t->same([101, 102, 104, 103], array_column($plan['before_indexes']['idx_module_slug_checked54']['current_entries'], 'rowid'));
        $t->same([101, 102, 104, 103], array_column($plan['after_indexes']['idx_module_slug_checked54']['current_entries'], 'rowid'));
        $t->same('NOCASE', $plan['after_indexes']['idx_module_slug_checked54']['collation']);
        $t->true($plan['after_indexes']['idx_module_slug_checked54']['unique']);
    },
    'jsonb generated check index current next54 preserves descending rank order' => static function (TestRunner $t) use ($plan54): void {
        $plan = $plan54();

        $t->same([40, 30, 20, 10], array_column($plan['before_indexes']['idx_module_rank_checked54']['current_entries'], 'key'));
        $t->same([40, 35, 20, 15], array_column($plan['after_indexes']['idx_module_rank_checked54']['current_entries'], 'key'));
        $t->same([104, 103, 102, 101], array_column($plan['after_indexes']['idx_module_rank_checked54']['current_entries'], 'rowid'));
        $t->true($plan['after_indexes']['idx_module_rank_checked54']['descending']);
        $t->same('BINARY', $plan['after_indexes']['idx_module_rank_checked54']['collation']);
    },
    'jsonb generated check index current next54 validates input guards' => static function (TestRunner $t) use ($createTable54, $rows54, $indexes54, $updates54): void {
        $t->throws(InvalidArgumentException::class, static fn () => SQLiteJsonbGeneratedCheckIndexPlan::plan(
            preg_replace('/\\s+CHECK\\([^)]*\\)/', '', $createTable54) ?? $createTable54,
            $rows54,
            $indexes54,
            $updates54,
        ));
        $t->throws(InvalidArgumentException::class, static fn () => SQLiteJsonbGeneratedCheckIndexPlan::plan($createTable54, $rows54, $indexes54, [
            ['rowid' => [], 'mutations' => []],
        ]));
        $t->throws(InvalidArgumentException::class, static fn () => SQLiteJsonbGeneratedCheckIndexPlan::plan(
            str_replace('99)', 'bad)', $createTable54),
            $rows54,
            $indexes54,
            $updates54,
        ));
    },
    'jsonb generated check index current next54 propagates unique conflicts after check pass' => static function (TestRunner $t) use ($createTable54, $rows54, $indexes54): void {
        $t->throws(InvalidArgumentException::class, static fn () => SQLiteJsonbGeneratedCheckIndexPlan::plan($createTable54, $rows54, $indexes54, [
            ['rowid' => 101, 'mutations' => [
                ['function' => 'jsonb_set', 'path' => '$.module.slug', 'value' => 'beta'],
            ]],
        ]));
    },
];

return $tests;

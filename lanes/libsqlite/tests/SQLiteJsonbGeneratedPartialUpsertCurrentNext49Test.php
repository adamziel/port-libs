<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBlobValue;
use PortLibs\LibSqlite\SQLiteJsonB;
use PortLibs\LibSqlite\SQLiteJsonbGeneratedPartialUpsertPlan;

$createTableSql = <<<'SQL'
CREATE TABLE app_settings(
  setting_id INTEGER PRIMARY KEY,
  key_name TEXT NOT NULL,
  key_value BLOB,
  load_policy TEXT,
  migration_generation INTEGER,
  plugin_slug TEXT GENERATED ALWAYS AS (jsonb_extract(key_value, '$.plugin.slug')) STORED,
  plugin_enabled INTEGER GENERATED ALWAYS AS (jsonb_extract(key_value, '$.plugin.enabled')) VIRTUAL,
  plugin_rank INTEGER GENERATED ALWAYS AS (jsonb_extract(key_value, '$.plugin.rank')) VIRTUAL
)
SQL;

$jsonb = static fn (array $value): SQLiteBlobValue => new SQLiteBlobValue(SQLiteJsonB::encode($value));

$rows = [
    ['rowid' => 1, 'setting_id' => 1, 'key_name' => 'plugin_alpha', 'key_value' => $jsonb(['plugin' => ['slug' => 'alpha', 'enabled' => 1, 'rank' => 20], 'source' => 'current']), 'load_policy' => 'yes', 'migration_generation' => 3],
    ['rowid' => 2, 'setting_id' => 2, 'key_name' => 'plugin_beta', 'key_value' => $jsonb(['plugin' => ['slug' => 'beta', 'enabled' => 0, 'rank' => 40], 'source' => 'current']), 'load_policy' => 'no', 'migration_generation' => 7],
    ['rowid' => 3, 'setting_id' => 3, 'key_name' => 'plugin_gamma', 'key_value' => $jsonb(['plugin' => ['slug' => 'gamma', 'rank' => 30], 'source' => 'current']), 'load_policy' => 'yes', 'migration_generation' => 5],
    ['rowid' => 4, 'setting_id' => 4, 'key_name' => 'plugin_delta', 'key_value' => $jsonb(['plugin' => ['slug' => 'delta', 'enabled' => 1, 'rank' => 10], 'source' => 'current']), 'load_policy' => 'yes', 'migration_generation' => 1],
];

$incoming = [
    ['rowid' => 10, 'setting_id' => 10, 'key_name' => 'plugin_alpha', 'key_value' => $jsonb(['plugin' => ['slug' => 'alpha', 'enabled' => 0, 'rank' => 25], 'source' => 'import']), 'load_policy' => 'no', 'migration_generation' => 9],
    ['rowid' => 11, 'setting_id' => 11, 'key_name' => 'plugin_beta', 'key_value' => $jsonb(['plugin' => ['slug' => 'beta', 'enabled' => 1, 'rank' => 45], 'source' => 'import']), 'load_policy' => 'yes', 'migration_generation' => 8],
    ['rowid' => 12, 'setting_id' => 12, 'key_name' => 'plugin_gamma', 'key_value' => $jsonb(['plugin' => ['slug' => 'gamma', 'enabled' => 1, 'rank' => 35], 'source' => 'stale']), 'load_policy' => 'no', 'migration_generation' => 4],
    ['rowid' => 13, 'setting_id' => 13, 'key_name' => 'plugin_epsilon', 'key_value' => $jsonb(['plugin' => ['slug' => 'epsilon', 'enabled' => 1, 'rank' => 5], 'source' => 'import']), 'load_policy' => 'yes', 'migration_generation' => 2],
];

$indexes = [
    ['name' => 'idx_enabled_partial', 'rootPage' => 31, 'sql' => 'CREATE INDEX idx_enabled_partial ON app_settings(plugin_enabled) WHERE plugin_enabled = 1'],
    ['name' => 'idx_slug_partial', 'rootPage' => 32, 'unique' => true, 'sql' => 'CREATE UNIQUE INDEX idx_slug_partial ON app_settings(plugin_slug COLLATE NOCASE) WHERE plugin_slug IS NOT NULL'],
    ['name' => 'idx_rank_partial', 'rootPage' => 33, 'sql' => 'CREATE INDEX idx_rank_partial ON app_settings(plugin_rank DESC) WHERE plugin_rank IS NOT NULL'],
];

$jsonSetValues = [
    '$.plugin.enabled' => ['excluded_json' => '$.plugin.enabled'],
    '$.plugin.rank' => ['excluded_json' => '$.plugin.rank'],
    '$.source' => ['excluded_json' => '$.source'],
    '$.previous_generation' => ['current_column' => 'migration_generation'],
    '$.import_context' => ['json' => '{"tool":"data-liberation","batch":49}'],
];

$plan = static fn (): array => SQLiteJsonbGeneratedPartialUpsertPlan::plan(
    $createTableSql,
    $rows,
    $incoming,
    $indexes,
    $jsonSetValues,
    static fn (array $current, array $excluded): bool => (int) $excluded['migration_generation'] >= (int) $current['migration_generation'],
);

$tests = [
    'jsonb generated partial upsert current next49 table metadata' => static function (TestRunner $t) use ($plan): void {
        $data = $plan();

        $t->same('app_settings', $data['table']);
        $t->same(3, count($data['generated_columns']));
        $t->same(['plugin_slug', 'plugin_enabled', 'plugin_rank'], array_column($data['generated_columns'], 'name'));
        $t->same(['STORED', 'VIRTUAL', 'VIRTUAL'], array_column($data['generated_columns'], 'storage'));
        $t->same(512, $data['pageSize']);
    },
    'jsonb generated partial upsert current next49 row routing' => static function (TestRunner $t) use ($plan): void {
        $data = $plan();

        $t->same(3, $data['changes']);
        $t->same(['plugin_epsilon'], array_column($data['inserted_rows'], 'key_name'));
        $t->same(['plugin_alpha', 'plugin_beta'], array_column($data['updated_rows'], 'key_name'));
        $t->same(['plugin_gamma'], array_column($data['skipped_rows'], 'key_name'));
        $t->same(3, count($data['matched_rows']));
        $t->same(['plugin_alpha', 'plugin_beta', 'plugin_gamma', 'plugin_delta', 'plugin_epsilon'], array_column($data['after'], 'key_name'));
    },
    'jsonb generated partial upsert current next49 generated values after upsert' => static function (TestRunner $t) use ($plan): void {
        $after = $plan()['after'];

        $t->same(['alpha', 'beta', 'gamma', 'delta', 'epsilon'], array_column($after, 'plugin_slug'));
        $t->same([0, 1, null, 1, 1], array_column($after, 'plugin_enabled'));
        $t->same([25, 45, 30, 10, 5], array_column($after, 'plugin_rank'));
        $t->same('no', $after[0]['load_policy']);
        $t->same('yes', $after[1]['load_policy']);
        $t->same('yes', $after[2]['load_policy']);
        $t->same(9, $after[0]['migration_generation']);
        $t->same(8, $after[1]['migration_generation']);
        $t->same(5, $after[2]['migration_generation']);
    },
    'jsonb generated partial upsert current next49 decodes updated JSONB payloads' => static function (TestRunner $t) use ($plan): void {
        $after = $plan()['after'];
        $alpha = SQLiteJsonB::decode($after[0]['key_value']->bytes);
        $beta = SQLiteJsonB::decode($after[1]['key_value']->bytes);
        $gamma = SQLiteJsonB::decode($after[2]['key_value']->bytes);

        $t->same(0, $alpha['plugin']['enabled']);
        $t->same(25, $alpha['plugin']['rank']);
        $t->same('import', $alpha['source']);
        $t->same(3, $alpha['previous_generation']);
        $t->same(['tool' => 'data-liberation', 'batch' => 49], $alpha['import_context']);
        $t->same(1, $beta['plugin']['enabled']);
        $t->same(45, $beta['plugin']['rank']);
        $t->same('import', $beta['source']);
        $t->same(7, $beta['previous_generation']);
        $t->same('current', $gamma['source']);
    },
    'jsonb generated partial upsert current next49 partial index membership changes' => static function (TestRunner $t) use ($plan): void {
        $data = $plan();
        $before = $data['before_indexes']['idx_enabled_partial']['current_entries'];
        $after = $data['after_indexes']['idx_enabled_partial']['current_entries'];

        $t->same([1, 1], array_column($before, 'key'));
        $t->same([1, 1, 1], array_column($after, 'key'));
        $t->same([1, 4], array_column($before, 'rowid'));
        $t->same([2, 4, 13], array_column($after, 'rowid'));
        $t->true($data['before_indexes']['idx_enabled_partial']['current_leaf_page_hex'] !== $data['after_indexes']['idx_enabled_partial']['current_leaf_page_hex']);
    },
    'jsonb generated partial upsert current next49 slug and rank index images' => static function (TestRunner $t) use ($plan): void {
        $data = $plan();

        $t->same(['alpha', 'beta', 'delta', 'gamma'], array_column($data['before_indexes']['idx_slug_partial']['current_entries'], 'key'));
        $t->same(['alpha', 'beta', 'delta', 'epsilon', 'gamma'], array_column($data['after_indexes']['idx_slug_partial']['current_entries'], 'key'));
        $t->same([40, 30, 20, 10], array_column($data['before_indexes']['idx_rank_partial']['current_entries'], 'key'));
        $t->same([45, 30, 25, 10, 5], array_column($data['after_indexes']['idx_rank_partial']['current_entries'], 'key'));
        $t->true($data['after_indexes']['idx_slug_partial']['unique']);
        $t->same('NOCASE', $data['after_indexes']['idx_slug_partial']['collation']);
        $t->true($data['after_indexes']['idx_rank_partial']['descending']);
    },
    'jsonb generated partial upsert current next49 logical index actions' => static function (TestRunner $t) use ($plan): void {
        $actions = $plan()['index_actions'];

        $t->same(9, count($actions));
        $t->same(9, $plan()['index_action_count']);
        $t->same(['delete', 'insert', 'insert'], array_values(array_filter(array_column($actions, 'action'), static fn (string $action, int $index): bool => $index < 3, ARRAY_FILTER_USE_BOTH)));
        $t->same(['idx_enabled_partial', 'idx_enabled_partial', 'idx_enabled_partial'], array_slice(array_column($actions, 'index'), 0, 3));
        $t->same([1, 2, 13], array_slice(array_column($actions, 'rowid'), 0, 3));
        $t->same(['idx_slug_partial', 'idx_rank_partial'], [$actions[3]['index'], $actions[6]['index']]);
        $t->same('epsilon', $actions[3]['key']);
        $t->same([45, 2], $actions[6]['record']);
    },
    'jsonb generated partial upsert current next49 keeps production source neutral' => static function (TestRunner $t): void {
        $source = file_get_contents(dirname(__DIR__) . '/src/SQLiteJsonbGeneratedPartialUpsertPlan.php');
        $t->true(is_string($source));

        $forbidden = [
            'w' . 'p_',
            'w' . 'p_options',
            'option' . '_id',
            'option' . '_name',
            'option' . '_value',
            'auto' . 'load',
            'blog' . '_id',
            'Word' . 'Press',
            'word' . 'press',
        ];
        $pattern = '/' . implode('|', array_map('preg_quote', $forbidden)) . '/';
        $t->same(0, preg_match($pattern, (string) $source));
    },
    'jsonb generated partial upsert current next49 validation errors' => static function (TestRunner $t) use ($createTableSql, $rows, $incoming, $indexes, $jsonSetValues): void {
        $t->throws(InvalidArgumentException::class, static fn () => SQLiteJsonbGeneratedPartialUpsertPlan::plan($createTableSql, $rows, $incoming, $indexes, []));
        $t->throws(InvalidArgumentException::class, static fn () => SQLiteJsonbGeneratedPartialUpsertPlan::plan($createTableSql, $rows, $incoming, $indexes, ['plugin.enabled' => 1]));
        $t->throws(InvalidArgumentException::class, static fn () => SQLiteJsonbGeneratedPartialUpsertPlan::plan($createTableSql, [['rowid' => 1, 'key_value' => '{}']], $incoming, $indexes, $jsonSetValues));
        $t->throws(InvalidArgumentException::class, static fn () => SQLiteJsonbGeneratedPartialUpsertPlan::plan($createTableSql, $rows, [['rowid' => 99, 'key_value' => '{}']], $indexes, $jsonSetValues));
        $t->throws(InvalidArgumentException::class, static fn () => SQLiteJsonbGeneratedPartialUpsertPlan::plan($createTableSql, $rows, $incoming, $indexes, ['$.x' => ['bad' => true]]));
        $t->throws(InvalidArgumentException::class, static fn () => SQLiteJsonbGeneratedPartialUpsertPlan::plan($createTableSql, $rows, $incoming, $indexes, $jsonSetValues, options: ['keyColumn' => 'key-name']));
        $t->throws(InvalidArgumentException::class, static fn () => SQLiteJsonbGeneratedPartialUpsertPlan::plan($createTableSql, $rows, $incoming, $indexes, $jsonSetValues, options: ['copyColumns' => 'load_policy']));
    },
];

return $tests;

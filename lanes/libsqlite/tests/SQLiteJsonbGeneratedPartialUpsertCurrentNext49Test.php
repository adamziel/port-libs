<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBlobValue;
use PortLibs\LibSqlite\SQLiteJsonB;
use PortLibs\LibSqlite\SQLiteJsonbGeneratedPartialUpsertPlan;

$createTableSql = <<<'SQL'
CREATE TABLE wp_options(
  option_id INTEGER PRIMARY KEY,
  option_name TEXT NOT NULL,
  option_value BLOB,
  autoload TEXT,
  migration_generation INTEGER,
  plugin_slug TEXT GENERATED ALWAYS AS (jsonb_extract(option_value, '$.plugin.slug')) STORED,
  plugin_enabled INTEGER GENERATED ALWAYS AS (jsonb_extract(option_value, '$.plugin.enabled')) VIRTUAL,
  plugin_rank INTEGER GENERATED ALWAYS AS (jsonb_extract(option_value, '$.plugin.rank')) VIRTUAL
)
SQL;

$jsonb = static fn (array $value): SQLiteBlobValue => new SQLiteBlobValue(SQLiteJsonB::encode($value));

$rows = [
    ['option_id' => 1, 'option_name' => 'plugin_alpha', 'option_value' => $jsonb(['plugin' => ['slug' => 'alpha', 'enabled' => 1, 'rank' => 20], 'source' => 'current']), 'autoload' => 'yes', 'migration_generation' => 3],
    ['option_id' => 2, 'option_name' => 'plugin_beta', 'option_value' => $jsonb(['plugin' => ['slug' => 'beta', 'enabled' => 0, 'rank' => 40], 'source' => 'current']), 'autoload' => 'no', 'migration_generation' => 7],
    ['option_id' => 3, 'option_name' => 'plugin_gamma', 'option_value' => $jsonb(['plugin' => ['slug' => 'gamma', 'rank' => 30], 'source' => 'current']), 'autoload' => 'yes', 'migration_generation' => 5],
    ['option_id' => 4, 'option_name' => 'plugin_delta', 'option_value' => $jsonb(['plugin' => ['slug' => 'delta', 'enabled' => 1, 'rank' => 10], 'source' => 'current']), 'autoload' => 'yes', 'migration_generation' => 1],
];

$incoming = [
    ['option_id' => 10, 'option_name' => 'plugin_alpha', 'option_value' => $jsonb(['plugin' => ['slug' => 'alpha', 'enabled' => 0, 'rank' => 25], 'source' => 'import']), 'autoload' => 'no', 'migration_generation' => 9],
    ['option_id' => 11, 'option_name' => 'plugin_beta', 'option_value' => $jsonb(['plugin' => ['slug' => 'beta', 'enabled' => 1, 'rank' => 45], 'source' => 'import']), 'autoload' => 'yes', 'migration_generation' => 8],
    ['option_id' => 12, 'option_name' => 'plugin_gamma', 'option_value' => $jsonb(['plugin' => ['slug' => 'gamma', 'enabled' => 1, 'rank' => 35], 'source' => 'stale']), 'autoload' => 'no', 'migration_generation' => 4],
    ['option_id' => 13, 'option_name' => 'plugin_epsilon', 'option_value' => $jsonb(['plugin' => ['slug' => 'epsilon', 'enabled' => 1, 'rank' => 5], 'source' => 'import']), 'autoload' => 'yes', 'migration_generation' => 2],
];

$indexes = [
    ['name' => 'idx_enabled_partial', 'rootPage' => 31, 'sql' => 'CREATE INDEX idx_enabled_partial ON wp_options(plugin_enabled) WHERE plugin_enabled = 1'],
    ['name' => 'idx_slug_partial', 'rootPage' => 32, 'unique' => true, 'sql' => 'CREATE UNIQUE INDEX idx_slug_partial ON wp_options(plugin_slug COLLATE NOCASE) WHERE plugin_slug IS NOT NULL'],
    ['name' => 'idx_rank_partial', 'rootPage' => 33, 'sql' => 'CREATE INDEX idx_rank_partial ON wp_options(plugin_rank DESC) WHERE plugin_rank IS NOT NULL'],
];

$jsonSetValues = [
    '$.plugin.enabled' => ['excluded_json' => '$.plugin.enabled'],
    '$.plugin.rank' => ['excluded_json' => '$.plugin.rank'],
    '$.source' => ['excluded_json' => '$.source'],
    '$.previous_generation' => ['current_column' => 'migration_generation'],
    '$.wp_import' => ['json' => '{"tool":"data-liberation","batch":49}'],
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

        $t->same('wp_options', $data['table']);
        $t->same(3, count($data['generated_columns']));
        $t->same(['plugin_slug', 'plugin_enabled', 'plugin_rank'], array_column($data['generated_columns'], 'name'));
        $t->same(['STORED', 'VIRTUAL', 'VIRTUAL'], array_column($data['generated_columns'], 'storage'));
        $t->same(512, $data['pageSize']);
    },
    'jsonb generated partial upsert current next49 row routing' => static function (TestRunner $t) use ($plan): void {
        $data = $plan();

        $t->same(3, $data['changes']);
        $t->same(['plugin_epsilon'], array_column($data['inserted_rows'], 'option_name'));
        $t->same(['plugin_alpha', 'plugin_beta'], array_column($data['updated_rows'], 'option_name'));
        $t->same(['plugin_gamma'], array_column($data['skipped_rows'], 'option_name'));
        $t->same(3, count($data['matched_rows']));
        $t->same(['plugin_alpha', 'plugin_beta', 'plugin_gamma', 'plugin_delta', 'plugin_epsilon'], array_column($data['after'], 'option_name'));
    },
    'jsonb generated partial upsert current next49 generated values after upsert' => static function (TestRunner $t) use ($plan): void {
        $after = $plan()['after'];

        $t->same(['alpha', 'beta', 'gamma', 'delta', 'epsilon'], array_column($after, 'plugin_slug'));
        $t->same([0, 1, null, 1, 1], array_column($after, 'plugin_enabled'));
        $t->same([25, 45, 30, 10, 5], array_column($after, 'plugin_rank'));
        $t->same('no', $after[0]['autoload']);
        $t->same('yes', $after[1]['autoload']);
        $t->same('yes', $after[2]['autoload']);
        $t->same(9, $after[0]['migration_generation']);
        $t->same(8, $after[1]['migration_generation']);
        $t->same(5, $after[2]['migration_generation']);
    },
    'jsonb generated partial upsert current next49 decodes updated JSONB payloads' => static function (TestRunner $t) use ($plan): void {
        $after = $plan()['after'];
        $alpha = SQLiteJsonB::decode($after[0]['option_value']->bytes);
        $beta = SQLiteJsonB::decode($after[1]['option_value']->bytes);
        $gamma = SQLiteJsonB::decode($after[2]['option_value']->bytes);

        $t->same(0, $alpha['plugin']['enabled']);
        $t->same(25, $alpha['plugin']['rank']);
        $t->same('import', $alpha['source']);
        $t->same(3, $alpha['previous_generation']);
        $t->same(['tool' => 'data-liberation', 'batch' => 49], $alpha['wp_import']);
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
    'jsonb generated partial upsert current next49 validation errors' => static function (TestRunner $t) use ($createTableSql, $rows, $incoming, $indexes, $jsonSetValues): void {
        $t->throws(InvalidArgumentException::class, static fn () => SQLiteJsonbGeneratedPartialUpsertPlan::plan($createTableSql, $rows, $incoming, $indexes, []));
        $t->throws(InvalidArgumentException::class, static fn () => SQLiteJsonbGeneratedPartialUpsertPlan::plan($createTableSql, $rows, $incoming, $indexes, ['plugin.enabled' => 1]));
        $t->throws(InvalidArgumentException::class, static fn () => SQLiteJsonbGeneratedPartialUpsertPlan::plan($createTableSql, [['option_id' => 1, 'option_value' => '{}']], $incoming, $indexes, $jsonSetValues));
        $t->throws(InvalidArgumentException::class, static fn () => SQLiteJsonbGeneratedPartialUpsertPlan::plan($createTableSql, $rows, [['option_id' => 99, 'option_value' => '{}']], $indexes, $jsonSetValues));
        $t->throws(InvalidArgumentException::class, static fn () => SQLiteJsonbGeneratedPartialUpsertPlan::plan($createTableSql, $rows, $incoming, $indexes, ['$.x' => ['bad' => true]]));
    },
];

return $tests;

<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBlobValue;
use PortLibs\LibSqlite\SQLiteGeneratedJsonPathIndexPlan;
use PortLibs\LibSqlite\SQLiteJsonB;
use PortLibs\LibSqlite\SQLiteJsonSubtypeValue;

$createTableSql = <<<'SQL'
CREATE TABLE wp_options(
  option_id INTEGER PRIMARY KEY,
  option_name TEXT NOT NULL,
  option_value TEXT,
  payload BLOB,
  autoload TEXT,
  plugin_slug TEXT GENERATED ALWAYS AS (json_extract(option_value, '$.plugin.slug')) STORED,
  plugin_rank INTEGER AS (json_extract(option_value, '$.plugin.rank')) VIRTUAL,
  plugin_channel TEXT AS (jsonb_extract(payload, '$.plugin.channel')) VIRTUAL
)
SQL;

$rows = static fn (): array => [
    ['option_id' => 1, 'option_name' => 'plugin_alpha', 'option_value' => '{"plugin":{"slug":"alpha","rank":1}}', 'payload' => new SQLiteBlobValue(SQLiteJsonB::encode(['plugin' => ['channel' => 'stable']])), 'autoload' => 'yes'],
    ['option_id' => 2, 'option_name' => 'plugin_beta', 'option_value' => '{"plugin":{"slug":"beta","rank":2}}', 'payload' => new SQLiteBlobValue(SQLiteJsonB::encode(['plugin' => ['channel' => 'beta']])), 'autoload' => 'yes'],
    ['option_id' => 3, 'option_name' => 'plugin_gamma', 'option_value' => '{"plugin":{"slug":"gamma","rank":3}}', 'payload' => new SQLiteBlobValue(SQLiteJsonB::encode(['plugin' => ['channel' => 'stable']])), 'autoload' => 'no'],
    ['option_id' => 4, 'option_name' => 'plugin_empty', 'option_value' => '{"plugin":{}}', 'payload' => null, 'autoload' => 'yes'],
];

$indexes = static fn (): array => [
    ['name' => 'idx_expr_slug', 'rootPage' => 41, 'unique' => true, 'sql' => "CREATE UNIQUE INDEX idx_expr_slug ON wp_options(json_extract(option_value, '$.plugin.slug') COLLATE NOCASE) WHERE plugin_slug IS NOT NULL"],
    ['name' => 'idx_expr_rank', 'rootPage' => 42, 'sql' => "CREATE INDEX idx_expr_rank ON wp_options(json_extract(option_value, '$.plugin.rank') DESC) WHERE plugin_rank IS NOT NULL"],
    ['name' => 'idx_column_slug', 'rootPage' => 43, 'sql' => 'CREATE INDEX idx_column_slug ON wp_options(plugin_slug) WHERE plugin_slug IS NOT NULL'],
    ['name' => 'idx_expr_channel', 'rootPage' => 44, 'sql' => "CREATE INDEX idx_expr_channel ON wp_options(jsonb_extract(payload, '$.plugin.channel') COLLATE RTRIM) WHERE plugin_channel IS NOT NULL"],
    ['name' => 'idx_unmatched_expr', 'rootPage' => 45, 'sql' => "CREATE INDEX idx_unmatched_expr ON wp_options(json_extract(option_value, '$.plugin.missing'))"],
    ['name' => 'idx_wrong_function', 'rootPage' => 46, 'sql' => "CREATE INDEX idx_wrong_function ON wp_options(jsonb_extract(option_value, '$.plugin.slug'))"],
];

$planFor = static function (array $updates, ?array $sourceRows = null, ?array $sourceIndexes = null, ?string $schema = null): array {
    $updates = array_map(static function (array $update): array {
        $update['column'] ??= 'option_value';
        return $update;
    }, $updates);

    return SQLiteGeneratedJsonPathIndexPlan::plan(
        $schema ?? $GLOBALS['createTableSql'],
        $sourceRows ?? $GLOBALS['rows'](),
        $sourceIndexes ?? $GLOBALS['indexes'](),
        $updates,
    );
};

$tests = [];

$cases = [
    'slug expression update emits expression and column indexes' => [
        [['rowid' => 1, 'mutations' => [['function' => 'json_set', 'path' => '$.plugin.slug', 'value' => 'alpha-pro']]]],
        [['idx_expr_slug', 'alpha', 'alpha-pro', true, 'json_extract'], ['idx_column_slug', 'alpha', 'alpha-pro', false, null]],
    ],
    'rank expression update emits descending expression index' => [
        [['rowid' => 1, 'mutations' => [['function' => 'json_set', 'path' => '$.plugin.rank', 'value' => 10]]]],
        [['idx_expr_rank', 1, 10, true, 'json_extract']],
    ],
    'rank removal deletes expression index entry' => [
        [['rowid' => 1, 'mutations' => [['function' => 'json_set', 'path' => '$.plugin', 'value' => new SQLiteJsonSubtypeValue('{"slug":"alpha"}')]]]],
        [['idx_expr_rank', 1, null, true, 'json_extract']],
    ],
    'missing slug creates expression and column index entries' => [
        [['rowid' => 4, 'mutations' => [['function' => 'json_set', 'path' => '$.plugin.slug', 'value' => 'delta']]]],
        [['idx_expr_slug', null, 'delta', true, 'json_extract'], ['idx_column_slug', null, 'delta', false, null]],
    ],
    'missing rank creates expression index entry' => [
        [['rowid' => 4, 'mutations' => [['function' => 'json_set', 'path' => '$.plugin.rank', 'value' => 4]]]],
        [['idx_expr_rank', null, 4, true, 'json_extract']],
    ],
    'jsonb payload channel update emits jsonb expression index' => [
        [['rowid' => 2, 'column' => 'payload', 'mutations' => [['function' => 'json_set', 'path' => '$.plugin.channel', 'value' => 'stable']]]],
        [['idx_expr_channel', 'beta', 'stable', true, 'jsonb_extract']],
        [['option_id' => 2, 'option_name' => 'plugin_beta', 'option_value' => '{"plugin":{"slug":"beta","rank":2}}', 'payload' => new SQLiteBlobValue(SQLiteJsonB::encode(['plugin' => ['channel' => 'beta']])), 'autoload' => 'yes']],
        [['name' => 'idx_expr_channel', 'rootPage' => 44, 'sql' => "CREATE INDEX idx_expr_channel ON wp_options(jsonb_extract(payload, '$.plugin.channel') COLLATE RTRIM) WHERE plugin_channel IS NOT NULL"]],
    ],
    'no-op slug leaves expression index stable' => [
        [['rowid' => 1, 'mutations' => [['function' => 'json_set', 'path' => '$.plugin.slug', 'value' => 'alpha']]]],
        [],
    ],
    'unrelated path leaves expression index stable' => [
        [['rowid' => 1, 'mutations' => [['function' => 'json_set', 'path' => '$.plugin.extra', 'value' => true]]]],
        [],
    ],
    'missing rowid leaves expression indexes stable' => [
        [['rowid' => 99, 'mutations' => [['function' => 'json_set', 'path' => '$.plugin.slug', 'value' => 'ghost']]]],
        [],
    ],
];

foreach ($cases as $name => $case) {
    [$updates, $expected] = $case;
    $sourceRows = $case[2] ?? null;
    $sourceIndexes = $case[3] ?? null;
    $tests['json index expression generated column current source next108 ' . $name] = static function (TestRunner $t) use ($planFor, $updates, $expected, $sourceRows, $sourceIndexes): void {
        $plan = $planFor($updates, $sourceRows, $sourceIndexes);
        $t->same($expected, array_map(
            static fn (array $update): array => [$update['index'], $update['current'], $update['next'], $update['expressionIndex'], $update['expressionFunction']],
            $plan['index_updates'],
        ));
    };
}

$tests['json index expression generated column current source next108 records expression source metadata'] = static function (TestRunner $t) use ($planFor): void {
    $plan = $planFor([['rowid' => 1, 'mutations' => [['function' => 'json_set', 'path' => '$.plugin.slug', 'value' => 'alpha-pro']]]]);
    $update = $plan['index_updates'][0];
    $t->same(['option_value', '$.plugin.slug', true, 'json_extract', 'NOCASE'], [$update['source'], $update['path'], $update['expressionIndex'], $update['expressionFunction'], $update['collation']]);
};

$tests['json index expression generated column current source next108 preserves generated function metadata'] = static function (TestRunner $t) use ($planFor): void {
    $plan = $planFor([]);
    $t->same([
        ['plugin_slug', 'json_extract', 'option_value'],
        ['plugin_rank', 'json_extract', 'option_value'],
        ['plugin_channel', 'jsonb_extract', 'payload'],
    ], array_map(static fn (array $column): array => [$column['name'], $column['functionName'], $column['source']], $plan['generated_columns']));
};

$tests['json index expression generated column current source next108 ignores wrong path and wrong function expression indexes'] = static function (TestRunner $t) use ($planFor): void {
    $plan = $planFor([['rowid' => 1, 'mutations' => [['function' => 'json_set', 'path' => '$.plugin.slug', 'value' => 'alpha-pro']]]]);
    $t->same(false, in_array('idx_unmatched_expr', array_column($plan['index_updates'], 'index'), true));
    $t->same(false, in_array('idx_wrong_function', array_column($plan['index_updates'], 'index'), true));
};

$tests['json index expression generated column current source next108 detects unique expression conflict'] = static function (TestRunner $t) use ($planFor): void {
    $t->throws(InvalidArgumentException::class, static fn () => $planFor([['rowid' => 1, 'mutations' => [['function' => 'json_set', 'path' => '$.plugin.slug', 'value' => 'beta']]]]));
};

$tests['json index expression generated column current source next108 allows unique null expression keys'] = static function (TestRunner $t) use ($planFor): void {
    $plan = $planFor([['rowid' => 1, 'mutations' => [['function' => 'json_set', 'path' => '$.plugin.slug', 'value' => null]]]]);
    $t->same([true, false, null], [$plan['index_updates'][0]['delete'], $plan['index_updates'][0]['insert'], $plan['index_updates'][0]['next']]);
};

$tests['json index expression generated column current source next108 btree plan carries expression metadata'] = static function (TestRunner $t): void {
    $plan = SQLiteGeneratedJsonPathIndexPlan::btreeYieldPlan($GLOBALS['createTableSql'], $GLOBALS['rows'](), $GLOBALS['indexes'](), [
        ['rowid' => 1, 'column' => 'option_value', 'mutations' => [['function' => 'json_set', 'path' => '$.plugin.rank', 'value' => 10]]],
    ]);
    $t->same([true, 'json_extract', 'option_value'], [$plan['btree_indexes']['idx_expr_rank']['expressionIndex'], $plan['btree_indexes']['idx_expr_rank']['expressionFunction'], $plan['btree_indexes']['idx_expr_rank']['source']]);
    $t->same([true, 'json_extract'], [$plan['btree_actions'][0]['expressionIndex'], $plan['btree_actions'][0]['expressionFunction']]);
};

$tests['json index expression generated column current source next108 delete plan carries expression metadata'] = static function (TestRunner $t): void {
    $plan = SQLiteGeneratedJsonPathIndexPlan::deleteBtreeYieldPlan($GLOBALS['createTableSql'], $GLOBALS['rows'](), $GLOBALS['indexes'](), [1]);
    $slugDelete = array_values(array_filter($plan['index_deletes'], static fn (array $delete): bool => $delete['index'] === 'idx_expr_slug'))[0];
    $t->same([true, 'json_extract', 'option_value', 'alpha'], [$slugDelete['expressionIndex'], $slugDelete['expressionFunction'], $slugDelete['source'], $slugDelete['current']]);
};

$tests['json index expression generated column current source next108 covering delete plan carries expression metadata'] = static function (TestRunner $t): void {
    $indexes = [
        ['name' => 'idx_expr_slug_cover', 'rootPage' => 51, 'coveringColumns' => ['option_name', 'autoload'], 'sql' => "CREATE INDEX idx_expr_slug_cover ON wp_options(json_extract(option_value, '$.plugin.slug'), option_name, autoload) WHERE plugin_slug IS NOT NULL"],
    ];
    $plan = SQLiteGeneratedJsonPathIndexPlan::coveringDeleteYieldPlan($GLOBALS['createTableSql'], $GLOBALS['rows'](), $indexes, [1]);
    $t->same([true, 'json_extract', 'option_value'], [$plan['btree_indexes']['idx_expr_slug_cover']['expressionIndex'], $plan['btree_indexes']['idx_expr_slug_cover']['expressionFunction'], $plan['btree_indexes']['idx_expr_slug_cover']['source']]);
    $t->same(['alpha', ['option_name' => 'plugin_alpha', 'autoload' => 'yes']], [$plan['delete_entries'][0]['key'], $plan['delete_entries'][0]['coveringValues']]);
};

foreach ([
    ['alpha-pro', ['alpha', 'alpha-pro']],
    ['alpha-final', ['alpha', 'alpha-final']],
    ['omega', ['alpha', 'omega']],
    ['mu-plugin', ['alpha', 'mu-plugin']],
    ['network-cache', ['alpha', 'network-cache']],
] as [$slug, $expected]) {
    $tests['json index expression generated column current source next108 slug variant ' . $slug] = static function (TestRunner $t) use ($planFor, $slug, $expected): void {
        $plan = $planFor([['rowid' => 1, 'mutations' => [['function' => 'json_set', 'path' => '$.plugin.slug', 'value' => $slug]]]]);
        $t->same($expected, [$plan['index_updates'][0]['current'], $plan['index_updates'][0]['next']]);
    };
}

foreach ([
    [5, [1, 5]],
    [10, [1, 10]],
    ['10', [1, '10']],
    [0, [1, 0]],
    [null, [1, null]],
] as [$rank, $expected]) {
    $tests['json index expression generated column current source next108 rank variant ' . (string) ($rank ?? 'null')] = static function (TestRunner $t) use ($planFor, $rank, $expected): void {
        $plan = $planFor([['rowid' => 1, 'mutations' => [['function' => 'json_set', 'path' => '$.plugin.rank', 'value' => $rank]]]]);
        $rankUpdate = array_values(array_filter($plan['index_updates'], static fn (array $update): bool => $update['index'] === 'idx_expr_rank'))[0];
        $t->same($expected, [$rankUpdate['current'], $rankUpdate['next']]);
    };
}

foreach ([
    ['stable', []],
    ['nightly', [['idx_expr_channel', 'stable', 'nightly', true, 'jsonb_extract']]],
    ['beta', [['idx_expr_channel', 'stable', 'beta', true, 'jsonb_extract']]],
    ['release ', [['idx_expr_channel', 'stable', 'release ', true, 'jsonb_extract']]],
] as [$channel, $expected]) {
    $tests['json index expression generated column current source next108 jsonb channel variant ' . trim($channel)] = static function (TestRunner $t) use ($planFor, $channel, $expected): void {
        $rows = [['option_id' => 1, 'option_name' => 'plugin_alpha', 'option_value' => '{"plugin":{"slug":"alpha","rank":1}}', 'payload' => new SQLiteBlobValue(SQLiteJsonB::encode(['plugin' => ['channel' => 'stable']])), 'autoload' => 'yes']];
        $indexes = [['name' => 'idx_expr_channel', 'rootPage' => 44, 'sql' => "CREATE INDEX idx_expr_channel ON wp_options(jsonb_extract(payload, '$.plugin.channel') COLLATE RTRIM) WHERE plugin_channel IS NOT NULL"]];
        $plan = $planFor([['rowid' => 1, 'column' => 'payload', 'mutations' => [['function' => 'json_set', 'path' => '$.plugin.channel', 'value' => $channel]]]], $rows, $indexes);
        $t->same($expected, array_map(static fn (array $update): array => [$update['index'], $update['current'], $update['next'], $update['expressionIndex'], $update['expressionFunction']], $plan['index_updates']));
    };
}

foreach ([
    ["CREATE INDEX idx_expr_slug ON wp_options(json_extract(option_value, '$.plugin.slug'))", true],
    ["CREATE INDEX idx_expr_slug ON wp_options(json_extract(\"option_value\", '$.plugin.slug'))", true],
    ["CREATE INDEX idx_expr_slug ON wp_options(jsonb_extract(option_value, '$.plugin.slug'))", false],
    ["CREATE INDEX idx_expr_slug ON wp_options(json_extract(option_value, '$.plugin.other'))", false],
    ['CREATE INDEX idx_column_slug ON wp_options(plugin_slug)', false],
] as $case => [$sql, $expression]) {
    $tests['json index expression generated column current source next108 expression parse case ' . $case] = static function (TestRunner $t) use ($planFor, $sql, $expression): void {
        if (!$expression && !str_contains($sql, 'plugin_slug)')) {
            $t->throws(InvalidArgumentException::class, static fn () => $planFor([['rowid' => 1, 'mutations' => [['function' => 'json_set', 'path' => '$.plugin.slug', 'value' => 'alpha-pro']]]], null, [['name' => 'idx_case', 'sql' => $sql]]));
            return;
        }
        $plan = $planFor([['rowid' => 1, 'mutations' => [['function' => 'json_set', 'path' => '$.plugin.slug', 'value' => 'alpha-pro']]]], null, [['name' => 'idx_case', 'sql' => $sql]]);
        $t->same($expression, $plan['index_updates'][0]['expressionIndex']);
    };
}

foreach ([
    ['json_set', 'alpha-set'],
    ['JSON_SET', 'alpha-upper'],
    ['json_replace', 'alpha-replace'],
] as [$function, $slug]) {
    $tests['json index expression generated column current source next108 mutation function ' . $function] = static function (TestRunner $t) use ($planFor, $function, $slug): void {
        $plan = $planFor([['rowid' => 1, 'mutations' => [['function' => $function, 'path' => '$.plugin.slug', 'value' => $slug]]]]);
        $t->same(['alpha', $slug], [$plan['index_updates'][0]['current'], $plan['index_updates'][0]['next']]);
    };
}

$tests['json index expression generated column current source next108 jsonb generated function mismatch rejected as no coverage'] = static function (TestRunner $t) use ($planFor): void {
    $t->throws(InvalidArgumentException::class, static fn () => $planFor([], null, [['name' => 'idx_wrong', 'sql' => "CREATE INDEX idx_wrong ON wp_options(json_extract(payload, '$.plugin.channel'))"]]));
};

$tests['json index expression generated column current source next108 rejects malformed expression path'] = static function (TestRunner $t) use ($planFor): void {
    $t->throws(InvalidArgumentException::class, static fn () => $planFor([], null, [['name' => 'idx_bad', 'sql' => "CREATE INDEX idx_bad ON wp_options(json_extract(option_value, '$.plugin['))"]]));
};

$tests['json index expression generated column current source next108 application payload image updated'] = static function (TestRunner $t) use ($planFor): void {
    $plan = $planFor([['rowid' => 1, 'mutations' => [['function' => 'json_set', 'path' => '$.plugin.slug', 'value' => 'alpha-pro']]]]);
    $t->same('alpha-pro', json_decode((string) $plan['after'][0]['option_value'], true, 512, JSON_THROW_ON_ERROR)['plugin']['slug']);
    $t->same('plugin_alpha', $plan['after'][0]['option_name']);
};

return $tests;

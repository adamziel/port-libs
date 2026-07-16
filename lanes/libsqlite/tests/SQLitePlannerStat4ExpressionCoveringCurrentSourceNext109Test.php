<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteSelectExpressionIndexPlan;

$expr = static fn (string $function, string $column, ?string $path = null): array => array_filter(
    ['function' => $function, 'column' => $column, 'path' => $path],
    static fn (mixed $value): bool => $value !== null,
);
$column = static fn (string $name): array => ['column' => $name];
$point = static fn (array $left, mixed $value): array => ['operator' => '=', 'left' => $left, 'right' => $value];
$range = static fn (array $left, string $operator, mixed $value): array => ['operator' => $operator, 'left' => $left, 'right' => $value];
$between = static fn (array $left, mixed $lower, mixed $upper): array => ['operator' => 'BETWEEN', 'left' => $left, 'lower' => $lower, 'upper' => $upper];
$in = static fn (array $left, array $values): array => ['operator' => 'IN', 'left' => $left, 'values' => $values];
$and = static fn (array ...$terms): array => ['operator' => 'AND', 'terms' => $terms];

$jsonPath = '$.plugin.channel';
$jsonExpr = $expr('jsonb_extract', 'option_value', $jsonPath);
$lowerExpr = $expr('lower', 'option_name');
$lengthExpr = $expr('length', 'option_name');
$integerExpr = $expr('cast_integer', 'option_value');
$autoloadYes = $point($column('autoload'), 'yes');

$stat4Samples = static fn (): array => [
    ['neq' => '2 1', 'nlt' => '0 0', 'ndlt' => '0 0', 'sample' => ['alpha', 'autoload_yes']],
    ['neq' => '4 1', 'nlt' => '2 1', 'ndlt' => '1 1', 'sample' => ['beta', 'autoload_yes']],
    ['neq' => '6 1', 'nlt' => '6 2', 'ndlt' => '2 2', 'sample' => ['delta', 'autoload_yes']],
    ['neq' => '8 1', 'nlt' => '12 3', 'ndlt' => '3 3', 'sample' => ['stable', 'autoload_yes']],
    ['neq' => '10 1', 'nlt' => '20 4', 'ndlt' => '4 4', 'sample' => ['theta', 'autoload_yes']],
];

$indexes = static fn (): array => [
    [
        'name' => 'idx_wp_options_channel_covering_stat4_next109',
        'rootPage' => 1091,
        'estimatedRows' => 200,
        'stat4Samples' => $stat4Samples(),
        'coveringColumns' => ['option_name', 'autoload', 'option_id', 'blog_id'],
        'sql' => "CREATE INDEX idx_wp_options_channel_covering_stat4_next109 ON wp_options(jsonb_extract(option_value, '$.plugin.channel'), autoload, option_id) WHERE autoload = 'yes'",
    ],
    [
        'name' => 'idx_wp_options_lower_covering_stat4_next109',
        'rootPage' => 1092,
        'estimatedRows' => 150,
        'stat4Samples' => [
            ['neq' => 3, 'nlt' => 0, 'ndlt' => 0, 'sample' => ['home']],
            ['neq' => 5, 'nlt' => 3, 'ndlt' => 1, 'sample' => ['siteurl']],
            ['neq' => 7, 'nlt' => 8, 'ndlt' => 2, 'sample' => ['stylesheet']],
        ],
        'coveringColumns' => ['option_name', 'autoload'],
        'sql' => "CREATE INDEX idx_wp_options_lower_covering_stat4_next109 ON wp_options(lower(option_name), autoload) WHERE autoload = 'yes'",
    ],
    [
        'name' => 'idx_wp_options_length_covering_stat4_next109',
        'rootPage' => 1093,
        'estimatedRows' => 120,
        'stat4Samples' => [
            ['neq' => 2, 'nlt' => 0, 'ndlt' => 0, 'sample' => [4]],
            ['neq' => 3, 'nlt' => 2, 'ndlt' => 1, 'sample' => [7]],
            ['neq' => 5, 'nlt' => 5, 'ndlt' => 2, 'sample' => [12]],
        ],
        'coveringColumns' => ['option_name', 'autoload'],
        'sql' => "CREATE INDEX idx_wp_options_length_covering_stat4_next109 ON wp_options(length(option_name), autoload) WHERE autoload = 'yes'",
    ],
    [
        'name' => 'idx_wp_options_int_covering_stat4_next109',
        'rootPage' => 1094,
        'estimatedRows' => 90,
        'stat4Samples' => [
            ['neq' => 1, 'nlt' => 0, 'ndlt' => 0, 'sample' => [0]],
            ['neq' => 2, 'nlt' => 1, 'ndlt' => 1, 'sample' => [10]],
            ['neq' => 4, 'nlt' => 3, 'ndlt' => 2, 'sample' => [30]],
        ],
        'coveringColumns' => ['option_value', 'autoload'],
        'sql' => "CREATE INDEX idx_wp_options_int_covering_stat4_next109 ON wp_options(CAST(option_value AS INTEGER), autoload) WHERE autoload = 'yes'",
    ],
    [
        'name' => 'idx_wp_options_channel_not_covering_stat4_next109',
        'rootPage' => 1095,
        'estimatedRows' => 40,
        'stat4Samples' => $stat4Samples(),
        'coveringColumns' => ['autoload'],
        'sql' => "CREATE INDEX idx_wp_options_channel_not_covering_stat4_next109 ON wp_options(jsonb_extract(option_value, '$.plugin.channel'), autoload) WHERE autoload = 'yes'",
    ],
];

$rows = static fn (): array => [
    ['rowid' => 5, 'option_id' => 5, 'option_name' => 'plugin_beta_a', 'autoload' => 'yes', 'blog_id' => 1, 'option_value' => '{"plugin":{"channel":"beta","enabled":true}}'],
    ['rowid' => 7, 'option_id' => 7, 'option_name' => 'plugin_stable', 'autoload' => 'yes', 'blog_id' => 1, 'option_value' => '{"plugin":{"channel":"stable","enabled":true}}'],
    ['rowid' => 9, 'option_id' => 9, 'option_name' => 'plugin_alpha', 'autoload' => 'yes', 'blog_id' => 1, 'option_value' => '{"plugin":{"channel":"alpha","enabled":false}}'],
    ['rowid' => 11, 'option_id' => 11, 'option_name' => 'plugin_beta_b', 'autoload' => 'yes', 'blog_id' => 2, 'option_value' => '{"plugin":{"channel":"beta","enabled":false}}'],
    ['rowid' => 13, 'option_id' => 13, 'option_name' => 'plugin_theta', 'autoload' => 'no', 'blog_id' => 1, 'option_value' => '{"plugin":{"channel":"theta"}}'],
    ['rowid' => 15, 'option_id' => 15, 'option_name' => 'plugin_missing', 'autoload' => 'yes', 'blog_id' => 1, 'option_value' => '{"plugin":{"enabled":true}}'],
    ['rowid' => 17, 'option_id' => 17, 'option_name' => 'plugin_bad_json', 'autoload' => 'yes', 'blog_id' => 1, 'option_value' => '{"plugin":'],
    ['rowid' => 19, 'option_id' => 19, 'option_name' => 'PLUGIN_DELTA', 'autoload' => 'yes', 'blog_id' => 3, 'option_value' => '{"plugin":{"channel":"delta"}}'],
];

$plan = static fn (array $predicate = null, array $needed = null, array $indexList = null, array $currentRows = null): ?array => SQLiteSelectExpressionIndexPlan::stat4ExpressionCoveringCurrentSourcePlan(
    $indexList ?? [$indexes()[0]],
    $predicate ?? $and($in($jsonExpr, ['beta', 'stable', null]), $autoloadYes),
    $currentRows ?? $rows(),
    [['function' => 'jsonb_extract', 'column' => 'option_value', 'path' => $jsonPath], ['column' => 'autoload']],
    $needed ?? ['option_name', 'autoload', 'option_id', 'blog_id'],
    [$jsonExpr],
);

$tests = [
    'planner stat4 expression covering current source next109 chooses covering stat4 index' => static fn (TestRunner $t) => $t->same('idx_wp_options_channel_covering_stat4_next109', $plan()['name']),
    'planner stat4 expression covering current source next109 preserves root page' => static fn (TestRunner $t) => $t->same(1091, $plan()['rootPage']),
    'planner stat4 expression covering current source next109 records dependency marker' => static fn (TestRunner $t) => $t->same(['sqlite-stat4-expression-covering-current-source-next109'], $plan()['dependencies']),
    'planner stat4 expression covering current source next109 stays covering' => static fn (TestRunner $t) => $t->same(true, $plan()['covering']),
    'planner stat4 expression covering current source next109 satisfies order' => static fn (TestRunner $t) => $t->same(true, $plan()['orderBySatisfied']),
    'planner stat4 expression covering current source next109 uses in operator' => static fn (TestRunner $t) => $t->same('IN', $plan()['operator']),
    'planner stat4 expression covering current source next109 keeps json path' => static fn (TestRunner $t) => $t->same($jsonPath, $plan()['path']),
    'planner stat4 expression covering current source next109 reports matched stat4 samples' => static fn (TestRunner $t) => $t->same(2, $plan()['stat4MatchedSamples']),
    'planner stat4 expression covering current source next109 reports covered row count' => static fn (TestRunner $t) => $t->same(3, $plan()['coveredRowCount']),
    'planner stat4 expression covering current source next109 sorts current rows by expression key' => static fn (TestRunner $t) => $t->same(['beta', 'beta', 'stable'], array_map(static fn (array $pair): mixed => $pair['current']['key'], $plan()['currentNextRows'])),
    'planner stat4 expression covering current source next109 stable row is after beta rows' => static fn (TestRunner $t) => $t->same(7, $plan()['currentNextRows'][2]['current']['rowid']),
    'planner stat4 expression covering current source next109 first beta points to second beta' => static fn (TestRunner $t) => $t->same(11, $plan()['currentNextRows'][0]['next']['rowid']),
    'planner stat4 expression covering current source next109 last row next is null' => static fn (TestRunner $t) => $t->same(null, $plan()['currentNextRows'][2]['next']),
    'planner stat4 expression covering current source next109 emits covering option name' => static fn (TestRunner $t) => $t->same('plugin_beta_a', $plan()['currentNextRows'][0]['current']['covering']['option_name']),
    'planner stat4 expression covering current source next109 emits covering blog id' => static fn (TestRunner $t) => $t->same(2, $plan()['currentNextRows'][1]['current']['covering']['blog_id']),
    'planner stat4 expression covering current source next109 emits expression payload' => static fn (TestRunner $t) => $t->same('beta', $plan()['currentNextRows'][0]['current']['coveringExpressions']['jsonb_extract(option_value)']),
    'planner stat4 expression covering current source next109 ignores missing json path rows' => static fn (TestRunner $t) => $t->same(false, in_array(15, array_map(static fn (array $pair): mixed => $pair['current']['rowid'], $plan()['currentNextRows']), true)),
    'planner stat4 expression covering current source next109 ignores malformed json rows' => static fn (TestRunner $t) => $t->same(false, in_array(17, array_map(static fn (array $pair): mixed => $pair['current']['rowid'], $plan()['currentNextRows']), true)),
    'planner stat4 expression covering current source next109 ignores unmatched stat4 row' => static fn (TestRunner $t) => $t->same(false, in_array(19, array_map(static fn (array $pair): mixed => $pair['current']['rowid'], $plan()['currentNextRows']), true)),
    'planner stat4 expression covering current source next109 rejects missing partial proof' => static fn (TestRunner $t) => $t->same(null, $plan($in($jsonExpr, ['beta', 'stable']))),
    'planner stat4 expression covering current source next109 rejects non covering index' => static fn (TestRunner $t) => $t->same(null, $plan(null, ['option_name'], [$indexes()[4]])),
    'planner stat4 expression covering current source next109 rejects no stat4 index' => static fn (TestRunner $t) => $t->same(null, $plan(null, ['autoload'], [[
        'name' => 'idx_no_stat4',
        'coveringColumns' => ['autoload'],
        'sql' => "CREATE INDEX idx_no_stat4 ON wp_options(jsonb_extract(option_value, '$.plugin.channel'), autoload) WHERE autoload = 'yes'",
    ]])),
    'planner stat4 expression covering current source next109 preserves stat4 estimate' => static fn (TestRunner $t) => $t->same(12, $plan()['stat4Estimate']),
    'planner stat4 expression covering current source next109 preserves estimated rows' => static fn (TestRunner $t) => $t->same(12, $plan()['estimatedRows']),
    'planner stat4 expression covering current source next109 exposes first matched stat4 key' => static fn (TestRunner $t) => $t->same('beta', $plan()['stat4MatchedCurrentNext'][0]['current']['key']),
    'planner stat4 expression covering current source next109 exposes second matched stat4 key' => static fn (TestRunner $t) => $t->same('stable', $plan()['stat4MatchedCurrentNext'][1]['current']['key']),
    'planner stat4 expression covering current source next109 emits source offset for stable row' => static fn (TestRunner $t) => $t->same(1, $plan()['currentNextRows'][2]['current']['sourceOffset']),
    'planner stat4 expression covering current source next109 emits source offset for second beta row' => static fn (TestRunner $t) => $t->same(3, $plan()['currentNextRows'][1]['current']['sourceOffset']),
    'planner stat4 expression covering current source next109 emits covering autoload' => static fn (TestRunner $t) => $t->same('yes', $plan()['currentNextRows'][0]['current']['covering']['autoload']),
    'planner stat4 expression covering current source next109 filters ordinary equality residual' => static fn (TestRunner $t) => $t->same(false, in_array(13, array_map(static fn (array $pair): mixed => $pair['current']['rowid'], $plan($and($range($jsonExpr, '>=', 'stable'), $autoloadYes))['currentNextRows']), true)),
];

$tests['planner stat4 expression covering current source next109 handles point predicate'] = static function (TestRunner $t) use ($plan, $and, $point, $jsonExpr, $autoloadYes): void {
    $candidate = $plan($and($point($jsonExpr, 'stable'), $autoloadYes));
    $t->same(1, $candidate['coveredRowCount']);
    $t->same('stable', $candidate['currentNextRows'][0]['current']['key']);
    $t->same('plugin_stable', $candidate['currentNextRows'][0]['current']['covering']['option_name']);
};

$tests['planner stat4 expression covering current source next109 handles between predicate'] = static function (TestRunner $t) use ($plan, $and, $between, $jsonExpr, $autoloadYes): void {
    $candidate = $plan($and($between($jsonExpr, 'alpha', 'delta'), $autoloadYes));
    $t->same(4, $candidate['coveredRowCount']);
    $t->same(['alpha', 'beta', 'beta', 'delta'], array_map(static fn (array $pair): mixed => $pair['current']['key'], $candidate['currentNextRows']));
    $t->same(19, $candidate['currentNextRows'][3]['current']['rowid']);
};

$tests['planner stat4 expression covering current source next109 handles lower range predicate'] = static function (TestRunner $t) use ($plan, $and, $range, $jsonExpr, $autoloadYes): void {
    $candidate = $plan($and($range($jsonExpr, '>=', 'stable'), $autoloadYes));
    $t->same(2, $candidate['stat4MatchedSamples']);
    $t->same(['stable'], array_map(static fn (array $pair): mixed => $pair['current']['key'], $candidate['currentNextRows']));
    $t->same(1, $candidate['coveredRowCount']);
};

$tests['planner stat4 expression covering current source next109 handles upper range predicate'] = static function (TestRunner $t) use ($plan, $and, $range, $jsonExpr, $autoloadYes): void {
    $candidate = $plan($and($range($jsonExpr, '<=', 'beta'), $autoloadYes));
    $t->same(2, $candidate['coveredRowCount'] - 1);
    $t->same('alpha', $candidate['currentNextRows'][0]['current']['key']);
    $t->same('beta', $candidate['currentNextRows'][2]['current']['key']);
};

$tests['planner stat4 expression covering current source next109 handles lower expression rows'] = static function (TestRunner $t) use ($indexes, $and, $point, $lowerExpr, $autoloadYes): void {
    $candidate = SQLiteSelectExpressionIndexPlan::stat4ExpressionCoveringCurrentSourcePlan(
        [$indexes()[1]],
        $and($point($lowerExpr, 'siteurl'), $autoloadYes),
        [
            ['rowid' => 1, 'option_name' => 'Home', 'autoload' => 'yes'],
            ['rowid' => 2, 'option_name' => 'SiteURL', 'autoload' => 'yes'],
            ['rowid' => 3, 'option_name' => 'stylesheet', 'autoload' => 'yes'],
        ],
        [],
        ['option_name', 'autoload'],
        [$lowerExpr],
    );
    $t->same(1, $candidate['coveredRowCount']);
    $t->same('siteurl', $candidate['currentNextRows'][0]['current']['coveringExpressions']['lower(option_name)']);
};

$tests['planner stat4 expression covering current source next109 handles length expression rows'] = static function (TestRunner $t) use ($indexes, $and, $between, $lengthExpr, $autoloadYes): void {
    $candidate = SQLiteSelectExpressionIndexPlan::stat4ExpressionCoveringCurrentSourcePlan(
        [$indexes()[2]],
        $and($between($lengthExpr, 4, 7), $autoloadYes),
        [
            ['rowid' => 1, 'option_name' => 'home', 'autoload' => 'yes'],
            ['rowid' => 2, 'option_name' => 'siteurl', 'autoload' => 'yes'],
            ['rowid' => 3, 'option_name' => 'stylesheet', 'autoload' => 'yes'],
        ],
        [],
        ['option_name'],
        [$lengthExpr],
    );
    $t->same(2, $candidate['coveredRowCount']);
    $t->same([4, 7], array_map(static fn (array $pair): mixed => $pair['current']['key'], $candidate['currentNextRows']));
};

$tests['planner stat4 expression covering current source next109 handles integer expression rows'] = static function (TestRunner $t) use ($indexes, $and, $in, $integerExpr, $autoloadYes): void {
    $candidate = SQLiteSelectExpressionIndexPlan::stat4ExpressionCoveringCurrentSourcePlan(
        [$indexes()[3]],
        $and($in($integerExpr, [10, 30]), $autoloadYes),
        [
            ['rowid' => 1, 'option_value' => '10', 'autoload' => 'yes'],
            ['rowid' => 2, 'option_value' => '30', 'autoload' => 'yes'],
            ['rowid' => 3, 'option_value' => '44', 'autoload' => 'yes'],
        ],
        [],
        ['option_value'],
        [$integerExpr],
    );
    $t->same(2, $candidate['coveredRowCount']);
    $t->same([10, 30], array_map(static fn (array $pair): mixed => $pair['current']['key'], $candidate['currentNextRows']));
};

$tests['planner stat4 expression covering current source next109 validates row list members'] = static function (TestRunner $t) use ($plan): void {
    $t->throws(InvalidArgumentException::class, static fn () => $plan(null, null, null, [['rowid' => 1], 'bad']));
};

$tests['planner stat4 expression covering current source next109 validates missing expression column'] = static function (TestRunner $t) use ($plan): void {
    $t->throws(InvalidArgumentException::class, static fn () => $plan(null, null, null, [['rowid' => 1, 'autoload' => 'yes']]));
};

$tests['planner stat4 expression covering current source next109 validates requested covering column names'] = static function (TestRunner $t) use ($plan): void {
    $t->throws(InvalidArgumentException::class, static fn () => $plan(null, ['']));
};

return $tests;

<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteSelectExpressionIndexPlan;

$expr = static fn (string $function, string $column, string $path): array => ['function' => $function, 'column' => $column, 'path' => $path];
$column = static fn (string $name): array => ['column' => $name];
$point = static fn (array $left, mixed $value): array => ['operator' => '=', 'left' => $left, 'right' => $value];
$range = static fn (array $left, string $operator, mixed $value): array => ['operator' => $operator, 'left' => $left, 'right' => $value];
$between = static fn (array $left, mixed $lower, mixed $upper): array => ['operator' => 'BETWEEN', 'left' => $left, 'lower' => $lower, 'upper' => $upper];
$in = static fn (array $left, array $values): array => ['operator' => 'IN', 'left' => $left, 'values' => $values];
$and = static fn (array ...$terms): array => ['operator' => 'AND', 'terms' => $terms];

$samples = static fn (): array => [
    ['neq' => '4 1', 'nlt' => '0 0', 'ndlt' => '0 0', 'sample' => ['alpha', 'autoload_yes']],
    ['neq' => '7 1', 'nlt' => '4 1', 'ndlt' => '1 1', 'sample' => ['beta', 'autoload_yes']],
    ['neq' => '11 1', 'nlt' => '11 2', 'ndlt' => '2 2', 'sample' => ['delta', 'autoload_no']],
    ['neq' => '13 1', 'nlt' => '22 3', 'ndlt' => '3 3', 'sample' => ['stable', 'autoload_yes']],
    ['neq' => '17 1', 'nlt' => '35 4', 'ndlt' => '4 4', 'sample' => ['theta', 'autoload_yes']],
];

$indexes = static fn (): array => [
    [
        'name' => 'idx_jsonb_channel_stat4_cover_order',
        'rootPage' => 551,
        'estimatedRows' => 10000,
        'stat4Samples' => $samples(),
        'sql' => "CREATE INDEX idx_jsonb_channel_stat4_cover_order ON wp_options(jsonb_extract(option_value, '$.plugin.channel') COLLATE BINARY, autoload, option_id DESC, option_name) WHERE autoload = 'yes'",
    ],
    [
        'name' => 'idx_json_text_channel_stat4_cover_order',
        'rootPage' => 552,
        'estimatedRows' => 8000,
        'stat4Samples' => $samples(),
        'sql' => "CREATE INDEX idx_json_text_channel_stat4_cover_order ON wp_options((option_value ->> '$.plugin.channel') COLLATE BINARY, autoload, option_id DESC, option_name) WHERE autoload = 'yes'",
    ],
    [
        'name' => 'idx_jsonb_channel_plain_order',
        'rootPage' => 553,
        'estimatedRows' => 600,
        'sql' => "CREATE INDEX idx_jsonb_channel_plain_order ON wp_options(jsonb_extract(option_value, '$.plugin.channel') COLLATE BINARY, option_name)",
    ],
];

$jsonbChannel = $expr('jsonb_extract', 'option_value', '$.plugin.channel');
$textChannel = $expr('json_text_operator', 'option_value', '$.plugin.channel');
$needed = ['autoload', 'option_id', 'option_name'];
$order = [
    ['function' => 'jsonb_extract', 'column' => 'option_value', 'path' => '$.plugin.channel'],
    ['column' => 'autoload'],
    ['column' => 'option_id', 'direction' => 'DESC'],
];
$textOrder = [
    ['function' => 'json_text_operator', 'column' => 'option_value', 'path' => '$.plugin.channel'],
    ['column' => 'autoload'],
    ['column' => 'option_id', 'direction' => 'DESC'],
];
$plan = static fn (array $predicate = null, array $orderArg = null, array $neededArg = null, array $indexesArg = null): ?array => SQLiteSelectExpressionIndexPlan::chooseLowestCost(
    $indexesArg ?? $indexes(),
    $predicate ?? $and($point($jsonbChannel, 'stable'), $point($column('autoload'), 'yes')),
    $orderArg ?? $order,
    $neededArg ?? $needed,
    [$jsonbChannel],
);

$tests = [
    'planner stat4 json covering order current next55 chooses jsonb stat4 covering index' => static fn (TestRunner $t) => $t->same('idx_jsonb_channel_stat4_cover_order', $plan()['name']),
    'planner stat4 json covering order current next55 marks stat4 used' => static fn (TestRunner $t) => $t->same(true, $plan()['stat4Used']),
    'planner stat4 json covering order current next55 records equality estimate' => static fn (TestRunner $t) => $t->same(13, $plan()['stat4Estimate']),
    'planner stat4 json covering order current next55 applies equality estimate' => static fn (TestRunner $t) => $t->same(13, $plan()['estimatedRows']),
    'planner stat4 json covering order current next55 counts matched equality sample' => static fn (TestRunner $t) => $t->same(1, $plan()['stat4MatchedSamples']),
    'planner stat4 json covering order current next55 exposes matched current key' => static fn (TestRunner $t) => $t->same('stable', $plan()['stat4MatchedCurrentNext'][0]['current']['key']),
    'planner stat4 json covering order current next55 matched equality next is null' => static fn (TestRunner $t) => $t->same(null, $plan()['stat4MatchedCurrentNext'][0]['next']),
    'planner stat4 json covering order current next55 exposes full current next cursor' => static fn (TestRunner $t) => $t->same('beta', $plan()['stat4CurrentNext'][1]['current']['key']),
    'planner stat4 json covering order current next55 full cursor next key' => static fn (TestRunner $t) => $t->same('delta', $plan()['stat4CurrentNext'][1]['next']['key']),
    'planner stat4 json covering order current next55 satisfies expression order' => static fn (TestRunner $t) => $t->same(true, $plan()['orderBySatisfied']),
    'planner stat4 json covering order current next55 stays covering' => static fn (TestRunner $t) => $t->same(true, $plan()['covering']),
    'planner stat4 json covering order current next55 covers json expression payload' => static fn (TestRunner $t) => $t->same(['jsonb_extract(option_value)'], $plan()['coveringExpressions']),
    'planner stat4 json covering order current next55 preserves trailing columns' => static fn (TestRunner $t) => $t->same(['autoload', 'option_id', 'option_name'], $plan()['trailingColumns']),
    'planner stat4 json covering order current next55 records partial predicate' => static fn (TestRunner $t) => $t->same(true, $plan()['partial']),
    'planner stat4 json covering order current next55 rejects missing partial proof' => static fn (TestRunner $t) => $t->same(null, $plan($point($jsonbChannel, 'stable'), $order, $needed, [$indexes()[0]])),
    'planner stat4 json covering order current next55 rejects wrong json function family' => static fn (TestRunner $t) => $t->same(null, SQLiteSelectExpressionIndexPlan::chooseLowestCost([$indexes()[0]], $point($textChannel, 'stable'), $textOrder, $needed, [$textChannel])),
    'planner stat4 json covering order current next55 text operator stat4 chooses text index' => static fn (TestRunner $t) => $t->same('idx_json_text_channel_stat4_cover_order', SQLiteSelectExpressionIndexPlan::chooseLowestCost([$indexes()[1]], $and($point($textChannel, 'stable'), $point($column('autoload'), 'yes')), $textOrder, $needed, [$textChannel])['name']),
    'planner stat4 json covering order current next55 text operator matched sample' => static fn (TestRunner $t) => $t->same(1, SQLiteSelectExpressionIndexPlan::chooseLowestCost([$indexes()[1]], $and($point($textChannel, 'stable'), $point($column('autoload'), 'yes')), $textOrder, $needed, [$textChannel])['stat4MatchedSamples']),
    'planner stat4 json covering order current next55 plain fallback has no stat4 matches' => static fn (TestRunner $t) => $t->same(0, $plan(null, [], ['option_name'], [$indexes()[2]])['stat4MatchedSamples']),
    'planner stat4 json covering order current next55 plain fallback marks stat4 unused' => static fn (TestRunner $t) => $t->same(false, $plan(null, [], ['option_name'], [$indexes()[2]])['stat4Used']),
    'planner stat4 json covering order current next55 skipped trailing order rejected' => static fn (TestRunner $t) => $t->same(false, $plan(null, [['column' => 'option_id', 'direction' => 'DESC']])['orderBySatisfied']),
    'planner stat4 json covering order current next55 descending mismatch rejected' => static fn (TestRunner $t) => $t->same(false, $plan(null, [['function' => 'jsonb_extract', 'column' => 'option_value', 'path' => '$.plugin.channel', 'direction' => 'DESC']])['orderBySatisfied']),
    'planner stat4 json covering order current next55 missing covering column defers coverage' => static fn (TestRunner $t) => $t->same(false, $plan(null, $order, ['autoload', 'option_value'])['covering']),
    'planner stat4 json covering order current next55 rejects bad covering column list' => static function (TestRunner $t) use ($indexes, $and, $point, $column, $jsonbChannel, $order): void {
        $t->throws(InvalidArgumentException::class, static fn () => SQLiteSelectExpressionIndexPlan::chooseLowestCost([array_merge($indexes()[0], ['coveringColumns' => ['autoload', 9]])], $and($point($jsonbChannel, 'stable'), $point($column('autoload'), 'yes')), $order, ['autoload']));
    },
];

foreach ([
    ['range->=', 'beta', $range($jsonbChannel, '>=', 'beta'), 9996, 4, ['beta', 'delta', 'stable', 'theta']],
    ['range->', 'beta', $range($jsonbChannel, '>', 'beta'), 9989, 3, ['delta', 'stable', 'theta']],
    ['range-<=', 'delta', $range($jsonbChannel, '<=', 'delta'), 22, 3, ['alpha', 'beta', 'delta']],
    ['range-<', 'delta', $range($jsonbChannel, '<', 'delta'), 11, 2, ['alpha', 'beta']],
    ['between', 'beta-theta', $between($jsonbChannel, 'beta', 'theta'), 48, 4, ['beta', 'delta', 'stable', 'theta']],
    ['in', 'alpha-stable-null', $in($jsonbChannel, ['stable', 'alpha', null, 'stable']), 17, 2, ['alpha', 'stable']],
] as [$name, $label, $predicate, $rows, $matches, $keys]) {
    $tests["planner stat4 json covering order current next55 {$name} estimates {$label}"] = static function (TestRunner $t) use ($indexes, $plan, $and, $point, $column, $predicate, $rows, $matches, $keys): void {
        $candidate = $plan($and($predicate, $point($column('autoload'), 'yes')), null, null, [$indexes()[0]]);
        $t->same($rows, $candidate['estimatedRows']);
        $t->same($matches, $candidate['stat4MatchedSamples']);
        $t->same($keys, array_map(static fn (array $pair): mixed => $pair['current']['key'], $candidate['stat4MatchedCurrentNext']));
        $t->same($matches === 0 ? null : ($keys[1] ?? null), $candidate['stat4MatchedCurrentNext'][0]['next']['key'] ?? null);
    };
}

foreach ([
    ['bad samples not list', ['stat4Samples' => ['bad' => []]]],
    ['bad sample row', ['stat4Samples' => ['bad']]],
    ['bad sample values', ['stat4Samples' => [['neq' => 1, 'nlt' => 0, 'sample' => []]]]],
    ['bad neq', ['stat4Samples' => [['neq' => 0, 'nlt' => 0, 'sample' => ['stable']]]]],
    ['bad nlt', ['stat4Samples' => [['neq' => 1, 'nlt' => -1, 'sample' => ['stable']]]]],
] as [$label, $override]) {
    $tests["planner stat4 json covering order current next55 validates {$label}"] = static function (TestRunner $t) use ($indexes, $and, $point, $column, $jsonbChannel, $order, $needed, $override): void {
        $t->throws(InvalidArgumentException::class, static fn () => SQLiteSelectExpressionIndexPlan::chooseLowestCost(
            [array_merge($indexes()[0], $override)],
            $and($point($jsonbChannel, 'stable'), $point($column('autoload'), 'yes')),
            $order,
            $needed,
            [$jsonbChannel],
        ));
    };
}

return $tests;

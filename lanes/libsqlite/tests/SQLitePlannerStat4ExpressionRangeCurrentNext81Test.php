<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteSelectExpressionIndexPlan;

$expr = static fn (string $function, string $column): array => ['function' => $function, 'column' => $column];
$range = static fn (array $left, string $operator, mixed $value): array => ['operator' => $operator, 'left' => $left, 'right' => $value];
$between = static fn (array $left, mixed $lower, mixed $upper): array => ['operator' => 'BETWEEN', 'left' => $left, 'lower' => $lower, 'upper' => $upper];
$and = static fn (array ...$terms): array => ['operator' => 'AND', 'terms' => $terms];

$stat4NameSamples = static fn (): array => [
    ['neq' => '1 1', 'nlt' => '0 0', 'ndlt' => '0 0', 'sample' => ['active_plugins', 'yes']],
    ['neq' => '8 8', 'nlt' => '1 1', 'ndlt' => '1 1', 'sample' => ['autoloaded_widget', 'yes']],
    ['neq' => '2 2', 'nlt' => '9 9', 'ndlt' => '2 2', 'sample' => ['home', 'yes']],
    ['neq' => '1 1', 'nlt' => '11 11', 'ndlt' => '3 3', 'sample' => ['siteurl', 'yes']],
    ['neq' => '24 24', 'nlt' => '12 12', 'ndlt' => '4 4', 'sample' => ['transient_feed', 'no']],
    ['neq' => '4 4', 'nlt' => '36 36', 'ndlt' => '5 5', 'sample' => ['widget_recent', 'yes']],
];

$stat4LengthSamples = static fn (): array => [
    ['neq' => [3, 1], 'nlt' => [0, 0], 'ndlt' => [0, 0], 'sample' => [4, 'home']],
    ['neq' => [7, 2], 'nlt' => [3, 1], 'ndlt' => [1, 1], 'sample' => [7, 'siteurl']],
    ['neq' => [12, 3], 'nlt' => [10, 2], 'ndlt' => [2, 2], 'sample' => [12, 'active_plugins']],
    ['neq' => [20, 4], 'nlt' => [22, 5], 'ndlt' => [3, 3], 'sample' => [18, 'transient_feed']],
    ['neq' => [5, 1], 'nlt' => [42, 9], 'ndlt' => [4, 4], 'sample' => [24, 'woocommerce_queue']],
];

$indexes = static fn (): array => [
    [
        'name' => 'idx_lower_name_stat4_range',
        'rootPage' => 181,
        'estimatedRows' => 60,
        'stat4Samples' => $stat4NameSamples(),
        'sql' => 'CREATE INDEX idx_lower_name_stat4_range ON wp_options(lower(option_name), autoload, option_value)',
    ],
    [
        'name' => 'idx_lower_name_plain_range',
        'rootPage' => 182,
        'estimatedRows' => 60,
        'sql' => 'CREATE INDEX idx_lower_name_plain_range ON wp_options(lower(option_name), autoload)',
    ],
    [
        'name' => 'idx_length_name_stat4_range',
        'rootPage' => 183,
        'estimatedRows' => 47,
        'stat4Samples' => $stat4LengthSamples(),
        'sql' => 'CREATE INDEX idx_length_name_stat4_range ON wp_options(length(option_name), autoload, option_value)',
    ],
];

$tests = [];

$tests['planner stat4 expression range current next81 exposes bounded lower exact current next'] = static function (TestRunner $t) use ($indexes, $expr, $range, $and): void {
    $plan = SQLiteSelectExpressionIndexPlan::chooseBoundedRangeCost([$indexes()[0]], $and(
        $range($expr('lower', 'option_name'), '>=', 'home'),
        $range($expr('lower', 'option_name'), '<', 'transient_timeout')
    ), [], ['autoload']);
    $boundary = $plan['stat4RangeCurrentNext'];

    $t->same('home', $boundary['lower']['current']['key']);
    $t->same('siteurl', $boundary['lower']['next']['key']);
    $t->same('lower', $boundary['lower']['side']);
    $t->same('home', $boundary['lower']['value']);
    $t->same(true, $boundary['lower']['exact']);
};

$tests['planner stat4 expression range current next81 exposes bounded upper gap current next'] = static function (TestRunner $t) use ($indexes, $expr, $range, $and): void {
    $plan = SQLiteSelectExpressionIndexPlan::chooseBoundedRangeCost([$indexes()[0]], $and(
        $range($expr('lower', 'option_name'), '>=', 'home'),
        $range($expr('lower', 'option_name'), '<', 'transient_timeout')
    ), [], ['autoload']);
    $boundary = $plan['stat4RangeCurrentNext'];

    $t->same('transient_feed', $boundary['upper']['current']['key']);
    $t->same('widget_recent', $boundary['upper']['next']['key']);
    $t->same('upper', $boundary['upper']['side']);
    $t->same('transient_timeout', $boundary['upper']['value']);
    $t->same(false, $boundary['upper']['exact']);
};

$tests['planner stat4 expression range current next81 preserves inclusivity for bounded range'] = static function (TestRunner $t) use ($indexes, $expr, $range, $and): void {
    $plan = SQLiteSelectExpressionIndexPlan::chooseBoundedRangeCost([$indexes()[0]], $and(
        $range($expr('lower', 'option_name'), '>', 'home'),
        $range($expr('lower', 'option_name'), '<=', 'transient_feed')
    ), [], ['autoload']);
    $boundary = $plan['stat4RangeCurrentNext'];

    $t->same(false, $boundary['lowerInclusive']);
    $t->same(true, $boundary['upperInclusive']);
    $t->same('home', $boundary['lower']['current']['key']);
    $t->same('transient_feed', $boundary['upper']['current']['key']);
    $t->same('widget_recent', $boundary['upper']['next']['key']);
};

$tests['planner stat4 expression range current next81 reports empty sample gap'] = static function (TestRunner $t) use ($indexes, $expr, $range, $and): void {
    $plan = SQLiteSelectExpressionIndexPlan::chooseBoundedRangeCost([$indexes()[0]], $and(
        $range($expr('lower', 'option_name'), '>=', 'cron_a'),
        $range($expr('lower', 'option_name'), '<', 'cron_z')
    ), [], ['autoload']);
    $boundary = $plan['stat4RangeCurrentNext'];

    $t->same(true, $boundary['emptyGap']);
    $t->same('autoloaded_widget', $boundary['lower']['current']['key']);
    $t->same('home', $boundary['lower']['next']['key']);
    $t->same('autoloaded_widget', $boundary['upper']['current']['key']);
    $t->same('home', $boundary['upper']['next']['key']);
};

$tests['planner stat4 expression range current next81 handles before-first lower boundary'] = static function (TestRunner $t) use ($indexes, $expr, $range, $and): void {
    $plan = SQLiteSelectExpressionIndexPlan::chooseBoundedRangeCost([$indexes()[0]], $and(
        $range($expr('lower', 'option_name'), '>=', 'aaa'),
        $range($expr('lower', 'option_name'), '<', 'active_q')
    ), [], ['autoload']);
    $boundary = $plan['stat4RangeCurrentNext'];

    $t->same(null, $boundary['lower']['current']);
    $t->same('active_plugins', $boundary['lower']['next']['key']);
    $t->same('active_plugins', $boundary['upper']['current']['key']);
    $t->same('autoloaded_widget', $boundary['upper']['next']['key']);
    $t->same(false, $boundary['lower']['exact']);
};

$tests['planner stat4 expression range current next81 handles after-last upper boundary'] = static function (TestRunner $t) use ($indexes, $expr, $range, $and): void {
    $plan = SQLiteSelectExpressionIndexPlan::chooseBoundedRangeCost([$indexes()[0]], $and(
        $range($expr('lower', 'option_name'), '>=', 'widget_recent'),
        $range($expr('lower', 'option_name'), '<=', 'zzzz')
    ), [], ['autoload']);
    $boundary = $plan['stat4RangeCurrentNext'];

    $t->same('widget_recent', $boundary['lower']['current']['key']);
    $t->same(null, $boundary['lower']['next']);
    $t->same('widget_recent', $boundary['upper']['current']['key']);
    $t->same(null, $boundary['upper']['next']);
    $t->same(false, $boundary['upper']['exact']);
};

$tests['planner stat4 expression range current next81 reports between boundary evidence'] = static function (TestRunner $t) use ($indexes, $expr, $between): void {
    $plan = SQLiteSelectExpressionIndexPlan::chooseLowestCost([$indexes()[0]], $between($expr('lower', 'option_name'), 'home', 'transient_feed'), [], ['autoload']);
    $boundary = $plan['stat4RangeCurrentNext'];

    $t->same('home', $boundary['lower']['current']['key']);
    $t->same('siteurl', $boundary['lower']['next']['key']);
    $t->same('transient_feed', $boundary['upper']['current']['key']);
    $t->same('widget_recent', $boundary['upper']['next']['key']);
    $t->same([true, true], [$boundary['lowerInclusive'], $boundary['upperInclusive']]);
};

$tests['planner stat4 expression range current next81 reports single lower range boundary'] = static function (TestRunner $t) use ($indexes, $expr, $range): void {
    $plan = SQLiteSelectExpressionIndexPlan::chooseLowestCost([$indexes()[0]], $range($expr('lower', 'option_name'), '>', 'siteurl'), [], ['autoload']);
    $boundary = $plan['stat4RangeCurrentNext'];

    $t->same('siteurl', $boundary['lower']['current']['key']);
    $t->same('transient_feed', $boundary['lower']['next']['key']);
    $t->same(null, $boundary['upper']);
    $t->same(false, $boundary['lowerInclusive']);
    $t->same(false, $boundary['emptyGap']);
};

$tests['planner stat4 expression range current next81 reports single upper range boundary'] = static function (TestRunner $t) use ($indexes, $expr, $range): void {
    $plan = SQLiteSelectExpressionIndexPlan::chooseLowestCost([$indexes()[0]], $range($expr('lower', 'option_name'), '<=', 'siteurl'), [], ['autoload']);
    $boundary = $plan['stat4RangeCurrentNext'];

    $t->same(null, $boundary['lower']);
    $t->same('siteurl', $boundary['upper']['current']['key']);
    $t->same('transient_feed', $boundary['upper']['next']['key']);
    $t->same(true, $boundary['upperInclusive']);
    $t->same(true, $boundary['upper']['exact']);
};

$tests['planner stat4 expression range current next81 supports numeric expression range boundaries'] = static function (TestRunner $t) use ($indexes, $expr, $range, $and): void {
    $plan = SQLiteSelectExpressionIndexPlan::chooseBoundedRangeCost([$indexes()[2]], $and(
        $range($expr('length', 'option_name'), '>=', 8),
        $range($expr('length', 'option_name'), '<', 20)
    ), [], ['autoload']);
    $boundary = $plan['stat4RangeCurrentNext'];

    $t->same(7, $boundary['lower']['current']['key']);
    $t->same(12, $boundary['lower']['next']['key']);
    $t->same(18, $boundary['upper']['current']['key']);
    $t->same(24, $boundary['upper']['next']['key']);
    $t->same(true, $plan['stat4Used']);
};

$tests['planner stat4 expression range current next81 keeps fallback range without stat4 boundary'] = static function (TestRunner $t) use ($indexes, $expr, $range, $and): void {
    $plan = SQLiteSelectExpressionIndexPlan::chooseBoundedRangeCost([$indexes()[1]], $and(
        $range($expr('lower', 'option_name'), '>=', 'home'),
        $range($expr('lower', 'option_name'), '<', 'transient_timeout')
    ), [], ['autoload']);

    $t->same(false, $plan['stat4Used']);
    $t->same(null, $plan['stat4RangeCurrentNext']);
    $t->same('idx_lower_name_plain_range', $plan['name']);
    $t->same('range-bounded', $plan['operator']);
    $t->same(true, $plan['legacyPlansUnaffected']);
};

return $tests;

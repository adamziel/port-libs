<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteSelectExpressionIndexPlan;

$expr = static fn (string $function, string $column): array => ['function' => $function, 'column' => $column];
$range = static fn (array $left, string $operator, mixed $value): array => ['operator' => $operator, 'left' => $left, 'right' => $value];
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
        'name' => 'idx_lower_name_stat4_current_source',
        'rootPage' => 286,
        'estimatedRows' => 60,
        'stat4Samples' => $stat4NameSamples(),
        'sql' => 'CREATE INDEX idx_lower_name_stat4_current_source ON wp_options(lower(option_name), autoload, option_value)',
    ],
    [
        'name' => 'idx_length_name_stat4_current_source',
        'rootPage' => 287,
        'estimatedRows' => 47,
        'stat4Samples' => $stat4LengthSamples(),
        'sql' => 'CREATE INDEX idx_length_name_stat4_current_source ON wp_options(length(option_name), autoload, option_value)',
    ],
    [
        'name' => 'idx_upper_name_stat4_current_source',
        'rootPage' => 288,
        'estimatedRows' => 60,
        'stat4Samples' => $stat4NameSamples(),
        'sql' => 'CREATE INDEX idx_upper_name_stat4_current_source ON wp_options(upper(option_name), autoload, option_value)',
    ],
];

$tests = [];

$tests['planner stat4 expression range current source next86 collapses redundant lower bounds'] = static function (TestRunner $t) use ($indexes, $expr, $range, $and): void {
    $plans = SQLiteSelectExpressionIndexPlan::boundedRangePlans([$indexes()[0]], $and(
        $range($expr('lower', 'option_name'), '>=', 'active_plugins'),
        $range($expr('lower', 'option_name'), '>=', 'home'),
        $range($expr('lower', 'option_name'), '<', 'widget_recent')
    ), [], ['autoload']);
    $plan = $plans[0];

    $t->same(1, count($plans));
    $t->same('home', $plan['values']['lower']);
    $t->same('widget_recent', $plan['values']['upper']);
    $t->same('home', $plan['stat4RangeCurrentNext']['lower']['current']['key']);
    $t->same('siteurl', $plan['stat4RangeCurrentNext']['lower']['next']['key']);
};

$tests['planner stat4 expression range current source next86 collapses redundant upper bounds'] = static function (TestRunner $t) use ($indexes, $expr, $range, $and): void {
    $plans = SQLiteSelectExpressionIndexPlan::boundedRangePlans([$indexes()[0]], $and(
        $range($expr('lower', 'option_name'), '>=', 'active_plugins'),
        $range($expr('lower', 'option_name'), '<', 'widget_recent'),
        $range($expr('lower', 'option_name'), '<', 'transient_timeout')
    ), [], ['autoload']);
    $plan = $plans[0];

    $t->same(1, count($plans));
    $t->same('active_plugins', $plan['values']['lower']);
    $t->same('transient_timeout', $plan['values']['upper']);
    $t->same('transient_feed', $plan['stat4RangeCurrentNext']['upper']['current']['key']);
    $t->same('widget_recent', $plan['stat4RangeCurrentNext']['upper']['next']['key']);
};

$tests['planner stat4 expression range current source next86 uses tight current source interval'] = static function (TestRunner $t) use ($indexes, $expr, $range, $and): void {
    $plans = SQLiteSelectExpressionIndexPlan::boundedRangePlans([$indexes()[0]], $and(
        $range($expr('lower', 'option_name'), '>=', 'active_plugins'),
        $range($expr('lower', 'option_name'), '>=', 'home'),
        $range($expr('lower', 'option_name'), '<', 'widget_recent'),
        $range($expr('lower', 'option_name'), '<', 'transient_timeout')
    ), [], ['autoload']);
    $plan = $plans[0];

    $t->same(1, count($plans));
    $t->same(['lower' => 'home', 'upper' => 'transient_timeout', 'lowerInclusive' => true, 'upperInclusive' => false], $plan['values']);
    $t->same('home', $plan['stat4RangeCurrentNext']['lower']['value']);
    $t->same('transient_timeout', $plan['stat4RangeCurrentNext']['upper']['value']);
    $t->same(3, $plan['stat4MatchedSamples']);
};

$tests['planner stat4 expression range current source next86 prefers exclusive lower tie'] = static function (TestRunner $t) use ($indexes, $expr, $range, $and): void {
    $plan = SQLiteSelectExpressionIndexPlan::chooseBoundedRangeCost([$indexes()[0]], $and(
        $range($expr('lower', 'option_name'), '>=', 'home'),
        $range($expr('lower', 'option_name'), '>', 'home'),
        $range($expr('lower', 'option_name'), '<=', 'transient_feed')
    ), [], ['autoload']);

    $t->same('home', $plan['values']['lower']);
    $t->same(false, $plan['values']['lowerInclusive']);
    $t->same('home', $plan['stat4RangeCurrentNext']['lower']['current']['key']);
    $t->same('siteurl', $plan['stat4RangeCurrentNext']['lower']['next']['key']);
    $t->same(false, $plan['stat4RangeCurrentNext']['lowerInclusive']);
};

$tests['planner stat4 expression range current source next86 prefers exclusive upper tie'] = static function (TestRunner $t) use ($indexes, $expr, $range, $and): void {
    $plan = SQLiteSelectExpressionIndexPlan::chooseBoundedRangeCost([$indexes()[0]], $and(
        $range($expr('lower', 'option_name'), '>=', 'home'),
        $range($expr('lower', 'option_name'), '<=', 'transient_feed'),
        $range($expr('lower', 'option_name'), '<', 'transient_feed')
    ), [], ['autoload']);

    $t->same('transient_feed', $plan['values']['upper']);
    $t->same(false, $plan['values']['upperInclusive']);
    $t->same('transient_feed', $plan['stat4RangeCurrentNext']['upper']['current']['key']);
    $t->same('widget_recent', $plan['stat4RangeCurrentNext']['upper']['next']['key']);
    $t->same(false, $plan['stat4RangeCurrentNext']['upperInclusive']);
};

$tests['planner stat4 expression range current source next86 admits equal inclusive point interval'] = static function (TestRunner $t) use ($indexes, $expr, $range, $and): void {
    $plan = SQLiteSelectExpressionIndexPlan::chooseBoundedRangeCost([$indexes()[0]], $and(
        $range($expr('lower', 'option_name'), '>=', 'home'),
        $range($expr('lower', 'option_name'), '<=', 'home')
    ), [], ['autoload']);

    $t->same('home', $plan['values']['lower']);
    $t->same('home', $plan['values']['upper']);
    $t->same(true, $plan['values']['lowerInclusive']);
    $t->same(true, $plan['values']['upperInclusive']);
    $t->same(2, $plan['estimatedRows']);
};

$tests['planner stat4 expression range current source next86 rejects empty equal exclusive interval'] = static function (TestRunner $t) use ($indexes, $expr, $range, $and): void {
    $plan = SQLiteSelectExpressionIndexPlan::chooseBoundedRangeCost([$indexes()[0]], $and(
        $range($expr('lower', 'option_name'), '>=', 'home'),
        $range($expr('lower', 'option_name'), '<', 'home')
    ), [], ['autoload']);

    $t->same(null, $plan);
    $t->same([], SQLiteSelectExpressionIndexPlan::boundedRangePlans([$indexes()[0]], $and(
        $range($expr('lower', 'option_name'), '>', 'siteurl'),
        $range($expr('lower', 'option_name'), '<=', 'siteurl')
    ), [], ['autoload']));
    $t->same(null, SQLiteSelectExpressionIndexPlan::chooseBoundedRangeCost([$indexes()[0]], $and(
        $range($expr('lower', 'option_name'), '>', 'zzzz'),
        $range($expr('lower', 'option_name'), '<', 'active_plugins')
    ), [], ['autoload']));
};

$tests['planner stat4 expression range current source next86 handles numeric tighter lower'] = static function (TestRunner $t) use ($indexes, $expr, $range, $and): void {
    $plan = SQLiteSelectExpressionIndexPlan::chooseBoundedRangeCost([$indexes()[1]], $and(
        $range($expr('length', 'option_name'), '>=', 4),
        $range($expr('length', 'option_name'), '>=', 8),
        $range($expr('length', 'option_name'), '<', 24)
    ), [], ['autoload']);

    $t->same(8, $plan['values']['lower']);
    $t->same(24, $plan['values']['upper']);
    $t->same(7, $plan['stat4RangeCurrentNext']['lower']['current']['key']);
    $t->same(12, $plan['stat4RangeCurrentNext']['lower']['next']['key']);
    $t->same(32, $plan['estimatedRows']);
};

$tests['planner stat4 expression range current source next86 handles numeric tighter upper'] = static function (TestRunner $t) use ($indexes, $expr, $range, $and): void {
    $plan = SQLiteSelectExpressionIndexPlan::chooseBoundedRangeCost([$indexes()[1]], $and(
        $range($expr('length', 'option_name'), '>=', 7),
        $range($expr('length', 'option_name'), '<', 24),
        $range($expr('length', 'option_name'), '<', 20)
    ), [], ['autoload']);

    $t->same(7, $plan['values']['lower']);
    $t->same(20, $plan['values']['upper']);
    $t->same(18, $plan['stat4RangeCurrentNext']['upper']['current']['key']);
    $t->same(24, $plan['stat4RangeCurrentNext']['upper']['next']['key']);
    $t->same(39, $plan['estimatedRows']);
};

$tests['planner stat4 expression range current source next86 groups independent expression intervals'] = static function (TestRunner $t) use ($indexes, $expr, $range, $and): void {
    $plans = SQLiteSelectExpressionIndexPlan::boundedRangePlans($indexes(), $and(
        $range($expr('lower', 'option_name'), '>=', 'home'),
        $range($expr('lower', 'option_name'), '<', 'transient_timeout'),
        $range($expr('length', 'option_name'), '>=', 8),
        $range($expr('length', 'option_name'), '<', 20)
    ), [], ['autoload']);

    $t->same(2, count($plans));
    $t->same(['idx_lower_name_stat4_current_source', 'idx_length_name_stat4_current_source'], array_column($plans, 'name'));
    $t->same(27, $plans[0]['estimatedRows']);
    $t->same(32, $plans[1]['estimatedRows']);
    $t->same(true, $plans[0]['stat4Used'] && $plans[1]['stat4Used']);
};

$tests['planner stat4 expression range current source next86 keeps function-specific groups separate'] = static function (TestRunner $t) use ($indexes, $expr, $range, $and): void {
    $plans = SQLiteSelectExpressionIndexPlan::boundedRangePlans([$indexes()[0], $indexes()[2]], $and(
        $range($expr('lower', 'option_name'), '>=', 'home'),
        $range($expr('lower', 'option_name'), '<', 'transient_timeout'),
        $range($expr('upper', 'option_name'), '>=', 'home'),
        $range($expr('upper', 'option_name'), '<', 'transient_timeout')
    ), [], ['autoload']);

    $t->same(2, count($plans));
    $t->same(['idx_lower_name_stat4_current_source', 'idx_upper_name_stat4_current_source'], array_column($plans, 'name'));
    $t->same('lower', $plans[0]['type']);
    $t->same('upper', $plans[1]['type']);
    $t->same(['home', 'home'], array_column(array_column($plans, 'values'), 'lower'));
};

$tests['planner stat4 expression range current source next86 leaves single-ended range on legacy path'] = static function (TestRunner $t) use ($indexes, $expr, $range): void {
    $bounded = SQLiteSelectExpressionIndexPlan::chooseBoundedRangeCost([$indexes()[0]], $range($expr('lower', 'option_name'), '>=', 'home'), [], ['autoload']);
    $legacy = SQLiteSelectExpressionIndexPlan::chooseLowestCost([$indexes()[0]], $range($expr('lower', 'option_name'), '>=', 'home'), [], ['autoload']);

    $t->same(null, $bounded);
    $t->same('range->=', $legacy['operator']);
    $t->same('home', $legacy['values']);
    $t->same('home', $legacy['stat4RangeCurrentNext']['lower']['value']);
    $t->same(null, $legacy['stat4RangeCurrentNext']['upper']);
};

return $tests;

<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteSelectExpressionIndexPlan;

$expr = static fn (string $function, string $column): array => ['function' => $function, 'column' => $column];
$point = static fn (array $left, mixed $value): array => ['operator' => '=', 'left' => $left, 'right' => $value];
$range = static fn (array $left, string $operator, mixed $value): array => ['operator' => $operator, 'left' => $left, 'right' => $value];
$between = static fn (array $left, mixed $lower, mixed $upper): array => ['operator' => 'BETWEEN', 'left' => $left, 'lower' => $lower, 'upper' => $upper];
$inList = static fn (array $left, array $values): array => ['operator' => 'IN', 'left' => $left, 'values' => $values];
$and = static fn (array ...$terms): array => ['operator' => 'AND', 'terms' => $terms];

$stat4NameSamples = static fn (): array => [
    ['neq' => '1 1', 'nlt' => '0 0', 'ndlt' => '0 0', 'sample' => ['active_plugins', 'yes']],
    ['neq' => '8 8', 'nlt' => '1 1', 'ndlt' => '1 1', 'sample' => ['autoloaded_widget', 'yes']],
    ['neq' => '2 2', 'nlt' => '9 9', 'ndlt' => '2 2', 'sample' => ['home', 'yes']],
    ['neq' => '1 1', 'nlt' => '11 11', 'ndlt' => '3 3', 'sample' => ['siteurl', 'yes']],
    ['neq' => '24 24', 'nlt' => '12 12', 'ndlt' => '4 4', 'sample' => ['transient_feed', 'no']],
    ['neq' => '4 4', 'nlt' => '36 36', 'ndlt' => '5 5', 'sample' => ['widget_recent', 'yes']],
];

$stat4NumericSamples = static fn (): array => [
    ['neq' => [3, 1], 'nlt' => [0, 0], 'ndlt' => [0, 0], 'sample' => [0, 'no']],
    ['neq' => [5, 1], 'nlt' => [3, 1], 'ndlt' => [1, 1], 'sample' => [1, 'yes']],
    ['neq' => [12, 2], 'nlt' => [8, 2], 'ndlt' => [2, 2], 'sample' => [10, 'yes']],
    ['neq' => [30, 4], 'nlt' => [20, 4], 'ndlt' => [3, 3], 'sample' => [100, 'yes']],
    ['neq' => [2, 1], 'nlt' => [50, 8], 'ndlt' => [4, 4], 'sample' => [1000, 'no']],
];

$indexes = static fn (): array => [
    [
        'name' => 'idx_lower_name_stat4_cover',
        'rootPage' => 141,
        'estimatedRows' => 60,
        'stat4Samples' => $stat4NameSamples(),
        'sql' => 'CREATE INDEX idx_lower_name_stat4_cover ON wp_options(lower(option_name), autoload, option_value)',
    ],
    [
        'name' => 'idx_lower_name_fallback',
        'rootPage' => 142,
        'estimatedRows' => 60,
        'sql' => 'CREATE INDEX idx_lower_name_fallback ON wp_options(lower(option_name), autoload, option_value)',
    ],
    [
        'name' => 'idx_upper_name_stat4_desc',
        'rootPage' => 143,
        'estimatedRows' => 60,
        'stat4Samples' => $stat4NameSamples(),
        'sql' => 'CREATE INDEX idx_upper_name_stat4_desc ON wp_options(upper(option_name) DESC, autoload)',
    ],
    [
        'name' => 'idx_value_int_stat4',
        'rootPage' => 144,
        'estimatedRows' => 60,
        'stat4Samples' => $stat4NumericSamples(),
        'sql' => 'CREATE INDEX idx_value_int_stat4 ON wp_options(CAST(option_value AS INTEGER), autoload)',
    ],
];

$tests = [
    'planner expression covering stat4 current next37 exact sample equality uses neq' => static function (TestRunner $t) use ($indexes, $expr, $point): void {
        $plan = SQLiteSelectExpressionIndexPlan::chooseLowestCost([$indexes()[0]], $point($expr('lower', 'option_name'), 'siteurl'), [], ['autoload']);
        $t->same(1, $plan['estimatedRows']);
        $t->same(true, $plan['stat4Used']);
    },
    'planner expression covering stat4 current next37 duplicate sample equality keeps larger neq' => static function (TestRunner $t) use ($indexes, $expr, $point): void {
        $plan = SQLiteSelectExpressionIndexPlan::chooseLowestCost([$indexes()[0]], $point($expr('lower', 'option_name'), 'transient_feed'), [], ['autoload']);
        $t->same(24, $plan['estimatedRows']);
    },
    'planner expression covering stat4 current next37 fallback index keeps legacy estimate' => static function (TestRunner $t) use ($indexes, $expr, $point): void {
        $plan = SQLiteSelectExpressionIndexPlan::chooseLowestCost([$indexes()[1]], $point($expr('lower', 'option_name'), 'siteurl'), [], ['autoload']);
        $t->same(1, $plan['estimatedRows']);
        $t->same(false, $plan['stat4Used']);
    },
    'planner expression covering stat4 current next37 unknown equality uses distinct average' => static function (TestRunner $t) use ($indexes, $expr, $point): void {
        $plan = SQLiteSelectExpressionIndexPlan::chooseLowestCost([$indexes()[0]], $point($expr('lower', 'option_name'), 'cron'), [], ['autoload']);
        $t->same(10, $plan['estimatedRows']);
        $t->same(10, $plan['stat4Estimate']);
    },
    'planner expression covering stat4 current next37 ranks stat4 selective index before fallback' => static function (TestRunner $t) use ($indexes, $expr, $point): void {
        $fallback = $indexes()[1];
        $fallback['sql'] = 'CREATE INDEX idx_lower_name_fallback ON wp_options(lower(option_name))';
        $plans = SQLiteSelectExpressionIndexPlan::rankedPlans([$fallback, $indexes()[0]], $point($expr('lower', 'option_name'), 'siteurl'), [], ['autoload']);
        $t->same('idx_lower_name_stat4_cover', $plans[0]['name']);
        $t->same(1, $plans[0]['estimatedRows']);
    },
    'planner expression covering stat4 current next37 ranks fallback before wide stat4 duplicate' => static function (TestRunner $t) use ($indexes, $expr, $point): void {
        $plans = SQLiteSelectExpressionIndexPlan::rankedPlans([$indexes()[0], $indexes()[1]], $point($expr('lower', 'option_name'), 'transient_feed'), [], ['autoload']);
        $t->same('idx_lower_name_fallback', $plans[0]['name']);
        $t->same(1, $plans[0]['estimatedRows']);
    },
    'planner expression covering stat4 current next37 reports first current next pair' => static function (TestRunner $t) use ($indexes, $expr, $point): void {
        $plan = SQLiteSelectExpressionIndexPlan::chooseLowestCost([$indexes()[0]], $point($expr('lower', 'option_name'), 'home'), [], ['autoload']);
        $t->same('active_plugins', $plan['stat4CurrentNext'][0]['current']['key']);
        $t->same('autoloaded_widget', $plan['stat4CurrentNext'][0]['next']['key']);
    },
    'planner expression covering stat4 current next37 reports terminal next null' => static function (TestRunner $t) use ($indexes, $expr, $point): void {
        $plan = SQLiteSelectExpressionIndexPlan::chooseLowestCost([$indexes()[0]], $point($expr('lower', 'option_name'), 'home'), [], ['autoload']);
        $t->same(null, $plan['stat4CurrentNext'][5]['next']);
    },
    'planner expression covering stat4 current next37 sorts unsorted sample input' => static function (TestRunner $t) use ($expr, $point, $stat4NameSamples): void {
        $samples = array_reverse($stat4NameSamples());
        $plan = SQLiteSelectExpressionIndexPlan::chooseLowestCost([[
            'name' => 'idx_unsorted',
            'estimatedRows' => 60,
            'stat4Samples' => $samples,
            'sql' => 'CREATE INDEX idx_unsorted ON wp_options(lower(option_name), autoload)',
        ]], $point($expr('lower', 'option_name'), 'siteurl'), [], ['autoload']);
        $t->same('active_plugins', $plan['stat4CurrentNext'][0]['current']['key']);
    },
    'planner expression covering stat4 current next37 keeps covering tail with stat4 estimate' => static function (TestRunner $t) use ($indexes, $expr, $point): void {
        $plan = SQLiteSelectExpressionIndexPlan::chooseLowestCost([$indexes()[0]], $point($expr('lower', 'option_name'), 'home'), [], ['autoload', 'option_value']);
        $t->same(true, $plan['covering']);
        $t->same(['autoload', 'option_value'], $plan['trailingColumns']);
    },
    'planner expression covering stat4 current next37 rejects uncovered source column despite stat4' => static function (TestRunner $t) use ($indexes, $expr, $point): void {
        $plan = SQLiteSelectExpressionIndexPlan::chooseLowestCost([$indexes()[0]], $point($expr('lower', 'option_name'), 'home'), [], ['option_name']);
        $t->same(false, $plan['covering']);
    },
    'planner expression covering stat4 current next37 order tail remains satisfied for point lookup' => static function (TestRunner $t) use ($indexes, $expr, $point): void {
        $plan = SQLiteSelectExpressionIndexPlan::chooseLowestCost([$indexes()[0]], $point($expr('lower', 'option_name'), 'home'), [['column' => 'autoload']], ['autoload']);
        $t->same(true, $plan['orderBySatisfied']);
    },
    'planner expression covering stat4 current next37 range disables tail order' => static function (TestRunner $t) use ($indexes, $expr, $range): void {
        $plan = SQLiteSelectExpressionIndexPlan::chooseLowestCost([$indexes()[0]], $range($expr('lower', 'option_name'), '>=', 'home'), [['column' => 'autoload']], ['autoload']);
        $t->same(false, $plan['orderBySatisfied']);
    },
    'planner expression covering stat4 current next37 lower exclusive range uses nlt boundary' => static function (TestRunner $t) use ($indexes, $expr, $range): void {
        $plan = SQLiteSelectExpressionIndexPlan::chooseLowestCost([$indexes()[0]], $range($expr('lower', 'option_name'), '<', 'siteurl'), [], ['autoload']);
        $t->same(11, $plan['estimatedRows']);
    },
    'planner expression covering stat4 current next37 lower inclusive range includes neq boundary' => static function (TestRunner $t) use ($indexes, $expr, $range): void {
        $plan = SQLiteSelectExpressionIndexPlan::chooseLowestCost([$indexes()[0]], $range($expr('lower', 'option_name'), '<=', 'siteurl'), [], ['autoload']);
        $t->same(12, $plan['estimatedRows']);
    },
    'planner expression covering stat4 current next37 upper exclusive range subtracts inclusive lower side' => static function (TestRunner $t) use ($indexes, $expr, $range): void {
        $plan = SQLiteSelectExpressionIndexPlan::chooseLowestCost([$indexes()[0]], $range($expr('lower', 'option_name'), '>', 'siteurl'), [], ['autoload']);
        $t->same(48, $plan['estimatedRows']);
    },
    'planner expression covering stat4 current next37 upper inclusive range subtracts exclusive lower side' => static function (TestRunner $t) use ($indexes, $expr, $range): void {
        $plan = SQLiteSelectExpressionIndexPlan::chooseLowestCost([$indexes()[0]], $range($expr('lower', 'option_name'), '>=', 'siteurl'), [], ['autoload']);
        $t->same(49, $plan['estimatedRows']);
    },
    'planner expression covering stat4 current next37 before first range clamps to one row minimum' => static function (TestRunner $t) use ($indexes, $expr, $range): void {
        $plan = SQLiteSelectExpressionIndexPlan::chooseLowestCost([$indexes()[0]], $range($expr('lower', 'option_name'), '<', 'aaa'), [], ['autoload']);
        $t->same(1, $plan['estimatedRows']);
    },
    'planner expression covering stat4 current next37 after last range uses final sample tail' => static function (TestRunner $t) use ($indexes, $expr, $range): void {
        $plan = SQLiteSelectExpressionIndexPlan::chooseLowestCost([$indexes()[0]], $range($expr('lower', 'option_name'), '<=', 'zzzz'), [], ['autoload']);
        $t->same(40, $plan['estimatedRows']);
    },
    'planner expression covering stat4 current next37 between uses inclusive upper exclusive lower math' => static function (TestRunner $t) use ($indexes, $expr, $between): void {
        $plan = SQLiteSelectExpressionIndexPlan::chooseLowestCost([$indexes()[0]], $between($expr('lower', 'option_name'), 'home', 'transient_feed'), [], ['autoload']);
        $t->same(27, $plan['estimatedRows']);
    },
    'planner expression covering stat4 current next37 between outside sample minimum clamps to one' => static function (TestRunner $t) use ($indexes, $expr, $between): void {
        $plan = SQLiteSelectExpressionIndexPlan::chooseLowestCost([$indexes()[0]], $between($expr('lower', 'option_name'), 'aaa', 'aab'), [], ['autoload']);
        $t->same(1, $plan['estimatedRows']);
    },
    'planner expression covering stat4 current next37 in list sums sample equalities' => static function (TestRunner $t) use ($indexes, $expr, $inList): void {
        $plan = SQLiteSelectExpressionIndexPlan::chooseLowestCost([$indexes()[0]], $inList($expr('lower', 'option_name'), ['home', 'siteurl', null]), [], ['autoload']);
        $t->same(3, $plan['estimatedRows']);
    },
    'planner expression covering stat4 current next37 in list deduplicates values' => static function (TestRunner $t) use ($indexes, $expr, $inList): void {
        $plan = SQLiteSelectExpressionIndexPlan::chooseLowestCost([$indexes()[0]], $inList($expr('lower', 'option_name'), ['siteurl', 'siteurl', null]), [], ['autoload']);
        $t->same(1, $plan['estimatedRows']);
    },
    'planner expression covering stat4 current next37 in list unknown values use average' => static function (TestRunner $t) use ($indexes, $expr, $inList): void {
        $plan = SQLiteSelectExpressionIndexPlan::chooseLowestCost([$indexes()[0]], $inList($expr('lower', 'option_name'), ['cron', 'theme_mods']), [], ['autoload']);
        $t->same(20, $plan['estimatedRows']);
    },
    'planner expression covering stat4 current next37 all null in list clamps to one' => static function (TestRunner $t) use ($indexes, $expr, $inList): void {
        $plan = SQLiteSelectExpressionIndexPlan::chooseLowestCost([$indexes()[0]], $inList($expr('lower', 'option_name'), [null]), [], ['autoload']);
        $t->same(1, $plan['estimatedRows']);
    },
    'planner expression covering stat4 current next37 reversed equality operand uses stat4' => static function (TestRunner $t) use ($indexes, $expr): void {
        $plan = SQLiteSelectExpressionIndexPlan::chooseLowestCost([$indexes()[0]], ['operator' => '=', 'left' => 'home', 'right' => $expr('lower', 'option_name')], [], ['autoload']);
        $t->same(2, $plan['estimatedRows']);
    },
    'planner expression covering stat4 current next37 reversed range operand uses stat4' => static function (TestRunner $t) use ($indexes, $expr): void {
        $plan = SQLiteSelectExpressionIndexPlan::chooseLowestCost([$indexes()[0]], ['operator' => '<=', 'left' => 'home', 'right' => $expr('lower', 'option_name')], [], ['autoload']);
        $t->same('range->=', $plan['operator']);
        $t->same(51, $plan['estimatedRows']);
    },
    'planner expression covering stat4 current next37 and predicate picks stat4 expression term' => static function (TestRunner $t) use ($indexes, $expr, $point, $and): void {
        $plan = SQLiteSelectExpressionIndexPlan::chooseLowestCost([$indexes()[0]], $and(['operator' => '=', 'left' => ['column' => 'autoload'], 'right' => 'yes'], $point($expr('lower', 'option_name'), 'home')), [], ['autoload']);
        $t->same(2, $plan['estimatedRows']);
    },
    'planner expression covering stat4 current next37 upper expression reuses text stat4 samples' => static function (TestRunner $t) use ($indexes, $expr, $point): void {
        $plan = SQLiteSelectExpressionIndexPlan::chooseLowestCost([$indexes()[2]], $point($expr('upper', 'option_name'), 'home'), [['column' => 'option_name', 'direction' => 'DESC']], ['autoload']);
        $t->same(2, $plan['estimatedRows']);
        $t->same(true, $plan['orderBySatisfied']);
    },
    'planner expression covering stat4 current next37 integer exact sample uses numeric neq' => static function (TestRunner $t) use ($indexes, $expr, $point): void {
        $plan = SQLiteSelectExpressionIndexPlan::chooseLowestCost([$indexes()[3]], $point($expr('cast_integer', 'option_value'), 10), [], ['autoload']);
        $t->same(12, $plan['estimatedRows']);
    },
    'planner expression covering stat4 current next37 integer unknown equality uses numeric distinct average' => static function (TestRunner $t) use ($indexes, $expr, $point): void {
        $plan = SQLiteSelectExpressionIndexPlan::chooseLowestCost([$indexes()[3]], $point($expr('cast_integer', 'option_value'), 7), [], ['autoload']);
        $t->same(12, $plan['estimatedRows']);
    },
    'planner expression covering stat4 current next37 integer less than boundary' => static function (TestRunner $t) use ($indexes, $expr, $range): void {
        $plan = SQLiteSelectExpressionIndexPlan::chooseLowestCost([$indexes()[3]], $range($expr('cast_integer', 'option_value'), '<', 100), [], ['autoload']);
        $t->same(20, $plan['estimatedRows']);
    },
    'planner expression covering stat4 current next37 integer less equal boundary' => static function (TestRunner $t) use ($indexes, $expr, $range): void {
        $plan = SQLiteSelectExpressionIndexPlan::chooseLowestCost([$indexes()[3]], $range($expr('cast_integer', 'option_value'), '<=', 100), [], ['autoload']);
        $t->same(50, $plan['estimatedRows']);
    },
    'planner expression covering stat4 current next37 integer greater than boundary' => static function (TestRunner $t) use ($indexes, $expr, $range): void {
        $plan = SQLiteSelectExpressionIndexPlan::chooseLowestCost([$indexes()[3]], $range($expr('cast_integer', 'option_value'), '>', 100), [], ['autoload']);
        $t->same(10, $plan['estimatedRows']);
    },
    'planner expression covering stat4 current next37 integer between uses numeric samples' => static function (TestRunner $t) use ($indexes, $expr, $between): void {
        $plan = SQLiteSelectExpressionIndexPlan::chooseLowestCost([$indexes()[3]], $between($expr('cast_integer', 'option_value'), 1, 100), [], ['autoload']);
        $t->same(47, $plan['estimatedRows']);
    },
    'planner expression covering stat4 current next37 integer in list sums exact numeric equalities' => static function (TestRunner $t) use ($indexes, $expr, $inList): void {
        $plan = SQLiteSelectExpressionIndexPlan::chooseLowestCost([$indexes()[3]], $inList($expr('cast_integer', 'option_value'), [1, 10, 1000]), [], ['autoload']);
        $t->same(19, $plan['estimatedRows']);
    },
    'planner expression covering stat4 current next37 numeric current next preserves integer key' => static function (TestRunner $t) use ($indexes, $expr, $point): void {
        $plan = SQLiteSelectExpressionIndexPlan::chooseLowestCost([$indexes()[3]], $point($expr('cast_integer', 'option_value'), 10), [], ['autoload']);
        $t->same(0, $plan['stat4CurrentNext'][0]['current']['key']);
        $t->same(1, $plan['stat4CurrentNext'][0]['next']['key']);
    },
    'planner expression covering stat4 current next37 stat4 rows are capped by base rows' => static function (TestRunner $t) use ($expr, $point): void {
        $plan = SQLiteSelectExpressionIndexPlan::chooseLowestCost([[
            'name' => 'idx_cap',
            'estimatedRows' => 5,
            'stat4Samples' => [['neq' => 99, 'nlt' => 0, 'ndlt' => 0, 'sample' => ['siteurl']]],
            'sql' => 'CREATE INDEX idx_cap ON wp_options(lower(option_name), autoload)',
        ]], $point($expr('lower', 'option_name'), 'siteurl'), [], ['autoload']);
        $t->same(5, $plan['estimatedRows']);
    },
    'planner expression covering stat4 current next37 stat4 changes selected covering plan by selectivity' => static function (TestRunner $t) use ($expr, $point, $stat4NameSamples): void {
        $plans = SQLiteSelectExpressionIndexPlan::rankedPlans([
            ['name' => 'idx_cover_wide', 'estimatedRows' => 60, 'stat4Samples' => $stat4NameSamples(), 'sql' => 'CREATE INDEX idx_cover_wide ON wp_options(lower(option_name), autoload, option_value)'],
            ['name' => 'idx_not_cover_small', 'estimatedRows' => 120, 'sql' => 'CREATE INDEX idx_not_cover_small ON wp_options(lower(option_name))'],
        ], $point($expr('lower', 'option_name'), 'transient_feed'), [], ['autoload', 'option_value']);
        $t->same('idx_not_cover_small', $plans[0]['name']);
    },
    'planner expression covering stat4 current next37 stat4 keeps covering win for selective sample' => static function (TestRunner $t) use ($expr, $point, $stat4NameSamples): void {
        $plans = SQLiteSelectExpressionIndexPlan::rankedPlans([
            ['name' => 'idx_cover_selective', 'estimatedRows' => 60, 'stat4Samples' => $stat4NameSamples(), 'sql' => 'CREATE INDEX idx_cover_selective ON wp_options(lower(option_name), autoload, option_value)'],
            ['name' => 'idx_plain_equal_base', 'estimatedRows' => 60, 'sql' => 'CREATE INDEX idx_plain_equal_base ON wp_options(lower(option_name))'],
        ], $point($expr('lower', 'option_name'), 'siteurl'), [], ['autoload']);
        $t->same('idx_cover_selective', $plans[0]['name']);
    },
    'planner expression covering stat4 current next37 validates stat4 list shape' => static function (TestRunner $t) use ($expr, $point): void {
        $t->throws(InvalidArgumentException::class, static fn () => SQLiteSelectExpressionIndexPlan::rankedPlans([[
            'name' => 'bad',
            'estimatedRows' => 60,
            'stat4Samples' => ['bad' => []],
            'sql' => 'CREATE INDEX bad ON wp_options(lower(option_name))',
        ]], $point($expr('lower', 'option_name'), 'siteurl')));
    },
    'planner expression covering stat4 current next37 validates stat4 row array' => static function (TestRunner $t) use ($expr, $point): void {
        $t->throws(InvalidArgumentException::class, static fn () => SQLiteSelectExpressionIndexPlan::rankedPlans([[
            'name' => 'bad',
            'estimatedRows' => 60,
            'stat4Samples' => ['bad'],
            'sql' => 'CREATE INDEX bad ON wp_options(lower(option_name))',
        ]], $point($expr('lower', 'option_name'), 'siteurl')));
    },
    'planner expression covering stat4 current next37 validates sample key vector' => static function (TestRunner $t) use ($expr, $point): void {
        $t->throws(InvalidArgumentException::class, static fn () => SQLiteSelectExpressionIndexPlan::rankedPlans([[
            'name' => 'bad',
            'estimatedRows' => 60,
            'stat4Samples' => [['neq' => 1, 'nlt' => 0, 'sample' => []]],
            'sql' => 'CREATE INDEX bad ON wp_options(lower(option_name))',
        ]], $point($expr('lower', 'option_name'), 'siteurl')));
    },
    'planner expression covering stat4 current next37 validates neq token' => static function (TestRunner $t) use ($expr, $point): void {
        $t->throws(InvalidArgumentException::class, static fn () => SQLiteSelectExpressionIndexPlan::rankedPlans([[
            'name' => 'bad',
            'estimatedRows' => 60,
            'stat4Samples' => [['neq' => 'x', 'nlt' => 0, 'sample' => ['siteurl']]],
            'sql' => 'CREATE INDEX bad ON wp_options(lower(option_name))',
        ]], $point($expr('lower', 'option_name'), 'siteurl')));
    },
    'planner expression covering stat4 current next37 validates nlt token' => static function (TestRunner $t) use ($expr, $point): void {
        $t->throws(InvalidArgumentException::class, static fn () => SQLiteSelectExpressionIndexPlan::rankedPlans([[
            'name' => 'bad',
            'estimatedRows' => 60,
            'stat4Samples' => [['neq' => 1, 'nlt' => -1, 'sample' => ['siteurl']]],
            'sql' => 'CREATE INDEX bad ON wp_options(lower(option_name))',
        ]], $point($expr('lower', 'option_name'), 'siteurl')));
    },
];

$sampleValues = ['active_plugins' => 1, 'autoloaded_widget' => 8, 'home' => 2, 'siteurl' => 1, 'transient_feed' => 24, 'widget_recent' => 4];
foreach ($sampleValues as $value => $expectedRows) {
    $tests["planner expression covering stat4 current next37 sample equality {$value} rows {$expectedRows}"] = static function (TestRunner $t) use ($indexes, $expr, $point, $value, $expectedRows): void {
        $plan = SQLiteSelectExpressionIndexPlan::chooseLowestCost([$indexes()[0]], $point($expr('lower', 'option_name'), $value), [], ['autoload']);
        $t->same($expectedRows, $plan['estimatedRows']);
        $t->same(true, $plan['stat4Used']);
    };
}

$rangeCases = [
    ['<', 'home', 9],
    ['<=', 'home', 11],
    ['>', 'home', 49],
    ['>=', 'home', 51],
    ['<', 'transient_feed', 12],
    ['<=', 'transient_feed', 36],
    ['>', 'transient_feed', 24],
    ['>=', 'transient_feed', 48],
];
foreach ($rangeCases as [$operator, $value, $expectedRows]) {
    $tests["planner expression covering stat4 current next37 range {$operator} {$value} estimates {$expectedRows}"] = static function (TestRunner $t) use ($indexes, $expr, $range, $operator, $value, $expectedRows): void {
        $plan = SQLiteSelectExpressionIndexPlan::chooseLowestCost([$indexes()[0]], $range($expr('lower', 'option_name'), $operator, $value), [], ['autoload']);
        $t->same($expectedRows, $plan['estimatedRows']);
        $t->same(true, $plan['stat4Used']);
    };
}

$betweenCases = [
    ['active_plugins', 'home', 11],
    ['autoloaded_widget', 'siteurl', 11],
    ['siteurl', 'widget_recent', 29],
    ['transient_feed', 'widget_recent', 28],
];
foreach ($betweenCases as [$lower, $upper, $expectedRows]) {
    $tests["planner expression covering stat4 current next37 between {$lower} and {$upper} estimates {$expectedRows}"] = static function (TestRunner $t) use ($indexes, $expr, $between, $lower, $upper, $expectedRows): void {
        $plan = SQLiteSelectExpressionIndexPlan::chooseLowestCost([$indexes()[0]], $between($expr('lower', 'option_name'), $lower, $upper), [], ['autoload']);
        $t->same($expectedRows, $plan['estimatedRows']);
    };
}

$inCases = [
    [['active_plugins', 'home'], 3],
    [['autoloaded_widget', 'widget_recent'], 12],
    [['transient_feed', 'siteurl'], 25],
    [['cron', 'home', 'siteurl'], 13],
];
foreach ($inCases as [$values, $expectedRows]) {
    $tests['planner expression covering stat4 current next37 in list ' . implode('-', $values) . " estimates {$expectedRows}"] = static function (TestRunner $t) use ($indexes, $expr, $inList, $values, $expectedRows): void {
        $plan = SQLiteSelectExpressionIndexPlan::chooseLowestCost([$indexes()[0]], $inList($expr('lower', 'option_name'), $values), [], ['autoload']);
        $t->same($expectedRows, $plan['estimatedRows']);
    };
}

return $tests;

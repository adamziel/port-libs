<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteSelectExpressionIndexPlan;

$expr = static fn (string $function, string $column, ?string $path = null): array => array_filter(
    ['function' => $function, 'column' => $column, 'path' => $path],
    static fn (mixed $value): bool => $value !== null
);
$point = static fn (array $left, mixed $value): array => ['operator' => '=', 'left' => $left, 'right' => $value];
$range = static fn (array $left, string $operator, mixed $value): array => ['operator' => $operator, 'left' => $left, 'right' => $value];
$between = static fn (array $left, mixed $lower, mixed $upper): array => ['operator' => 'BETWEEN', 'left' => $left, 'lower' => $lower, 'upper' => $upper];
$inList = static fn (array $left, array $values): array => ['operator' => 'IN', 'left' => $left, 'values' => $values];
$and = static fn (array ...$terms): array => ['operator' => 'AND', 'terms' => $terms];

$stat4NameSamples = static fn (): array => [
    ['neq' => '1 1', 'nlt' => '0 0', 'ndlt' => '0 0', 'sample' => ['active_plugins', 'yes']],
    ['neq' => '5 5', 'nlt' => '1 1', 'ndlt' => '1 1', 'sample' => ['jetpack_settings', 'yes']],
    ['neq' => '2 2', 'nlt' => '6 6', 'ndlt' => '2 2', 'sample' => ['siteurl', 'yes']],
    ['neq' => '12 12', 'nlt' => '8 8', 'ndlt' => '3 3', 'sample' => ['transient_feed', 'no']],
    ['neq' => '4 4', 'nlt' => '20 20', 'ndlt' => '4 4', 'sample' => ['widget_recent', 'yes']],
];

$stat4JsonSamples = static fn (): array => [
    ['neq' => '3 1', 'nlt' => '0 0', 'ndlt' => '0 0', 'sample' => ['enabled', 'plugin_alpha']],
    ['neq' => '7 2', 'nlt' => '3 1', 'ndlt' => '1 1', 'sample' => ['network', 'plugin_beta']],
    ['neq' => '2 1', 'nlt' => '10 3', 'ndlt' => '2 2', 'sample' => ['private', 'plugin_delta']],
    ['neq' => '9 3', 'nlt' => '12 4', 'ndlt' => '3 3', 'sample' => ['public', 'plugin_gamma']],
];

$indexes = static fn (): array => [
    [
        'name' => 'idx_lower_name_expr_cover_stat4',
        'rootPage' => 5101,
        'estimatedRows' => 24,
        'coveringColumns' => ['option_name', 'autoload'],
        'coveringExpressions' => [
            ['function' => 'upper', 'column' => 'option_name'],
            ['function' => 'length', 'column' => 'option_name'],
        ],
        'stat4Samples' => $stat4NameSamples(),
        'sql' => 'CREATE INDEX idx_lower_name_expr_cover_stat4 ON wp_options(lower(option_name), upper(option_name), length(option_name), autoload)',
    ],
    [
        'name' => 'idx_lower_name_column_only_stat4',
        'rootPage' => 5102,
        'estimatedRows' => 24,
        'coveringColumns' => ['option_name', 'autoload'],
        'stat4Samples' => $stat4NameSamples(),
        'sql' => 'CREATE INDEX idx_lower_name_column_only_stat4 ON wp_options(lower(option_name), autoload)',
    ],
    [
        'name' => 'idx_json_mode_expr_cover_stat4',
        'rootPage' => 5103,
        'estimatedRows' => 21,
        'coveringColumns' => ['option_name'],
        'coveringExpressions' => [
            ['function' => 'json_text_operator', 'column' => 'option_value', 'path' => '$.plugin'],
            ['function' => 'json_value_operator', 'column' => 'option_value', 'path' => '$.enabled'],
        ],
        'stat4Samples' => $stat4JsonSamples(),
        'sql' => "CREATE INDEX idx_json_mode_expr_cover_stat4 ON wp_options(json_extract(option_value,'$.mode'), option_name)",
    ],
    [
        'name' => 'idx_json_mode_wrong_kind_stat4',
        'rootPage' => 5104,
        'estimatedRows' => 21,
        'coveringColumns' => ['option_name'],
        'coveringExpressions' => [
            ['function' => 'json_value_operator', 'column' => 'option_value', 'path' => '$.plugin'],
        ],
        'stat4Samples' => $stat4JsonSamples(),
        'sql' => "CREATE INDEX idx_json_mode_wrong_kind_stat4 ON wp_options(json_extract(option_value,'$.mode'), option_name)",
    ],
    [
        'name' => 'idx_value_int_expr_cover_stat4',
        'rootPage' => 5105,
        'estimatedRows' => 40,
        'coveringColumns' => ['option_value'],
        'coveringExpressions' => [
            ['function' => 'length', 'column' => 'option_value'],
        ],
        'stat4Samples' => [
            ['neq' => [2, 1], 'nlt' => [0, 0], 'ndlt' => [0, 0], 'sample' => [0, 1]],
            ['neq' => [6, 2], 'nlt' => [2, 1], 'ndlt' => [1, 1], 'sample' => [10, 2]],
            ['neq' => [18, 3], 'nlt' => [8, 3], 'ndlt' => [2, 2], 'sample' => [100, 3]],
            ['neq' => [4, 1], 'nlt' => [26, 6], 'ndlt' => [3, 3], 'sample' => [1000, 4]],
        ],
        'sql' => 'CREATE INDEX idx_value_int_expr_cover_stat4 ON wp_options(CAST(option_value AS INTEGER), length(option_value), option_value)',
    ],
];

$lowerPlan = static fn (array $neededExpressions = null, array $neededColumns = ['autoload'], array $indexList = null): ?array => SQLiteSelectExpressionIndexPlan::chooseLowestCost(
    $indexList ?? $indexes(),
    $point($expr('lower', 'option_name'), 'siteurl'),
    [],
    $neededColumns,
    $neededExpressions ?? [$expr('upper', 'option_name')]
);

$jsonPlan = static fn (array $neededExpressions = null, array $indexList = null): ?array => SQLiteSelectExpressionIndexPlan::chooseLowestCost(
    $indexList ?? $indexes(),
    $point($expr('json_extract', 'option_value', '$.mode'), 'network'),
    [],
    ['option_name'],
    $neededExpressions ?? [$expr('json_text_operator', 'option_value', '$.plugin')]
);

$tests = [
    'planner stat4 covering expression current next51 chooses expression-covering lower index' => static function (TestRunner $t) use ($lowerPlan): void {
        $t->same('idx_lower_name_expr_cover_stat4', $lowerPlan()['name']);
    },
    'planner stat4 covering expression current next51 marks lower expression covering' => static function (TestRunner $t) use ($lowerPlan): void {
        $t->same(true, $lowerPlan()['covering']);
    },
    'planner stat4 covering expression current next51 reports covered upper expression' => static function (TestRunner $t) use ($lowerPlan): void {
        $t->same(['upper(option_name)'], $lowerPlan()['coveringExpressions']);
    },
    'planner stat4 covering expression current next51 keeps stat4 row estimate with expression payload' => static function (TestRunner $t) use ($lowerPlan): void {
        $t->same(2, $lowerPlan()['estimatedRows']);
    },
    'planner stat4 covering expression current next51 keeps stat4 true with expression payload' => static function (TestRunner $t) use ($lowerPlan): void {
        $t->same(true, $lowerPlan()['stat4Used']);
    },
    'planner stat4 covering expression current next51 keeps current next evidence' => static function (TestRunner $t) use ($lowerPlan): void {
        $t->same('active_plugins', $lowerPlan()['stat4CurrentNext'][0]['current']['key']);
    },
    'planner stat4 covering expression current next51 keeps root page' => static function (TestRunner $t) use ($lowerPlan): void {
        $t->same(5101, $lowerPlan()['rootPage']);
    },
    'planner stat4 covering expression current next51 keeps expression payload out of column tail' => static function (TestRunner $t) use ($lowerPlan): void {
        $t->same([], $lowerPlan()['trailingColumns']);
    },
    'planner stat4 covering expression current next51 expression coverage lowers cost' => static function (TestRunner $t) use ($lowerPlan): void {
        $t->same(2, $lowerPlan()['estimatedCost']);
    },
    'planner stat4 covering expression current next51 ranks covering expression before column only' => static function (TestRunner $t) use ($indexes, $expr, $point): void {
        $plans = SQLiteSelectExpressionIndexPlan::rankedPlans($indexes(), $point($expr('lower', 'option_name'), 'siteurl'), [], ['autoload'], [$expr('upper', 'option_name')]);
        $t->same('idx_lower_name_expr_cover_stat4', $plans[0]['name']);
    },
    'planner stat4 covering expression current next51 keeps column-only candidate noncovering' => static function (TestRunner $t) use ($indexes, $expr, $point): void {
        $plans = SQLiteSelectExpressionIndexPlan::rankedPlans($indexes(), $point($expr('lower', 'option_name'), 'siteurl'), [], ['autoload'], [$expr('upper', 'option_name')]);
        $t->same(false, $plans[1]['covering']);
    },
    'planner stat4 covering expression current next51 covers first searched expression without metadata' => static function (TestRunner $t) use ($indexes, $expr, $point): void {
        $plan = SQLiteSelectExpressionIndexPlan::chooseLowestCost([$indexes()[1]], $point($expr('lower', 'option_name'), 'siteurl'), [], ['autoload'], [$expr('lower', 'option_name')]);
        $t->same(true, $plan['covering']);
    },
    'planner stat4 covering expression current next51 does not cover unrelated upper without metadata' => static function (TestRunner $t) use ($indexes, $expr, $point): void {
        $plan = SQLiteSelectExpressionIndexPlan::chooseLowestCost([$indexes()[1]], $point($expr('lower', 'option_name'), 'siteurl'), [], ['autoload'], [$expr('upper', 'option_name')]);
        $t->same(false, $plan['covering']);
    },
    'planner stat4 covering expression current next51 covers multiple expression payloads' => static function (TestRunner $t) use ($lowerPlan, $expr): void {
        $plan = $lowerPlan([$expr('upper', 'option_name'), $expr('length', 'option_name')]);
        $t->same(true, $plan['covering']);
    },
    'planner stat4 covering expression current next51 reports multiple expression payloads' => static function (TestRunner $t) use ($lowerPlan, $expr): void {
        $plan = $lowerPlan([$expr('upper', 'option_name'), $expr('length', 'option_name')]);
        $t->same(['upper(option_name)', 'length(option_name)'], $plan['coveringExpressions']);
    },
    'planner stat4 covering expression current next51 rejects missing expression payload' => static function (TestRunner $t) use ($lowerPlan, $expr): void {
        $plan = $lowerPlan([$expr('upper', 'option_name'), $expr('cast_integer', 'option_value')]);
        $t->same(false, $plan['covering']);
    },
    'planner stat4 covering expression current next51 still uses stat4 when expression uncovered' => static function (TestRunner $t) use ($lowerPlan, $expr): void {
        $plan = $lowerPlan([$expr('cast_integer', 'option_value')]);
        $t->same(true, $plan['stat4Used']);
    },
    'planner stat4 covering expression current next51 missing column breaks covering despite expression' => static function (TestRunner $t) use ($lowerPlan): void {
        $plan = $lowerPlan(null, ['option_id']);
        $t->same(false, $plan['covering']);
    },
    'planner stat4 covering expression current next51 json text operator payload covers json extract search' => static function (TestRunner $t) use ($jsonPlan): void {
        $t->same(true, $jsonPlan()['covering']);
    },
    'planner stat4 covering expression current next51 json payload display includes path' => static function (TestRunner $t) use ($jsonPlan): void {
        $t->same(['json_text_operator(option_value,$.plugin)'], $jsonPlan()['coveringExpressions']);
    },
    'planner stat4 covering expression current next51 json stat4 estimate uses matching sample' => static function (TestRunner $t) use ($jsonPlan): void {
        $t->same(7, $jsonPlan()['estimatedRows']);
    },
    'planner stat4 covering expression current next51 json current next first key' => static function (TestRunner $t) use ($jsonPlan): void {
        $t->same('enabled', $jsonPlan()['stat4CurrentNext'][0]['current']['key']);
    },
    'planner stat4 covering expression current next51 json value operator payload is distinct' => static function (TestRunner $t) use ($jsonPlan, $expr): void {
        $plan = $jsonPlan([$expr('json_value_operator', 'option_value', '$.enabled')]);
        $t->same(true, $plan['covering']);
    },
    'planner stat4 covering expression current next51 json operator kind mismatch is uncovered' => static function (TestRunner $t) use ($jsonPlan, $expr): void {
        $plan = $jsonPlan([$expr('json_text_operator', 'option_value', '$.enabled')]);
        $t->same(false, $plan['covering']);
    },
    'planner stat4 covering expression current next51 json path mismatch is uncovered' => static function (TestRunner $t) use ($jsonPlan, $expr): void {
        $plan = $jsonPlan([$expr('json_text_operator', 'option_value', '$.missing')]);
        $t->same(false, $plan['covering']);
    },
    'planner stat4 covering expression current next51 json wrong-kind metadata stays uncovered' => static function (TestRunner $t) use ($indexes, $expr, $point): void {
        $plan = SQLiteSelectExpressionIndexPlan::chooseLowestCost([$indexes()[3]], $point($expr('json_extract', 'option_value', '$.mode'), 'network'), [], ['option_name'], [$expr('json_text_operator', 'option_value', '$.plugin')]);
        $t->same(false, $plan['covering']);
    },
    'planner stat4 covering expression current next51 integer cast covers length payload' => static function (TestRunner $t) use ($indexes, $expr, $point): void {
        $plan = SQLiteSelectExpressionIndexPlan::chooseLowestCost([$indexes()[4]], $point($expr('cast_integer', 'option_value'), 10), [], ['option_value'], [$expr('length', 'option_value')]);
        $t->same(true, $plan['covering']);
    },
    'planner stat4 covering expression current next51 integer cast stat4 equality' => static function (TestRunner $t) use ($indexes, $expr, $point): void {
        $plan = SQLiteSelectExpressionIndexPlan::chooseLowestCost([$indexes()[4]], $point($expr('cast_integer', 'option_value'), 10), [], ['option_value'], [$expr('length', 'option_value')]);
        $t->same(6, $plan['estimatedRows']);
    },
    'planner stat4 covering expression current next51 integer cast reports length payload' => static function (TestRunner $t) use ($indexes, $expr, $point): void {
        $plan = SQLiteSelectExpressionIndexPlan::chooseLowestCost([$indexes()[4]], $point($expr('cast_integer', 'option_value'), 10), [], ['option_value'], [$expr('length', 'option_value')]);
        $t->same(['length(option_value)'], $plan['coveringExpressions']);
    },
    'planner stat4 covering expression current next51 integer cast range remains covering' => static function (TestRunner $t) use ($indexes, $expr, $range): void {
        $plan = SQLiteSelectExpressionIndexPlan::chooseLowestCost([$indexes()[4]], $range($expr('cast_integer', 'option_value'), '<=', 100), [], ['option_value'], [$expr('length', 'option_value')]);
        $t->same(true, $plan['covering']);
    },
    'planner stat4 covering expression current next51 integer cast range stat4 estimate' => static function (TestRunner $t) use ($indexes, $expr, $range): void {
        $plan = SQLiteSelectExpressionIndexPlan::chooseLowestCost([$indexes()[4]], $range($expr('cast_integer', 'option_value'), '<=', 100), [], ['option_value'], [$expr('length', 'option_value')]);
        $t->same(26, $plan['estimatedRows']);
    },
    'planner stat4 covering expression current next51 in-list expression covering sums samples' => static function (TestRunner $t) use ($indexes, $expr, $inList): void {
        $plan = SQLiteSelectExpressionIndexPlan::chooseLowestCost([$indexes()[0]], $inList($expr('lower', 'option_name'), ['siteurl', 'transient_feed']), [], ['autoload'], [$expr('upper', 'option_name')]);
        $t->same(14, $plan['estimatedRows']);
    },
    'planner stat4 covering expression current next51 in-list expression remains covering' => static function (TestRunner $t) use ($indexes, $expr, $inList): void {
        $plan = SQLiteSelectExpressionIndexPlan::chooseLowestCost([$indexes()[0]], $inList($expr('lower', 'option_name'), ['siteurl', 'transient_feed']), [], ['autoload'], [$expr('upper', 'option_name')]);
        $t->same(true, $plan['covering']);
    },
    'planner stat4 covering expression current next51 between expression stat4 estimate' => static function (TestRunner $t) use ($indexes, $expr, $between): void {
        $plan = SQLiteSelectExpressionIndexPlan::chooseLowestCost([$indexes()[0]], $between($expr('lower', 'option_name'), 'jetpack_settings', 'transient_feed'), [], ['autoload'], [$expr('upper', 'option_name')]);
        $t->same(19, $plan['estimatedRows']);
    },
    'planner stat4 covering expression current next51 between expression remains covering' => static function (TestRunner $t) use ($indexes, $expr, $between): void {
        $plan = SQLiteSelectExpressionIndexPlan::chooseLowestCost([$indexes()[0]], $between($expr('lower', 'option_name'), 'jetpack_settings', 'transient_feed'), [], ['autoload'], [$expr('upper', 'option_name')]);
        $t->same(true, $plan['covering']);
    },
    'planner stat4 covering expression current next51 and predicate expression coverage survives ordinary term' => static function (TestRunner $t) use ($indexes, $expr, $point, $and): void {
        $plan = SQLiteSelectExpressionIndexPlan::chooseLowestCost([$indexes()[0]], $and(['operator' => '=', 'left' => ['column' => 'autoload'], 'right' => 'yes'], $point($expr('lower', 'option_name'), 'siteurl')), [], ['autoload'], [$expr('upper', 'option_name')]);
        $t->same(true, $plan['covering']);
    },
    'planner stat4 covering expression current next51 and predicate keeps stat4 rows' => static function (TestRunner $t) use ($indexes, $expr, $point, $and): void {
        $plan = SQLiteSelectExpressionIndexPlan::chooseLowestCost([$indexes()[0]], $and(['operator' => '=', 'left' => ['column' => 'autoload'], 'right' => 'yes'], $point($expr('lower', 'option_name'), 'siteurl')), [], ['autoload'], [$expr('upper', 'option_name')]);
        $t->same(2, $plan['estimatedRows']);
    },
    'planner stat4 covering expression current next51 first expression and extra expression both covered' => static function (TestRunner $t) use ($lowerPlan, $expr): void {
        $plan = $lowerPlan([$expr('lower', 'option_name'), $expr('upper', 'option_name')]);
        $t->same(true, $plan['covering']);
    },
    'planner stat4 covering expression current next51 first expression appears in coverage report' => static function (TestRunner $t) use ($lowerPlan, $expr): void {
        $plan = $lowerPlan([$expr('lower', 'option_name'), $expr('upper', 'option_name')]);
        $t->same(['lower(option_name)', 'upper(option_name)'], $plan['coveringExpressions']);
    },
    'planner stat4 covering expression current next51 validates covering expression list shape' => static function (TestRunner $t) use ($indexes, $expr, $point): void {
        $bad = $indexes();
        $bad[0]['coveringExpressions'] = ['bad' => []];
        $t->throws(InvalidArgumentException::class, static fn () => SQLiteSelectExpressionIndexPlan::chooseLowestCost([$bad[0]], $point($expr('lower', 'option_name'), 'siteurl'), [], ['autoload'], [$expr('upper', 'option_name')]));
    },
    'planner stat4 covering expression current next51 validates covering expression row shape' => static function (TestRunner $t) use ($indexes, $expr, $point): void {
        $bad = $indexes();
        $bad[0]['coveringExpressions'] = ['bad'];
        $t->throws(InvalidArgumentException::class, static fn () => SQLiteSelectExpressionIndexPlan::chooseLowestCost([$bad[0]], $point($expr('lower', 'option_name'), 'siteurl'), [], ['autoload'], [$expr('upper', 'option_name')]));
    },
    'planner stat4 covering expression current next51 validates covering expression operand' => static function (TestRunner $t) use ($indexes, $expr, $point): void {
        $bad = $indexes();
        $bad[0]['coveringExpressions'] = [['function' => 'substr', 'column' => 'option_name']];
        $t->throws(InvalidArgumentException::class, static fn () => SQLiteSelectExpressionIndexPlan::chooseLowestCost([$bad[0]], $point($expr('lower', 'option_name'), 'siteurl'), [], ['autoload'], [$expr('upper', 'option_name')]));
    },
    'planner stat4 covering expression current next51 validates needed expression operand' => static function (TestRunner $t) use ($indexes, $expr, $point): void {
        $t->throws(InvalidArgumentException::class, static fn () => SQLiteSelectExpressionIndexPlan::chooseLowestCost([$indexes()[0]], $point($expr('lower', 'option_name'), 'siteurl'), [], ['autoload'], [['function' => 'substr', 'column' => 'option_name']]));
    },
    'planner stat4 covering expression current next51 validates needed json path' => static function (TestRunner $t) use ($indexes, $expr, $point): void {
        $t->throws(InvalidArgumentException::class, static fn () => SQLiteSelectExpressionIndexPlan::chooseLowestCost([$indexes()[2]], $point($expr('json_extract', 'option_value', '$.mode'), 'network'), [], ['option_name'], [['function' => 'json_text_operator', 'column' => 'option_value', 'path' => 'bad']]));
    },
];

$equalityCases = [
    ['active_plugins', 1],
    ['jetpack_settings', 5],
    ['siteurl', 2],
    ['transient_feed', 12],
    ['widget_recent', 4],
];
foreach ($equalityCases as [$value, $expectedRows]) {
    $tests["planner stat4 covering expression current next51 equality {$value} rows {$expectedRows}"] = static function (TestRunner $t) use ($indexes, $expr, $point, $value, $expectedRows): void {
        $plan = SQLiteSelectExpressionIndexPlan::chooseLowestCost([$indexes()[0]], $point($expr('lower', 'option_name'), $value), [], ['autoload'], [$expr('upper', 'option_name')]);
        $t->same($expectedRows, $plan['estimatedRows']);
        $t->same(true, $plan['covering']);
    };
}

$rangeCases = [
    ['<', 'siteurl', 6],
    ['<=', 'siteurl', 8],
    ['>', 'siteurl', 16],
    ['>=', 'siteurl', 18],
    ['<', 'widget_recent', 20],
    ['<=', 'widget_recent', 24],
    ['>', 'active_plugins', 23],
    ['>=', 'active_plugins', 24],
];
foreach ($rangeCases as [$operator, $value, $expectedRows]) {
    $tests["planner stat4 covering expression current next51 range {$operator} {$value} rows {$expectedRows}"] = static function (TestRunner $t) use ($indexes, $expr, $range, $operator, $value, $expectedRows): void {
        $plan = SQLiteSelectExpressionIndexPlan::chooseLowestCost([$indexes()[0]], $range($expr('lower', 'option_name'), $operator, $value), [], ['autoload'], [$expr('upper', 'option_name')]);
        $t->same($expectedRows, $plan['estimatedRows']);
        $t->same(true, $plan['covering']);
    };
}

return $tests;

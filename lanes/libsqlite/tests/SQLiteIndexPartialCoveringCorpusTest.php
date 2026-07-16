<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteSelectExpressionIndexPlan;

$indexes = static fn (): array => [
    [
        'name' => 'idx_lower_name_partial',
        'rootPage' => 21,
        'estimatedRows' => 12000,
        'coveringColumns' => ['option_name'],
        'sql' => 'CREATE INDEX idx_lower_name_partial ON wp_options(lower(option_name)) WHERE option_name IS NOT NULL',
    ],
    [
        'name' => 'idx_lower_name_covering',
        'rootPage' => 22,
        'estimatedRows' => 320,
        'coveringColumns' => ['option_name', 'autoload', 'option_value'],
        'sql' => 'CREATE INDEX idx_lower_name_covering ON wp_options(lower(option_name)) WHERE option_name IS NOT NULL',
    ],
    [
        'name' => 'idx_upper_name_desc_covering',
        'rootPage' => 23,
        'estimatedRows' => 180,
        'coveringColumns' => ['option_name', 'autoload'],
        'sql' => 'CREATE INDEX idx_upper_name_desc_covering ON wp_options(upper(option_name) DESC) WHERE option_name IS NOT NULL',
    ],
    [
        'name' => 'idx_length_name_partial',
        'rootPage' => 24,
        'estimatedRows' => 1500,
        'coveringColumns' => ['option_name', 'autoload'],
        'sql' => 'CREATE INDEX idx_length_name_partial ON wp_options(length(option_name)) WHERE option_name IS NOT NULL',
    ],
    [
        'name' => 'idx_value_int_partial',
        'rootPage' => 25,
        'estimatedRows' => 640,
        'coveringColumns' => ['option_value', 'autoload'],
        'sql' => 'CREATE INDEX idx_value_int_partial ON wp_options(CAST(option_value AS INTEGER)) WHERE option_value IS NOT NULL',
    ],
    [
        'name' => 'idx_lower_name_or_partial',
        'rootPage' => 26,
        'estimatedRows' => 600,
        'coveringColumns' => ['option_name', 'autoload'],
        'sql' => "CREATE INDEX idx_lower_name_or_partial ON wp_options(lower(option_name)) WHERE option_name IS NOT NULL OR autoload='yes'",
    ],
];

$lowerPoint = static fn (mixed $value = 'siteurl'): array => [
    'operator' => '=',
    'left' => ['function' => 'lower', 'column' => 'option_name'],
    'right' => $value,
];

$upperRange = static fn (): array => [
    'operator' => 'AND',
    'terms' => [
        ['operator' => '>=', 'left' => ['function' => 'upper', 'column' => 'option_name'], 'right' => '_TRANSIENT_'],
        ['operator' => '<', 'left' => ['function' => 'upper', 'column' => 'option_name'], 'right' => '_TRANSIENT`'],
    ],
];

$tests = [
    'index partial covering corpus chooses covering lower point lookup' => static function (TestRunner $t) use ($indexes, $lowerPoint): void {
        $plan = SQLiteSelectExpressionIndexPlan::chooseLowestCost($indexes(), $lowerPoint(), [['column' => 'option_name']], ['option_name', 'autoload']);
        $t->same('idx_lower_name_covering', $plan['name']);
        $t->same(true, $plan['partial']);
        $t->same(true, $plan['covering']);
    },
    'index partial covering corpus keeps root page for selected covering lookup' => static function (TestRunner $t) use ($indexes, $lowerPoint): void {
        $plan = SQLiteSelectExpressionIndexPlan::chooseLowestCost($indexes(), $lowerPoint(), [], ['option_name', 'autoload']);
        $t->same(22, $plan['rootPage']);
        $t->same('point', $plan['operator']);
        $t->same('siteurl', $plan['values']);
    },
    'index partial covering corpus ranks narrow covering before broad partial' => static function (TestRunner $t) use ($indexes, $lowerPoint): void {
        $plans = SQLiteSelectExpressionIndexPlan::rankedPlans($indexes(), $lowerPoint(), [], ['option_name', 'autoload']);
        $t->same(['idx_lower_name_covering', 'idx_lower_name_or_partial', 'idx_lower_name_partial'], array_column($plans, 'name'));
    },
    'index partial covering corpus records non covering broad lower lookup' => static function (TestRunner $t) use ($indexes, $lowerPoint): void {
        $plans = SQLiteSelectExpressionIndexPlan::rankedPlans($indexes(), $lowerPoint(), [], ['option_name', 'autoload']);
        $t->same(false, $plans[2]['covering']);
        $t->same(120, $plans[2]['estimatedRows']);
    },
    'index partial covering corpus satisfies ascending order from lower expression index' => static function (TestRunner $t) use ($indexes, $lowerPoint): void {
        $plan = SQLiteSelectExpressionIndexPlan::chooseLowestCost($indexes(), $lowerPoint(), [['column' => 'option_name', 'direction' => 'ASC']], ['option_name']);
        $t->same(true, $plan['orderBySatisfied']);
        $t->same(false, $plan['descending']);
    },
    'index partial covering corpus rejects descending order for ascending lower index' => static function (TestRunner $t) use ($indexes, $lowerPoint): void {
        $plan = SQLiteSelectExpressionIndexPlan::chooseLowestCost($indexes(), $lowerPoint(), [['column' => 'option_name', 'direction' => 'DESC']], ['option_name']);
        $t->same(false, $plan['orderBySatisfied']);
    },
    'index partial covering corpus chooses descending upper range for transient scan' => static function (TestRunner $t) use ($indexes, $upperRange): void {
        $plan = SQLiteSelectExpressionIndexPlan::chooseLowestCost($indexes(), $upperRange(), [['column' => 'option_name', 'direction' => 'DESC']], ['option_name', 'autoload']);
        $t->same('idx_upper_name_desc_covering', $plan['name']);
        $t->same('range->=', $plan['operator']);
        $t->same(true, $plan['descending']);
    },
    'index partial covering corpus records upper range estimated rows' => static function (TestRunner $t) use ($indexes, $upperRange): void {
        $plan = SQLiteSelectExpressionIndexPlan::chooseLowestCost($indexes(), $upperRange(), [['column' => 'option_name', 'direction' => 'DESC']], ['option_name', 'autoload']);
        $t->same(45, $plan['estimatedRows']);
        $t->same(54, $plan['estimatedCost']);
    },
    'index partial covering corpus leaves upper ascending order unsatisfied' => static function (TestRunner $t) use ($indexes, $upperRange): void {
        $plan = SQLiteSelectExpressionIndexPlan::chooseLowestCost($indexes(), $upperRange(), [['column' => 'option_name', 'direction' => 'ASC']], ['option_name']);
        $t->same(false, $plan['orderBySatisfied']);
    },
    'index partial covering corpus uses length in list with non null member' => static function (TestRunner $t) use ($indexes): void {
        $plan = SQLiteSelectExpressionIndexPlan::chooseLowestCost($indexes(), [
            'operator' => 'IN',
            'left' => ['function' => 'length', 'column' => 'option_name'],
            'values' => [4, null, 7, 12],
        ], [], ['option_name', 'autoload']);
        $t->same('idx_length_name_partial', $plan['name']);
        $t->same([4, null, 7, 12], $plan['values']);
    },
    'index partial covering corpus length in list estimates distinct non null values' => static function (TestRunner $t) use ($indexes): void {
        $plan = SQLiteSelectExpressionIndexPlan::chooseLowestCost($indexes(), [
            'operator' => 'IN',
            'left' => ['function' => 'length', 'column' => 'option_name'],
            'values' => [4, 4, 7, null],
        ], [], ['option_name']);
        $t->same(45, $plan['estimatedRows']);
        $t->same(49, $plan['estimatedCost']);
    },
    'index partial covering corpus rejects all null in list for partial index' => static function (TestRunner $t) use ($indexes): void {
        $plans = SQLiteSelectExpressionIndexPlan::rankedPlans($indexes(), [
            'operator' => 'IN',
            'left' => ['function' => 'length', 'column' => 'option_name'],
            'values' => [null, null],
        ]);
        $t->same([], $plans);
    },
    'index partial covering corpus uses length between as residual range' => static function (TestRunner $t) use ($indexes): void {
        $plan = SQLiteSelectExpressionIndexPlan::chooseLowestCost($indexes(), [
            'operator' => 'BETWEEN',
            'left' => ['function' => 'length', 'column' => 'option_name'],
            'lower' => 5,
            'upper' => 20,
        ], [], ['autoload']);
        $t->same('BETWEEN', $plan['operator']);
        $t->same(['lower' => 5, 'upper' => 20], $plan['values']);
    },
    'index partial covering corpus rejects null between bounds for partial index' => static function (TestRunner $t) use ($indexes): void {
        $plans = SQLiteSelectExpressionIndexPlan::rankedPlans($indexes(), [
            'operator' => 'BETWEEN',
            'left' => ['function' => 'length', 'column' => 'option_name'],
            'lower' => null,
            'upper' => null,
        ]);
        $t->same([], $plans);
    },
    'index partial covering corpus uses integer cast point with reversed operands' => static function (TestRunner $t) use ($indexes): void {
        $plan = SQLiteSelectExpressionIndexPlan::chooseLowestCost($indexes(), [
            'operator' => '=',
            'left' => 60000,
            'right' => ['function' => 'cast_integer', 'column' => 'option_value'],
        ], [], ['option_value', 'autoload']);
        $t->same('idx_value_int_partial', $plan['name']);
        $t->same(60000, $plan['values']);
    },
    'index partial covering corpus reverses less than integer cast predicate' => static function (TestRunner $t) use ($indexes): void {
        $plan = SQLiteSelectExpressionIndexPlan::chooseLowestCost($indexes(), [
            'operator' => '<',
            'left' => 100,
            'right' => ['function' => 'cast_integer', 'column' => 'option_value'],
        ]);
        $t->same('range->', $plan['operator']);
        $t->same(100, $plan['values']);
    },
    'index partial covering corpus covers integer cast requested columns' => static function (TestRunner $t) use ($indexes): void {
        $plan = SQLiteSelectExpressionIndexPlan::chooseLowestCost($indexes(), [
            'operator' => 'BETWEEN',
            'left' => ['function' => 'cast_integer', 'column' => 'option_value'],
            'lower' => 1,
            'upper' => 999999,
        ], [], ['option_value', 'autoload']);
        $t->same(true, $plan['covering']);
        $t->same(64, $plan['estimatedRows']);
    },
    'index partial covering corpus marks integer cast non covering when option name needed' => static function (TestRunner $t) use ($indexes): void {
        $plan = SQLiteSelectExpressionIndexPlan::chooseLowestCost($indexes(), [
            'operator' => 'BETWEEN',
            'left' => ['function' => 'cast_integer', 'column' => 'option_value'],
            'lower' => 1,
            'upper' => 999999,
        ], [], ['option_name']);
        $t->same(false, $plan['covering']);
    },
    'index partial covering corpus rejects text values for integer cast partial index' => static function (TestRunner $t) use ($indexes): void {
        $plans = SQLiteSelectExpressionIndexPlan::rankedPlans($indexes(), [
            'operator' => '=',
            'left' => ['function' => 'cast_integer', 'column' => 'option_value'],
            'right' => '60000',
        ]);
        $t->same([], $plans);
    },
    'index partial covering corpus accepts zero integer value as non null' => static function (TestRunner $t) use ($indexes): void {
        $plan = SQLiteSelectExpressionIndexPlan::chooseLowestCost($indexes(), [
            'operator' => '=',
            'left' => ['function' => 'cast_integer', 'column' => 'option_value'],
            'right' => 0,
        ]);
        $t->same('idx_value_int_partial', $plan['name']);
        $t->same(0, $plan['values']);
    },
    'index partial covering corpus rejects null equality for partial lower index' => static function (TestRunner $t) use ($indexes, $lowerPoint): void {
        $plans = SQLiteSelectExpressionIndexPlan::rankedPlans($indexes(), $lowerPoint(null));
        $t->same([], $plans);
    },
    'index partial covering corpus ignores unrelated lower expression column' => static function (TestRunner $t) use ($indexes): void {
        $plans = SQLiteSelectExpressionIndexPlan::rankedPlans($indexes(), [
            'operator' => '=',
            'left' => ['function' => 'lower', 'column' => 'option_value'],
            'right' => 'siteurl',
        ]);
        $t->same([], $plans);
    },
    'index partial covering corpus ignores unsupported expression function' => static function (TestRunner $t) use ($indexes): void {
        $plans = SQLiteSelectExpressionIndexPlan::rankedPlans($indexes(), [
            'operator' => '=',
            'left' => ['function' => 'substr', 'column' => 'option_name'],
            'right' => 'site',
        ]);
        $t->same([], $plans);
    },
    'index partial covering corpus ignores unsupported like predicate' => static function (TestRunner $t) use ($indexes): void {
        $plans = SQLiteSelectExpressionIndexPlan::rankedPlans($indexes(), [
            'operator' => 'LIKE',
            'left' => ['function' => 'lower', 'column' => 'option_name'],
            'right' => 'site%',
        ]);
        $t->same([], $plans);
    },
    'index partial covering corpus preserves residual predicate marker for point scan' => static function (TestRunner $t) use ($indexes, $lowerPoint): void {
        $plan = SQLiteSelectExpressionIndexPlan::chooseLowestCost($indexes(), $lowerPoint());
        $t->same(true, $plan['residualPredicateRequired']);
    },
    'index partial covering corpus preserves residual predicate marker for range scan' => static function (TestRunner $t) use ($indexes, $upperRange): void {
        $plan = SQLiteSelectExpressionIndexPlan::chooseLowestCost($indexes(), $upperRange());
        $t->same(true, $plan['residualPredicateRequired']);
    },
    'index partial covering corpus orders equal cost plans by estimated rows' => static function (TestRunner $t) use ($lowerPoint): void {
        $plans = SQLiteSelectExpressionIndexPlan::rankedPlans([
            ['name' => 'idx_big', 'estimatedRows' => 1000, 'sql' => 'CREATE INDEX idx_big ON wp_options(lower(option_name)) WHERE option_name IS NOT NULL'],
            ['name' => 'idx_small', 'estimatedRows' => 100, 'sql' => 'CREATE INDEX idx_small ON wp_options(lower(option_name)) WHERE option_name IS NOT NULL'],
        ], $lowerPoint());
        $t->same(['idx_small', 'idx_big'], array_column($plans, 'name'));
    },
    'index partial covering corpus orders same cost plans by index name' => static function (TestRunner $t) use ($lowerPoint): void {
        $plans = SQLiteSelectExpressionIndexPlan::rankedPlans([
            ['name' => 'idx_b', 'estimatedRows' => 100, 'sql' => 'CREATE INDEX idx_b ON wp_options(lower(option_name)) WHERE option_name IS NOT NULL'],
            ['name' => 'idx_a', 'estimatedRows' => 100, 'sql' => 'CREATE INDEX idx_a ON wp_options(lower(option_name)) WHERE option_name IS NOT NULL'],
        ], $lowerPoint());
        $t->same(['idx_a', 'idx_b'], array_column($plans, 'name'));
    },
    'index partial covering corpus validates covering column list' => static function (TestRunner $t) use ($lowerPoint): void {
        $t->throws(InvalidArgumentException::class, static fn () => SQLiteSelectExpressionIndexPlan::chooseLowestCost([
            ['name' => 'bad', 'coveringColumns' => ['option_name', ''], 'sql' => 'CREATE INDEX bad ON wp_options(lower(option_name)) WHERE option_name IS NOT NULL'],
        ], $lowerPoint(), [], ['option_name']));
    },
    'index partial covering corpus validates requested covering columns' => static function (TestRunner $t) use ($indexes, $lowerPoint): void {
        $t->throws(InvalidArgumentException::class, static fn () => SQLiteSelectExpressionIndexPlan::chooseLowestCost($indexes(), $lowerPoint(), [], ['']));
    },
    'index partial covering corpus validates order direction' => static function (TestRunner $t) use ($indexes, $lowerPoint): void {
        $t->throws(InvalidArgumentException::class, static fn () => SQLiteSelectExpressionIndexPlan::chooseLowestCost($indexes(), $lowerPoint(), [['column' => 'option_name', 'direction' => 'SIDEWAYS']]));
    },
];

return $tests;

<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteMultiColumnRangePlan;

$point = static fn (string $column, mixed $value): array => ['operator' => '=', 'left' => ['column' => $column], 'right' => $value];
$range = static fn (string $column, string $operator, mixed $value): array => ['operator' => $operator, 'left' => ['column' => $column], 'right' => $value];
$reverseRange = static fn (mixed $value, string $operator, string $column): array => ['operator' => $operator, 'left' => $value, 'right' => ['column' => $column]];
$between = static fn (string $column, mixed $lower, mixed $upper): array => ['operator' => 'BETWEEN', 'left' => ['column' => $column], 'lower' => $lower, 'upper' => $upper];
$and = static fn (array ...$terms): array => ['operator' => 'AND', 'terms' => $terms];

$indexes = static fn (): array => [
    [
        'name' => 'idx_blog_name_autoload',
        'rootPage' => 81,
        'estimatedRows' => 20000,
        'sql' => 'CREATE INDEX idx_blog_name_autoload ON wp_options(blog_id, option_name, autoload)',
    ],
    [
        'name' => 'idx_blog_autoload_name',
        'rootPage' => 82,
        'estimatedRows' => 12000,
        'sql' => 'CREATE INDEX idx_blog_autoload_name ON wp_options(blog_id, autoload, option_name)',
    ],
    [
        'name' => 'idx_autoload_name_value',
        'rootPage' => 83,
        'estimatedRows' => 9000,
        'sql' => 'CREATE INDEX idx_autoload_name_value ON wp_options(autoload, option_name, option_value)',
    ],
    [
        'name' => 'idx_blog_name_desc',
        'rootPage' => 84,
        'estimatedRows' => 20000,
        'sql' => 'CREATE INDEX idx_blog_name_desc ON wp_options(blog_id, option_name DESC, autoload)',
    ],
];

$predicate = static fn () => $and(
    ['operator' => '>=', 'left' => ['column' => 'option_name'], 'right' => '_transient_'],
    ['operator' => '<', 'left' => ['column' => 'autoload'], 'right' => 'z'],
    ['operator' => '=', 'left' => ['column' => 'blog_id'], 'right' => 1],
);

$tests = [
    'planner multicolumn range current next25 chooses current range after equality prefix' => static function (TestRunner $t) use ($indexes, $predicate): void {
        $plan = SQLiteMultiColumnRangePlan::choose([$indexes()[0]], $predicate());
        $t->same('idx_blog_name_autoload', $plan['name']);
        $t->same(['blog_id', 'option_name'], $plan['usedColumns']);
        $t->same('option_name', $plan['rangeColumn']);
        $t->same(['autoload'], $plan['residualRangeColumns']);
    },
    'planner multicolumn range current next25 keeps next range residual after first range' => static function (TestRunner $t) use ($indexes, $predicate): void {
        $plan = SQLiteMultiColumnRangePlan::choose([$indexes()[0]], $predicate());
        $t->same(true, $plan['residualPredicateRequired']);
        $t->same('range-<', $plan['residualConstraints'][0]['operator']);
        $t->same('z', $plan['residualConstraints'][0]['values']);
    },
    'planner multicolumn range current next25 preserves selected root page' => static function (TestRunner $t) use ($indexes, $predicate): void {
        $plan = SQLiteMultiColumnRangePlan::choose([$indexes()[0]], $predicate());
        $t->same(81, $plan['rootPage']);
        $t->same(1, $plan['equalityPrefix']);
    },
    'planner multicolumn range current next25 uses alternate index when it can move autoload into equality prefix' => static function (TestRunner $t) use ($indexes, $point, $range, $and): void {
        $plan = SQLiteMultiColumnRangePlan::choose(
            $indexes(),
            $and($point('blog_id', 1), $point('autoload', 'yes'), $range('option_name', '>=', '_transient_'))
        );
        $t->same('idx_blog_autoload_name', $plan['name']);
        $t->same(['blog_id', 'autoload', 'option_name'], $plan['usedColumns']);
    },
    'planner multicolumn range current next25 estimates equality plus current range only' => static function (TestRunner $t) use ($indexes, $predicate): void {
        $plan = SQLiteMultiColumnRangePlan::choose([$indexes()[0]], $predicate());
        $t->same(400, $plan['estimatedRows']);
    },
    'planner multicolumn range current next25 does not reduce estimate for next residual range' => static function (TestRunner $t) use ($indexes, $point, $range, $and): void {
        $withResidual = SQLiteMultiColumnRangePlan::choose([$indexes()[0]], $and($point('blog_id', 1), $range('option_name', '>=', '_'), $range('autoload', '<', 'z')));
        $withoutResidual = SQLiteMultiColumnRangePlan::choose([$indexes()[0]], $and($point('blog_id', 1), $range('option_name', '>=', '_')));
        $t->same($withoutResidual['estimatedRows'], $withResidual['estimatedRows']);
    },
    'planner multicolumn range current next25 chooses lower cost longer equality prefix' => static function (TestRunner $t) use ($indexes, $point, $range, $and): void {
        $plans = SQLiteMultiColumnRangePlan::rankedPlans($indexes(), $and($point('blog_id', 1), $point('autoload', 'yes'), $range('option_name', '>=', '_')));
        $t->same('idx_blog_autoload_name', $plans[0]['name']);
        $t->same(20, $plans[0]['estimatedRows']);
    },
    'planner multicolumn range current next25 keeps next equality unusable after current range' => static function (TestRunner $t) use ($indexes, $point, $range, $and): void {
        $plan = SQLiteMultiColumnRangePlan::choose([$indexes()[0]], $and($point('blog_id', 1), $range('option_name', '>=', '_'), $point('autoload', 'yes')));
        $t->same(['blog_id', 'option_name'], $plan['usedColumns']);
        $t->same([], $plan['residualRangeColumns']);
    },
    'planner multicolumn range current next25 accepts between as current range' => static function (TestRunner $t) use ($indexes, $point, $between, $and): void {
        $plan = SQLiteMultiColumnRangePlan::choose([$indexes()[0]], $and($point('blog_id', 1), $between('option_name', '_site_', '_transient_')));
        $t->same('BETWEEN', $plan['rangeConstraint']['operator']);
        $t->same(['lower' => '_site_', 'upper' => '_transient_'], $plan['rangeConstraint']['values']);
    },
    'planner multicolumn range current next25 accepts reversed lower operand' => static function (TestRunner $t) use ($indexes, $point, $reverseRange, $and): void {
        $plan = SQLiteMultiColumnRangePlan::choose([$indexes()[0]], $and($point('blog_id', 1), $reverseRange('_transient_', '<=', 'option_name')));
        $t->same('range->=', $plan['rangeConstraint']['operator']);
        $t->same('_transient_', $plan['rangeConstraint']['values']);
    },
    'planner multicolumn range current next25 accepts reversed upper operand' => static function (TestRunner $t) use ($indexes, $point, $reverseRange, $and): void {
        $plan = SQLiteMultiColumnRangePlan::choose([$indexes()[0]], $and($point('blog_id', 1), $reverseRange('theme_', '>', 'option_name')));
        $t->same('range-<', $plan['rangeConstraint']['operator']);
    },
    'planner multicolumn range current next25 treats non null in list as equality prefix' => static function (TestRunner $t) use ($indexes, $range, $and): void {
        $plan = SQLiteMultiColumnRangePlan::choose([$indexes()[0]], $and(['operator' => 'IN', 'left' => ['column' => 'blog_id'], 'values' => [1, 2, null]], $range('option_name', '>=', '_')));
        $t->same(1, $plan['equalityPrefix']);
        $t->same(['blog_id', 'option_name'], $plan['usedColumns']);
    },
    'planner multicolumn range current next25 rejects all null in list prefix' => static function (TestRunner $t) use ($indexes, $range, $and): void {
        $plans = SQLiteMultiColumnRangePlan::rankedPlans([$indexes()[0]], $and(['operator' => 'IN', 'left' => ['column' => 'blog_id'], 'values' => [null]], $range('option_name', '>=', '_')));
        $t->same([], $plans);
    },
    'planner multicolumn range current next25 rejects missing leading equality before second column range' => static function (TestRunner $t) use ($indexes, $range): void {
        $plans = SQLiteMultiColumnRangePlan::rankedPlans([$indexes()[0]], $range('option_name', '>=', '_'));
        $t->same([], $plans);
    },
    'planner multicolumn range current next25 rejects point-only lookup because no range is current' => static function (TestRunner $t) use ($indexes, $point, $and): void {
        $plans = SQLiteMultiColumnRangePlan::rankedPlans([$indexes()[0]], $and($point('blog_id', 1), $point('option_name', 'siteurl')));
        $t->same([], $plans);
    },
    'planner multicolumn range current next25 rejects unsupported expression index' => static function (TestRunner $t) use ($point, $range, $and): void {
        $plans = SQLiteMultiColumnRangePlan::rankedPlans([
            ['name' => 'idx_expr', 'sql' => 'CREATE INDEX idx_expr ON wp_options(lower(option_name), autoload)'],
        ], $and($point('blog_id', 1), $range('option_name', '>=', '_')));
        $t->same([], $plans);
    },
    'planner multicolumn range current next25 reports order by satisfied on current range column' => static function (TestRunner $t) use ($indexes, $point, $range, $and): void {
        $plan = SQLiteMultiColumnRangePlan::choose([$indexes()[0]], $and($point('blog_id', 1), $range('option_name', '>=', '_')), [['column' => 'option_name']]);
        $t->same(true, $plan['orderBySatisfied']);
    },
    'planner multicolumn range current next25 rejects order by on next residual column alone' => static function (TestRunner $t) use ($indexes, $predicate): void {
        $plan = SQLiteMultiColumnRangePlan::choose([$indexes()[0]], $predicate(), [['column' => 'autoload']]);
        $t->same(false, $plan['orderBySatisfied']);
    },
    'planner multicolumn range current next25 honors descending range column order' => static function (TestRunner $t) use ($indexes, $point, $range, $and): void {
        $plan = SQLiteMultiColumnRangePlan::choose([$indexes()[3]], $and($point('blog_id', 1), $range('option_name', '>=', '_')), [['column' => 'option_name', 'direction' => 'DESC']]);
        $t->same(true, $plan['orderBySatisfied']);
        $t->same('idx_blog_name_desc', $plan['name']);
    },
    'planner multicolumn range current next25 rejects opposite descending range order' => static function (TestRunner $t) use ($indexes, $point, $range, $and): void {
        $plan = SQLiteMultiColumnRangePlan::choose([$indexes()[3]], $and($point('blog_id', 1), $range('option_name', '>=', '_')), [['column' => 'option_name', 'direction' => 'ASC']]);
        $t->same(false, $plan['orderBySatisfied']);
    },
    'planner multicolumn range current next25 ranks order-compatible plan ahead when estimates tie' => static function (TestRunner $t) use ($point, $range, $and): void {
        $plans = SQLiteMultiColumnRangePlan::rankedPlans([
            ['name' => 'idx_asc', 'estimatedRows' => 1000, 'sql' => 'CREATE INDEX idx_asc ON wp_options(blog_id, option_name)'],
            ['name' => 'idx_desc', 'estimatedRows' => 1000, 'sql' => 'CREATE INDEX idx_desc ON wp_options(blog_id, option_name DESC)'],
        ], $and($point('blog_id', 1), $range('option_name', '>=', '_')), [['column' => 'option_name', 'direction' => 'DESC']]);
        $t->same('idx_desc', $plans[0]['name']);
    },
    'planner multicolumn range current next25 ranks same cost plans by name' => static function (TestRunner $t) use ($point, $range, $and): void {
        $plans = SQLiteMultiColumnRangePlan::rankedPlans([
            ['name' => 'idx_z', 'estimatedRows' => 1000, 'sql' => 'CREATE INDEX idx_z ON wp_options(blog_id, option_name)'],
            ['name' => 'idx_a', 'estimatedRows' => 1000, 'sql' => 'CREATE INDEX idx_a ON wp_options(blog_id, option_name)'],
        ], $and($point('blog_id', 1), $range('option_name', '>=', '_')));
        $t->same(['idx_a', 'idx_z'], array_column($plans, 'name'));
    },
    'planner multicolumn range current next25 validates create index sql' => static function (TestRunner $t) use ($predicate): void {
        $t->throws(InvalidArgumentException::class, static fn () => SQLiteMultiColumnRangePlan::rankedPlans([['name' => 'bad']], $predicate()));
    },
    'planner multicolumn range current next25 validates estimated rows' => static function (TestRunner $t) use ($point, $range, $and): void {
        $t->throws(InvalidArgumentException::class, static fn () => SQLiteMultiColumnRangePlan::rankedPlans([
            ['name' => 'bad', 'estimatedRows' => 0, 'sql' => 'CREATE INDEX bad ON wp_options(blog_id, option_name)'],
        ], $and($point('blog_id', 1), $range('option_name', '>=', '_'))));
    },
    'planner multicolumn range current next25 validates order direction' => static function (TestRunner $t) use ($indexes, $point, $range, $and): void {
        $t->throws(InvalidArgumentException::class, static fn () => SQLiteMultiColumnRangePlan::rankedPlans([$indexes()[0]], $and($point('blog_id', 1), $range('option_name', '>=', '_')), [['column' => 'option_name', 'direction' => 'SIDEWAYS']]));
    },
    'planner multicolumn range current next25 validates scalar range literal' => static function (TestRunner $t) use ($indexes, $point, $and): void {
        $t->throws(InvalidArgumentException::class, static fn () => SQLiteMultiColumnRangePlan::rankedPlans([$indexes()[0]], $and($point('blog_id', 1), ['operator' => '>=', 'left' => ['column' => 'option_name'], 'right' => ['bad']])));
    },
];

$rangeVariants = [
    ['>=', '_site_', 'range->='],
    ['>', '_site_', 'range->'],
    ['<', 'theme_', 'range-<'],
    ['<=', 'theme_', 'range-<='],
];

foreach ($rangeVariants as [$operator, $value, $expectedOperator]) {
    $tests["planner multicolumn range current next25 current operator {$operator} remains bounded"] = static function (TestRunner $t) use ($indexes, $point, $range, $and, $operator, $value, $expectedOperator): void {
        $plan = SQLiteMultiColumnRangePlan::choose([$indexes()[0]], $and($point('blog_id', 1), $range('option_name', $operator, $value), $range('autoload', '<', 'z')));
        $t->same($expectedOperator, $plan['rangeConstraint']['operator']);
        $t->same('option_name', $plan['rangeColumn']);
        $t->same(['autoload'], $plan['residualRangeColumns']);
    };
}

$nextRangeVariants = [
    ['<', 'z', 'range-<'],
    ['<=', 'yes', 'range-<='],
    ['>', 'no', 'range->'],
    ['>=', 'no', 'range->='],
];

foreach ($nextRangeVariants as [$operator, $value, $expectedOperator]) {
    $tests["planner multicolumn range current next25 next operator {$operator} stays residual"] = static function (TestRunner $t) use ($indexes, $point, $range, $and, $operator, $value, $expectedOperator): void {
        $plan = SQLiteMultiColumnRangePlan::choose([$indexes()[0]], $and($point('blog_id', 1), $range('option_name', '>=', '_'), $range('autoload', $operator, $value)));
        $t->same([$expectedOperator], array_column($plan['residualConstraints'], 'operator'));
        $t->same(true, $plan['residualPredicateRequired']);
    };
}

$equalityPrefixes = [
    ['blog_id', 1, 'option_name', 'idx_blog_name_autoload'],
    ['autoload', 'yes', 'option_name', 'idx_autoload_name_value'],
];

foreach ($equalityPrefixes as [$equalityColumn, $equalityValue, $rangeColumn, $expectedIndex]) {
    $tests["planner multicolumn range current next25 equality {$equalityColumn} selects {$expectedIndex}"] = static function (TestRunner $t) use ($indexes, $point, $range, $and, $equalityColumn, $equalityValue, $rangeColumn, $expectedIndex): void {
        $plan = SQLiteMultiColumnRangePlan::choose($indexes(), $and($point($equalityColumn, $equalityValue), $range($rangeColumn, '>=', '_')));
        $t->same($expectedIndex, $plan['name']);
        $t->same($rangeColumn, $plan['rangeColumn']);
    };
}

$betweenResiduals = [
    ['autoload', 'no', 'yes'],
    ['autoload', 'a', 'z'],
    ['autoload', '', 'settings'],
    ['autoload', '_site_', '_transient_'],
    ['autoload', '0', 'z'],
];

foreach ($betweenResiduals as $offset => [$residualColumn, $lower, $upper]) {
    $tests["planner multicolumn range current next25 between residual {$residualColumn} variant {$offset} is not a second interval"] = static function (TestRunner $t) use ($indexes, $point, $range, $between, $and, $residualColumn, $lower, $upper): void {
        $plan = SQLiteMultiColumnRangePlan::choose([$indexes()[0], $indexes()[2]], $and($point('blog_id', 1), $range('option_name', '>=', '_'), $between($residualColumn, $lower, $upper)));
        $t->same('option_name', $plan['rangeColumn']);
        $t->same([$residualColumn], $plan['residualRangeColumns']);
        $t->same('BETWEEN', $plan['residualConstraints'][0]['operator']);
    };
}

return $tests;

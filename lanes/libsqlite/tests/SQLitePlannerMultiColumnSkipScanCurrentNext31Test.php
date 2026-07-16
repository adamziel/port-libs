<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteMultiColumnRangePlan;

$point = static fn (string $column, mixed $value): array => ['operator' => '=', 'left' => ['column' => $column], 'right' => $value];
$range = static fn (string $column, string $operator, mixed $value): array => ['operator' => $operator, 'left' => ['column' => $column], 'right' => $value];
$between = static fn (string $column, mixed $lower, mixed $upper): array => ['operator' => 'BETWEEN', 'left' => ['column' => $column], 'lower' => $lower, 'upper' => $upper];
$and = static fn (array ...$terms): array => ['operator' => 'AND', 'terms' => $terms];

$indexes = static fn (): array => [
    [
        'name' => 'idx_autoload_blog_name',
        'rootPage' => 131,
        'estimatedRows' => 18000,
        'distinctValues' => ['autoload' => 3],
        'sql' => 'CREATE INDEX idx_autoload_blog_name ON wp_options(autoload, blog_id, option_name, option_value)',
    ],
    [
        'name' => 'idx_site_autoload_blog_name',
        'rootPage' => 132,
        'estimatedRows' => 36000,
        'distinctValues' => ['site_id' => 2, 'autoload' => 3],
        'sql' => 'CREATE INDEX idx_site_autoload_blog_name ON wp_options(site_id, autoload, blog_id, option_name)',
    ],
    [
        'name' => 'idx_blog_name',
        'rootPage' => 133,
        'estimatedRows' => 10000,
        'sql' => 'CREATE INDEX idx_blog_name ON wp_options(blog_id, option_name)',
    ],
    [
        'name' => 'idx_autoload_blog_name_desc',
        'rootPage' => 134,
        'estimatedRows' => 18000,
        'distinctValues' => ['autoload' => 3],
        'sql' => 'CREATE INDEX idx_autoload_blog_name_desc ON wp_options(autoload, blog_id, option_name DESC)',
    ],
];

$skipPredicate = static fn () => $and(
    $point('blog_id', 1),
    $range('option_name', '>=', '_transient_'),
    $range('option_value', '<', 'z'),
);

$tests = [
    'planner multicolumn skipscan current next31 chooses skipped leading column plan' => static function (TestRunner $t) use ($indexes, $skipPredicate): void {
        $plan = SQLiteMultiColumnRangePlan::choose([$indexes()[0]], $skipPredicate());
        $t->same('idx_autoload_blog_name', $plan['name']);
    },
    'planner multicolumn skipscan current next31 records skip scan flag' => static function (TestRunner $t) use ($indexes, $skipPredicate): void {
        $t->same(true, SQLiteMultiColumnRangePlan::choose([$indexes()[0]], $skipPredicate())['usesSkipScan']);
    },
    'planner multicolumn skipscan current next31 records skipped autoload column' => static function (TestRunner $t) use ($indexes, $skipPredicate): void {
        $t->same(['autoload'], SQLiteMultiColumnRangePlan::choose([$indexes()[0]], $skipPredicate())['skippedColumns']);
    },
    'planner multicolumn skipscan current next31 keeps blog equality as usable prefix' => static function (TestRunner $t) use ($indexes, $skipPredicate): void {
        $t->same(1, SQLiteMultiColumnRangePlan::choose([$indexes()[0]], $skipPredicate())['equalityPrefix']);
    },
    'planner multicolumn skipscan current next31 uses blog and option name columns' => static function (TestRunner $t) use ($indexes, $skipPredicate): void {
        $t->same(['blog_id', 'option_name'], SQLiteMultiColumnRangePlan::choose([$indexes()[0]], $skipPredicate())['usedColumns']);
    },
    'planner multicolumn skipscan current next31 makes option name current range' => static function (TestRunner $t) use ($indexes, $skipPredicate): void {
        $t->same('option_name', SQLiteMultiColumnRangePlan::choose([$indexes()[0]], $skipPredicate())['rangeColumn']);
    },
    'planner multicolumn skipscan current next31 keeps value range residual' => static function (TestRunner $t) use ($indexes, $skipPredicate): void {
        $t->same(['option_value'], SQLiteMultiColumnRangePlan::choose([$indexes()[0]], $skipPredicate())['residualRangeColumns']);
    },
    'planner multicolumn skipscan current next31 reports residual predicate required' => static function (TestRunner $t) use ($indexes, $skipPredicate): void {
        $t->same(true, SQLiteMultiColumnRangePlan::choose([$indexes()[0]], $skipPredicate())['residualPredicateRequired']);
    },
    'planner multicolumn skipscan current next31 keeps residual operator' => static function (TestRunner $t) use ($indexes, $skipPredicate): void {
        $t->same('range-<', SQLiteMultiColumnRangePlan::choose([$indexes()[0]], $skipPredicate())['residualConstraints'][0]['operator']);
    },
    'planner multicolumn skipscan current next31 keeps residual value' => static function (TestRunner $t) use ($indexes, $skipPredicate): void {
        $t->same('z', SQLiteMultiColumnRangePlan::choose([$indexes()[0]], $skipPredicate())['residualConstraints'][0]['values']);
    },
    'planner multicolumn skipscan current next31 carries root page' => static function (TestRunner $t) use ($indexes, $skipPredicate): void {
        $t->same(131, SQLiteMultiColumnRangePlan::choose([$indexes()[0]], $skipPredicate())['rootPage']);
    },
    'planner multicolumn skipscan current next31 records distinct prefix loops' => static function (TestRunner $t) use ($indexes, $skipPredicate): void {
        $t->same(3, SQLiteMultiColumnRangePlan::choose([$indexes()[0]], $skipPredicate())['skipScanLoops']);
    },
    'planner multicolumn skipscan current next31 records skip scan penalty' => static function (TestRunner $t) use ($indexes, $skipPredicate): void {
        $t->same(30, SQLiteMultiColumnRangePlan::choose([$indexes()[0]], $skipPredicate())['skipScanPenalty']);
    },
    'planner multicolumn skipscan current next31 records current index offset' => static function (TestRunner $t) use ($indexes, $skipPredicate): void {
        $t->same(2, SQLiteMultiColumnRangePlan::choose([$indexes()[0]], $skipPredicate())['currentIndexColumnOffset']);
    },
    'planner multicolumn skipscan current next31 estimates equality plus range per loop' => static function (TestRunner $t) use ($indexes, $skipPredicate): void {
        $t->same(360, SQLiteMultiColumnRangePlan::choose([$indexes()[0]], $skipPredicate())['estimatedRows']);
    },
    'planner multicolumn skipscan current next31 includes loop penalty in cost' => static function (TestRunner $t) use ($indexes, $skipPredicate): void {
        $t->same(423, SQLiteMultiColumnRangePlan::choose([$indexes()[0]], $skipPredicate())['estimatedCost']);
    },
    'planner multicolumn skipscan current next31 rejects suffix order alone' => static function (TestRunner $t) use ($indexes, $skipPredicate): void {
        $t->same(false, SQLiteMultiColumnRangePlan::choose([$indexes()[0]], $skipPredicate(), [['column' => 'option_name']])['orderBySatisfied']);
    },
    'planner multicolumn skipscan current next31 accepts full skipped prefix order' => static function (TestRunner $t) use ($indexes, $skipPredicate): void {
        $t->same(true, SQLiteMultiColumnRangePlan::choose([$indexes()[0]], $skipPredicate(), [['column' => 'autoload'], ['column' => 'blog_id'], ['column' => 'option_name']])['orderBySatisfied']);
    },
    'planner multicolumn skipscan current next31 honors descending suffix order' => static function (TestRunner $t) use ($indexes, $point, $range, $and): void {
        $plan = SQLiteMultiColumnRangePlan::choose([$indexes()[3]], $and($point('blog_id', 1), $range('option_name', '>=', '_')), [['column' => 'autoload'], ['column' => 'blog_id'], ['column' => 'option_name', 'direction' => 'DESC']]);
        $t->same(true, $plan['orderBySatisfied']);
    },
    'planner multicolumn skipscan current next31 rejects opposite descending suffix order' => static function (TestRunner $t) use ($indexes, $point, $range, $and): void {
        $plan = SQLiteMultiColumnRangePlan::choose([$indexes()[3]], $and($point('blog_id', 1), $range('option_name', '>=', '_')), [['column' => 'autoload'], ['column' => 'blog_id'], ['column' => 'option_name']]);
        $t->same(false, $plan['orderBySatisfied']);
    },
    'planner multicolumn skipscan current next31 prefers direct prefix index over skip scan' => static function (TestRunner $t) use ($indexes, $skipPredicate): void {
        $plan = SQLiteMultiColumnRangePlan::choose([$indexes()[0], $indexes()[2]], $skipPredicate());
        $t->same('idx_blog_name', $plan['name']);
    },
    'planner multicolumn skipscan current next31 ranked plans keeps skip scan second after direct prefix' => static function (TestRunner $t) use ($indexes, $skipPredicate): void {
        $plans = SQLiteMultiColumnRangePlan::rankedPlans([$indexes()[0], $indexes()[2]], $skipPredicate());
        $t->same(['idx_blog_name', 'idx_autoload_blog_name'], array_column($plans, 'name'));
    },
    'planner multicolumn skipscan current next31 rejects missing distinct evidence' => static function (TestRunner $t) use ($point, $range, $and): void {
        $plans = SQLiteMultiColumnRangePlan::rankedPlans([
            ['name' => 'idx', 'sql' => 'CREATE INDEX idx ON wp_options(autoload, blog_id, option_name)'],
        ], $and($point('blog_id', 1), $range('option_name', '>=', '_')));
        $t->same([], $plans);
    },
    'planner multicolumn skipscan current next31 rejects single distinct prefix' => static function (TestRunner $t) use ($point, $range, $and): void {
        $plans = SQLiteMultiColumnRangePlan::rankedPlans([
            ['name' => 'idx', 'distinctValues' => ['autoload' => 1], 'sql' => 'CREATE INDEX idx ON wp_options(autoload, blog_id, option_name)'],
        ], $and($point('blog_id', 1), $range('option_name', '>=', '_')));
        $t->same([], $plans);
    },
    'planner multicolumn skipscan current next31 rejects no equality after skipped column' => static function (TestRunner $t) use ($indexes, $range): void {
        $plans = SQLiteMultiColumnRangePlan::rankedPlans([$indexes()[0]], $range('option_name', '>=', '_'));
        $t->same([], $plans);
    },
    'planner multicolumn skipscan current next31 rejects point only after skipped column' => static function (TestRunner $t) use ($indexes, $point, $and): void {
        $plans = SQLiteMultiColumnRangePlan::rankedPlans([$indexes()[0]], $and($point('blog_id', 1), $point('option_name', 'siteurl')));
        $t->same([], $plans);
    },
    'planner multicolumn skipscan current next31 accepts IN equality after skipped column' => static function (TestRunner $t) use ($indexes, $range, $and): void {
        $plan = SQLiteMultiColumnRangePlan::choose([$indexes()[0]], $and(['operator' => 'IN', 'left' => ['column' => 'blog_id'], 'values' => [1, 2, null]], $range('option_name', '>=', '_')));
        $t->same(['blog_id', 'option_name'], $plan['usedColumns']);
    },
    'planner multicolumn skipscan current next31 accepts between current range' => static function (TestRunner $t) use ($indexes, $point, $between, $and): void {
        $plan = SQLiteMultiColumnRangePlan::choose([$indexes()[0]], $and($point('blog_id', 1), $between('option_name', '_site_', '_transient_')));
        $t->same('BETWEEN', $plan['rangeConstraint']['operator']);
    },
    'planner multicolumn skipscan current next31 keeps between bounds' => static function (TestRunner $t) use ($indexes, $point, $between, $and): void {
        $plan = SQLiteMultiColumnRangePlan::choose([$indexes()[0]], $and($point('blog_id', 1), $between('option_name', '_site_', '_transient_')));
        $t->same(['lower' => '_site_', 'upper' => '_transient_'], $plan['rangeConstraint']['values']);
    },
    'planner multicolumn skipscan current next31 supports two skipped columns' => static function (TestRunner $t) use ($indexes, $point, $range, $and): void {
        $plan = SQLiteMultiColumnRangePlan::choose([$indexes()[1]], $and($point('blog_id', 1), $range('option_name', '>=', '_')));
        $t->same(['site_id', 'autoload'], $plan['skippedColumns']);
    },
    'planner multicolumn skipscan current next31 multiplies two skipped loop counts' => static function (TestRunner $t) use ($indexes, $point, $range, $and): void {
        $plan = SQLiteMultiColumnRangePlan::choose([$indexes()[1]], $and($point('blog_id', 1), $range('option_name', '>=', '_')));
        $t->same(6, $plan['skipScanLoops']);
    },
    'planner multicolumn skipscan current next31 adds deeper skip penalty' => static function (TestRunner $t) use ($indexes, $point, $range, $and): void {
        $plan = SQLiteMultiColumnRangePlan::choose([$indexes()[1]], $and($point('blog_id', 1), $range('option_name', '>=', '_')));
        $t->same(50, $plan['skipScanPenalty']);
    },
    'planner multicolumn skipscan current next31 estimates two skipped columns with loop multiplier' => static function (TestRunner $t) use ($indexes, $point, $range, $and): void {
        $plan = SQLiteMultiColumnRangePlan::choose([$indexes()[1]], $and($point('blog_id', 1), $range('option_name', '>=', '_')));
        $t->same(720, $plan['estimatedRows']);
    },
    'planner multicolumn skipscan current next31 rejects invalid distinct count' => static function (TestRunner $t) use ($point, $range, $and): void {
        $t->throws(InvalidArgumentException::class, static fn () => SQLiteMultiColumnRangePlan::rankedPlans([
            ['name' => 'bad', 'distinctValues' => ['autoload' => 0], 'sql' => 'CREATE INDEX bad ON wp_options(autoload, blog_id, option_name)'],
        ], $and($point('blog_id', 1), $range('option_name', '>=', '_'))));
    },
    'planner multicolumn skipscan current next31 rejects non integer distinct count' => static function (TestRunner $t) use ($point, $range, $and): void {
        $t->throws(InvalidArgumentException::class, static fn () => SQLiteMultiColumnRangePlan::rankedPlans([
            ['name' => 'bad', 'distinctValues' => ['autoload' => '3'], 'sql' => 'CREATE INDEX bad ON wp_options(autoload, blog_id, option_name)'],
        ], $and($point('blog_id', 1), $range('option_name', '>=', '_'))));
    },
    'planner multicolumn skipscan current next31 validates suffix order direction' => static function (TestRunner $t) use ($indexes, $skipPredicate): void {
        $t->throws(InvalidArgumentException::class, static fn () => SQLiteMultiColumnRangePlan::choose([$indexes()[0]], $skipPredicate(), [['column' => 'autoload'], ['column' => 'blog_id'], ['column' => 'option_name', 'direction' => 'SIDEWAYS']]));
    },
    'planner multicolumn skipscan current next31 validates suffix order column' => static function (TestRunner $t) use ($indexes, $skipPredicate): void {
        $t->throws(InvalidArgumentException::class, static fn () => SQLiteMultiColumnRangePlan::choose([$indexes()[0]], $skipPredicate(), [['column' => '']]));
    },
];

$currentRangeOperators = [
    ['>=', '_transient_', 'range->='],
    ['>', '_transient_', 'range->'],
    ['<', 'theme_', 'range-<'],
    ['<=', 'theme_', 'range-<='],
];

foreach ($currentRangeOperators as [$operator, $value, $expected]) {
    $tests["planner multicolumn skipscan current next31 current operator {$operator} remains bounded"] = static function (TestRunner $t) use ($indexes, $point, $range, $and, $operator, $value, $expected): void {
        $plan = SQLiteMultiColumnRangePlan::choose([$indexes()[0]], $and($point('blog_id', 1), $range('option_name', $operator, $value), $range('option_value', '<', 'z')));
        $t->same($expected, $plan['rangeConstraint']['operator']);
        $t->same(['option_value'], $plan['residualRangeColumns']);
    };
}

$residualRangeOperators = [
    ['<', 'z', 'range-<'],
    ['<=', 'yes', 'range-<='],
    ['>', 'no', 'range->'],
    ['>=', 'no', 'range->='],
];

foreach ($residualRangeOperators as [$operator, $value, $expected]) {
    $tests["planner multicolumn skipscan current next31 residual operator {$operator} stays next"] = static function (TestRunner $t) use ($indexes, $point, $range, $and, $operator, $value, $expected): void {
        $plan = SQLiteMultiColumnRangePlan::choose([$indexes()[0]], $and($point('blog_id', 1), $range('option_name', '>=', '_'), $range('option_value', $operator, $value)));
        $t->same([$expected], array_column($plan['residualConstraints'], 'operator'));
        $t->same(true, $plan['usesSkipScan']);
    };
}

$orderCases = [
    'autoload only' => [[['column' => 'autoload']], true],
    'autoload blog only' => [[['column' => 'autoload'], ['column' => 'blog_id']], true],
    'blog name suffix only' => [[['column' => 'blog_id'], ['column' => 'option_name']], false],
    'wrong leading skipped column' => [[['column' => 'site_id'], ['column' => 'blog_id'], ['column' => 'option_name']], false],
    'full index prefix' => [[['column' => 'autoload'], ['column' => 'blog_id'], ['column' => 'option_name']], true],
];

foreach ($orderCases as $label => [$orderBy, $expected]) {
    $tests["planner multicolumn skipscan current next31 order case {$label}"] = static function (TestRunner $t) use ($indexes, $skipPredicate, $orderBy, $expected): void {
        $plan = SQLiteMultiColumnRangePlan::choose([$indexes()[0]], $skipPredicate(), $orderBy);
        $t->same($expected, $plan['orderBySatisfied']);
    };
}

return $tests;

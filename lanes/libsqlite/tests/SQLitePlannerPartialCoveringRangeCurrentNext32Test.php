<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteMultiColumnRangePlan;

$point = static fn (string $column, mixed $value): array => ['operator' => '=', 'left' => ['column' => $column], 'right' => $value];
$range = static fn (string $column, string $operator, mixed $value): array => ['operator' => $operator, 'left' => ['column' => $column], 'right' => $value];
$between = static fn (string $column, mixed $lower, mixed $upper): array => ['operator' => 'BETWEEN', 'left' => ['column' => $column], 'lower' => $lower, 'upper' => $upper];
$inList = static fn (string $column, array $values): array => ['operator' => 'IN', 'left' => ['column' => $column], 'values' => $values];
$and = static fn (array ...$terms): array => ['operator' => 'AND', 'terms' => $terms];

$indexes = static fn (): array => [
    [
        'name' => 'idx_partial_autoload_name_value',
        'rootPage' => 110,
        'estimatedRows' => 2400,
        'sql' => "CREATE INDEX idx_partial_autoload_name_value ON wp_options(autoload, option_name, option_value) WHERE autoload = 'yes'",
    ],
    [
        'name' => 'idx_partial_plugin_name_value',
        'rootPage' => 111,
        'estimatedRows' => 900,
        'sql' => "CREATE INDEX idx_partial_plugin_name_value ON wp_options(autoload, option_name, option_value) WHERE option_name >= 'plugin_'",
    ],
    [
        'name' => 'idx_partial_nonnull_name',
        'rootPage' => 112,
        'estimatedRows' => 1400,
        'sql' => 'CREATE INDEX idx_partial_nonnull_name ON wp_options(option_name, autoload, option_value) WHERE option_name IS NOT NULL',
    ],
    [
        'name' => 'idx_full_autoload_name',
        'rootPage' => 113,
        'estimatedRows' => 6000,
        'sql' => 'CREATE INDEX idx_full_autoload_name ON wp_options(autoload, option_name)',
    ],
    [
        'name' => 'idx_partial_or_name_value',
        'rootPage' => 114,
        'estimatedRows' => 1200,
        'sql' => "CREATE INDEX idx_partial_or_name_value ON wp_options(autoload, option_name, option_value) WHERE autoload = 'yes' OR option_name >= 'plugin_'",
    ],
    [
        'name' => 'idx_partial_and_name_value',
        'rootPage' => 115,
        'estimatedRows' => 1200,
        'sql' => "CREATE INDEX idx_partial_and_name_value ON wp_options(autoload, option_name, option_value) WHERE autoload = 'yes' AND option_name >= 'plugin_'",
    ],
    [
        'name' => 'idx_partial_desc_name_value',
        'rootPage' => 116,
        'estimatedRows' => 1500,
        'sql' => "CREATE INDEX idx_partial_desc_name_value ON wp_options(autoload, option_name DESC, option_value) WHERE autoload = 'yes'",
    ],
];

$autoloadPluginRange = static fn () => $and(
    $point('autoload', 'yes'),
    $range('option_name', '>=', 'plugin_'),
    $range('option_value', '<', 'z')
);

$tests = [
    'planner partial covering range current next32 chooses proved partial covering index' => static function (TestRunner $t) use ($indexes, $autoloadPluginRange): void {
        $plan = SQLiteMultiColumnRangePlan::choose($indexes(), $autoloadPluginRange(), [], ['autoload', 'option_name', 'option_value']);
        $t->same('idx_partial_plugin_name_value', $plan['name']);
        $t->same(true, $plan['partial']);
        $t->same(true, $plan['covering']);
    },
    'planner partial covering range current next32 uses current range after equality prefix' => static function (TestRunner $t) use ($indexes, $autoloadPluginRange): void {
        $plan = SQLiteMultiColumnRangePlan::choose([$indexes()[0]], $autoloadPluginRange(), [], ['autoload', 'option_name']);
        $t->same(['autoload', 'option_name'], $plan['usedColumns']);
        $t->same('option_name', $plan['rangeColumn']);
        $t->same('range->=', $plan['rangeConstraint']['operator']);
    },
    'planner partial covering range current next32 leaves next range as residual predicate' => static function (TestRunner $t) use ($indexes, $autoloadPluginRange): void {
        $plan = SQLiteMultiColumnRangePlan::choose([$indexes()[0]], $autoloadPluginRange(), [], ['autoload', 'option_name']);
        $t->same(['option_value'], $plan['residualRangeColumns']);
        $t->same(true, $plan['residualPredicateRequired']);
        $t->same('range-<', $plan['residualConstraints'][0]['operator']);
    },
    'planner partial covering range current next32 reports non covering when payload column is missing' => static function (TestRunner $t) use ($indexes, $autoloadPluginRange): void {
        $plan = SQLiteMultiColumnRangePlan::choose([$indexes()[3]], $autoloadPluginRange(), [], ['autoload', 'option_name', 'option_value']);
        $t->same('idx_full_autoload_name', $plan['name']);
        $t->same(false, $plan['covering']);
        $t->same(false, $plan['partial']);
    },
    'planner partial covering range current next32 ranks partial covering before full fallback' => static function (TestRunner $t) use ($indexes, $autoloadPluginRange): void {
        $plans = SQLiteMultiColumnRangePlan::rankedPlans([$indexes()[0], $indexes()[3]], $autoloadPluginRange(), [], ['autoload', 'option_name', 'option_value']);
        $t->same(['idx_partial_autoload_name_value', 'idx_full_autoload_name'], array_column($plans, 'name'));
    },
    'planner partial covering range current next32 rejects unproved literal partial' => static function (TestRunner $t) use ($indexes, $range, $and): void {
        $plans = SQLiteMultiColumnRangePlan::rankedPlans([$indexes()[0]], $and($point = ['operator' => '=', 'left' => ['column' => 'autoload'], 'right' => 'no'], $range('option_name', '>=', 'plugin_')));
        $t->same([], $plans);
    },
    'planner partial covering range current next32 proves range partial from current range bound' => static function (TestRunner $t) use ($indexes, $point, $range, $and): void {
        $plan = SQLiteMultiColumnRangePlan::choose([$indexes()[1]], $and($point('autoload', 'yes'), $range('option_name', '>=', 'plugin_cache')));
        $t->same('idx_partial_plugin_name_value', $plan['name']);
        $t->same(true, $plan['partial']);
    },
    'planner partial covering range current next32 rejects broad range partial bound' => static function (TestRunner $t) use ($indexes, $point, $range, $and): void {
        $plans = SQLiteMultiColumnRangePlan::rankedPlans([$indexes()[1]], $and($point('autoload', 'yes'), $range('option_name', '>=', 'admin_')));
        $t->same([], $plans);
    },
    'planner partial covering range current next32 proves non null partial from range lookup' => static function (TestRunner $t) use ($indexes, $range): void {
        $plan = SQLiteMultiColumnRangePlan::choose([$indexes()[2]], $range('option_name', '>=', '_transient_'), [], ['option_name', 'autoload']);
        $t->same('idx_partial_nonnull_name', $plan['name']);
        $t->same(true, $plan['partial']);
    },
    'planner partial covering range current next32 preserves selected root page' => static function (TestRunner $t) use ($indexes, $autoloadPluginRange): void {
        $plan = SQLiteMultiColumnRangePlan::choose([$indexes()[0]], $autoloadPluginRange());
        $t->same(110, $plan['rootPage']);
    },
    'planner partial covering range current next32 satisfies order on current range column' => static function (TestRunner $t) use ($indexes, $autoloadPluginRange): void {
        $plan = SQLiteMultiColumnRangePlan::choose([$indexes()[0]], $autoloadPluginRange(), [['column' => 'option_name']]);
        $t->same(true, $plan['orderBySatisfied']);
    },
    'planner partial covering range current next32 rejects order on residual next column alone' => static function (TestRunner $t) use ($indexes, $autoloadPluginRange): void {
        $plan = SQLiteMultiColumnRangePlan::choose([$indexes()[0]], $autoloadPluginRange(), [['column' => 'option_value']]);
        $t->same(false, $plan['orderBySatisfied']);
    },
    'planner partial covering range current next32 honors descending partial range order' => static function (TestRunner $t) use ($indexes, $point, $range, $and): void {
        $plan = SQLiteMultiColumnRangePlan::choose([$indexes()[6]], $and($point('autoload', 'yes'), $range('option_name', '<=', 'plugin_z')), [['column' => 'option_name', 'direction' => 'DESC']]);
        $t->same('idx_partial_desc_name_value', $plan['name']);
        $t->same(true, $plan['orderBySatisfied']);
    },
    'planner partial covering range current next32 rejects opposite descending partial range order' => static function (TestRunner $t) use ($indexes, $point, $range, $and): void {
        $plan = SQLiteMultiColumnRangePlan::choose([$indexes()[6]], $and($point('autoload', 'yes'), $range('option_name', '<=', 'plugin_z')), [['column' => 'option_name', 'direction' => 'ASC']]);
        $t->same(false, $plan['orderBySatisfied']);
    },
    'planner partial covering range current next32 proves OR partial by literal branch' => static function (TestRunner $t) use ($indexes, $point, $range, $and): void {
        $plan = SQLiteMultiColumnRangePlan::choose([$indexes()[4]], $and($point('autoload', 'yes'), $range('option_name', '>=', 'admin_')));
        $t->same('idx_partial_or_name_value', $plan['name']);
        $t->same(true, $plan['partial']);
    },
    'planner partial covering range current next32 proves OR partial by range branch' => static function (TestRunner $t) use ($indexes, $point, $range, $and): void {
        $plan = SQLiteMultiColumnRangePlan::choose([$indexes()[4]], $and($point('autoload', 'no'), $range('option_name', '>=', 'plugin_mail')));
        $t->same('idx_partial_or_name_value', $plan['name']);
    },
    'planner partial covering range current next32 proves AND partial from both terms' => static function (TestRunner $t) use ($indexes, $point, $range, $and): void {
        $plan = SQLiteMultiColumnRangePlan::choose([$indexes()[5]], $and($point('autoload', 'yes'), $range('option_name', '>=', 'plugin_mail')));
        $t->same('idx_partial_and_name_value', $plan['name']);
    },
    'planner partial covering range current next32 rejects AND partial missing range proof' => static function (TestRunner $t) use ($indexes, $point, $range, $and): void {
        $plans = SQLiteMultiColumnRangePlan::rankedPlans([$indexes()[5]], $and($point('autoload', 'yes'), $range('option_name', '>=', 'admin_')));
        $t->same([], $plans);
    },
    'planner partial covering range current next32 accepts between as current partial proof' => static function (TestRunner $t) use ($indexes, $point, $between, $and): void {
        $plan = SQLiteMultiColumnRangePlan::choose([$indexes()[1]], $and($point('autoload', 'yes'), $between('option_name', 'plugin_a', 'plugin_z')));
        $t->same('BETWEEN', $plan['rangeConstraint']['operator']);
        $t->same(true, $plan['partial']);
    },
    'planner partial covering range current next32 rejects between below partial lower bound' => static function (TestRunner $t) use ($indexes, $point, $between, $and): void {
        $plans = SQLiteMultiColumnRangePlan::rankedPlans([$indexes()[1]], $and($point('autoload', 'yes'), $between('option_name', 'admin_a', 'plugin_z')));
        $t->same([], $plans);
    },
    'planner partial covering range current next32 treats IN equality prefix as partial proof' => static function (TestRunner $t) use ($indexes, $inList, $range, $and): void {
        $plan = SQLiteMultiColumnRangePlan::choose([$indexes()[0]], $and($inList('autoload', ['yes']), $range('option_name', '>=', 'plugin_')));
        $t->same(1, $plan['equalityPrefix']);
        $t->same(true, $plan['partial']);
    },
    'planner partial covering range current next32 rejects IN list that includes unproved partial value' => static function (TestRunner $t) use ($indexes, $inList, $range, $and): void {
        $plans = SQLiteMultiColumnRangePlan::rankedPlans([$indexes()[0]], $and($inList('autoload', ['yes', 'no']), $range('option_name', '>=', 'plugin_')));
        $t->same([], $plans);
    },
    'planner partial covering range current next32 validates needed column names' => static function (TestRunner $t) use ($indexes, $autoloadPluginRange): void {
        $t->throws(InvalidArgumentException::class, static fn () => SQLiteMultiColumnRangePlan::rankedPlans([$indexes()[0]], $autoloadPluginRange(), [], ['']));
    },
];

$residualOperators = [
    ['<', 'z', 'range-<'],
    ['<=', 'zz', 'range-<='],
    ['>', 'a', 'range->'],
    ['>=', 'aa', 'range->='],
];

foreach ($residualOperators as [$operator, $value, $expected]) {
    $tests["planner partial covering range current next32 residual operator {$operator} remains post seek"] = static function (TestRunner $t) use ($indexes, $point, $range, $and, $operator, $value, $expected): void {
        $plan = SQLiteMultiColumnRangePlan::choose([$indexes()[0]], $and($point('autoload', 'yes'), $range('option_name', '>=', 'plugin_'), $range('option_value', $operator, $value)));
        $t->same([$expected], array_column($plan['residualConstraints'], 'operator'));
        $t->same(['option_value'], $plan['residualRangeColumns']);
    };
}

$neededColumnSets = [
    [['autoload'], true],
    [['autoload', 'option_name'], true],
    [['autoload', 'option_name', 'option_value'], true],
    [['option_id'], false],
    [['autoload', 'option_id'], false],
];

foreach ($neededColumnSets as $offset => [$neededColumns, $expectedCovering]) {
    $tests["planner partial covering range current next32 covering set {$offset} is reported"] = static function (TestRunner $t) use ($indexes, $autoloadPluginRange, $neededColumns, $expectedCovering): void {
        $plan = SQLiteMultiColumnRangePlan::choose([$indexes()[0]], $autoloadPluginRange(), [], $neededColumns);
        $t->same($expectedCovering, $plan['covering']);
    };
}

$partialProofs = [
    ['plugin_cache', true],
    ['plugin_mail', true],
    ['plugin_z', true],
    ['option_', false],
    ['admin_', false],
];

foreach ($partialProofs as [$lowerBound, $expectedUsable]) {
    $tests["planner partial covering range current next32 lower bound {$lowerBound} partial proof"] = static function (TestRunner $t) use ($indexes, $point, $range, $and, $lowerBound, $expectedUsable): void {
        $plans = SQLiteMultiColumnRangePlan::rankedPlans([$indexes()[1]], $and($point('autoload', 'yes'), $range('option_name', '>=', $lowerBound)));
        $t->same($expectedUsable, $plans !== []);
    };
}

$rankingCases = [
    ['covering payload wins', ['autoload', 'option_name', 'option_value'], 'idx_partial_autoload_name_value'],
    ['non covering still usable', ['autoload', 'option_name', 'option_id'], 'idx_partial_autoload_name_value'],
    ['full fallback is available after partial', ['autoload', 'option_name'], 'idx_partial_autoload_name_value'],
];

foreach ($rankingCases as [$label, $neededColumns, $expectedFirst]) {
    $tests["planner partial covering range current next32 ranking {$label}"] = static function (TestRunner $t) use ($indexes, $autoloadPluginRange, $neededColumns, $expectedFirst): void {
        $plans = SQLiteMultiColumnRangePlan::rankedPlans([$indexes()[0], $indexes()[3]], $autoloadPluginRange(), [], $neededColumns);
        $t->same($expectedFirst, $plans[0]['name']);
        $t->same(['idx_partial_autoload_name_value', 'idx_full_autoload_name'], array_column($plans, 'name'));
    };
}

return $tests;

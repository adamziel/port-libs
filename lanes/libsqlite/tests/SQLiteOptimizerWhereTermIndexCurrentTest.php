<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteCoveringIndexPlan;
use PortLibs\LibSqlite\SQLiteMultiColumnRangePlan;

$isNull = static fn (string $column): array => ['operator' => 'IS NULL', 'left' => ['column' => $column]];
$isNotNull = static fn (string $column): array => ['operator' => 'IS NOT NULL', 'left' => ['column' => $column]];
$point = static fn (string $column, mixed $value): array => ['operator' => '=', 'left' => ['column' => $column], 'right' => $value];
$range = static fn (string $column, string $operator, mixed $value): array => ['operator' => $operator, 'left' => ['column' => $column], 'right' => $value];
$and = static fn (array ...$terms): array => ['operator' => 'AND', 'terms' => $terms];

$coveringIndexes = static fn (): array => [
    [
        'name' => 'idx_value_name_autoload',
        'rootPage' => 61,
        'estimatedRows' => 1200,
        'sql' => 'CREATE INDEX idx_value_name_autoload ON wp_options(option_value, option_name, autoload)',
        'stat4Samples' => [
            ['values' => ['option_value' => null, 'option_name' => 'empty_option'], 'rows' => 6],
            ['values' => ['option_value' => null, 'option_name' => 'missing_payload'], 'rows' => 3],
            ['values' => ['option_value' => 'a:1', 'option_name' => 'plugin_alpha'], 'rows' => 40],
        ],
    ],
    [
        'name' => 'idx_name_value_autoload',
        'rootPage' => 62,
        'estimatedRows' => 900,
        'sql' => 'CREATE INDEX idx_name_value_autoload ON wp_options(option_name, option_value, autoload)',
    ],
];

$rangeIndexes = static fn (): array => [
    [
        'name' => 'idx_value_name_autoload_range',
        'rootPage' => 71,
        'estimatedRows' => 2000,
        'sql' => 'CREATE INDEX idx_value_name_autoload_range ON wp_options(option_value, option_name, autoload)',
        'stat4Samples' => [
            ['neq' => [9, 2], 'nlt' => [0, 0], 'ndlt' => [0, 0], 'sample' => [null, 'empty_option', 'yes']],
            ['neq' => [9, 1], 'nlt' => [9, 2], 'ndlt' => [1, 1], 'sample' => [null, 'missing_payload', 'no']],
            ['neq' => [30, 4], 'nlt' => [18, 0], 'ndlt' => [2, 0], 'sample' => ['a:1', 'plugin_alpha', 'yes']],
            ['neq' => [25, 3], 'nlt' => [48, 4], 'ndlt' => [3, 2], 'sample' => ['a:2', 'plugin_beta', 'yes']],
        ],
    ],
];

$tests = [
    'optimizer where term index current covering uses is null as first index term' => static function (TestRunner $t) use ($coveringIndexes, $isNull): void {
        $plan = SQLiteCoveringIndexPlan::choose([$coveringIndexes()[0]], $isNull('option_value'), ['option_value', 'option_name']);
        $t->same('idx_value_name_autoload', $plan['name']);
        $t->same(['option_value'], $plan['usedColumns']);
    },
    'optimizer where term index current covering treats is null as equality prefix' => static function (TestRunner $t) use ($coveringIndexes, $isNull): void {
        $plan = SQLiteCoveringIndexPlan::choose([$coveringIndexes()[0]], $isNull('option_value'), ['option_value', 'option_name']);
        $t->same(1, $plan['equalityPrefix']);
        $t->same(null, $plan['rangeColumn']);
    },
    'optimizer where term index current covering remains covering for null term' => static function (TestRunner $t) use ($coveringIndexes, $isNull): void {
        $plan = SQLiteCoveringIndexPlan::choose([$coveringIndexes()[0]], $isNull('option_value'), ['option_value', 'option_name', 'autoload']);
        $t->same(true, $plan['covering']);
        $t->same(false, $plan['orderBySatisfied']);
    },
    'optimizer where term index current covering applies stat4 null samples' => static function (TestRunner $t) use ($coveringIndexes, $isNull): void {
        $plan = SQLiteCoveringIndexPlan::choose([$coveringIndexes()[0]], $isNull('option_value'), ['option_value', 'option_name'], [['column' => 'option_name']]);
        $t->same(true, $plan['stat4Used']);
        $t->same(2, $plan['stat4MatchedSamples']);
        $t->same(9, $plan['estimatedRows']);
    },
    'optimizer where term index current covering rejects equals null as indexable point' => static function (TestRunner $t) use ($coveringIndexes, $point): void {
        $plans = SQLiteCoveringIndexPlan::rankedPlans([$coveringIndexes()[0]], $point('option_value', null), ['option_value', 'option_name']);
        $t->same([], $plans);
    },
    'optimizer where term index current covering keeps is not null as range term' => static function (TestRunner $t) use ($coveringIndexes, $isNotNull): void {
        $plan = SQLiteCoveringIndexPlan::choose([$coveringIndexes()[0]], $isNotNull('option_value'), ['option_value', 'option_name']);
        $t->same('idx_value_name_autoload', $plan['name']);
        $t->same('option_value', $plan['rangeColumn']);
    },
    'optimizer where term index current covering uses is null before next range term' => static function (TestRunner $t) use ($coveringIndexes, $isNull, $range, $and): void {
        $plan = SQLiteCoveringIndexPlan::choose([$coveringIndexes()[0]], $and($isNull('option_value'), $range('option_name', '>=', 'empty_')), ['option_value', 'option_name']);
        $t->same(['option_value', 'option_name'], $plan['usedColumns']);
        $t->same('option_name', $plan['rangeColumn']);
    },
    'optimizer where term index current covering orders by suffix after null equality' => static function (TestRunner $t) use ($coveringIndexes, $isNull): void {
        $plan = SQLiteCoveringIndexPlan::choose([$coveringIndexes()[0]], $isNull('option_value'), ['option_value', 'option_name'], [['column' => 'option_name']]);
        $t->same(true, $plan['orderBySatisfied']);
        $t->same(1, $plan['equalityPrefix']);
    },
    'optimizer where term index current covering rejects order before suffix after null equality' => static function (TestRunner $t) use ($coveringIndexes, $isNull): void {
        $plan = SQLiteCoveringIndexPlan::choose([$coveringIndexes()[0]], $isNull('option_value'), ['option_value', 'option_name'], [['column' => 'autoload']]);
        $t->same(false, $plan['orderBySatisfied']);
    },
    'optimizer where term index current covering ranks null equality prefix ahead of later-column index' => static function (TestRunner $t) use ($coveringIndexes, $isNull): void {
        $plans = SQLiteCoveringIndexPlan::rankedPlans($coveringIndexes(), $isNull('option_value'), ['option_value', 'option_name']);
        $t->same('idx_value_name_autoload', $plans[0]['name']);
    },
    'optimizer where term index current multicolumn uses is null equality before range' => static function (TestRunner $t) use ($rangeIndexes, $isNull, $range, $and): void {
        $plan = SQLiteMultiColumnRangePlan::choose($rangeIndexes(), $and($isNull('option_value'), $range('option_name', '>=', 'empty_')));
        $t->same('idx_value_name_autoload_range', $plan['name']);
        $t->same(['option_value', 'option_name'], $plan['usedColumns']);
    },
    'optimizer where term index current multicolumn tracks null equality prefix' => static function (TestRunner $t) use ($rangeIndexes, $isNull, $range, $and): void {
        $plan = SQLiteMultiColumnRangePlan::choose($rangeIndexes(), $and($isNull('option_value'), $range('option_name', '>=', 'empty_')));
        $t->same(1, $plan['equalityPrefix']);
        $t->same('option_name', $plan['rangeColumn']);
    },
    'optimizer where term index current multicolumn applies stat4 after null prefix' => static function (TestRunner $t) use ($rangeIndexes, $isNull, $range, $and): void {
        $plan = SQLiteMultiColumnRangePlan::choose($rangeIndexes(), $and($isNull('option_value'), $range('option_name', '>=', 'empty_')));
        $t->same(true, $plan['stat4Used']);
        $t->same('option_name', $plan['stat4CurrentSourceColumn']);
    },
    'optimizer where term index current multicolumn rejects equals null prefix' => static function (TestRunner $t) use ($rangeIndexes, $point, $range, $and): void {
        $plans = SQLiteMultiColumnRangePlan::rankedPlans($rangeIndexes(), $and($point('option_value', null), $range('option_name', '>=', 'empty_')));
        $t->same([], $plans);
    },
    'optimizer where term index current multicolumn satisfies suffix order after null prefix' => static function (TestRunner $t) use ($rangeIndexes, $isNull, $range, $and): void {
        $plan = SQLiteMultiColumnRangePlan::choose($rangeIndexes(), $and($isNull('option_value'), $range('option_name', '>=', 'empty_')), [['column' => 'option_name']]);
        $t->same(true, $plan['orderBySatisfied']);
    },
    'optimizer where term index current multicolumn preserves null equality constraint operator' => static function (TestRunner $t) use ($rangeIndexes, $isNull, $range, $and): void {
        $plan = SQLiteMultiColumnRangePlan::choose($rangeIndexes(), $and($isNull('option_value'), $range('option_name', '>=', 'empty_')));
        $t->same('is-null', $plan['equalityConstraints'][0]['operator']);
        $t->same(null, $plan['equalityConstraints'][0]['values']);
    },
];

return $tests;

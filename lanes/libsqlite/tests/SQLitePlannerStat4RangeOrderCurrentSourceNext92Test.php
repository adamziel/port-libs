<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteMultiColumnRangePlan;

$point = static fn (string $column, mixed $value): array => ['operator' => '=', 'left' => ['column' => $column], 'right' => $value];
$range = static fn (string $column, string $operator, mixed $value): array => ['operator' => $operator, 'left' => ['column' => $column], 'right' => $value];
$between = static fn (string $column, mixed $lower, mixed $upper): array => ['operator' => 'BETWEEN', 'left' => ['column' => $column], 'lower' => $lower, 'upper' => $upper];
$and = static fn (array ...$terms): array => ['operator' => 'AND', 'terms' => $terms];

$stat4 = static fn (): array => [
    ['neq' => '5 1 1', 'nlt' => '0 0 0', 'ndlt' => '0 0 0', 'sample' => ['yes', 'active_plugins', 'core']],
    ['neq' => '8 2 1', 'nlt' => '5 1 1', 'ndlt' => '1 1 1', 'sample' => ['yes', 'autoloaded_widget', 'widget']],
    ['neq' => '7 2 1', 'nlt' => '13 3 2', 'ndlt' => '2 2 2', 'sample' => ['yes', 'plugin_alpha', 'plugin']],
    ['neq' => '4 1 1', 'nlt' => '20 5 3', 'ndlt' => '3 3 3', 'sample' => ['yes', 'plugin_beta', 'plugin']],
    ['neq' => '6 1 1', 'nlt' => '24 6 4', 'ndlt' => '4 4 4', 'sample' => ['yes', 'plugin_gamma', 'plugin']],
    ['neq' => '3 1 1', 'nlt' => '30 7 5', 'ndlt' => '5 5 5', 'sample' => ['yes', 'siteurl', 'core']],
    ['neq' => '10 3 1', 'nlt' => '33 8 6', 'ndlt' => '6 6 6', 'sample' => ['yes', 'theme_mods_twentysix', 'theme']],
    ['neq' => '9 2 1', 'nlt' => '43 11 7', 'ndlt' => '7 7 7', 'sample' => ['yes', 'transient_feed', 'transient']],
    ['neq' => '11 2 1', 'nlt' => '52 13 8', 'ndlt' => '8 8 8', 'sample' => ['yes', 'widget_recent', 'widget']],
    ['neq' => '6 2 1', 'nlt' => '63 15 9', 'ndlt' => '9 9 9', 'sample' => ['no', 'plugin_alpha', 'plugin']],
    ['neq' => '5 1 1', 'nlt' => '69 17 10', 'ndlt' => '10 10 10', 'sample' => ['no', 'transient_feed', 'transient']],
];

$indexes = static fn (): array => [
    [
        'name' => 'idx_wp_options_autoload_name_kind_stat4',
        'rootPage' => 9201,
        'estimatedRows' => 120,
        'stat4Samples' => $stat4(),
        'sql' => 'CREATE INDEX idx_wp_options_autoload_name_kind_stat4 ON wp_options(autoload, option_name, option_value)',
    ],
    [
        'name' => 'idx_wp_options_name_autoload_plain',
        'rootPage' => 9202,
        'estimatedRows' => 120,
        'sql' => 'CREATE INDEX idx_wp_options_name_autoload_plain ON wp_options(option_name, autoload)',
    ],
    [
        'name' => 'zz_idx_wp_options_autoload_name_covering',
        'rootPage' => 9203,
        'estimatedRows' => 160,
        'stat4Samples' => $stat4(),
        'sql' => 'CREATE INDEX idx_wp_options_autoload_name_covering ON wp_options(autoload, option_name, option_id, option_value)',
    ],
];

$plan = static fn (
    ?array $predicate = null,
    array $orderBy = [['column' => 'option_name']],
    array $neededColumns = ['option_name'],
): array => SQLiteMultiColumnRangePlan::stat4RangeOrder(
    $indexes(),
    $predicate ?? $and($point('autoload', 'yes'), $range('option_name', '>=', 'plugin_'), $range('option_name', '<', 'theme_')),
    $orderBy,
    $neededColumns,
);

$tests = [
    'planner stat4 range order current source next92 chooses usable plan' => static fn (TestRunner $t) => $t->same('usable', $plan()['status']),
    'planner stat4 range order current source next92 selects stat4 index' => static fn (TestRunner $t) => $t->same('idx_wp_options_autoload_name_kind_stat4', $plan()['selected']),
    'planner stat4 range order current source next92 keeps root page' => static fn (TestRunner $t) => $t->same(9201, $plan()['rootPage']),
    'planner stat4 range order current source next92 ranks all usable plans' => static fn (TestRunner $t) => $t->same(3, $plan()['rankedPlanCount']),
    'planner stat4 range order current source next92 reports rank order' => static fn (TestRunner $t) => $t->same(['idx_wp_options_autoload_name_kind_stat4', 'zz_idx_wp_options_autoload_name_covering', 'idx_wp_options_name_autoload_plain'], $plan()['rankedPlanNames']),
    'planner stat4 range order current source next92 satisfies order by' => static fn (TestRunner $t) => $t->same(true, $plan()['orderBySatisfied']),
    'planner stat4 range order current source next92 avoids block sort' => static fn (TestRunner $t) => $t->same(false, $plan()['blockSortRequired']),
    'planner stat4 range order current source next92 records mode' => static fn (TestRunner $t) => $t->same('range-current-source-order', $plan()['rangeOrderMode']),
    'planner stat4 range order current source next92 records current source column' => static fn (TestRunner $t) => $t->same('option_name', $plan()['currentSourceColumn']),
    'planner stat4 range order current source next92 records current source offset' => static fn (TestRunner $t) => $t->same(1, $plan()['currentSourceOffset']),
    'planner stat4 range order current source next92 records range column' => static fn (TestRunner $t) => $t->same('option_name', $plan()['rangeColumn']),
    'planner stat4 range order current source next92 uses stat4' => static fn (TestRunner $t) => $t->same(true, $plan()['stat4Used']),
    'planner stat4 range order current source next92 estimates stat4 rows' => static fn (TestRunner $t) => $t->same(5, $plan()['stat4Estimate']),
    'planner stat4 range order current source next92 estimates rows' => static fn (TestRunner $t) => $t->same(5, $plan()['estimatedRows']),
    'planner stat4 range order current source next92 estimates cost' => static fn (TestRunner $t) => $t->same(11, $plan()['estimatedCost']),
    'planner stat4 range order current source next92 counts matched samples' => static fn (TestRunner $t) => $t->same(4, $plan()['stat4MatchedSamples']),
    'planner stat4 range order current source next92 keeps lower key' => static fn (TestRunner $t) => $t->same('autoloaded_widget', $plan()['rangeCurrentSourceKeys']['lower']),
    'planner stat4 range order current source next92 keeps lower next key' => static fn (TestRunner $t) => $t->same('plugin_alpha', $plan()['rangeCurrentSourceKeys']['lowerNext']),
    'planner stat4 range order current source next92 keeps upper key' => static fn (TestRunner $t) => $t->same('siteurl', $plan()['rangeCurrentSourceKeys']['upper']),
    'planner stat4 range order current source next92 keeps upper next key' => static fn (TestRunner $t) => $t->same('theme_mods_twentysix', $plan()['rangeCurrentSourceKeys']['upperNext']),
    'planner stat4 range order current source next92 keeps matched source keys' => static fn (TestRunner $t) => $t->same(['plugin_alpha', 'plugin_beta', 'plugin_gamma', 'siteurl'], $plan()['matchedCurrentSourceKeys']),
    'planner stat4 range order current source next92 records lower inclusivity' => static fn (TestRunner $t) => $t->same(true, $plan()['stat4RangeCurrentNext']['lowerInclusive']),
    'planner stat4 range order current source next92 records upper exclusivity' => static fn (TestRunner $t) => $t->same(false, $plan()['stat4RangeCurrentNext']['upperInclusive']),
    'planner stat4 range order current source next92 records nonempty gap' => static fn (TestRunner $t) => $t->same(false, $plan()['stat4RangeCurrentNext']['emptyGap']),
    'planner stat4 range order current source next92 records order column' => static fn (TestRunner $t) => $t->same('option_name', $plan()['orderBy'][0]['column']),
    'planner stat4 range order current source next92 records order direction' => static fn (TestRunner $t) => $t->same('ASC', $plan()['orderBy'][0]['direction']),
    'planner stat4 range order current source next92 records order position' => static fn (TestRunner $t) => $t->same(1, $plan()['orderBy'][0]['position']),
    'planner stat4 range order current source next92 is covering option name' => static fn (TestRunner $t) => $t->same(true, $plan()['covering']),
    'planner stat4 range order current source next92 marks no partial index' => static fn (TestRunner $t) => $t->same(false, $plan()['partial']),
    'planner stat4 range order current source next92 records residual predicate' => static fn (TestRunner $t) => $t->same(false, $plan()['residualPredicateRequired']),
    'planner stat4 range order current source next92 is not skip scan' => static fn (TestRunner $t) => $t->same(false, $plan()['usesSkipScan']),
    'planner stat4 range order current source next92 detail uses current source' => static fn (TestRunner $t) => $t->same('SEARCH idx_wp_options_autoload_name_kind_stat4 (option_name RANGE) USING STAT4 ORDER BY CURRENT SOURCE COVERING', $plan()['detail']),
    'planner stat4 range order current source next92 exposes next alternative' => static fn (TestRunner $t) => $t->same('zz_idx_wp_options_autoload_name_covering', $plan()['nextAlternative']['name']),
    'planner stat4 range order current source next92 next alternative keeps rows' => static fn (TestRunner $t) => $t->same(5, $plan()['nextAlternative']['estimatedRows']),
    'planner stat4 range order current source next92 dependency closure' => static fn (TestRunner $t) => $t->same(true, str_contains($plan()['dependency_closure'], 'no new support component')),
];

$descending = static fn (): array => $plan(null, [['column' => 'option_name', 'direction' => 'DESC']]);
$tests['planner stat4 range order current source next92 desc requires temp btree'] = static fn (TestRunner $t) => $t->same(true, $descending()['blockSortRequired']);
$tests['planner stat4 range order current source next92 desc mode'] = static fn (TestRunner $t) => $t->same('temp-btree-order', $descending()['rangeOrderMode']);
$tests['planner stat4 range order current source next92 desc detail'] = static fn (TestRunner $t) => $t->same('SEARCH idx_wp_options_autoload_name_kind_stat4 (option_name RANGE) USING STAT4 USE TEMP B-TREE FOR ORDER BY COVERING', $descending()['detail']);
$tests['planner stat4 range order current source next92 desc order direction'] = static fn (TestRunner $t) => $t->same('DESC', $descending()['orderBy'][0]['direction']);

$betweenPlan = static fn (): array => $plan($and($point('autoload', 'yes'), $between('option_name', 'plugin_alpha', 'plugin_gamma')));
$tests['planner stat4 range order current source next92 between lower key'] = static fn (TestRunner $t) => $t->same('plugin_alpha', $betweenPlan()['rangeCurrentSourceKeys']['lower']);
$tests['planner stat4 range order current source next92 between upper key'] = static fn (TestRunner $t) => $t->same('plugin_gamma', $betweenPlan()['rangeCurrentSourceKeys']['upper']);
$tests['planner stat4 range order current source next92 between upper inclusive'] = static fn (TestRunner $t) => $t->same(true, $betweenPlan()['stat4RangeCurrentNext']['upperInclusive']);
$tests['planner stat4 range order current source next92 between matched keys'] = static fn (TestRunner $t) => $t->same(['plugin_alpha', 'plugin_beta', 'plugin_gamma'], $betweenPlan()['matchedCurrentSourceKeys']);

$emptyGap = static fn (): array => $plan($and($point('autoload', 'yes'), $range('option_name', '>=', 'cron_a'), $range('option_name', '<', 'cron_z')));
$tests['planner stat4 range order current source next92 empty gap flag'] = static fn (TestRunner $t) => $t->same(true, $emptyGap()['stat4RangeCurrentNext']['emptyGap']);
$tests['planner stat4 range order current source next92 empty gap lower'] = static fn (TestRunner $t) => $t->same('autoloaded_widget', $emptyGap()['rangeCurrentSourceKeys']['lower']);
$tests['planner stat4 range order current source next92 empty gap lower next'] = static fn (TestRunner $t) => $t->same('plugin_alpha', $emptyGap()['rangeCurrentSourceKeys']['lowerNext']);
$tests['planner stat4 range order current source next92 empty gap matched keys empty'] = static fn (TestRunner $t) => $t->same([], $emptyGap()['matchedCurrentSourceKeys']);

$covering = static fn (): array => $plan(null, [['column' => 'option_name']], ['option_id', 'option_value']);
$tests['planner stat4 range order current source next92 covering alternative wins when columns require it'] = static fn (TestRunner $t) => $t->same('zz_idx_wp_options_autoload_name_covering', $covering()['selected']);
$tests['planner stat4 range order current source next92 covering detail'] = static fn (TestRunner $t) => $t->same('SEARCH zz_idx_wp_options_autoload_name_covering (option_name RANGE) USING STAT4 ORDER BY CURRENT SOURCE COVERING', $covering()['detail']);
$tests['planner stat4 range order current source next92 covering flag'] = static fn (TestRunner $t) => $t->same(true, $covering()['covering']);

$noPlan = static fn (): array => $plan($and($point('autoload', 'maybe'), $range('missing_column', '>=', 'a')), [['column' => 'option_name']]);
$tests['planner stat4 range order current source next92 no usable status'] = static fn (TestRunner $t) => $t->same('no-usable-plan', $noPlan()['status']);
$tests['planner stat4 range order current source next92 no usable block sort'] = static fn (TestRunner $t) => $t->same(true, $noPlan()['blockSortRequired']);
$tests['planner stat4 range order current source next92 validates order direction'] = static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => $plan(null, [['column' => 'option_name', 'direction' => 'SIDEWAYS']]));

return $tests;

<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteIndexPredicate;
use PortLibs\LibSqlite\SQLiteSkipScanStat4PartialOrderPlan;

$rows = static fn (): array => [
    ['rowid' => 1, 'autoload' => 'auto', 'option_name' => 'admin_email', 'kind' => 'core'],
    ['rowid' => 2, 'autoload' => 'auto', 'option_name' => 'plugin_alpha', 'kind' => 'plugin'],
    ['rowid' => 3, 'autoload' => 'auto', 'option_name' => 'plugin_beta', 'kind' => 'plugin'],
    ['rowid' => 4, 'autoload' => 'auto', 'option_name' => 'plugin_delta', 'kind' => 'plugin'],
    ['rowid' => 5, 'autoload' => 'lazy', 'option_name' => 'Plugin_Epsilon', 'kind' => 'plugin'],
    ['rowid' => 6, 'autoload' => 'lazy', 'option_name' => 'plugin_zeta', 'kind' => 'plugin'],
    ['rowid' => 7, 'autoload' => 'lazy', 'option_name' => 'widget_recent-posts', 'kind' => 'widget'],
    ['rowid' => 8, 'autoload' => 'no', 'option_name' => '_transient_alpha', 'kind' => 'transient'],
    ['rowid' => 9, 'autoload' => 'no', 'option_name' => 'plugin_alpha', 'kind' => 'plugin'],
    ['rowid' => 10, 'autoload' => 'no', 'option_name' => 'plugin_gamma', 'kind' => 'plugin'],
    ['rowid' => 11, 'autoload' => 'no', 'option_name' => 'plugin_theta', 'kind' => 'plugin'],
    ['rowid' => 12, 'autoload' => 'yes', 'option_name' => null, 'kind' => 'plugin'],
    ['rowid' => 13, 'autoload' => 'yes', 'option_name' => 'blogname', 'kind' => 'core'],
    ['rowid' => 14, 'autoload' => 'yes', 'option_name' => 'plugin_alpha', 'kind' => 'plugin'],
    ['rowid' => 15, 'autoload' => 'yes', 'option_name' => 'plugin_delta', 'kind' => 'plugin'],
    ['rowid' => 16, 'autoload' => 'yes', 'option_name' => 'theme_mods_child', 'kind' => 'theme'],
];

$stat4 = static fn (): array => [
    ['prefix' => 'auto', 'suffix' => 'plugin_alpha', 'nEq' => 2, 'nLt' => 1, 'nDLt' => 1],
    ['prefix' => 'auto', 'suffix' => 'plugin_beta', 'nEq' => 2, 'nLt' => 3, 'nDLt' => 2],
    ['prefix' => 'auto', 'suffix' => 'plugin_delta', 'nEq' => 1, 'nLt' => 5, 'nDLt' => 3],
    ['prefix' => 'lazy', 'suffix' => 'plugin_epsilon', 'nEq' => 1, 'nLt' => 1, 'nDLt' => 1],
    ['prefix' => 'lazy', 'suffix' => 'plugin_zeta', 'nEq' => 1, 'nLt' => 2, 'nDLt' => 2],
    ['prefix' => 'no', 'suffix' => 'plugin_alpha', 'nEq' => 4, 'nLt' => 1, 'nDLt' => 1],
    ['prefix' => 'no', 'suffix' => 'plugin_gamma', 'nEq' => 3, 'nLt' => 5, 'nDLt' => 2],
    ['prefix' => 'no', 'suffix' => 'plugin_theta', 'nEq' => 2, 'nLt' => 8, 'nDLt' => 3],
    ['prefix' => 'yes', 'suffix' => 'plugin_alpha', 'nEq' => 5, 'nLt' => 2, 'nDLt' => 1],
    ['prefix' => 'yes', 'suffix' => 'plugin_delta', 'nEq' => 4, 'nLt' => 7, 'nDLt' => 2],
];

$range = static fn (string $column, string $operator, mixed $value): array => ['operator' => $operator, 'left' => ['column' => $column], 'right' => $value];
$point = static fn (string $column, mixed $value): array => ['operator' => '=', 'left' => ['column' => $column], 'right' => $value];

$partial = new SQLiteIndexPredicate('', SQLiteIndexPredicate::AND, [
    new SQLiteIndexPredicate('kind', SQLiteIndexPredicate::EQUALS, 'plugin'),
    new SQLiteIndexPredicate('option_name', SQLiteIndexPredicate::GREATER_THAN_OR_EQUAL, 'plugin_'),
]);

$plan = static function (
    array $orderBy = [['column' => 'option_name']],
    mixed $lower = 'plugin_',
    mixed $upper = 'plugin_zzzz',
    bool $upperInclusive = true,
    string $collation = 'BINARY',
    ?array $samples = null,
) use ($rows, $stat4, $partial, $point, $range): array {
    return SQLiteSkipScanStat4PartialOrderPlan::plan(
        $rows(),
        'idx_wp_options_autoload_plugin_name_stat4',
        'autoload',
        'option_name',
        $lower,
        $upper,
        $partial,
        [$point('kind', 'plugin'), $range('option_name', '>=', 'plugin_')],
        $samples ?? $stat4(),
        $orderBy,
        $upperInclusive,
        $collation,
    );
};

$tests = [
    'planner stat4 partial skipscan order current next52 uses partial index' => static fn (TestRunner $t) => $t->same('usable', $plan()['status']),
    'planner stat4 partial skipscan order current next52 proves partial predicate' => static fn (TestRunner $t) => $t->same(true, $plan()['partialPredicateImplied']),
    'planner stat4 partial skipscan order current next52 keeps skip scan loops' => static fn (TestRunner $t) => $t->same(['auto', 'lazy', 'no', 'yes'], array_column($plan()['loops'], 'prefix')),
    'planner stat4 partial skipscan order current next52 keeps range rowids' => static fn (TestRunner $t) => $t->same([2, 3, 4, 6, 9, 10, 11, 14, 15], $plan()['rowids']),
    'planner stat4 partial skipscan order current next52 skips nonpartial rows' => static fn (TestRunner $t) => $t->same(7, $plan()['skippedPartialRows']),
    'planner stat4 partial skipscan order current next52 omits null option names before index scan' => static fn (TestRunner $t) => $t->same(0, $plan()['omittedNullRangeRows']),
    'planner stat4 partial skipscan order current next52 counts all stat samples' => static fn (TestRunner $t) => $t->same(10, $plan()['stat4SamplesUsed']),
    'planner stat4 partial skipscan order current next52 sums loop estimates' => static fn (TestRunner $t) => $t->same(8, $plan()['estimatedRows']),
    'planner stat4 partial skipscan order current next52 costs block sort by seek count' => static fn (TestRunner $t) => $t->same(48, $plan()['estimatedCost']),
    'planner stat4 partial skipscan order current next52 reports block sort count' => static fn (TestRunner $t) => $t->same(4, $plan()['sortBlockCount']),
    'planner stat4 partial skipscan order current next52 records suffix partial mode' => static fn (TestRunner $t) => $t->same('partial-current-next', $plan()['orderByMode']),
    'planner stat4 partial skipscan order current next52 records suffix partial sort' => static fn (TestRunner $t) => $t->same(true, $plan()['partialOrderBy']),
    'planner stat4 partial skipscan order current next52 records block sort required' => static fn (TestRunner $t) => $t->same(true, $plan()['blockSortRequired']),
    'planner stat4 partial skipscan order current next52 records skipped break column' => static fn (TestRunner $t) => $t->same(['autoload'], $plan()['sortBreakColumns']),
    'planner stat4 partial skipscan order current next52 records asc direction' => static fn (TestRunner $t) => $t->same(['ASC'], $plan()['orderByDirections']),
    'planner stat4 partial skipscan order current next52 suffix asc is not reverse scan' => static fn (TestRunner $t) => $t->same(false, $plan()['reverseScan']),
    'planner stat4 partial skipscan order current next52 detail keeps temp btree evidence' => static fn (TestRunner $t) => $t->same('SEARCH USING SKIP-SCAN idx_wp_options_autoload_plugin_name_stat4 (ANY(autoload) AND option_name RANGE) USING STAT4 USE TEMP B-TREE FOR RIGHT PART OF ORDER BY', $plan()['detail']),
];

$tests += [
    'planner stat4 partial skipscan order current next52 auto current suffix' => static fn (TestRunner $t) => $t->same('plugin_alpha', $plan()['stat4CurrentNextByPrefix'][0]['current']['suffix']),
    'planner stat4 partial skipscan order current next52 auto next suffix' => static fn (TestRunner $t) => $t->same('plugin_beta', $plan()['stat4CurrentNextByPrefix'][0]['next']['suffix']),
    'planner stat4 partial skipscan order current next52 auto range sample count' => static fn (TestRunner $t) => $t->same(3, $plan()['stat4CurrentNextByPrefix'][0]['rangeSamples']),
    'planner stat4 partial skipscan order current next52 lazy current suffix' => static fn (TestRunner $t) => $t->same('plugin_epsilon', $plan()['stat4CurrentNextByPrefix'][1]['current']['suffix']),
    'planner stat4 partial skipscan order current next52 lazy next suffix' => static fn (TestRunner $t) => $t->same('plugin_zeta', $plan()['stat4CurrentNextByPrefix'][1]['next']['suffix']),
    'planner stat4 partial skipscan order current next52 no current suffix' => static fn (TestRunner $t) => $t->same('plugin_alpha', $plan()['stat4CurrentNextByPrefix'][2]['current']['suffix']),
    'planner stat4 partial skipscan order current next52 no next suffix' => static fn (TestRunner $t) => $t->same('plugin_gamma', $plan()['stat4CurrentNextByPrefix'][2]['next']['suffix']),
    'planner stat4 partial skipscan order current next52 yes current suffix' => static fn (TestRunner $t) => $t->same('plugin_alpha', $plan()['stat4CurrentNextByPrefix'][3]['current']['suffix']),
    'planner stat4 partial skipscan order current next52 yes next suffix' => static fn (TestRunner $t) => $t->same('plugin_delta', $plan()['stat4CurrentNextByPrefix'][3]['next']['suffix']),
    'planner stat4 partial skipscan order current next52 loop embeds current suffix' => static fn (TestRunner $t) => $t->same('plugin_alpha', $plan()['stat4LoopEstimates'][0]['current']['suffix']),
    'planner stat4 partial skipscan order current next52 loop embeds next suffix' => static fn (TestRunner $t) => $t->same('plugin_beta', $plan()['stat4LoopEstimates'][0]['next']['suffix']),
    'planner stat4 partial skipscan order current next52 loop records range samples' => static fn (TestRunner $t) => $t->same([3, 2, 3, 2], array_column($plan()['stat4LoopEstimates'], 'rangeSamples')),
];

$tests += [
    'planner stat4 partial skipscan order current next52 full order is satisfied' => static fn (TestRunner $t) => $t->same(true, $plan([['column' => 'autoload'], ['column' => 'option_name']])['orderBySatisfied']),
    'planner stat4 partial skipscan order current next52 full order mode' => static fn (TestRunner $t) => $t->same('full', $plan([['column' => 'autoload'], ['column' => 'option_name']])['orderByMode']),
    'planner stat4 partial skipscan order current next52 full order avoids block sort' => static fn (TestRunner $t) => $t->same(false, $plan([['column' => 'autoload'], ['column' => 'option_name']])['blockSortRequired']),
    'planner stat4 partial skipscan order current next52 full order cost omits sort penalty' => static fn (TestRunner $t) => $t->same(40, $plan([['column' => 'autoload'], ['column' => 'option_name']])['estimatedCost']),
    'planner stat4 partial skipscan order current next52 reverse full order is satisfied' => static fn (TestRunner $t) => $t->same(true, $plan([['column' => 'autoload', 'direction' => 'DESC'], ['column' => 'option_name', 'direction' => 'DESC']])['orderBySatisfied']),
    'planner stat4 partial skipscan order current next52 reverse full order mode' => static fn (TestRunner $t) => $t->same('full-reverse', $plan([['column' => 'autoload', 'direction' => 'DESC'], ['column' => 'option_name', 'direction' => 'DESC']])['orderByMode']),
    'planner stat4 partial skipscan order current next52 reverse full scan flag' => static fn (TestRunner $t) => $t->same(true, $plan([['column' => 'autoload', 'direction' => 'DESC'], ['column' => 'option_name', 'direction' => 'DESC']])['reverseScan']),
    'planner stat4 partial skipscan order current next52 reverse full directions' => static fn (TestRunner $t) => $t->same(['DESC', 'DESC'], $plan([['column' => 'autoload', 'direction' => 'DESC'], ['column' => 'option_name', 'direction' => 'DESC']])['orderByDirections']),
    'planner stat4 partial skipscan order current next52 mixed direction requires external sort' => static fn (TestRunner $t) => $t->same('mixed-direction-external-sort', $plan([['column' => 'autoload'], ['column' => 'option_name', 'direction' => 'DESC']])['orderByMode']),
    'planner stat4 partial skipscan order current next52 mixed direction breaks both key columns' => static fn (TestRunner $t) => $t->same(['autoload', 'option_name'], $plan([['column' => 'autoload'], ['column' => 'option_name', 'direction' => 'DESC']])['sortBreakColumns']),
    'planner stat4 partial skipscan order current next52 suffix desc keeps partial mode' => static fn (TestRunner $t) => $t->same('partial-current-next', $plan([['column' => 'option_name', 'direction' => 'DESC']])['orderByMode']),
    'planner stat4 partial skipscan order current next52 suffix desc reverse flag' => static fn (TestRunner $t) => $t->same(true, $plan([['column' => 'option_name', 'direction' => 'DESC']])['reverseScan']),
    'planner stat4 partial skipscan order current next52 prefix desc satisfied' => static fn (TestRunner $t) => $t->same(true, $plan([['column' => 'autoload', 'direction' => 'DESC']])['orderBySatisfied']),
    'planner stat4 partial skipscan order current next52 prefix desc mode' => static fn (TestRunner $t) => $t->same('prefix-only-reverse', $plan([['column' => 'autoload', 'direction' => 'DESC']])['orderByMode']),
    'planner stat4 partial skipscan order current next52 validates order direction' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => $plan([['column' => 'autoload', 'direction' => 'SIDEWAYS']])),
];

$tests += [
    'planner stat4 partial skipscan order current next52 narrowed upper filters rowids' => static fn (TestRunner $t) => $t->same([2, 3, 4, 9, 10, 14, 15], $plan([['column' => 'option_name']], 'plugin_', 'plugin_gamma')['rowids']),
    'planner stat4 partial skipscan order current next52 narrowed upper current next counts' => static fn (TestRunner $t) => $t->same([3, 1, 2, 2], array_column($plan([['column' => 'option_name']], 'plugin_', 'plugin_gamma')['stat4CurrentNextByPrefix'], 'rangeSamples')),
    'planner stat4 partial skipscan order current next52 exclusive upper filters boundary rowids' => static fn (TestRunner $t) => $t->same([2, 3, 4, 9, 14, 15], $plan([['column' => 'option_name']], 'plugin_', 'plugin_gamma', false)['rowids']),
    'planner stat4 partial skipscan order current next52 exclusive upper filters boundary samples' => static fn (TestRunner $t) => $t->same([3, 1, 1, 2], array_column($plan([['column' => 'option_name']], 'plugin_', 'plugin_gamma', false)['stat4CurrentNextByPrefix'], 'rangeSamples')),
    'planner stat4 partial skipscan order current next52 nocase admits uppercase plugin row' => static fn (TestRunner $t) => $t->same([2, 3, 4, 5, 6, 9, 10, 11, 14, 15], $plan([['column' => 'option_name']], 'PLUGIN_', 'PLUGIN_ZZZZ', true, 'NOCASE')['rowids']),
    'planner stat4 partial skipscan order current next52 nocase lazy current suffix' => static fn (TestRunner $t) => $t->same('plugin_epsilon', $plan([['column' => 'option_name']], 'PLUGIN_', 'PLUGIN_ZZZZ', true, 'NOCASE')['stat4CurrentNextByPrefix'][1]['current']['suffix']),
    'planner stat4 partial skipscan order current next52 nocase lazy next suffix' => static fn (TestRunner $t) => $t->same('plugin_zeta', $plan([['column' => 'option_name']], 'PLUGIN_', 'PLUGIN_ZZZZ', true, 'NOCASE')['stat4CurrentNextByPrefix'][1]['next']['suffix']),
    'planner stat4 partial skipscan order current next52 empty samples keeps null current evidence' => static fn (TestRunner $t) => $t->same([null, null, null, null], array_column($plan([['column' => 'autoload'], ['column' => 'option_name']], 'plugin_', 'plugin_zzzz', true, 'BINARY', [])['stat4CurrentNextByPrefix'], 'current')),
    'planner stat4 partial skipscan order current next52 empty samples uses matched fallback estimates' => static fn (TestRunner $t) => $t->same([3, 1, 3, 2], array_column($plan([['column' => 'autoload'], ['column' => 'option_name']], 'plugin_', 'plugin_zzzz', true, 'BINARY', [])['stat4LoopEstimates'], 'estimatedRows')),
    'planner stat4 partial skipscan order current next52 rejects negative sample counter' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => $plan([['column' => 'option_name']], 'plugin_', 'plugin_zzzz', true, 'BINARY', [['prefix' => 'auto', 'suffix' => 'plugin_alpha', 'nEq' => 1, 'nLt' => -1, 'nDLt' => 0]])),
    'planner stat4 partial skipscan order current next52 external unrelated sort reports column' => static fn (TestRunner $t) => $t->same(['kind'], $plan([['column' => 'kind']])['sortBreakColumns']),
];

return $tests;

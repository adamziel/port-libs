<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteStat4SkipScanOrderCurrentSourcePlan;

$source = static function (array $overrides = []): array {
    return $overrides + [
        'name' => 'main',
        'schemaCookie' => 12,
        'stat4Generation' => 5,
        'indexName' => 'idx_wp_options_autoload_name_stat4',
        'skippedColumn' => 'autoload',
        'rangeColumn' => 'option_name',
        'lower' => 'plugin_',
        'upper' => 'plugin_zzzz',
        'upperInclusive' => true,
        'collation' => 'NOCASE',
        'partialPredicate' => [
            ['column' => 'kind', 'operator' => '=', 'value' => 'plugin'],
            ['column' => 'option_name', 'operator' => '>=', 'value' => 'plugin_'],
        ],
        'queryTerms' => [
            ['operator' => '=', 'left' => ['column' => 'kind'], 'right' => 'plugin'],
            ['operator' => '>=', 'left' => ['column' => 'option_name'], 'right' => 'plugin_'],
        ],
        'rows' => [
            ['rowid' => 1, 'autoload' => 'auto', 'option_name' => 'plugin_alpha', 'kind' => 'plugin'],
            ['rowid' => 2, 'autoload' => 'auto', 'option_name' => 'plugin_beta', 'kind' => 'plugin'],
            ['rowid' => 3, 'autoload' => 'lazy', 'option_name' => 'plugin_delta', 'kind' => 'plugin'],
            ['rowid' => 4, 'autoload' => 'lazy', 'option_name' => 'theme_mods_child', 'kind' => 'theme'],
            ['rowid' => 5, 'autoload' => 'no', 'option_name' => 'plugin_gamma', 'kind' => 'plugin'],
            ['rowid' => 6, 'autoload' => 'yes', 'option_name' => 'blogname', 'kind' => 'core'],
            ['rowid' => 7, 'autoload' => 'yes', 'option_name' => 'plugin_delta', 'kind' => 'plugin'],
        ],
        'stat4Samples' => [
            ['prefix' => 'auto', 'suffix' => 'plugin_alpha', 'nEq' => 3, 'nLt' => 0, 'nDLt' => 0],
            ['prefix' => 'auto', 'suffix' => 'plugin_beta', 'nEq' => 2, 'nLt' => 3, 'nDLt' => 1],
            ['prefix' => 'lazy', 'suffix' => 'plugin_delta', 'nEq' => 1, 'nLt' => 0, 'nDLt' => 0],
            ['prefix' => 'no', 'suffix' => 'plugin_gamma', 'nEq' => 2, 'nLt' => 0, 'nDLt' => 0],
            ['prefix' => 'yes', 'suffix' => 'plugin_delta', 'nEq' => 4, 'nLt' => 0, 'nDLt' => 0],
        ],
    ];
};

$current = static function () use ($source): array {
    return $source([
        'name' => 'main-after-analyze',
        'schemaCookie' => 13,
        'stat4Generation' => 6,
        'rows' => [
            ['rowid' => 1, 'autoload' => 'auto', 'option_name' => 'plugin_alpha', 'kind' => 'plugin'],
            ['rowid' => 2, 'autoload' => 'auto', 'option_name' => 'plugin_beta', 'kind' => 'plugin'],
            ['rowid' => 3, 'autoload' => 'auto', 'option_name' => 'plugin_cache', 'kind' => 'plugin'],
            ['rowid' => 4, 'autoload' => 'lazy', 'option_name' => 'Plugin_Delta', 'kind' => 'plugin'],
            ['rowid' => 5, 'autoload' => 'lazy', 'option_name' => 'theme_mods_child', 'kind' => 'theme'],
            ['rowid' => 6, 'autoload' => 'no', 'option_name' => 'plugin_gamma', 'kind' => 'plugin'],
            ['rowid' => 7, 'autoload' => 'no', 'option_name' => 'plugin_theta', 'kind' => 'plugin'],
            ['rowid' => 8, 'autoload' => 'yes', 'option_name' => 'blogname', 'kind' => 'core'],
            ['rowid' => 9, 'autoload' => 'yes', 'option_name' => 'plugin_delta', 'kind' => 'plugin'],
            ['rowid' => 10, 'autoload' => 'yes', 'option_name' => 'plugin_zeta', 'kind' => 'plugin'],
        ],
        'stat4Samples' => [
            ['prefix' => 'auto', 'suffix' => 'plugin_alpha', 'nEq' => 1, 'nLt' => 0, 'nDLt' => 0],
            ['prefix' => 'auto', 'suffix' => 'plugin_beta', 'nEq' => 1, 'nLt' => 1, 'nDLt' => 1],
            ['prefix' => 'auto', 'suffix' => 'plugin_cache', 'nEq' => 1, 'nLt' => 2, 'nDLt' => 2],
            ['prefix' => 'lazy', 'suffix' => 'plugin_delta', 'nEq' => 1, 'nLt' => 0, 'nDLt' => 0],
            ['prefix' => 'no', 'suffix' => 'plugin_gamma', 'nEq' => 2, 'nLt' => 0, 'nDLt' => 0],
            ['prefix' => 'no', 'suffix' => 'plugin_theta', 'nEq' => 1, 'nLt' => 2, 'nDLt' => 1],
            ['prefix' => 'yes', 'suffix' => 'plugin_delta', 'nEq' => 2, 'nLt' => 0, 'nDLt' => 0],
            ['prefix' => 'yes', 'suffix' => 'plugin_zeta', 'nEq' => 1, 'nLt' => 2, 'nDLt' => 1],
        ],
    ]);
};

$plan = static fn (array $orderBy = [['column' => 'option_name']]): array => SQLiteStat4SkipScanOrderCurrentSourcePlan::compare($source(), $current(), $orderBy);

$tests = [
    'planner stat4 skipscan order current source next87 selects current after cookie change' => static fn (TestRunner $t) => $t->same('current', $plan()['selectedSource']),
    'planner stat4 skipscan order current source next87 marks stale prepared statement' => static fn (TestRunner $t) => $t->same(true, $plan()['stalePreparedStatement']),
    'planner stat4 skipscan order current source next87 requires reprepare' => static fn (TestRunner $t) => $t->same(true, $plan()['reprepareRequired']),
    'planner stat4 skipscan order current source next87 detects schema cookie change' => static fn (TestRunner $t) => $t->same(true, $plan()['schemaCookieChanged']),
    'planner stat4 skipscan order current source next87 detects stat4 generation change' => static fn (TestRunner $t) => $t->same(true, $plan()['stat4GenerationChanged']),
    'planner stat4 skipscan order current source next87 keeps status usable' => static fn (TestRunner $t) => $t->same('usable', $plan()['status']),
    'planner stat4 skipscan order current source next87 prepared rowids are old snapshot' => static fn (TestRunner $t) => $t->same([1, 2, 3, 5, 7], $plan()['preparedSource']['rowids']),
    'planner stat4 skipscan order current source next87 current rowids include analyzed source' => static fn (TestRunner $t) => $t->same([1, 2, 3, 4, 6, 7, 9, 10], $plan()['currentSource']['rowids']),
    'planner stat4 skipscan order current source next87 selected plan rowids use current source' => static fn (TestRunner $t) => $t->same([1, 2, 3, 4, 6, 7, 9, 10], $plan()['selectedPlan']['rowids']),
    'planner stat4 skipscan order current source next87 rowid delta added' => static fn (TestRunner $t) => $t->same([4, 6, 9, 10], $plan()['rowidDelta']['added']),
    'planner stat4 skipscan order current source next87 rowid delta removed' => static fn (TestRunner $t) => $t->same([5], $plan()['rowidDelta']['removed']),
    'planner stat4 skipscan order current source next87 rowid delta stable' => static fn (TestRunner $t) => $t->same([1, 2, 3, 7], $plan()['rowidDelta']['stable']),
    'planner stat4 skipscan order current source next87 prepared estimate' => static fn (TestRunner $t) => $t->same(9, $plan()['preparedSource']['estimatedRows']),
    'planner stat4 skipscan order current source next87 current estimate' => static fn (TestRunner $t) => $t->same(4, $plan()['currentSource']['estimatedRows']),
    'planner stat4 skipscan order current source next87 estimate delta uses refreshed stat4' => static fn (TestRunner $t) => $t->same(-5, $plan()['estimatedRowsDelta']),
    'planner stat4 skipscan order current source next87 current source name' => static fn (TestRunner $t) => $t->same('main-after-analyze', $plan()['currentSource']['name']),
    'planner stat4 skipscan order current source next87 selected detail reparses current' => static fn (TestRunner $t) => $t->contains('REPREPARE USING CURRENT SOURCE main-after-analyze', $plan()['detail']),
    'planner stat4 skipscan order current source next87 selected detail keeps temp btree evidence' => static fn (TestRunner $t) => $t->contains('USE TEMP B-TREE FOR RIGHT PART OF ORDER BY', $plan()['detail']),
    'planner stat4 skipscan order current source next87 suffix order mode stable' => static fn (TestRunner $t) => $t->same(false, $plan()['orderByModeChanged']),
    'planner stat4 skipscan order current source next87 suffix order satisfaction stable' => static fn (TestRunner $t) => $t->same(false, $plan()['orderBySatisfiedChanged']),
    'planner stat4 skipscan order current source next87 suffix order selected partial mode' => static fn (TestRunner $t) => $t->same('partial-current-next', $plan()['selectedPlan']['orderByMode']),
    'planner stat4 skipscan order current source next87 suffix order block sort count' => static fn (TestRunner $t) => $t->same(4, $plan()['selectedPlan']['sortBlockCount']),
    'planner stat4 skipscan order current source next87 current prefixes' => static fn (TestRunner $t) => $t->same(['auto', 'lazy', 'no', 'yes'], array_column($plan()['selectedPlan']['stat4CurrentNextByPrefix'], 'prefix')),
    'planner stat4 skipscan order current source next87 auto current suffix refreshed' => static fn (TestRunner $t) => $t->same('plugin_alpha', $plan()['selectedPlan']['stat4CurrentNextByPrefix'][0]['current']['suffix']),
    'planner stat4 skipscan order current source next87 auto next suffix refreshed' => static fn (TestRunner $t) => $t->same('plugin_beta', $plan()['selectedPlan']['stat4CurrentNextByPrefix'][0]['next']['suffix']),
    'planner stat4 skipscan order current source next87 auto sample count refreshed' => static fn (TestRunner $t) => $t->same(3, $plan()['selectedPlan']['stat4CurrentNextByPrefix'][0]['rangeSamples']),
    'planner stat4 skipscan order current source next87 no next suffix refreshed' => static fn (TestRunner $t) => $t->same('plugin_theta', $plan()['selectedPlan']['stat4CurrentNextByPrefix'][2]['next']['suffix']),
    'planner stat4 skipscan order current source next87 yes next suffix refreshed' => static fn (TestRunner $t) => $t->same('plugin_zeta', $plan()['selectedPlan']['stat4CurrentNextByPrefix'][3]['next']['suffix']),
    'planner stat4 skipscan order current source next87 selected stat4 samples count' => static fn (TestRunner $t) => $t->same(8, $plan()['selectedPlan']['stat4SamplesUsed']),
    'planner stat4 skipscan order current source next87 selected dependencies include stat4 helper' => static fn (TestRunner $t) => $t->same(true, in_array('SQLiteSkipScanStat4PartialOrderPlan', $plan()['dependencies'], true)),
    'planner stat4 skipscan order current source next87 full order is satisfied' => static fn (TestRunner $t) => $t->same(true, $plan([['column' => 'autoload'], ['column' => 'option_name']])['selectedPlan']['orderBySatisfied']),
    'planner stat4 skipscan order current source next87 full order avoids block sort' => static fn (TestRunner $t) => $t->same(false, $plan([['column' => 'autoload'], ['column' => 'option_name']])['selectedPlan']['blockSortRequired']),
    'planner stat4 skipscan order current source next87 full order detail satisfied' => static fn (TestRunner $t) => $t->contains('ORDER BY SATISFIED', $plan([['column' => 'autoload'], ['column' => 'option_name']])['detail']),
    'planner stat4 skipscan order current source next87 reverse suffix selected' => static fn (TestRunner $t) => $t->same(true, $plan([['column' => 'option_name', 'direction' => 'DESC']])['selectedPlan']['reverseScan']),
    'planner stat4 skipscan order current source next87 reverse full order mode' => static fn (TestRunner $t) => $t->same('full-reverse', $plan([['column' => 'autoload', 'direction' => 'DESC'], ['column' => 'option_name', 'direction' => 'DESC']])['selectedPlan']['orderByMode']),
    'planner stat4 skipscan order current source next87 reuse when cookies match' => static function (TestRunner $t) use ($source): void {
        $prepared = $source();
        $current = $source(['schemaCookie' => 12, 'stat4Generation' => 5, 'name' => 'main-still-current']);
        $t->same('prepared', SQLiteStat4SkipScanOrderCurrentSourcePlan::compare($prepared, $current, [['column' => 'option_name']])['selectedSource']);
    },
    'planner stat4 skipscan order current source next87 no reprepare when cookies match' => static function (TestRunner $t) use ($source): void {
        $prepared = $source();
        $current = $source(['schemaCookie' => 12, 'stat4Generation' => 5, 'name' => 'main-still-current']);
        $t->same(false, SQLiteStat4SkipScanOrderCurrentSourcePlan::compare($prepared, $current, [['column' => 'option_name']])['reprepareRequired']);
    },
    'planner stat4 skipscan order current source next87 stat4 generation alone invalidates' => static function (TestRunner $t) use ($source): void {
        $t->same(true, SQLiteStat4SkipScanOrderCurrentSourcePlan::compare($source(), $source(['stat4Generation' => 6]), [['column' => 'option_name']])['stat4GenerationChanged']);
    },
    'planner stat4 skipscan order current source next87 schema cookie alone invalidates' => static function (TestRunner $t) use ($source): void {
        $t->same(true, SQLiteStat4SkipScanOrderCurrentSourcePlan::compare($source(), $source(['schemaCookie' => 13]), [['column' => 'option_name']])['schemaCookieChanged']);
    },
    'planner stat4 skipscan order current source next87 validates schema cookie' => static function (TestRunner $t) use ($source): void {
        $t->throws(InvalidArgumentException::class, static fn () => SQLiteStat4SkipScanOrderCurrentSourcePlan::compare($source(['schemaCookie' => -1]), $source(), [['column' => 'option_name']]));
    },
    'planner stat4 skipscan order current source next87 validates stat4 generation' => static function (TestRunner $t) use ($source): void {
        $t->throws(InvalidArgumentException::class, static fn () => SQLiteStat4SkipScanOrderCurrentSourcePlan::compare($source(['stat4Generation' => -1]), $source(), [['column' => 'option_name']]));
    },
    'planner stat4 skipscan order current source next87 validates list rows' => static function (TestRunner $t) use ($source): void {
        $t->throws(InvalidArgumentException::class, static fn () => SQLiteStat4SkipScanOrderCurrentSourcePlan::compare($source(['rows' => ['bad' => []]]), $source(), [['column' => 'option_name']]));
    },
    'planner stat4 skipscan order current source next87 validates partial predicate list' => static function (TestRunner $t) use ($source): void {
        $t->throws(InvalidArgumentException::class, static fn () => SQLiteStat4SkipScanOrderCurrentSourcePlan::compare($source(['partialPredicate' => ['bad' => true]]), $source(), [['column' => 'option_name']]));
    },
];

return $tests;

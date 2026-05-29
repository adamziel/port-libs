<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteStat4RangeOrderCurrentSourceNextPlan;

$source = static function (array $overrides = []): array {
    return $overrides + [
        'name' => 'wp-options-before-analyze',
        'schemaCookie' => 21,
        'stat4Generation' => 7,
        'indexName' => 'idx_wp_options_option_name_stat4',
        'rangeColumn' => 'option_name',
        'lower' => 'plugin_',
        'upper' => 'plugin_theta',
        'upperInclusive' => true,
        'collation' => 'NOCASE',
        'rows' => [
            ['rowid' => 1, 'option_name' => 'plugin_alpha', 'autoload' => 'yes'],
            ['rowid' => 2, 'option_name' => 'plugin_beta', 'autoload' => 'no'],
            ['rowid' => 3, 'option_name' => 'siteurl', 'autoload' => 'yes'],
            ['rowid' => 4, 'option_name' => 'plugin_gamma', 'autoload' => 'auto'],
            ['rowid' => 5, 'option_name' => null, 'autoload' => 'yes'],
            ['rowid' => 6, 'option_name' => 'plugin_theta', 'autoload' => 'no'],
            ['rowid' => 7, 'option_name' => 'plugin_zeta', 'autoload' => 'no'],
        ],
        'stat4Samples' => [
            ['value' => 'plugin_alpha', 'nEq' => 3, 'nLt' => 0, 'nDLt' => 0],
            ['value' => 'plugin_beta', 'nEq' => 2, 'nLt' => 3, 'nDLt' => 1],
            ['value' => 'plugin_gamma', 'nEq' => 4, 'nLt' => 5, 'nDLt' => 2],
            ['value' => 'plugin_zeta', 'nEq' => 1, 'nLt' => 9, 'nDLt' => 3],
        ],
    ];
};

$current = static function (array $overrides = []) use ($source): array {
    return $source($overrides + [
        'name' => 'wp-options-after-analyze',
        'schemaCookie' => 22,
        'stat4Generation' => 8,
        'rows' => [
            ['rowid' => 1, 'option_name' => 'Plugin_Alpha', 'autoload' => 'yes'],
            ['rowid' => 2, 'option_name' => 'plugin_beta', 'autoload' => 'no'],
            ['rowid' => 3, 'option_name' => 'siteurl', 'autoload' => 'yes'],
            ['rowid' => 4, 'option_name' => 'plugin_delta', 'autoload' => 'auto'],
            ['rowid' => 5, 'option_name' => null, 'autoload' => 'yes'],
            ['rowid' => 6, 'option_name' => 'plugin_gamma', 'autoload' => 'auto'],
            ['rowid' => 7, 'option_name' => 'plugin_theta', 'autoload' => 'no'],
            ['rowid' => 8, 'option_name' => 'plugin_zeta', 'autoload' => 'no'],
            ['rowid' => 9, 'option_name' => 'widget_recent-posts', 'autoload' => 'yes'],
        ],
        'stat4Samples' => [
            ['value' => 'plugin_alpha', 'nEq' => 1, 'nLt' => 0, 'nDLt' => 0],
            ['value' => 'plugin_beta', 'nEq' => 1, 'nLt' => 1, 'nDLt' => 1],
            ['value' => 'plugin_delta', 'nEq' => 2, 'nLt' => 2, 'nDLt' => 2],
            ['value' => 'plugin_gamma', 'nEq' => 1, 'nLt' => 4, 'nDLt' => 3],
            ['value' => 'plugin_theta', 'nEq' => 1, 'nLt' => 5, 'nDLt' => 4],
        ],
    ]);
};

$plan = static fn (array $orderBy = [['column' => 'option_name']], ?array $prepared = null, ?array $fresh = null): array =>
    SQLiteStat4RangeOrderCurrentSourceNextPlan::compareNext97($prepared ?? $source(), $fresh ?? $current(), $orderBy);

$tests = [
    'planner stat4 range order current source next97 selects current source' => static fn (TestRunner $t) => $t->same('current', $plan()['selectedSource']),
    'planner stat4 range order current source next97 marks stale prepared statement' => static fn (TestRunner $t) => $t->same(true, $plan()['stalePreparedStatement']),
    'planner stat4 range order current source next97 requires reprepare' => static fn (TestRunner $t) => $t->same(true, $plan()['reprepareRequired']),
    'planner stat4 range order current source next97 detects schema cookie' => static fn (TestRunner $t) => $t->same(true, $plan()['schemaCookieChanged']),
    'planner stat4 range order current source next97 detects stat4 generation' => static fn (TestRunner $t) => $t->same(true, $plan()['stat4GenerationChanged']),
    'planner stat4 range order current source next97 keeps range stable' => static fn (TestRunner $t) => $t->same(false, $plan()['rangeChanged']),
    'planner stat4 range order current source next97 status usable' => static fn (TestRunner $t) => $t->same('usable', $plan()['status']),
    'planner stat4 range order current source next97 prepared rowids' => static fn (TestRunner $t) => $t->same([1, 2, 4, 6], $plan()['preparedSource']['rowids']),
    'planner stat4 range order current source next97 current rowids' => static fn (TestRunner $t) => $t->same([1, 2, 4, 6, 7], $plan()['currentSource']['rowids']),
    'planner stat4 range order current source next97 selected rowids use current' => static fn (TestRunner $t) => $t->same([1, 2, 4, 6, 7], $plan()['selectedPlan']['rowids']),
    'planner stat4 range order current source next97 prepared current sample' => static fn (TestRunner $t) => $t->same('plugin_alpha', $plan()['preparedSource']['stat4Current']['value']),
    'planner stat4 range order current source next97 prepared next sample' => static fn (TestRunner $t) => $t->same('plugin_beta', $plan()['preparedSource']['stat4Next']['value']),
    'planner stat4 range order current source next97 current sample refreshed' => static fn (TestRunner $t) => $t->same('plugin_alpha', $plan()['currentSource']['stat4Current']['value']),
    'planner stat4 range order current source next97 current next refreshed' => static fn (TestRunner $t) => $t->same('plugin_beta', $plan()['currentSource']['stat4Next']['value']),
    'planner stat4 range order current source next97 selected samples count' => static fn (TestRunner $t) => $t->same(5, $plan()['selectedPlan']['stat4SamplesUsed']),
    'planner stat4 range order current source next97 current range samples' => static fn (TestRunner $t) => $t->same(5, $plan()['selectedPlan']['stat4RangeSamples']),
    'planner stat4 range order current source next97 current nlt span' => static fn (TestRunner $t) => $t->same(6, $plan()['selectedPlan']['stat4RangeNltSpan']),
    'planner stat4 range order current source next97 prepared estimate' => static fn (TestRunner $t) => $t->same(4, $plan()['preparedSource']['estimatedRows']),
    'planner stat4 range order current source next97 current estimate' => static fn (TestRunner $t) => $t->same(5, $plan()['currentSource']['estimatedRows']),
    'planner stat4 range order current source next97 estimate delta' => static fn (TestRunner $t) => $t->same(1, $plan()['estimatedRowsDelta']),
    'planner stat4 range order current source next97 current cost' => static fn (TestRunner $t) => $t->same(13, $plan()['selectedPlan']['estimatedCost']),
    'planner stat4 range order current source next97 omits nulls' => static fn (TestRunner $t) => $t->same(1, $plan()['selectedPlan']['omittedNullRangeRows']),
    'planner stat4 range order current source next97 order is satisfied' => static fn (TestRunner $t) => $t->same(true, $plan()['selectedPlan']['orderBySatisfied']),
    'planner stat4 range order current source next97 order mode range' => static fn (TestRunner $t) => $t->same('range', $plan()['selectedPlan']['orderByMode']),
    'planner stat4 range order current source next97 no block sort' => static fn (TestRunner $t) => $t->same(false, $plan()['selectedPlan']['blockSortRequired']),
    'planner stat4 range order current source next97 no reverse asc' => static fn (TestRunner $t) => $t->same(false, $plan()['selectedPlan']['reverseScan']),
    'planner stat4 range order current source next97 detail reparses current' => static fn (TestRunner $t) => $t->contains('REPREPARE STAT4 RANGE ORDER USING CURRENT SOURCE wp-options-after-analyze', $plan()['detail']),
    'planner stat4 range order current source next97 detail keeps range order' => static fn (TestRunner $t) => $t->contains('ORDER BY RANGE', $plan()['detail']),
    'planner stat4 range order current source next97 dependencies name helper' => static fn (TestRunner $t) => $t->same(true, in_array('SQLiteStat4RangeOrderCurrentSourceNextPlan', $plan()['dependencies'], true)),
    'planner stat4 range order current source next97 reverse rowids' => static fn (TestRunner $t) => $t->same([7, 6, 4, 2, 1], $plan([['column' => 'option_name', 'direction' => 'DESC']])['selectedPlan']['rowids']),
    'planner stat4 range order current source next97 reverse mode' => static fn (TestRunner $t) => $t->same('range-reverse', $plan([['column' => 'option_name', 'direction' => 'DESC']])['selectedPlan']['orderByMode']),
    'planner stat4 range order current source next97 reverse flag' => static fn (TestRunner $t) => $t->same(true, $plan([['column' => 'option_name', 'direction' => 'DESC']])['selectedPlan']['reverseScan']),
    'planner stat4 range order current source next97 reverse detail' => static fn (TestRunner $t) => $t->contains('ORDER BY RANGE REVERSE', $plan([['column' => 'option_name', 'direction' => 'DESC']])['detail']),
    'planner stat4 range order current source next97 unrelated order sorts' => static fn (TestRunner $t) => $t->same('external-sort', $plan([['column' => 'autoload']])['selectedPlan']['orderByMode']),
    'planner stat4 range order current source next97 unrelated order block sort' => static fn (TestRunner $t) => $t->same(true, $plan([['column' => 'autoload']])['selectedPlan']['blockSortRequired']),
    'planner stat4 range order current source next97 unrelated order cost includes sort' => static fn (TestRunner $t) => $t->same(18, $plan([['column' => 'autoload']])['selectedPlan']['estimatedCost']),
    'planner stat4 range order current source next97 multi term order sorts' => static fn (TestRunner $t) => $t->same('external-sort', $plan([['column' => 'option_name'], ['column' => 'autoload']])['selectedPlan']['orderByMode']),
    'planner stat4 range order current source next97 range change invalidates' => static function (TestRunner $t) use ($source, $current, $plan): void {
        $t->same(true, $plan([['column' => 'option_name']], $source(), $current(['upper' => 'plugin_gamma']))['rangeChanged']);
    },
    'planner stat4 range order current source next97 narrowed range rowids' => static function (TestRunner $t) use ($source, $current, $plan): void {
        $t->same([1, 2, 4, 6], $plan([['column' => 'option_name']], $source(), $current(['upper' => 'plugin_gamma']))['selectedPlan']['rowids']);
    },
    'planner stat4 range order current source next97 narrowed range samples' => static function (TestRunner $t) use ($source, $current, $plan): void {
        $t->same(4, $plan([['column' => 'option_name']], $source(), $current(['upper' => 'plugin_gamma']))['selectedPlan']['stat4RangeSamples']);
    },
    'planner stat4 range order current source next97 exclusive upper drops boundary row' => static function (TestRunner $t) use ($source, $current, $plan): void {
        $t->same([1, 2, 4, 6], $plan([['column' => 'option_name']], $source(), $current(['upperInclusive' => false]))['selectedPlan']['rowids']);
    },
    'planner stat4 range order current source next97 exclusive upper drops sample' => static function (TestRunner $t) use ($source, $current, $plan): void {
        $t->same(4, $plan([['column' => 'option_name']], $source(), $current(['upperInclusive' => false]))['selectedPlan']['stat4RangeSamples']);
    },
    'planner stat4 range order current source next97 nocase admits uppercase current row' => static fn (TestRunner $t) => $t->same(1, $plan()['selectedPlan']['rowids'][0]),
    'planner stat4 range order current source next97 binary lower excludes uppercase current row' => static function (TestRunner $t) use ($source, $current, $plan): void {
        $t->same([2, 4, 6, 7], $plan([['column' => 'option_name']], $source(['collation' => 'BINARY']), $current(['collation' => 'BINARY']))['selectedPlan']['rowids']);
    },
    'planner stat4 range order current source next97 matching cookies reuse prepared' => static function (TestRunner $t) use ($source, $plan): void {
        $t->same('prepared', $plan([['column' => 'option_name']], $source(), $source(['name' => 'wp-options-same-source']))['selectedSource']);
    },
    'planner stat4 range order current source next97 matching cookies skip reprepare' => static function (TestRunner $t) use ($source, $plan): void {
        $t->same(false, $plan([['column' => 'option_name']], $source(), $source(['name' => 'wp-options-same-source']))['reprepareRequired']);
    },
    'planner stat4 range order current source next97 stat4 generation alone invalidates' => static function (TestRunner $t) use ($source, $plan): void {
        $t->same(true, $plan([['column' => 'option_name']], $source(), $source(['stat4Generation' => 8]))['stat4GenerationChanged']);
    },
    'planner stat4 range order current source next97 validates schema cookie' => static function (TestRunner $t) use ($source, $current): void {
        $t->throws(InvalidArgumentException::class, static fn () => SQLiteStat4RangeOrderCurrentSourceNextPlan::compareNext97($source(['schemaCookie' => -1]), $current()));
    },
    'planner stat4 range order current source next97 validates stat4 generation' => static function (TestRunner $t) use ($source, $current): void {
        $t->throws(InvalidArgumentException::class, static fn () => SQLiteStat4RangeOrderCurrentSourceNextPlan::compareNext97($source(['stat4Generation' => -1]), $current()));
    },
    'planner stat4 range order current source next97 validates row lists' => static function (TestRunner $t) use ($source, $current): void {
        $t->throws(InvalidArgumentException::class, static fn () => SQLiteStat4RangeOrderCurrentSourceNextPlan::compareNext97($source(['rows' => ['bad' => []]]), $current()));
    },
    'planner stat4 range order current source next97 validates sample value' => static function (TestRunner $t) use ($source, $current): void {
        $bad = $current(['stat4Samples' => [['nEq' => 1, 'nLt' => 0, 'nDLt' => 0]]]);
        $t->throws(InvalidArgumentException::class, static fn () => SQLiteStat4RangeOrderCurrentSourceNextPlan::compareNext97($source(), $bad));
    },
    'planner stat4 range order current source next97 validates sample counters' => static function (TestRunner $t) use ($source, $current): void {
        $bad = $current(['stat4Samples' => [['value' => 'plugin_alpha', 'nEq' => -1, 'nLt' => 0, 'nDLt' => 0]]]);
        $t->throws(InvalidArgumentException::class, static fn () => SQLiteStat4RangeOrderCurrentSourceNextPlan::compareNext97($source(), $bad));
    },
    'planner stat4 range order current source next97 validates order direction' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => $plan([['column' => 'option_name', 'direction' => 'SIDEWAYS']])),
    'planner stat4 range order current source next97 validates collation' => static function (TestRunner $t) use ($source, $current): void {
        $t->throws(InvalidArgumentException::class, static fn () => SQLiteStat4RangeOrderCurrentSourceNextPlan::compareNext97($source(['collation' => 'RTRIM']), $current()));
    },
];

return $tests;

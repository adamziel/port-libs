<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteStat4OrderCoveringCurrentSourceNextPlan;

$point = static fn (string $column, mixed $value): array => ['operator' => '=', 'left' => ['column' => $column], 'right' => $value];
$range = static fn (string $column, string $operator, mixed $value): array => ['operator' => $operator, 'left' => ['column' => $column], 'right' => $value];
$and = static fn (array ...$terms): array => ['operator' => 'AND', 'terms' => $terms];

$predicate = $and(
    $point('blog_id', 1),
    $point('autoload', 'yes'),
    $range('option_name', '>=', 'plugin_'),
    $range('option_name', '<', 'plugin_z')
);
$orderBy = [['column' => 'option_name'], ['column' => 'option_value']];
$neededColumns = ['option_name', 'option_value', 'autoload'];

$source = static function (array $overrides = []): array {
    return $overrides + [
        'name' => 'prepared-before-analyze',
        'schemaCookie' => 30,
        'stat4Generation' => 12,
        'coveringColumns' => ['autoload', 'option_name', 'option_value'],
        'indexes' => [[
            'name' => 'idx_wp_options_blog_autoload_name_value_stat4_current_source',
            'rootPage' => 9401,
            'estimatedRows' => 150,
            'distinctValues' => ['blog_id' => 2, 'autoload' => 3, 'option_name' => 120],
            'stat4Samples' => [
                ['neq' => '1 8 8 8', 'nlt' => '0 0 0 0', 'ndlt' => '0 0 0 0', 'sample' => [1, 'yes', 'plugin_alpha', 'a:1:{}']],
                ['neq' => '1 14 14 14', 'nlt' => '8 8 8 8', 'ndlt' => '1 1 1 1', 'sample' => [1, 'yes', 'plugin_forms', 'a:2:{}']],
                ['neq' => '1 25 25 25', 'nlt' => '22 22 22 22', 'ndlt' => '2 2 2 2', 'sample' => [1, 'yes', 'plugin_security', 'a:3:{}']],
            ],
            'sql' => "CREATE INDEX idx_wp_options_blog_autoload_name_value_stat4_current_source ON wp_options(blog_id, autoload, option_name, option_value) WHERE option_name >= 'plugin_' AND option_name < 'plugin_z'",
        ], [
            'name' => 'idx_wp_options_blog_name_stat4_noncovering_current_source',
            'rootPage' => 9402,
            'estimatedRows' => 90,
            'stat4Samples' => [
                ['neq' => '1 16', 'nlt' => '0 0', 'ndlt' => '0 0', 'sample' => [1, 'plugin_forms']],
            ],
            'sql' => 'CREATE INDEX idx_wp_options_blog_name_stat4_noncovering_current_source ON wp_options(blog_id, option_name)',
        ]],
    ];
};

$current = static function () use ($source): array {
    $data = $source([
        'name' => 'current-after-plugin-import-analyze',
        'schemaCookie' => 31,
        'stat4Generation' => 13,
    ]);
    $data['indexes'][0]['rootPage'] = 9410;
    $data['indexes'][0]['stat4Samples'] = [
        ['neq' => '1 2 2 2', 'nlt' => '0 0 0 0', 'ndlt' => '0 0 0 0', 'sample' => [1, 'yes', 'plugin_alpha', 'a:1:{}']],
        ['neq' => '1 4 4 4', 'nlt' => '2 2 2 2', 'ndlt' => '1 1 1 1', 'sample' => [1, 'yes', 'plugin_cache', 'a:2:{}']],
        ['neq' => '1 5 5 5', 'nlt' => '6 6 6 6', 'ndlt' => '2 2 2 2', 'sample' => [1, 'yes', 'plugin_forms', 'a:3:{}']],
        ['neq' => '1 3 3 3', 'nlt' => '11 11 11 11', 'ndlt' => '3 3 3 3', 'sample' => [1, 'yes', 'plugin_security', 'a:4:{}']],
        ['neq' => '1 2 2 2', 'nlt' => '14 14 14 14', 'ndlt' => '4 4 4 4', 'sample' => [1, 'yes', 'plugin_slider', 'a:5:{}']],
    ];
    $data['indexes'][1]['estimatedRows'] = 60;

    return $data;
};

$plan = static fn (
    ?array $prepared = null,
    ?array $fresh = null,
    ?array $order = null,
    ?array $columns = null,
): array => SQLiteStat4OrderCoveringCurrentSourceNextPlan::compareCurrentSourcePlan(
    $prepared ?? $source(),
    $fresh ?? $current(),
    $GLOBALS['predicate_current_source'],
    $order ?? $GLOBALS['order_by_current_source'],
    $columns ?? $GLOBALS['needed_columns_current_source'],
);

$GLOBALS['predicate_current_source'] = $predicate;
$GLOBALS['order_by_current_source'] = $orderBy;
$GLOBALS['needed_columns_current_source'] = $neededColumns;

$tests = [
    'planner stat4 order covering current source current-source selects current source' => static fn (TestRunner $t) => $t->same('current', $plan()['selectedSource']),
    'planner stat4 order covering current source current-source marks stale prepared statement' => static fn (TestRunner $t) => $t->same(true, $plan()['stalePreparedStatement']),
    'planner stat4 order covering current source current-source requires reprepare' => static fn (TestRunner $t) => $t->same(true, $plan()['reprepareRequired']),
    'planner stat4 order covering current source current-source detects schema cookie change' => static fn (TestRunner $t) => $t->same(true, $plan()['schemaCookieChanged']),
    'planner stat4 order covering current source current-source detects stat4 generation change' => static fn (TestRunner $t) => $t->same(true, $plan()['stat4GenerationChanged']),
    'planner stat4 order covering current source current-source detects index signature change' => static fn (TestRunner $t) => $t->same(true, $plan()['indexSignatureChanged']),
    'planner stat4 order covering current source current-source projection remains stable' => static fn (TestRunner $t) => $t->same(false, $plan()['projectionChanged']),
    'planner stat4 order covering current source current-source status usable' => static fn (TestRunner $t) => $t->same('usable', $plan()['status']),
    'planner stat4 order covering current source current-source selected index' => static fn (TestRunner $t) => $t->same('idx_wp_options_blog_autoload_name_value_stat4_current_source', $plan()['selectedPlan']['name']),
    'planner stat4 order covering current source current-source selected root page is current' => static fn (TestRunner $t) => $t->same(9410, $plan()['selectedPlan']['rootPage']),
    'planner stat4 order covering current source current-source prepared root page is stale' => static fn (TestRunner $t) => $t->same(9401, $plan()['preparedSource']['rootPage']),
    'planner stat4 order covering current source current-source current root page summary' => static fn (TestRunner $t) => $t->same(9410, $plan()['currentSource']['rootPage']),
    'planner stat4 order covering current source current-source uses covering order plan' => static fn (TestRunner $t) => $t->same(true, $plan()['coveringOrderPlan']),
    'planner stat4 order covering current source current-source selected plan covering' => static fn (TestRunner $t) => $t->same(true, $plan()['selectedPlan']['covering']),
    'planner stat4 order covering current source current-source current summary covering' => static fn (TestRunner $t) => $t->same(true, $plan()['currentSource']['covering']),
    'planner stat4 order covering current source current-source covering unchanged' => static fn (TestRunner $t) => $t->same(false, $plan()['coveringChanged']),
    'planner stat4 order covering current source current-source order by satisfied' => static fn (TestRunner $t) => $t->same(true, $plan()['selectedPlan']['orderBySatisfied']),
    'planner stat4 order covering current source current-source current summary order by satisfied' => static fn (TestRunner $t) => $t->same(true, $plan()['currentSource']['orderBySatisfied']),
    'planner stat4 order covering current source current-source order mode current source' => static fn (TestRunner $t) => $t->same('partial-current-source', $plan()['selectedPlan']['orderByMode']),
    'planner stat4 order covering current source current-source order mode unchanged' => static fn (TestRunner $t) => $t->same(false, $plan()['orderByModeChanged']),
    'planner stat4 order covering current source current-source temp sort elided' => static fn (TestRunner $t) => $t->same(true, $plan()['tempSortElided']),
    'planner stat4 order covering current source current-source selected block sort false' => static fn (TestRunner $t) => $t->same(false, $plan()['selectedPlan']['blockSortRequired']),
    'planner stat4 order covering current source current-source table lookup elided' => static fn (TestRunner $t) => $t->same(true, $plan()['tableLookupElided']),
    'planner stat4 order covering current source current-source deferred table lookup false' => static fn (TestRunner $t) => $t->same(false, $plan()['selectedPlan']['deferredTableLookup']),
    'planner stat4 order covering current source current-source next source covering index' => static fn (TestRunner $t) => $t->same('covering-index', $plan()['selectedPlan']['nextSource']),
    'planner stat4 order covering current source current-source residual not required' => static fn (TestRunner $t) => $t->same(false, $plan()['selectedPlan']['nextResidualPredicateRequired']),
    'planner stat4 order covering current source current-source partial predicate implied' => static fn (TestRunner $t) => $t->same(true, $plan()['selectedPlan']['partialPredicateImplied']),
    'planner stat4 order covering current source current-source current partial predicate summary' => static fn (TestRunner $t) => $t->same(true, $plan()['currentSource']['partialPredicateImplied']),
    'planner stat4 order covering current source current-source stat4 used' => static fn (TestRunner $t) => $t->same(true, $plan()['selectedPlan']['stat4Used']),
    'planner stat4 order covering current source current-source current stat4 used' => static fn (TestRunner $t) => $t->same(true, $plan()['currentSource']['stat4Used']),
    'planner stat4 order covering current source current-source current stat4 sample count' => static fn (TestRunner $t) => $t->same(5, $plan()['selectedPlan']['stat4MatchedSamples']),
    'planner stat4 order covering current source current-source prepared stat4 sample count' => static fn (TestRunner $t) => $t->same(3, $plan()['preparedSource']['stat4MatchedSamples']),
    'planner stat4 order covering current source current-source current lower sample before range is null' => static fn (TestRunner $t) => $t->same(null, $plan()['selectedPlan']['stat4RangeCurrentNext']['lower']['current']),
    'planner stat4 order covering current source current-source current lower next key' => static fn (TestRunner $t) => $t->same('plugin_alpha', $plan()['selectedPlan']['stat4RangeCurrentNext']['lower']['next']['key']),
    'planner stat4 order covering current source current-source current upper sample key' => static fn (TestRunner $t) => $t->same('plugin_slider', $plan()['selectedPlan']['stat4RangeCurrentNext']['upper']['current']['key']),
    'planner stat4 order covering current source current-source current source name' => static fn (TestRunner $t) => $t->same('current-after-plugin-import-analyze', $plan()['currentSource']['name']),
    'planner stat4 order covering current source current-source prepared source name' => static fn (TestRunner $t) => $t->same('prepared-before-analyze', $plan()['preparedSource']['name']),
    'planner stat4 order covering current source current-source order signature' => static fn (TestRunner $t) => $t->same('option_name ASC,option_value ASC', $plan()['orderSignature']),
    'planner stat4 order covering current source current-source projection signature stable' => static fn (TestRunner $t) => $t->same($plan()['preparedSource']['projectionSignature'], $plan()['currentSource']['projectionSignature']),
    'planner stat4 order covering current source current-source index signature differs' => static fn (TestRunner $t) => $t->same(false, $plan()['preparedSource']['indexSignature'] === $plan()['currentSource']['indexSignature']),
    'planner stat4 order covering current source current-source detail reparses current source' => static fn (TestRunner $t) => $t->contains('REPREPARE STAT4 ORDER COVERING USING CURRENT SOURCE current-after-plugin-import-analyze', $plan()['detail']),
    'planner stat4 order covering current source current-source detail names covering order' => static fn (TestRunner $t) => $t->contains('COVERING ORDER CURRENT SOURCE', $plan()['detail']),
    'planner stat4 order covering current source current-source detail names partial order' => static fn (TestRunner $t) => $t->contains('ORDER BY FROM PARTIAL INDEX', $plan()['detail']),
    'planner stat4 order covering current source current-source dependencies include current marker' => static fn (TestRunner $t) => $t->same(true, in_array('sqlite-stat4-order-covering-current-source', $plan()['dependencies'], true)),
    'planner stat4 order covering current source current-source reuses prepared when signatures match' => static function (TestRunner $t) use ($source): void {
        $prepared = $source();
        $fresh = $source(['name' => 'current-same-analysis', 'schemaCookie' => 30, 'stat4Generation' => 12]);
        $t->same('prepared', SQLiteStat4OrderCoveringCurrentSourceNextPlan::compareCurrentSourcePlan($prepared, $fresh, $GLOBALS['predicate_current_source'], $GLOBALS['order_by_current_source'], $GLOBALS['needed_columns_current_source'])['selectedSource']);
    },
    'planner stat4 order covering current source current-source no reprepare when signatures match' => static function (TestRunner $t) use ($source): void {
        $prepared = $source();
        $fresh = $source(['name' => 'current-same-analysis', 'schemaCookie' => 30, 'stat4Generation' => 12]);
        $t->same(false, SQLiteStat4OrderCoveringCurrentSourceNextPlan::compareCurrentSourcePlan($prepared, $fresh, $GLOBALS['predicate_current_source'], $GLOBALS['order_by_current_source'], $GLOBALS['needed_columns_current_source'])['reprepareRequired']);
    },
    'planner stat4 order covering current source current-source schema cookie alone invalidates' => static function (TestRunner $t) use ($source): void {
        $t->same(true, SQLiteStat4OrderCoveringCurrentSourceNextPlan::compareCurrentSourcePlan($source(), $source(['schemaCookie' => 31]), $GLOBALS['predicate_current_source'], $GLOBALS['order_by_current_source'], $GLOBALS['needed_columns_current_source'])['schemaCookieChanged']);
    },
    'planner stat4 order covering current source current-source stat4 generation alone invalidates' => static function (TestRunner $t) use ($source): void {
        $t->same(true, SQLiteStat4OrderCoveringCurrentSourceNextPlan::compareCurrentSourcePlan($source(), $source(['stat4Generation' => 13]), $GLOBALS['predicate_current_source'], $GLOBALS['order_by_current_source'], $GLOBALS['needed_columns_current_source'])['stat4GenerationChanged']);
    },
    'planner stat4 order covering current source current-source projection change invalidates' => static function (TestRunner $t) use ($source): void {
        $prepared = $source();
        $fresh = $source(['coveringColumns' => ['autoload', 'option_name', 'option_value', 'blog_id']]);
        $t->same(true, SQLiteStat4OrderCoveringCurrentSourceNextPlan::compareCurrentSourcePlan($prepared, $fresh, $GLOBALS['predicate_current_source'], $GLOBALS['order_by_current_source'], $GLOBALS['needed_columns_current_source'])['projectionChanged']);
    },
    'planner stat4 order covering current source current-source missing covering column defers lookup' => static fn (TestRunner $t) => $t->same(false, $plan(null, null, null, ['option_name', 'missing_meta'])['tableLookupElided']),
    'planner stat4 order covering current source current-source missing covering column not covering order' => static fn (TestRunner $t) => $t->same(false, $plan(null, null, null, ['option_name', 'missing_meta'])['coveringOrderPlan']),
    'planner stat4 order covering current source current-source reverse order needs temp sort' => static fn (TestRunner $t) => $t->same(true, $plan(null, null, [['column' => 'option_name', 'direction' => 'DESC']])['selectedPlan']['blockSortRequired']),
    'planner stat4 order covering current source current-source reverse order not covering order plan' => static fn (TestRunner $t) => $t->same(false, $plan(null, null, [['column' => 'option_name', 'direction' => 'DESC']])['coveringOrderPlan']),
    'planner stat4 order covering current source current-source validates schema cookie' => static function (TestRunner $t) use ($source): void {
        $t->throws(InvalidArgumentException::class, static fn () => SQLiteStat4OrderCoveringCurrentSourceNextPlan::compareCurrentSourcePlan($source(['schemaCookie' => -1]), $source(), $GLOBALS['predicate_current_source'], $GLOBALS['order_by_current_source'], $GLOBALS['needed_columns_current_source']));
    },
    'planner stat4 order covering current source current-source validates stat4 generation' => static function (TestRunner $t) use ($source): void {
        $t->throws(InvalidArgumentException::class, static fn () => SQLiteStat4OrderCoveringCurrentSourceNextPlan::compareCurrentSourcePlan($source(['stat4Generation' => -1]), $source(), $GLOBALS['predicate_current_source'], $GLOBALS['order_by_current_source'], $GLOBALS['needed_columns_current_source']));
    },
    'planner stat4 order covering current source current-source validates indexes list' => static function (TestRunner $t) use ($source): void {
        $t->throws(InvalidArgumentException::class, static fn () => SQLiteStat4OrderCoveringCurrentSourceNextPlan::compareCurrentSourcePlan($source(['indexes' => ['bad' => []]]), $source(), $GLOBALS['predicate_current_source'], $GLOBALS['order_by_current_source'], $GLOBALS['needed_columns_current_source']));
    },
    'planner stat4 order covering current source current-source validates needed columns' => static function (TestRunner $t) use ($source): void {
        $t->throws(InvalidArgumentException::class, static fn () => SQLiteStat4OrderCoveringCurrentSourceNextPlan::compareCurrentSourcePlan($source(), $source(), $GLOBALS['predicate_current_source'], $GLOBALS['order_by_current_source'], ['']));
    },
    'planner stat4 order covering current source current-source validates order columns' => static function (TestRunner $t) use ($source): void {
        $t->throws(InvalidArgumentException::class, static fn () => SQLiteStat4OrderCoveringCurrentSourceNextPlan::compareCurrentSourcePlan($source(), $source(), $GLOBALS['predicate_current_source'], [['column' => '']], $GLOBALS['needed_columns_current_source']));
    },
    'planner stat4 order covering current source current-source validates order direction' => static function (TestRunner $t) use ($source): void {
        $t->throws(InvalidArgumentException::class, static fn () => SQLiteStat4OrderCoveringCurrentSourceNextPlan::compareCurrentSourcePlan($source(), $source(), $GLOBALS['predicate_current_source'], [['column' => 'option_name', 'direction' => 'SIDEWAYS']], $GLOBALS['needed_columns_current_source']));
    },
];

return $tests;

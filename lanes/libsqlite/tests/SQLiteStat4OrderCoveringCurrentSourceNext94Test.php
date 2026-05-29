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
            'name' => 'idx_wp_options_blog_autoload_name_value_stat4_next94',
            'rootPage' => 9401,
            'estimatedRows' => 150,
            'distinctValues' => ['blog_id' => 2, 'autoload' => 3, 'option_name' => 120],
            'stat4Samples' => [
                ['neq' => '1 8 8 8', 'nlt' => '0 0 0 0', 'ndlt' => '0 0 0 0', 'sample' => [1, 'yes', 'plugin_alpha', 'a:1:{}']],
                ['neq' => '1 14 14 14', 'nlt' => '8 8 8 8', 'ndlt' => '1 1 1 1', 'sample' => [1, 'yes', 'plugin_forms', 'a:2:{}']],
                ['neq' => '1 25 25 25', 'nlt' => '22 22 22 22', 'ndlt' => '2 2 2 2', 'sample' => [1, 'yes', 'plugin_security', 'a:3:{}']],
            ],
            'sql' => "CREATE INDEX idx_wp_options_blog_autoload_name_value_stat4_next94 ON wp_options(blog_id, autoload, option_name, option_value) WHERE option_name >= 'plugin_' AND option_name < 'plugin_z'",
        ], [
            'name' => 'idx_wp_options_blog_name_stat4_noncovering_next94',
            'rootPage' => 9402,
            'estimatedRows' => 90,
            'stat4Samples' => [
                ['neq' => '1 16', 'nlt' => '0 0', 'ndlt' => '0 0', 'sample' => [1, 'plugin_forms']],
            ],
            'sql' => 'CREATE INDEX idx_wp_options_blog_name_stat4_noncovering_next94 ON wp_options(blog_id, option_name)',
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
): array => SQLiteStat4OrderCoveringCurrentSourceNextPlan::compareNext94(
    $prepared ?? $source(),
    $fresh ?? $current(),
    $GLOBALS['predicate_next94'],
    $order ?? $GLOBALS['order_by_next94'],
    $columns ?? $GLOBALS['needed_columns_next94'],
);

$GLOBALS['predicate_next94'] = $predicate;
$GLOBALS['order_by_next94'] = $orderBy;
$GLOBALS['needed_columns_next94'] = $neededColumns;

$tests = [
    'planner stat4 order covering current source next94 selects current source' => static fn (TestRunner $t) => $t->same('current', $plan()['selectedSource']),
    'planner stat4 order covering current source next94 marks stale prepared statement' => static fn (TestRunner $t) => $t->same(true, $plan()['stalePreparedStatement']),
    'planner stat4 order covering current source next94 requires reprepare' => static fn (TestRunner $t) => $t->same(true, $plan()['reprepareRequired']),
    'planner stat4 order covering current source next94 detects schema cookie change' => static fn (TestRunner $t) => $t->same(true, $plan()['schemaCookieChanged']),
    'planner stat4 order covering current source next94 detects stat4 generation change' => static fn (TestRunner $t) => $t->same(true, $plan()['stat4GenerationChanged']),
    'planner stat4 order covering current source next94 detects index signature change' => static fn (TestRunner $t) => $t->same(true, $plan()['indexSignatureChanged']),
    'planner stat4 order covering current source next94 projection remains stable' => static fn (TestRunner $t) => $t->same(false, $plan()['projectionChanged']),
    'planner stat4 order covering current source next94 status usable' => static fn (TestRunner $t) => $t->same('usable', $plan()['status']),
    'planner stat4 order covering current source next94 selected index' => static fn (TestRunner $t) => $t->same('idx_wp_options_blog_autoload_name_value_stat4_next94', $plan()['selectedPlan']['name']),
    'planner stat4 order covering current source next94 selected root page is current' => static fn (TestRunner $t) => $t->same(9410, $plan()['selectedPlan']['rootPage']),
    'planner stat4 order covering current source next94 prepared root page is stale' => static fn (TestRunner $t) => $t->same(9401, $plan()['preparedSource']['rootPage']),
    'planner stat4 order covering current source next94 current root page summary' => static fn (TestRunner $t) => $t->same(9410, $plan()['currentSource']['rootPage']),
    'planner stat4 order covering current source next94 uses covering order plan' => static fn (TestRunner $t) => $t->same(true, $plan()['coveringOrderPlan']),
    'planner stat4 order covering current source next94 selected plan covering' => static fn (TestRunner $t) => $t->same(true, $plan()['selectedPlan']['covering']),
    'planner stat4 order covering current source next94 current summary covering' => static fn (TestRunner $t) => $t->same(true, $plan()['currentSource']['covering']),
    'planner stat4 order covering current source next94 covering unchanged' => static fn (TestRunner $t) => $t->same(false, $plan()['coveringChanged']),
    'planner stat4 order covering current source next94 order by satisfied' => static fn (TestRunner $t) => $t->same(true, $plan()['selectedPlan']['orderBySatisfied']),
    'planner stat4 order covering current source next94 current summary order by satisfied' => static fn (TestRunner $t) => $t->same(true, $plan()['currentSource']['orderBySatisfied']),
    'planner stat4 order covering current source next94 order mode current source' => static fn (TestRunner $t) => $t->same('partial-current-source', $plan()['selectedPlan']['orderByMode']),
    'planner stat4 order covering current source next94 order mode unchanged' => static fn (TestRunner $t) => $t->same(false, $plan()['orderByModeChanged']),
    'planner stat4 order covering current source next94 temp sort elided' => static fn (TestRunner $t) => $t->same(true, $plan()['tempSortElided']),
    'planner stat4 order covering current source next94 selected block sort false' => static fn (TestRunner $t) => $t->same(false, $plan()['selectedPlan']['blockSortRequired']),
    'planner stat4 order covering current source next94 table lookup elided' => static fn (TestRunner $t) => $t->same(true, $plan()['tableLookupElided']),
    'planner stat4 order covering current source next94 deferred table lookup false' => static fn (TestRunner $t) => $t->same(false, $plan()['selectedPlan']['deferredTableLookup']),
    'planner stat4 order covering current source next94 next source covering index' => static fn (TestRunner $t) => $t->same('covering-index', $plan()['selectedPlan']['nextSource']),
    'planner stat4 order covering current source next94 residual not required' => static fn (TestRunner $t) => $t->same(false, $plan()['selectedPlan']['nextResidualPredicateRequired']),
    'planner stat4 order covering current source next94 partial predicate implied' => static fn (TestRunner $t) => $t->same(true, $plan()['selectedPlan']['partialPredicateImplied']),
    'planner stat4 order covering current source next94 current partial predicate summary' => static fn (TestRunner $t) => $t->same(true, $plan()['currentSource']['partialPredicateImplied']),
    'planner stat4 order covering current source next94 stat4 used' => static fn (TestRunner $t) => $t->same(true, $plan()['selectedPlan']['stat4Used']),
    'planner stat4 order covering current source next94 current stat4 used' => static fn (TestRunner $t) => $t->same(true, $plan()['currentSource']['stat4Used']),
    'planner stat4 order covering current source next94 current stat4 sample count' => static fn (TestRunner $t) => $t->same(5, $plan()['selectedPlan']['stat4MatchedSamples']),
    'planner stat4 order covering current source next94 prepared stat4 sample count' => static fn (TestRunner $t) => $t->same(3, $plan()['preparedSource']['stat4MatchedSamples']),
    'planner stat4 order covering current source next94 current lower sample before range is null' => static fn (TestRunner $t) => $t->same(null, $plan()['selectedPlan']['stat4RangeCurrentNext']['lower']['current']),
    'planner stat4 order covering current source next94 current lower next key' => static fn (TestRunner $t) => $t->same('plugin_alpha', $plan()['selectedPlan']['stat4RangeCurrentNext']['lower']['next']['key']),
    'planner stat4 order covering current source next94 current upper sample key' => static fn (TestRunner $t) => $t->same('plugin_slider', $plan()['selectedPlan']['stat4RangeCurrentNext']['upper']['current']['key']),
    'planner stat4 order covering current source next94 current source name' => static fn (TestRunner $t) => $t->same('current-after-plugin-import-analyze', $plan()['currentSource']['name']),
    'planner stat4 order covering current source next94 prepared source name' => static fn (TestRunner $t) => $t->same('prepared-before-analyze', $plan()['preparedSource']['name']),
    'planner stat4 order covering current source next94 order signature' => static fn (TestRunner $t) => $t->same('option_name ASC,option_value ASC', $plan()['orderSignature']),
    'planner stat4 order covering current source next94 projection signature stable' => static fn (TestRunner $t) => $t->same($plan()['preparedSource']['projectionSignature'], $plan()['currentSource']['projectionSignature']),
    'planner stat4 order covering current source next94 index signature differs' => static fn (TestRunner $t) => $t->same(false, $plan()['preparedSource']['indexSignature'] === $plan()['currentSource']['indexSignature']),
    'planner stat4 order covering current source next94 detail reparses current source' => static fn (TestRunner $t) => $t->contains('REPREPARE STAT4 ORDER COVERING USING CURRENT SOURCE current-after-plugin-import-analyze', $plan()['detail']),
    'planner stat4 order covering current source next94 detail names covering order' => static fn (TestRunner $t) => $t->contains('COVERING ORDER CURRENT SOURCE', $plan()['detail']),
    'planner stat4 order covering current source next94 detail names partial order' => static fn (TestRunner $t) => $t->contains('ORDER BY FROM PARTIAL INDEX', $plan()['detail']),
    'planner stat4 order covering current source next94 dependencies include current marker' => static fn (TestRunner $t) => $t->same(true, in_array('sqlite-stat4-order-covering-current-source-next94', $plan()['dependencies'], true)),
    'planner stat4 order covering current source next94 reuses prepared when signatures match' => static function (TestRunner $t) use ($source): void {
        $prepared = $source();
        $fresh = $source(['name' => 'current-same-analysis', 'schemaCookie' => 30, 'stat4Generation' => 12]);
        $t->same('prepared', SQLiteStat4OrderCoveringCurrentSourceNextPlan::compareNext94($prepared, $fresh, $GLOBALS['predicate_next94'], $GLOBALS['order_by_next94'], $GLOBALS['needed_columns_next94'])['selectedSource']);
    },
    'planner stat4 order covering current source next94 no reprepare when signatures match' => static function (TestRunner $t) use ($source): void {
        $prepared = $source();
        $fresh = $source(['name' => 'current-same-analysis', 'schemaCookie' => 30, 'stat4Generation' => 12]);
        $t->same(false, SQLiteStat4OrderCoveringCurrentSourceNextPlan::compareNext94($prepared, $fresh, $GLOBALS['predicate_next94'], $GLOBALS['order_by_next94'], $GLOBALS['needed_columns_next94'])['reprepareRequired']);
    },
    'planner stat4 order covering current source next94 schema cookie alone invalidates' => static function (TestRunner $t) use ($source): void {
        $t->same(true, SQLiteStat4OrderCoveringCurrentSourceNextPlan::compareNext94($source(), $source(['schemaCookie' => 31]), $GLOBALS['predicate_next94'], $GLOBALS['order_by_next94'], $GLOBALS['needed_columns_next94'])['schemaCookieChanged']);
    },
    'planner stat4 order covering current source next94 stat4 generation alone invalidates' => static function (TestRunner $t) use ($source): void {
        $t->same(true, SQLiteStat4OrderCoveringCurrentSourceNextPlan::compareNext94($source(), $source(['stat4Generation' => 13]), $GLOBALS['predicate_next94'], $GLOBALS['order_by_next94'], $GLOBALS['needed_columns_next94'])['stat4GenerationChanged']);
    },
    'planner stat4 order covering current source next94 projection change invalidates' => static function (TestRunner $t) use ($source): void {
        $prepared = $source();
        $fresh = $source(['coveringColumns' => ['autoload', 'option_name', 'option_value', 'blog_id']]);
        $t->same(true, SQLiteStat4OrderCoveringCurrentSourceNextPlan::compareNext94($prepared, $fresh, $GLOBALS['predicate_next94'], $GLOBALS['order_by_next94'], $GLOBALS['needed_columns_next94'])['projectionChanged']);
    },
    'planner stat4 order covering current source next94 missing covering column defers lookup' => static fn (TestRunner $t) => $t->same(false, $plan(null, null, null, ['option_name', 'missing_meta'])['tableLookupElided']),
    'planner stat4 order covering current source next94 missing covering column not covering order' => static fn (TestRunner $t) => $t->same(false, $plan(null, null, null, ['option_name', 'missing_meta'])['coveringOrderPlan']),
    'planner stat4 order covering current source next94 reverse order needs temp sort' => static fn (TestRunner $t) => $t->same(true, $plan(null, null, [['column' => 'option_name', 'direction' => 'DESC']])['selectedPlan']['blockSortRequired']),
    'planner stat4 order covering current source next94 reverse order not covering order plan' => static fn (TestRunner $t) => $t->same(false, $plan(null, null, [['column' => 'option_name', 'direction' => 'DESC']])['coveringOrderPlan']),
    'planner stat4 order covering current source next94 validates schema cookie' => static function (TestRunner $t) use ($source): void {
        $t->throws(InvalidArgumentException::class, static fn () => SQLiteStat4OrderCoveringCurrentSourceNextPlan::compareNext94($source(['schemaCookie' => -1]), $source(), $GLOBALS['predicate_next94'], $GLOBALS['order_by_next94'], $GLOBALS['needed_columns_next94']));
    },
    'planner stat4 order covering current source next94 validates stat4 generation' => static function (TestRunner $t) use ($source): void {
        $t->throws(InvalidArgumentException::class, static fn () => SQLiteStat4OrderCoveringCurrentSourceNextPlan::compareNext94($source(['stat4Generation' => -1]), $source(), $GLOBALS['predicate_next94'], $GLOBALS['order_by_next94'], $GLOBALS['needed_columns_next94']));
    },
    'planner stat4 order covering current source next94 validates indexes list' => static function (TestRunner $t) use ($source): void {
        $t->throws(InvalidArgumentException::class, static fn () => SQLiteStat4OrderCoveringCurrentSourceNextPlan::compareNext94($source(['indexes' => ['bad' => []]]), $source(), $GLOBALS['predicate_next94'], $GLOBALS['order_by_next94'], $GLOBALS['needed_columns_next94']));
    },
    'planner stat4 order covering current source next94 validates needed columns' => static function (TestRunner $t) use ($source): void {
        $t->throws(InvalidArgumentException::class, static fn () => SQLiteStat4OrderCoveringCurrentSourceNextPlan::compareNext94($source(), $source(), $GLOBALS['predicate_next94'], $GLOBALS['order_by_next94'], ['']));
    },
    'planner stat4 order covering current source next94 validates order columns' => static function (TestRunner $t) use ($source): void {
        $t->throws(InvalidArgumentException::class, static fn () => SQLiteStat4OrderCoveringCurrentSourceNextPlan::compareNext94($source(), $source(), $GLOBALS['predicate_next94'], [['column' => '']], $GLOBALS['needed_columns_next94']));
    },
    'planner stat4 order covering current source next94 validates order direction' => static function (TestRunner $t) use ($source): void {
        $t->throws(InvalidArgumentException::class, static fn () => SQLiteStat4OrderCoveringCurrentSourceNextPlan::compareNext94($source(), $source(), $GLOBALS['predicate_next94'], [['column' => 'option_name', 'direction' => 'SIDEWAYS']], $GLOBALS['needed_columns_next94']));
    },
];

return $tests;

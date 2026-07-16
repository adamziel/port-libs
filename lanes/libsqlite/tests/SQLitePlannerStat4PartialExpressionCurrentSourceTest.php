<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePlannerStat4PartialExpressionCurrentSourceNextPlan;

$expr = static fn (string $function, string $column): array => ['function' => $function, 'column' => $column];
$column = static fn (string $name): array => ['column' => $name];
$point = static fn (array $left, mixed $value): array => ['operator' => '=', 'left' => $left, 'right' => $value];
$range = static fn (array $left, string $operator, mixed $value): array => ['operator' => $operator, 'left' => $left, 'right' => $value];
$and = static fn (array ...$terms): array => ['operator' => 'AND', 'terms' => $terms];

$lower = $expr('lower', 'option_name');
$predicate = $and(
    $range($lower, '>=', 'plugin_'),
    $range($lower, '<', 'plugin_z'),
    $point($column('autoload'), 'yes'),
);
$order = [$lower, ['column' => 'option_id']];
$needed = ['option_name', 'autoload', 'option_value', 'option_id', 'blog_id'];

$preparedSourceFactory = static function (array $overrides = []): array {
    return $overrides + [
        'name' => 'prepared-partial-expression-stat4-current-source',
        'schemaCookie' => 1330,
        'stat4Generation' => 40,
        'rowGeneration' => 10,
        'indexes' => [[
            'name' => 'idx_wp_options_lower_partial_expr_current_source',
            'rootPage' => 13301,
            'estimatedRows' => 360,
            'coveringColumns' => ['option_name', 'autoload', 'option_value', 'option_id', 'blog_id'],
            'coveringExpressions' => [['function' => 'lower', 'column' => 'option_name']],
            'stat4Samples' => [
                ['neq' => '1 1', 'nlt' => '0 0', 'ndlt' => '0 0', 'sample' => ['plugin_cache', 21]],
                ['neq' => '2 1', 'nlt' => '1 1', 'ndlt' => '1 1', 'sample' => ['plugin_forms', 31]],
                ['neq' => '4 1', 'nlt' => '3 2', 'ndlt' => '2 2', 'sample' => ['plugin_seo', 51]],
            ],
            'sql' => "CREATE INDEX idx_wp_options_lower_partial_expr_current_source ON wp_options(lower(option_name), option_id, option_value, blog_id) WHERE autoload = 'yes'",
        ]],
    ];
};

$currentSourceFactory = static function () use ($preparedSourceFactory): array {
    $source = $preparedSourceFactory([
        'name' => 'current-partial-expression-stat4-current-source',
        'schemaCookie' => 1334,
        'stat4Generation' => 47,
        'rowGeneration' => 16,
    ]);
    $source['indexes'][0]['rootPage'] = 13344;
    $source['indexes'][0]['stat4Samples'] = [
        ['neq' => '1 1', 'nlt' => '0 0', 'ndlt' => '0 0', 'sample' => ['plugin_alpha', 11]],
        ['neq' => '2 1', 'nlt' => '1 1', 'ndlt' => '1 1', 'sample' => ['plugin_cache', 21]],
        ['neq' => '3 1', 'nlt' => '3 2', 'ndlt' => '2 2', 'sample' => ['plugin_forms', 31]],
        ['neq' => '2 1', 'nlt' => '6 3', 'ndlt' => '3 3', 'sample' => ['plugin_mail', 41]],
        ['neq' => '4 1', 'nlt' => '8 4', 'ndlt' => '4 4', 'sample' => ['plugin_seo', 51]],
        ['neq' => '1 1', 'nlt' => '12 5', 'ndlt' => '5 5', 'sample' => ['theme_mods', 71]],
    ];

    return $source;
};

$preparedRowsFactory = static fn (): array => [
    ['rowid' => 21, 'option_name' => 'plugin_cache', 'autoload' => 'yes', 'option_value' => 'cache-old', 'option_id' => 21, 'blog_id' => 1],
    ['rowid' => 31, 'option_name' => 'Plugin_Forms', 'autoload' => 'yes', 'option_value' => 'forms-enabled', 'option_id' => 31, 'blog_id' => 1],
    ['rowid' => 51, 'option_name' => 'plugin_seo', 'autoload' => 'yes', 'option_value' => 'seo-enabled', 'option_id' => 51, 'blog_id' => 1],
    ['rowid' => 81, 'option_name' => 'plugin_deleted', 'autoload' => 'yes', 'option_value' => 'deleted', 'option_id' => 81, 'blog_id' => 1],
];

$currentRowsFactory = static fn (): array => [
    ['rowid' => 41, 'option_name' => 'Plugin_Mail', 'autoload' => 'yes', 'option_value' => 'mail-enabled', 'option_id' => 41, 'blog_id' => 2],
    ['rowid' => 11, 'option_name' => 'plugin_alpha', 'autoload' => 'yes', 'option_value' => 'alpha-enabled', 'option_id' => 11, 'blog_id' => 1],
    ['rowid' => 31, 'option_name' => 'Plugin_Forms', 'autoload' => 'yes', 'option_value' => 'forms-enabled', 'option_id' => 31, 'blog_id' => 1],
    ['rowid' => 21, 'option_name' => 'plugin_cache', 'autoload' => 'yes', 'option_value' => 'cache-new', 'option_id' => 21, 'blog_id' => 1],
    ['rowid' => 51, 'option_name' => 'plugin_seo', 'autoload' => 'yes', 'option_value' => 'seo-enabled', 'option_id' => 51, 'blog_id' => 1],
    ['rowid' => 61, 'option_name' => 'plugin_cache', 'autoload' => 'no', 'option_value' => 'cache-disabled', 'option_id' => 61, 'blog_id' => 3],
    ['rowid' => 71, 'option_name' => 'theme_mods', 'autoload' => 'yes', 'option_value' => 'theme', 'option_id' => 71, 'blog_id' => 1],
];

$planForCurrentSource = static fn (
    ?array $preparedSource = null,
    ?array $currentSource = null,
    ?array $preparedRows = null,
    ?array $currentRows = null,
    ?array $predicate = null,
): array => SQLitePlannerStat4PartialExpressionCurrentSourceNextPlan::materialize(
    $preparedSource ?? $preparedSourceFactory(),
    $currentSource ?? $currentSourceFactory(),
    $predicate ?? $GLOBALS['predicate_current_source'],
    $preparedRows ?? $GLOBALS['prepared_rows_current_source'](),
    $currentRows ?? $GLOBALS['current_rows_current_source'](),
    $GLOBALS['order_current_source'],
    $GLOBALS['needed_current_source'],
    [$GLOBALS['lower_current_source']],
);

$GLOBALS['predicate_current_source'] = $predicate;
$GLOBALS['prepared_rows_current_source'] = $preparedRowsFactory;
$GLOBALS['current_rows_current_source'] = $currentRowsFactory;
$GLOBALS['order_current_source'] = $order;
$GLOBALS['needed_current_source'] = $needed;
$GLOBALS['lower_current_source'] = $lower;

$freshPlan = static fn (): array => $planForCurrentSource($preparedSourceFactory(), $preparedSourceFactory(['name' => 'current-fresh-current-source']), $preparedRowsFactory(), $preparedRowsFactory());
$noStat4Plan = static function () use ($currentSourceFactory, $planForCurrentSource): array {
    $current = $currentSourceFactory();
    $current['indexes'][0]['stat4Samples'] = [];

    return $planForCurrentSource(null, $current);
};
$nonCoveringPlan = static function () use ($currentSourceFactory, $planForCurrentSource): array {
    $current = $currentSourceFactory();
    $current['indexes'][0]['coveringColumns'] = ['option_name'];

    return $planForCurrentSource(null, $current);
};

return [
    'planner stat4 partial expression current source status ready' => static fn (TestRunner $t) => $t->same('partial-expression-stat4-current-source-ready', $planForCurrentSource()['status']),
    'planner stat4 partial expression current source selects current' => static fn (TestRunner $t) => $t->same('current', $planForCurrentSource()['selectedSource']),
    'planner stat4 partial expression current source stale prepared' => static fn (TestRunner $t) => $t->same(true, $planForCurrentSource()['stalePreparedStatement']),
    'planner stat4 partial expression current source reprepare required' => static fn (TestRunner $t) => $t->same(true, $planForCurrentSource()['reprepareRequired']),
    'planner stat4 partial expression current source schema changed' => static fn (TestRunner $t) => $t->same(true, $planForCurrentSource()['schemaCookieChanged']),
    'planner stat4 partial expression current source stat4 changed' => static fn (TestRunner $t) => $t->same(true, $planForCurrentSource()['stat4GenerationChanged']),
    'planner stat4 partial expression current source index signature changed' => static fn (TestRunner $t) => $t->same(true, $planForCurrentSource()['indexSignatureChanged']),
    'planner stat4 partial expression current source row signature changed' => static fn (TestRunner $t) => $t->same(true, $planForCurrentSource()['rowSignatureChanged']),
    'planner stat4 partial expression current source prepared row signature length' => static fn (TestRunner $t) => $t->same(64, strlen($planForCurrentSource()['preparedRowSignature'])),
    'planner stat4 partial expression current source current row signature length' => static fn (TestRunner $t) => $t->same(64, strlen($planForCurrentSource()['currentRowSignature'])),
    'planner stat4 partial expression current source selected index' => static fn (TestRunner $t) => $t->same('idx_wp_options_lower_partial_expr_current_source', $planForCurrentSource()['selectedPlan']['name']),
    'planner stat4 partial expression current source selected root' => static fn (TestRunner $t) => $t->same(13344, $planForCurrentSource()['selectedPlan']['rootPage']),
    'planner stat4 partial expression current source partial true' => static fn (TestRunner $t) => $t->same(true, $planForCurrentSource()['selectedPlan']['partial']),
    'planner stat4 partial expression current source covering true' => static fn (TestRunner $t) => $t->same(true, $planForCurrentSource()['selectedPlan']['covering']),
    'planner stat4 partial expression current source stat4 used' => static fn (TestRunner $t) => $t->same(true, $planForCurrentSource()['selectedPlan']['stat4Used']),
    'planner stat4 partial expression current source operator bounded' => static fn (TestRunner $t) => $t->same('range-bounded', $planForCurrentSource()['selectedPlan']['operator']),
    'planner stat4 partial expression current source type lower' => static fn (TestRunner $t) => $t->same('lower', $planForCurrentSource()['selectedPlan']['type']),
    'planner stat4 partial expression current source column option name' => static fn (TestRunner $t) => $t->same('option_name', $planForCurrentSource()['selectedPlan']['column']),
    'planner stat4 partial expression current source estimated rows' => static fn (TestRunner $t) => $t->same(12, $planForCurrentSource()['selectedPlan']['estimatedRows']),
    'planner stat4 partial expression current source covered rows' => static fn (TestRunner $t) => $t->same(5, $planForCurrentSource()['selectedPlan']['coveredRowCount']),
    'planner stat4 partial expression current source partial expression flag' => static fn (TestRunner $t) => $t->same(true, $planForCurrentSource()['selectedPlan']['partialExpressionCurrentSource']),
    'planner stat4 partial expression current source row generation changed flag' => static fn (TestRunner $t) => $t->same(true, $planForCurrentSource()['selectedPlan']['rowGenerationChanged']),
    'planner stat4 partial expression current source deleted blocked' => static fn (TestRunner $t) => $t->same([81], $planForCurrentSource()['selectedPlan']['deletedPreparedRowidsBlocked']),
    'planner stat4 partial expression current source inserted admitted' => static fn (TestRunner $t) => $t->same([11, 41, 61, 71], $planForCurrentSource()['selectedPlan']['insertedCurrentRowidsAdmitted']),
    'planner stat4 partial expression current source updated refreshed' => static fn (TestRunner $t) => $t->same([21], $planForCurrentSource()['selectedPlan']['updatedCurrentRowidsRefreshed']),
    'planner stat4 partial expression current source row fence prepared count' => static fn (TestRunner $t) => $t->same(4, $planForCurrentSource()['rowGenerationFence']['preparedRows']),
    'planner stat4 partial expression current source row fence current count' => static fn (TestRunner $t) => $t->same(7, $planForCurrentSource()['rowGenerationFence']['currentRows']),
    'planner stat4 partial expression current source row fence unchanged' => static fn (TestRunner $t) => $t->same([31, 51], $planForCurrentSource()['rowGenerationFence']['unchangedRowids']),
    'planner stat4 partial expression current source matched rowids sorted' => static fn (TestRunner $t) => $t->same([11, 21, 31, 41, 51], $planForCurrentSource()['cursorTape']['matchedRowids']),
    'planner stat4 partial expression current source matched keys sorted' => static fn (TestRunner $t) => $t->same(['plugin_alpha', 'plugin_cache', 'plugin_forms', 'plugin_mail', 'plugin_seo'], $planForCurrentSource()['cursorTape']['matchedKeys']),
    'planner stat4 partial expression current source blocks deleted rowid' => static fn (TestRunner $t) => $t->same([81], $planForCurrentSource()['cursorTape']['deletedRowidsBlocked']),
    'planner stat4 partial expression current source cursor inserts' => static fn (TestRunner $t) => $t->same([11, 41, 61, 71], $planForCurrentSource()['cursorTape']['insertedRowids']),
    'planner stat4 partial expression current source cursor updates' => static fn (TestRunner $t) => $t->same([21], $planForCurrentSource()['cursorTape']['updatedRowids']),
    'planner stat4 partial expression current source current next first row' => static fn (TestRunner $t) => $t->same(11, $planForCurrentSource()['currentNextRows'][0]['current']['rowid']),
    'planner stat4 partial expression current source current next second next' => static fn (TestRunner $t) => $t->same(31, $planForCurrentSource()['currentNextRows'][1]['next']['rowid']),
    'planner stat4 partial expression current source current next last eof' => static fn (TestRunner $t) => $t->same(null, $planForCurrentSource()['currentNextRows'][4]['next']),
    'planner stat4 partial expression current source uses refreshed payload' => static fn (TestRunner $t) => $t->same('cache-new', $planForCurrentSource()['currentNextRows'][1]['current']['covering']['option_value']),
    'planner stat4 partial expression current source excludes disabled partial miss' => static fn (TestRunner $t) => $t->same(false, in_array(61, $planForCurrentSource()['cursorTape']['matchedRowids'], true)),
    'planner stat4 partial expression current source excludes upper range miss' => static fn (TestRunner $t) => $t->same(false, in_array(71, $planForCurrentSource()['cursorTape']['matchedRowids'], true)),
    'planner stat4 partial expression current source no table lookup' => static fn (TestRunner $t) => $t->same(true, $planForCurrentSource()['tableLookupElided']),
    'planner stat4 partial expression current source no deferred seek' => static fn (TestRunner $t) => $t->same(null, $planForCurrentSource()['deferredTableSeekOpcode']),
    'planner stat4 partial expression current source row fence keeps table lookup elided' => static fn (TestRunner $t) => $t->same(true, $planForCurrentSource()['cursorTape']['tableLookupElidedAfterRowFence']),
    'planner stat4 partial expression current source program opens partial expression index' => static fn (TestRunner $t) => $t->same('partial-expression-index', $planForCurrentSource()['cursorTape']['program'][0]['source']),
    'planner stat4 partial expression current source program fences source' => static fn (TestRunner $t) => $t->same('FenceCurrentSource', $planForCurrentSource()['cursorTape']['program'][1]['opcode']),
    'planner stat4 partial expression current source program filters deleted' => static fn (TestRunner $t) => $t->same(['opcode' => 'FilterDeletedRowids', 'rowids' => [81]], $planForCurrentSource()['cursorTape']['program'][4]),
    'planner stat4 partial expression current source program reads current covering' => static fn (TestRunner $t) => $t->same('current-covering-index', $planForCurrentSource()['cursorTape']['program'][5]['source']),
    'planner stat4 partial expression current source current fence row generation' => static fn (TestRunner $t) => $t->same(16, $planForCurrentSource()['currentSourceFence']['rowGeneration']),
    'planner stat4 partial expression current source current fence signature length' => static fn (TestRunner $t) => $t->same(64, strlen($planForCurrentSource()['currentSourceFence']['rowSignature'])),
    'planner stat4 partial expression current source detail' => static fn (TestRunner $t) => $t->contains('REPREPARE PARTIAL EXPRESSION STAT4 CURRENT SOURCE', $planForCurrentSource()['detail']),
    'planner stat4 partial expression current source dependency marker' => static fn (TestRunner $t) => $t->true(in_array('sqlite-sqlplanner-stat4-partial-expression-current-source', $planForCurrentSource()['dependencies'], true)),
    'planner stat4 partial expression current source dependency closure' => static fn (TestRunner $t) => $t->contains('no new support component needed', $planForCurrentSource()['dependency_closure']),
    'planner stat4 partial expression current source non overlap' => static fn (TestRunner $t) => $t->contains('avoids accepted range-cost', $planForCurrentSource()['non_overlap']),
    'planner stat4 partial expression current source fresh reuses prepared' => static fn (TestRunner $t) => $t->same('prepared', $freshPlan()['selectedSource']),
    'planner stat4 partial expression current source fresh no row signature change' => static fn (TestRunner $t) => $t->same(false, $freshPlan()['rowSignatureChanged']),
    'planner stat4 partial expression current source fresh no reprepare' => static fn (TestRunner $t) => $t->same(false, $freshPlan()['reprepareRequired']),
    'planner stat4 partial expression current source no stat4 requires next' => static fn (TestRunner $t) => $t->same('requires-next-stage', $noStat4Plan()['status']),
    'planner stat4 partial expression current source non covering requires next' => static fn (TestRunner $t) => $t->same('requires-next-stage', $nonCoveringPlan()['status']),
    'planner stat4 partial expression current source validates rowid' => static function (TestRunner $t) use ($preparedRowsFactory, $currentRowsFactory, $planForCurrentSource): void {
        $bad = $preparedRowsFactory();
        $bad[0]['rowid'] = -1;
        $t->throws(InvalidArgumentException::class, static fn () => $planForCurrentSource(null, null, $bad, $currentRowsFactory()));
    },
    'planner stat4 partial expression current source validates row generation' => static function (TestRunner $t) use ($currentSourceFactory, $planForCurrentSource): void {
        $bad = $currentSourceFactory();
        $bad['rowGeneration'] = -1;
        $t->throws(InvalidArgumentException::class, static fn () => $planForCurrentSource(null, $bad));
    },
];

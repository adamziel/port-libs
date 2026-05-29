<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePlannerSubqueryPartialIndexCurrentSourceNextPlan;

$expr = static fn (string $function, string $column): array => ['function' => $function, 'column' => $column];

$preparedSource106 = static fn (): array => [
    'name' => 'prepared-subquery-partial-current_source',
    'schemaCookie' => 104,
    'stat4Generation' => 41,
    'indexes' => [[
        'name' => 'idx_wp_options_lower_plugin_subquery_current_source',
        'rootPage' => 10601,
        'estimatedRows' => 80,
        'coveringColumns' => ['option_name', 'autoload', 'option_value'],
        'stat4Samples' => [
            ['neq' => '1', 'nlt' => '0', 'ndlt' => '0', 'sample' => ['plugin_cache']],
            ['neq' => '2', 'nlt' => '1', 'ndlt' => '1', 'sample' => ['plugin_forms']],
            ['neq' => '3', 'nlt' => '3', 'ndlt' => '2', 'sample' => ['plugin_security']],
        ],
        'sql' => "CREATE INDEX idx_wp_options_lower_plugin_subquery_current_source ON wp_options(lower(option_name), autoload, option_value) WHERE lower(option_name) >= 'plugin_'",
    ], [
        'name' => 'idx_wp_options_lower_plain_subquery_current_source',
        'rootPage' => 10602,
        'estimatedRows' => 20,
        'coveringColumns' => ['option_name'],
        'sql' => 'CREATE INDEX idx_wp_options_lower_plain_subquery_current_source ON wp_options(lower(option_name))',
    ]],
];

$currentSource106 = static function () use ($preparedSource106): array {
    $source = $preparedSource106();
    $source['name'] = 'current-subquery-partial-current_source';
    $source['schemaCookie'] = 106;
    $source['stat4Generation'] = 43;
    $source['indexes'][0]['rootPage'] = 10610;
    $source['indexes'][0]['stat4Samples'] = [
        ['neq' => '1', 'nlt' => '0', 'ndlt' => '0', 'sample' => ['plugin_cache']],
        ['neq' => '1', 'nlt' => '1', 'ndlt' => '1', 'sample' => ['plugin_editor']],
        ['neq' => '2', 'nlt' => '2', 'ndlt' => '2', 'sample' => ['plugin_forms']],
        ['neq' => '1', 'nlt' => '4', 'ndlt' => '3', 'sample' => ['plugin_security']],
    ];

    return $source;
};

$predicate106 = static fn (array $rows = null, string $column = 'selected_name'): array => [
    'operator' => 'IN_SUBQUERY',
    'left' => $GLOBALS['expr_current_source']('lower', 'option_name'),
    'subquery' => [
        'sourceName' => 'active_plugin_names',
        'column' => $column,
        'rows' => $rows ?? [
            ['selected_name' => 'plugin_cache'],
            ['selected_name' => 'plugin_forms'],
            ['selected_name' => 'plugin_cache'],
            ['selected_name' => 'plugin_security'],
        ],
        'correlatedOuterColumns' => ['blog_id', 'autoload'],
    ],
];

$plan106 = static fn (
    ?array $prepared = null,
    ?array $current = null,
    ?array $predicate = null,
    array $needed = ['option_name', 'autoload', 'option_value'],
): array => SQLitePlannerSubqueryPartialIndexCurrentSourceNextPlan::materializeSubqueryPartialIndexPlan(
    $prepared ?? $GLOBALS['prepared_source_current_source'](),
    $current ?? $GLOBALS['current_source_current_source'](),
    $predicate ?? $GLOBALS['predicate_current_source'](),
    $needed,
);

$GLOBALS['expr_current_source'] = $expr;
$GLOBALS['prepared_source_current_source'] = $preparedSource106;
$GLOBALS['current_source_current_source'] = $currentSource106;
$GLOBALS['predicate_current_source'] = $predicate106;

$freshPlan = static fn (): array => $plan106($preparedSource106(), $preparedSource106());
$nullPlan = static fn (): array => $plan106(null, null, $predicate106([
    ['selected_name' => 'plugin_cache'],
    ['selected_name' => null],
    ['selected_name' => 'plugin_forms'],
]));
$outsidePlan = static fn (): array => $plan106(null, null, $predicate106([
    ['selected_name' => 'plugin_cache'],
    ['selected_name' => 'admin_email'],
]));
$uncoveredPlan = static fn (): array => $plan106(null, null, null, ['option_name', 'missing_meta']);

$tests = [
    'planner subquery partial index current_source current_source status ready' => static fn (TestRunner $t) => $t->same('subquery-partial-index-current-source-ready', $plan106()['status']),
    'planner subquery partial index current_source current_source selects current' => static fn (TestRunner $t) => $t->same('current', $plan106()['selectedSource']),
    'planner subquery partial index current_source current_source stale prepared' => static fn (TestRunner $t) => $t->same(true, $plan106()['stalePreparedStatement']),
    'planner subquery partial index current_source current_source requires reprepare' => static fn (TestRunner $t) => $t->same(true, $plan106()['reprepareRequired']),
    'planner subquery partial index current_source current_source schema changed' => static fn (TestRunner $t) => $t->same(true, $plan106()['schemaCookieChanged']),
    'planner subquery partial index current_source current_source stat4 changed' => static fn (TestRunner $t) => $t->same(true, $plan106()['stat4GenerationChanged']),
    'planner subquery partial index current_source current_source signature changed' => static fn (TestRunner $t) => $t->same(true, $plan106()['indexSignatureChanged']),
    'planner subquery partial index current_source current_source selected index' => static fn (TestRunner $t) => $t->same('idx_wp_options_lower_plugin_subquery_current_source', $plan106()['selectedPlan']['name']),
    'planner subquery partial index current_source current_source selected root current' => static fn (TestRunner $t) => $t->same(10610, $plan106()['selectedPlan']['rootPage']),
    'planner subquery partial index current_source current_source prepared root' => static fn (TestRunner $t) => $t->same(10601, $plan106()['preparedSource']['rootPage']),
    'planner subquery partial index current_source current_source current root' => static fn (TestRunner $t) => $t->same(10610, $plan106()['currentSource']['rootPage']),
    'planner subquery partial index current_source current_source selected is partial' => static fn (TestRunner $t) => $t->same(true, $plan106()['selectedPlan']['partial']),
    'planner subquery partial index current_source current_source partial implied' => static fn (TestRunner $t) => $t->same(true, $plan106()['selectedPlan']['partialPredicateImplied']),
    'planner subquery partial index current_source current_source covering true' => static fn (TestRunner $t) => $t->same(true, $plan106()['selectedPlan']['covering']),
    'planner subquery partial index current_source current_source next source covering' => static fn (TestRunner $t) => $t->same('covering-index', $plan106()['selectedPlan']['nextSource']),
    'planner subquery partial index current_source current_source no deferred table lookup' => static fn (TestRunner $t) => $t->same(false, $plan106()['selectedPlan']['deferredTableLookup']),
    'planner subquery partial index current_source current_source subquery source' => static fn (TestRunner $t) => $t->same('active_plugin_names', $plan106()['subquery']['source']),
    'planner subquery partial index current_source current_source subquery column' => static fn (TestRunner $t) => $t->same('selected_name', $plan106()['subquery']['column']),
    'planner subquery partial index current_source current_source subquery row count' => static fn (TestRunner $t) => $t->same(4, $plan106()['subquery']['rowCount']),
    'planner subquery partial index current_source current_source deduped values' => static fn (TestRunner $t) => $t->same(['plugin_cache', 'plugin_forms', 'plugin_security'], $plan106()['subquery']['values']),
    'planner subquery partial index current_source current_source duplicate count' => static fn (TestRunner $t) => $t->same(1, $plan106()['subquery']['duplicatesRemoved']),
    'planner subquery partial index current_source current_source null absent' => static fn (TestRunner $t) => $t->same(false, $plan106()['subquery']['nullSeen']),
    'planner subquery partial index current_source current_source correlated columns' => static fn (TestRunner $t) => $t->same(['blog_id', 'autoload'], $plan106()['subquery']['correlatedOuterColumns']),
    'planner subquery partial index current_source current_source cursor source' => static fn (TestRunner $t) => $t->same('current', $plan106()['cursorTape']['source']),
    'planner subquery partial index current_source current_source cursor index' => static fn (TestRunner $t) => $t->same('idx_wp_options_lower_plugin_subquery_current_source', $plan106()['cursorTape']['indexName']),
    'planner subquery partial index current_source current_source cursor root' => static fn (TestRunner $t) => $t->same(10610, $plan106()['cursorTape']['rootPage']),
    'planner subquery partial index current_source current_source seek opcode' => static fn (TestRunner $t) => $t->same('SeekGE', $plan106()['cursorTape']['seekOpcode']),
    'planner subquery partial index current_source current_source stop opcode' => static fn (TestRunner $t) => $t->same('IdxGT', $plan106()['cursorTape']['stopOpcode']),
    'planner subquery partial index current_source current_source next opcode' => static fn (TestRunner $t) => $t->same('Next', $plan106()['cursorTape']['nextOpcode']),
    'planner subquery partial index current_source current_source cursor value count' => static fn (TestRunner $t) => $t->same(3, $plan106()['cursorTape']['subqueryValueCount']),
    'planner subquery partial index current_source current_source cursor values' => static fn (TestRunner $t) => $t->same(['plugin_cache', 'plugin_forms', 'plugin_security'], $plan106()['cursorTape']['subqueryValues']),
    'planner subquery partial index current_source current_source cursor dedupes values' => static fn (TestRunner $t) => $t->same(true, $plan106()['cursorTape']['dedupeSubqueryValues']),
    'planner subquery partial index current_source current_source cursor filters nulls' => static fn (TestRunner $t) => $t->same(true, $plan106()['cursorTape']['nullFilteredBeforeIndexSeek']),
    'planner subquery partial index current_source current_source cursor partial implied' => static fn (TestRunner $t) => $t->same(true, $plan106()['cursorTape']['partialPredicateImplied']),
    'planner subquery partial index current_source current_source table lookup elided' => static fn (TestRunner $t) => $t->same(true, $plan106()['cursorTape']['tableLookupElided']),
    'planner subquery partial index current_source current_source no deferred seek' => static fn (TestRunner $t) => $t->same(null, $plan106()['cursorTape']['deferredSeekOpcode']),
    'planner subquery partial index current_source current_source program opens index' => static fn (TestRunner $t) => $t->same('OpenRead', $plan106()['cursorTape']['program'][0]['opcode']),
    'planner subquery partial index current_source current_source program opens ephemeral' => static fn (TestRunner $t) => $t->same('OpenEphemeral', $plan106()['cursorTape']['program'][1]['opcode']),
    'planner subquery partial index current_source current_source program seeks' => static fn (TestRunner $t) => $t->same('SeekGE', $plan106()['cursorTape']['program'][3]['opcode']),
    'planner subquery partial index current_source current_source program stops' => static fn (TestRunner $t) => $t->same('IdxGT', $plan106()['cursorTape']['program'][4]['opcode']),
    'planner subquery partial index current_source current_source program reads index column' => static fn (TestRunner $t) => $t->same(['opcode' => 'Column', 'source' => 'index', 'column' => 'option_name'], $plan106()['cursorTape']['program'][5]),
    'planner subquery partial index current_source current_source program advances subquery values' => static fn (TestRunner $t) => $t->same(['opcode' => 'Next', 'source' => 'subquery-values'], $plan106()['cursorTape']['program'][8]),
    'planner subquery partial index current_source current_source prepared signature hash' => static fn (TestRunner $t) => $t->same(64, strlen($plan106()['preparedSource']['indexSignature'])),
    'planner subquery partial index current_source current_source current signature hash' => static fn (TestRunner $t) => $t->same(64, strlen($plan106()['currentSource']['indexSignature'])),
    'planner subquery partial index current_source current_source subquery signature hash' => static fn (TestRunner $t) => $t->same(64, strlen($plan106()['currentSourceFence']['subquerySignature'])),
    'planner subquery partial index current_source current_source detail current_source' => static fn (TestRunner $t) => $t->contains('REPREPARE SUBQUERY PARTIAL INDEX USING current-subquery-partial-current_source', $plan106()['detail']),
    'planner subquery partial index current_source current_source detail names partial' => static fn (TestRunner $t) => $t->contains('PARTIAL-PREDICATE IMPLIED', $plan106()['detail']),
    'planner subquery partial index current_source current_source dependency closure' => static fn (TestRunner $t) => $t->contains('no new support component needed', $plan106()['dependency_closure']),
    'planner subquery partial index current_source current_source non overlap' => static fn (TestRunner $t) => $t->contains('IN-subquery values', $plan106()['non_overlap']),
    'planner subquery partial index current_source current_source fresh reuses prepared' => static fn (TestRunner $t) => $t->same('prepared', $freshPlan()['selectedSource']),
    'planner subquery partial index current_source current_source fresh does not reprepare' => static fn (TestRunner $t) => $t->same(false, $freshPlan()['reprepareRequired']),
    'planner subquery partial index current_source current_source fresh root page' => static fn (TestRunner $t) => $t->same(10601, $freshPlan()['selectedPlan']['rootPage']),
    'planner subquery partial index current_source current_source null subquery blocks partial' => static fn (TestRunner $t) => $t->same('requires-next-stage', $nullPlan()['status']),
    'planner subquery partial index current_source current_source null seen recorded' => static fn (TestRunner $t) => $t->same(true, $nullPlan()['subquery']['nullSeen']),
    'planner subquery partial index current_source current_source null plan blocks selected partial' => static fn (TestRunner $t) => $t->same(false, $nullPlan()['selectedPlan']['partialPredicateImplied']),
    'planner subquery partial index current_source current_source outside value falls back to plain index' => static fn (TestRunner $t) => $t->same('idx_wp_options_lower_plain_subquery_current_source', $outsidePlan()['selectedPlan']['name']),
    'planner subquery partial index current_source current_source outside requires next stage' => static fn (TestRunner $t) => $t->same('requires-next-stage', $outsidePlan()['status']),
    'planner subquery partial index current_source current_source uncovered defers lookup' => static fn (TestRunner $t) => $t->same('requires-next-stage', $uncoveredPlan()['status']),
    'planner subquery partial index current_source current_source uncovered deferred seek' => static fn (TestRunner $t) => $t->same('DeferredSeek', $uncoveredPlan()['cursorTape']['deferredSeekOpcode']),
    'planner subquery partial index current_source current_source validates predicate operator' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => $plan106(null, null, ['operator' => 'IN'])),
    'planner subquery partial index current_source current_source validates subquery rows' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => $plan106(null, null, $predicate106(['bad']))),
    'planner subquery partial index current_source current_source validates projected column' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => $plan106(null, null, $predicate106([['other' => 'plugin_cache']]))),
    'planner subquery partial index current_source current_source validates non null values' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => $plan106(null, null, $predicate106([['selected_name' => null]]))),
    'planner subquery partial index current_source current_source validates current_source list' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => $plan106(null, ['name' => 'bad', 'schemaCookie' => 1, 'stat4Generation' => 1, 'indexes' => ['bad' => []]])),
];

return $tests;

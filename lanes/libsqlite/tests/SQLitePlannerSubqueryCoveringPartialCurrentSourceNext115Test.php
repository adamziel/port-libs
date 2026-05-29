<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePlannerSubqueryCoveringPartialCurrentSourceNextPlan;

$expr115 = static fn (string $function, string $column): array => ['function' => $function, 'column' => $column];

$preparedSource115 = static fn (): array => [
    'name' => 'prepared-subquery-covering-partial-next115',
    'schemaCookie' => 114,
    'stat4Generation' => 51,
    'indexes' => [[
        'name' => 'idx_wp_options_lower_plugin_subquery_covering_next115',
        'rootPage' => 11501,
        'estimatedRows' => 60,
        'coveringColumns' => ['option_name'],
        'stat4Samples' => [
            ['neq' => '1', 'nlt' => '0', 'ndlt' => '0', 'sample' => ['plugin_cache']],
            ['neq' => '2', 'nlt' => '1', 'ndlt' => '1', 'sample' => ['plugin_forms']],
            ['neq' => '2', 'nlt' => '3', 'ndlt' => '2', 'sample' => ['plugin_security']],
        ],
        'sql' => "CREATE INDEX idx_wp_options_lower_plugin_subquery_covering_next115 ON wp_options(lower(option_name)) WHERE lower(option_name) >= 'plugin_'",
    ], [
        'name' => 'idx_wp_options_lower_plain_subquery_covering_next115',
        'rootPage' => 11502,
        'estimatedRows' => 12,
        'coveringColumns' => ['option_name'],
        'sql' => 'CREATE INDEX idx_wp_options_lower_plain_subquery_covering_next115 ON wp_options(lower(option_name))',
    ]],
];

$currentSource115 = static function () use ($preparedSource115): array {
    $source = $preparedSource115();
    $source['name'] = 'current-subquery-covering-partial-next115';
    $source['schemaCookie'] = 115;
    $source['stat4Generation'] = 52;
    $source['indexes'][0]['rootPage'] = 11510;
    $source['indexes'][0]['stat4Samples'] = [
        ['neq' => '1', 'nlt' => '0', 'ndlt' => '0', 'sample' => ['plugin_cache']],
        ['neq' => '1', 'nlt' => '1', 'ndlt' => '1', 'sample' => ['plugin_editor']],
        ['neq' => '2', 'nlt' => '2', 'ndlt' => '2', 'sample' => ['plugin_forms']],
        ['neq' => '1', 'nlt' => '4', 'ndlt' => '3', 'sample' => ['plugin_security']],
    ];

    return $source;
};

$predicate115 = static fn (array $rows = null, array $projected = ['selected_name', 'option_name', 'autoload', 'option_value'], string $column = 'selected_name'): array => [
    'operator' => 'IN_SUBQUERY',
    'left' => $GLOBALS['expr_next115']('lower', 'option_name'),
    'subquery' => [
        'sourceName' => 'active_plugin_option_payloads',
        'column' => $column,
        'projectedColumns' => $projected,
        'rows' => $rows ?? [
            ['selected_name' => 'plugin_cache', 'option_name' => 'plugin_cache', 'autoload' => 'yes', 'option_value' => '{"ttl":3600}'],
            ['selected_name' => 'plugin_forms', 'option_name' => 'plugin_forms', 'autoload' => 'no', 'option_value' => '{"enabled":true}'],
            ['selected_name' => 'plugin_cache', 'option_name' => 'plugin_cache', 'autoload' => 'yes', 'option_value' => '{"ttl":3600}'],
            ['selected_name' => 'plugin_security', 'option_name' => 'plugin_security', 'autoload' => 'yes', 'option_value' => '{"rules":4}'],
        ],
        'correlatedOuterColumns' => ['blog_id', 'autoload'],
    ],
];

$plan115 = static fn (
    ?array $prepared = null,
    ?array $current = null,
    ?array $predicate = null,
    array $needed = ['option_name', 'autoload', 'option_value'],
): array => SQLitePlannerSubqueryCoveringPartialCurrentSourceNextPlan::materializeNext115(
    $prepared ?? $GLOBALS['prepared_source_next115'](),
    $current ?? $GLOBALS['current_source_next115'](),
    $predicate ?? $GLOBALS['predicate_next115'](),
    $needed,
);

$GLOBALS['expr_next115'] = $expr115;
$GLOBALS['prepared_source_next115'] = $preparedSource115;
$GLOBALS['current_source_next115'] = $currentSource115;
$GLOBALS['predicate_next115'] = $predicate115;

$freshPlan115 = static fn (): array => $plan115($preparedSource115(), $preparedSource115());
$missingPayloadPlan115 = static fn (): array => $plan115(null, null, $predicate115(null, ['selected_name', 'option_name', 'autoload']));
$nullPlan115 = static fn (): array => $plan115(null, null, $predicate115([
    ['selected_name' => 'plugin_cache', 'option_name' => 'plugin_cache', 'autoload' => 'yes', 'option_value' => '{}'],
    ['selected_name' => null, 'option_name' => null, 'autoload' => 'no', 'option_value' => '{}'],
    ['selected_name' => 'plugin_forms', 'option_name' => 'plugin_forms', 'autoload' => 'no', 'option_value' => '{}'],
]));
$outsidePlan115 = static fn (): array => $plan115(null, null, $predicate115([
    ['selected_name' => 'plugin_cache', 'option_name' => 'plugin_cache', 'autoload' => 'yes', 'option_value' => '{}'],
    ['selected_name' => 'admin_email', 'option_name' => 'admin_email', 'autoload' => 'yes', 'option_value' => 'admin@example.test'],
]));

$tests = [
    'planner subquery covering partial current source next115 status ready' => static fn (TestRunner $t) => $t->same('subquery-covering-partial-current-source-ready', $plan115()['status']),
    'planner subquery covering partial current source next115 selects current' => static fn (TestRunner $t) => $t->same('current', $plan115()['selectedSource']),
    'planner subquery covering partial current source next115 stale prepared' => static fn (TestRunner $t) => $t->same(true, $plan115()['stalePreparedStatement']),
    'planner subquery covering partial current source next115 requires reprepare' => static fn (TestRunner $t) => $t->same(true, $plan115()['reprepareRequired']),
    'planner subquery covering partial current source next115 schema changed' => static fn (TestRunner $t) => $t->same(true, $plan115()['schemaCookieChanged']),
    'planner subquery covering partial current source next115 stat4 changed' => static fn (TestRunner $t) => $t->same(true, $plan115()['stat4GenerationChanged']),
    'planner subquery covering partial current source next115 signature changed' => static fn (TestRunner $t) => $t->same(true, $plan115()['indexSignatureChanged']),
    'planner subquery covering partial current source next115 selected index' => static fn (TestRunner $t) => $t->same('idx_wp_options_lower_plugin_subquery_covering_next115', $plan115()['selectedPlan']['name']),
    'planner subquery covering partial current source next115 selected root current' => static fn (TestRunner $t) => $t->same(11510, $plan115()['selectedPlan']['rootPage']),
    'planner subquery covering partial current source next115 prepared root' => static fn (TestRunner $t) => $t->same(11501, $plan115()['preparedSource']['rootPage']),
    'planner subquery covering partial current source next115 current root' => static fn (TestRunner $t) => $t->same(11510, $plan115()['currentSource']['rootPage']),
    'planner subquery covering partial current source next115 selected is partial' => static fn (TestRunner $t) => $t->same(true, $plan115()['selectedPlan']['partial']),
    'planner subquery covering partial current source next115 partial implied' => static fn (TestRunner $t) => $t->same(true, $plan115()['selectedPlan']['partialPredicateImplied']),
    'planner subquery covering partial current source next115 subquery covering true' => static fn (TestRunner $t) => $t->same(true, $plan115()['selectedPlan']['subqueryCovering']),
    'planner subquery covering partial current source next115 index itself not covering payload' => static fn (TestRunner $t) => $t->same(false, $plan115()['selectedPlan']['covering']),
    'planner subquery covering partial current source next115 next source payload' => static fn (TestRunner $t) => $t->same('subquery-covering-payload', $plan115()['selectedPlan']['nextSource']),
    'planner subquery covering partial current source next115 no deferred table lookup' => static fn (TestRunner $t) => $t->same(false, $plan115()['selectedPlan']['deferredTableLookup']),
    'planner subquery covering partial current source next115 projected columns' => static fn (TestRunner $t) => $t->same(['selected_name', 'option_name', 'autoload', 'option_value'], $plan115()['subquery']['projectedColumns']),
    'planner subquery covering partial current source next115 key column' => static fn (TestRunner $t) => $t->same('selected_name', $plan115()['subquery']['keyColumn']),
    'planner subquery covering partial current source next115 subquery source' => static fn (TestRunner $t) => $t->same('active_plugin_option_payloads', $plan115()['subquery']['source']),
    'planner subquery covering partial current source next115 subquery row count' => static fn (TestRunner $t) => $t->same(4, $plan115()['subquery']['rowCount']),
    'planner subquery covering partial current source next115 deduped values' => static fn (TestRunner $t) => $t->same(['plugin_cache', 'plugin_forms', 'plugin_security'], $plan115()['subquery']['values']),
    'planner subquery covering partial current source next115 duplicate count' => static fn (TestRunner $t) => $t->same(1, $plan115()['subquery']['duplicatesRemoved']),
    'planner subquery covering partial current source next115 null absent' => static fn (TestRunner $t) => $t->same(false, $plan115()['subquery']['nullSeen']),
    'planner subquery covering partial current source next115 covering rows count' => static fn (TestRunner $t) => $t->same(3, count($plan115()['subquery']['coveringRows'])),
    'planner subquery covering partial current source next115 first covering row payload' => static fn (TestRunner $t) => $t->same(['selected_name' => 'plugin_cache', 'option_name' => 'plugin_cache', 'autoload' => 'yes', 'option_value' => '{"ttl":3600}'], $plan115()['subquery']['coveringRows'][0]),
    'planner subquery covering partial current source next115 correlated columns' => static fn (TestRunner $t) => $t->same(['blog_id', 'autoload'], $plan115()['subquery']['correlatedOuterColumns']),
    'planner subquery covering partial current source next115 cursor source' => static fn (TestRunner $t) => $t->same('current', $plan115()['cursorTape']['source']),
    'planner subquery covering partial current source next115 cursor index' => static fn (TestRunner $t) => $t->same('idx_wp_options_lower_plugin_subquery_covering_next115', $plan115()['cursorTape']['indexName']),
    'planner subquery covering partial current source next115 cursor root' => static fn (TestRunner $t) => $t->same(11510, $plan115()['cursorTape']['rootPage']),
    'planner subquery covering partial current source next115 seek opcode' => static fn (TestRunner $t) => $t->same('SeekGE', $plan115()['cursorTape']['seekOpcode']),
    'planner subquery covering partial current source next115 stop opcode' => static fn (TestRunner $t) => $t->same('IdxGT', $plan115()['cursorTape']['stopOpcode']),
    'planner subquery covering partial current source next115 cursor value count' => static fn (TestRunner $t) => $t->same(3, $plan115()['cursorTape']['subqueryValueCount']),
    'planner subquery covering partial current source next115 cursor covering true' => static fn (TestRunner $t) => $t->same(true, $plan115()['cursorTape']['subqueryCovering']),
    'planner subquery covering partial current source next115 table lookup elided' => static fn (TestRunner $t) => $t->same(true, $plan115()['cursorTape']['tableLookupElided']),
    'planner subquery covering partial current source next115 no deferred seek' => static fn (TestRunner $t) => $t->same(null, $plan115()['cursorTape']['deferredSeekOpcode']),
    'planner subquery covering partial current source next115 program opens index' => static fn (TestRunner $t) => $t->same('OpenRead', $plan115()['cursorTape']['program'][0]['opcode']),
    'planner subquery covering partial current source next115 program opens payload ephemeral' => static fn (TestRunner $t) => $t->same('subquery-covering-rows', $plan115()['cursorTape']['program'][1]['source']),
    'planner subquery covering partial current source next115 program seeks' => static fn (TestRunner $t) => $t->same('SeekGE', $plan115()['cursorTape']['program'][3]['opcode']),
    'planner subquery covering partial current source next115 program reads payload column' => static fn (TestRunner $t) => $t->same(['opcode' => 'Column', 'source' => 'subquery-covering-row', 'column' => 'option_value'], $plan115()['cursorTape']['program'][7]),
    'planner subquery covering partial current source next115 prepared signature hash' => static fn (TestRunner $t) => $t->same(64, strlen($plan115()['preparedSource']['indexSignature'])),
    'planner subquery covering partial current source next115 current signature hash' => static fn (TestRunner $t) => $t->same(64, strlen($plan115()['currentSource']['indexSignature'])),
    'planner subquery covering partial current source next115 subquery signature hash' => static fn (TestRunner $t) => $t->same(64, strlen($plan115()['currentSourceFence']['subquerySignature'])),
    'planner subquery covering partial current source next115 detail current source' => static fn (TestRunner $t) => $t->contains('REPREPARE SUBQUERY COVERING PARTIAL INDEX USING current-subquery-covering-partial-next115', $plan115()['detail']),
    'planner subquery covering partial current source next115 detail names payload covering' => static fn (TestRunner $t) => $t->contains('SUBQUERY-COVERING', $plan115()['detail']),
    'planner subquery covering partial current source next115 dependency closure' => static fn (TestRunner $t) => $t->contains('no new support component needed', $plan115()['dependency_closure']),
    'planner subquery covering partial current source next115 non overlap' => static fn (TestRunner $t) => $t->contains('subquery-projection covering payload', $plan115()['non_overlap']),
    'planner subquery covering partial current source next115 fresh reuses prepared' => static fn (TestRunner $t) => $t->same('prepared', $freshPlan115()['selectedSource']),
    'planner subquery covering partial current source next115 fresh root page' => static fn (TestRunner $t) => $t->same(11501, $freshPlan115()['selectedPlan']['rootPage']),
    'planner subquery covering partial current source next115 missing payload requires next stage' => static fn (TestRunner $t) => $t->same('requires-next-stage', $missingPayloadPlan115()['status']),
    'planner subquery covering partial current source next115 missing payload records column' => static fn (TestRunner $t) => $t->same(['option_value'], $missingPayloadPlan115()['selectedPlan']['missingSubqueryCoveringColumns']),
    'planner subquery covering partial current source next115 missing payload defers seek' => static fn (TestRunner $t) => $t->same('DeferredSeek', $missingPayloadPlan115()['cursorTape']['deferredSeekOpcode']),
    'planner subquery covering partial current source next115 null subquery blocks partial' => static fn (TestRunner $t) => $t->same('requires-next-stage', $nullPlan115()['status']),
    'planner subquery covering partial current source next115 null seen recorded' => static fn (TestRunner $t) => $t->same(true, $nullPlan115()['subquery']['nullSeen']),
    'planner subquery covering partial current source next115 outside value falls back to plain index' => static fn (TestRunner $t) => $t->same('idx_wp_options_lower_plain_subquery_covering_next115', $outsidePlan115()['selectedPlan']['name']),
    'planner subquery covering partial current source next115 validates predicate operator' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => $plan115(null, null, ['operator' => 'IN'])),
    'planner subquery covering partial current source next115 validates projected column list' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => $plan115(null, null, $predicate115(null, ['option_name', 'autoload']))),
    'planner subquery covering partial current source next115 validates missing payload cell' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => $plan115(null, null, $predicate115([['selected_name' => 'plugin_cache', 'option_name' => 'plugin_cache', 'autoload' => 'yes']], ['selected_name', 'option_name', 'autoload', 'option_value']))),
    'planner subquery covering partial current source next115 validates source index list' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => $plan115(null, ['name' => 'bad', 'schemaCookie' => 1, 'stat4Generation' => 1, 'indexes' => ['bad' => []]])),
];

return $tests;

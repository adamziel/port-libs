<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePlannerStat4ExpressionCoveringCurrentSourceNextPlan;

$expr144 = static fn (string $function, string $column): array => ['function' => $function, 'column' => $column];
$column144 = static fn (string $name): array => ['column' => $name];
$point144 = static fn (array $left, mixed $value): array => ['operator' => '=', 'left' => $left, 'right' => $value];
$and144 = static fn (array ...$terms): array => ['operator' => 'AND', 'terms' => $terms];

$lower144 = $expr144('lower', 'option_name');
$predicate144 = $and144($point144($lower144, 'plugin_cache'), $point144($column144('autoload'), 'yes'));
$order144 = [$lower144, ['column' => 'option_id']];
$needed144 = ['option_name', 'autoload', 'option_value', 'option_id', 'blog_id'];
$neededExpressions144 = [$lower144];

$preparedSource144 = static function (array $overrides = []): array {
    return $overrides + [
        'name' => 'prepared-stat4-expression-covering-current-source-next144',
        'schemaCookie' => 1440,
        'stat4Generation' => 81,
        'indexes' => [[
            'name' => 'idx_wp_options_lower_point_covering_stat4_next144',
            'rootPage' => 14401,
            'estimatedRows' => 180,
            'coveringColumns' => ['option_name', 'autoload', 'option_value', 'option_id', 'blog_id'],
            'stat4Samples' => [
                ['neq' => '2 1', 'nlt' => '0 0', 'ndlt' => '0 0', 'sample' => ['plugin_analytics', 10]],
                ['neq' => '3 1', 'nlt' => '2 1', 'ndlt' => '1 1', 'sample' => ['plugin_cache', 20]],
                ['neq' => '1 1', 'nlt' => '5 2', 'ndlt' => '2 2', 'sample' => ['plugin_forms', 30]],
                ['neq' => '1 1', 'nlt' => '6 3', 'ndlt' => '3 3', 'sample' => ['plugin_mail', 40]],
            ],
            'sql' => "CREATE INDEX idx_wp_options_lower_point_covering_stat4_next144 ON wp_options(lower(option_name), option_id, option_value, blog_id) WHERE autoload = 'yes'",
        ]],
        'rows' => [
            ['rowid' => 101, 'option_id' => 101, 'option_name' => 'Plugin_Cache', 'autoload' => 'yes', 'option_value' => 'prepared-cache-a', 'blog_id' => 1],
            ['rowid' => 102, 'option_id' => 102, 'option_name' => 'plugin_cache', 'autoload' => 'yes', 'option_value' => 'prepared-cache-b', 'blog_id' => 2],
            ['rowid' => 103, 'option_id' => 103, 'option_name' => 'plugin_forms', 'autoload' => 'yes', 'option_value' => 'prepared-forms', 'blog_id' => 1],
            ['rowid' => 104, 'option_id' => 104, 'option_name' => 'plugin_cache', 'autoload' => 'no', 'option_value' => 'prepared-disabled', 'blog_id' => 3],
        ],
    ];
};

$currentSource144 = static function () use ($preparedSource144): array {
    $source = $preparedSource144([
        'name' => 'current-stat4-expression-covering-current-source-next144',
        'schemaCookie' => 1447,
        'stat4Generation' => 88,
    ]);
    $source['indexes'][0]['rootPage'] = 14477;
    $source['indexes'][0]['estimatedRows'] = 42;
    $source['indexes'][0]['stat4Samples'] = [
        ['neq' => '1 1', 'nlt' => '0 0', 'ndlt' => '0 0', 'sample' => ['plugin_analytics', 201]],
        ['neq' => '2 1', 'nlt' => '1 1', 'ndlt' => '1 1', 'sample' => ['plugin_cache', 202]],
        ['neq' => '1 1', 'nlt' => '4 2', 'ndlt' => '2 2', 'sample' => ['plugin_mail', 203]],
        ['neq' => '1 1', 'nlt' => '5 3', 'ndlt' => '3 3', 'sample' => ['plugin_seo', 204]],
    ];
    $source['rows'] = [
        ['rowid' => 201, 'option_id' => 201, 'option_name' => 'plugin_analytics', 'autoload' => 'yes', 'option_value' => 'analytics', 'blog_id' => 1],
        ['rowid' => 202, 'option_id' => 202, 'option_name' => 'plugin_cache', 'autoload' => 'yes', 'option_value' => 'current-cache-a', 'blog_id' => 7],
        ['rowid' => 203, 'option_id' => 203, 'option_name' => 'Plugin_Cache', 'autoload' => 'yes', 'option_value' => 'current-cache-b', 'blog_id' => 8],
        ['rowid' => 204, 'option_id' => 204, 'option_name' => 'plugin_cache', 'autoload' => 'no', 'option_value' => 'disabled', 'blog_id' => 9],
        ['rowid' => 205, 'option_id' => 205, 'option_name' => 'plugin_mail', 'autoload' => 'yes', 'option_value' => 'mail', 'blog_id' => 1],
    ];

    return $source;
};

$plan144 = static fn (?array $prepared = null, ?array $current = null, ?array $predicate = null, ?array $needed = null): array => SQLitePlannerStat4ExpressionCoveringCurrentSourceNextPlan::materializeNext144(
    $prepared ?? $preparedSource144(),
    $current ?? $currentSource144(),
    $predicate ?? $predicate144,
    $order144,
    $needed ?? $needed144,
    $neededExpressions144,
);

$fresh144 = static fn (): array => $plan144(
    $preparedSource144(),
    $preparedSource144(['name' => 'current-fresh-stat4-expression-covering-current-source-next144'])
);

$nonCovering144 = static function () use ($currentSource144, $plan144): array {
    $current = $currentSource144();
    $current['indexes'][0]['coveringColumns'] = ['option_name'];

    return $plan144(null, $current);
};

$tests = [
    'planner stat4 expression covering current source next144 status ready' => static fn (TestRunner $t) => $t->same('stat4-expression-covering-current-source-next144-ready', $plan144()['status']),
    'planner stat4 expression covering current source next144 selects current' => static fn (TestRunner $t) => $t->same('current', $plan144()['selectedSource']),
    'planner stat4 expression covering current source next144 stale prepared' => static fn (TestRunner $t) => $t->same(true, $plan144()['stalePreparedStatement']),
    'planner stat4 expression covering current source next144 reprepare required' => static fn (TestRunner $t) => $t->same(true, $plan144()['reprepareRequired']),
    'planner stat4 expression covering current source next144 schema changed' => static fn (TestRunner $t) => $t->same(true, $plan144()['schemaCookieChanged']),
    'planner stat4 expression covering current source next144 stat4 changed' => static fn (TestRunner $t) => $t->same(true, $plan144()['stat4GenerationChanged']),
    'planner stat4 expression covering current source next144 index signature changed' => static fn (TestRunner $t) => $t->same(true, $plan144()['indexSignatureChanged']),
    'planner stat4 expression covering current source next144 selected index' => static fn (TestRunner $t) => $t->same('idx_wp_options_lower_point_covering_stat4_next144', $plan144()['selectedPlan']['name']),
    'planner stat4 expression covering current source next144 current root' => static fn (TestRunner $t) => $t->same(14477, $plan144()['selectedPlan']['rootPage']),
    'planner stat4 expression covering current source next144 point operator' => static fn (TestRunner $t) => $t->same('point', $plan144()['selectedPlan']['operator']),
    'planner stat4 expression covering current source next144 covering true' => static fn (TestRunner $t) => $t->same(true, $plan144()['selectedPlan']['covering']),
    'planner stat4 expression covering current source next144 order satisfied' => static fn (TestRunner $t) => $t->same(true, $plan144()['selectedPlan']['orderBySatisfied']),
    'planner stat4 expression covering current source next144 stat4 matched samples' => static fn (TestRunner $t) => $t->same(1, $plan144()['selectedPlan']['stat4MatchedSamples']),
    'planner stat4 expression covering current source next144 selected covered row count' => static fn (TestRunner $t) => $t->same(2, $plan144()['selectedPlan']['coveredRowCount']),
    'planner stat4 expression covering current source next144 prepared rowids' => static fn (TestRunner $t) => $t->same([101, 102], $plan144()['preparedCoveringRowids']),
    'planner stat4 expression covering current source next144 current rowids' => static fn (TestRunner $t) => $t->same([202, 203], $plan144()['currentCoveringRowids']),
    'planner stat4 expression covering current source next144 rejects stale rows' => static fn (TestRunner $t) => $t->same([101, 102], $plan144()['staleCoveringRejectedRowids']),
    'planner stat4 expression covering current source next144 admits current rows' => static fn (TestRunner $t) => $t->same([202, 203], $plan144()['currentCoveringAdmittedRowids']),
    'planner stat4 expression covering current source next144 no stable rows' => static fn (TestRunner $t) => $t->same([], $plan144()['stableCoveringRowids']),
    'planner stat4 expression covering current source next144 stream changed' => static fn (TestRunner $t) => $t->same(true, $plan144()['currentSourceRowStreamChanged']),
    'planner stat4 expression covering current source next144 payload changed' => static fn (TestRunner $t) => $t->same(true, $plan144()['currentSourcePayloadChanged']),
    'planner stat4 expression covering current source next144 current first rowid' => static fn (TestRunner $t) => $t->same(202, $plan144()['currentCoveringRows'][0]['rowid']),
    'planner stat4 expression covering current source next144 current second rowid' => static fn (TestRunner $t) => $t->same(203, $plan144()['currentCoveringRows'][1]['rowid']),
    'planner stat4 expression covering current source next144 current first payload' => static fn (TestRunner $t) => $t->same('current-cache-a', $plan144()['currentCoveringRows'][0]['covering']['option_value']),
    'planner stat4 expression covering current source next144 current second payload' => static fn (TestRunner $t) => $t->same('current-cache-b', $plan144()['currentCoveringRows'][1]['covering']['option_value']),
    'planner stat4 expression covering current source next144 current blog id' => static fn (TestRunner $t) => $t->same(8, $plan144()['currentCoveringRows'][1]['covering']['blog_id']),
    'planner stat4 expression covering current source next144 expression payload' => static fn (TestRunner $t) => $t->same('plugin_cache', $plan144()['currentCoveringRows'][1]['coveringExpressions']['lower(option_name)']),
    'planner stat4 expression covering current source next144 prepared payload visible' => static fn (TestRunner $t) => $t->same('prepared-cache-a', $plan144()['preparedCoveringRows'][0]['covering']['option_value']),
    'planner stat4 expression covering current source next144 table lookup elided' => static fn (TestRunner $t) => $t->same(true, $plan144()['cursorTape']['tableLookupElided']),
    'planner stat4 expression covering current source next144 deferred seek absent' => static fn (TestRunner $t) => $t->same(null, $plan144()['cursorTape']['deferredSeekOpcode']),
    'planner stat4 expression covering current source next144 sorter absent' => static fn (TestRunner $t) => $t->same(false, $plan144()['cursorTape']['sorterOpen']),
    'planner stat4 expression covering current source next144 tape starts recheck' => static fn (TestRunner $t) => $t->same('RecheckPointSource', $plan144()['cursorTape']['program'][0]['opcode']),
    'planner stat4 expression covering current source next144 tape then open read' => static fn (TestRunner $t) => $t->same('OpenRead', $plan144()['cursorTape']['program'][1]['opcode']),
    'planner stat4 expression covering current source next144 tape source current' => static fn (TestRunner $t) => $t->same('current', $plan144()['cursorTape']['source']),
    'planner stat4 expression covering current source next144 tape root' => static fn (TestRunner $t) => $t->same(14477, $plan144()['cursorTape']['rootPage']),
    'planner stat4 expression covering current source next144 tape expression keys' => static fn (TestRunner $t) => $t->same(['plugin_cache', 'plugin_cache'], $plan144()['cursorTape']['expressionKeys']),
    'planner stat4 expression covering current source next144 tape rejected rowids' => static fn (TestRunner $t) => $t->same([101, 102], $plan144()['cursorTape']['staleCoveringRejectedRowids']),
    'planner stat4 expression covering current source next144 tape admitted rowids' => static fn (TestRunner $t) => $t->same([202, 203], $plan144()['cursorTape']['currentCoveringAdmittedRowids']),
    'planner stat4 expression covering current source next144 tape lookup elided after recheck' => static fn (TestRunner $t) => $t->same(true, $plan144()['cursorTape']['tableLookupElidedAfterCurrentSourceRecheck']),
    'planner stat4 expression covering current source next144 fence cookie' => static fn (TestRunner $t) => $t->same(1447, $plan144()['currentSourceFence']['schemaCookie']),
    'planner stat4 expression covering current source next144 fence stat4' => static fn (TestRunner $t) => $t->same(88, $plan144()['currentSourceFence']['stat4Generation']),
    'planner stat4 expression covering current source next144 predicate signature length' => static fn (TestRunner $t) => $t->same(64, strlen($plan144()['pointPredicateSignature'])),
    'planner stat4 expression covering current source next144 row signatures differ' => static fn (TestRunner $t) => $t->same(false, $plan144()['currentSourceFence']['next144PreparedRowStreamSignature'] === $plan144()['currentSourceFence']['next144CurrentRowStreamSignature']),
    'planner stat4 expression covering current source next144 payload signature length' => static fn (TestRunner $t) => $t->same(64, strlen($plan144()['currentSourceFence']['next144PayloadSignature'])),
    'planner stat4 expression covering current source next144 expression signature' => static fn (TestRunner $t) => $t->same('lower(option_name)', $plan144()['coveringExpressionSignature']),
    'planner stat4 expression covering current source next144 column signature' => static fn (TestRunner $t) => $t->same('option_name,autoload,option_value,option_id,blog_id', $plan144()['coveringColumnSignature']),
    'planner stat4 expression covering current source next144 dependency marker' => static fn (TestRunner $t) => $t->contains('sqlite-sqlplanner-stat4-expression-covering-current-source-next144', implode(',', $plan144()['dependencies'])),
    'planner stat4 expression covering current source next144 dependency closure' => static fn (TestRunner $t) => $t->contains('no new support component needed', $plan144()['dependency_closure']),
    'planner stat4 expression covering current source next144 non overlap' => static fn (TestRunner $t) => $t->contains('point-predicate covering rows', $plan144()['non_overlap']),
    'planner stat4 expression covering current source next144 detail' => static fn (TestRunner $t) => $t->contains('REPREPARE STAT4 EXPRESSION COVERING CURRENT-SOURCE NEXT144', $plan144()['detail']),
    'planner stat4 expression covering current source next144 fresh requires next stage' => static fn (TestRunner $t) => $t->same('requires-next-stage', $fresh144()['status']),
    'planner stat4 expression covering current source next144 fresh selected prepared' => static fn (TestRunner $t) => $t->same('prepared', $fresh144()['selectedSource']),
    'planner stat4 expression covering current source next144 fresh no stream change' => static fn (TestRunner $t) => $t->same(false, $fresh144()['currentSourceRowStreamChanged']),
    'planner stat4 expression covering current source next144 non covering requires next' => static fn (TestRunner $t) => $t->same('requires-next-stage', $nonCovering144()['status']),
    'planner stat4 expression covering current source next144 non covering deferred seek' => static fn (TestRunner $t) => $t->same('DeferredSeek', $nonCovering144()['cursorTape']['deferredSeekOpcode']),
    'planner stat4 expression covering current source next144 validates output columns' => static function (TestRunner $t) use ($preparedSource144, $currentSource144, $predicate144, $order144, $neededExpressions144): void {
        $t->throws(InvalidArgumentException::class, static fn () => SQLitePlannerStat4ExpressionCoveringCurrentSourceNextPlan::materializeNext144($preparedSource144(), $currentSource144(), $predicate144, $order144, [], $neededExpressions144));
    },
    'planner stat4 expression covering current source next144 validates source indexes' => static function (TestRunner $t) use ($preparedSource144, $currentSource144, $predicate144, $order144, $needed144, $neededExpressions144): void {
        $bad = $preparedSource144();
        $bad['indexes'] = [];
        $t->throws(InvalidArgumentException::class, static fn () => SQLitePlannerStat4ExpressionCoveringCurrentSourceNextPlan::materializeNext144($bad, $currentSource144(), $predicate144, $order144, $needed144, $neededExpressions144));
    },
];

return $tests;

<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePlannerStat4ExpressionCoveringRangeCurrentSourceNextPlan;

$expr128 = static fn (string $function, string $column): array => ['function' => $function, 'column' => $column];
$column128 = static fn (string $name): array => ['column' => $name];
$point128 = static fn (array $left, mixed $value): array => ['operator' => '=', 'left' => $left, 'right' => $value];
$range128 = static fn (array $left, string $operator, mixed $value): array => ['operator' => $operator, 'left' => $left, 'right' => $value];
$and128 = static fn (array ...$terms): array => ['operator' => 'AND', 'terms' => $terms];

$lower128 = $expr128('lower', 'option_name');
$autoloadYes128 = $point128($column128('autoload'), 'yes');
$preparedPredicate128 = $and128(
    $range128($lower128, '>=', 'plugin_'),
    $range128($lower128, '<', 'plugin_z'),
    $autoloadYes128,
);
$currentPredicate128 = $and128(
    $range128($lower128, '>=', 'plugin_c'),
    $range128($lower128, '<=', 'plugin_seo'),
    $autoloadYes128,
);
$order128 = [$lower128, ['column' => 'option_id']];
$needed128 = ['option_name', 'autoload', 'option_value', 'option_id', 'blog_id'];

$preparedSource128 = static function (array $overrides = []): array {
    return $overrides + [
        'name' => 'prepared-stat4-expression-covering-range-next128',
        'schemaCookie' => 1280,
        'stat4Generation' => 52,
        'indexes' => [[
            'name' => 'idx_wp_options_lower_covering_stat4_next128',
            'rootPage' => 12801,
            'estimatedRows' => 420,
            'coveringColumns' => ['option_name', 'autoload', 'option_value', 'option_id', 'blog_id'],
            'coveringExpressions' => [['function' => 'lower', 'column' => 'option_name']],
            'stat4Samples' => [
                ['neq' => '1 1', 'nlt' => '0 0', 'ndlt' => '0 0', 'sample' => ['plugin_alpha', 101]],
                ['neq' => '1 1', 'nlt' => '1 1', 'ndlt' => '1 1', 'sample' => ['plugin_beta', 102]],
                ['neq' => '2 1', 'nlt' => '2 2', 'ndlt' => '2 2', 'sample' => ['plugin_cache', 103]],
                ['neq' => '1 1', 'nlt' => '4 3', 'ndlt' => '3 3', 'sample' => ['plugin_forms', 104]],
                ['neq' => '1 1', 'nlt' => '5 4', 'ndlt' => '4 4', 'sample' => ['plugin_mail', 105]],
            ],
            'sql' => "CREATE INDEX idx_wp_options_lower_covering_stat4_next128 ON wp_options(lower(option_name), option_id, option_value, blog_id) WHERE autoload = 'yes'",
        ]],
    ];
};

$currentSource128 = static function () use ($preparedSource128): array {
    $source = $preparedSource128([
        'name' => 'current-stat4-expression-covering-range-next128',
        'schemaCookie' => 1286,
        'stat4Generation' => 58,
    ]);
    $source['indexes'][0]['rootPage'] = 12864;
    $source['indexes'][0]['stat4Samples'] = [
        ['neq' => '1 1', 'nlt' => '0 0', 'ndlt' => '0 0', 'sample' => ['plugin_beta', 201]],
        ['neq' => '2 1', 'nlt' => '1 1', 'ndlt' => '1 1', 'sample' => ['plugin_cache', 202]],
        ['neq' => '2 1', 'nlt' => '3 2', 'ndlt' => '2 2', 'sample' => ['plugin_forms', 203]],
        ['neq' => '1 1', 'nlt' => '5 3', 'ndlt' => '3 3', 'sample' => ['plugin_mail', 204]],
        ['neq' => '2 1', 'nlt' => '6 4', 'ndlt' => '4 4', 'sample' => ['plugin_seo', 205]],
        ['neq' => '1 1', 'nlt' => '8 5', 'ndlt' => '5 5', 'sample' => ['plugin_zeta', 206]],
    ];

    return $source;
};

$rows128 = static fn (): array => [
    ['rowid' => 41, 'option_name' => 'Plugin_Mail', 'autoload' => 'yes', 'option_value' => 'mail-enabled', 'option_id' => 41, 'blog_id' => 2],
    ['rowid' => 11, 'option_name' => 'plugin_alpha', 'autoload' => 'yes', 'option_value' => 'alpha-enabled', 'option_id' => 11, 'blog_id' => 1],
    ['rowid' => 31, 'option_name' => 'Plugin_Forms', 'autoload' => 'yes', 'option_value' => 'forms-enabled', 'option_id' => 31, 'blog_id' => 1],
    ['rowid' => 21, 'option_name' => 'plugin_cache', 'autoload' => 'yes', 'option_value' => 'cache-enabled', 'option_id' => 21, 'blog_id' => 1],
    ['rowid' => 51, 'option_name' => 'plugin_seo', 'autoload' => 'yes', 'option_value' => 'seo-enabled', 'option_id' => 51, 'blog_id' => 1],
    ['rowid' => 61, 'option_name' => 'plugin_cache', 'autoload' => 'no', 'option_value' => 'cache-disabled', 'option_id' => 61, 'blog_id' => 3],
    ['rowid' => 71, 'option_name' => 'theme_mods', 'autoload' => 'yes', 'option_value' => 'theme', 'option_id' => 71, 'blog_id' => 1],
    ['rowid' => 81, 'option_name' => 'plugin_zeta', 'autoload' => 'yes', 'option_value' => 'zeta', 'option_id' => 81, 'blog_id' => 1],
];

$plan128 = static fn (
    ?array $prepared = null,
    ?array $current = null,
    ?array $preparedPredicate = null,
    ?array $currentPredicate = null,
    ?array $rows = null,
    ?array $needed = null,
): array => SQLitePlannerStat4ExpressionCoveringRangeCurrentSourceNextPlan::materializeCurrentSourceRange(
    $prepared ?? $preparedSource128(),
    $current ?? $currentSource128(),
    $preparedPredicate ?? $preparedPredicate128,
    $currentPredicate ?? $currentPredicate128,
    $rows ?? $rows128(),
    $order128,
    $needed ?? $needed128,
    [$lower128],
);

$fresh128 = static fn (): array => $plan128(
    $preparedSource128(),
    $preparedSource128(['name' => 'current-fresh-stat4-expression-covering-range-next128']),
    $preparedPredicate128,
    $preparedPredicate128,
);
$nonCovering128 = static function () use ($currentSource128, $plan128): array {
    $current = $currentSource128();
    $current['indexes'][0]['coveringColumns'] = ['option_name'];

    return $plan128(null, $current);
};
$noStat4128 = static function () use ($currentSource128, $plan128): array {
    $current = $currentSource128();
    $current['indexes'][0]['stat4Samples'] = [];

    return $plan128(null, $current);
};

$tests = [
    'planner stat4 expression covering range current source next128 status ready' => static fn (TestRunner $t) => $t->same('stat4-expression-covering-range-current-source-next128-ready', $plan128()['status']),
    'planner stat4 expression covering range current source next128 selects current' => static fn (TestRunner $t) => $t->same('current', $plan128()['selectedSource']),
    'planner stat4 expression covering range current source next128 stale prepared' => static fn (TestRunner $t) => $t->same(true, $plan128()['stalePreparedStatement']),
    'planner stat4 expression covering range current source next128 reprepare required' => static fn (TestRunner $t) => $t->same(true, $plan128()['reprepareRequired']),
    'planner stat4 expression covering range current source next128 schema changed' => static fn (TestRunner $t) => $t->same(true, $plan128()['schemaCookieChanged']),
    'planner stat4 expression covering range current source next128 stat4 changed' => static fn (TestRunner $t) => $t->same(true, $plan128()['stat4GenerationChanged']),
    'planner stat4 expression covering range current source next128 signature changed' => static fn (TestRunner $t) => $t->same(true, $plan128()['indexSignatureChanged']),
    'planner stat4 expression covering range current source next128 selected index' => static fn (TestRunner $t) => $t->same('idx_wp_options_lower_covering_stat4_next128', $plan128()['selectedPlan']['name']),
    'planner stat4 expression covering range current source next128 selected root' => static fn (TestRunner $t) => $t->same(12864, $plan128()['selectedPlan']['rootPage']),
    'planner stat4 expression covering range current source next128 expression type' => static fn (TestRunner $t) => $t->same('lower', $plan128()['selectedPlan']['type']),
    'planner stat4 expression covering range current source next128 expression column' => static fn (TestRunner $t) => $t->same('option_name', $plan128()['selectedPlan']['column']),
    'planner stat4 expression covering range current source next128 bounded operator' => static fn (TestRunner $t) => $t->same('range-bounded', $plan128()['selectedPlan']['operator']),
    'planner stat4 expression covering range current source next128 prepared lower' => static fn (TestRunner $t) => $t->same('plugin_', $plan128()['preparedRangeValues']['lower']),
    'planner stat4 expression covering range current source next128 prepared upper' => static fn (TestRunner $t) => $t->same('plugin_z', $plan128()['preparedRangeValues']['upper']),
    'planner stat4 expression covering range current source next128 current lower' => static fn (TestRunner $t) => $t->same('plugin_c', $plan128()['currentRangeValues']['lower']),
    'planner stat4 expression covering range current source next128 current upper' => static fn (TestRunner $t) => $t->same('plugin_seo', $plan128()['currentRangeValues']['upper']),
    'planner stat4 expression covering range current source next128 current upper inclusive' => static fn (TestRunner $t) => $t->same(true, $plan128()['currentRangeValues']['upperInclusive']),
    'planner stat4 expression covering range current source next128 range changed' => static fn (TestRunner $t) => $t->same(true, $plan128()['rangePredicateChanged']),
    'planner stat4 expression covering range current source next128 covering true' => static fn (TestRunner $t) => $t->same(true, $plan128()['selectedPlan']['covering']),
    'planner stat4 expression covering range current source next128 stat4 used' => static fn (TestRunner $t) => $t->same(true, $plan128()['selectedPlan']['stat4Used']),
    'planner stat4 expression covering range current source next128 stat4 matched' => static fn (TestRunner $t) => $t->same(4, $plan128()['selectedPlan']['stat4MatchedSamples']),
    'planner stat4 expression covering range current source next128 covered rows' => static fn (TestRunner $t) => $t->same(4, $plan128()['selectedPlan']['coveredRowCount']),
    'planner stat4 expression covering range current source next128 prepared rowids' => static fn (TestRunner $t) => $t->same([11, 21, 31, 41, 51], $plan128()['preparedMatchedRowids']),
    'planner stat4 expression covering range current source next128 current rowids' => static fn (TestRunner $t) => $t->same([21, 31, 41, 51], $plan128()['currentMatchedRowids']),
    'planner stat4 expression covering range current source next128 rejects stale alpha' => static fn (TestRunner $t) => $t->same([11], $plan128()['staleRangeRejectedRowids']),
    'planner stat4 expression covering range current source next128 no newly admitted rows' => static fn (TestRunner $t) => $t->same([], $plan128()['currentRangeAdmittedRowids']),
    'planner stat4 expression covering range current source next128 residual recheck required' => static fn (TestRunner $t) => $t->same(true, $plan128()['residualRangeRecheckRequired']),
    'planner stat4 expression covering range current source next128 recheck opcode' => static fn (TestRunner $t) => $t->same('IdxGE/IdxLT current-source fence', $plan128()['rangeRecheckOpcode']),
    'planner stat4 expression covering range current source next128 matched keys' => static fn (TestRunner $t) => $t->same(['plugin_cache', 'plugin_forms', 'plugin_mail', 'plugin_seo'], $plan128()['cursorTape']['matchedKeys']),
    'planner stat4 expression covering range current source next128 first current rowid' => static fn (TestRunner $t) => $t->same(21, $plan128()['currentNextRows'][0]['current']['rowid']),
    'planner stat4 expression covering range current source next128 last current next eof' => static fn (TestRunner $t) => $t->same(null, $plan128()['currentNextRows'][3]['next']),
    'planner stat4 expression covering range current source next128 covering value' => static fn (TestRunner $t) => $t->same('forms-enabled', $plan128()['currentNextRows'][1]['current']['covering']['option_value']),
    'planner stat4 expression covering range current source next128 covering blog id' => static fn (TestRunner $t) => $t->same(2, $plan128()['currentNextRows'][2]['current']['covering']['blog_id']),
    'planner stat4 expression covering range current source next128 expression payload' => static fn (TestRunner $t) => $t->same('plugin_mail', $plan128()['currentNextRows'][2]['current']['coveringExpressions']['lower(option_name)']),
    'planner stat4 expression covering range current source next128 table lookup elided' => static fn (TestRunner $t) => $t->same(true, $plan128()['tableLookupElided']),
    'planner stat4 expression covering range current source next128 no deferred seek' => static fn (TestRunner $t) => $t->same(null, $plan128()['deferredTableSeekOpcode']),
    'planner stat4 expression covering range current source next128 sorter elided' => static fn (TestRunner $t) => $t->same(true, $plan128()['tempSorterElided']),
    'planner stat4 expression covering range current source next128 cursor source' => static fn (TestRunner $t) => $t->same('current', $plan128()['cursorTape']['source']),
    'planner stat4 expression covering range current source next128 cursor root' => static fn (TestRunner $t) => $t->same(12864, $plan128()['cursorTape']['rootPage']),
    'planner stat4 expression covering range current source next128 seek opcode' => static fn (TestRunner $t) => $t->same('SeekGE', $plan128()['cursorTape']['seekOpcode']),
    'planner stat4 expression covering range current source next128 stop opcode inclusive' => static fn (TestRunner $t) => $t->same('IdxGT', $plan128()['cursorTape']['stopOpcode']),
    'planner stat4 expression covering range current source next128 annotated program starts recheck' => static fn (TestRunner $t) => $t->same('RecheckRangeBounds', $plan128()['cursorTape']['program'][0]['opcode']),
    'planner stat4 expression covering range current source next128 annotated program then seek' => static fn (TestRunner $t) => $t->same('SeekGE', $plan128()['cursorTape']['program'][1]['opcode']),
    'planner stat4 expression covering range current source next128 tape rejected rowid' => static fn (TestRunner $t) => $t->same([11], $plan128()['cursorTape']['staleRangeRejectedRowids']),
    'planner stat4 expression covering range current source next128 tape table lookup elided after recheck' => static fn (TestRunner $t) => $t->same(true, $plan128()['cursorTape']['tableLookupElidedAfterRecheck']),
    'planner stat4 expression covering range current source next128 fence cookie' => static fn (TestRunner $t) => $t->same(1286, $plan128()['currentSourceFence']['schemaCookie']),
    'planner stat4 expression covering range current source next128 fence stat4' => static fn (TestRunner $t) => $t->same(58, $plan128()['currentSourceFence']['stat4Generation']),
    'planner stat4 expression covering range current source next128 predicate signature length' => static fn (TestRunner $t) => $t->same(64, strlen($plan128()['currentPredicateSignature'])),
    'planner stat4 expression covering range current source next128 prepared signature differs' => static fn (TestRunner $t) => $t->same(false, $plan128()['preparedPredicateSignature'] === $plan128()['currentPredicateSignature']),
    'planner stat4 expression covering range current source next128 dependency marker' => static fn (TestRunner $t) => $t->contains('sqlite-planner-stat4-expression-covering-range-current-source-next128', implode(',', $plan128()['dependencies'])),
    'planner stat4 expression covering range current source next128 dependency closure' => static fn (TestRunner $t) => $t->contains('no new support component needed', $plan128()['dependency_closure']),
    'planner stat4 expression covering range current source next128 non overlap' => static fn (TestRunner $t) => $t->contains('stale prepared range rows are rejected', $plan128()['non_overlap']),
    'planner stat4 expression covering range current source next128 fresh remains delegated' => static fn (TestRunner $t) => $t->same('requires-next-stage', $fresh128()['status']),
    'planner stat4 expression covering range current source next128 fresh no stale rows' => static fn (TestRunner $t) => $t->same([], $fresh128()['staleRangeRejectedRowids']),
    'planner stat4 expression covering range current source next128 fresh no recheck' => static fn (TestRunner $t) => $t->same(false, $fresh128()['residualRangeRecheckRequired']),
    'planner stat4 expression covering range current source next128 non covering requires next' => static fn (TestRunner $t) => $t->same('requires-next-stage', $nonCovering128()['status']),
    'planner stat4 expression covering range current source next128 non covering deferred seek' => static fn (TestRunner $t) => $t->same('DeferredSeek', $nonCovering128()['deferredTableSeekOpcode']),
    'planner stat4 expression covering range current source next128 no stat4 requires next' => static fn (TestRunner $t) => $t->same('requires-next-stage', $noStat4128()['status']),
    'planner stat4 expression covering range current source next128 no stat4 matched zero' => static fn (TestRunner $t) => $t->same(0, $noStat4128()['selectedPlan']['stat4MatchedSamples']),
    'planner stat4 expression covering range current source next128 validates source indexes' => static function (TestRunner $t) use ($preparedSource128, $currentSource128, $preparedPredicate128, $currentPredicate128, $rows128, $order128, $needed128, $lower128): void {
        $bad = $preparedSource128();
        $bad['indexes'] = [];
        $t->throws(InvalidArgumentException::class, static fn () => SQLitePlannerStat4ExpressionCoveringRangeCurrentSourceNextPlan::materializeCurrentSourceRange($bad, $currentSource128(), $preparedPredicate128, $currentPredicate128, $rows128(), $order128, $needed128, [$lower128]));
    },
    'planner stat4 expression covering range current source next128 validates output columns' => static function (TestRunner $t) use ($preparedSource128, $currentSource128, $preparedPredicate128, $currentPredicate128, $rows128, $order128, $lower128): void {
        $t->throws(InvalidArgumentException::class, static fn () => SQLitePlannerStat4ExpressionCoveringRangeCurrentSourceNextPlan::materializeCurrentSourceRange($preparedSource128(), $currentSource128(), $preparedPredicate128, $currentPredicate128, $rows128(), $order128, [], [$lower128]));
    },
];

return $tests;

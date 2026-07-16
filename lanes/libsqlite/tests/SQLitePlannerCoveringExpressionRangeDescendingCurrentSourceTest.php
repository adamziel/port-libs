<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePlannerCoveringExpressionRangeCurrentSourceNextPlan;

$expr134 = static fn (string $function, string $column): array => ['function' => $function, 'column' => $column];
$column134 = static fn (string $name): array => ['column' => $name];
$point134 = static fn (array $left, mixed $value): array => ['operator' => '=', 'left' => $left, 'right' => $value];
$range134 = static fn (array $left, string $operator, mixed $value): array => ['operator' => $operator, 'left' => $left, 'right' => $value];
$and134 = static fn (array ...$terms): array => ['operator' => 'AND', 'terms' => $terms];

$lower134 = $expr134('lower', 'option_name');
$autoloadYes134 = $point134($column134('autoload'), 'yes');
$preparedPredicate134 = $and134(
    $range134($lower134, '>=', 'plugin_'),
    $range134($lower134, '<=', 'plugin_z'),
    $autoloadYes134,
);
$currentPredicate134 = $and134(
    $range134($lower134, '>', 'plugin_beta'),
    $range134($lower134, '<=', 'plugin_seo'),
    $autoloadYes134,
);
$order134 = [['function' => 'lower', 'column' => 'option_name', 'direction' => 'DESC'], ['column' => 'option_id', 'direction' => 'DESC']];
$needed134 = ['option_name', 'autoload', 'option_value', 'option_id', 'blog_id'];

$preparedSource134 = static function (array $overrides = []): array {
    return $overrides + [
        'name' => 'prepared-covering-expression-range-descending',
        'schemaCookie' => 1340,
        'stat4Generation' => 41,
        'indexes' => [[
            'name' => 'idx_wp_options_lower_desc_covering_descending',
            'rootPage' => 13401,
            'estimatedRows' => 512,
            'coveringColumns' => ['option_name', 'autoload', 'option_value', 'option_id', 'blog_id'],
            'coveringExpressions' => [['function' => 'lower', 'column' => 'option_name']],
            'stat4Samples' => [
                ['neq' => '1 1', 'nlt' => '0 0', 'ndlt' => '0 0', 'sample' => ['plugin_alpha', 101]],
                ['neq' => '1 1', 'nlt' => '1 1', 'ndlt' => '1 1', 'sample' => ['plugin_beta', 102]],
                ['neq' => '1 1', 'nlt' => '2 2', 'ndlt' => '2 2', 'sample' => ['plugin_cache', 103]],
                ['neq' => '1 1', 'nlt' => '3 3', 'ndlt' => '3 3', 'sample' => ['plugin_forms', 104]],
                ['neq' => '1 1', 'nlt' => '4 4', 'ndlt' => '4 4', 'sample' => ['plugin_mail', 105]],
                ['neq' => '1 1', 'nlt' => '5 5', 'ndlt' => '5 5', 'sample' => ['plugin_seo', 106]],
            ],
            'sql' => "CREATE INDEX idx_wp_options_lower_desc_covering_descending ON wp_options(lower(option_name) DESC, option_id DESC, option_value, blog_id) WHERE autoload = 'yes'",
        ]],
    ];
};

$currentSource134 = static function (array $overrides = []) use ($preparedSource134): array {
    $source = $preparedSource134($overrides + [
        'name' => 'current-covering-expression-range-descending',
        'schemaCookie' => 1348,
        'stat4Generation' => 49,
    ]);
    $source['indexes'][0]['rootPage'] = 13488;
    $source['indexes'][0]['stat4Samples'] = [
        ['neq' => '1 1', 'nlt' => '0 0', 'ndlt' => '0 0', 'sample' => ['plugin_beta', 201]],
        ['neq' => '2 1', 'nlt' => '1 1', 'ndlt' => '1 1', 'sample' => ['plugin_cache', 202]],
        ['neq' => '1 1', 'nlt' => '3 2', 'ndlt' => '2 2', 'sample' => ['plugin_forms', 203]],
        ['neq' => '1 1', 'nlt' => '4 3', 'ndlt' => '3 3', 'sample' => ['plugin_mail', 204]],
        ['neq' => '2 1', 'nlt' => '5 4', 'ndlt' => '4 4', 'sample' => ['plugin_seo', 205]],
        ['neq' => '1 1', 'nlt' => '7 5', 'ndlt' => '5 5', 'sample' => ['plugin_zeta', 206]],
    ];

    return $source;
};

$rows134 = static fn (): array => [
    ['rowid' => 10, 'option_name' => 'plugin_alpha', 'autoload' => 'yes', 'option_value' => 'alpha-enabled', 'option_id' => 10, 'blog_id' => 1],
    ['rowid' => 20, 'option_name' => 'plugin_beta', 'autoload' => 'yes', 'option_value' => 'beta-enabled', 'option_id' => 20, 'blog_id' => 1],
    ['rowid' => 30, 'option_name' => 'plugin_cache', 'autoload' => 'yes', 'option_value' => 'cache-enabled', 'option_id' => 30, 'blog_id' => 1],
    ['rowid' => 35, 'option_name' => 'Plugin_Cache_Extra', 'autoload' => 'yes', 'option_value' => 'cache-extra', 'option_id' => 35, 'blog_id' => 2],
    ['rowid' => 40, 'option_name' => 'Plugin_Forms', 'autoload' => 'yes', 'option_value' => 'forms-enabled', 'option_id' => 40, 'blog_id' => 1],
    ['rowid' => 50, 'option_name' => 'Plugin_Mail', 'autoload' => 'yes', 'option_value' => 'mail-enabled', 'option_id' => 50, 'blog_id' => 3],
    ['rowid' => 60, 'option_name' => 'plugin_seo', 'autoload' => 'yes', 'option_value' => 'seo-enabled', 'option_id' => 60, 'blog_id' => 1],
    ['rowid' => 70, 'option_name' => 'plugin_zeta', 'autoload' => 'yes', 'option_value' => 'zeta-enabled', 'option_id' => 70, 'blog_id' => 1],
    ['rowid' => 80, 'option_name' => 'plugin_cache', 'autoload' => 'no', 'option_value' => 'cache-disabled', 'option_id' => 80, 'blog_id' => 4],
    ['rowid' => 90, 'option_name' => 'theme_mods', 'autoload' => 'yes', 'option_value' => 'theme', 'option_id' => 90, 'blog_id' => 1],
];

$plan134 = static fn (
    ?array $prepared = null,
    ?array $current = null,
    ?array $preparedPredicate = null,
    ?array $currentPredicate = null,
    ?array $rows = null,
    ?array $order = null,
    ?array $needed = null,
): array => SQLitePlannerCoveringExpressionRangeCurrentSourceNextPlan::materializeDescendingCurrentRange(
    $prepared ?? $preparedSource134(),
    $current ?? $currentSource134(),
    $preparedPredicate ?? $preparedPredicate134,
    $currentPredicate ?? $currentPredicate134,
    $rows ?? $rows134(),
    $order ?? $order134,
    $needed ?? $needed134,
    [$lower134],
);

$ascending134 = static fn (): array => $plan134(null, $currentSource134(['indexes' => [[
    'name' => 'idx_wp_options_lower_asc_covering_descending',
    'rootPage' => 13489,
    'estimatedRows' => 512,
    'coveringColumns' => ['option_name', 'autoload', 'option_value', 'option_id', 'blog_id'],
    'coveringExpressions' => [['function' => 'lower', 'column' => 'option_name']],
    'stat4Samples' => [
        ['neq' => '2 1', 'nlt' => '1 1', 'ndlt' => '1 1', 'sample' => ['plugin_cache', 202]],
        ['neq' => '1 1', 'nlt' => '3 2', 'ndlt' => '2 2', 'sample' => ['plugin_forms', 203]],
        ['neq' => '1 1', 'nlt' => '4 3', 'ndlt' => '3 3', 'sample' => ['plugin_mail', 204]],
        ['neq' => '2 1', 'nlt' => '5 4', 'ndlt' => '4 4', 'sample' => ['plugin_seo', 205]],
    ],
    'sql' => "CREATE INDEX idx_wp_options_lower_asc_covering_descending ON wp_options(lower(option_name), option_id, option_value, blog_id) WHERE autoload = 'yes'",
]]]));
$nonCovering134 = static function () use ($currentSource134, $plan134): array {
    $current = $currentSource134();
    $current['indexes'][0]['coveringColumns'] = ['option_name'];

    return $plan134(null, $current);
};

$tests = [
    'planner covering expression range current source descending status ready' => static fn (TestRunner $t) => $t->same('covering-expression-range-current-source-descending-ready', $plan134()['status']),
    'planner covering expression range current source descending selected current' => static fn (TestRunner $t) => $t->same('current', $plan134()['selectedSource']),
    'planner covering expression range current source descending stale prepared' => static fn (TestRunner $t) => $t->same(true, $plan134()['stalePreparedStatement']),
    'planner covering expression range current source descending requires reprepare' => static fn (TestRunner $t) => $t->same(true, $plan134()['reprepareRequired']),
    'planner covering expression range current source descending schema changed' => static fn (TestRunner $t) => $t->same(true, $plan134()['schemaCookieChanged']),
    'planner covering expression range current source descending stat4 changed' => static fn (TestRunner $t) => $t->same(true, $plan134()['stat4GenerationChanged']),
    'planner covering expression range current source descending signature changed' => static fn (TestRunner $t) => $t->same(true, $plan134()['indexSignatureChanged']),
    'planner covering expression range current source descending selected index' => static fn (TestRunner $t) => $t->same('idx_wp_options_lower_desc_covering_descending', $plan134()['selectedPlan']['name']),
    'planner covering expression range current source descending selected root' => static fn (TestRunner $t) => $t->same(13488, $plan134()['selectedPlan']['rootPage']),
    'planner covering expression range current source descending range operator' => static fn (TestRunner $t) => $t->same('range-bounded', $plan134()['selectedPlan']['operator']),
    'planner covering expression range current source descending expression type' => static fn (TestRunner $t) => $t->same('lower', $plan134()['selectedPlan']['type']),
    'planner covering expression range current source descending expression column' => static fn (TestRunner $t) => $t->same('option_name', $plan134()['selectedPlan']['column']),
    'planner covering expression range current source descending desc flag' => static fn (TestRunner $t) => $t->same(true, $plan134()['selectedPlan']['descending']),
    'planner covering expression range current source descending direction' => static fn (TestRunner $t) => $t->same('DESC', $plan134()['rangeDirection']),
    'planner covering expression range current source descending cursor mode' => static fn (TestRunner $t) => $t->same('descending-covering-expression-range', $plan134()['currentSourceRangeCursor']),
    'planner covering expression range current source descending prepared lower' => static fn (TestRunner $t) => $t->same('plugin_', $plan134()['preparedRangeValues']['lower']),
    'planner covering expression range current source descending prepared upper' => static fn (TestRunner $t) => $t->same('plugin_z', $plan134()['preparedRangeValues']['upper']),
    'planner covering expression range current source descending current lower' => static fn (TestRunner $t) => $t->same('plugin_beta', $plan134()['currentRangeValues']['lower']),
    'planner covering expression range current source descending current lower exclusive' => static fn (TestRunner $t) => $t->same(false, $plan134()['currentRangeValues']['lowerInclusive']),
    'planner covering expression range current source descending current upper' => static fn (TestRunner $t) => $t->same('plugin_seo', $plan134()['currentRangeValues']['upper']),
    'planner covering expression range current source descending current upper inclusive' => static fn (TestRunner $t) => $t->same(true, $plan134()['currentRangeValues']['upperInclusive']),
    'planner covering expression range current source descending range changed' => static fn (TestRunner $t) => $t->same(true, $plan134()['rangePredicateChanged']),
    'planner covering expression range current source descending prepared rowids' => static fn (TestRunner $t) => $t->same([10, 20, 30, 35, 40, 50, 60], $plan134()['preparedMatchedRowids']),
    'planner covering expression range current source descending current rowids ascending evidence' => static fn (TestRunner $t) => $t->same([30, 35, 40, 50, 60], $plan134()['currentMatchedRowids']),
    'planner covering expression range current source descending rejects stale low rows' => static fn (TestRunner $t) => $t->same([10, 20], $plan134()['staleRangeRejectedRowids']),
    'planner covering expression range current source descending no admitted rows' => static fn (TestRunner $t) => $t->same([], $plan134()['currentRangeAdmittedRowids']),
    'planner covering expression range current source descending residual recheck' => static fn (TestRunner $t) => $t->same(true, $plan134()['residualRangeRecheckRequired']),
    'planner covering expression range current source descending stream rowids desc' => static fn (TestRunner $t) => $t->same([60, 50, 40, 35, 30], $plan134()['currentSourceNextRowids']),
    'planner covering expression range current source descending stream keys desc' => static fn (TestRunner $t) => $t->same(['plugin_seo', 'plugin_mail', 'plugin_forms', 'plugin_cache_extra', 'plugin_cache'], $plan134()['currentSourceNextKeys']),
    'planner covering expression range current source descending first current rowid' => static fn (TestRunner $t) => $t->same(60, $plan134()['currentNextRows'][0]['current']['rowid']),
    'planner covering expression range current source descending first next rowid' => static fn (TestRunner $t) => $t->same(50, $plan134()['currentNextRows'][0]['next']['rowid']),
    'planner covering expression range current source descending last current rowid' => static fn (TestRunner $t) => $t->same(30, $plan134()['currentNextRows'][4]['current']['rowid']),
    'planner covering expression range current source descending last next eof' => static fn (TestRunner $t) => $t->same(null, $plan134()['currentNextRows'][4]['next']),
    'planner covering expression range current source descending covering value from index' => static fn (TestRunner $t) => $t->same('mail-enabled', $plan134()['currentNextRows'][1]['current']['covering']['option_value']),
    'planner covering expression range current source descending covering blog id' => static fn (TestRunner $t) => $t->same(3, $plan134()['currentNextRows'][1]['current']['covering']['blog_id']),
    'planner covering expression range current source descending expression payload lower' => static fn (TestRunner $t) => $t->same('plugin_forms', $plan134()['currentNextRows'][2]['current']['coveringExpressions']['lower(option_name)']),
    'planner covering expression range current source descending table lookup elided' => static fn (TestRunner $t) => $t->same(true, $plan134()['tableLookupElided']),
    'planner covering expression range current source descending no deferred seek' => static fn (TestRunner $t) => $t->same(null, $plan134()['deferredTableSeekOpcode']),
    'planner covering expression range current source descending sorter elided' => static fn (TestRunner $t) => $t->same(true, $plan134()['tempSorterElided']),
    'planner covering expression range current source descending stat4 used' => static fn (TestRunner $t) => $t->same(true, $plan134()['selectedPlan']['stat4Used']),
    'planner covering expression range current source descending matched samples' => static fn (TestRunner $t) => $t->same(4, $plan134()['selectedPlan']['stat4MatchedSamples']),
    'planner covering expression range current source descending covered row count' => static fn (TestRunner $t) => $t->same(5, $plan134()['selectedPlan']['coveredRowCount']),
    'planner covering expression range current source descending seek opcode' => static fn (TestRunner $t) => $t->same('SeekLE', $plan134()['rangeSeekOpcode']),
    'planner covering expression range current source descending stop opcode' => static fn (TestRunner $t) => $t->same('IdxLE', $plan134()['rangeStopOpcode']),
    'planner covering expression range current source descending tape direction' => static fn (TestRunner $t) => $t->same('DESC', $plan134()['cursorTape']['direction']),
    'planner covering expression range current source descending tape seek' => static fn (TestRunner $t) => $t->same('SeekLE', $plan134()['cursorTape']['seekOpcode']),
    'planner covering expression range current source descending tape stop' => static fn (TestRunner $t) => $t->same('IdxLE', $plan134()['cursorTape']['stopOpcode']),
    'planner covering expression range current source descending tape prev opcode' => static fn (TestRunner $t) => $t->same('Prev', $plan134()['cursorTape']['nextOpcode']),
    'planner covering expression range current source descending program starts recheck' => static fn (TestRunner $t) => $t->same('RecheckRangeBounds', $plan134()['cursorTape']['program'][0]['opcode']),
    'planner covering expression range current source descending program seek desc' => static fn (TestRunner $t) => $t->same('SeekLE', $plan134()['cursorTape']['program'][1]['opcode']),
    'planner covering expression range current source descending program stop desc' => static fn (TestRunner $t) => $t->same('IdxLE', $plan134()['cursorTape']['program'][2]['opcode']),
    'planner covering expression range current source descending program emits prev' => static fn (TestRunner $t) => $t->same('Prev', $plan134()['cursorTape']['program'][count($plan134()['cursorTape']['program']) - 1]['opcode']),
    'planner covering expression range current source descending tape keys desc' => static fn (TestRunner $t) => $t->same(['plugin_seo', 'plugin_mail', 'plugin_forms', 'plugin_cache_extra', 'plugin_cache'], $plan134()['cursorTape']['matchedKeys']),
    'planner covering expression range current source descending tape table lookup elided' => static fn (TestRunner $t) => $t->same(true, $plan134()['cursorTape']['tableLookupElidedAfterRecheck']),
    'planner covering expression range current source descending output column source index' => static fn (TestRunner $t) => $t->same('index', $plan134()['cursorTape']['outputColumns'][0]['source']),
    'planner covering expression range current source descending current source fence cookie' => static fn (TestRunner $t) => $t->same(1348, $plan134()['currentSourceFence']['schemaCookie']),
    'planner covering expression range current source descending current source fence stat4' => static fn (TestRunner $t) => $t->same(49, $plan134()['currentSourceFence']['stat4Generation']),
    'planner covering expression range current source descending predicate signature differs' => static fn (TestRunner $t) => $t->same(false, $plan134()['preparedPredicateSignature'] === $plan134()['currentPredicateSignature']),
    'planner covering expression range current source descending dependency marker' => static fn (TestRunner $t) => $t->contains('sqlite-planner-covering-expression-range-current-source-descending', implode(',', $plan134()['dependencies'])),
    'planner covering expression range current source descending dependency closure' => static fn (TestRunner $t) => $t->contains('no new support component needed', $plan134()['dependency_closure']),
    'planner covering expression range current source descending non overlap' => static fn (TestRunner $t) => $t->contains('descending covering expression range', $plan134()['non_overlap']),
    'planner covering expression range current source descending ascending requires next' => static fn (TestRunner $t) => $t->same('requires-next-stage', $ascending134()['status']),
    'planner covering expression range current source descending ascending cursor names forward' => static fn (TestRunner $t) => $t->same('forward-covering-expression-range', $ascending134()['currentSourceRangeCursor']),
    'planner covering expression range current source descending non covering requires next' => static fn (TestRunner $t) => $t->same('requires-next-stage', $nonCovering134()['status']),
    'planner covering expression range current source descending non covering table seek' => static fn (TestRunner $t) => $t->same('DeferredSeek', $nonCovering134()['deferredTableSeekOpcode']),
    'planner covering expression range current source descending validates source indexes' => static function (TestRunner $t) use ($preparedSource134, $currentSource134, $preparedPredicate134, $currentPredicate134, $rows134, $order134, $needed134, $lower134): void {
        $bad = $preparedSource134();
        $bad['indexes'] = [];
        $t->throws(InvalidArgumentException::class, static fn () => SQLitePlannerCoveringExpressionRangeCurrentSourceNextPlan::materializeDescendingCurrentRange($bad, $currentSource134(), $preparedPredicate134, $currentPredicate134, $rows134(), $order134, $needed134, [$lower134]));
    },
    'planner covering expression range current source descending validates output columns' => static function (TestRunner $t) use ($preparedSource134, $currentSource134, $preparedPredicate134, $currentPredicate134, $rows134, $order134, $lower134): void {
        $t->throws(InvalidArgumentException::class, static fn () => SQLitePlannerCoveringExpressionRangeCurrentSourceNextPlan::materializeDescendingCurrentRange($preparedSource134(), $currentSource134(), $preparedPredicate134, $currentPredicate134, $rows134(), $order134, [], [$lower134]));
    },
];

return $tests;

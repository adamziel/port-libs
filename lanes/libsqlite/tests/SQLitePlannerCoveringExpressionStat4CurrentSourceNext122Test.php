<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePlannerCoveringExpressionStat4CurrentSourceNextPlan;

$expr122 = static fn (string $function, string $column): array => ['function' => $function, 'column' => $column];
$column122 = static fn (string $name): array => ['column' => $name];
$point122 = static fn (array $left, mixed $value): array => ['operator' => '=', 'left' => $left, 'right' => $value];
$range122 = static fn (array $left, string $operator, mixed $value): array => ['operator' => $operator, 'left' => $left, 'right' => $value];
$and122 = static fn (array ...$terms): array => ['operator' => 'AND', 'terms' => $terms];

$lower122 = $expr122('lower', 'option_name');
$autoloadYes122 = $point122($column122('autoload'), 'yes');
$predicate122 = $and122(
    $range122($lower122, '>=', 'plugin_'),
    $range122($lower122, '<', 'plugin_z'),
    $autoloadYes122,
);
$order122 = [$lower122, ['column' => 'option_id']];
$needed122 = ['option_name', 'autoload', 'option_value', 'option_id', 'blog_id'];

$preparedSource122 = static function (array $overrides = []): array {
    return $overrides + [
        'name' => 'prepared-covering-expression-stat4-next122',
        'schemaCookie' => 1220,
        'stat4Generation' => 31,
        'indexes' => [[
            'name' => 'idx_wp_options_lower_covering_stat4_next122',
            'rootPage' => 12201,
            'estimatedRows' => 360,
            'coveringColumns' => ['option_name', 'autoload', 'option_value', 'option_id', 'blog_id'],
            'coveringExpressions' => [['function' => 'lower', 'column' => 'option_name']],
            'stat4Samples' => [
                ['neq' => '1 1', 'nlt' => '0 0', 'ndlt' => '0 0', 'sample' => ['plugin_cache', 101]],
                ['neq' => '2 1', 'nlt' => '1 1', 'ndlt' => '1 1', 'sample' => ['plugin_forms', 102]],
                ['neq' => '4 1', 'nlt' => '3 2', 'ndlt' => '2 2', 'sample' => ['plugin_seo', 103]],
            ],
            'sql' => "CREATE INDEX idx_wp_options_lower_covering_stat4_next122 ON wp_options(lower(option_name), option_id, option_value, blog_id) WHERE autoload = 'yes'",
        ], [
            'name' => 'idx_wp_options_lower_noncovering_next122',
            'rootPage' => 12202,
            'estimatedRows' => 12,
            'coveringColumns' => ['option_name'],
            'stat4Samples' => [
                ['neq' => '1 1', 'nlt' => '0 0', 'ndlt' => '0 0', 'sample' => ['plugin_cache', 201]],
            ],
            'sql' => 'CREATE INDEX idx_wp_options_lower_noncovering_next122 ON wp_options(lower(option_name))',
        ]],
    ];
};

$currentSource122 = static function () use ($preparedSource122): array {
    $source = $preparedSource122([
        'name' => 'current-covering-expression-stat4-next122',
        'schemaCookie' => 1224,
        'stat4Generation' => 35,
    ]);
    $source['indexes'][0]['rootPage'] = 12244;
    $source['indexes'][0]['stat4Samples'] = [
        ['neq' => '1 1', 'nlt' => '0 0', 'ndlt' => '0 0', 'sample' => ['plugin_alpha', 301]],
        ['neq' => '2 1', 'nlt' => '1 1', 'ndlt' => '1 1', 'sample' => ['plugin_cache', 302]],
        ['neq' => '3 1', 'nlt' => '3 2', 'ndlt' => '2 2', 'sample' => ['plugin_forms', 303]],
        ['neq' => '2 1', 'nlt' => '6 3', 'ndlt' => '3 3', 'sample' => ['plugin_mail', 304]],
        ['neq' => '4 1', 'nlt' => '8 4', 'ndlt' => '4 4', 'sample' => ['plugin_seo', 305]],
        ['neq' => '1 1', 'nlt' => '12 5', 'ndlt' => '5 5', 'sample' => ['theme_mods', 306]],
    ];

    return $source;
};

$rows122 = static fn (): array => [
    ['rowid' => 41, 'option_name' => 'Plugin_Mail', 'autoload' => 'yes', 'option_value' => 'mail-enabled', 'option_id' => 41, 'blog_id' => 2],
    ['rowid' => 11, 'option_name' => 'plugin_alpha', 'autoload' => 'yes', 'option_value' => 'alpha-enabled', 'option_id' => 11, 'blog_id' => 1],
    ['rowid' => 31, 'option_name' => 'Plugin_Forms', 'autoload' => 'yes', 'option_value' => 'forms-enabled', 'option_id' => 31, 'blog_id' => 1],
    ['rowid' => 21, 'option_name' => 'plugin_cache', 'autoload' => 'yes', 'option_value' => 'cache-enabled', 'option_id' => 21, 'blog_id' => 1],
    ['rowid' => 51, 'option_name' => 'plugin_seo', 'autoload' => 'yes', 'option_value' => 'seo-enabled', 'option_id' => 51, 'blog_id' => 1],
    ['rowid' => 61, 'option_name' => 'plugin_cache', 'autoload' => 'no', 'option_value' => 'cache-disabled', 'option_id' => 61, 'blog_id' => 3],
    ['rowid' => 71, 'option_name' => 'theme_mods', 'autoload' => 'yes', 'option_value' => 'theme', 'option_id' => 71, 'blog_id' => 1],
];

$plan122 = static fn (
    ?array $prepared = null,
    ?array $current = null,
    ?array $predicate = null,
    ?array $rows = null,
    ?array $needed = null,
): array => SQLitePlannerCoveringExpressionStat4CurrentSourceNextPlan::materializeNext122(
    $prepared ?? $preparedSource122(),
    $current ?? $currentSource122(),
    $predicate ?? $GLOBALS['predicate_next122'],
    $rows ?? $GLOBALS['rows_next122'](),
    $GLOBALS['order_next122'],
    $needed ?? $GLOBALS['needed_next122'],
    [$GLOBALS['lower_next122']],
);

$GLOBALS['predicate_next122'] = $predicate122;
$GLOBALS['rows_next122'] = $rows122;
$GLOBALS['order_next122'] = $order122;
$GLOBALS['needed_next122'] = $needed122;
$GLOBALS['lower_next122'] = $lower122;

$fresh122 = static fn (): array => $plan122($preparedSource122(), $preparedSource122(['name' => 'current-fresh-covering-expression-stat4-next122']));
$nonCovering122 = static function () use ($currentSource122, $plan122): array {
    $current = $currentSource122();
    $current['indexes'][0]['coveringColumns'] = ['option_name'];

    return $plan122(null, $current);
};
$noStat4122 = static function () use ($currentSource122, $plan122): array {
    $current = $currentSource122();
    foreach ($current['indexes'] as &$index) {
        $index['stat4Samples'] = [];
    }
    unset($index);

    return $plan122(null, $current);
};
$emptyRange122 = static fn (): array => $plan122(null, null, $and122($range122($lower122, '>=', 'plugin_z'), $range122($lower122, '<', 'plugin_zz'), $autoloadYes122));
$openLower122 = static fn (): array => $plan122(null, null, $and122($range122($lower122, '>', 'plugin_cache'), $range122($lower122, '<=', 'plugin_seo'), $autoloadYes122));

$tests = [
    'planner covering expression stat4 current source next122 status ready' => static fn (TestRunner $t) => $t->same('covering-expression-stat4-current-source-ready', $plan122()['status']),
    'planner covering expression stat4 current source next122 selects current' => static fn (TestRunner $t) => $t->same('current', $plan122()['selectedSource']),
    'planner covering expression stat4 current source next122 stale prepared' => static fn (TestRunner $t) => $t->same(true, $plan122()['stalePreparedStatement']),
    'planner covering expression stat4 current source next122 reprepare required' => static fn (TestRunner $t) => $t->same(true, $plan122()['reprepareRequired']),
    'planner covering expression stat4 current source next122 schema changed' => static fn (TestRunner $t) => $t->same(true, $plan122()['schemaCookieChanged']),
    'planner covering expression stat4 current source next122 stat4 changed' => static fn (TestRunner $t) => $t->same(true, $plan122()['stat4GenerationChanged']),
    'planner covering expression stat4 current source next122 signature changed' => static fn (TestRunner $t) => $t->same(true, $plan122()['indexSignatureChanged']),
    'planner covering expression stat4 current source next122 selected index' => static fn (TestRunner $t) => $t->same('idx_wp_options_lower_covering_stat4_next122', $plan122()['selectedPlan']['name']),
    'planner covering expression stat4 current source next122 selected root' => static fn (TestRunner $t) => $t->same(12244, $plan122()['selectedPlan']['rootPage']),
    'planner covering expression stat4 current source next122 type lower' => static fn (TestRunner $t) => $t->same('lower', $plan122()['selectedPlan']['type']),
    'planner covering expression stat4 current source next122 column option name' => static fn (TestRunner $t) => $t->same('option_name', $plan122()['selectedPlan']['column']),
    'planner covering expression stat4 current source next122 bounded operator' => static fn (TestRunner $t) => $t->same('range-bounded', $plan122()['selectedPlan']['operator']),
    'planner covering expression stat4 current source next122 range lower' => static fn (TestRunner $t) => $t->same('plugin_', $plan122()['selectedPlan']['values']['lower']),
    'planner covering expression stat4 current source next122 range upper' => static fn (TestRunner $t) => $t->same('plugin_z', $plan122()['selectedPlan']['values']['upper']),
    'planner covering expression stat4 current source next122 lower inclusive' => static fn (TestRunner $t) => $t->same(true, $plan122()['selectedPlan']['values']['lowerInclusive']),
    'planner covering expression stat4 current source next122 upper exclusive' => static fn (TestRunner $t) => $t->same(false, $plan122()['selectedPlan']['values']['upperInclusive']),
    'planner covering expression stat4 current source next122 partial true' => static fn (TestRunner $t) => $t->same(true, $plan122()['selectedPlan']['partial']),
    'planner covering expression stat4 current source next122 covering true' => static fn (TestRunner $t) => $t->same(true, $plan122()['selectedPlan']['covering']),
    'planner covering expression stat4 current source next122 stat4 used' => static fn (TestRunner $t) => $t->same(true, $plan122()['selectedPlan']['stat4Used']),
    'planner covering expression stat4 current source next122 stat4 matched' => static fn (TestRunner $t) => $t->same(5, $plan122()['selectedPlan']['stat4MatchedSamples']),
    'planner covering expression stat4 current source next122 estimated rows' => static fn (TestRunner $t) => $t->same(12, $plan122()['selectedPlan']['estimatedRows']),
    'planner covering expression stat4 current source next122 covered row count' => static fn (TestRunner $t) => $t->same(5, $plan122()['selectedPlan']['coveredRowCount']),
    'planner covering expression stat4 current source next122 keys sorted' => static fn (TestRunner $t) => $t->same(['plugin_alpha', 'plugin_cache', 'plugin_forms', 'plugin_mail', 'plugin_seo'], $plan122()['cursorTape']['matchedKeys']),
    'planner covering expression stat4 current source next122 first rowid' => static fn (TestRunner $t) => $t->same(11, $plan122()['currentNextRows'][0]['current']['rowid']),
    'planner covering expression stat4 current source next122 second next rowid' => static fn (TestRunner $t) => $t->same(31, $plan122()['currentNextRows'][1]['next']['rowid']),
    'planner covering expression stat4 current source next122 last next eof' => static fn (TestRunner $t) => $t->same(null, $plan122()['currentNextRows'][4]['next']),
    'planner covering expression stat4 current source next122 excludes partial miss' => static fn (TestRunner $t) => $t->same(false, in_array(61, array_map(static fn (array $pair): mixed => $pair['current']['rowid'], $plan122()['currentNextRows']), true)),
    'planner covering expression stat4 current source next122 excludes upper range miss' => static fn (TestRunner $t) => $t->same(false, in_array(71, array_map(static fn (array $pair): mixed => $pair['current']['rowid'], $plan122()['currentNextRows']), true)),
    'planner covering expression stat4 current source next122 covering value' => static fn (TestRunner $t) => $t->same('cache-enabled', $plan122()['currentNextRows'][1]['current']['covering']['option_value']),
    'planner covering expression stat4 current source next122 covering blog id' => static fn (TestRunner $t) => $t->same(2, $plan122()['currentNextRows'][3]['current']['covering']['blog_id']),
    'planner covering expression stat4 current source next122 expression payload' => static fn (TestRunner $t) => $t->same('plugin_forms', $plan122()['currentNextRows'][2]['current']['coveringExpressions']['lower(option_name)']),
    'planner covering expression stat4 current source next122 payload columns' => static fn (TestRunner $t) => $t->same($GLOBALS['needed_next122'], $plan122()['coveringPayloadColumns']),
    'planner covering expression stat4 current source next122 expression count' => static fn (TestRunner $t) => $t->same(1, $plan122()['coveringExpressionCount']),
    'planner covering expression stat4 current source next122 table lookup elided' => static fn (TestRunner $t) => $t->same(true, $plan122()['tableLookupElided']),
    'planner covering expression stat4 current source next122 no deferred seek' => static fn (TestRunner $t) => $t->same(null, $plan122()['deferredTableSeekOpcode']),
    'planner covering expression stat4 current source next122 sorter elided' => static fn (TestRunner $t) => $t->same(true, $plan122()['tempSorterElided']),
    'planner covering expression stat4 current source next122 cursor source' => static fn (TestRunner $t) => $t->same('current', $plan122()['cursorTape']['source']),
    'planner covering expression stat4 current source next122 cursor index' => static fn (TestRunner $t) => $t->same('idx_wp_options_lower_covering_stat4_next122', $plan122()['cursorTape']['indexName']),
    'planner covering expression stat4 current source next122 cursor root' => static fn (TestRunner $t) => $t->same(12244, $plan122()['cursorTape']['rootPage']),
    'planner covering expression stat4 current source next122 cursor expression type' => static fn (TestRunner $t) => $t->same('lower', $plan122()['cursorTape']['expressionType']),
    'planner covering expression stat4 current source next122 seek opcode' => static fn (TestRunner $t) => $t->same('SeekGE', $plan122()['cursorTape']['seekOpcode']),
    'planner covering expression stat4 current source next122 stop opcode' => static fn (TestRunner $t) => $t->same('IdxGE', $plan122()['cursorTape']['stopOpcode']),
    'planner covering expression stat4 current source next122 output from index' => static fn (TestRunner $t) => $t->same('index', $plan122()['cursorTape']['outputColumns'][2]['source']),
    'planner covering expression stat4 current source next122 program seek' => static fn (TestRunner $t) => $t->same(['opcode' => 'SeekGE', 'source' => 'index', 'key' => 'plugin_'], $plan122()['cursorTape']['program'][0]),
    'planner covering expression stat4 current source next122 program stop' => static fn (TestRunner $t) => $t->same(['opcode' => 'IdxGE', 'source' => 'index', 'key' => 'plugin_z'], $plan122()['cursorTape']['program'][1]),
    'planner covering expression stat4 current source next122 program result covering' => static fn (TestRunner $t) => $t->same('covering-index', $plan122()['cursorTape']['program'][7]['source']),
    'planner covering expression stat4 current source next122 fence cookie' => static fn (TestRunner $t) => $t->same(1224, $plan122()['currentSourceFence']['schemaCookie']),
    'planner covering expression stat4 current source next122 fence stat4' => static fn (TestRunner $t) => $t->same(35, $plan122()['currentSourceFence']['stat4Generation']),
    'planner covering expression stat4 current source next122 fence signature length' => static fn (TestRunner $t) => $t->same(64, strlen($plan122()['currentSourceFence']['indexSignature'])),
    'planner covering expression stat4 current source next122 predicate signature length' => static fn (TestRunner $t) => $t->same(64, strlen($plan122()['currentSourceFence']['predicateSignature'])),
    'planner covering expression stat4 current source next122 covering signature' => static fn (TestRunner $t) => $t->same('option_name,autoload,option_value,option_id,blog_id', $plan122()['currentSourceFence']['coveringSignature']),
    'planner covering expression stat4 current source next122 prepared summary root' => static fn (TestRunner $t) => $t->same(12201, $plan122()['preparedSource']['rootPage']),
    'planner covering expression stat4 current source next122 current summary root' => static fn (TestRunner $t) => $t->same(12244, $plan122()['currentSource']['rootPage']),
    'planner covering expression stat4 current source next122 detail reprepare' => static fn (TestRunner $t) => $t->contains('REPREPARE COVERING EXPRESSION STAT4', $plan122()['detail']),
    'planner covering expression stat4 current source next122 dependency marker' => static fn (TestRunner $t) => $t->contains('sqlite-planner-covering-expression-stat4-current-source-next122', implode(',', $plan122()['dependencies'])),
    'planner covering expression stat4 current source next122 dependency closure' => static fn (TestRunner $t) => $t->contains('no new support component needed', $plan122()['dependency_closure']),
    'planner covering expression stat4 current source next122 non overlap' => static fn (TestRunner $t) => $t->contains('avoids accepted next118 partial expression covering', $plan122()['non_overlap']),
    'planner covering expression stat4 current source next122 fresh selects prepared' => static fn (TestRunner $t) => $t->same('prepared', $fresh122()['selectedSource']),
    'planner covering expression stat4 current source next122 fresh no reprepare' => static fn (TestRunner $t) => $t->same(false, $fresh122()['reprepareRequired']),
    'planner covering expression stat4 current source next122 fresh root' => static fn (TestRunner $t) => $t->same(12201, $fresh122()['selectedPlan']['rootPage']),
    'planner covering expression stat4 current source next122 non covering requires next' => static fn (TestRunner $t) => $t->same('requires-next-stage', $nonCovering122()['status']),
    'planner covering expression stat4 current source next122 non covering deferred seek' => static fn (TestRunner $t) => $t->same('DeferredSeek', $nonCovering122()['deferredTableSeekOpcode']),
    'planner covering expression stat4 current source next122 no stat4 requires next' => static fn (TestRunner $t) => $t->same('requires-next-stage', $noStat4122()['status']),
    'planner covering expression stat4 current source next122 no stat4 matched zero' => static fn (TestRunner $t) => $t->same(0, $noStat4122()['selectedPlan']['stat4MatchedSamples']),
    'planner covering expression stat4 current source next122 empty range requires next' => static fn (TestRunner $t) => $t->same('requires-next-stage', $emptyRange122()['status']),
    'planner covering expression stat4 current source next122 empty range rows zero' => static fn (TestRunner $t) => $t->same(0, $emptyRange122()['selectedPlan']['coveredRowCount']),
    'planner covering expression stat4 current source next122 open lower seek gt' => static fn (TestRunner $t) => $t->same('SeekGT', $openLower122()['cursorTape']['seekOpcode']),
    'planner covering expression stat4 current source next122 open lower stop inclusive' => static fn (TestRunner $t) => $t->same('IdxGT', $openLower122()['cursorTape']['stopOpcode']),
    'planner covering expression stat4 current source next122 open lower keys' => static fn (TestRunner $t) => $t->same(['plugin_forms', 'plugin_mail', 'plugin_seo'], $openLower122()['cursorTape']['matchedKeys']),
    'planner covering expression stat4 current source next122 validates source indexes' => static function (TestRunner $t) use ($preparedSource122, $currentSource122, $predicate122, $rows122, $order122, $needed122, $lower122): void {
        $bad = $preparedSource122();
        $bad['indexes'] = [];
        $t->throws(InvalidArgumentException::class, static fn () => SQLitePlannerCoveringExpressionStat4CurrentSourceNextPlan::materializeNext122($bad, $currentSource122(), $predicate122, $rows122(), $order122, $needed122, [$lower122]));
    },
    'planner covering expression stat4 current source next122 validates schema cookie' => static function (TestRunner $t) use ($preparedSource122, $currentSource122, $predicate122, $rows122, $order122, $needed122, $lower122): void {
        $bad = $preparedSource122(['schemaCookie' => -1]);
        $t->throws(InvalidArgumentException::class, static fn () => SQLitePlannerCoveringExpressionStat4CurrentSourceNextPlan::materializeNext122($bad, $currentSource122(), $predicate122, $rows122(), $order122, $needed122, [$lower122]));
    },
    'planner covering expression stat4 current source next122 validates output columns' => static function (TestRunner $t) use ($preparedSource122, $currentSource122, $predicate122, $rows122, $order122, $lower122): void {
        $t->throws(InvalidArgumentException::class, static fn () => SQLitePlannerCoveringExpressionStat4CurrentSourceNextPlan::materializeNext122($preparedSource122(), $currentSource122(), $predicate122, $rows122(), $order122, [], [$lower122]));
    },
];

return $tests;

<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePlannerCoveringExpressionStat4CurrentSourceNextPlan;

$expr = static fn (string $function, string $column): array => ['function' => $function, 'column' => $column];
$column = static fn (string $name): array => ['column' => $name];
$point = static fn (array $left, mixed $value): array => ['operator' => '=', 'left' => $left, 'right' => $value];
$range = static fn (array $left, string $operator, mixed $value): array => ['operator' => $operator, 'left' => $left, 'right' => $value];
$and = static fn (array ...$terms): array => ['operator' => 'AND', 'terms' => $terms];

$lower = $expr('lower', 'option_name');
$autoloadYes = $point($column('autoload'), 'yes');
$predicate = $and(
    $range($lower, '>=', 'plugin_'),
    $range($lower, '<', 'plugin_z'),
    $autoloadYes,
);
$order = [$lower, ['column' => 'option_id']];
$needed = ['option_name', 'autoload', 'option_value', 'option_id', 'blog_id'];

$preparedSource = static function (array $overrides = []): array {
    return $overrides + [
        'name' => 'prepared-covering-expression-stat4-canonical',
        'schemaCookie' => 1220,
        'stat4Generation' => 31,
        'indexes' => [[
            'name' => 'idx_wp_options_lower_covering_stat4_canonical',
            'rootPage' => 12201,
            'estimatedRows' => 360,
            'coveringColumns' => ['option_name', 'autoload', 'option_value', 'option_id', 'blog_id'],
            'coveringExpressions' => [['function' => 'lower', 'column' => 'option_name']],
            'stat4Samples' => [
                ['neq' => '1 1', 'nlt' => '0 0', 'ndlt' => '0 0', 'sample' => ['plugin_cache', 101]],
                ['neq' => '2 1', 'nlt' => '1 1', 'ndlt' => '1 1', 'sample' => ['plugin_forms', 102]],
                ['neq' => '4 1', 'nlt' => '3 2', 'ndlt' => '2 2', 'sample' => ['plugin_seo', 103]],
            ],
            'sql' => "CREATE INDEX idx_wp_options_lower_covering_stat4_canonical ON wp_options(lower(option_name), option_id, option_value, blog_id) WHERE autoload = 'yes'",
        ], [
            'name' => 'idx_wp_options_lower_noncovering_canonical',
            'rootPage' => 12202,
            'estimatedRows' => 12,
            'coveringColumns' => ['option_name'],
            'stat4Samples' => [
                ['neq' => '1 1', 'nlt' => '0 0', 'ndlt' => '0 0', 'sample' => ['plugin_cache', 201]],
            ],
            'sql' => 'CREATE INDEX idx_wp_options_lower_noncovering_canonical ON wp_options(lower(option_name))',
        ]],
    ];
};

$currentSource = static function () use ($preparedSource): array {
    $source = $preparedSource([
        'name' => 'current-covering-expression-stat4-canonical',
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

$rows = static fn (): array => [
    ['rowid' => 41, 'option_name' => 'Plugin_Mail', 'autoload' => 'yes', 'option_value' => 'mail-enabled', 'option_id' => 41, 'blog_id' => 2],
    ['rowid' => 11, 'option_name' => 'plugin_alpha', 'autoload' => 'yes', 'option_value' => 'alpha-enabled', 'option_id' => 11, 'blog_id' => 1],
    ['rowid' => 31, 'option_name' => 'Plugin_Forms', 'autoload' => 'yes', 'option_value' => 'forms-enabled', 'option_id' => 31, 'blog_id' => 1],
    ['rowid' => 21, 'option_name' => 'plugin_cache', 'autoload' => 'yes', 'option_value' => 'cache-enabled', 'option_id' => 21, 'blog_id' => 1],
    ['rowid' => 51, 'option_name' => 'plugin_seo', 'autoload' => 'yes', 'option_value' => 'seo-enabled', 'option_id' => 51, 'blog_id' => 1],
    ['rowid' => 61, 'option_name' => 'plugin_cache', 'autoload' => 'no', 'option_value' => 'cache-disabled', 'option_id' => 61, 'blog_id' => 3],
    ['rowid' => 71, 'option_name' => 'theme_mods', 'autoload' => 'yes', 'option_value' => 'theme', 'option_id' => 71, 'blog_id' => 1],
];

$plan = static fn (
    ?array $prepared = null,
    ?array $current = null,
    ?array $predicate = null,
    ?array $rows = null,
    ?array $needed = null,
): array => SQLitePlannerCoveringExpressionStat4CurrentSourceNextPlan::materialize(
    $prepared ?? $preparedSource(),
    $current ?? $currentSource(),
    $predicate ?? $GLOBALS['predicate_canonical'],
    $rows ?? $GLOBALS['rows_canonical'](),
    $GLOBALS['order_canonical'],
    $needed ?? $GLOBALS['needed_canonical'],
    [$GLOBALS['lower_canonical']],
);

$GLOBALS['predicate_canonical'] = $predicate;
$GLOBALS['rows_canonical'] = $rows;
$GLOBALS['order_canonical'] = $order;
$GLOBALS['needed_canonical'] = $needed;
$GLOBALS['lower_canonical'] = $lower;

$fresh = static fn (): array => $plan($preparedSource(), $preparedSource(['name' => 'current-fresh-covering-expression-stat4-canonical']));
$nonCovering = static function () use ($currentSource, $plan): array {
    $current = $currentSource();
    $current['indexes'][0]['coveringColumns'] = ['option_name'];

    return $plan(null, $current);
};
$noStat4 = static function () use ($currentSource, $plan): array {
    $current = $currentSource();
    foreach ($current['indexes'] as &$index) {
        $index['stat4Samples'] = [];
    }
    unset($index);

    return $plan(null, $current);
};
$emptyRange = static fn (): array => $plan(null, null, $and($range($lower, '>=', 'plugin_z'), $range($lower, '<', 'plugin_zz'), $autoloadYes));
$openLower = static fn (): array => $plan(null, null, $and($range($lower, '>', 'plugin_cache'), $range($lower, '<=', 'plugin_seo'), $autoloadYes));

$tests = [
    'planner covering expression stat4 current source canonical status ready' => static fn (TestRunner $t) => $t->same('covering-expression-stat4-current-source-ready', $plan()['status']),
    'planner covering expression stat4 current source canonical selects current' => static fn (TestRunner $t) => $t->same('current', $plan()['selectedSource']),
    'planner covering expression stat4 current source canonical stale prepared' => static fn (TestRunner $t) => $t->same(true, $plan()['stalePreparedStatement']),
    'planner covering expression stat4 current source canonical reprepare required' => static fn (TestRunner $t) => $t->same(true, $plan()['reprepareRequired']),
    'planner covering expression stat4 current source canonical schema changed' => static fn (TestRunner $t) => $t->same(true, $plan()['schemaCookieChanged']),
    'planner covering expression stat4 current source canonical stat4 changed' => static fn (TestRunner $t) => $t->same(true, $plan()['stat4GenerationChanged']),
    'planner covering expression stat4 current source canonical signature changed' => static fn (TestRunner $t) => $t->same(true, $plan()['indexSignatureChanged']),
    'planner covering expression stat4 current source canonical selected index' => static fn (TestRunner $t) => $t->same('idx_wp_options_lower_covering_stat4_canonical', $plan()['selectedPlan']['name']),
    'planner covering expression stat4 current source canonical selected root' => static fn (TestRunner $t) => $t->same(12244, $plan()['selectedPlan']['rootPage']),
    'planner covering expression stat4 current source canonical type lower' => static fn (TestRunner $t) => $t->same('lower', $plan()['selectedPlan']['type']),
    'planner covering expression stat4 current source canonical column option name' => static fn (TestRunner $t) => $t->same('option_name', $plan()['selectedPlan']['column']),
    'planner covering expression stat4 current source canonical bounded operator' => static fn (TestRunner $t) => $t->same('range-bounded', $plan()['selectedPlan']['operator']),
    'planner covering expression stat4 current source canonical range lower' => static fn (TestRunner $t) => $t->same('plugin_', $plan()['selectedPlan']['values']['lower']),
    'planner covering expression stat4 current source canonical range upper' => static fn (TestRunner $t) => $t->same('plugin_z', $plan()['selectedPlan']['values']['upper']),
    'planner covering expression stat4 current source canonical lower inclusive' => static fn (TestRunner $t) => $t->same(true, $plan()['selectedPlan']['values']['lowerInclusive']),
    'planner covering expression stat4 current source canonical upper exclusive' => static fn (TestRunner $t) => $t->same(false, $plan()['selectedPlan']['values']['upperInclusive']),
    'planner covering expression stat4 current source canonical partial true' => static fn (TestRunner $t) => $t->same(true, $plan()['selectedPlan']['partial']),
    'planner covering expression stat4 current source canonical covering true' => static fn (TestRunner $t) => $t->same(true, $plan()['selectedPlan']['covering']),
    'planner covering expression stat4 current source canonical stat4 used' => static fn (TestRunner $t) => $t->same(true, $plan()['selectedPlan']['stat4Used']),
    'planner covering expression stat4 current source canonical stat4 matched' => static fn (TestRunner $t) => $t->same(5, $plan()['selectedPlan']['stat4MatchedSamples']),
    'planner covering expression stat4 current source canonical estimated rows' => static fn (TestRunner $t) => $t->same(12, $plan()['selectedPlan']['estimatedRows']),
    'planner covering expression stat4 current source canonical covered row count' => static fn (TestRunner $t) => $t->same(5, $plan()['selectedPlan']['coveredRowCount']),
    'planner covering expression stat4 current source canonical keys sorted' => static fn (TestRunner $t) => $t->same(['plugin_alpha', 'plugin_cache', 'plugin_forms', 'plugin_mail', 'plugin_seo'], $plan()['cursorTape']['matchedKeys']),
    'planner covering expression stat4 current source canonical first rowid' => static fn (TestRunner $t) => $t->same(11, $plan()['currentNextRows'][0]['current']['rowid']),
    'planner covering expression stat4 current source canonical second next rowid' => static fn (TestRunner $t) => $t->same(31, $plan()['currentNextRows'][1]['next']['rowid']),
    'planner covering expression stat4 current source canonical last next eof' => static fn (TestRunner $t) => $t->same(null, $plan()['currentNextRows'][4]['next']),
    'planner covering expression stat4 current source canonical excludes partial miss' => static fn (TestRunner $t) => $t->same(false, in_array(61, array_map(static fn (array $pair): mixed => $pair['current']['rowid'], $plan()['currentNextRows']), true)),
    'planner covering expression stat4 current source canonical excludes upper range miss' => static fn (TestRunner $t) => $t->same(false, in_array(71, array_map(static fn (array $pair): mixed => $pair['current']['rowid'], $plan()['currentNextRows']), true)),
    'planner covering expression stat4 current source canonical covering value' => static fn (TestRunner $t) => $t->same('cache-enabled', $plan()['currentNextRows'][1]['current']['covering']['option_value']),
    'planner covering expression stat4 current source canonical covering blog id' => static fn (TestRunner $t) => $t->same(2, $plan()['currentNextRows'][3]['current']['covering']['blog_id']),
    'planner covering expression stat4 current source canonical expression payload' => static fn (TestRunner $t) => $t->same('plugin_forms', $plan()['currentNextRows'][2]['current']['coveringExpressions']['lower(option_name)']),
    'planner covering expression stat4 current source canonical payload columns' => static fn (TestRunner $t) => $t->same($GLOBALS['needed_canonical'], $plan()['coveringPayloadColumns']),
    'planner covering expression stat4 current source canonical expression count' => static fn (TestRunner $t) => $t->same(1, $plan()['coveringExpressionCount']),
    'planner covering expression stat4 current source canonical table lookup elided' => static fn (TestRunner $t) => $t->same(true, $plan()['tableLookupElided']),
    'planner covering expression stat4 current source canonical no deferred seek' => static fn (TestRunner $t) => $t->same(null, $plan()['deferredTableSeekOpcode']),
    'planner covering expression stat4 current source canonical sorter elided' => static fn (TestRunner $t) => $t->same(true, $plan()['tempSorterElided']),
    'planner covering expression stat4 current source canonical cursor source' => static fn (TestRunner $t) => $t->same('current', $plan()['cursorTape']['source']),
    'planner covering expression stat4 current source canonical cursor index' => static fn (TestRunner $t) => $t->same('idx_wp_options_lower_covering_stat4_canonical', $plan()['cursorTape']['indexName']),
    'planner covering expression stat4 current source canonical cursor root' => static fn (TestRunner $t) => $t->same(12244, $plan()['cursorTape']['rootPage']),
    'planner covering expression stat4 current source canonical cursor expression type' => static fn (TestRunner $t) => $t->same('lower', $plan()['cursorTape']['expressionType']),
    'planner covering expression stat4 current source canonical seek opcode' => static fn (TestRunner $t) => $t->same('SeekGE', $plan()['cursorTape']['seekOpcode']),
    'planner covering expression stat4 current source canonical stop opcode' => static fn (TestRunner $t) => $t->same('IdxGE', $plan()['cursorTape']['stopOpcode']),
    'planner covering expression stat4 current source canonical output from index' => static fn (TestRunner $t) => $t->same('index', $plan()['cursorTape']['outputColumns'][2]['source']),
    'planner covering expression stat4 current source canonical program seek' => static fn (TestRunner $t) => $t->same(['opcode' => 'SeekGE', 'source' => 'index', 'key' => 'plugin_'], $plan()['cursorTape']['program'][0]),
    'planner covering expression stat4 current source canonical program stop' => static fn (TestRunner $t) => $t->same(['opcode' => 'IdxGE', 'source' => 'index', 'key' => 'plugin_z'], $plan()['cursorTape']['program'][1]),
    'planner covering expression stat4 current source canonical program result covering' => static fn (TestRunner $t) => $t->same('covering-index', $plan()['cursorTape']['program'][7]['source']),
    'planner covering expression stat4 current source canonical fence cookie' => static fn (TestRunner $t) => $t->same(1224, $plan()['currentSourceFence']['schemaCookie']),
    'planner covering expression stat4 current source canonical fence stat4' => static fn (TestRunner $t) => $t->same(35, $plan()['currentSourceFence']['stat4Generation']),
    'planner covering expression stat4 current source canonical fence signature length' => static fn (TestRunner $t) => $t->same(64, strlen($plan()['currentSourceFence']['indexSignature'])),
    'planner covering expression stat4 current source canonical predicate signature length' => static fn (TestRunner $t) => $t->same(64, strlen($plan()['currentSourceFence']['predicateSignature'])),
    'planner covering expression stat4 current source canonical covering signature' => static fn (TestRunner $t) => $t->same('option_name,autoload,option_value,option_id,blog_id', $plan()['currentSourceFence']['coveringSignature']),
    'planner covering expression stat4 current source canonical prepared summary root' => static fn (TestRunner $t) => $t->same(12201, $plan()['preparedSource']['rootPage']),
    'planner covering expression stat4 current source canonical current summary root' => static fn (TestRunner $t) => $t->same(12244, $plan()['currentSource']['rootPage']),
    'planner covering expression stat4 current source canonical detail reprepare' => static fn (TestRunner $t) => $t->contains('REPREPARE COVERING EXPRESSION STAT4', $plan()['detail']),
    'planner covering expression stat4 current source canonical dependency marker' => static fn (TestRunner $t) => $t->contains('sqlite-planner-covering-expression-stat4-current-source', implode(',', $plan()['dependencies'])),
    'planner covering expression stat4 current source canonical dependency closure' => static fn (TestRunner $t) => $t->contains('no new support component needed', $plan()['dependency_closure']),
    'planner covering expression stat4 current source canonical non overlap' => static fn (TestRunner $t) => $t->contains('avoids accepted next118 partial expression covering', $plan()['non_overlap']),
    'planner covering expression stat4 current source canonical fresh selects prepared' => static fn (TestRunner $t) => $t->same('prepared', $fresh()['selectedSource']),
    'planner covering expression stat4 current source canonical fresh no reprepare' => static fn (TestRunner $t) => $t->same(false, $fresh()['reprepareRequired']),
    'planner covering expression stat4 current source canonical fresh root' => static fn (TestRunner $t) => $t->same(12201, $fresh()['selectedPlan']['rootPage']),
    'planner covering expression stat4 current source canonical non covering requires next' => static fn (TestRunner $t) => $t->same('requires-next-stage', $nonCovering()['status']),
    'planner covering expression stat4 current source canonical non covering deferred seek' => static fn (TestRunner $t) => $t->same('DeferredSeek', $nonCovering()['deferredTableSeekOpcode']),
    'planner covering expression stat4 current source canonical no stat4 requires next' => static fn (TestRunner $t) => $t->same('requires-next-stage', $noStat4()['status']),
    'planner covering expression stat4 current source canonical no stat4 matched zero' => static fn (TestRunner $t) => $t->same(0, $noStat4()['selectedPlan']['stat4MatchedSamples']),
    'planner covering expression stat4 current source canonical empty range requires next' => static fn (TestRunner $t) => $t->same('requires-next-stage', $emptyRange()['status']),
    'planner covering expression stat4 current source canonical empty range rows zero' => static fn (TestRunner $t) => $t->same(0, $emptyRange()['selectedPlan']['coveredRowCount']),
    'planner covering expression stat4 current source canonical open lower seek gt' => static fn (TestRunner $t) => $t->same('SeekGT', $openLower()['cursorTape']['seekOpcode']),
    'planner covering expression stat4 current source canonical open lower stop inclusive' => static fn (TestRunner $t) => $t->same('IdxGT', $openLower()['cursorTape']['stopOpcode']),
    'planner covering expression stat4 current source canonical open lower keys' => static fn (TestRunner $t) => $t->same(['plugin_forms', 'plugin_mail', 'plugin_seo'], $openLower()['cursorTape']['matchedKeys']),
    'planner covering expression stat4 current source canonical validates source indexes' => static function (TestRunner $t) use ($preparedSource, $currentSource, $predicate, $rows, $order, $needed, $lower): void {
        $bad = $preparedSource();
        $bad['indexes'] = [];
        $t->throws(InvalidArgumentException::class, static fn () => SQLitePlannerCoveringExpressionStat4CurrentSourceNextPlan::materialize($bad, $currentSource(), $predicate, $rows(), $order, $needed, [$lower]));
    },
    'planner covering expression stat4 current source canonical validates schema cookie' => static function (TestRunner $t) use ($preparedSource, $currentSource, $predicate, $rows, $order, $needed, $lower): void {
        $bad = $preparedSource(['schemaCookie' => -1]);
        $t->throws(InvalidArgumentException::class, static fn () => SQLitePlannerCoveringExpressionStat4CurrentSourceNextPlan::materialize($bad, $currentSource(), $predicate, $rows(), $order, $needed, [$lower]));
    },
    'planner covering expression stat4 current source canonical validates output columns' => static function (TestRunner $t) use ($preparedSource, $currentSource, $predicate, $rows, $order, $lower): void {
        $t->throws(InvalidArgumentException::class, static fn () => SQLitePlannerCoveringExpressionStat4CurrentSourceNextPlan::materialize($preparedSource(), $currentSource(), $predicate, $rows(), $order, [], [$lower]));
    },
];

return $tests;

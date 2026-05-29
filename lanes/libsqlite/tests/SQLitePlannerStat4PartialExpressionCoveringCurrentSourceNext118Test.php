<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePlannerStat4PartialExpressionCoveringCurrentSourceNextPlan;

$expr118 = static fn (string $function, string $column): array => ['function' => $function, 'column' => $column];
$column118 = static fn (string $name): array => ['column' => $name];
$point118 = static fn (array $left, mixed $value): array => ['operator' => '=', 'left' => $left, 'right' => $value];
$in118 = static fn (array $left, array $values): array => ['operator' => 'IN', 'left' => $left, 'values' => $values];
$and118 = static fn (array ...$terms): array => ['operator' => 'AND', 'terms' => $terms];

$lower118 = $expr118('lower', 'option_name');
$autoloadYes118 = $point118($column118('autoload'), 'yes');
$predicate118 = $and118($in118($lower118, ['plugin_cache', 'plugin_forms', 'plugin_seo']), $autoloadYes118);
$order118 = [$lower118, ['column' => 'autoload']];
$needed118 = ['option_name', 'autoload', 'option_value', 'blog_id'];

$preparedSource118 = static function (array $overrides = []): array {
    return $overrides + [
        'name' => 'prepared-stat4-partial-expression-covering-next118',
        'schemaCookie' => 1180,
        'stat4Generation' => 17,
        'indexes' => [[
            'name' => 'idx_wp_options_lower_plugin_covering_next118',
            'rootPage' => 11801,
            'estimatedRows' => 400,
            'coveringColumns' => ['option_name', 'autoload', 'option_value', 'blog_id'],
            'stat4Samples' => [
                ['neq' => '1 1', 'nlt' => '0 0', 'ndlt' => '0 0', 'sample' => ['plugin_cache', 101]],
                ['neq' => '2 1', 'nlt' => '1 1', 'ndlt' => '1 1', 'sample' => ['plugin_forms', 102]],
                ['neq' => '3 1', 'nlt' => '3 2', 'ndlt' => '2 2', 'sample' => ['plugin_seo', 103]],
            ],
            'sql' => "CREATE INDEX idx_wp_options_lower_plugin_covering_next118 ON wp_options(lower(option_name), autoload, option_value, blog_id) WHERE autoload = 'yes'",
        ]],
    ];
};

$currentSource118 = static function () use ($preparedSource118): array {
    $source = $preparedSource118([
        'name' => 'current-stat4-partial-expression-covering-next118',
        'schemaCookie' => 1184,
        'stat4Generation' => 21,
    ]);
    $source['indexes'][0]['rootPage'] = 11844;
    $source['indexes'][0]['stat4Samples'] = [
        ['neq' => '1 1', 'nlt' => '0 0', 'ndlt' => '0 0', 'sample' => ['plugin_cache', 201]],
        ['neq' => '2 1', 'nlt' => '1 1', 'ndlt' => '1 1', 'sample' => ['plugin_forms', 202]],
        ['neq' => '2 1', 'nlt' => '3 2', 'ndlt' => '2 2', 'sample' => ['plugin_mail', 203]],
        ['neq' => '4 1', 'nlt' => '5 3', 'ndlt' => '3 3', 'sample' => ['plugin_seo', 204]],
    ];

    return $source;
};

$rows118 = static fn (): array => [
    ['rowid' => 31, 'option_name' => 'Plugin_Forms', 'autoload' => 'yes', 'option_value' => 'forms-enabled', 'blog_id' => 1],
    ['rowid' => 11, 'option_name' => 'plugin_cache', 'autoload' => 'yes', 'option_value' => 'cache-enabled', 'blog_id' => 1],
    ['rowid' => 41, 'option_name' => 'plugin_mail', 'autoload' => 'yes', 'option_value' => 'mail-enabled', 'blog_id' => 2],
    ['rowid' => 21, 'option_name' => 'plugin_seo', 'autoload' => 'yes', 'option_value' => 'seo-enabled', 'blog_id' => 1],
    ['rowid' => 51, 'option_name' => 'plugin_cache', 'autoload' => 'no', 'option_value' => 'cache-disabled', 'blog_id' => 3],
    ['rowid' => 61, 'option_name' => 'siteurl', 'autoload' => 'yes', 'option_value' => 'https://example.test', 'blog_id' => 1],
];

$plan118 = static fn (
    ?array $prepared = null,
    ?array $current = null,
    ?array $predicate = null,
    ?array $rows = null,
    ?array $needed = null,
): array => SQLitePlannerStat4PartialExpressionCoveringCurrentSourceNextPlan::materializeNext118(
    $prepared ?? $preparedSource118(),
    $current ?? $currentSource118(),
    $predicate ?? $GLOBALS['predicate_next118'],
    $rows ?? $GLOBALS['rows_next118'](),
    $GLOBALS['order_next118'],
    $needed ?? $GLOBALS['needed_next118'],
    [$GLOBALS['lower_next118']],
);

$GLOBALS['predicate_next118'] = $predicate118;
$GLOBALS['rows_next118'] = $rows118;
$GLOBALS['order_next118'] = $order118;
$GLOBALS['needed_next118'] = $needed118;
$GLOBALS['lower_next118'] = $lower118;

$tests = [
    'planner stat4 partial expression covering current source next118 status ready' => static fn (TestRunner $t) => $t->same('stat4-partial-expression-covering-current-source-ready', $plan118()['status']),
    'planner stat4 partial expression covering current source next118 selects current' => static fn (TestRunner $t) => $t->same('current', $plan118()['selectedSource']),
    'planner stat4 partial expression covering current source next118 stale prepared true' => static fn (TestRunner $t) => $t->same(true, $plan118()['stalePreparedStatement']),
    'planner stat4 partial expression covering current source next118 reprepare true' => static fn (TestRunner $t) => $t->same(true, $plan118()['reprepareRequired']),
    'planner stat4 partial expression covering current source next118 schema changed' => static fn (TestRunner $t) => $t->same(true, $plan118()['schemaCookieChanged']),
    'planner stat4 partial expression covering current source next118 stat4 changed' => static fn (TestRunner $t) => $t->same(true, $plan118()['stat4GenerationChanged']),
    'planner stat4 partial expression covering current source next118 signature changed' => static fn (TestRunner $t) => $t->same(true, $plan118()['indexSignatureChanged']),
    'planner stat4 partial expression covering current source next118 selected index name' => static fn (TestRunner $t) => $t->same('idx_wp_options_lower_plugin_covering_next118', $plan118()['selectedPlan']['name']),
    'planner stat4 partial expression covering current source next118 selected root page' => static fn (TestRunner $t) => $t->same(11844, $plan118()['selectedPlan']['rootPage']),
    'planner stat4 partial expression covering current source next118 selected type lower' => static fn (TestRunner $t) => $t->same('lower', $plan118()['selectedPlan']['type']),
    'planner stat4 partial expression covering current source next118 selected column' => static fn (TestRunner $t) => $t->same('option_name', $plan118()['selectedPlan']['column']),
    'planner stat4 partial expression covering current source next118 operator in' => static fn (TestRunner $t) => $t->same('IN', $plan118()['selectedPlan']['operator']),
    'planner stat4 partial expression covering current source next118 partial true' => static fn (TestRunner $t) => $t->same(true, $plan118()['selectedPlan']['partial']),
    'planner stat4 partial expression covering current source next118 covering true' => static fn (TestRunner $t) => $t->same(true, $plan118()['selectedPlan']['covering']),
    'planner stat4 partial expression covering current source next118 stat4 used' => static fn (TestRunner $t) => $t->same(true, $plan118()['selectedPlan']['stat4Used']),
    'planner stat4 partial expression covering current source next118 matched stat4 count' => static fn (TestRunner $t) => $t->same(3, $plan118()['selectedPlan']['stat4MatchedSamples']),
    'planner stat4 partial expression covering current source next118 stat4 estimate' => static fn (TestRunner $t) => $t->same(7, $plan118()['selectedPlan']['stat4Estimate']),
    'planner stat4 partial expression covering current source next118 estimated rows' => static fn (TestRunner $t) => $t->same(7, $plan118()['selectedPlan']['estimatedRows']),
    'planner stat4 partial expression covering current source next118 covered row count' => static fn (TestRunner $t) => $t->same(3, $plan118()['selectedPlan']['coveredRowCount']),
    'planner stat4 partial expression covering current source next118 row order by lower key' => static fn (TestRunner $t) => $t->same(['plugin_cache', 'plugin_forms', 'plugin_seo'], array_map(static fn (array $pair): mixed => $pair['current']['key'], $plan118()['selectedPlan']['currentNextRows'])),
    'planner stat4 partial expression covering current source next118 current next first rowid' => static fn (TestRunner $t) => $t->same(11, $plan118()['selectedPlan']['currentNextRows'][0]['current']['rowid']),
    'planner stat4 partial expression covering current source next118 current next second rowid' => static fn (TestRunner $t) => $t->same(31, $plan118()['selectedPlan']['currentNextRows'][1]['current']['rowid']),
    'planner stat4 partial expression covering current source next118 first points next' => static fn (TestRunner $t) => $t->same(31, $plan118()['selectedPlan']['currentNextRows'][0]['next']['rowid']),
    'planner stat4 partial expression covering current source next118 last eof' => static fn (TestRunner $t) => $t->same(null, $plan118()['selectedPlan']['currentNextRows'][2]['next']),
    'planner stat4 partial expression covering current source next118 covering option value' => static fn (TestRunner $t) => $t->same('cache-enabled', $plan118()['selectedPlan']['currentNextRows'][0]['current']['covering']['option_value']),
    'planner stat4 partial expression covering current source next118 covering blog id' => static fn (TestRunner $t) => $t->same(1, $plan118()['selectedPlan']['currentNextRows'][2]['current']['covering']['blog_id']),
    'planner stat4 partial expression covering current source next118 expression payload' => static fn (TestRunner $t) => $t->same('plugin_forms', $plan118()['selectedPlan']['currentNextRows'][1]['current']['coveringExpressions']['lower(option_name)']),
    'planner stat4 partial expression covering current source next118 excludes partial miss' => static fn (TestRunner $t) => $t->same(false, in_array(51, array_map(static fn (array $pair): mixed => $pair['current']['rowid'], $plan118()['selectedPlan']['currentNextRows']), true)),
    'planner stat4 partial expression covering current source next118 excludes unmatched stat4 row' => static fn (TestRunner $t) => $t->same(false, in_array(41, array_map(static fn (array $pair): mixed => $pair['current']['rowid'], $plan118()['selectedPlan']['currentNextRows']), true)),
    'planner stat4 partial expression covering current source next118 excludes unmatched name' => static fn (TestRunner $t) => $t->same(false, in_array(61, array_map(static fn (array $pair): mixed => $pair['current']['rowid'], $plan118()['selectedPlan']['currentNextRows']), true)),
    'planner stat4 partial expression covering current source next118 payload columns' => static fn (TestRunner $t) => $t->same($GLOBALS['needed_next118'], $plan118()['coveringPayloadColumns']),
    'planner stat4 partial expression covering current source next118 expression count' => static fn (TestRunner $t) => $t->same(1, $plan118()['coveringExpressionCount']),
    'planner stat4 partial expression covering current source next118 defers table seek' => static fn (TestRunner $t) => $t->same(true, $plan118()['deferredTableSeek']),
    'planner stat4 partial expression covering current source next118 no temp payload btree' => static fn (TestRunner $t) => $t->same(false, $plan118()['tempBtreeForCoveringPayload']),
    'planner stat4 partial expression covering current source next118 cursor source current' => static fn (TestRunner $t) => $t->same('current', $plan118()['cursorTape']['source']),
    'planner stat4 partial expression covering current source next118 cursor index name' => static fn (TestRunner $t) => $t->same('idx_wp_options_lower_plugin_covering_next118', $plan118()['cursorTape']['indexName']),
    'planner stat4 partial expression covering current source next118 cursor root page' => static fn (TestRunner $t) => $t->same(11844, $plan118()['cursorTape']['rootPage']),
    'planner stat4 partial expression covering current source next118 cursor matched samples' => static fn (TestRunner $t) => $t->same(3, count($plan118()['cursorTape']['stat4MatchedCurrentNext'])),
    'planner stat4 partial expression covering current source next118 opcode opens index' => static fn (TestRunner $t) => $t->same('OpenRead', $plan118()['cursorTape']['opcodes'][0]['opcode']),
    'planner stat4 partial expression covering current source next118 opcode seeks stat4' => static fn (TestRunner $t) => $t->same('SeekStat4Matched', $plan118()['cursorTape']['opcodes'][1]['opcode']),
    'planner stat4 partial expression covering current source next118 opcode reads option value' => static fn (TestRunner $t) => $t->same('option_value', $plan118()['cursorTape']['opcodes'][4]['column']),
    'planner stat4 partial expression covering current source next118 opcode result covering' => static fn (TestRunner $t) => $t->same('covering-index', $plan118()['cursorTape']['opcodes'][6]['source']),
    'planner stat4 partial expression covering current source next118 fence cookie' => static fn (TestRunner $t) => $t->same(1184, $plan118()['currentSourceFence']['schemaCookie']),
    'planner stat4 partial expression covering current source next118 fence stat4' => static fn (TestRunner $t) => $t->same(21, $plan118()['currentSourceFence']['stat4Generation']),
    'planner stat4 partial expression covering current source next118 fence covering signature' => static fn (TestRunner $t) => $t->same('option_name,autoload,option_value,blog_id', $plan118()['currentSourceFence']['coveringSignature']),
    'planner stat4 partial expression covering current source next118 prepared summary rows' => static fn (TestRunner $t) => $t->same(3, $plan118()['preparedSource']['coveredRowCount']),
    'planner stat4 partial expression covering current source next118 current summary rows' => static fn (TestRunner $t) => $t->same(3, $plan118()['currentSource']['coveredRowCount']),
    'planner stat4 partial expression covering current source next118 detail reprepare' => static fn (TestRunner $t) => $t->contains('REPREPARE STAT4 PARTIAL EXPRESSION COVERING INDEX', $plan118()['detail']),
    'planner stat4 partial expression covering current source next118 dependency marker' => static fn (TestRunner $t) => $t->contains('sqlite-stat4-partial-expression-covering-current-source-next118', implode(',', $plan118()['dependencies'])),
    'planner stat4 partial expression covering current source next118 dependency closure' => static fn (TestRunner $t) => $t->contains('no new support component needed', $plan118()['dependency_closure']),
    'planner stat4 partial expression covering current source next118 non overlap' => static fn (TestRunner $t) => $t->contains('avoids accepted expression ORDER BY', $plan118()['non_overlap']),
];

$fresh118 = static fn (): array => $plan118($preparedSource118(), $preparedSource118(['name' => 'current-fresh-stat4-partial-expression-covering-next118']));
$tests['planner stat4 partial expression covering current source next118 reuses prepared when fresh'] = static fn (TestRunner $t) => $t->same('prepared', $fresh118()['selectedSource']);
$tests['planner stat4 partial expression covering current source next118 fresh no reprepare'] = static fn (TestRunner $t) => $t->same(false, $fresh118()['reprepareRequired']);
$tests['planner stat4 partial expression covering current source next118 fresh root page'] = static fn (TestRunner $t) => $t->same(11801, $fresh118()['selectedPlan']['rootPage']);

$missingPartial118 = static fn (): array => $plan118(null, null, $in118($lower118, ['plugin_cache']));
$tests['planner stat4 partial expression covering current source next118 missing partial proof falls back'] = static fn (TestRunner $t) => $t->same('requires-next-stage', $missingPartial118()['status']);
$tests['planner stat4 partial expression covering current source next118 missing partial no index'] = static fn (TestRunner $t) => $t->same(null, $missingPartial118()['cursorTape']['indexName']);
$tests['planner stat4 partial expression covering current source next118 missing partial table scan opcode'] = static fn (TestRunner $t) => $t->same('Rewind', $missingPartial118()['cursorTape']['opcodes'][1]['opcode']);

$nonCovering118 = static function () use ($currentSource118, $plan118): array {
    $current = $currentSource118();
    $current['indexes'][0]['coveringColumns'] = ['option_name', 'autoload'];

    return $plan118(null, $current);
};
$tests['planner stat4 partial expression covering current source next118 non covering falls back'] = static fn (TestRunner $t) => $t->same('requires-next-stage', $nonCovering118()['status']);
$tests['planner stat4 partial expression covering current source next118 non covering current rows zero'] = static fn (TestRunner $t) => $t->same(0, $nonCovering118()['currentSource']['coveredRowCount']);

$noStat4118 = static function () use ($currentSource118, $plan118): array {
    $current = $currentSource118();
    $current['indexes'][0]['stat4Samples'] = [];

    return $plan118(null, $current);
};
$tests['planner stat4 partial expression covering current source next118 no stat4 falls back'] = static fn (TestRunner $t) => $t->same('requires-next-stage', $noStat4118()['status']);
$tests['planner stat4 partial expression covering current source next118 no stat4 not deferred'] = static fn (TestRunner $t) => $t->same(false, $noStat4118()['deferredTableSeek']);

$tests['planner stat4 partial expression covering current source next118 validates source indexes'] = static function (TestRunner $t) use ($preparedSource118, $currentSource118, $predicate118, $rows118, $order118, $needed118, $lower118): void {
    $bad = $preparedSource118();
    $bad['indexes'] = [];
    $t->throws(InvalidArgumentException::class, static fn () => SQLitePlannerStat4PartialExpressionCoveringCurrentSourceNextPlan::materializeNext118($bad, $currentSource118(), $predicate118, $rows118(), $order118, $needed118, [$lower118]));
};
$tests['planner stat4 partial expression covering current source next118 validates schema cookie'] = static function (TestRunner $t) use ($preparedSource118, $currentSource118, $predicate118, $rows118, $order118, $needed118, $lower118): void {
    $bad = $preparedSource118(['schemaCookie' => -1]);
    $t->throws(InvalidArgumentException::class, static fn () => SQLitePlannerStat4PartialExpressionCoveringCurrentSourceNextPlan::materializeNext118($bad, $currentSource118(), $predicate118, $rows118(), $order118, $needed118, [$lower118]));
};
$tests['planner stat4 partial expression covering current source next118 validates covering columns'] = static function (TestRunner $t) use ($preparedSource118, $currentSource118, $predicate118, $rows118, $order118, $lower118): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLitePlannerStat4PartialExpressionCoveringCurrentSourceNextPlan::materializeNext118($preparedSource118(), $currentSource118(), $predicate118, $rows118(), $order118, [], [$lower118]));
};

return $tests;

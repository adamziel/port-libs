<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan;

$expr158 = static fn (string $function, string $column): array => ['function' => $function, 'column' => $column];
$column158 = static fn (string $name): array => ['column' => $name];
$point158 = static fn (array $left, mixed $value): array => ['operator' => '=', 'left' => $left, 'right' => $value];
$range158 = static fn (array $left, string $operator, mixed $value): array => ['operator' => $operator, 'left' => $left, 'right' => $value];
$and158 = static fn (array ...$terms): array => ['operator' => 'AND', 'terms' => $terms];

$lower158 = $expr158('lower', 'option_name');
$predicate158 = $and158(
    $range158($lower158, '>=', 'plugin_'),
    $range158($lower158, '<', 'plugin_z'),
    $point158($column158('autoload'), 'yes'),
);
$order158 = [$lower158, ['column' => 'option_id']];
$needed158 = ['option_name', 'autoload', 'option_value', 'option_id', 'blog_id'];
$neededExpressions158 = [$lower158];

$preparedSource158 = static function (array $overrides = []): array {
    return $overrides + [
        'name' => 'prepared-stat4-expression-partial-current-source-rangeFence',
        'schemaCookie' => 1580,
        'stat4Generation' => 90,
        'rowGeneration' => 20,
        'indexes' => [[
            'name' => 'idx_wp_options_lower_partial_stat4_rangeFence',
            'rootPage' => 15801,
            'estimatedRows' => 420,
            'coveringColumns' => ['option_name', 'autoload', 'option_value', 'option_id', 'blog_id'],
            'coveringExpressions' => [['function' => 'lower', 'column' => 'option_name']],
            'stat4Samples' => [
                ['neq' => '1 1', 'nlt' => '0 0', 'ndlt' => '0 0', 'sample' => ['plugin_cache', 10]],
                ['neq' => '2 1', 'nlt' => '1 1', 'ndlt' => '1 1', 'sample' => ['plugin_forms', 20]],
                ['neq' => '1 1', 'nlt' => '3 2', 'ndlt' => '2 2', 'sample' => ['plugin_seo', 30]],
            ],
            'sql' => "CREATE INDEX idx_wp_options_lower_partial_stat4_rangeFence ON wp_options(lower(option_name), option_id, option_value, blog_id) WHERE autoload = 'yes' AND lower(option_name) >= 'plugin_'",
        ]],
    ];
};

$currentSource158 = static function () use ($preparedSource158): array {
    $source = $preparedSource158([
        'name' => 'current-stat4-expression-partial-current-source-rangeFence',
        'schemaCookie' => 1586,
        'stat4Generation' => 99,
        'rowGeneration' => 27,
    ]);
    $source['indexes'][0]['rootPage'] = 15866;
    $source['indexes'][0]['estimatedRows'] = 96;
    $source['indexes'][0]['stat4Samples'] = [
        ['neq' => '1 1', 'nlt' => '0 0', 'ndlt' => '0 0', 'sample' => ['plugin_alpha', 11]],
        ['neq' => '2 1', 'nlt' => '1 1', 'ndlt' => '1 1', 'sample' => ['plugin_cache', 21]],
        ['neq' => '1 1', 'nlt' => '3 2', 'ndlt' => '2 2', 'sample' => ['plugin_forms', 31]],
        ['neq' => '1 1', 'nlt' => '4 3', 'ndlt' => '3 3', 'sample' => ['plugin_mail', 41]],
        ['neq' => '3 1', 'nlt' => '5 4', 'ndlt' => '4 4', 'sample' => ['plugin_seo', 51]],
        ['neq' => '1 1', 'nlt' => '8 5', 'ndlt' => '5 5', 'sample' => ['theme_mods', 71]],
    ];

    return $source;
};

$preparedRows158 = static fn (): array => [
    ['rowid' => 21, 'option_name' => 'plugin_cache', 'autoload' => 'yes', 'option_value' => 'cache-old', 'option_id' => 21, 'blog_id' => 1],
    ['rowid' => 31, 'option_name' => 'Plugin_Forms', 'autoload' => 'yes', 'option_value' => 'forms-enabled', 'option_id' => 31, 'blog_id' => 1],
    ['rowid' => 51, 'option_name' => 'plugin_seo', 'autoload' => 'yes', 'option_value' => 'seo-enabled', 'option_id' => 51, 'blog_id' => 1],
    ['rowid' => 81, 'option_name' => 'plugin_deleted', 'autoload' => 'yes', 'option_value' => 'deleted', 'option_id' => 81, 'blog_id' => 1],
];

$currentRows158 = static fn (): array => [
    ['rowid' => 41, 'option_name' => 'Plugin_Mail', 'autoload' => 'yes', 'option_value' => 'mail-enabled', 'option_id' => 41, 'blog_id' => 2],
    ['rowid' => 11, 'option_name' => 'plugin_alpha', 'autoload' => 'yes', 'option_value' => 'alpha-enabled', 'option_id' => 11, 'blog_id' => 1],
    ['rowid' => 31, 'option_name' => 'Plugin_Forms', 'autoload' => 'yes', 'option_value' => 'forms-enabled', 'option_id' => 31, 'blog_id' => 1],
    ['rowid' => 21, 'option_name' => 'plugin_cache', 'autoload' => 'yes', 'option_value' => 'cache-new', 'option_id' => 21, 'blog_id' => 1],
    ['rowid' => 51, 'option_name' => 'plugin_seo', 'autoload' => 'yes', 'option_value' => 'seo-enabled', 'option_id' => 51, 'blog_id' => 1],
    ['rowid' => 61, 'option_name' => 'plugin_cache', 'autoload' => 'no', 'option_value' => 'cache-disabled', 'option_id' => 61, 'blog_id' => 3],
    ['rowid' => 71, 'option_name' => 'theme_mods', 'autoload' => 'yes', 'option_value' => 'theme', 'option_id' => 71, 'blog_id' => 1],
];

$plan158 = static fn (
    ?array $preparedSource = null,
    ?array $currentSource = null,
    ?array $preparedRows = null,
    ?array $currentRows = null,
    ?array $predicate = null,
): array => SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan::materializeCurrentSourceRangeFence(
    $preparedSource ?? $preparedSource158(),
    $currentSource ?? $currentSource158(),
    $predicate ?? $predicate158,
    $preparedRows ?? $preparedRows158(),
    $currentRows ?? $currentRows158(),
    $order158,
    $needed158,
    $neededExpressions158,
);

$fresh158 = static fn (): array => $plan158($preparedSource158(), $preparedSource158(['name' => 'current-fresh-rangeFence']), $preparedRows158(), $preparedRows158());
$noStat4158 = static function () use ($currentSource158, $plan158): array {
    $current = $currentSource158();
    $current['indexes'][0]['stat4Samples'] = [];

    return $plan158(null, $current);
};
$nonCovering158 = static function () use ($currentSource158, $plan158): array {
    $current = $currentSource158();
    $current['indexes'][0]['coveringColumns'] = ['option_name'];

    return $plan158(null, $current);
};

$tests = [
    'planner stat4 expression partial current source rangeFence status ready' => static fn (TestRunner $t) => $t->same('stat4-expression-partial-current-source-rangeFence-ready', $plan158()['status']),
    'planner stat4 expression partial current source rangeFence selects current' => static fn (TestRunner $t) => $t->same('current', $plan158()['selectedSource']),
    'planner stat4 expression partial current source rangeFence stale prepared' => static fn (TestRunner $t) => $t->same(true, $plan158()['stalePreparedStatement']),
    'planner stat4 expression partial current source rangeFence reprepare required' => static fn (TestRunner $t) => $t->same(true, $plan158()['reprepareRequired']),
    'planner stat4 expression partial current source rangeFence schema changed' => static fn (TestRunner $t) => $t->same(true, $plan158()['schemaCookieChanged']),
    'planner stat4 expression partial current source rangeFence stat4 changed' => static fn (TestRunner $t) => $t->same(true, $plan158()['stat4GenerationChanged']),
    'planner stat4 expression partial current source rangeFence index changed' => static fn (TestRunner $t) => $t->same(true, $plan158()['indexSignatureChanged']),
    'planner stat4 expression partial current source rangeFence row signature changed' => static fn (TestRunner $t) => $t->same(true, $plan158()['rowSignatureChanged']),
    'planner stat4 expression partial current source rangeFence selected index' => static fn (TestRunner $t) => $t->same('idx_wp_options_lower_partial_stat4_rangeFence', $plan158()['selectedPlan']['name']),
    'planner stat4 expression partial current source rangeFence root page' => static fn (TestRunner $t) => $t->same(15866, $plan158()['selectedPlan']['rootPage']),
    'planner stat4 expression partial current source rangeFence partial true' => static fn (TestRunner $t) => $t->same(true, $plan158()['selectedPlan']['partial']),
    'planner stat4 expression partial current source rangeFence covering true' => static fn (TestRunner $t) => $t->same(true, $plan158()['selectedPlan']['covering']),
    'planner stat4 expression partial current source rangeFence stat4 used' => static fn (TestRunner $t) => $t->same(true, $plan158()['selectedPlan']['stat4Used']),
    'planner stat4 expression partial current source rangeFence range bounded' => static fn (TestRunner $t) => $t->same('range-bounded', $plan158()['selectedPlan']['operator']),
    'planner stat4 expression partial current source rangeFence type lower' => static fn (TestRunner $t) => $t->same('lower', $plan158()['selectedPlan']['type']),
    'planner stat4 expression partial current source rangeFence column option name' => static fn (TestRunner $t) => $t->same('option_name', $plan158()['selectedPlan']['column']),
    'planner stat4 expression partial current source rangeFence estimated rows' => static fn (TestRunner $t) => $t->same(8, $plan158()['selectedPlan']['estimatedRows']),
    'planner stat4 expression partial current source rangeFence range window count' => static fn (TestRunner $t) => $t->same(5, $plan158()['rangeWindowCount']),
    'planner stat4 expression partial current source rangeFence selected range window count' => static fn (TestRunner $t) => $t->same(5, $plan158()['selectedPlan']['rangeFenceRangeWindowCount']),
    'planner stat4 expression partial current source rangeFence rowids' => static fn (TestRunner $t) => $t->same([11, 21, 31, 41, 51], $plan158()['rangeWindowRowids']),
    'planner stat4 expression partial current source rangeFence keys' => static fn (TestRunner $t) => $t->same(['plugin_alpha', 'plugin_cache', 'plugin_forms', 'plugin_mail', 'plugin_seo'], $plan158()['rangeWindowKeys']),
    'planner stat4 expression partial current source rangeFence selected keys' => static fn (TestRunner $t) => $t->same(['plugin_alpha', 'plugin_cache', 'plugin_forms', 'plugin_mail', 'plugin_seo'], $plan158()['selectedPlan']['rangeFenceRangeWindowKeys']),
    'planner stat4 expression partial current source rangeFence lower fence key' => static fn (TestRunner $t) => $t->same('plugin_alpha', $plan158()['lowerFenceKey']),
    'planner stat4 expression partial current source rangeFence upper fence key' => static fn (TestRunner $t) => $t->same('plugin_seo', $plan158()['upperFenceKey']),
    'planner stat4 expression partial current source rangeFence upper next key' => static fn (TestRunner $t) => $t->same('theme_mods', $plan158()['upperFenceNextKey']),
    'planner stat4 expression partial current source rangeFence lower fence not exact' => static fn (TestRunner $t) => $t->same(false, $plan158()['selectedPlan']['rangeFenceRangeFenceExactLower']),
    'planner stat4 expression partial current source rangeFence upper fence not exact' => static fn (TestRunner $t) => $t->same(false, $plan158()['selectedPlan']['rangeFenceRangeFenceExactUpper']),
    'planner stat4 expression partial current source rangeFence first window anchor' => static fn (TestRunner $t) => $t->same('plugin_alpha', $plan158()['rangeWindows'][0]['anchor']),
    'planner stat4 expression partial current source rangeFence first window rowid' => static fn (TestRunner $t) => $t->same(11, $plan158()['rangeWindows'][0]['firstRowid']),
    'planner stat4 expression partial current source rangeFence cache window rowid' => static fn (TestRunner $t) => $t->same([21], $plan158()['rangeWindows'][1]['rowids']),
    'planner stat4 expression partial current source rangeFence last window eof' => static fn (TestRunner $t) => $t->same(null, $plan158()['rangeWindows'][4]['nextAnchor']),
    'planner stat4 expression partial current source rangeFence stale blocked' => static fn (TestRunner $t) => $t->same([81], $plan158()['stalePreparedRowidsBlockedByRangeFence']),
    'planner stat4 expression partial current source rangeFence inserted admitted' => static fn (TestRunner $t) => $t->same([11, 41, 61, 71], $plan158()['currentSourceRowidsAdmittedByRangeFence']),
    'planner stat4 expression partial current source rangeFence updated refreshed' => static fn (TestRunner $t) => $t->same([21], $plan158()['currentSourceRowidsRefreshedByRangeFence']),
    'planner stat4 expression partial current source rangeFence excludes deleted prepared' => static fn (TestRunner $t) => $t->same(false, in_array(81, $plan158()['rangeWindowRowids'], true)),
    'planner stat4 expression partial current source rangeFence excludes disabled partial miss' => static fn (TestRunner $t) => $t->same(false, in_array(61, $plan158()['rangeWindowRowids'], true)),
    'planner stat4 expression partial current source rangeFence excludes upper range miss' => static fn (TestRunner $t) => $t->same(false, in_array(71, $plan158()['rangeWindowRowids'], true)),
    'planner stat4 expression partial current source rangeFence current cache payload' => static fn (TestRunner $t) => $t->same('cache-new', $plan158()['rangeWindowRows'][1]['covering']['option_value']),
    'planner stat4 expression partial current source rangeFence mixed case expression payload' => static fn (TestRunner $t) => $t->same('plugin_forms', $plan158()['rangeWindowRows'][2]['coveringExpressions']['lower(option_name)']),
    'planner stat4 expression partial current source rangeFence table lookup elided' => static fn (TestRunner $t) => $t->same(true, $plan158()['cursorTape']['tableLookupElidedForRangeWindow']),
    'planner stat4 expression partial current source rangeFence rangeFence source only' => static fn (TestRunner $t) => $t->same(true, $plan158()['selectedPlan']['rangeFenceUsesCurrentSourceOnly']),
    'planner stat4 expression partial current source rangeFence program open read' => static fn (TestRunner $t) => $t->same('OpenRead', $plan158()['cursorTape']['rangeFenceProgram'][0]['opcode']),
    'planner stat4 expression partial current source rangeFence program fences source' => static fn (TestRunner $t) => $t->same('FenceCurrentSource', $plan158()['cursorTape']['rangeFenceProgram'][1]['opcode']),
    'planner stat4 expression partial current source rangeFence program lower seek' => static fn (TestRunner $t) => $t->same(['opcode' => 'SeekGE', 'source' => 'index', 'key' => 'plugin_'], $plan158()['cursorTape']['rangeFenceProgram'][2]),
    'planner stat4 expression partial current source rangeFence program upper stop' => static fn (TestRunner $t) => $t->same(['opcode' => 'IdxGE', 'source' => 'index', 'key' => 'plugin_z'], $plan158()['cursorTape']['rangeFenceProgram'][3]),
    'planner stat4 expression partial current source rangeFence program filters stale' => static fn (TestRunner $t) => $t->same(['opcode' => 'FilterDeletedRowids', 'rowids' => [81]], $plan158()['cursorTape']['rangeFenceProgram'][4]),
    'planner stat4 expression partial current source rangeFence program emits covering row' => static fn (TestRunner $t) => $t->same('ResultRow', $plan158()['cursorTape']['rangeFenceProgram'][5]['opcode']),
    'planner stat4 expression partial current source rangeFence cursor lower fence' => static fn (TestRunner $t) => $t->same('plugin_', $plan158()['cursorTape']['rangeFenceLower']),
    'planner stat4 expression partial current source rangeFence cursor upper fence' => static fn (TestRunner $t) => $t->same('plugin_z', $plan158()['cursorTape']['rangeFenceUpper']),
    'planner stat4 expression partial current source rangeFence signature length' => static fn (TestRunner $t) => $t->same(64, strlen($plan158()['rangeWindowSignature'])),
    'planner stat4 expression partial current source rangeFence fence signature length' => static fn (TestRunner $t) => $t->same(64, strlen($plan158()['currentSourceFence']['rangeFenceRangeFenceSignature'])),
    'planner stat4 expression partial current source rangeFence window signature length' => static fn (TestRunner $t) => $t->same(64, strlen($plan158()['currentSourceFence']['rangeFenceRangeWindowSignature'])),
    'planner stat4 expression partial current source rangeFence detail' => static fn (TestRunner $t) => $t->contains('REPREPARE STAT4 EXPRESSION PARTIAL CURRENT-SOURCE RANGE FENCE', $plan158()['detail']),
    'planner stat4 expression partial current source rangeFence dependency marker' => static fn (TestRunner $t) => $t->true(in_array('sqlite-sqlplanner-stat4-expression-partial-current-source-rangeFence', $plan158()['dependencies'], true)),
    'planner stat4 expression partial current source rangeFence dependency closure' => static fn (TestRunner $t) => $t->contains('no new support component needed', $plan158()['dependency_closure']),
    'planner stat4 expression partial current source rangeFence non overlap' => static fn (TestRunner $t) => $t->contains('stale prepared row exclusion', $plan158()['non_overlap']),
    'planner stat4 expression partial current source rangeFence fresh requires next stage' => static fn (TestRunner $t) => $t->same('requires-next-stage', $fresh158()['status']),
    'planner stat4 expression partial current source rangeFence fresh no row signature change' => static fn (TestRunner $t) => $t->same(false, $fresh158()['rowSignatureChanged']),
    'planner stat4 expression partial current source rangeFence no stat4 requires next' => static fn (TestRunner $t) => $t->same('requires-next-stage', $noStat4158()['status']),
    'planner stat4 expression partial current source rangeFence non covering requires next' => static fn (TestRunner $t) => $t->same('requires-next-stage', $nonCovering158()['status']),
    'planner stat4 expression partial current source rangeFence validates rowid' => static function (TestRunner $t) use ($preparedRows158, $currentRows158, $plan158): void {
        $bad = $preparedRows158();
        $bad[0]['rowid'] = -1;
        $t->throws(InvalidArgumentException::class, static fn () => $plan158(null, null, $bad, $currentRows158()));
    },
];

return $tests;

<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan;

$expr155 = static fn (string $function, string $column): array => ['function' => $function, 'column' => $column];
$column155 = static fn (string $name): array => ['column' => $name];
$point155 = static fn (array $left, mixed $value): array => ['operator' => '=', 'left' => $left, 'right' => $value];
$range155 = static fn (array $left, string $operator, mixed $value): array => ['operator' => $operator, 'left' => $left, 'right' => $value];
$notNull155 = static fn (array $left): array => ['operator' => 'IS NOT NULL', 'left' => $left];
$and155 = static fn (array ...$terms): array => ['operator' => 'AND', 'terms' => $terms];

$lower155 = $expr155('lower', 'option_name');
$predicate155 = $and155(
    $range155($lower155, '>=', 'plugin_'),
    $range155($lower155, '<', 'plugin_z'),
    $point155($column155('autoload'), 'yes'),
    $point155($column155('blog_id'), 1),
    $notNull155($column155('option_value')),
);
$order155 = [$lower155, ['column' => 'option_id']];
$needed155 = ['option_name', 'autoload', 'option_value', 'option_id', 'blog_id'];
$neededExpressions155 = [$lower155];

$preparedSource155 = static function (array $overrides = []): array {
    return array_replace_recursive([
        'name' => 'prepared-stat4-expression-partial-next155',
        'schemaCookie' => 1550,
        'stat4Generation' => 21,
        'rowGeneration' => 8,
        'indexes' => [[
            'name' => 'idx_wp_options_lower_blog_autoload_partial_next155',
            'rootPage' => 15501,
            'estimatedRows' => 340,
            'coveringColumns' => ['option_name', 'autoload', 'option_value', 'option_id', 'blog_id'],
            'coveringExpressions' => [['function' => 'lower', 'column' => 'option_name']],
            'stat4Samples' => [
                ['neq' => '1 1', 'nlt' => '0 0', 'ndlt' => '0 0', 'sample' => ['plugin_cache', 101]],
                ['neq' => '1 1', 'nlt' => '1 1', 'ndlt' => '1 1', 'sample' => ['plugin_forms', 102]],
                ['neq' => '1 1', 'nlt' => '2 2', 'ndlt' => '2 2', 'sample' => ['plugin_mail', 103]],
            ],
            'sql' => "CREATE INDEX idx_wp_options_lower_blog_autoload_partial_next155 ON wp_options(lower(option_name), option_id, option_value, blog_id) WHERE autoload = 'yes' AND blog_id = 1",
        ]],
        'rows' => [
            ['rowid' => 101, 'option_id' => 101, 'option_name' => 'Plugin_Cache', 'autoload' => 'yes', 'option_value' => 'prepared-cache', 'blog_id' => 1],
            ['rowid' => 102, 'option_id' => 102, 'option_name' => 'plugin_forms', 'autoload' => 'yes', 'option_value' => 'forms', 'blog_id' => 1],
            ['rowid' => 103, 'option_id' => 103, 'option_name' => 'plugin_mail', 'autoload' => 'yes', 'option_value' => 'mail', 'blog_id' => 1],
            ['rowid' => 104, 'option_id' => 104, 'option_name' => 'plugin_zero', 'autoload' => 'yes', 'option_value' => null, 'blog_id' => 1],
        ],
    ], $overrides);
};

$currentSource155 = static function (array $overrides = []) use ($preparedSource155): array {
    $source = $preparedSource155([
        'name' => 'current-stat4-expression-partial-next155',
        'schemaCookie' => 1556,
        'stat4Generation' => 29,
        'rowGeneration' => 14,
    ]);
    $source['indexes'][0]['rootPage'] = 15566;
    $source['indexes'][0]['estimatedRows'] = 24;
    $source['indexes'][0]['stat4Samples'] = [
        ['neq' => '1 1', 'nlt' => '0 0', 'ndlt' => '0 0', 'sample' => ['plugin_alpha', 201]],
        ['neq' => '2 1', 'nlt' => '1 1', 'ndlt' => '1 1', 'sample' => ['plugin_cache', 202]],
        ['neq' => '1 1', 'nlt' => '3 2', 'ndlt' => '2 2', 'sample' => ['plugin_forms', 203]],
        ['neq' => '1 1', 'nlt' => '4 3', 'ndlt' => '3 3', 'sample' => ['plugin_seo', 204]],
    ];
    $source['indexes'][0]['sql'] = "CREATE INDEX idx_wp_options_lower_blog_autoload_partial_next155 ON wp_options(lower(option_name), option_id, option_value, blog_id) WHERE autoload = 'yes' AND blog_id = 1 AND option_value IS NOT NULL";
    $source['rows'] = [
        ['rowid' => 101, 'option_id' => 101, 'option_name' => 'Plugin_Cache', 'autoload' => 'yes', 'option_value' => 'current-cache', 'blog_id' => 1],
        ['rowid' => 102, 'option_id' => 102, 'option_name' => 'plugin_forms', 'autoload' => 'yes', 'option_value' => 'forms', 'blog_id' => 1],
        ['rowid' => 103, 'option_id' => 103, 'option_name' => 'plugin_mail', 'autoload' => 'no', 'option_value' => 'mail-disabled', 'blog_id' => 1],
        ['rowid' => 201, 'option_id' => 201, 'option_name' => 'plugin_alpha', 'autoload' => 'yes', 'option_value' => 'alpha', 'blog_id' => 1],
        ['rowid' => 202, 'option_id' => 202, 'option_name' => 'plugin_seo', 'autoload' => 'yes', 'option_value' => 'seo', 'blog_id' => 1],
        ['rowid' => 203, 'option_id' => 203, 'option_name' => 'plugin_cache', 'autoload' => 'yes', 'option_value' => null, 'blog_id' => 1],
        ['rowid' => 204, 'option_id' => 204, 'option_name' => 'plugin_cache', 'autoload' => 'yes', 'option_value' => 'network', 'blog_id' => 2],
        ['rowid' => 205, 'option_id' => 205, 'option_name' => 'theme_mods', 'autoload' => 'yes', 'option_value' => 'theme', 'blog_id' => 1],
    ];

    return array_replace_recursive($source, $overrides);
};

$plan155 = static fn (
    ?array $prepared = null,
    ?array $current = null,
    ?array $predicate = null,
    ?array $needed = null,
): array => SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan::materializeStat4PartialPredicateDrift(
    $prepared ?? $preparedSource155(),
    $current ?? $currentSource155(),
    $predicate ?? $predicate155,
    $order155,
    $needed ?? $needed155,
    $neededExpressions155,
);

$fresh155 = static fn (): array => $plan155($preparedSource155(), $preparedSource155(['name' => 'current-fresh-next155']));
$noStat4155 = static function () use ($currentSource155, $plan155): array {
    $current = $currentSource155();
    $current['indexes'][0]['stat4Samples'] = [];

    return $plan155(null, $current);
};
$uncovered155 = static function () use ($currentSource155, $plan155): array {
    $current = $currentSource155();
    $current['indexes'][0]['coveringColumns'] = ['option_name'];

    return $plan155(null, $current);
};
$unproved155 = static function () use ($currentSource155, $plan155, $range155, $point155, $notNull155, $column155, $lower155): array {
    return $plan155(null, $currentSource155(), [
        'operator' => 'AND',
        'terms' => [
            $range155($lower155, '>=', 'plugin_'),
            $range155($lower155, '<', 'plugin_z'),
            $point155($column155('autoload'), 'yes'),
            $notNull155($column155('option_value')),
        ],
    ]);
};

return [
    'planner stat4 expression partial current source next155 status ready' => static fn (TestRunner $t) => $t->same('stat4-expression-partial-current-source-next155-ready', $plan155()['status']),
    'planner stat4 expression partial current source next155 selects current' => static fn (TestRunner $t) => $t->same('current', $plan155()['selectedSource']),
    'planner stat4 expression partial current source next155 stale prepared' => static fn (TestRunner $t) => $t->same(true, $plan155()['stalePreparedStatement']),
    'planner stat4 expression partial current source next155 reprepare required' => static fn (TestRunner $t) => $t->same(true, $plan155()['reprepareRequired']),
    'planner stat4 expression partial current source next155 schema changed' => static fn (TestRunner $t) => $t->same(true, $plan155()['schemaCookieChanged']),
    'planner stat4 expression partial current source next155 stat4 changed' => static fn (TestRunner $t) => $t->same(true, $plan155()['stat4GenerationChanged']),
    'planner stat4 expression partial current source next155 row generation changed' => static fn (TestRunner $t) => $t->same(true, $plan155()['rowGenerationChanged']),
    'planner stat4 expression partial current source next155 index changed' => static fn (TestRunner $t) => $t->same(true, $plan155()['indexSignatureChanged']),
    'planner stat4 expression partial current source next155 partial changed' => static fn (TestRunner $t) => $t->same(true, $plan155()['partialPredicateChanged']),
    'planner stat4 expression partial current source next155 selected index' => static fn (TestRunner $t) => $t->same('idx_wp_options_lower_blog_autoload_partial_next155', $plan155()['selectedPlan']['name']),
    'planner stat4 expression partial current source next155 selected root' => static fn (TestRunner $t) => $t->same(15566, $plan155()['selectedPlan']['rootPage']),
    'planner stat4 expression partial current source next155 type lower' => static fn (TestRunner $t) => $t->same('lower', $plan155()['selectedPlan']['type']),
    'planner stat4 expression partial current source next155 column option name' => static fn (TestRunner $t) => $t->same('option_name', $plan155()['selectedPlan']['column']),
    'planner stat4 expression partial current source next155 operator bounded' => static fn (TestRunner $t) => $t->same('range-bounded', $plan155()['selectedPlan']['operator']),
    'planner stat4 expression partial current source next155 partial true' => static fn (TestRunner $t) => $t->same(true, $plan155()['selectedPlan']['partial']),
    'planner stat4 expression partial current source next155 partial implied' => static fn (TestRunner $t) => $t->same(true, ($plan155()['selectedPlan']['partialPredicateImplied'] ?? true) !== false),
    'planner stat4 expression partial current source next155 covering true' => static fn (TestRunner $t) => $t->same(true, $plan155()['selectedPlan']['covering']),
    'planner stat4 expression partial current source next155 stat4 used' => static fn (TestRunner $t) => $t->same(true, $plan155()['selectedPlan']['stat4Used']),
    'planner stat4 expression partial current source next155 order satisfied' => static fn (TestRunner $t) => $t->same(true, $plan155()['selectedPlan']['orderBySatisfied']),
    'planner stat4 expression partial current source next155 estimated rows' => static fn (TestRunner $t) => $t->same(5, $plan155()['selectedPlan']['estimatedRows']),
    'planner stat4 expression partial current source next155 matched samples' => static fn (TestRunner $t) => $t->same(4, $plan155()['selectedPlan']['stat4MatchedSamples']),
    'planner stat4 expression partial current source next155 covered count' => static fn (TestRunner $t) => $t->same(4, $plan155()['selectedPlan']['coveredRowCount']),
    'planner stat4 expression partial current source next155 current rowids' => static fn (TestRunner $t) => $t->same([201, 101, 102, 202], $plan155()['currentCoveringRowids']),
    'planner stat4 expression partial current source next155 prepared rowids' => static fn (TestRunner $t) => $t->same([101, 102, 103], $plan155()['preparedCoveringRowids']),
    'planner stat4 expression partial current source next155 inserted current' => static fn (TestRunner $t) => $t->same([201, 202], $plan155()['selectedPlan']['insertedCurrentRowids']),
    'planner stat4 expression partial current source next155 deleted prepared' => static fn (TestRunner $t) => $t->same([103], $plan155()['selectedPlan']['deletedPreparedRowids']),
    'planner stat4 expression partial current source next155 updated current' => static fn (TestRunner $t) => $t->same([101], $plan155()['selectedPlan']['updatedCurrentRowids']),
    'planner stat4 expression partial current source next155 stable rowids' => static fn (TestRunner $t) => $t->same([102], $plan155()['stableCoveringRowids']),
    'planner stat4 expression partial current source next155 rejects stale partial row' => static fn (TestRunner $t) => $t->same(false, in_array(103, $plan155()['cursorTape']['matchedRowids'], true)),
    'planner stat4 expression partial current source next155 rejects null value row' => static fn (TestRunner $t) => $t->same(false, in_array(203, $plan155()['cursorTape']['matchedRowids'], true)),
    'planner stat4 expression partial current source next155 rejects blog mismatch' => static fn (TestRunner $t) => $t->same(false, in_array(204, $plan155()['cursorTape']['matchedRowids'], true)),
    'planner stat4 expression partial current source next155 rejects upper range' => static fn (TestRunner $t) => $t->same(false, in_array(205, $plan155()['cursorTape']['matchedRowids'], true)),
    'planner stat4 expression partial current source next155 matched keys' => static fn (TestRunner $t) => $t->same(['plugin_alpha', 'plugin_cache', 'plugin_forms', 'plugin_seo'], $plan155()['cursorTape']['matchedKeys']),
    'planner stat4 expression partial current source next155 first payload' => static fn (TestRunner $t) => $t->same('alpha', $plan155()['currentCoveringRows'][0]['covering']['option_value']),
    'planner stat4 expression partial current source next155 updated payload' => static fn (TestRunner $t) => $t->same('current-cache', $plan155()['currentCoveringRows'][1]['covering']['option_value']),
    'planner stat4 expression partial current source next155 expression payload' => static fn (TestRunner $t) => $t->same('plugin_cache', $plan155()['currentCoveringRows'][1]['coveringExpressions']['lower(option_name)']),
    'planner stat4 expression partial current source next155 current next first' => static fn (TestRunner $t) => $t->same(201, $plan155()['currentNextRows'][0]['current']['rowid']),
    'planner stat4 expression partial current source next155 current next second' => static fn (TestRunner $t) => $t->same(101, $plan155()['currentNextRows'][0]['next']['rowid']),
    'planner stat4 expression partial current source next155 current next eof' => static fn (TestRunner $t) => $t->same(null, $plan155()['currentNextRows'][3]['next']),
    'planner stat4 expression partial current source next155 cursor index name' => static fn (TestRunner $t) => $t->same('idx_wp_options_lower_blog_autoload_partial_next155', $plan155()['cursorTape']['indexName']),
    'planner stat4 expression partial current source next155 cursor root' => static fn (TestRunner $t) => $t->same(15566, $plan155()['cursorTape']['rootPage']),
    'planner stat4 expression partial current source next155 cursor lower' => static fn (TestRunner $t) => $t->same('plugin_', $plan155()['cursorTape']['rangeLower']),
    'planner stat4 expression partial current source next155 cursor upper' => static fn (TestRunner $t) => $t->same('plugin_z', $plan155()['cursorTape']['rangeUpper']),
    'planner stat4 expression partial current source next155 cursor deleted blocked' => static fn (TestRunner $t) => $t->same([103], $plan155()['cursorTape']['deletedRowidsBlocked']),
    'planner stat4 expression partial current source next155 cursor inserted' => static fn (TestRunner $t) => $t->same([201, 202], $plan155()['cursorTape']['insertedRowids']),
    'planner stat4 expression partial current source next155 cursor updated' => static fn (TestRunner $t) => $t->same([101], $plan155()['cursorTape']['updatedRowids']),
    'planner stat4 expression partial current source next155 no table lookup' => static fn (TestRunner $t) => $t->same(true, $plan155()['tableLookupElided']),
    'planner stat4 expression partial current source next155 no deferred seek' => static fn (TestRunner $t) => $t->same(null, $plan155()['deferredTableSeekOpcode']),
    'planner stat4 expression partial current source next155 tape table lookup elided' => static fn (TestRunner $t) => $t->same(true, $plan155()['cursorTape']['tableLookupElidedAfterPartialFence']),
    'planner stat4 expression partial current source next155 program opens index' => static fn (TestRunner $t) => $t->same('partial-expression-stat4-index', $plan155()['cursorTape']['program'][0]['source']),
    'planner stat4 expression partial current source next155 program fences partial' => static fn (TestRunner $t) => $t->same('FenceCurrentSource', $plan155()['cursorTape']['program'][1]['opcode']),
    'planner stat4 expression partial current source next155 program seek' => static fn (TestRunner $t) => $t->same('SeekGE', $plan155()['cursorTape']['program'][2]['opcode']),
    'planner stat4 expression partial current source next155 program stop' => static fn (TestRunner $t) => $t->same('IdxGE', $plan155()['cursorTape']['program'][3]['opcode']),
    'planner stat4 expression partial current source next155 program filters stale' => static fn (TestRunner $t) => $t->same(['opcode' => 'FilterStalePartialRowids', 'rowids' => [103]], $plan155()['cursorTape']['program'][4]),
    'planner stat4 expression partial current source next155 program reads current covering' => static fn (TestRunner $t) => $t->same('current-partial-covering-index', $plan155()['cursorTape']['program'][5]['source']),
    'planner stat4 expression partial current source next155 fence cookie' => static fn (TestRunner $t) => $t->same(1556, $plan155()['currentSourceFence']['schemaCookie']),
    'planner stat4 expression partial current source next155 fence stat4' => static fn (TestRunner $t) => $t->same(29, $plan155()['currentSourceFence']['stat4Generation']),
    'planner stat4 expression partial current source next155 fence row generation' => static fn (TestRunner $t) => $t->same(14, $plan155()['currentSourceFence']['rowGeneration']),
    'planner stat4 expression partial current source next155 fence signatures' => static fn (TestRunner $t) => $t->same(64, strlen($plan155()['currentSourceFence']['partialPredicateSignature'])),
    'planner stat4 expression partial current source next155 row stream signature' => static fn (TestRunner $t) => $t->same(64, strlen($plan155()['currentSourceFence']['rowStreamSignature'])),
    'planner stat4 expression partial current source next155 detail' => static fn (TestRunner $t) => $t->contains('REPREPARE STAT4 EXPRESSION PARTIAL CURRENT SOURCE NEXT155', $plan155()['detail']),
    'planner stat4 expression partial current source next155 dependency marker' => static fn (TestRunner $t) => $t->true(in_array('sqlite-sqlplanner-stat4-expression-partial-current-source-next155', $plan155()['dependencies'], true)),
    'planner stat4 expression partial current source next155 dependency closure' => static fn (TestRunner $t) => $t->contains('no new support component needed', $plan155()['dependency_closure']),
    'planner stat4 expression partial current source next155 non overlap' => static fn (TestRunner $t) => $t->contains('current-source partial-predicate drift', $plan155()['non_overlap']),
    'planner stat4 expression partial current source next155 fresh uses prepared' => static fn (TestRunner $t) => $t->same('prepared', $fresh155()['selectedSource']),
    'planner stat4 expression partial current source next155 fresh no partial change' => static fn (TestRunner $t) => $t->same(false, $fresh155()['partialPredicateChanged']),
    'planner stat4 expression partial current source next155 fresh rowids' => static fn (TestRunner $t) => $t->same([101, 102, 103], $fresh155()['cursorTape']['matchedRowids']),
    'planner stat4 expression partial current source next155 no stat4 falls back' => static fn (TestRunner $t) => $t->same('requires-next-stage', $noStat4155()['status']),
    'planner stat4 expression partial current source next155 uncovered falls back' => static fn (TestRunner $t) => $t->same('requires-next-stage', $uncovered155()['status']),
    'planner stat4 expression partial current source next155 unproved falls back' => static fn (TestRunner $t) => $t->same('requires-next-stage', $unproved155()['status']),
    'planner stat4 expression partial current source next155 invalid rowid' => static function (TestRunner $t) use ($currentSource155, $plan155): void {
        $bad = $currentSource155();
        $bad['rows'][0]['rowid'] = -1;
        $t->throws(InvalidArgumentException::class, static fn () => $plan155(null, $bad));
    },
    'planner stat4 expression partial current source next155 invalid row generation' => static function (TestRunner $t) use ($currentSource155, $plan155): void {
        $bad = $currentSource155();
        $bad['rowGeneration'] = -1;
        $t->throws(InvalidArgumentException::class, static fn () => $plan155(null, $bad));
    },
    'planner stat4 expression partial current source next155 invalid needed columns' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => $plan155(null, null, null, [])),
];

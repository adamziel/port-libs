<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteExpressionIndexPartialCollationStat4CurrentSourceNextPlan;

$point = static fn (string $column, mixed $value): array => ['operator' => '=', 'left' => ['column' => $column], 'right' => $value];
$range = static fn (string $expression, string $operator, mixed $value): array => ['operator' => $operator, 'left' => ['expression' => $expression], 'right' => $value];
$between = static fn (string $expression, mixed $lower, mixed $upper): array => ['operator' => 'BETWEEN', 'left' => ['expression' => $expression], 'lower' => $lower, 'upper' => $upper];
$and = static fn (array ...$terms): array => ['operator' => 'AND', 'terms' => $terms];

$source114 = static function (array $overrides = []): array {
    return $overrides + [
        'name' => 'prepared-partial-collation-stat4-next114',
        'schemaCookie' => 1140,
        'stat4Generation' => 41,
        'indexes' => [[
            'name' => 'idx_wp_options_lower_name_autoload_nocase_next114',
            'rootPage' => 11401,
            'stat4Samples' => [
                ['neq' => '1 1', 'nlt' => '0 0', 'ndlt' => '0 0', 'sample' => ['Plugin Alpha', 11]],
                ['neq' => '1 1', 'nlt' => '1 1', 'ndlt' => '1 1', 'sample' => ['plugin cache', 12]],
                ['neq' => '1 1', 'nlt' => '2 2', 'ndlt' => '2 2', 'sample' => ['Plugin Forms', 13]],
                ['neq' => '1 1', 'nlt' => '3 3', 'ndlt' => '3 3', 'sample' => ['plugin SEO', 14]],
            ],
            'sql' => "CREATE INDEX idx_wp_options_lower_name_autoload_nocase_next114 ON wp_options(lower(option_name) COLLATE NOCASE, option_id) WHERE autoload = 'yes'",
        ], [
            'name' => 'idx_wp_options_upper_name_binary_next114',
            'rootPage' => 11402,
            'stat4Samples' => [
                ['neq' => '1', 'nlt' => '0', 'ndlt' => '0', 'sample' => ['PLUGIN FORMS', 31]],
            ],
            'sql' => "CREATE INDEX idx_wp_options_upper_name_binary_next114 ON wp_options(upper(option_name) COLLATE BINARY) WHERE autoload = 'yes'",
        ]],
    ];
};

$current114 = static function () use ($source114): array {
    $data = $source114([
        'name' => 'current-partial-collation-stat4-next114',
        'schemaCookie' => 1144,
        'stat4Generation' => 44,
    ]);
    $data['indexes'][0]['rootPage'] = 11410;
    $data['indexes'][0]['stat4Samples'] = [
        ['neq' => '1 1', 'nlt' => '0 0', 'ndlt' => '0 0', 'sample' => ['Plugin Alpha', 21]],
        ['neq' => '1 1', 'nlt' => '1 1', 'ndlt' => '1 1', 'sample' => ['plugin cache', 22]],
        ['neq' => '1 1', 'nlt' => '2 2', 'ndlt' => '2 2', 'sample' => ['Plugin Forms', 23]],
        ['neq' => '1 1', 'nlt' => '3 3', 'ndlt' => '3 3', 'sample' => ['plugin Security', 24]],
        ['neq' => '1 1', 'nlt' => '4 4', 'ndlt' => '4 4', 'sample' => ['Plugin SEO', 25]],
        ['neq' => '1 1', 'nlt' => '5 5', 'ndlt' => '5 5', 'sample' => ['plugin Slider', 26]],
    ];

    return $data;
};

$predicate114 = $and(
    $point('autoload', 'yes'),
    $range('lower(option_name)', '>=', 'plugin cache'),
    $range('lower(option_name)', '<', 'plugin t'),
);
$order114 = [['expression' => 'lower(option_name)', 'direction' => 'ASC', 'collation' => 'NOCASE']];

$plan114 = static fn (
    ?array $prepared = null,
    ?array $current = null,
    ?array $predicate = null,
    ?array $order = null,
): array => SQLiteExpressionIndexPartialCollationStat4CurrentSourceNextPlan::materialize(
    $prepared ?? $source114(),
    $current ?? $current114(),
    $predicate ?? $GLOBALS['predicate_next114'],
    $order ?? $GLOBALS['order_next114'],
);

$GLOBALS['predicate_next114'] = $predicate114;
$GLOBALS['order_next114'] = $order114;

$tests = [
    'planner expression index partial collation stat4 next114 status ready' => static fn (TestRunner $t) => $t->same('expression-index-partial-collation-stat4-current-source-ready', $plan114()['status']),
    'planner expression index partial collation stat4 next114 selects current' => static fn (TestRunner $t) => $t->same('current', $plan114()['selectedSource']),
    'planner expression index partial collation stat4 next114 stale prepared' => static fn (TestRunner $t) => $t->same(true, $plan114()['stalePreparedStatement']),
    'planner expression index partial collation stat4 next114 reprepare required' => static fn (TestRunner $t) => $t->same(true, $plan114()['reprepareRequired']),
    'planner expression index partial collation stat4 next114 schema cookie changed' => static fn (TestRunner $t) => $t->same(true, $plan114()['schemaCookieChanged']),
    'planner expression index partial collation stat4 next114 stat4 changed' => static fn (TestRunner $t) => $t->same(true, $plan114()['stat4GenerationChanged']),
    'planner expression index partial collation stat4 next114 index signature changed' => static fn (TestRunner $t) => $t->same(true, $plan114()['indexSignatureChanged']),
    'planner expression index partial collation stat4 next114 selected index' => static fn (TestRunner $t) => $t->same('idx_wp_options_lower_name_autoload_nocase_next114', $plan114()['selectedPlan']['name']),
    'planner expression index partial collation stat4 next114 selected root page' => static fn (TestRunner $t) => $t->same(11410, $plan114()['selectedPlan']['rootPage']),
    'planner expression index partial collation stat4 next114 selected expression' => static fn (TestRunner $t) => $t->same('lower(option_name)', $plan114()['selectedPlan']['expression']),
    'planner expression index partial collation stat4 next114 expression column' => static fn (TestRunner $t) => $t->same('option_name', $plan114()['selectedPlan']['expressionColumn']),
    'planner expression index partial collation stat4 next114 collation nocase' => static fn (TestRunner $t) => $t->same('NOCASE', $plan114()['selectedPlan']['collation']),
    'planner expression index partial collation stat4 next114 partial true' => static fn (TestRunner $t) => $t->same(true, $plan114()['selectedPlan']['partial']),
    'planner expression index partial collation stat4 next114 partial implied' => static fn (TestRunner $t) => $t->same(true, $plan114()['selectedPlan']['partialPredicateImplied']),
    'planner expression index partial collation stat4 next114 order satisfied' => static fn (TestRunner $t) => $t->same(true, $plan114()['selectedPlan']['orderBySatisfied']),
    'planner expression index partial collation stat4 next114 stat4 used' => static fn (TestRunner $t) => $t->same(true, $plan114()['selectedPlan']['stat4Used']),
    'planner expression index partial collation stat4 next114 matched sample count' => static fn (TestRunner $t) => $t->same(5, $plan114()['selectedPlan']['stat4MatchedSamples']),
    'planner expression index partial collation stat4 next114 estimate rows' => static fn (TestRunner $t) => $t->same(5, $plan114()['selectedPlan']['estimatedRows']),
    'planner expression index partial collation stat4 next114 matched keys case-insensitive' => static fn (TestRunner $t) => $t->same(['plugin cache', 'Plugin Forms', 'plugin Security', 'Plugin SEO', 'plugin Slider'], $plan114()['selectedPlan']['matchedKeys']),
    'planner expression index partial collation stat4 next114 matched rowids' => static fn (TestRunner $t) => $t->same([22, 23, 24, 25, 26], $plan114()['selectedPlan']['matchedRowids']),
    'planner expression index partial collation stat4 next114 cursor source' => static fn (TestRunner $t) => $t->same('current', $plan114()['cursorTape']['source']),
    'planner expression index partial collation stat4 next114 cursor collation' => static fn (TestRunner $t) => $t->same('NOCASE', $plan114()['cursorTape']['collation']),
    'planner expression index partial collation stat4 next114 seek opcode' => static fn (TestRunner $t) => $t->same('SeekGE', $plan114()['cursorTape']['seekOpcode']),
    'planner expression index partial collation stat4 next114 stop opcode' => static fn (TestRunner $t) => $t->same('IdxGE', $plan114()['cursorTape']['stopOpcode']),
    'planner expression index partial collation stat4 next114 next opcode' => static fn (TestRunner $t) => $t->same('Next', $plan114()['cursorTape']['nextOpcode']),
    'planner expression index partial collation stat4 next114 scan ascending' => static fn (TestRunner $t) => $t->same('ascending', $plan114()['cursorTape']['scanDirection']),
    'planner expression index partial collation stat4 next114 lower bound' => static fn (TestRunner $t) => $t->same('plugin cache', $plan114()['cursorTape']['rangeLower']),
    'planner expression index partial collation stat4 next114 upper bound' => static fn (TestRunner $t) => $t->same('plugin t', $plan114()['cursorTape']['rangeUpper']),
    'planner expression index partial collation stat4 next114 lower exact' => static fn (TestRunner $t) => $t->same(true, $plan114()['cursorTape']['rangeLowerExact']),
    'planner expression index partial collation stat4 next114 upper exclusive' => static fn (TestRunner $t) => $t->same(false, $plan114()['cursorTape']['rangeUpperExact']),
    'planner expression index partial collation stat4 next114 current next count' => static fn (TestRunner $t) => $t->same(6, count($plan114()['cursorTape']['stat4CurrentNext'])),
    'planner expression index partial collation stat4 next114 matched current next count' => static fn (TestRunner $t) => $t->same(5, count($plan114()['cursorTape']['stat4MatchedCurrentNext'])),
    'planner expression index partial collation stat4 next114 first matched current' => static fn (TestRunner $t) => $t->same('plugin cache', $plan114()['cursorTape']['stat4MatchedCurrentNext'][0]['current']['key']),
    'planner expression index partial collation stat4 next114 first matched next' => static fn (TestRunner $t) => $t->same('Plugin Forms', $plan114()['cursorTape']['stat4MatchedCurrentNext'][0]['next']['key']),
    'planner expression index partial collation stat4 next114 last matched eof' => static fn (TestRunner $t) => $t->same(null, $plan114()['cursorTape']['stat4MatchedCurrentNext'][4]['next']),
    'planner expression index partial collation stat4 next114 lower boundary exact' => static fn (TestRunner $t) => $t->same(true, $plan114()['cursorTape']['stat4RangeCurrentNext']['lower']['exact']),
    'planner expression index partial collation stat4 next114 lower boundary next' => static fn (TestRunner $t) => $t->same('Plugin Forms', $plan114()['cursorTape']['stat4RangeCurrentNext']['lower']['next']['key']),
    'planner expression index partial collation stat4 next114 upper boundary gap' => static fn (TestRunner $t) => $t->same(false, $plan114()['cursorTape']['stat4RangeCurrentNext']['upper']['exact']),
    'planner expression index partial collation stat4 next114 upper boundary current' => static fn (TestRunner $t) => $t->same('plugin Slider', $plan114()['cursorTape']['stat4RangeCurrentNext']['upper']['current']['key']),
    'planner expression index partial collation stat4 next114 table lookup deferred' => static fn (TestRunner $t) => $t->same(true, $plan114()['tableLookupDeferred']),
    'planner expression index partial collation stat4 next114 sort elided' => static fn (TestRunner $t) => $t->same(true, $plan114()['tempSortElided']),
    'planner expression index partial collation stat4 next114 program opens index' => static fn (TestRunner $t) => $t->same('OpenRead', $plan114()['cursorTape']['program'][0]['opcode']),
    'planner expression index partial collation stat4 next114 program seek collation' => static fn (TestRunner $t) => $t->same('NOCASE', $plan114()['cursorTape']['program'][1]['collation']),
    'planner expression index partial collation stat4 next114 program reads expression column' => static fn (TestRunner $t) => $t->same('option_name', $plan114()['cursorTape']['program'][3]['column']),
    'planner expression index partial collation stat4 next114 fence cookie' => static fn (TestRunner $t) => $t->same(1144, $plan114()['currentSourceFence']['schemaCookie']),
    'planner expression index partial collation stat4 next114 fence stat4' => static fn (TestRunner $t) => $t->same(44, $plan114()['currentSourceFence']['stat4Generation']),
    'planner expression index partial collation stat4 next114 fence order signature' => static fn (TestRunner $t) => $t->same('lower(option_name) ASC COLLATE NOCASE', $plan114()['currentSourceFence']['orderSignature']),
    'planner expression index partial collation stat4 next114 detail reprepare' => static fn (TestRunner $t) => $t->contains('REPREPARE PARTIAL COLLATION STAT4 EXPRESSION INDEX', $plan114()['detail']),
    'planner expression index partial collation stat4 next114 dependency closure' => static fn (TestRunner $t) => $t->contains('no new support component needed', $plan114()['dependency_closure']),
    'planner expression index partial collation stat4 next114 non overlap' => static fn (TestRunner $t) => $t->contains('partial expression-index STAT4 boundaries', $plan114()['non_overlap']),
];

$samePlan = static fn (): array => $plan114($source114(), $source114(['name' => 'current-same-partial-collation-stat4-next114']));
$tests['planner expression index partial collation stat4 next114 reuses prepared when fresh'] = static fn (TestRunner $t) => $t->same('prepared', $samePlan()['selectedSource']);
$tests['planner expression index partial collation stat4 next114 no reprepare when fresh'] = static fn (TestRunner $t) => $t->same(false, $samePlan()['reprepareRequired']);
$tests['planner expression index partial collation stat4 next114 fresh prepared rowids'] = static fn (TestRunner $t) => $t->same([12, 13, 14], $samePlan()['selectedPlan']['matchedRowids']);

$betweenPlan = static fn (): array => $plan114(null, null, $and($point('autoload', 'yes'), $between('lower(option_name)', 'PLUGIN CACHE', 'PLUGIN SECURITY')));
$tests['planner expression index partial collation stat4 next114 between inclusive stop' ] = static fn (TestRunner $t) => $t->same('IdxGT', $betweenPlan()['cursorTape']['stopOpcode']);
$tests['planner expression index partial collation stat4 next114 between nocase matches uppercase probes'] = static fn (TestRunner $t) => $t->same(['plugin cache', 'Plugin Forms', 'plugin Security'], $betweenPlan()['selectedPlan']['matchedKeys']);
$tests['planner expression index partial collation stat4 next114 between upper exact'] = static fn (TestRunner $t) => $t->same(true, $betweenPlan()['cursorTape']['stat4RangeCurrentNext']['upper']['exact']);

$openPlan = static fn (): array => $plan114(null, null, $and($point('autoload', 'yes'), $range('lower(option_name)', '>', 'plugin cache'), $range('lower(option_name)', '<', 'plugin seo')));
$tests['planner expression index partial collation stat4 next114 open lower seek gt'] = static fn (TestRunner $t) => $t->same('SeekGT', $openPlan()['cursorTape']['seekOpcode']);
$tests['planner expression index partial collation stat4 next114 open lower excludes boundary'] = static fn (TestRunner $t) => $t->same(['Plugin Forms', 'plugin Security'], $openPlan()['selectedPlan']['matchedKeys']);

$wrongOrder = static fn (): array => $plan114(null, null, null, [['expression' => 'lower(option_name)', 'direction' => 'ASC', 'collation' => 'BINARY']]);
$tests['planner expression index partial collation stat4 next114 wrong collation requires next stage'] = static fn (TestRunner $t) => $t->same('requires-next-stage', $wrongOrder()['status']);
$tests['planner expression index partial collation stat4 next114 wrong collation opens sorter'] = static fn (TestRunner $t) => $t->same('SorterOpen', $wrongOrder()['cursorTape']['program'][5]['opcode']);

$missingPartial = static fn (): array => $plan114(null, null, $and($range('lower(option_name)', '>=', 'plugin cache'), $range('lower(option_name)', '<', 'plugin t')));
$tests['planner expression index partial collation stat4 next114 missing partial unusable'] = static fn (TestRunner $t) => $t->same('requires-next-stage', $missingPartial()['status']);
$tests['planner expression index partial collation stat4 next114 missing partial no index'] = static fn (TestRunner $t) => $t->same(null, $missingPartial()['cursorTape']['indexName']);

$tests['planner expression index partial collation stat4 next114 validates schema cookie'] = static function (TestRunner $t) use ($source114, $current114): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteExpressionIndexPartialCollationStat4CurrentSourceNextPlan::materialize($source114(['schemaCookie' => -1]), $current114(), $GLOBALS['predicate_next114'], $GLOBALS['order_next114']));
};
$tests['planner expression index partial collation stat4 next114 validates order direction'] = static function (TestRunner $t) use ($source114, $current114): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteExpressionIndexPartialCollationStat4CurrentSourceNextPlan::materialize($source114(), $current114(), $GLOBALS['predicate_next114'], [['expression' => 'lower(option_name)', 'direction' => 'SIDEWAYS']]));
};
$tests['planner expression index partial collation stat4 next114 validates stat4 samples'] = static function (TestRunner $t) use ($source114, $current114): void {
    $bad = $current114();
    $bad['indexes'][0]['stat4Samples'][0]['sample'] = [];
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteExpressionIndexPartialCollationStat4CurrentSourceNextPlan::materialize($source114(), $bad, $GLOBALS['predicate_next114'], $GLOBALS['order_next114']));
};

return $tests;

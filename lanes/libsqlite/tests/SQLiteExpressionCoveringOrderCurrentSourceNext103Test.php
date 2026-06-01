<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteExpressionCoveringOrderCurrentSourceNextPlan;

$expr = static fn (string $sql): array => ['expression' => $sql];
$point = static fn (string $column, mixed $value): array => ['operator' => '=', 'left' => ['column' => $column], 'right' => $value];
$range = static fn (string $sql, string $operator, mixed $value): array => ['operator' => $operator, 'left' => $expr($sql), 'right' => $value];
$between = static fn (string $sql, mixed $lower, mixed $upper): array => ['operator' => 'BETWEEN', 'left' => $expr($sql), 'lower' => $lower, 'upper' => $upper];
$and = static fn (array ...$terms): array => ['operator' => 'AND', 'terms' => $terms];

$source103 = static function (array $overrides = []): array {
    return $overrides + [
        'name' => 'prepared-expression-covering-order-next103',
        'schemaCookie' => 101,
        'stat4Generation' => 31,
        'indexes' => [[
            'name' => 'idx_wp_options_lower_name_autoload_value_next103',
            'rootPage' => 10301,
            'estimatedRows' => 120,
            'stat4Samples' => [
                ['neq' => '1 2 2', 'nlt' => '0 0 0', 'ndlt' => '0 0 0', 'sample' => ['plugin alpha', 'yes', 'a:1:{}']],
                ['neq' => '1 4 4', 'nlt' => '2 2 2', 'ndlt' => '1 1 1', 'sample' => ['plugin forms', 'yes', 'a:2:{}']],
                ['neq' => '1 6 6', 'nlt' => '6 6 6', 'ndlt' => '2 2 2', 'sample' => ['plugin seo', 'yes', 'a:3:{}']],
            ],
            'sql' => "CREATE INDEX idx_wp_options_lower_name_autoload_value_next103 ON wp_options(lower(option_name), autoload, option_value) WHERE autoload = 'yes'",
        ], [
            'name' => 'idx_wp_options_lower_name_only_next103',
            'rootPage' => 10302,
            'estimatedRows' => 80,
            'stat4Samples' => [
                ['neq' => '1', 'nlt' => '0', 'ndlt' => '0', 'sample' => ['plugin forms']],
            ],
            'sql' => "CREATE INDEX idx_wp_options_lower_name_only_next103 ON wp_options(lower(option_name)) WHERE autoload = 'yes'",
        ]],
    ];
};

$current103 = static function () use ($source103): array {
    $data = $source103([
        'name' => 'current-expression-covering-order-next103',
        'schemaCookie' => 103,
        'stat4Generation' => 33,
    ]);
    $data['indexes'][0]['rootPage'] = 10310;
    $data['indexes'][0]['stat4Samples'] = [
        ['neq' => '1 2 2', 'nlt' => '0 0 0', 'ndlt' => '0 0 0', 'sample' => ['plugin alpha', 'yes', 'a:1:{}']],
        ['neq' => '1 3 3', 'nlt' => '2 2 2', 'ndlt' => '1 1 1', 'sample' => ['plugin cache', 'yes', 'a:2:{}']],
        ['neq' => '1 5 5', 'nlt' => '5 5 5', 'ndlt' => '2 2 2', 'sample' => ['plugin forms', 'yes', 'a:3:{}']],
        ['neq' => '1 4 4', 'nlt' => '10 10 10', 'ndlt' => '3 3 3', 'sample' => ['plugin security', 'yes', 'a:4:{}']],
        ['neq' => '1 2 2', 'nlt' => '14 14 14', 'ndlt' => '4 4 4', 'sample' => ['plugin slider', 'yes', 'a:5:{}']],
    ];

    return $data;
};

$predicate103 = $and(
    $point('autoload', 'yes'),
    $range('lower(option_name)', '>=', 'plugin '),
    $range('lower(option_name)', '<', 'plugin z'),
);
$order103 = [['expression' => 'lower(option_name)', 'direction' => 'ASC']];
$needed103 = ['option_name', 'autoload', 'option_value'];

$plan103 = static fn (
    ?array $prepared = null,
    ?array $current = null,
    ?array $predicate = null,
    ?array $order = null,
    ?array $needed = null,
): array => SQLiteExpressionCoveringOrderCurrentSourceNextPlan::materialize(
    $prepared ?? $source103(),
    $current ?? $current103(),
    $predicate ?? $GLOBALS['predicate_next103'],
    $order ?? $GLOBALS['order_next103'],
    $needed ?? $GLOBALS['needed_next103'],
);

$GLOBALS['predicate_next103'] = $predicate103;
$GLOBALS['order_next103'] = $order103;
$GLOBALS['needed_next103'] = $needed103;

$tests = [
    'planner expression covering order current source next103 status ready' => static fn (TestRunner $t) => $t->same('expression-covering-order-current-source-ready', $plan103()['status']),
    'planner expression covering order current source next103 selects current' => static fn (TestRunner $t) => $t->same('current', $plan103()['selectedSource']),
    'planner expression covering order current source next103 stale prepared' => static fn (TestRunner $t) => $t->same(true, $plan103()['stalePreparedStatement']),
    'planner expression covering order current source next103 requires reprepare' => static fn (TestRunner $t) => $t->same(true, $plan103()['reprepareRequired']),
    'planner expression covering order current source next103 schema cookie changed' => static fn (TestRunner $t) => $t->same(true, $plan103()['schemaCookieChanged']),
    'planner expression covering order current source next103 stat4 changed' => static fn (TestRunner $t) => $t->same(true, $plan103()['stat4GenerationChanged']),
    'planner expression covering order current source next103 index signature changed' => static fn (TestRunner $t) => $t->same(true, $plan103()['indexSignatureChanged']),
    'planner expression covering order current source next103 selected index' => static fn (TestRunner $t) => $t->same('idx_wp_options_lower_name_autoload_value_next103', $plan103()['selectedPlan']['name']),
    'planner expression covering order current source next103 selected root page' => static fn (TestRunner $t) => $t->same(10310, $plan103()['selectedPlan']['rootPage']),
    'planner expression covering order current source next103 prepared root page' => static fn (TestRunner $t) => $t->same(10301, $plan103()['preparedSource']['rootPage']),
    'planner expression covering order current source next103 current root page' => static fn (TestRunner $t) => $t->same(10310, $plan103()['currentSource']['rootPage']),
    'planner expression covering order current source next103 expression' => static fn (TestRunner $t) => $t->same('lower(option_name)', $plan103()['selectedPlan']['expression']),
    'planner expression covering order current source next103 expression opcode' => static fn (TestRunner $t) => $t->same('Function0 lower(1)', $plan103()['selectedPlan']['expressionOpcode']),
    'planner expression covering order current source next103 expression column' => static fn (TestRunner $t) => $t->same('option_name', $plan103()['selectedPlan']['expressionColumn']),
    'planner expression covering order current source next103 covering true' => static fn (TestRunner $t) => $t->same(true, $plan103()['selectedPlan']['covering']),
    'planner expression covering order current source next103 order satisfied' => static fn (TestRunner $t) => $t->same(true, $plan103()['selectedPlan']['orderBySatisfied']),
    'planner expression covering order current source next103 range usable' => static fn (TestRunner $t) => $t->same(true, $plan103()['selectedPlan']['rangeUsable']),
    'planner expression covering order current source next103 partial implied' => static fn (TestRunner $t) => $t->same(true, $plan103()['selectedPlan']['partialPredicateImplied']),
    'planner expression covering order current source next103 table lookup elided' => static fn (TestRunner $t) => $t->same(true, $plan103()['tableLookupElided']),
    'planner expression covering order current source next103 temp sort elided' => static fn (TestRunner $t) => $t->same(true, $plan103()['tempSortElided']),
    'planner expression covering order current source next103 cursor source' => static fn (TestRunner $t) => $t->same('current', $plan103()['cursorTape']['source']),
    'planner expression covering order current source next103 cursor index' => static fn (TestRunner $t) => $t->same('idx_wp_options_lower_name_autoload_value_next103', $plan103()['cursorTape']['indexName']),
    'planner expression covering order current source next103 cursor root' => static fn (TestRunner $t) => $t->same(10310, $plan103()['cursorTape']['rootPage']),
    'planner expression covering order current source next103 cursor expression' => static fn (TestRunner $t) => $t->same('lower(option_name)', $plan103()['cursorTape']['expression']),
    'planner expression covering order current source next103 seek ge' => static fn (TestRunner $t) => $t->same('SeekGE', $plan103()['cursorTape']['seekOpcode']),
    'planner expression covering order current source next103 stop ge exclusive' => static fn (TestRunner $t) => $t->same('IdxGE', $plan103()['cursorTape']['stopOpcode']),
    'planner expression covering order current source next103 next opcode' => static fn (TestRunner $t) => $t->same('Next', $plan103()['cursorTape']['nextOpcode']),
    'planner expression covering order current source next103 scan ascending' => static fn (TestRunner $t) => $t->same('ascending', $plan103()['cursorTape']['scanDirection']),
    'planner expression covering order current source next103 range lower' => static fn (TestRunner $t) => $t->same('plugin ', $plan103()['cursorTape']['rangeLower']),
    'planner expression covering order current source next103 range upper' => static fn (TestRunner $t) => $t->same('plugin z', $plan103()['cursorTape']['rangeUpper']),
    'planner expression covering order current source next103 lower exact' => static fn (TestRunner $t) => $t->same(true, $plan103()['cursorTape']['rangeLowerExact']),
    'planner expression covering order current source next103 upper exclusive' => static fn (TestRunner $t) => $t->same(false, $plan103()['cursorTape']['rangeUpperExact']),
    'planner expression covering order current source next103 segment count' => static fn (TestRunner $t) => $t->same(5, $plan103()['cursorTape']['currentNextCount']),
    'planner expression covering order current source next103 matched expression keys' => static fn (TestRunner $t) => $t->same(['plugin alpha', 'plugin cache', 'plugin forms', 'plugin security', 'plugin slider'], $plan103()['cursorTape']['matchedExpressionKeys']),
    'planner expression covering order current source next103 first segment key' => static fn (TestRunner $t) => $t->same('plugin alpha', $plan103()['cursorTape']['currentNextSegments'][0]['currentKey']),
    'planner expression covering order current source next103 first segment next' => static fn (TestRunner $t) => $t->same('plugin cache', $plan103()['cursorTape']['currentNextSegments'][0]['nextKey']),
    'planner expression covering order current source next103 first segment neq' => static fn (TestRunner $t) => $t->same(1, $plan103()['cursorTape']['currentNextSegments'][0]['neq']),
    'planner expression covering order current source next103 middle nlt' => static fn (TestRunner $t) => $t->same(5, $plan103()['cursorTape']['currentNextSegments'][2]['nlt']),
    'planner expression covering order current source next103 last segment eof' => static fn (TestRunner $t) => $t->same('eof', $plan103()['cursorTape']['currentNextSegments'][4]['advance']),
    'planner expression covering order current source next103 last segment next null' => static fn (TestRunner $t) => $t->same(null, $plan103()['cursorTape']['currentNextSegments'][4]['nextKey']),
    'planner expression covering order current source next103 output column count' => static fn (TestRunner $t) => $t->same(3, count($plan103()['cursorTape']['outputColumns'])),
    'planner expression covering order current source next103 output column one index' => static fn (TestRunner $t) => $t->same(['column' => 'option_name', 'opcode' => 'Column', 'source' => 'index'], $plan103()['cursorTape']['outputColumns'][0]),
    'planner expression covering order current source next103 deferred seek absent' => static fn (TestRunner $t) => $t->same(null, $plan103()['cursorTape']['deferredSeekOpcode']),
    'planner expression covering order current source next103 sorter closed' => static fn (TestRunner $t) => $t->same(false, $plan103()['cursorTape']['sorterOpen']),
    'planner expression covering order current source next103 program opens index' => static fn (TestRunner $t) => $t->same('OpenRead', $plan103()['cursorTape']['program'][0]['opcode']),
    'planner expression covering order current source next103 program computes expression' => static fn (TestRunner $t) => $t->same(['opcode' => 'Function0', 'function' => 'lower', 'column' => 'option_name'], $plan103()['cursorTape']['program'][1]),
    'planner expression covering order current source next103 program seeks expression' => static fn (TestRunner $t) => $t->same('SeekGE', $plan103()['cursorTape']['program'][2]['opcode']),
    'planner expression covering order current source next103 program stops expression' => static fn (TestRunner $t) => $t->same('IdxGE', $plan103()['cursorTape']['program'][3]['opcode']),
    'planner expression covering order current source next103 program reads index column' => static fn (TestRunner $t) => $t->same(['opcode' => 'Column', 'source' => 'index', 'column' => 'option_name'], $plan103()['cursorTape']['program'][4]),
    'planner expression covering order current source next103 program advances' => static fn (TestRunner $t) => $t->same('Next', $plan103()['cursorTape']['program'][7]['opcode']),
    'planner expression covering order current source next103 no deferred seek opcode in program' => static fn (TestRunner $t) => $t->same(false, in_array('DeferredSeek', array_column($plan103()['cursorTape']['program'], 'opcode'), true)),
    'planner expression covering order current source next103 no sorter opcode in program' => static fn (TestRunner $t) => $t->same(false, in_array('SorterOpen', array_column($plan103()['cursorTape']['program'], 'opcode'), true)),
    'planner expression covering order current source next103 fence cookie' => static fn (TestRunner $t) => $t->same(103, $plan103()['currentSourceFence']['schemaCookie']),
    'planner expression covering order current source next103 fence stat4' => static fn (TestRunner $t) => $t->same(33, $plan103()['currentSourceFence']['stat4Generation']),
    'planner expression covering order current source next103 fence order signature' => static fn (TestRunner $t) => $t->same('lower(option_name) ASC', $plan103()['currentSourceFence']['orderSignature']),
    'planner expression covering order current source next103 detail reparses' => static fn (TestRunner $t) => $t->contains('REPREPARE EXPRESSION COVERING ORDER USING current-expression-covering-order-next103', $plan103()['detail']),
    'planner expression covering order current source next103 dependency closure' => static fn (TestRunner $t) => $t->contains('no new support component needed', $plan103()['dependency_closure']),
    'planner expression covering order current source next103 non overlap' => static fn (TestRunner $t) => $t->contains('generic key expression-index', $plan103()['non_overlap']),
];

$samePlan = static fn (): array => $plan103($source103(), $source103(['name' => 'current-same-expression-covering-order-next103']));
$tests['planner expression covering order current source next103 reuses fresh prepared'] = static fn (TestRunner $t) => $t->same('prepared', $samePlan()['selectedSource']);
$tests['planner expression covering order current source next103 no reprepare when signatures match'] = static fn (TestRunner $t) => $t->same(false, $samePlan()['reprepareRequired']);
$tests['planner expression covering order current source next103 fresh sample count'] = static fn (TestRunner $t) => $t->same(3, $samePlan()['cursorTape']['currentNextCount']);

$betweenPlan = static fn (): array => $plan103(null, null, $and($point('autoload', 'yes'), $between('lower(option_name)', 'plugin cache', 'plugin security')));
$tests['planner expression covering order current source next103 between stop inclusive' ] = static fn (TestRunner $t) => $t->same('IdxGT', $betweenPlan()['cursorTape']['stopOpcode']);
$tests['planner expression covering order current source next103 between matched keys'] = static fn (TestRunner $t) => $t->same(['plugin cache', 'plugin forms', 'plugin security'], $betweenPlan()['cursorTape']['matchedExpressionKeys']);
$tests['planner expression covering order current source next103 between upper exact'] = static fn (TestRunner $t) => $t->same(true, $betweenPlan()['cursorTape']['rangeUpperExact']);

$openLowerPlan = static fn (): array => $plan103(null, null, $and($point('autoload', 'yes'), $range('lower(option_name)', '>', 'plugin cache'), $range('lower(option_name)', '<', 'plugin security')));
$tests['planner expression covering order current source next103 open lower seek gt'] = static fn (TestRunner $t) => $t->same('SeekGT', $openLowerPlan()['cursorTape']['seekOpcode']);
$tests['planner expression covering order current source next103 open lower excludes boundary'] = static fn (TestRunner $t) => $t->same(['plugin forms'], $openLowerPlan()['cursorTape']['matchedExpressionKeys']);

$missingColumnPlan = static fn (): array => $plan103(null, null, null, null, ['option_name', 'missing_meta']);
$tests['planner expression covering order current source next103 missing column requires next stage'] = static fn (TestRunner $t) => $t->same('requires-next-stage', $missingColumnPlan()['status']);
$tests['planner expression covering order current source next103 missing column deferred seek'] = static fn (TestRunner $t) => $t->same('DeferredSeek', $missingColumnPlan()['cursorTape']['deferredSeekOpcode']);
$tests['planner expression covering order current source next103 missing column table source'] = static fn (TestRunner $t) => $t->same('table', $missingColumnPlan()['cursorTape']['program'][6]['source']);

$descPlan = static fn (): array => $plan103(null, null, null, [['expression' => 'lower(option_name)', 'direction' => 'DESC']]);
$tests['planner expression covering order current source next103 desc requires next stage'] = static fn (TestRunner $t) => $t->same('requires-next-stage', $descPlan()['status']);
$tests['planner expression covering order current source next103 desc scan direction'] = static fn (TestRunner $t) => $t->same('descending', $descPlan()['cursorTape']['scanDirection']);
$tests['planner expression covering order current source next103 desc sorter opens'] = static fn (TestRunner $t) => $t->same(true, $descPlan()['cursorTape']['sorterOpen']);
$tests['planner expression covering order current source next103 desc prev opcode'] = static fn (TestRunner $t) => $t->same('Prev', $descPlan()['cursorTape']['nextOpcode']);

$tests['planner expression covering order current source next103 validates schema cookie'] = static function (TestRunner $t) use ($source103, $current103): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteExpressionCoveringOrderCurrentSourceNextPlan::materialize($source103(['schemaCookie' => -1]), $current103(), $GLOBALS['predicate_next103'], $GLOBALS['order_next103'], $GLOBALS['needed_next103']));
};
$tests['planner expression covering order current source next103 validates order direction'] = static function (TestRunner $t) use ($source103, $current103): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteExpressionCoveringOrderCurrentSourceNextPlan::materialize($source103(), $current103(), $GLOBALS['predicate_next103'], [['expression' => 'lower(option_name)', 'direction' => 'SIDEWAYS']], $GLOBALS['needed_next103']));
};
$tests['planner expression covering order current source next103 validates output columns'] = static function (TestRunner $t) use ($source103, $current103): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteExpressionCoveringOrderCurrentSourceNextPlan::materialize($source103(), $current103(), $GLOBALS['predicate_next103'], $GLOBALS['order_next103'], ['']));
};

return $tests;

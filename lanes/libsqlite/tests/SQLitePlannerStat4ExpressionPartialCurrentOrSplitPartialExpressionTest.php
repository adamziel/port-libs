<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan;

$eq161 = static fn (string $column, mixed $right): array => ['left' => ['column' => $column], 'operator' => '=', 'right' => $right];
$notNull161 = static fn (string $column): array => ['left' => ['column' => $column], 'operator' => 'IS NOT NULL'];
$exprEq161 = static fn (string $expression, mixed $right): array => ['left' => ['expression' => $expression], 'operator' => '=', 'right' => $right];
$exprIn161 = static fn (string $expression, array $values): array => ['left' => ['expression' => $expression], 'operator' => 'IN', 'values' => $values];

$prepared161 = static function (array $overrides = []): array {
    return array_replace_recursive([
        'name' => 'prepared-wp-options-stat4-expression-partial-or-split-partial-expression',
        'schemaCookie' => 1610,
        'stat4Generation' => 44,
        'rows' => [
            ['rowid' => 12, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_cache', 'option_value' => 'old-cache', 'updated_at' => 10],
            ['rowid' => 24, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_forms', 'option_value' => 'forms', 'updated_at' => 20],
            ['rowid' => 36, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_seo', 'option_value' => 'seo', 'updated_at' => 30],
        ],
        'indexes' => [[
            'name' => 'idx_wp_options_lower_or_partial_stat4_or-split-partial-expression',
            'rootPage' => 16101,
            'expression' => 'lower(option_name)',
            'expressionColumn' => '__expr_lower_option_name',
            'collation' => 'BINARY',
            'partialPredicateTerms' => [
                ['left' => ['column' => 'blog_id'], 'operator' => '=', 'right' => 1],
                ['left' => ['column' => 'autoload'], 'operator' => '=', 'right' => 'yes'],
                ['left' => ['column' => 'option_name'], 'operator' => 'IS NOT NULL'],
            ],
            'coveringColumns' => ['option_name', 'option_value', 'updated_at', 'blog_id', 'autoload'],
            'stat4Samples' => [
                ['neq' => '1 1', 'nlt' => '0 0', 'ndlt' => '0 0', 'sample' => ['plugin_cache', 12]],
                ['neq' => '1 1', 'nlt' => '1 1', 'ndlt' => '1 1', 'sample' => ['plugin_forms', 24]],
                ['neq' => '1 1', 'nlt' => '2 2', 'ndlt' => '2 2', 'sample' => ['plugin_seo', 36]],
            ],
        ]],
    ], $overrides);
};

$current161 = static function (array $overrides = []) use ($prepared161): array {
    $source = $prepared161([
        'name' => 'current-wp-options-stat4-expression-partial-or-split-partial-expression',
        'schemaCookie' => 1618,
        'stat4Generation' => 55,
    ]);
    $source['indexes'][0]['rootPage'] = 16188;
    $source['indexes'][0]['stat4Samples'] = [
        ['neq' => '2 1', 'nlt' => '0 0', 'ndlt' => '0 0', 'sample' => ['plugin_cache', 12]],
        ['neq' => '1 1', 'nlt' => '2 1', 'ndlt' => '1 1', 'sample' => ['plugin_forms', 24]],
        ['neq' => '1 1', 'nlt' => '3 2', 'ndlt' => '2 2', 'sample' => ['plugin_mail', 48]],
        ['neq' => '1 1', 'nlt' => '4 3', 'ndlt' => '3 3', 'sample' => ['plugin_seo', 36]],
        ['neq' => '1 1', 'nlt' => '5 4', 'ndlt' => '4 4', 'sample' => ['theme_mods', 60]],
    ];
    $source['rows'] = [
        ['rowid' => 48, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'Plugin_Mail', 'option_value' => 'mail', 'updated_at' => 40],
        ['rowid' => 12, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_cache', 'option_value' => 'new-cache', 'updated_at' => 15],
        ['rowid' => 24, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_forms', 'option_value' => 'forms', 'updated_at' => 20],
        ['rowid' => 36, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_seo', 'option_value' => 'seo', 'updated_at' => 30],
        ['rowid' => 72, 'blog_id' => 1, 'autoload' => 'no', 'option_name' => 'plugin_cache', 'option_value' => 'lazy', 'updated_at' => 70],
        ['rowid' => 84, 'blog_id' => 2, 'autoload' => 'yes', 'option_name' => 'plugin_seo', 'option_value' => 'network', 'updated_at' => 80],
        ['rowid' => 96, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => null, 'option_value' => 'null-name', 'updated_at' => 90],
    ];

    return array_replace_recursive($source, $overrides);
};

$arms161 = static fn (): array => [[
    $exprEq161('LOWER( option_name )', 'plugin_cache'),
    $eq161('blog_id', 1),
    $eq161('autoload', 'yes'),
    $notNull161('option_name'),
], [
    $exprIn161('lower(option_name)', ['plugin_forms', 'plugin_mail', 'plugin_seo']),
    $eq161('blog_id', 1),
    $eq161('autoload', 'yes'),
    $notNull161('option_name'),
]];
$needed161 = ['option_name', 'option_value', 'updated_at'];
$plan161 = static fn (?array $prepared = null, ?array $current = null, ?array $arms = null, ?array $needed = null): array => SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan::materializeCurrentOrSplitPartialExpression(
    $prepared ?? $prepared161(),
    $current ?? $current161(),
    $arms ?? $arms161(),
    $needed ?? $needed161,
);
$fresh161 = static function () use ($prepared161, $plan161): array {
    $source = $prepared161();

    return $plan161($source, $source);
};
$unproved161 = static function () use ($arms161, $plan161): array {
    $arms = $arms161();
    $arms[1] = [$arms[1][0], $arms[1][1], $arms[1][3]];

    return $plan161(null, null, $arms);
};
$nostat4161 = static function () use ($current161, $plan161): array {
    $current = $current161();
    $current['indexes'][0]['stat4Samples'] = [];

    return $plan161(null, $current);
};
$noncovering161 = static function () use ($current161, $plan161): array {
    $current = $current161();
    $current['indexes'][0]['coveringColumns'] = ['option_name'];

    return $plan161(null, $current);
};

return [
    'planner stat4 expression partial current source or-split-partial-expression status ready' => static fn (TestRunner $t) => $t->same('stat4-expression-partial-current-or-split-partial-expression-ready', $plan161()['status']),
    'planner stat4 expression partial current source or-split-partial-expression selects current' => static fn (TestRunner $t) => $t->same('current', $plan161()['selectedSource']),
    'planner stat4 expression partial current source or-split-partial-expression stale prepared' => static fn (TestRunner $t) => $t->same(true, $plan161()['stalePreparedStatement']),
    'planner stat4 expression partial current source or-split-partial-expression reprepare required' => static fn (TestRunner $t) => $t->same(true, $plan161()['reprepareRequired']),
    'planner stat4 expression partial current source or-split-partial-expression schema changed' => static fn (TestRunner $t) => $t->same(true, $plan161()['schemaCookieChanged']),
    'planner stat4 expression partial current source or-split-partial-expression stat4 changed' => static fn (TestRunner $t) => $t->same(true, $plan161()['stat4GenerationChanged']),
    'planner stat4 expression partial current source or-split-partial-expression signature changed' => static fn (TestRunner $t) => $t->same(true, $plan161()['sourceSignatureChanged']),
    'planner stat4 expression partial current source or-split-partial-expression selected index' => static fn (TestRunner $t) => $t->same('idx_wp_options_lower_or_partial_stat4_or-split-partial-expression', $plan161()['selectedPlan']['name']),
    'planner stat4 expression partial current source or-split-partial-expression root page' => static fn (TestRunner $t) => $t->same(16188, $plan161()['selectedPlan']['rootPage']),
    'planner stat4 expression partial current source or-split-partial-expression expression' => static fn (TestRunner $t) => $t->same('lower(option_name)', $plan161()['selectedPlan']['expression']),
    'planner stat4 expression partial current source or-split-partial-expression covering' => static fn (TestRunner $t) => $t->same(true, $plan161()['selectedPlan']['covering']),
    'planner stat4 expression partial current source or-split-partial-expression table lookup elided' => static fn (TestRunner $t) => $t->same(false, $plan161()['tableLookupRequired']),
    'planner stat4 expression partial current source or-split-partial-expression stat4 used' => static fn (TestRunner $t) => $t->same(true, $plan161()['selectedPlan']['stat4Used']),
    'planner stat4 expression partial current source or-split-partial-expression arm count' => static fn (TestRunner $t) => $t->same(2, $plan161()['selectedPlan']['orArmCount']),
    'planner stat4 expression partial current source or-split-partial-expression all partial implied' => static fn (TestRunner $t) => $t->same(true, $plan161()['selectedPlan']['allArmsPartialImplied']),
    'planner stat4 expression partial current source or-split-partial-expression all stat4 constrained' => static fn (TestRunner $t) => $t->same(true, $plan161()['selectedPlan']['allArmsConstrainedByStat4']),
    'planner stat4 expression partial current source or-split-partial-expression first arm keys' => static fn (TestRunner $t) => $t->same(['plugin_cache'], $plan161()['orArmPlans'][0]['matchedStat4Keys']),
    'planner stat4 expression partial current source or-split-partial-expression second arm keys' => static fn (TestRunner $t) => $t->same(['plugin_forms', 'plugin_mail', 'plugin_seo'], $plan161()['orArmPlans'][1]['matchedStat4Keys']),
    'planner stat4 expression partial current source or-split-partial-expression first arm estimate' => static fn (TestRunner $t) => $t->same(2, $plan161()['orArmPlans'][0]['stat4Estimate']),
    'planner stat4 expression partial current source or-split-partial-expression second arm estimate' => static fn (TestRunner $t) => $t->same(3, $plan161()['orArmPlans'][1]['stat4Estimate']),
    'planner stat4 expression partial current source or-split-partial-expression estimated rows' => static fn (TestRunner $t) => $t->same(5, $plan161()['selectedPlan']['estimatedRows']),
    'planner stat4 expression partial current source or-split-partial-expression estimated cost' => static fn (TestRunner $t) => $t->same(9, $plan161()['selectedPlan']['estimatedCost']),
    'planner stat4 expression partial current source or-split-partial-expression row count' => static fn (TestRunner $t) => $t->same(4, $plan161()['selectedPlan']['matchedRowCount']),
    'planner stat4 expression partial current source or-split-partial-expression rowids' => static fn (TestRunner $t) => $t->same([12, 24, 48, 36], $plan161()['matchedRowids']),
    'planner stat4 expression partial current source or-split-partial-expression keys' => static fn (TestRunner $t) => $t->same(['plugin_cache', 'plugin_forms', 'plugin_mail', 'plugin_seo'], $plan161()['matchedExpressionKeys']),
    'planner stat4 expression partial current source or-split-partial-expression current payload wins' => static fn (TestRunner $t) => $t->same('new-cache', $plan161()['matchedRows'][0]['payload']['option_value']),
    'planner stat4 expression partial current source or-split-partial-expression mixed case key normalized' => static fn (TestRunner $t) => $t->same('plugin_mail', $plan161()['matchedRows'][2]['expressionKey']),
    'planner stat4 expression partial current source or-split-partial-expression excludes autoload no' => static fn (TestRunner $t) => $t->same(false, in_array(72, $plan161()['matchedRowids'], true)),
    'planner stat4 expression partial current source or-split-partial-expression excludes blog two' => static fn (TestRunner $t) => $t->same(false, in_array(84, $plan161()['matchedRowids'], true)),
    'planner stat4 expression partial current source or-split-partial-expression excludes null name' => static fn (TestRunner $t) => $t->same(false, in_array(96, $plan161()['matchedRowids'], true)),
    'planner stat4 expression partial current source or-split-partial-expression current next first' => static fn (TestRunner $t) => $t->same(24, $plan161()['currentNextRows'][0]['next']['rowid']),
    'planner stat4 expression partial current source or-split-partial-expression current next eof' => static fn (TestRunner $t) => $t->same(null, $plan161()['currentNextRows'][3]['next']),
    'planner stat4 expression partial current source or-split-partial-expression no temp union btree' => static fn (TestRunner $t) => $t->same(false, $plan161()['tempUnionBtreeRequired']),
    'planner stat4 expression partial current source or-split-partial-expression selected ready flag' => static fn (TestRunner $t) => $t->same(true, $plan161()['selectedPlan']['orSplitPartialExpressionReady']),
    'planner stat4 expression partial current source or-split-partial-expression cursor open read' => static fn (TestRunner $t) => $t->same('OpenRead', $plan161()['cursorProgram'][0]['opcode']),
    'planner stat4 expression partial current source or-split-partial-expression cursor rewinds arms' => static fn (TestRunner $t) => $t->same(['opcode' => 'RewindOrArm', 'armCount' => 2], $plan161()['cursorProgram'][1]),
    'planner stat4 expression partial current source or-split-partial-expression cursor first seek' => static fn (TestRunner $t) => $t->same(['opcode' => 'SeekStat4Expression', 'arm' => 0, 'keys' => ['plugin_cache']], $plan161()['cursorProgram'][2]),
    'planner stat4 expression partial current source or-split-partial-expression cursor second seek' => static fn (TestRunner $t) => $t->same(['opcode' => 'SeekStat4Expression', 'arm' => 1, 'keys' => ['plugin_forms', 'plugin_mail', 'plugin_seo']], $plan161()['cursorProgram'][3]),
    'planner stat4 expression partial current source or-split-partial-expression cursor no distinct' => static fn (TestRunner $t) => $t->same('Noop', $plan161()['cursorProgram'][4]['opcode']),
    'planner stat4 expression partial current source or-split-partial-expression cursor result row' => static fn (TestRunner $t) => $t->same('ResultRow', $plan161()['cursorProgram'][5]['opcode']),
    'planner stat4 expression partial current source or-split-partial-expression cursor next arm' => static fn (TestRunner $t) => $t->same('NextOrArm', $plan161()['cursorProgram'][6]['opcode']),
    'planner stat4 expression partial current source or-split-partial-expression fence cookie' => static fn (TestRunner $t) => $t->same(1618, $plan161()['stat4Fence']['schemaCookie']),
    'planner stat4 expression partial current source or-split-partial-expression fence generation' => static fn (TestRunner $t) => $t->same(55, $plan161()['stat4Fence']['stat4Generation']),
    'planner stat4 expression partial current source or-split-partial-expression fence signatures' => static fn (TestRunner $t) => $t->same([64, 64, 64, 64], array_map('strlen', [$plan161()['stat4Fence']['sourceSignature'], $plan161()['stat4Fence']['orArmSignature'], $plan161()['stat4Fence']['stat4Signature'], $plan161()['stat4Fence']['rowStreamSignature']])),
    'planner stat4 expression partial current source or-split-partial-expression detail' => static fn (TestRunner $t) => $t->contains('OR-SPLIT', $plan161()['detail']),
    'planner stat4 expression partial current source or-split-partial-expression dependency marker' => static fn (TestRunner $t) => $t->same(['sqlite-sqlplanner-stat4-expression-partial-current-or-split-partial-expression'], $plan161()['dependencies']),
    'planner stat4 expression partial current source or-split-partial-expression dependency closure' => static fn (TestRunner $t) => $t->contains('no new support component needed', $plan161()['dependency_closure']),
    'planner stat4 expression partial current source or-split-partial-expression non overlap' => static fn (TestRunner $t) => $t->contains('OR-split partial expression probes', $plan161()['non_overlap']),
    'planner stat4 expression partial current source or-split-partial-expression fresh reuses prepared' => static fn (TestRunner $t) => $t->same('prepared', $fresh161()['selectedSource']),
    'planner stat4 expression partial current source or-split-partial-expression fresh rowids' => static fn (TestRunner $t) => $t->same([12, 24, 36], $fresh161()['matchedRowids']),
    'planner stat4 expression partial current source or-split-partial-expression unproved arm falls back' => static fn (TestRunner $t) => $t->same('requires-next-stage', $unproved161()['status']),
    'planner stat4 expression partial current source or-split-partial-expression no stat4 falls back' => static fn (TestRunner $t) => $t->same('requires-next-stage', $nostat4161()['status']),
    'planner stat4 expression partial current source or-split-partial-expression noncovering keeps ready' => static fn (TestRunner $t) => $t->same('stat4-expression-partial-current-or-split-partial-expression-ready', $noncovering161()['status']),
    'planner stat4 expression partial current source or-split-partial-expression noncovering table lookup' => static fn (TestRunner $t) => $t->same(true, $noncovering161()['tableLookupRequired']),
    'planner stat4 expression partial current source or-split-partial-expression invalid single arm' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => $plan161(null, null, [[$exprEq161('lower(option_name)', 'plugin_cache')]])),
    'planner stat4 expression partial current source or-split-partial-expression invalid needed columns' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => $plan161(null, null, null, [])),
    'planner stat4 expression partial current source or-split-partial-expression invalid stat4 sample' => static function (TestRunner $t) use ($current161, $plan161): void {
        $bad = $current161();
        $bad['indexes'][0]['stat4Samples'][0]['sample'] = [];
        $t->throws(InvalidArgumentException::class, static fn () => $plan161(null, $bad));
    },
    'planner stat4 expression partial current source or-split-partial-expression invalid rowid' => static function (TestRunner $t) use ($current161, $plan161): void {
        $bad = $current161();
        $bad['rows'][0]['rowid'] = -1;
        $t->throws(InvalidArgumentException::class, static fn () => $plan161(null, $bad));
    },
];

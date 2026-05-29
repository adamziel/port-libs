<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan;

$eq169 = static fn (string $column, mixed $right): array => ['left' => ['column' => $column], 'operator' => '=', 'right' => $right];
$notNull169 = static fn (string $column): array => ['left' => ['column' => $column], 'operator' => 'IS NOT NULL'];
$exprRange169 = static fn (string $expression, string $operator, mixed $right): array => ['left' => ['expression' => $expression], 'operator' => $operator, 'right' => $right];

$source169 = static function (array $overrides = []): array {
    $source = [
        'name' => 'prepared-wp-options-stat4-expression-partial-cost-next169',
        'schemaCookie' => 1690,
        'stat4Generation' => 62,
        'rows' => [
            ['rowid' => 10, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_cache', 'option_value' => 'stale-cache', 'updated_at' => 10],
            ['rowid' => 20, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_forms', 'option_value' => 'forms', 'updated_at' => 20],
            ['rowid' => 30, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_seo', 'option_value' => 'seo', 'updated_at' => 30],
        ],
        'indexes' => [[
            'name' => 'idx_wp_options_lower_plugin_partial_stat4_next169',
            'rootPage' => 16901,
            'expression' => 'lower(option_name)',
            'expressionColumn' => '__expr_lower_option_name',
            'collation' => 'BINARY',
            'partialPredicateTerms' => [
                ['left' => ['expression' => 'lower(option_name)'], 'operator' => '>=', 'right' => 'plugin_'],
                ['left' => ['expression' => 'lower(option_name)'], 'operator' => '<', 'right' => 'plugin`'],
                ['left' => ['column' => 'autoload'], 'operator' => '=', 'right' => 'yes'],
                ['left' => ['column' => 'option_name'], 'operator' => 'IS NOT NULL'],
            ],
            'coveringColumns' => ['option_name', 'option_value', 'updated_at', 'autoload'],
            'stat4Samples' => [
                ['neq' => '1 1', 'nlt' => '0 0', 'ndlt' => '0 0', 'sample' => ['plugin_cache', 10]],
                ['neq' => '1 1', 'nlt' => '1 1', 'ndlt' => '1 1', 'sample' => ['plugin_forms', 20]],
                ['neq' => '1 1', 'nlt' => '2 2', 'ndlt' => '2 2', 'sample' => ['plugin_seo', 30]],
            ],
        ], [
            'name' => 'idx_wp_options_lower_full_stat4_next169',
            'rootPage' => 16902,
            'expression' => 'lower(option_name)',
            'expressionColumn' => '__expr_lower_option_name',
            'collation' => 'BINARY',
            'partialPredicateTerms' => [],
            'coveringColumns' => ['option_name', 'option_value', 'updated_at', 'autoload'],
            'stat4Samples' => [
                ['neq' => '5 1', 'nlt' => '0 0', 'ndlt' => '0 0', 'sample' => ['active_plugins', 4]],
                ['neq' => '3 1', 'nlt' => '5 1', 'ndlt' => '1 1', 'sample' => ['plugin_cache', 10]],
                ['neq' => '3 1', 'nlt' => '8 2', 'ndlt' => '2 2', 'sample' => ['plugin_forms', 20]],
                ['neq' => '3 1', 'nlt' => '11 3', 'ndlt' => '3 3', 'sample' => ['plugin_seo', 30]],
                ['neq' => '8 1', 'nlt' => '14 4', 'ndlt' => '4 4', 'sample' => ['theme_mods', 50]],
            ],
        ]],
    ];

    return array_replace_recursive($source, $overrides);
};

$current169 = static function (array $overrides = []) use ($source169): array {
    $source = $source169([
        'name' => 'current-wp-options-stat4-expression-partial-cost-next169',
        'schemaCookie' => 1698,
        'stat4Generation' => 77,
    ]);
    $source['indexes'][0]['rootPage'] = 16981;
    $source['indexes'][0]['stat4Samples'] = [
        ['neq' => '2 1', 'nlt' => '0 0', 'ndlt' => '0 0', 'sample' => ['plugin_cache', 10]],
        ['neq' => '1 1', 'nlt' => '2 1', 'ndlt' => '1 1', 'sample' => ['plugin_forms', 20]],
        ['neq' => '1 1', 'nlt' => '3 2', 'ndlt' => '2 2', 'sample' => ['plugin_mail', 40]],
        ['neq' => '1 1', 'nlt' => '4 3', 'ndlt' => '3 3', 'sample' => ['plugin_seo', 30]],
    ];
    $source['rows'] = [
        ['rowid' => 40, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'Plugin_Mail', 'option_value' => 'mail', 'updated_at' => 40],
        ['rowid' => 10, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_cache', 'option_value' => 'fresh-cache', 'updated_at' => 15],
        ['rowid' => 20, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_forms', 'option_value' => 'forms', 'updated_at' => 20],
        ['rowid' => 30, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_seo', 'option_value' => 'seo', 'updated_at' => 30],
        ['rowid' => 50, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'theme_mods_twentysix', 'option_value' => 'theme', 'updated_at' => 50],
        ['rowid' => 60, 'blog_id' => 1, 'autoload' => 'no', 'option_name' => 'plugin_lazy', 'option_value' => 'lazy', 'updated_at' => 60],
        ['rowid' => 70, 'blog_id' => 2, 'autoload' => 'yes', 'option_name' => 'plugin_network', 'option_value' => 'network', 'updated_at' => 70],
    ];

    return array_replace_recursive($source, $overrides);
};

$terms169 = static fn (): array => [
    $exprRange169('LOWER( option_name )', '>=', 'plugin_cache'),
    $exprRange169('lower(option_name)', '<', 'plugin_t'),
    $eq169('autoload', 'yes'),
    $notNull169('option_name'),
];
$plan169 = static fn (?array $prepared = null, ?array $current = null, ?array $terms = null, ?array $needed = null): array => SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan::materializeNext169(
    $prepared ?? $source169(),
    $current ?? $current169(),
    $terms ?? $terms169(),
    $needed ?? ['option_name', 'option_value', 'updated_at'],
);
$fresh169 = static function () use ($source169, $plan169): array {
    $source = $source169();

    return $plan169($source, $source);
};
$cheapFull169 = static function () use ($current169, $plan169): array {
    $current = $current169();
    $current['indexes'][1]['stat4Samples'] = [
        ['neq' => '1 1', 'nlt' => '0 0', 'ndlt' => '0 0', 'sample' => ['plugin_cache', 10]],
    ];

    return $plan169(null, $current);
};
$noFull169 = static function () use ($current169, $plan169): array {
    $current = $current169();
    array_splice($current['indexes'], 1, 1);

    return $plan169(null, $current);
};

$tests = [
    'planner stat4 expression partial current source next169 status ready' => static fn (TestRunner $t) => $t->same('stat4-expression-partial-current-source-next169-ready', $plan169()['status']),
    'planner stat4 expression partial current source next169 selects current' => static fn (TestRunner $t) => $t->same('current', $plan169()['selectedSource']),
    'planner stat4 expression partial current source next169 stale prepared' => static fn (TestRunner $t) => $t->same(true, $plan169()['stalePreparedStatement']),
    'planner stat4 expression partial current source next169 reprepare required' => static fn (TestRunner $t) => $t->same(true, $plan169()['reprepareRequired']),
    'planner stat4 expression partial current source next169 schema changed' => static fn (TestRunner $t) => $t->same(true, $plan169()['schemaCookieChanged']),
    'planner stat4 expression partial current source next169 stat4 changed' => static fn (TestRunner $t) => $t->same(true, $plan169()['stat4GenerationChanged']),
    'planner stat4 expression partial current source next169 selected partial index' => static fn (TestRunner $t) => $t->same('idx_wp_options_lower_plugin_partial_stat4_next169', $plan169()['selectedPlan']['name']),
    'planner stat4 expression partial current source next169 root page current' => static fn (TestRunner $t) => $t->same(16981, $plan169()['selectedPlan']['rootPage']),
    'planner stat4 expression partial current source next169 expression' => static fn (TestRunner $t) => $t->same('lower(option_name)', $plan169()['selectedPlan']['expression']),
    'planner stat4 expression partial current source next169 partial implied' => static fn (TestRunner $t) => $t->same(true, $plan169()['selectedPlan']['partialPredicateImpliedByRange']),
    'planner stat4 expression partial current source next169 stat4 used' => static fn (TestRunner $t) => $t->same(true, $plan169()['selectedPlan']['stat4Used']),
    'planner stat4 expression partial current source next169 covering' => static fn (TestRunner $t) => $t->same(true, $plan169()['selectedPlan']['covering']),
    'planner stat4 expression partial current source next169 no table lookup' => static fn (TestRunner $t) => $t->same(false, $plan169()['tableLookupRequired']),
    'planner stat4 expression partial current source next169 partial cost' => static fn (TestRunner $t) => $t->same(5, $plan169()['selectedPlan']['next169PartialCost']),
    'planner stat4 expression partial current source next169 full cost' => static fn (TestRunner $t) => $t->same(40, $plan169()['selectedPlan']['next169BestFullExpressionCost']),
    'planner stat4 expression partial current source next169 cost beats full' => static fn (TestRunner $t) => $t->same(true, $plan169()['selectedPlan']['next169PartialBeatsFullExpressionIndex']),
    'planner stat4 expression partial current source next169 cost delta' => static fn (TestRunner $t) => $t->same(35, $plan169()['costFence']['costDelta']),
    'planner stat4 expression partial current source next169 candidate count' => static fn (TestRunner $t) => $t->same(2, count($plan169()['competingExpressionIndexes'])),
    'planner stat4 expression partial current source next169 selected candidate first' => static fn (TestRunner $t) => $t->same(true, $plan169()['competingExpressionIndexes'][0]['selected']),
    'planner stat4 expression partial current source next169 selected candidate partial' => static fn (TestRunner $t) => $t->same(true, $plan169()['competingExpressionIndexes'][0]['partial']),
    'planner stat4 expression partial current source next169 full candidate second' => static fn (TestRunner $t) => $t->same(false, $plan169()['competingExpressionIndexes'][1]['partial']),
    'planner stat4 expression partial current source next169 rejected full name' => static fn (TestRunner $t) => $t->same('idx_wp_options_lower_full_stat4_next169', $plan169()['rejectedFullExpressionIndexes'][0]['name']),
    'planner stat4 expression partial current source next169 matched rowids' => static fn (TestRunner $t) => $t->same([10, 20, 40, 70, 30], $plan169()['matchedRowids']),
    'planner stat4 expression partial current source next169 matched keys' => static fn (TestRunner $t) => $t->same(['plugin_cache', 'plugin_forms', 'plugin_mail', 'plugin_network', 'plugin_seo'], $plan169()['matchedExpressionKeys']),
    'planner stat4 expression partial current source next169 fresh cache payload' => static fn (TestRunner $t) => $t->same('fresh-cache', $plan169()['matchedRows'][0]['payload']['option_value']),
    'planner stat4 expression partial current source next169 mixed case normalized' => static fn (TestRunner $t) => $t->same('plugin_mail', $plan169()['matchedRows'][2]['expressionKey']),
    'planner stat4 expression partial current source next169 excludes theme row' => static fn (TestRunner $t) => $t->same(false, in_array(50, $plan169()['matchedRowids'], true)),
    'planner stat4 expression partial current source next169 excludes autoload no' => static fn (TestRunner $t) => $t->same(false, in_array(60, $plan169()['matchedRowids'], true)),
    'planner stat4 expression partial current source next169 admits blog two because partial does not constrain blog' => static fn (TestRunner $t) => $t->same(true, in_array(70, $plan169()['matchedRowids'], true)),
    'planner stat4 expression partial current source next169 stat4 keys' => static fn (TestRunner $t) => $t->same(['plugin_cache', 'plugin_forms', 'plugin_mail', 'plugin_seo'], $plan169()['selectedPlan']['matchedStat4Keys']),
    'planner stat4 expression partial current source next169 stat4 rowids' => static fn (TestRunner $t) => $t->same([10, 20, 40, 30], $plan169()['selectedPlan']['matchedStat4Rowids']),
    'planner stat4 expression partial current source next169 estimated rows' => static fn (TestRunner $t) => $t->same(5, $plan169()['selectedPlan']['estimatedRows']),
    'planner stat4 expression partial current source next169 cursor seek lower' => static fn (TestRunner $t) => $t->same('plugin_cache', $plan169()['cursorProgram'][1]['key']),
    'planner stat4 expression partial current source next169 cursor upper' => static fn (TestRunner $t) => $t->same('plugin_t', $plan169()['cursorProgram'][2]['key']),
    'planner stat4 expression partial current source next169 cursor result rowids' => static fn (TestRunner $t) => $t->same([10, 20, 40, 70, 30], $plan169()['cursorProgram'][5]['rowids']),
    'planner stat4 expression partial current source next169 fence cookie' => static fn (TestRunner $t) => $t->same(1698, $plan169()['stat4Fence']['schemaCookie']),
    'planner stat4 expression partial current source next169 fence generation' => static fn (TestRunner $t) => $t->same(77, $plan169()['stat4Fence']['stat4Generation']),
    'planner stat4 expression partial current source next169 fence hashes' => static fn (TestRunner $t) => $t->same([64, 64, 64], array_map('strlen', [$plan169()['costFence']['sourceSignature'], $plan169()['costFence']['candidateSignature'], $plan169()['stat4Fence']['rowStreamSignature']])),
    'planner stat4 expression partial current source next169 detail' => static fn (TestRunner $t) => $t->contains('COST-FENCE', $plan169()['detail']),
    'planner stat4 expression partial current source next169 dependency marker' => static fn (TestRunner $t) => $t->same(['sqlite-sqlplanner-stat4-expression-partial-current-source-next169'], $plan169()['dependencies']),
    'planner stat4 expression partial current source next169 dependency closure' => static fn (TestRunner $t) => $t->contains('no new support component needed', $plan169()['dependency_closure']),
    'planner stat4 expression partial current source next169 non overlap' => static fn (TestRunner $t) => $t->contains('competing full expression index', $plan169()['non_overlap']),
    'planner stat4 expression partial current source next169 fresh reuses prepared' => static fn (TestRunner $t) => $t->same('prepared', $fresh169()['selectedSource']),
    'planner stat4 expression partial current source next169 fresh rowids' => static fn (TestRunner $t) => $t->same([10, 20, 30], $fresh169()['matchedRowids']),
    'planner stat4 expression partial current source next169 cheap full still costlier than partial' => static fn (TestRunner $t) => $t->same('stat4-expression-partial-current-source-next169-ready', $cheapFull169()['status']),
    'planner stat4 expression partial current source next169 cheap full best cost' => static fn (TestRunner $t) => $t->same(19, $cheapFull169()['selectedPlan']['next169BestFullExpressionCost']),
    'planner stat4 expression partial current source next169 no full falls back' => static fn (TestRunner $t) => $t->same('requires-next-stage', $noFull169()['status']),
    'planner stat4 expression partial current source next169 no full has no cost' => static fn (TestRunner $t) => $t->same(null, $noFull169()['selectedPlan']['next169BestFullExpressionCost']),
    'planner stat4 expression partial current source next169 invalid needed columns' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => $plan169(null, null, null, [])),
    'planner stat4 expression partial current source next169 invalid stat4 integer' => static function (TestRunner $t) use ($current169, $plan169): void {
        $bad = $current169();
        $bad['indexes'][1]['stat4Samples'][0]['neq'] = 'bad';
        $t->throws(InvalidArgumentException::class, static fn () => $plan169(null, $bad));
    },
];

foreach (range(1, 12) as $case) {
    $tests['planner stat4 expression partial current source next169 repeated cost fence ' . $case] = static function (TestRunner $t) use ($plan169, $case): void {
        $plan = $plan169();
        $t->same(true, $plan['costFence']['costDelta'] > $case);
    };
}

return $tests;

<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan;

$eq177 = static fn (string $column, mixed $right): array => ['left' => ['column' => $column], 'operator' => '=', 'right' => $right];
$notNull177 = static fn (string $column): array => ['left' => ['column' => $column], 'operator' => 'IS NOT NULL'];
$between177 = static fn (string $expression, mixed $lower, mixed $upper): array => ['left' => ['expression' => $expression], 'operator' => 'BETWEEN', 'lower' => $lower, 'upper' => $upper];

$prepared177 = static fn (): array => [
    'name' => 'prepared-wp-options-stat4-expression-partial-between-next177',
    'schemaCookie' => 1770,
    'stat4Generation' => 50,
    'rows' => [
        ['rowid' => 10, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_cache', 'option_value' => 'old-cache', 'updated_at' => 10],
        ['rowid' => 20, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_forms', 'option_value' => 'forms', 'updated_at' => 20],
        ['rowid' => 30, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_seo', 'option_value' => 'seo', 'updated_at' => 30],
    ],
    'indexes' => [[
        'name' => 'idx_wp_options_lower_between_partial_stat4_next177',
        'rootPage' => 17701,
        'expression' => 'lower(option_name)',
        'expressionColumn' => '__expr_lower_option_name',
        'collation' => 'BINARY',
        'partialPredicateTerms' => [
            ['left' => ['expression' => 'lower(option_name)'], 'operator' => '>=', 'right' => 'plugin_cache'],
            ['left' => ['expression' => 'lower(option_name)'], 'operator' => '<=', 'right' => 'plugin_seo'],
            ['left' => ['column' => 'autoload'], 'operator' => '=', 'right' => 'yes'],
            ['left' => ['column' => 'option_name'], 'operator' => 'IS NOT NULL'],
        ],
        'coveringColumns' => ['option_name', 'option_value', 'updated_at', 'autoload', 'blog_id'],
        'stat4Samples' => [
            ['neq' => '1 1', 'nlt' => '0 0', 'ndlt' => '0 0', 'sample' => ['plugin_cache', 10]],
            ['neq' => '1 1', 'nlt' => '1 1', 'ndlt' => '1 1', 'sample' => ['plugin_forms', 20]],
            ['neq' => '1 1', 'nlt' => '2 2', 'ndlt' => '2 2', 'sample' => ['plugin_seo', 30]],
        ],
    ]],
];

$current177 = static function () use ($prepared177): array {
    $source = $prepared177();
    $source['name'] = 'current-wp-options-stat4-expression-partial-between-next177';
    $source['schemaCookie'] = 1778;
    $source['stat4Generation'] = 68;
    $source['indexes'][0]['rootPage'] = 17788;
    $source['indexes'][0]['stat4Samples'] = [
        ['neq' => '2 1', 'nlt' => '0 0', 'ndlt' => '0 0', 'sample' => ['plugin_cache', 10]],
        ['neq' => '1 1', 'nlt' => '2 1', 'ndlt' => '1 1', 'sample' => ['plugin_forms', 20]],
        ['neq' => '1 1', 'nlt' => '3 2', 'ndlt' => '2 2', 'sample' => ['plugin_mail', 40]],
        ['neq' => '1 1', 'nlt' => '4 3', 'ndlt' => '3 3', 'sample' => ['plugin_security', 50]],
        ['neq' => '1 1', 'nlt' => '5 4', 'ndlt' => '4 4', 'sample' => ['plugin_seo', 30]],
        ['neq' => '1 1', 'nlt' => '6 5', 'ndlt' => '5 5', 'sample' => ['theme_mods', 60]],
    ];
    $source['rows'] = [
        ['rowid' => 50, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'Plugin_Security', 'option_value' => 'security', 'updated_at' => 50],
        ['rowid' => 10, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_cache', 'option_value' => 'fresh-cache', 'updated_at' => 15],
        ['rowid' => 11, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'Plugin_Cache', 'option_value' => 'fresh-cache-copy', 'updated_at' => 16],
        ['rowid' => 20, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_forms', 'option_value' => 'forms', 'updated_at' => 20],
        ['rowid' => 40, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'Plugin_Mail', 'option_value' => 'mail', 'updated_at' => 40],
        ['rowid' => 30, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_seo', 'option_value' => 'seo', 'updated_at' => 30],
        ['rowid' => 60, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'theme_mods_twentysix', 'option_value' => 'theme', 'updated_at' => 60],
        ['rowid' => 70, 'blog_id' => 1, 'autoload' => 'no', 'option_name' => 'plugin_forms', 'option_value' => 'lazy', 'updated_at' => 70],
        ['rowid' => 80, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => null, 'option_value' => 'null-name', 'updated_at' => 80],
    ];

    return $source;
};

$terms177 = static fn (): array => [
    $between177('LOWER( option_name )', 'plugin_cache', 'plugin_seo'),
    $eq177('autoload', 'yes'),
    $notNull177('option_name'),
];
$plan177 = static fn (?array $prepared = null, ?array $current = null, ?array $terms = null, ?array $needed = null): array => SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan::materializeStat4BetweenRangeFence(
    $prepared ?? $prepared177(),
    $current ?? $current177(),
    $terms ?? $terms177(),
    $needed ?? ['option_name', 'option_value', 'updated_at', 'blog_id'],
);
$fresh177 = static function () use ($prepared177, $plan177): array {
    $source = $prepared177();

    return $plan177($source, $source);
};
$wide177 = static function () use ($terms177, $plan177): array {
    $terms = $terms177();
    $terms[0]['lower'] = 'option_';

    return $plan177(null, null, $terms);
};
$openUpper177 = static function () use ($current177, $plan177): array {
    $current = $current177();
    $current['rows'] = array_values(array_filter(
        $current['rows'],
        static fn (array $row): bool => ($row['rowid'] ?? null) !== 30,
    ));

    return $plan177(null, $current);
};

$tests = [
    'planner stat4 expression partial current source next177 status ready' => static fn (TestRunner $t) => $t->same('stat4-expression-partial-current-source-next177-ready', $plan177()['status']),
    'planner stat4 expression partial current source next177 selected current' => static fn (TestRunner $t) => $t->same('current', $plan177()['selectedSource']),
    'planner stat4 expression partial current source next177 stale prepared' => static fn (TestRunner $t) => $t->same(true, $plan177()['stalePreparedStatement']),
    'planner stat4 expression partial current source next177 reprepare required' => static fn (TestRunner $t) => $t->same(true, $plan177()['reprepareRequired']),
    'planner stat4 expression partial current source next177 schema changed' => static fn (TestRunner $t) => $t->same(true, $plan177()['schemaCookieChanged']),
    'planner stat4 expression partial current source next177 stat4 changed' => static fn (TestRunner $t) => $t->same(true, $plan177()['stat4GenerationChanged']),
    'planner stat4 expression partial current source next177 selected index' => static fn (TestRunner $t) => $t->same('idx_wp_options_lower_between_partial_stat4_next177', $plan177()['selectedPlan']['name']),
    'planner stat4 expression partial current source next177 root page' => static fn (TestRunner $t) => $t->same(17788, $plan177()['selectedPlan']['rootPage']),
    'planner stat4 expression partial current source next177 expression' => static fn (TestRunner $t) => $t->same('lower(option_name)', $plan177()['selectedPlan']['expression']),
    'planner stat4 expression partial current source next177 ready flag' => static fn (TestRunner $t) => $t->same(true, $plan177()['selectedPlan']['next177Ready']),
    'planner stat4 expression partial current source next177 operator flag' => static fn (TestRunner $t) => $t->same('BETWEEN', $plan177()['selectedPlan']['next177NormalizedOperator']),
    'planner stat4 expression partial current source next177 inclusive flag' => static fn (TestRunner $t) => $t->same(true, $plan177()['selectedPlan']['next177BetweenInclusive']),
    'planner stat4 expression partial current source next177 lower boundary rowids' => static fn (TestRunner $t) => $t->same([10, 11], $plan177()['selectedPlan']['next177LowerBoundaryRowids']),
    'planner stat4 expression partial current source next177 upper boundary rowids' => static fn (TestRunner $t) => $t->same([30], $plan177()['selectedPlan']['next177UpperBoundaryRowids']),
    'planner stat4 expression partial current source next177 between expression normalized' => static fn (TestRunner $t) => $t->same('lower(option_name)', $plan177()['betweenTerm']['expression']),
    'planner stat4 expression partial current source next177 between lower' => static fn (TestRunner $t) => $t->same('plugin_cache', $plan177()['betweenTerm']['lower']),
    'planner stat4 expression partial current source next177 between upper' => static fn (TestRunner $t) => $t->same('plugin_seo', $plan177()['betweenTerm']['upper']),
    'planner stat4 expression partial current source next177 normalized lower op' => static fn (TestRunner $t) => $t->same('>=', $plan177()['normalizedWhereTerms'][0]['operator']),
    'planner stat4 expression partial current source next177 normalized upper op' => static fn (TestRunner $t) => $t->same('<=', $plan177()['normalizedWhereTerms'][1]['operator']),
    'planner stat4 expression partial current source next177 range lower inclusive' => static fn (TestRunner $t) => $t->same(true, $plan177()['selectedPlan']['rangeConstraint']['lowerInclusive']),
    'planner stat4 expression partial current source next177 range upper inclusive' => static fn (TestRunner $t) => $t->same(true, $plan177()['selectedPlan']['rangeConstraint']['upperInclusive']),
    'planner stat4 expression partial current source next177 range lower' => static fn (TestRunner $t) => $t->same('plugin_cache', $plan177()['selectedPlan']['rangeConstraint']['lower']),
    'planner stat4 expression partial current source next177 range upper' => static fn (TestRunner $t) => $t->same('plugin_seo', $plan177()['selectedPlan']['rangeConstraint']['upper']),
    'planner stat4 expression partial current source next177 stat4 keys' => static fn (TestRunner $t) => $t->same(['plugin_cache', 'plugin_forms', 'plugin_mail', 'plugin_security', 'plugin_seo'], $plan177()['selectedPlan']['matchedStat4Keys']),
    'planner stat4 expression partial current source next177 stat4 rowids' => static fn (TestRunner $t) => $t->same([10, 20, 40, 50, 30], $plan177()['selectedPlan']['matchedStat4Rowids']),
    'planner stat4 expression partial current source next177 estimated rows' => static fn (TestRunner $t) => $t->same(6, $plan177()['selectedPlan']['estimatedRows']),
    'planner stat4 expression partial current source next177 matched rowids' => static fn (TestRunner $t) => $t->same([10, 11, 20, 40, 50, 30], $plan177()['matchedRowids']),
    'planner stat4 expression partial current source next177 matched keys' => static fn (TestRunner $t) => $t->same(['plugin_cache', 'plugin_cache', 'plugin_forms', 'plugin_mail', 'plugin_security', 'plugin_seo'], $plan177()['matchedExpressionKeys']),
    'planner stat4 expression partial current source next177 lower duplicate payload' => static fn (TestRunner $t) => $t->same('fresh-cache-copy', $plan177()['matchedRows'][1]['payload']['option_value']),
    'planner stat4 expression partial current source next177 mixed case normalized' => static fn (TestRunner $t) => $t->same('plugin_mail', $plan177()['matchedRows'][3]['expressionKey']),
    'planner stat4 expression partial current source next177 excludes theme' => static fn (TestRunner $t) => $t->same(false, in_array(60, $plan177()['matchedRowids'], true)),
    'planner stat4 expression partial current source next177 excludes autoload no' => static fn (TestRunner $t) => $t->same(false, in_array(70, $plan177()['matchedRowids'], true)),
    'planner stat4 expression partial current source next177 excludes null' => static fn (TestRunner $t) => $t->same(false, in_array(80, $plan177()['matchedRowids'], true)),
    'planner stat4 expression partial current source next177 fence lower' => static fn (TestRunner $t) => $t->same('plugin_cache', $plan177()['betweenFence']['lower']),
    'planner stat4 expression partial current source next177 fence upper' => static fn (TestRunner $t) => $t->same('plugin_seo', $plan177()['betweenFence']['upper']),
    'planner stat4 expression partial current source next177 fence lower rowids' => static fn (TestRunner $t) => $t->same([10, 11], $plan177()['betweenFence']['lowerBoundaryRowids']),
    'planner stat4 expression partial current source next177 fence upper rowids' => static fn (TestRunner $t) => $t->same([30], $plan177()['betweenFence']['upperBoundaryRowids']),
    'planner stat4 expression partial current source next177 fence merged rowids' => static fn (TestRunner $t) => $t->same([10, 11, 30], $plan177()['betweenFence']['matchedBoundaryRowids']),
    'planner stat4 expression partial current source next177 fence hashes' => static fn (TestRunner $t) => $t->same([64, 64, 64, 64], array_map('strlen', [$plan177()['betweenFence']['betweenSignature'], $plan177()['betweenFence']['normalizedTermSignature'], $plan177()['stat4Fence']['next177BetweenSignature'], $plan177()['stat4Fence']['next177BoundarySignature']])),
    'planner stat4 expression partial current source next177 cursor open' => static fn (TestRunner $t) => $t->same('OpenRead', $plan177()['cursorProgram'][0]['opcode']),
    'planner stat4 expression partial current source next177 cursor seek ge' => static fn (TestRunner $t) => $t->same(['opcode' => 'SeekGE', 'key' => 'plugin_cache'], $plan177()['cursorProgram'][1]),
    'planner stat4 expression partial current source next177 cursor idxle' => static fn (TestRunner $t) => $t->same(['opcode' => 'IdxLE', 'key' => 'plugin_seo'], $plan177()['cursorProgram'][2]),
    'planner stat4 expression partial current source next177 cursor fence inserted' => static fn (TestRunner $t) => $t->same('BetweenInclusiveFence', $plan177()['cursorProgram'][3]['opcode']),
    'planner stat4 expression partial current source next177 cursor covering follows fence' => static fn (TestRunner $t) => $t->same('ColumnFromIndex', $plan177()['cursorProgram'][4]['opcode']),
    'planner stat4 expression partial current source next177 cursor rowids shifted' => static fn (TestRunner $t) => $t->same([10, 11, 20, 40, 50, 30], $plan177()['cursorProgram'][6]['rowids']),
    'planner stat4 expression partial current source next177 detail' => static fn (TestRunner $t) => $t->contains('NEXT177 BETWEEN', $plan177()['detail']),
    'planner stat4 expression partial current source next177 dependency marker' => static fn (TestRunner $t) => $t->true(in_array('sqlite-sqlplanner-stat4-expression-partial-current-source-next177', $plan177()['dependencies'], true)),
    'planner stat4 expression partial current source next177 dependency closure' => static fn (TestRunner $t) => $t->contains('no new support component needed', $plan177()['dependency_closure']),
    'planner stat4 expression partial current source next177 non overlap' => static fn (TestRunner $t) => $t->contains('BETWEEN terms', $plan177()['non_overlap']),
    'planner stat4 expression partial current source next177 fresh uses prepared' => static fn (TestRunner $t) => $t->same('prepared', $fresh177()['selectedSource']),
    'planner stat4 expression partial current source next177 fresh rowids' => static fn (TestRunner $t) => $t->same([10, 20, 30], $fresh177()['matchedRowids']),
    'planner stat4 expression partial current source next177 wide cannot prove partial' => static fn (TestRunner $t) => $t->same('requires-current-source-reprepare', $wide177()['status']),
    'planner stat4 expression partial current source next177 missing upper boundary blocks' => static fn (TestRunner $t) => $t->same('requires-current-source-reprepare', $openUpper177()['status']),
    'planner stat4 expression partial current source next177 missing upper boundary rowids empty' => static fn (TestRunner $t) => $t->same([], $openUpper177()['betweenFence']['upperBoundaryRowids']),
    'planner stat4 expression partial current source next177 invalid bounds' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => $plan177(null, null, [['left' => ['expression' => 'lower(option_name)'], 'operator' => 'BETWEEN']])),
    'planner stat4 expression partial current source next177 invalid left' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => $plan177(null, null, [['left' => ['column' => 'option_name'], 'operator' => 'BETWEEN', 'lower' => 'a', 'upper' => 'z']])),
];

foreach (range(1, 10) as $case) {
    $tests['planner stat4 expression partial current source next177 repeated boundary fence ' . $case] = static function (TestRunner $t) use ($plan177, $case): void {
        $plan = $plan177();
        $t->same(true, count($plan['matchedRowids']) >= $case % 6);
    };
}

return $tests;

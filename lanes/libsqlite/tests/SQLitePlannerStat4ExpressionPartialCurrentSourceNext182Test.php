<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan;

$eq182 = static fn (string $column, mixed $right): array => ['left' => ['column' => $column], 'operator' => '=', 'right' => $right];
$notNull182 = static fn (string $column): array => ['left' => ['column' => $column], 'operator' => 'IS NOT NULL'];
$between182 = static fn (string $expression, mixed $lower, mixed $upper): array => ['left' => ['expression' => $expression], 'operator' => 'BETWEEN', 'lower' => $lower, 'upper' => $upper];

$prepared182 = static fn (): array => [
    'name' => 'prepared-wp-options-limit-stat4-expression-partial-next182',
    'schemaCookie' => 1820,
    'stat4Generation' => 91,
    'rows' => [
        ['rowid' => 10, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_alpha', 'option_value' => 'alpha-old', 'updated_at' => 10],
        ['rowid' => 20, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_forms', 'option_value' => 'forms-old', 'updated_at' => 20],
        ['rowid' => 30, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_seo', 'option_value' => 'seo-old', 'updated_at' => 30],
    ],
    'indexes' => [[
        'name' => 'idx_wp_options_lower_desc_partial_stat4_next182',
        'rootPage' => 18201,
        'expression' => 'lower(option_name)',
        'expressionColumn' => '__expr_lower_option_name',
        'collation' => 'BINARY',
        'descending' => true,
        'partialPredicateTerms' => [
            ['left' => ['expression' => 'lower(option_name)'], 'operator' => '>=', 'right' => 'plugin_alpha'],
            ['left' => ['expression' => 'lower(option_name)'], 'operator' => '<=', 'right' => 'plugin_zulu'],
            ['left' => ['column' => 'autoload'], 'operator' => '=', 'right' => 'yes'],
            ['left' => ['column' => 'option_name'], 'operator' => 'IS NOT NULL'],
        ],
        'coveringColumns' => ['option_name', 'option_value', 'updated_at', 'autoload', 'blog_id'],
        'stat4Samples' => [
            ['neq' => '1 1', 'nlt' => '0 0', 'ndlt' => '0 0', 'sample' => ['plugin_alpha', 10]],
            ['neq' => '1 1', 'nlt' => '1 1', 'ndlt' => '1 1', 'sample' => ['plugin_forms', 20]],
            ['neq' => '1 1', 'nlt' => '2 2', 'ndlt' => '2 2', 'sample' => ['plugin_seo', 30]],
        ],
    ]],
];

$current182 = static function () use ($prepared182): array {
    $source = $prepared182();
    $source['name'] = 'current-wp-options-limit-stat4-expression-partial-next182';
    $source['schemaCookie'] = 1828;
    $source['stat4Generation'] = 118;
    $source['indexes'][0]['rootPage'] = 18288;
    $source['indexes'][0]['stat4Samples'] = [
        ['neq' => '1 1', 'nlt' => '0 0', 'ndlt' => '0 0', 'sample' => ['plugin_alpha', 10]],
        ['neq' => '1 1', 'nlt' => '1 1', 'ndlt' => '1 1', 'sample' => ['plugin_cache', 40]],
        ['neq' => '2 1', 'nlt' => '2 2', 'ndlt' => '2 2', 'sample' => ['plugin_forms', 20]],
        ['neq' => '1 1', 'nlt' => '4 3', 'ndlt' => '3 3', 'sample' => ['plugin_mail', 50]],
        ['neq' => '1 1', 'nlt' => '5 4', 'ndlt' => '4 4', 'sample' => ['plugin_seo', 30]],
        ['neq' => '1 1', 'nlt' => '6 5', 'ndlt' => '5 5', 'sample' => ['plugin_zulu', 60]],
    ];
    $source['rows'] = [
        ['rowid' => 60, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_zulu', 'option_value' => 'zulu', 'updated_at' => 60],
        ['rowid' => 30, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_seo', 'option_value' => 'seo', 'updated_at' => 30],
        ['rowid' => 50, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'Plugin_Mail', 'option_value' => 'mail', 'updated_at' => 50],
        ['rowid' => 20, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_forms', 'option_value' => 'forms', 'updated_at' => 20],
        ['rowid' => 21, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'Plugin_Forms', 'option_value' => 'forms-copy', 'updated_at' => 21],
        ['rowid' => 40, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_cache', 'option_value' => 'cache', 'updated_at' => 40],
        ['rowid' => 10, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_alpha', 'option_value' => 'alpha', 'updated_at' => 10],
        ['rowid' => 70, 'blog_id' => 1, 'autoload' => 'no', 'option_name' => 'plugin_seo', 'option_value' => 'lazy', 'updated_at' => 70],
        ['rowid' => 80, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'theme_mods_twentysix', 'option_value' => 'theme', 'updated_at' => 80],
        ['rowid' => 90, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => null, 'option_value' => 'null-name', 'updated_at' => 90],
    ];

    return $source;
};

$terms182 = static fn (): array => [
    $between182('LOWER(option_name)', 'plugin_alpha', 'plugin_zulu'),
    $eq182('autoload', 'yes'),
    $notNull182('option_name'),
];
$plan182 = static fn (int $limit = 3, int $offset = 1, ?array $prepared = null, ?array $current = null, ?array $terms = null, ?array $needed = null): array => SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan::materializeCurrentSourceLimitProjectionFence(
    $prepared ?? $prepared182(),
    $current ?? $current182(),
    $terms ?? $terms182(),
    $needed ?? ['option_name', 'option_value', 'updated_at', 'blog_id'],
    $limit,
    $offset,
);

$tests = [
    'planner stat4 expression partial current source next182 status ready' => static fn (TestRunner $t) => $t->same('stat4-expression-partial-current-source-next182-ready', $plan182()['status']),
    'planner stat4 expression partial current source next182 selected current' => static fn (TestRunner $t) => $t->same('current', $plan182()['selectedSource']),
    'planner stat4 expression partial current source next182 stale prepared' => static fn (TestRunner $t) => $t->same(true, $plan182()['stalePreparedStatement']),
    'planner stat4 expression partial current source next182 selected index' => static fn (TestRunner $t) => $t->same('idx_wp_options_lower_desc_partial_stat4_next182', $plan182()['selectedPlan']['name']),
    'planner stat4 expression partial current source next182 root page' => static fn (TestRunner $t) => $t->same(18288, $plan182()['selectedPlan']['rootPage']),
    'planner stat4 expression partial current source next182 base descending ready' => static fn (TestRunner $t) => $t->same(true, $plan182()['selectedPlan']['next180Ready']),
    'planner stat4 expression partial current source next182 ready flag' => static fn (TestRunner $t) => $t->same(true, $plan182()['selectedPlan']['next182Ready']),
    'planner stat4 expression partial current source next182 no table lookup' => static fn (TestRunner $t) => $t->same(false, $plan182()['tableLookupRequired']),
    'planner stat4 expression partial current source next182 no temp sort' => static fn (TestRunner $t) => $t->same(false, $plan182()['temporarySortRequired']),
    'planner stat4 expression partial current source next182 limit' => static fn (TestRunner $t) => $t->same(3, $plan182()['limitWindow']['limit']),
    'planner stat4 expression partial current source next182 offset' => static fn (TestRunner $t) => $t->same(1, $plan182()['limitWindow']['offset']),
    'planner stat4 expression partial current source next182 input rowids' => static fn (TestRunner $t) => $t->same([60, 30, 50, 20, 21, 40, 10], $plan182()['limitWindow']['inputRowids']),
    'planner stat4 expression partial current source next182 window rowids' => static fn (TestRunner $t) => $t->same([30, 50, 20], $plan182()['limitWindow']['rowids']),
    'planner stat4 expression partial current source next182 matched rowids windowed' => static fn (TestRunner $t) => $t->same([30, 50, 20], $plan182()['matchedRowids']),
    'planner stat4 expression partial current source next182 window keys' => static fn (TestRunner $t) => $t->same(['plugin_seo', 'plugin_mail', 'plugin_forms'], $plan182()['limitWindow']['keys']),
    'planner stat4 expression partial current source next182 matched keys windowed' => static fn (TestRunner $t) => $t->same(['plugin_seo', 'plugin_mail', 'plugin_forms'], $plan182()['matchedExpressionKeys']),
    'planner stat4 expression partial current source next182 window count' => static fn (TestRunner $t) => $t->same(3, $plan182()['limitWindow']['count']),
    'planner stat4 expression partial current source next182 not exhausted' => static fn (TestRunner $t) => $t->same(false, $plan182()['limitWindow']['exhausted']),
    'planner stat4 expression partial current source next182 projected count' => static fn (TestRunner $t) => $t->same(3, count($plan182()['projectedRows'])),
    'planner stat4 expression partial current source next182 projected columns' => static fn (TestRunner $t) => $t->same(['option_name', 'option_value', 'updated_at', 'blog_id'], $plan182()['projectedColumns']),
    'planner stat4 expression partial current source next182 first projected rowid' => static fn (TestRunner $t) => $t->same(30, $plan182()['projectedRows'][0]['rowid']),
    'planner stat4 expression partial current source next182 first projected name' => static fn (TestRunner $t) => $t->same('plugin_seo', $plan182()['projectedRows'][0]['option_name']),
    'planner stat4 expression partial current source next182 first projected value' => static fn (TestRunner $t) => $t->same('seo', $plan182()['projectedRows'][0]['option_value']),
    'planner stat4 expression partial current source next182 middle projected mixed case' => static fn (TestRunner $t) => $t->same('Plugin_Mail', $plan182()['projectedRows'][1]['option_name']),
    'planner stat4 expression partial current source next182 duplicate key first rowid retained' => static fn (TestRunner $t) => $t->same(20, $plan182()['projectedRows'][2]['rowid']),
    'planner stat4 expression partial current source next182 selected window rowids' => static fn (TestRunner $t) => $t->same([30, 50, 20], $plan182()['selectedPlan']['next182WindowRowids']),
    'planner stat4 expression partial current source next182 selected window keys' => static fn (TestRunner $t) => $t->same(['plugin_seo', 'plugin_mail', 'plugin_forms'], $plan182()['selectedPlan']['next182WindowKeys']),
    'planner stat4 expression partial current source next182 selected limit' => static fn (TestRunner $t) => $t->same(3, $plan182()['selectedPlan']['next182Limit']),
    'planner stat4 expression partial current source next182 selected offset' => static fn (TestRunner $t) => $t->same(1, $plan182()['selectedPlan']['next182Offset']),
    'planner stat4 expression partial current source next182 projection signature' => static fn (TestRunner $t) => $t->same(64, strlen($plan182()['stat4Fence']['next182ProjectionSignature'])),
    'planner stat4 expression partial current source next182 limit signature' => static fn (TestRunner $t) => $t->same(64, strlen($plan182()['stat4Fence']['next182LimitSignature'])),
    'planner stat4 expression partial current source next182 window signature' => static fn (TestRunner $t) => $t->same(64, strlen($plan182()['limitWindow']['signature'])),
    'planner stat4 expression partial current source next182 cursor limit opcode' => static fn (TestRunner $t) => $t->same('LimitOffsetWindow', $plan182()['cursorProgram'][array_key_last($plan182()['cursorProgram']) - 1]['opcode']),
    'planner stat4 expression partial current source next182 cursor payload opcode' => static fn (TestRunner $t) => $t->same('ColumnFromCoveringIndexPayload', $plan182()['cursorProgram'][array_key_last($plan182()['cursorProgram'])]['opcode']),
    'planner stat4 expression partial current source next182 cursor window rowids' => static fn (TestRunner $t) => $t->same([30, 50, 20], $plan182()['cursorProgram'][array_key_last($plan182()['cursorProgram']) - 1]['rowids']),
    'planner stat4 expression partial current source next182 zero limit' => static fn (TestRunner $t) => $t->same([], $plan182(0, 0)['matchedRowids']),
    'planner stat4 expression partial current source next182 zero limit projection' => static fn (TestRunner $t) => $t->same([], $plan182(0, 0)['projectedRows']),
    'planner stat4 expression partial current source next182 offset tail' => static fn (TestRunner $t) => $t->same([40, 10], $plan182(4, 5)['matchedRowids']),
    'planner stat4 expression partial current source next182 offset exhausted' => static fn (TestRunner $t) => $t->same(true, $plan182(2, 9)['limitWindow']['exhausted']),
    'planner stat4 expression partial current source next182 offset exhausted rows' => static fn (TestRunner $t) => $t->same([], $plan182(2, 9)['matchedRowids']),
    'planner stat4 expression partial current source next182 full window' => static fn (TestRunner $t) => $t->same([60, 30, 50, 20, 21, 40, 10], $plan182(99, 0)['matchedRowids']),
    'planner stat4 expression partial current source next182 duplicate second row reachable' => static fn (TestRunner $t) => $t->same([20, 21], $plan182(2, 3)['matchedRowids']),
    'planner stat4 expression partial current source next182 excludes autoload no' => static fn (TestRunner $t) => $t->same(false, in_array(70, $plan182(99, 0)['limitWindow']['inputRowids'], true)),
    'planner stat4 expression partial current source next182 excludes theme' => static fn (TestRunner $t) => $t->same(false, in_array(80, $plan182(99, 0)['limitWindow']['inputRowids'], true)),
    'planner stat4 expression partial current source next182 excludes null name' => static fn (TestRunner $t) => $t->same(false, in_array(90, $plan182(99, 0)['limitWindow']['inputRowids'], true)),
    'planner stat4 expression partial current source next182 detail' => static fn (TestRunner $t) => $t->contains('NEXT182 LIMIT WINDOW', $plan182()['detail']),
    'planner stat4 expression partial current source next182 dependency marker' => static fn (TestRunner $t) => $t->true(in_array('sqlite-sqlplanner-stat4-expression-partial-current-source-next182', $plan182()['dependencies'], true)),
    'planner stat4 expression partial current source next182 dependency closure' => static fn (TestRunner $t) => $t->contains('no new support component needed', $plan182()['dependency_closure']),
    'planner stat4 expression partial current source next182 non overlap' => static fn (TestRunner $t) => $t->contains('windows and projects', $plan182()['non_overlap']),
    'planner stat4 expression partial current source next182 negative limit' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => $plan182(-1, 0)),
    'planner stat4 expression partial current source next182 negative offset' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => $plan182(1, -1)),
    'planner stat4 expression partial current source next182 missing covering payload' => static function (TestRunner $t) use ($plan182): void {
        $t->throws(InvalidArgumentException::class, static fn () => $plan182(1, 0, null, null, null, ['missing_column']));
    },
    'planner stat4 expression partial current source next182 invalid projected column' => static function (TestRunner $t) use ($plan182): void {
        $t->throws(InvalidArgumentException::class, static fn () => $plan182(1, 0, null, null, null, ['']));
    },
];

foreach (range(1, 10) as $case) {
    $tests['planner stat4 expression partial current source next182 repeated window check ' . $case] = static function (TestRunner $t) use ($plan182, $case): void {
        $plan = $plan182(2 + ($case % 3), $case % 4);
        $t->same(count($plan['matchedRowids']), $plan['limitWindow']['count']);
    };
}

return $tests;

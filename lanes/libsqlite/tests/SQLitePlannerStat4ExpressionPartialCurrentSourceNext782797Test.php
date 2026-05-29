<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePlannerStat4ExpressionPartialCurrentSourceNext224Plan;

$eq782797 = static fn (string $column, mixed $right): array => ['left' => ['column' => $column], 'operator' => '=', 'right' => $right];
$like782797 = static fn (string $column, string $right): array => ['left' => ['column' => $column], 'operator' => 'LIKE', 'right' => $right];
$notNull782797 = static fn (string $column): array => ['left' => ['column' => $column], 'operator' => 'IS NOT NULL'];
$between782797 = static fn (string $expression, mixed $lower, mixed $upper): array => ['left' => ['expression' => $expression], 'operator' => 'BETWEEN', 'lower' => $lower, 'upper' => $upper];

$rows782797 = static fn (): array => [
    ['rowid' => 60, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_zulu', 'option_value' => 'zulu', 'updated_at' => 60],
    ['rowid' => 30, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_seo', 'option_value' => 'seo', 'updated_at' => 30],
    ['rowid' => 50, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'Plugin_Mail', 'option_value' => 'mail', 'updated_at' => 50],
    ['rowid' => 20, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_forms', 'option_value' => 'forms-anchor', 'updated_at' => 20],
    ['rowid' => 21, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'Plugin_Forms', 'option_value' => 'forms-copy-a', 'updated_at' => 21],
    ['rowid' => 22, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'PLUGIN_FORMS', 'option_value' => 'forms-copy-b', 'updated_at' => 22],
    ['rowid' => 70, 'blog_id' => 1, 'autoload' => 'no', 'option_name' => 'plugin_forms', 'option_value' => 'lazy', 'updated_at' => 70],
    ['rowid' => 80, 'blog_id' => 2, 'autoload' => 'yes', 'option_name' => 'plugin_forms', 'option_value' => 'other-blog', 'updated_at' => 80],
];

$samples782797 = static fn (): array => [
    ['neq' => '3 3', 'nlt' => '0 0', 'ndlt' => '0 0', 'sample' => ['plugin_forms', 20, 1]],
    ['neq' => '1 1', 'nlt' => '3 3', 'ndlt' => '1 1', 'sample' => ['plugin_mail', 50, 1]],
    ['neq' => '1 1', 'nlt' => '4 4', 'ndlt' => '2 2', 'sample' => ['plugin_seo', 30, 1]],
    ['neq' => '1 1', 'nlt' => '5 5', 'ndlt' => '3 3', 'sample' => ['plugin_zulu', 60, 1]],
];

$payload782797 = static fn (array $row): array => [
    'rowid' => $row['rowid'],
    'expressionKey' => strtolower((string) $row['option_name']),
    'coveredValues' => [
        'option_name' => $row['option_name'],
        'option_value' => $row['option_value'],
        'updated_at' => $row['updated_at'],
        'blog_id' => $row['blog_id'],
        'autoload' => $row['autoload'],
    ],
];

$prepared782797 = static fn (): array => [
    'name' => 'prepared-wp-options-stat4-handoff-next782797',
    'schemaCookie' => 3868,
    'stat4Generation' => 382,
    'rows' => [
        ['rowid' => 20, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_forms', 'option_value' => 'forms-old', 'updated_at' => 20],
        ['rowid' => 30, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_seo', 'option_value' => 'seo-old', 'updated_at' => 30],
        ['rowid' => 60, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_zulu', 'option_value' => 'zulu-old', 'updated_at' => 60],
    ],
    'indexes' => [[
        'name' => 'idx_wp_options_lower_handoff_next782797',
        'rootPage' => 38681,
        'expression' => 'lower(option_name)',
        'expressionColumn' => '__expr_lower_option_name',
        'collation' => 'BINARY',
        'descending' => true,
        'partialPredicateTerms' => [
            ['left' => ['expression' => 'lower(option_name)'], 'operator' => '>=', 'right' => 'plugin_forms'],
            ['left' => ['expression' => 'lower(option_name)'], 'operator' => '<=', 'right' => 'plugin_zulu'],
            ['left' => ['column' => 'autoload'], 'operator' => '=', 'right' => 'yes'],
            ['left' => ['column' => 'option_name'], 'operator' => 'IS NOT NULL'],
        ],
        'partialGroupedOrPredicateArms' => [[
            ['left' => ['column' => 'blog_id'], 'operator' => '=', 'right' => 1],
            ['left' => ['column' => 'autoload'], 'operator' => '=', 'right' => 'yes'],
        ]],
        'partialGroupedLikePredicateArms' => [[
            ['left' => ['column' => 'blog_id'], 'operator' => '=', 'right' => 1],
            ['left' => ['column' => 'option_name'], 'operator' => 'LIKE', 'right' => 'plugin_%'],
        ]],
        'coveringColumns' => ['option_name', 'option_value', 'updated_at', 'autoload', 'blog_id'],
        'stat4ExpressionPayloads' => [],
        'stat4Samples' => [
            ['neq' => '1 1', 'nlt' => '0 0', 'ndlt' => '0 0', 'sample' => ['plugin_forms', 20, 1]],
            ['neq' => '1 1', 'nlt' => '1 1', 'ndlt' => '1 1', 'sample' => ['plugin_seo', 30, 1]],
            ['neq' => '1 1', 'nlt' => '2 2', 'ndlt' => '2 2', 'sample' => ['plugin_zulu', 60, 1]],
        ],
    ]],
];

$current782797 = static function (?array $rows = null, ?array $samples = null) use ($prepared782797, $rows782797, $samples782797, $payload782797): array {
    $source = $prepared782797();
    $source['name'] = 'current-wp-options-stat4-handoff-next782797';
    $source['schemaCookie'] = 4018;
    $source['stat4Generation'] = 950;
    $source['rows'] = $rows ?? $rows782797();
    $source['indexes'][0]['rootPage'] = 40188;
    $source['indexes'][0]['stat1'] = ['rows' => '6 2 1'];
    $source['indexes'][0]['stat4Samples'] = $samples ?? $samples782797();
    $source['indexes'][0]['stat4ExpressionPayloads'] = array_map($payload782797, $source['rows']);

    return $source;
};

$terms782797 = static fn (): array => [
    $between782797('LOWER(option_name)', 'plugin_forms', 'plugin_zulu'),
    $eq782797('autoload', 'yes'),
    $notNull782797('option_name'),
    $eq782797('blog_id', 1),
    $like782797('option_name', 'plugin_%'),
];

$plan782797 = static fn (?array $rows = null, ?array $samples = null): array => SQLitePlannerStat4ExpressionPartialCurrentSourceNext224Plan::materializeNext782797(
    $prepared782797(),
    $current782797($rows, $samples),
    $terms782797(),
    ['option_name', 'option_value', 'updated_at', 'blog_id'],
    6,
    0,
);

$tests = [
    'planner stat4 expression partial current source next782797 status prepared' => static fn (TestRunner $t) => $t->same('stat4-expression-partial-current-source-next782-797-prepared', $plan782797()['status']),
    'planner stat4 expression partial current source next782797 inherits next766781' => static fn (TestRunner $t) => $t->same(true, $plan782797()['selectedPlan']['next766781Prepared']),
    'planner stat4 expression partial current source next782797 ready flag' => static fn (TestRunner $t) => $t->same(true, $plan782797()['selectedPlan']['next782797Prepared']),
    'planner stat4 expression partial current source next782797 prior ready' => static fn (TestRunner $t) => $t->same(true, $plan782797()['stat4Next782797PreparationFence']['previousFenceReady']),
    'planner stat4 expression partial current source next782797 slice range' => static fn (TestRunner $t) => $t->same([782, 797], $plan782797()['stat4Next782797PreparationFence']['sliceRange']),
    'planner stat4 expression partial current source next782797 prior range' => static fn (TestRunner $t) => $t->same([766, 781], $plan782797()['stat4Next782797PreparationFence']['priorSliceRange']),
    'planner stat4 expression partial current source next782797 prepared slices' => static fn (TestRunner $t) => $t->same(range(782, 797), $plan782797()['selectedPlan']['next782797PreparedSlices']),
    'planner stat4 expression partial current source next782797 blocked slices empty' => static fn (TestRunner $t) => $t->same([], $plan782797()['selectedPlan']['next782797BlockedSlices']),
    'planner stat4 expression partial current source next782797 first continues' => static fn (TestRunner $t) => $t->same(766, $plan782797()['stat4Next782797PreparationFence']['handoffWindows'][0]['continuesSlice']),
    'planner stat4 expression partial current source next782797 first rowid' => static fn (TestRunner $t) => $t->same(60, $plan782797()['stat4Next782797PreparationFence']['handoffWindows'][0]['rowid']),
    'planner stat4 expression partial current source next782797 first projection matches' => static fn (TestRunner $t) => $t->same(true, $plan782797()['stat4Next782797PreparationFence']['handoffWindows'][0]['projectionMatchesPrior']),
    'planner stat4 expression partial current source next782797 signature length' => static fn (TestRunner $t) => $t->same(64, strlen($plan782797()['stat4Next782797PreparationFence']['handoffSignature'])),
    'planner stat4 expression partial current source next782797 selected signature' => static fn (TestRunner $t) => $t->same($plan782797()['stat4Next782797PreparationFence']['handoffSignature'], $plan782797()['selectedPlan']['next782797HandoffSignature']),
    'planner stat4 expression partial current source next782797 stat4 signature' => static fn (TestRunner $t) => $t->same($plan782797()['stat4Next782797PreparationFence']['handoffSignature'], $plan782797()['stat4Fence']['next782797HandoffSignature']),
    'planner stat4 expression partial current source next782797 prior signature threaded' => static fn (TestRunner $t) => $t->same($plan782797()['stat4Next766781PreparationFence']['handoffSignature'], $plan782797()['selectedPlan']['next782797PriorHandoffSignature']),
    'planner stat4 expression partial current source next782797 preserves next766781 fence' => static fn (TestRunner $t) => $t->same(range(766, 781), $plan782797()['stat4Next766781PreparationFence']['preparedSlices']),
    'planner stat4 expression partial current source next782797 cursor appended' => static fn (TestRunner $t) => $t->same('PrepareStat4ExpressionPartialNext782797Handoff', $plan782797()['cursorProgram'][array_key_last($plan782797()['cursorProgram'])]['opcode']),
    'planner stat4 expression partial current source next782797 cursor mode' => static fn (TestRunner $t) => $t->same('next782-797-current-source-stat4-expression-partial-prep', $plan782797()['cursorProgram'][array_key_last($plan782797()['cursorProgram'])]['mode']),
    'planner stat4 expression partial current source next782797 detail' => static fn (TestRunner $t) => $t->contains('NEXT782-797 PREPARED HANDOFF', $plan782797()['detail']),
    'planner stat4 expression partial current source next782797 dependency marker' => static fn (TestRunner $t) => $t->true(in_array('sqlite-sqlplanner-stat4-expression-partial-current-source-next782-797-prep', $plan782797()['dependencies'], true)),
    'planner stat4 expression partial current source next782797 dependency closure' => static fn (TestRunner $t) => $t->contains('next782-797 preparation extends', $plan782797()['dependency_closure']),
    'planner stat4 expression partial current source next782797 non overlap' => static fn (TestRunner $t) => $t->contains('next766-781 handoff windows', $plan782797()['non_overlap']),
    'planner stat4 expression partial current source next782797 malformed needed column' => static function (TestRunner $t) use ($prepared782797, $current782797, $terms782797): void {
        $t->throws(InvalidArgumentException::class, static fn () => SQLitePlannerStat4ExpressionPartialCurrentSourceNext224Plan::materializeNext782797($prepared782797(), $current782797(), $terms782797(), ['option_name', ''], 6));
    },
];

foreach (range(1, 16) as $case) {
    $tests['planner stat4 expression partial current source next782797 repeated handoff proof ' . $case] = static function (TestRunner $t) use ($plan782797): void {
        $plan = $plan782797();
        $t->same($plan['stat4Next782797PreparationFence']['handoffSignature'], $plan['selectedPlan']['next782797HandoffSignature']);
    };
}

return $tests;

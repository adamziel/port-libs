<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePlannerStat4ExpressionPartialCurrentSourceNext224Plan;

$eq398413 = static fn (string $column, mixed $right): array => ['left' => ['column' => $column], 'operator' => '=', 'right' => $right];
$like398413 = static fn (string $column, string $right): array => ['left' => ['column' => $column], 'operator' => 'LIKE', 'right' => $right];
$notNull398413 = static fn (string $column): array => ['left' => ['column' => $column], 'operator' => 'IS NOT NULL'];
$between398413 = static fn (string $expression, mixed $lower, mixed $upper): array => ['left' => ['expression' => $expression], 'operator' => 'BETWEEN', 'lower' => $lower, 'upper' => $upper];

$rows398413 = static fn (): array => [
    ['rowid' => 60, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_zulu', 'option_value' => 'zulu', 'updated_at' => 60],
    ['rowid' => 30, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_seo', 'option_value' => 'seo', 'updated_at' => 30],
    ['rowid' => 50, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'Plugin_Mail', 'option_value' => 'mail', 'updated_at' => 50],
    ['rowid' => 20, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_forms', 'option_value' => 'forms-anchor', 'updated_at' => 20],
    ['rowid' => 21, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'Plugin_Forms', 'option_value' => 'forms-copy-a', 'updated_at' => 21],
    ['rowid' => 22, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'PLUGIN_FORMS', 'option_value' => 'forms-copy-b', 'updated_at' => 22],
    ['rowid' => 70, 'blog_id' => 1, 'autoload' => 'no', 'option_name' => 'plugin_forms', 'option_value' => 'lazy', 'updated_at' => 70],
    ['rowid' => 80, 'blog_id' => 2, 'autoload' => 'yes', 'option_name' => 'plugin_forms', 'option_value' => 'other-blog', 'updated_at' => 80],
];

$samples398413 = static fn (): array => [
    ['neq' => '3 3', 'nlt' => '0 0', 'ndlt' => '0 0', 'sample' => ['plugin_forms', 20, 1]],
    ['neq' => '1 1', 'nlt' => '3 3', 'ndlt' => '1 1', 'sample' => ['plugin_mail', 50, 1]],
    ['neq' => '1 1', 'nlt' => '4 4', 'ndlt' => '2 2', 'sample' => ['plugin_seo', 30, 1]],
    ['neq' => '1 1', 'nlt' => '5 5', 'ndlt' => '3 3', 'sample' => ['plugin_zulu', 60, 1]],
];

$payload398413 = static fn (array $row): array => [
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

$prepared398413 = static fn (): array => [
    'name' => 'prepared-wp-options-stat4-handoff-next398413',
    'schemaCookie' => 3820,
    'stat4Generation' => 334,
    'rows' => [
        ['rowid' => 20, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_forms', 'option_value' => 'forms-old', 'updated_at' => 20],
        ['rowid' => 30, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_seo', 'option_value' => 'seo-old', 'updated_at' => 30],
        ['rowid' => 60, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_zulu', 'option_value' => 'zulu-old', 'updated_at' => 60],
    ],
    'indexes' => [[
        'name' => 'idx_wp_options_lower_handoff_next398413',
        'rootPage' => 38201,
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

$current398413 = static function (?array $rows = null, ?array $samples = null) use ($prepared398413, $rows398413, $samples398413, $payload398413): array {
    $source = $prepared398413();
    $source['name'] = 'current-wp-options-stat4-handoff-next398413';
    $source['schemaCookie'] = 3970;
    $source['stat4Generation'] = 902;
    $source['rows'] = $rows ?? $rows398413();
    $source['indexes'][0]['rootPage'] = 39708;
    $source['indexes'][0]['stat1'] = ['rows' => '6 2 1'];
    $source['indexes'][0]['stat4Samples'] = $samples ?? $samples398413();
    $source['indexes'][0]['stat4ExpressionPayloads'] = array_map($payload398413, $source['rows']);

    return $source;
};

$terms398413 = static fn (): array => [
    $between398413('LOWER(option_name)', 'plugin_forms', 'plugin_zulu'),
    $eq398413('autoload', 'yes'),
    $notNull398413('option_name'),
    $eq398413('blog_id', 1),
    $like398413('option_name', 'plugin_%'),
];

$plan398413 = static fn (?array $rows = null, ?array $samples = null): array => SQLitePlannerStat4ExpressionPartialCurrentSourceNext224Plan::materializeNext398413(
    $prepared398413(),
    $current398413($rows, $samples),
    $terms398413(),
    ['option_name', 'option_value', 'updated_at', 'blog_id'],
    6,
    0,
);

$tests = [
    'planner stat4 expression partial current source next398413 status prepared' => static fn (TestRunner $t) => $t->same('stat4-expression-partial-current-source-next398-413-prepared', $plan398413()['status']),
    'planner stat4 expression partial current source next398413 inherits next382397' => static fn (TestRunner $t) => $t->same(true, $plan398413()['selectedPlan']['next382397Prepared']),
    'planner stat4 expression partial current source next398413 ready flag' => static fn (TestRunner $t) => $t->same(true, $plan398413()['selectedPlan']['next398413Prepared']),
    'planner stat4 expression partial current source next398413 prior ready' => static fn (TestRunner $t) => $t->same(true, $plan398413()['stat4Next398413PreparationFence']['previousFenceReady']),
    'planner stat4 expression partial current source next398413 slice range' => static fn (TestRunner $t) => $t->same([398, 413], $plan398413()['stat4Next398413PreparationFence']['sliceRange']),
    'planner stat4 expression partial current source next398413 prior range' => static fn (TestRunner $t) => $t->same([382, 397], $plan398413()['stat4Next398413PreparationFence']['priorSliceRange']),
    'planner stat4 expression partial current source next398413 prepared slices' => static fn (TestRunner $t) => $t->same(range(398, 413), $plan398413()['selectedPlan']['next398413PreparedSlices']),
    'planner stat4 expression partial current source next398413 blocked slices empty' => static fn (TestRunner $t) => $t->same([], $plan398413()['selectedPlan']['next398413BlockedSlices']),
    'planner stat4 expression partial current source next398413 first continues' => static fn (TestRunner $t) => $t->same(382, $plan398413()['stat4Next398413PreparationFence']['handoffWindows'][0]['continuesSlice']),
    'planner stat4 expression partial current source next398413 first rowid' => static fn (TestRunner $t) => $t->same(60, $plan398413()['stat4Next398413PreparationFence']['handoffWindows'][0]['rowid']),
    'planner stat4 expression partial current source next398413 first projection matches' => static fn (TestRunner $t) => $t->same(true, $plan398413()['stat4Next398413PreparationFence']['handoffWindows'][0]['projectionMatchesPrior']),
    'planner stat4 expression partial current source next398413 signature length' => static fn (TestRunner $t) => $t->same(64, strlen($plan398413()['stat4Next398413PreparationFence']['handoffSignature'])),
    'planner stat4 expression partial current source next398413 selected signature' => static fn (TestRunner $t) => $t->same($plan398413()['stat4Next398413PreparationFence']['handoffSignature'], $plan398413()['selectedPlan']['next398413HandoffSignature']),
    'planner stat4 expression partial current source next398413 stat4 signature' => static fn (TestRunner $t) => $t->same($plan398413()['stat4Next398413PreparationFence']['handoffSignature'], $plan398413()['stat4Fence']['next398413HandoffSignature']),
    'planner stat4 expression partial current source next398413 prior signature threaded' => static fn (TestRunner $t) => $t->same($plan398413()['stat4Next382397PreparationFence']['handoffSignature'], $plan398413()['selectedPlan']['next398413PriorHandoffSignature']),
    'planner stat4 expression partial current source next398413 cursor appended' => static fn (TestRunner $t) => $t->same('PrepareStat4ExpressionPartialNext398413Handoff', $plan398413()['cursorProgram'][array_key_last($plan398413()['cursorProgram'])]['opcode']),
    'planner stat4 expression partial current source next398413 cursor mode' => static fn (TestRunner $t) => $t->same('next398-413-current-source-stat4-expression-partial-prep', $plan398413()['cursorProgram'][array_key_last($plan398413()['cursorProgram'])]['mode']),
    'planner stat4 expression partial current source next398413 detail' => static fn (TestRunner $t) => $t->contains('NEXT398-413 PREPARED HANDOFF', $plan398413()['detail']),
    'planner stat4 expression partial current source next398413 dependency marker' => static fn (TestRunner $t) => $t->true(in_array('sqlite-sqlplanner-stat4-expression-partial-current-source-next398-413-prep', $plan398413()['dependencies'], true)),
    'planner stat4 expression partial current source next398413 dependency closure' => static fn (TestRunner $t) => $t->contains('next398-413 preparation extends', $plan398413()['dependency_closure']),
    'planner stat4 expression partial current source next398413 non overlap' => static fn (TestRunner $t) => $t->contains('next382-397 handoff windows', $plan398413()['non_overlap']),
    'planner stat4 expression partial current source next398413 malformed needed column' => static function (TestRunner $t) use ($prepared398413, $current398413, $terms398413): void {
        $t->throws(InvalidArgumentException::class, static fn () => SQLitePlannerStat4ExpressionPartialCurrentSourceNext224Plan::materializeNext398413($prepared398413(), $current398413(), $terms398413(), ['option_name', ''], 6));
    },
];

foreach (range(1, 16) as $case) {
    $tests['planner stat4 expression partial current source next398413 repeated handoff proof ' . $case] = static function (TestRunner $t) use ($plan398413): void {
        $plan = $plan398413();
        $t->same($plan['stat4Next398413PreparationFence']['handoffSignature'], $plan['selectedPlan']['next398413HandoffSignature']);
    };
}

return $tests;

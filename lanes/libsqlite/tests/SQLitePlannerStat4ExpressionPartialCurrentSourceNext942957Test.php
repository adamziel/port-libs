<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePlannerStat4ExpressionPartialCurrentSourceNext224Plan;

$eq942957 = static fn (string $column, mixed $right): array => ['left' => ['column' => $column], 'operator' => '=', 'right' => $right];
$like942957 = static fn (string $column, string $right): array => ['left' => ['column' => $column], 'operator' => 'LIKE', 'right' => $right];
$notNull942957 = static fn (string $column): array => ['left' => ['column' => $column], 'operator' => 'IS NOT NULL'];
$between942957 = static fn (string $expression, mixed $lower, mixed $upper): array => ['left' => ['expression' => $expression], 'operator' => 'BETWEEN', 'lower' => $lower, 'upper' => $upper];

$rows942957 = static fn (): array => [
    ['rowid' => 60, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_zulu', 'option_value' => 'zulu', 'updated_at' => 60],
    ['rowid' => 30, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_seo', 'option_value' => 'seo', 'updated_at' => 30],
    ['rowid' => 50, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'Plugin_Mail', 'option_value' => 'mail', 'updated_at' => 50],
    ['rowid' => 20, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_forms', 'option_value' => 'forms-anchor', 'updated_at' => 20],
    ['rowid' => 21, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'Plugin_Forms', 'option_value' => 'forms-copy-a', 'updated_at' => 21],
    ['rowid' => 22, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'PLUGIN_FORMS', 'option_value' => 'forms-copy-b', 'updated_at' => 22],
    ['rowid' => 70, 'blog_id' => 1, 'autoload' => 'no', 'option_name' => 'plugin_forms', 'option_value' => 'lazy', 'updated_at' => 70],
    ['rowid' => 80, 'blog_id' => 2, 'autoload' => 'yes', 'option_name' => 'plugin_forms', 'option_value' => 'other-blog', 'updated_at' => 80],
];

$samples942957 = static fn (): array => [
    ['neq' => '3 3', 'nlt' => '0 0', 'ndlt' => '0 0', 'sample' => ['plugin_forms', 20, 1]],
    ['neq' => '1 1', 'nlt' => '3 3', 'ndlt' => '1 1', 'sample' => ['plugin_mail', 50, 1]],
    ['neq' => '1 1', 'nlt' => '4 4', 'ndlt' => '2 2', 'sample' => ['plugin_seo', 30, 1]],
    ['neq' => '1 1', 'nlt' => '5 5', 'ndlt' => '3 3', 'sample' => ['plugin_zulu', 60, 1]],
];

$payload942957 = static fn (array $row): array => [
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

$prepared942957 = static fn (): array => [
    'name' => 'prepared-wp-options-stat4-handoff-next942957',
    'schemaCookie' => 3920,
    'stat4Generation' => 398,
    'rows' => [
        ['rowid' => 20, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_forms', 'option_value' => 'forms-old', 'updated_at' => 20],
        ['rowid' => 30, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_seo', 'option_value' => 'seo-old', 'updated_at' => 30],
        ['rowid' => 60, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_zulu', 'option_value' => 'zulu-old', 'updated_at' => 60],
    ],
    'indexes' => [[
        'name' => 'idx_wp_options_lower_handoff_next942957',
        'rootPage' => 39201,
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

$current942957 = static function (?array $rows = null, ?array $samples = null) use ($prepared942957, $rows942957, $samples942957, $payload942957): array {
    $source = $prepared942957();
    $source['name'] = 'current-wp-options-stat4-handoff-next942957';
    $source['schemaCookie'] = 4070;
    $source['stat4Generation'] = 966;
    $source['rows'] = $rows ?? $rows942957();
    $source['indexes'][0]['rootPage'] = 40708;
    $source['indexes'][0]['stat1'] = ['rows' => '6 2 1'];
    $source['indexes'][0]['stat4Samples'] = $samples ?? $samples942957();
    $source['indexes'][0]['stat4ExpressionPayloads'] = array_map($payload942957, $source['rows']);

    return $source;
};

$terms942957 = static fn (): array => [
    $between942957('LOWER(option_name)', 'plugin_forms', 'plugin_zulu'),
    $eq942957('autoload', 'yes'),
    $notNull942957('option_name'),
    $eq942957('blog_id', 1),
    $like942957('option_name', 'plugin_%'),
];

$plan942957 = static fn (?array $rows = null, ?array $samples = null): array => SQLitePlannerStat4ExpressionPartialCurrentSourceNext224Plan::materializeNext942957(
    $prepared942957(),
    $current942957($rows, $samples),
    $terms942957(),
    ['option_name', 'option_value', 'updated_at', 'blog_id'],
    6,
    0,
);

$tests = [
    'planner stat4 expression partial current source next942957 status prepared' => static fn (TestRunner $t) => $t->same('stat4-expression-partial-current-source-next942-957-prepared', $plan942957()['status']),
    'planner stat4 expression partial current source next942957 inherits next926941' => static fn (TestRunner $t) => $t->same(true, $plan942957()['selectedPlan']['next926941Prepared']),
    'planner stat4 expression partial current source next942957 ready flag' => static fn (TestRunner $t) => $t->same(true, $plan942957()['selectedPlan']['next942957Prepared']),
    'planner stat4 expression partial current source next942957 prior ready' => static fn (TestRunner $t) => $t->same(true, $plan942957()['stat4Next942957PreparationFence']['previousFenceReady']),
    'planner stat4 expression partial current source next942957 slice range' => static fn (TestRunner $t) => $t->same([942, 957], $plan942957()['stat4Next942957PreparationFence']['sliceRange']),
    'planner stat4 expression partial current source next942957 prior range' => static fn (TestRunner $t) => $t->same([926, 941], $plan942957()['stat4Next942957PreparationFence']['priorSliceRange']),
    'planner stat4 expression partial current source next942957 prepared slices' => static fn (TestRunner $t) => $t->same(range(942, 957), $plan942957()['selectedPlan']['next942957PreparedSlices']),
    'planner stat4 expression partial current source next942957 blocked slices empty' => static fn (TestRunner $t) => $t->same([], $plan942957()['selectedPlan']['next942957BlockedSlices']),
    'planner stat4 expression partial current source next942957 first continues' => static fn (TestRunner $t) => $t->same(926, $plan942957()['stat4Next942957PreparationFence']['handoffWindows'][0]['continuesSlice']),
    'planner stat4 expression partial current source next942957 first rowid' => static fn (TestRunner $t) => $t->same(60, $plan942957()['stat4Next942957PreparationFence']['handoffWindows'][0]['rowid']),
    'planner stat4 expression partial current source next942957 first projection matches' => static fn (TestRunner $t) => $t->same(true, $plan942957()['stat4Next942957PreparationFence']['handoffWindows'][0]['projectionMatchesPrior']),
    'planner stat4 expression partial current source next942957 signature length' => static fn (TestRunner $t) => $t->same(64, strlen($plan942957()['stat4Next942957PreparationFence']['handoffSignature'])),
    'planner stat4 expression partial current source next942957 selected signature' => static fn (TestRunner $t) => $t->same($plan942957()['stat4Next942957PreparationFence']['handoffSignature'], $plan942957()['selectedPlan']['next942957HandoffSignature']),
    'planner stat4 expression partial current source next942957 stat4 signature' => static fn (TestRunner $t) => $t->same($plan942957()['stat4Next942957PreparationFence']['handoffSignature'], $plan942957()['stat4Fence']['next942957HandoffSignature']),
    'planner stat4 expression partial current source next942957 prior signature threaded' => static fn (TestRunner $t) => $t->same($plan942957()['stat4Next926941PreparationFence']['handoffSignature'], $plan942957()['selectedPlan']['next942957PriorHandoffSignature']),
    'planner stat4 expression partial current source next942957 preserves next926941 fence' => static fn (TestRunner $t) => $t->same(range(926, 941), $plan942957()['stat4Next926941PreparationFence']['preparedSlices']),
    'planner stat4 expression partial current source next942957 cursor appended' => static fn (TestRunner $t) => $t->same('PrepareStat4ExpressionPartialNext942957Handoff', $plan942957()['cursorProgram'][array_key_last($plan942957()['cursorProgram'])]['opcode']),
    'planner stat4 expression partial current source next942957 cursor mode' => static fn (TestRunner $t) => $t->same('next942-957-current-source-stat4-expression-partial-prep', $plan942957()['cursorProgram'][array_key_last($plan942957()['cursorProgram'])]['mode']),
    'planner stat4 expression partial current source next942957 detail' => static fn (TestRunner $t) => $t->contains('NEXT942-957 PREPARED HANDOFF', $plan942957()['detail']),
    'planner stat4 expression partial current source next942957 dependency marker' => static fn (TestRunner $t) => $t->true(in_array('sqlite-sqlplanner-stat4-expression-partial-current-source-next942-957-prep', $plan942957()['dependencies'], true)),
    'planner stat4 expression partial current source next942957 dependency closure' => static fn (TestRunner $t) => $t->contains('next942-957 preparation extends', $plan942957()['dependency_closure']),
    'planner stat4 expression partial current source next942957 non overlap' => static fn (TestRunner $t) => $t->contains('next926-941 handoff windows', $plan942957()['non_overlap']),
    'planner stat4 expression partial current source next942957 malformed needed column' => static function (TestRunner $t) use ($prepared942957, $current942957, $terms942957): void {
        $t->throws(InvalidArgumentException::class, static fn () => SQLitePlannerStat4ExpressionPartialCurrentSourceNext224Plan::materializeNext942957($prepared942957(), $current942957(), $terms942957(), ['option_name', ''], 6));
    },
];

foreach (range(1, 16) as $case) {
    $tests['planner stat4 expression partial current source next942957 repeated handoff proof ' . $case] = static function (TestRunner $t) use ($plan942957): void {
        $plan = $plan942957();
        $t->same($plan['stat4Next942957PreparationFence']['handoffSignature'], $plan['selectedPlan']['next942957HandoffSignature']);
    };
}

return $tests;

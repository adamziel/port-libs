<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan;

$eq494509 = static fn (string $column, mixed $right): array => ['left' => ['column' => $column], 'operator' => '=', 'right' => $right];
$like494509 = static fn (string $column, string $right): array => ['left' => ['column' => $column], 'operator' => 'LIKE', 'right' => $right];
$notNull494509 = static fn (string $column): array => ['left' => ['column' => $column], 'operator' => 'IS NOT NULL'];
$between494509 = static fn (string $expression, mixed $lower, mixed $upper): array => ['left' => ['expression' => $expression], 'operator' => 'BETWEEN', 'lower' => $lower, 'upper' => $upper];

$rows494509 = static fn (): array => [
    ['rowid' => 60, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_zulu', 'option_value' => 'zulu', 'updated_at' => 60],
    ['rowid' => 30, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_seo', 'option_value' => 'seo', 'updated_at' => 30],
    ['rowid' => 50, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'Plugin_Mail', 'option_value' => 'mail', 'updated_at' => 50],
    ['rowid' => 20, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_forms', 'option_value' => 'forms-anchor', 'updated_at' => 20],
    ['rowid' => 21, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'Plugin_Forms', 'option_value' => 'forms-copy-a', 'updated_at' => 21],
    ['rowid' => 22, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'PLUGIN_FORMS', 'option_value' => 'forms-copy-b', 'updated_at' => 22],
    ['rowid' => 70, 'blog_id' => 1, 'autoload' => 'no', 'option_name' => 'plugin_forms', 'option_value' => 'lazy', 'updated_at' => 70],
    ['rowid' => 80, 'blog_id' => 2, 'autoload' => 'yes', 'option_name' => 'plugin_forms', 'option_value' => 'other-blog', 'updated_at' => 80],
];

$samples494509 = static fn (): array => [
    ['neq' => '3 3', 'nlt' => '0 0', 'ndlt' => '0 0', 'sample' => ['plugin_forms', 20, 1]],
    ['neq' => '1 1', 'nlt' => '3 3', 'ndlt' => '1 1', 'sample' => ['plugin_mail', 50, 1]],
    ['neq' => '1 1', 'nlt' => '4 4', 'ndlt' => '2 2', 'sample' => ['plugin_seo', 30, 1]],
    ['neq' => '1 1', 'nlt' => '5 5', 'ndlt' => '3 3', 'sample' => ['plugin_zulu', 60, 1]],
];

$payload494509 = static fn (array $row): array => [
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

$prepared494509 = static fn (): array => [
    'name' => 'prepared-wp-options-stat4-handoff-next494509',
    'schemaCookie' => 3820,
    'stat4Generation' => 334,
    'rows' => [
        ['rowid' => 20, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_forms', 'option_value' => 'forms-old', 'updated_at' => 20],
        ['rowid' => 30, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_seo', 'option_value' => 'seo-old', 'updated_at' => 30],
        ['rowid' => 60, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_zulu', 'option_value' => 'zulu-old', 'updated_at' => 60],
    ],
    'indexes' => [[
        'name' => 'idx_wp_options_lower_handoff_next494509',
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

$current494509 = static function (?array $rows = null, ?array $samples = null) use ($prepared494509, $rows494509, $samples494509, $payload494509): array {
    $source = $prepared494509();
    $source['name'] = 'current-wp-options-stat4-handoff-next494509';
    $source['schemaCookie'] = 3970;
    $source['stat4Generation'] = 902;
    $source['rows'] = $rows ?? $rows494509();
    $source['indexes'][0]['rootPage'] = 39708;
    $source['indexes'][0]['stat1'] = ['rows' => '6 2 1'];
    $source['indexes'][0]['stat4Samples'] = $samples ?? $samples494509();
    $source['indexes'][0]['stat4ExpressionPayloads'] = array_map($payload494509, $source['rows']);

    return $source;
};

$terms494509 = static fn (): array => [
    $between494509('LOWER(option_name)', 'plugin_forms', 'plugin_zulu'),
    $eq494509('autoload', 'yes'),
    $notNull494509('option_name'),
    $eq494509('blog_id', 1),
    $like494509('option_name', 'plugin_%'),
];

$plan494509 = static fn (?array $rows = null, ?array $samples = null): array => SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan::materializeNext494509(
    $prepared494509(),
    $current494509($rows, $samples),
    $terms494509(),
    ['option_name', 'option_value', 'updated_at', 'blog_id'],
    6,
    0,
);

$tests = [
    'planner stat4 expression partial current source next494509 status prepared' => static fn (TestRunner $t) => $t->same('stat4-expression-partial-current-source-next494-509-prepared', $plan494509()['status']),
    'planner stat4 expression partial current source next494509 inherits next478493' => static fn (TestRunner $t) => $t->same(true, $plan494509()['selectedPlan']['next478493Prepared']),
    'planner stat4 expression partial current source next494509 ready flag' => static fn (TestRunner $t) => $t->same(true, $plan494509()['selectedPlan']['next494509Prepared']),
    'planner stat4 expression partial current source next494509 prior ready' => static fn (TestRunner $t) => $t->same(true, $plan494509()['stat4Next494509PreparationFence']['previousFenceReady']),
    'planner stat4 expression partial current source next494509 slice range' => static fn (TestRunner $t) => $t->same([494, 509], $plan494509()['stat4Next494509PreparationFence']['sliceRange']),
    'planner stat4 expression partial current source next494509 prior range' => static fn (TestRunner $t) => $t->same([478, 493], $plan494509()['stat4Next494509PreparationFence']['priorSliceRange']),
    'planner stat4 expression partial current source next494509 prepared slices' => static fn (TestRunner $t) => $t->same(range(494, 509), $plan494509()['selectedPlan']['next494509PreparedSlices']),
    'planner stat4 expression partial current source next494509 blocked slices empty' => static fn (TestRunner $t) => $t->same([], $plan494509()['selectedPlan']['next494509BlockedSlices']),
    'planner stat4 expression partial current source next494509 first continues' => static fn (TestRunner $t) => $t->same(478, $plan494509()['stat4Next494509PreparationFence']['handoffWindows'][0]['continuesSlice']),
    'planner stat4 expression partial current source next494509 first rowid' => static fn (TestRunner $t) => $t->same(60, $plan494509()['stat4Next494509PreparationFence']['handoffWindows'][0]['rowid']),
    'planner stat4 expression partial current source next494509 first projection matches' => static fn (TestRunner $t) => $t->same(true, $plan494509()['stat4Next494509PreparationFence']['handoffWindows'][0]['projectionMatchesPrior']),
    'planner stat4 expression partial current source next494509 signature length' => static fn (TestRunner $t) => $t->same(64, strlen($plan494509()['stat4Next494509PreparationFence']['handoffSignature'])),
    'planner stat4 expression partial current source next494509 selected signature' => static fn (TestRunner $t) => $t->same($plan494509()['stat4Next494509PreparationFence']['handoffSignature'], $plan494509()['selectedPlan']['next494509HandoffSignature']),
    'planner stat4 expression partial current source next494509 stat4 signature' => static fn (TestRunner $t) => $t->same($plan494509()['stat4Next494509PreparationFence']['handoffSignature'], $plan494509()['stat4Fence']['next494509HandoffSignature']),
    'planner stat4 expression partial current source next494509 prior signature threaded' => static fn (TestRunner $t) => $t->same($plan494509()['stat4Next478493PreparationFence']['handoffSignature'], $plan494509()['selectedPlan']['next494509PriorHandoffSignature']),
    'planner stat4 expression partial current source next494509 preserves next478493 fence' => static fn (TestRunner $t) => $t->same(range(478, 493), $plan494509()['stat4Next478493PreparationFence']['preparedSlices']),
    'planner stat4 expression partial current source next494509 cursor appended' => static fn (TestRunner $t) => $t->same('PrepareStat4ExpressionPartialNext494509Handoff', $plan494509()['cursorProgram'][array_key_last($plan494509()['cursorProgram'])]['opcode']),
    'planner stat4 expression partial current source next494509 cursor mode' => static fn (TestRunner $t) => $t->same('next494-509-current-source-stat4-expression-partial-prep', $plan494509()['cursorProgram'][array_key_last($plan494509()['cursorProgram'])]['mode']),
    'planner stat4 expression partial current source next494509 detail' => static fn (TestRunner $t) => $t->contains('NEXT494-509 PREPARED HANDOFF', $plan494509()['detail']),
    'planner stat4 expression partial current source next494509 dependency marker' => static fn (TestRunner $t) => $t->true(in_array('sqlite-sqlplanner-stat4-expression-partial-current-source-next494-509-prep', $plan494509()['dependencies'], true)),
    'planner stat4 expression partial current source next494509 dependency closure' => static fn (TestRunner $t) => $t->contains('next494-509 preparation extends', $plan494509()['dependency_closure']),
    'planner stat4 expression partial current source next494509 non overlap' => static fn (TestRunner $t) => $t->contains('next478-493 handoff windows', $plan494509()['non_overlap']),
    'planner stat4 expression partial current source next494509 malformed needed column' => static function (TestRunner $t) use ($prepared494509, $current494509, $terms494509): void {
        $t->throws(InvalidArgumentException::class, static fn () => SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan::materializeNext494509($prepared494509(), $current494509(), $terms494509(), ['option_name', ''], 6));
    },
];

foreach (range(1, 16) as $case) {
    $tests['planner stat4 expression partial current source next494509 repeated handoff proof ' . $case] = static function (TestRunner $t) use ($plan494509): void {
        $plan = $plan494509();
        $t->same($plan['stat4Next494509PreparationFence']['handoffSignature'], $plan['selectedPlan']['next494509HandoffSignature']);
    };
}

return $tests;

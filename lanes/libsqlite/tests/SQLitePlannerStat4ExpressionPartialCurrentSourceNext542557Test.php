<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan;

$eq542557 = static fn (string $column, mixed $right): array => ['left' => ['column' => $column], 'operator' => '=', 'right' => $right];
$like542557 = static fn (string $column, string $right): array => ['left' => ['column' => $column], 'operator' => 'LIKE', 'right' => $right];
$notNull542557 = static fn (string $column): array => ['left' => ['column' => $column], 'operator' => 'IS NOT NULL'];
$between542557 = static fn (string $expression, mixed $lower, mixed $upper): array => ['left' => ['expression' => $expression], 'operator' => 'BETWEEN', 'lower' => $lower, 'upper' => $upper];

$rows542557 = static fn (): array => [
    ['rowid' => 60, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_zulu', 'option_value' => 'zulu', 'updated_at' => 60],
    ['rowid' => 30, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_seo', 'option_value' => 'seo', 'updated_at' => 30],
    ['rowid' => 50, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'Plugin_Mail', 'option_value' => 'mail', 'updated_at' => 50],
    ['rowid' => 20, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_forms', 'option_value' => 'forms-anchor', 'updated_at' => 20],
    ['rowid' => 21, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'Plugin_Forms', 'option_value' => 'forms-copy-a', 'updated_at' => 21],
    ['rowid' => 22, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'PLUGIN_FORMS', 'option_value' => 'forms-copy-b', 'updated_at' => 22],
    ['rowid' => 70, 'blog_id' => 1, 'autoload' => 'no', 'option_name' => 'plugin_forms', 'option_value' => 'lazy', 'updated_at' => 70],
    ['rowid' => 80, 'blog_id' => 2, 'autoload' => 'yes', 'option_name' => 'plugin_forms', 'option_value' => 'other-blog', 'updated_at' => 80],
];

$samples542557 = static fn (): array => [
    ['neq' => '3 3', 'nlt' => '0 0', 'ndlt' => '0 0', 'sample' => ['plugin_forms', 20, 1]],
    ['neq' => '1 1', 'nlt' => '3 3', 'ndlt' => '1 1', 'sample' => ['plugin_mail', 50, 1]],
    ['neq' => '1 1', 'nlt' => '4 4', 'ndlt' => '2 2', 'sample' => ['plugin_seo', 30, 1]],
    ['neq' => '1 1', 'nlt' => '5 5', 'ndlt' => '3 3', 'sample' => ['plugin_zulu', 60, 1]],
];

$payload542557 = static fn (array $row): array => [
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

$prepared542557 = static fn (): array => [
    'name' => 'prepared-wp-options-stat4-handoff-next542557',
    'schemaCookie' => 3820,
    'stat4Generation' => 334,
    'rows' => [
        ['rowid' => 20, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_forms', 'option_value' => 'forms-old', 'updated_at' => 20],
        ['rowid' => 30, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_seo', 'option_value' => 'seo-old', 'updated_at' => 30],
        ['rowid' => 60, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_zulu', 'option_value' => 'zulu-old', 'updated_at' => 60],
    ],
    'indexes' => [[
        'name' => 'idx_wp_options_lower_handoff_next542557',
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

$current542557 = static function (?array $rows = null, ?array $samples = null) use ($prepared542557, $rows542557, $samples542557, $payload542557): array {
    $source = $prepared542557();
    $source['name'] = 'current-wp-options-stat4-handoff-next542557';
    $source['schemaCookie'] = 3970;
    $source['stat4Generation'] = 902;
    $source['rows'] = $rows ?? $rows542557();
    $source['indexes'][0]['rootPage'] = 39708;
    $source['indexes'][0]['stat1'] = ['rows' => '6 2 1'];
    $source['indexes'][0]['stat4Samples'] = $samples ?? $samples542557();
    $source['indexes'][0]['stat4ExpressionPayloads'] = array_map($payload542557, $source['rows']);

    return $source;
};

$terms542557 = static fn (): array => [
    $between542557('LOWER(option_name)', 'plugin_forms', 'plugin_zulu'),
    $eq542557('autoload', 'yes'),
    $notNull542557('option_name'),
    $eq542557('blog_id', 1),
    $like542557('option_name', 'plugin_%'),
];

$plan542557 = static fn (?array $rows = null, ?array $samples = null): array => SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan::materializeNext542557(
    $prepared542557(),
    $current542557($rows, $samples),
    $terms542557(),
    ['option_name', 'option_value', 'updated_at', 'blog_id'],
    6,
    0,
);

$tests = [
    'planner stat4 expression partial current source next542557 status prepared' => static fn (TestRunner $t) => $t->same('stat4-expression-partial-current-source-next542-557-prepared', $plan542557()['status']),
    'planner stat4 expression partial current source next542557 inherits next526541' => static fn (TestRunner $t) => $t->same(true, $plan542557()['selectedPlan']['next526541Prepared']),
    'planner stat4 expression partial current source next542557 ready flag' => static fn (TestRunner $t) => $t->same(true, $plan542557()['selectedPlan']['next542557Prepared']),
    'planner stat4 expression partial current source next542557 prior ready' => static fn (TestRunner $t) => $t->same(true, $plan542557()['stat4Next542557PreparationFence']['previousFenceReady']),
    'planner stat4 expression partial current source next542557 slice range' => static fn (TestRunner $t) => $t->same([542, 557], $plan542557()['stat4Next542557PreparationFence']['sliceRange']),
    'planner stat4 expression partial current source next542557 prior range' => static fn (TestRunner $t) => $t->same([526, 541], $plan542557()['stat4Next542557PreparationFence']['priorSliceRange']),
    'planner stat4 expression partial current source next542557 prepared slices' => static fn (TestRunner $t) => $t->same(range(542, 557), $plan542557()['selectedPlan']['next542557PreparedSlices']),
    'planner stat4 expression partial current source next542557 blocked slices empty' => static fn (TestRunner $t) => $t->same([], $plan542557()['selectedPlan']['next542557BlockedSlices']),
    'planner stat4 expression partial current source next542557 first continues' => static fn (TestRunner $t) => $t->same(526, $plan542557()['stat4Next542557PreparationFence']['handoffWindows'][0]['continuesSlice']),
    'planner stat4 expression partial current source next542557 first rowid' => static fn (TestRunner $t) => $t->same(60, $plan542557()['stat4Next542557PreparationFence']['handoffWindows'][0]['rowid']),
    'planner stat4 expression partial current source next542557 first projection matches' => static fn (TestRunner $t) => $t->same(true, $plan542557()['stat4Next542557PreparationFence']['handoffWindows'][0]['projectionMatchesPrior']),
    'planner stat4 expression partial current source next542557 signature length' => static fn (TestRunner $t) => $t->same(64, strlen($plan542557()['stat4Next542557PreparationFence']['handoffSignature'])),
    'planner stat4 expression partial current source next542557 selected signature' => static fn (TestRunner $t) => $t->same($plan542557()['stat4Next542557PreparationFence']['handoffSignature'], $plan542557()['selectedPlan']['next542557HandoffSignature']),
    'planner stat4 expression partial current source next542557 stat4 signature' => static fn (TestRunner $t) => $t->same($plan542557()['stat4Next542557PreparationFence']['handoffSignature'], $plan542557()['stat4Fence']['next542557HandoffSignature']),
    'planner stat4 expression partial current source next542557 prior signature threaded' => static fn (TestRunner $t) => $t->same($plan542557()['stat4Next526541PreparationFence']['handoffSignature'], $plan542557()['selectedPlan']['next542557PriorHandoffSignature']),
    'planner stat4 expression partial current source next542557 preserves next526541 fence' => static fn (TestRunner $t) => $t->same(range(526, 541), $plan542557()['stat4Next526541PreparationFence']['preparedSlices']),
    'planner stat4 expression partial current source next542557 cursor appended' => static fn (TestRunner $t) => $t->same('PrepareStat4ExpressionPartialNext542557Handoff', $plan542557()['cursorProgram'][array_key_last($plan542557()['cursorProgram'])]['opcode']),
    'planner stat4 expression partial current source next542557 cursor mode' => static fn (TestRunner $t) => $t->same('next542-557-current-source-stat4-expression-partial-prep', $plan542557()['cursorProgram'][array_key_last($plan542557()['cursorProgram'])]['mode']),
    'planner stat4 expression partial current source next542557 detail' => static fn (TestRunner $t) => $t->contains('NEXT542-557 PREPARED HANDOFF', $plan542557()['detail']),
    'planner stat4 expression partial current source next542557 dependency marker' => static fn (TestRunner $t) => $t->true(in_array('sqlite-sqlplanner-stat4-expression-partial-current-source-next542-557-prep', $plan542557()['dependencies'], true)),
    'planner stat4 expression partial current source next542557 dependency closure' => static fn (TestRunner $t) => $t->contains('next542-557 preparation extends', $plan542557()['dependency_closure']),
    'planner stat4 expression partial current source next542557 non overlap' => static fn (TestRunner $t) => $t->contains('next526-541 handoff windows', $plan542557()['non_overlap']),
    'planner stat4 expression partial current source next542557 malformed needed column' => static function (TestRunner $t) use ($prepared542557, $current542557, $terms542557): void {
        $t->throws(InvalidArgumentException::class, static fn () => SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan::materializeNext542557($prepared542557(), $current542557(), $terms542557(), ['option_name', ''], 6));
    },
];

foreach (range(1, 16) as $case) {
    $tests['planner stat4 expression partial current source next542557 repeated handoff proof ' . $case] = static function (TestRunner $t) use ($plan542557): void {
        $plan = $plan542557();
        $t->same($plan['stat4Next542557PreparationFence']['handoffSignature'], $plan['selectedPlan']['next542557HandoffSignature']);
    };
}

return $tests;

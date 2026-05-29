<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePlannerStat4ExpressionPartialCurrentSourceNext224Plan;

$eq254269 = static fn (string $column, mixed $right): array => ['left' => ['column' => $column], 'operator' => '=', 'right' => $right];
$like254269 = static fn (string $column, string $right): array => ['left' => ['column' => $column], 'operator' => 'LIKE', 'right' => $right];
$notNull254269 = static fn (string $column): array => ['left' => ['column' => $column], 'operator' => 'IS NOT NULL'];
$between254269 = static fn (string $expression, mixed $lower, mixed $upper): array => ['left' => ['expression' => $expression], 'operator' => 'BETWEEN', 'lower' => $lower, 'upper' => $upper];

$payload254269 = static fn (array $row): array => [
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

$prepared254269 = static fn (): array => [
    'name' => 'prepared-wp-options-stat4-next254269',
    'schemaCookie' => 2540,
    'stat4Generation' => 254,
    'rows' => [
        ['rowid' => 10, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_alpha', 'option_value' => 'alpha-old', 'updated_at' => 10],
        ['rowid' => 20, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_forms', 'option_value' => 'forms-old', 'updated_at' => 20],
        ['rowid' => 30, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_seo', 'option_value' => 'seo-old', 'updated_at' => 30],
    ],
    'indexes' => [[
        'name' => 'idx_wp_options_stat4_next254269',
        'rootPage' => 25401,
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
        'partialGroupedOrPredicateArms' => [[
            ['left' => ['column' => 'blog_id'], 'operator' => '=', 'right' => 1],
            ['left' => ['column' => 'autoload'], 'operator' => '=', 'right' => 'yes'],
        ]],
        'partialGroupedLikePredicateArms' => [[
            ['left' => ['column' => 'blog_id'], 'operator' => '=', 'right' => 1],
            ['left' => ['column' => 'option_name'], 'operator' => 'LIKE', 'right' => 'plugin_%'],
        ]],
        'coveringColumns' => ['option_name', 'option_value', 'updated_at', 'autoload', 'blog_id'],
        'stat4Samples' => [
            ['neq' => '1 1', 'nlt' => '0 0', 'ndlt' => '0 0', 'sample' => ['plugin_alpha', 1, 10]],
            ['neq' => '1 1', 'nlt' => '1 1', 'ndlt' => '1 1', 'sample' => ['plugin_cache', 1, 40]],
            ['neq' => '3 3', 'nlt' => '2 2', 'ndlt' => '2 2', 'sample' => ['plugin_forms', 1, 20]],
            ['neq' => '1 1', 'nlt' => '5 5', 'ndlt' => '3 3', 'sample' => ['plugin_mail', 1, 50]],
            ['neq' => '1 1', 'nlt' => '6 6', 'ndlt' => '4 4', 'sample' => ['plugin_seo', 1, 30]],
            ['neq' => '1 1', 'nlt' => '7 7', 'ndlt' => '5 5', 'sample' => ['plugin_zulu', 1, 60]],
        ],
        'stat4ExpressionPayloads' => [],
    ]],
];

$current254269 = static function (string $variant = 'ready') use ($prepared254269, $payload254269): array {
    $source = $prepared254269();
    $source['name'] = 'current-wp-options-stat4-next254269';
    $source['schemaCookie'] = 2699;
    $source['stat4Generation'] = 1269;
    $source['indexes'][0]['rootPage'] = 26988;
    $source['rows'] = [
        ['rowid' => 60, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_zulu', 'option_value' => 'zulu', 'updated_at' => 60],
        ['rowid' => 30, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_seo', 'option_value' => 'seo', 'updated_at' => 30],
        ['rowid' => 50, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'Plugin_Mail', 'option_value' => 'mail', 'updated_at' => 50],
        ['rowid' => 20, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_forms', 'option_value' => 'forms-anchor', 'updated_at' => 20],
        ['rowid' => 21, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'Plugin_Forms', 'option_value' => 'forms-copy-a', 'updated_at' => 21],
        ['rowid' => 22, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'PLUGIN_FORMS', 'option_value' => 'forms-copy-b', 'updated_at' => 22],
        ['rowid' => 40, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_cache', 'option_value' => 'cache', 'updated_at' => 40],
        ['rowid' => 10, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_alpha', 'option_value' => 'alpha', 'updated_at' => 10],
    ];
    $payloads = array_map($payload254269, $source['rows']);
    if ($variant === 'missing-payload') {
        $payloads = array_values(array_filter($payloads, static fn (array $payload): bool => $payload['rowid'] !== 21));
    }
    $source['indexes'][0]['stat4ExpressionPayloads'] = $payloads;

    return $source;
};

$terms254269 = static fn (): array => [
    $between254269('LOWER(option_name)', 'plugin_alpha', 'plugin_zulu'),
    $eq254269('autoload', 'yes'),
    $notNull254269('option_name'),
    $eq254269('blog_id', 1),
    $like254269('option_name', 'plugin_%'),
];

$plan254269 = static fn (string $variant = 'ready', array $needed = ['option_name', 'option_value', 'updated_at', 'blog_id']): array => SQLitePlannerStat4ExpressionPartialCurrentSourceNext224Plan::materializeNext254269(
    $prepared254269(),
    $current254269($variant),
    $terms254269(),
    $needed,
    5,
    1,
);

$tests = [
    'planner stat4 expression partial current source next254269 status prepared' => static fn (TestRunner $t) => $t->same('stat4-expression-partial-current-source-next254-269-prepared', $plan254269()['status']),
    'planner stat4 expression partial current source next254269 inherits payload fence' => static fn (TestRunner $t) => $t->same(true, $plan254269()['selectedPlan']['next253Ready']),
    'planner stat4 expression partial current source next254269 selected prepared' => static fn (TestRunner $t) => $t->same(true, $plan254269()['selectedPlan']['next254269Prepared']),
    'planner stat4 expression partial current source next254269 slice count' => static fn (TestRunner $t) => $t->same(16, $plan254269()['stat4Next254269PreparationFence']['sliceCount']),
    'planner stat4 expression partial current source next254269 range' => static fn (TestRunner $t) => $t->same([254, 269], $plan254269()['stat4Next254269PreparationFence']['sliceRange']),
    'planner stat4 expression partial current source next254269 all prepared' => static fn (TestRunner $t) => $t->same(true, $plan254269()['stat4Next254269PreparationFence']['allSlicesPrepared']),
    'planner stat4 expression partial current source next254269 no blocked slices' => static fn (TestRunner $t) => $t->same([], $plan254269()['stat4Next254269PreparationFence']['blockedSlices']),
    'planner stat4 expression partial current source next254269 prepared slices' => static fn (TestRunner $t) => $t->same(range(254, 269), $plan254269()['stat4Next254269PreparationFence']['preparedSlices']),
    'planner stat4 expression partial current source next254269 yielded rowids' => static fn (TestRunner $t) => $t->same([30, 50, 20, 21, 22], $plan254269()['stat4Next254269PreparationFence']['yieldedRowids']),
    'planner stat4 expression partial current source next254269 first window' => static fn (TestRunner $t) => $t->same(['slice' => 254, 'rowid' => 30], array_intersect_key($plan254269()['stat4Next254269PreparationFence']['handoffWindows'][0], ['slice' => true, 'rowid' => true])),
    'planner stat4 expression partial current source next254269 wraps yielded rowids' => static fn (TestRunner $t) => $t->same(30, $plan254269()['stat4Next254269PreparationFence']['handoffWindows'][15]['rowid']),
    'planner stat4 expression partial current source next254269 projected values' => static fn (TestRunner $t) => $t->same('forms-copy-a', $plan254269()['stat4Next254269PreparationFence']['handoffWindows'][3]['projectedColumns']['option_value']),
    'planner stat4 expression partial current source next254269 signature selected' => static fn (TestRunner $t) => $t->same($plan254269()['stat4Next254269PreparationFence']['handoffSignature'], $plan254269()['selectedPlan']['next254269HandoffSignature']),
    'planner stat4 expression partial current source next254269 signature length' => static fn (TestRunner $t) => $t->same(64, strlen($plan254269()['stat4Next254269PreparationFence']['handoffSignature'])),
    'planner stat4 expression partial current source next254269 cursor appended' => static fn (TestRunner $t) => $t->same('PrepareStat4ExpressionPartialNext254269Handoff', $plan254269()['cursorProgram'][array_key_last($plan254269()['cursorProgram'])]['opcode']),
    'planner stat4 expression partial current source next254269 cursor mode' => static fn (TestRunner $t) => $t->same('next254-269-current-source-stat4-expression-partial-prep', $plan254269()['cursorProgram'][array_key_last($plan254269()['cursorProgram'])]['mode']),
    'planner stat4 expression partial current source next254269 detail' => static fn (TestRunner $t) => $t->contains('NEXT254-269 PREPARED HANDOFF', $plan254269()['detail']),
    'planner stat4 expression partial current source next254269 dependency marker' => static fn (TestRunner $t) => $t->true(in_array('sqlite-sqlplanner-stat4-expression-partial-current-source-next254-269-prep', $plan254269()['dependencies'], true)),
    'planner stat4 expression partial current source next254269 non overlap' => static fn (TestRunner $t) => $t->contains('prepares next254-269 current-source handoff slices only', $plan254269()['non_overlap']),
    'planner stat4 expression partial current source next254269 blocked by inherited payload' => static fn (TestRunner $t) => $t->same('requires-current-source-stat4-next254-269-prep', $plan254269('missing-payload')['status']),
    'planner stat4 expression partial current source next254269 inherited missing payload rowid' => static fn (TestRunner $t) => $t->same([21], $plan254269('missing-payload')['stat4CurrentPayloadFence']['missingPayloadRowids']),
    'planner stat4 expression partial current source next254269 invalid columns' => static function (TestRunner $t) use ($plan254269): void {
        $t->throws(InvalidArgumentException::class, static fn () => $plan254269('ready', []));
    },
];

foreach (range(254, 269) as $slice) {
    $tests['planner stat4 expression partial current source next254269 generated slice ready ' . $slice] = static function (TestRunner $t) use ($plan254269, $slice): void {
        $window = $plan254269()['stat4Next254269PreparationFence']['handoffWindows'][$slice - 254];
        $t->same([$slice, true, true], [$window['slice'], $window['hasCurrentRow'], $window['prepared']]);
    };
}

return $tests;

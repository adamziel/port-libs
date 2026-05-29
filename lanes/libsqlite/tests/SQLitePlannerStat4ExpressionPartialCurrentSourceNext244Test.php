<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan;

$eq244 = static fn (string $column, mixed $right): array => ['left' => ['column' => $column], 'operator' => '=', 'right' => $right];
$like244 = static fn (string $column, string $right): array => ['left' => ['column' => $column], 'operator' => 'LIKE', 'right' => $right];
$notNull244 = static fn (string $column): array => ['left' => ['column' => $column], 'operator' => 'IS NOT NULL'];
$between244 = static fn (string $expression, mixed $lower, mixed $upper): array => ['left' => ['expression' => $expression], 'operator' => 'BETWEEN', 'lower' => $lower, 'upper' => $upper];

$payload244 = static fn (array $row): array => [
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

$prepared244 = static fn (): array => [
    'name' => 'prepared-wp-options-stat4-window-next244',
    'schemaCookie' => 2440,
    'stat4Generation' => 244,
    'rows' => [
        ['rowid' => 10, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_alpha', 'option_value' => 'alpha-old', 'updated_at' => 10],
        ['rowid' => 20, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_forms', 'option_value' => 'forms-old', 'updated_at' => 20],
        ['rowid' => 30, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_seo', 'option_value' => 'seo-old', 'updated_at' => 30],
    ],
    'indexes' => [[
        'name' => 'idx_wp_options_stat4_window_partial_next244',
        'rootPage' => 24401,
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
            ['neq' => '4 3', 'nlt' => '2 2', 'ndlt' => '2 2', 'sample' => ['plugin_forms', 1, 20]],
            ['neq' => '4 1', 'nlt' => '2 5', 'ndlt' => '2 3', 'sample' => ['plugin_forms', 2, 80]],
            ['neq' => '1 1', 'nlt' => '6 6', 'ndlt' => '3 4', 'sample' => ['plugin_mail', 1, 50]],
            ['neq' => '1 1', 'nlt' => '7 7', 'ndlt' => '4 5', 'sample' => ['plugin_seo', 1, 30]],
            ['neq' => '1 1', 'nlt' => '8 8', 'ndlt' => '5 6', 'sample' => ['plugin_zulu', 1, 60]],
        ],
        'stat4ExpressionPayloads' => [],
    ]],
];

$current244 = static function () use ($prepared244, $payload244): array {
    $source = $prepared244();
    $source['name'] = 'current-wp-options-stat4-window-next244';
    $source['schemaCookie'] = 2449;
    $source['stat4Generation'] = 744;
    $source['indexes'][0]['rootPage'] = 24488;
    $source['rows'] = [
        ['rowid' => 60, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_zulu', 'option_value' => 'zulu', 'updated_at' => 60],
        ['rowid' => 30, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_seo', 'option_value' => 'seo', 'updated_at' => 30],
        ['rowid' => 50, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'Plugin_Mail', 'option_value' => 'mail', 'updated_at' => 50],
        ['rowid' => 80, 'blog_id' => 2, 'autoload' => 'yes', 'option_name' => 'plugin_forms', 'option_value' => 'other-blog', 'updated_at' => 80],
        ['rowid' => 20, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_forms', 'option_value' => 'forms-anchor', 'updated_at' => 20],
        ['rowid' => 21, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'Plugin_Forms', 'option_value' => 'forms-copy-a', 'updated_at' => 21],
        ['rowid' => 22, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'PLUGIN_FORMS', 'option_value' => 'forms-copy-b', 'updated_at' => 22],
        ['rowid' => 40, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_cache', 'option_value' => 'cache', 'updated_at' => 40],
        ['rowid' => 10, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_alpha', 'option_value' => 'alpha', 'updated_at' => 10],
        ['rowid' => 70, 'blog_id' => 1, 'autoload' => 'no', 'option_name' => 'plugin_forms', 'option_value' => 'lazy', 'updated_at' => 70],
    ];
    $source['indexes'][0]['stat4ExpressionPayloads'] = array_map(
        $payload244,
        array_values(array_filter($source['rows'], static fn (array $row): bool => $row['autoload'] === 'yes')),
    );

    return $source;
};

$terms244 = static fn (): array => [
    $between244('LOWER(option_name)', 'plugin_alpha', 'plugin_zulu'),
    $eq244('autoload', 'yes'),
    $notNull244('option_name'),
    $eq244('blog_id', 1),
    $like244('option_name', 'plugin_%'),
];

$plan244 = static fn (int $limit = 5, int $offset = 1, ?array $prepared = null, ?array $current = null, ?array $terms = null, ?array $needed = null): array => SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan::materializeNext244(
    $prepared ?? $prepared244(),
    $current ?? $current244(),
    $terms ?? $terms244(),
    $needed ?? ['option_name', 'option_value', 'updated_at', 'blog_id'],
    $limit,
    $offset,
);

$ascPlan244 = static function () use ($current244, $plan244): array {
    $current = $current244();
    $current['indexes'][0]['descending'] = false;

    return $plan244(4, 2, null, $current);
};

$shiftedWindow244 = static function () use ($current244, $payload244, $plan244): array {
    $current = $current244();
    foreach ($current['rows'] as &$row) {
        if ($row['rowid'] === 40) {
            $row['option_name'] = 'plugin_tax';
        }
    }
    unset($row);
    $current['indexes'][0]['stat4ExpressionPayloads'] = array_map(
        $payload244,
        array_values(array_filter($current['rows'], static fn (array $row): bool => $row['autoload'] === 'yes')),
    );

    return $plan244(5, 1, null, $current);
};

$tests = [
    'planner stat4 expression partial current source next244 status ready' => static fn (TestRunner $t) => $t->same('stat4-expression-partial-current-source-next244-ready', $plan244()['status']),
    'planner stat4 expression partial current source next244 inherits residual ready' => static fn (TestRunner $t) => $t->same(true, $plan244()['selectedPlan']['next241Ready']),
    'planner stat4 expression partial current source next244 ready flag' => static fn (TestRunner $t) => $t->same(true, $plan244()['selectedPlan']['next244Ready']),
    'planner stat4 expression partial current source next244 selected current' => static fn (TestRunner $t) => $t->same('current', $plan244()['selectedSource']),
    'planner stat4 expression partial current source next244 selected index' => static fn (TestRunner $t) => $t->same('idx_wp_options_stat4_window_partial_next244', $plan244()['selectedPlan']['name']),
    'planner stat4 expression partial current source next244 ordered rowids' => static fn (TestRunner $t) => $t->same([60, 30, 50, 20, 21, 22, 40, 10], $plan244()['stat4CurrentWindowFence']['orderedCurrentRowids']),
    'planner stat4 expression partial current source next244 window rowids' => static fn (TestRunner $t) => $t->same([30, 50, 20, 21, 22], $plan244()['stat4CurrentWindowFence']['currentWindowRowids']),
    'planner stat4 expression partial current source next244 yielded rowids' => static fn (TestRunner $t) => $t->same([30, 50, 20, 21, 22], $plan244()['stat4CurrentWindowFence']['yieldedWindowRowids']),
    'planner stat4 expression partial current source next244 selected window rowids' => static fn (TestRunner $t) => $t->same([30, 50, 20, 21, 22], $plan244()['selectedPlan']['next244CurrentWindowRowids']),
    'planner stat4 expression partial current source next244 no mismatches' => static fn (TestRunner $t) => $t->same([], $plan244()['stat4CurrentWindowFence']['windowMismatchRowids']),
    'planner stat4 expression partial current source next244 no missing' => static fn (TestRunner $t) => $t->same([], $plan244()['stat4CurrentWindowFence']['missingWindowRowids']),
    'planner stat4 expression partial current source next244 no extra' => static fn (TestRunner $t) => $t->same([], $plan244()['stat4CurrentWindowFence']['extraWindowRowids']),
    'planner stat4 expression partial current source next244 matches current source' => static fn (TestRunner $t) => $t->same(true, $plan244()['stat4CurrentWindowFence']['windowMatchesCurrentSource']),
    'planner stat4 expression partial current source next244 current rows detail' => static fn (TestRunner $t) => $t->same(['plugin_seo', 'plugin_mail', 'plugin_forms', 'plugin_forms', 'plugin_forms'], array_column($plan244()['stat4CurrentWindowFence']['currentWindowRows'], 'expressionKey')),
    'planner stat4 expression partial current source next244 limit recorded' => static fn (TestRunner $t) => $t->same(5, $plan244()['stat4CurrentWindowFence']['limit']),
    'planner stat4 expression partial current source next244 offset recorded' => static fn (TestRunner $t) => $t->same(1, $plan244()['stat4CurrentWindowFence']['offset']),
    'planner stat4 expression partial current source next244 descending recorded' => static fn (TestRunner $t) => $t->same(true, $plan244()['stat4CurrentWindowFence']['descending']),
    'planner stat4 expression partial current source next244 signature lengths' => static fn (TestRunner $t) => $t->same([64, 64], [strlen($plan244()['stat4CurrentWindowFence']['currentWindowSignature']), strlen($plan244()['stat4CurrentWindowFence']['proofSignature'])]),
    'planner stat4 expression partial current source next244 selected proof signature' => static fn (TestRunner $t) => $t->same($plan244()['stat4CurrentWindowFence']['proofSignature'], $plan244()['selectedPlan']['next244ProofSignature']),
    'planner stat4 expression partial current source next244 stat4 window signature' => static fn (TestRunner $t) => $t->same($plan244()['stat4CurrentWindowFence']['currentWindowSignature'], $plan244()['stat4Fence']['next244CurrentWindowSignature']),
    'planner stat4 expression partial current source next244 stat4 proof signature' => static fn (TestRunner $t) => $t->same($plan244()['stat4CurrentWindowFence']['proofSignature'], $plan244()['stat4Fence']['next244ProofSignature']),
    'planner stat4 expression partial current source next244 cursor appended' => static fn (TestRunner $t) => $t->same('VerifyCurrentStat4LimitOffsetWindow', $plan244()['cursorProgram'][array_key_last($plan244()['cursorProgram'])]['opcode']),
    'planner stat4 expression partial current source next244 cursor mode' => static fn (TestRunner $t) => $t->same('next244-current-source-stat4-expression-partial-window', $plan244()['cursorProgram'][array_key_last($plan244()['cursorProgram'])]['mode']),
    'planner stat4 expression partial current source next244 cursor rowids' => static fn (TestRunner $t) => $t->same([30, 50, 20, 21, 22], $plan244()['cursorProgram'][array_key_last($plan244()['cursorProgram'])]['currentWindowRowids']),
    'planner stat4 expression partial current source next244 cursor signature' => static fn (TestRunner $t) => $t->same($plan244()['stat4CurrentWindowFence']['proofSignature'], $plan244()['cursorProgram'][array_key_last($plan244()['cursorProgram'])]['signature']),
    'planner stat4 expression partial current source next244 projected payload retained' => static fn (TestRunner $t) => $t->same('forms-copy-b', $plan244()['projectedRows'][4]['option_value']),
    'planner stat4 expression partial current source next244 dependency marker' => static fn (TestRunner $t) => $t->true(in_array('sqlite-sqlplanner-stat4-expression-partial-current-source-next244', $plan244()['dependencies'], true)),
    'planner stat4 expression partial current source next244 dependency closure' => static fn (TestRunner $t) => $t->contains('no new support component needed', $plan244()['dependency_closure']),
    'planner stat4 expression partial current source next244 non overlap' => static fn (TestRunner $t) => $t->contains('LIMIT/OFFSET window validation', $plan244()['non_overlap']),
    'planner stat4 expression partial current source next244 detail' => static fn (TestRunner $t) => $t->contains('NEXT244 WINDOW FENCE', $plan244()['detail']),
    'planner stat4 expression partial current source next244 ascending status blocked' => static fn (TestRunner $t) => $t->same('requires-current-source-stat4-window-reprepare', $ascPlan244()['status']),
    'planner stat4 expression partial current source next244 ascending order' => static fn (TestRunner $t) => $t->same([10, 40, 20, 21, 22, 50, 30, 60], $ascPlan244()['stat4CurrentWindowFence']['orderedCurrentRowids']),
    'planner stat4 expression partial current source next244 ascending window' => static fn (TestRunner $t) => $t->same([20, 21, 22, 50], $ascPlan244()['stat4CurrentWindowFence']['currentWindowRowids']),
    'planner stat4 expression partial current source next244 shifted current rowids' => static fn (TestRunner $t) => $t->same([40, 30, 50, 20, 21], $shiftedWindow244()['stat4CurrentWindowFence']['currentWindowRowids']),
    'planner stat4 expression partial current source next244 shifted payload still ready' => static fn (TestRunner $t) => $t->same('stat4-expression-partial-current-source-next244-ready', $shiftedWindow244()['status']),
    'planner stat4 expression partial current source next244 zero limit window' => static fn (TestRunner $t) => $t->same([], $plan244(0, 0)['stat4CurrentWindowFence']['currentWindowRowids']),
    'planner stat4 expression partial current source next244 tail offset window' => static fn (TestRunner $t) => $t->same([10], $plan244(5, 7)['stat4CurrentWindowFence']['currentWindowRowids']),
    'planner stat4 expression partial current source next244 invalid negative limit' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => $plan244(-1, 0)),
    'planner stat4 expression partial current source next244 invalid negative offset' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => $plan244(1, -1)),
    'planner stat4 expression partial current source next244 malformed rows' => static function (TestRunner $t) use ($current244, $plan244): void {
        $bad = $current244();
        $bad['rows'] = 'bad';
        $t->throws(InvalidArgumentException::class, static fn () => $plan244(5, 1, null, $bad));
    },
    'planner stat4 expression partial current source next244 malformed yielded lower dependency' => static function (TestRunner $t) use ($current244, $plan244): void {
        $bad = $current244();
        $bad['rows'][0]['rowid'] = 'bad';
        $t->throws(InvalidArgumentException::class, static fn () => $plan244(5, 1, null, $bad));
    },
    'planner stat4 expression partial current source next244 unsupported operator rejected' => static function (TestRunner $t) use ($plan244, $terms244): void {
        $terms = $terms244();
        $terms[] = ['left' => ['column' => 'option_name'], 'operator' => 'GLOB', 'right' => 'plugin_*'];
        $t->throws(InvalidArgumentException::class, static fn () => $plan244(5, 1, null, null, $terms));
    },
];

foreach (range(1, 18) as $case) {
    $tests['planner stat4 expression partial current source next244 repeated window proof ' . $case] = static function (TestRunner $t) use ($plan244, $case): void {
        $plan = $plan244(1 + ($case % 5), $case % 4);
        $t->same($plan['stat4CurrentWindowFence']['proofSignature'], $plan['selectedPlan']['next244ProofSignature']);
    };
}

return $tests;

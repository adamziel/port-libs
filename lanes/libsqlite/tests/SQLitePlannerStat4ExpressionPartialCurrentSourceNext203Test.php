<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan;

$eq203 = static fn (string $column, mixed $right): array => ['left' => ['column' => $column], 'operator' => '=', 'right' => $right];
$notNull203 = static fn (string $column): array => ['left' => ['column' => $column], 'operator' => 'IS NOT NULL'];
$between203 = static fn (string $expression, mixed $lower, mixed $upper): array => ['left' => ['expression' => $expression], 'operator' => 'BETWEEN', 'lower' => $lower, 'upper' => $upper];

$prepared203 = static fn (): array => [
    'name' => 'prepared-wp-options-stat4-boundary-expression-partial-next203',
    'schemaCookie' => 2030,
    'stat4Generation' => 191,
    'rows' => [
        ['rowid' => 10, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_alpha', 'option_value' => 'alpha-old', 'updated_at' => 10],
        ['rowid' => 20, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_forms', 'option_value' => 'forms-old', 'updated_at' => 20],
        ['rowid' => 30, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_seo', 'option_value' => 'seo-old', 'updated_at' => 30],
    ],
    'indexes' => [[
        'name' => 'idx_wp_options_lower_boundary_partial_stat4_next203',
        'rootPage' => 20301,
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

$current203 = static function () use ($prepared203): array {
    $source = $prepared203();
    $source['name'] = 'current-wp-options-stat4-boundary-expression-partial-next203';
    $source['schemaCookie'] = 2039;
    $source['stat4Generation'] = 222;
    $source['indexes'][0]['rootPage'] = 20388;
    $source['indexes'][0]['stat4Samples'] = [
        ['neq' => '1 1', 'nlt' => '0 0', 'ndlt' => '0 0', 'sample' => ['plugin_alpha', 10]],
        ['neq' => '1 1', 'nlt' => '1 1', 'ndlt' => '1 1', 'sample' => ['plugin_cache', 40]],
        ['neq' => '3 1', 'nlt' => '2 2', 'ndlt' => '2 2', 'sample' => ['plugin_forms', 20]],
        ['neq' => '1 1', 'nlt' => '5 3', 'ndlt' => '3 3', 'sample' => ['plugin_mail', 50]],
        ['neq' => '1 1', 'nlt' => '6 4', 'ndlt' => '4 4', 'sample' => ['plugin_seo', 30]],
        ['neq' => '1 1', 'nlt' => '7 5', 'ndlt' => '5 5', 'sample' => ['plugin_zulu', 60]],
    ];
    $source['rows'] = [
        ['rowid' => 60, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_zulu', 'option_value' => 'zulu', 'updated_at' => 60],
        ['rowid' => 30, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_seo', 'option_value' => 'seo', 'updated_at' => 30],
        ['rowid' => 50, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'Plugin_Mail', 'option_value' => 'mail', 'updated_at' => 50],
        ['rowid' => 20, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_forms', 'option_value' => 'forms-anchor', 'updated_at' => 20],
        ['rowid' => 21, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'Plugin_Forms', 'option_value' => 'forms-copy-a', 'updated_at' => 21],
        ['rowid' => 22, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'PLUGIN_FORMS', 'option_value' => 'forms-copy-b', 'updated_at' => 22],
        ['rowid' => 40, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_cache', 'option_value' => 'cache', 'updated_at' => 40],
        ['rowid' => 10, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_alpha', 'option_value' => 'alpha', 'updated_at' => 10],
        ['rowid' => 70, 'blog_id' => 1, 'autoload' => 'no', 'option_name' => 'plugin_forms', 'option_value' => 'lazy', 'updated_at' => 70],
    ];

    return $source;
};

$terms203 = static fn (): array => [
    $between203('LOWER(option_name)', 'plugin_alpha', 'plugin_zulu'),
    $eq203('autoload', 'yes'),
    $notNull203('option_name'),
];
$plan203 = static fn (int $limit = 5, int $offset = 1, ?array $prepared = null, ?array $current = null, ?array $terms = null, ?array $needed = null): array => SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan::materializeNext203(
    $prepared ?? $prepared203(),
    $current ?? $current203(),
    $terms ?? $terms203(),
    $needed ?? ['option_name', 'option_value', 'updated_at', 'blog_id'],
    $limit,
    $offset,
);
$missingBoundary203 = static function () use ($current203, $plan203): array {
    $current = $current203();
    array_splice($current['indexes'][0]['stat4Samples'], 4, 1);

    return $plan203(5, 1, null, $current);
};
$driftBoundary203 = static function () use ($current203, $plan203): array {
    $current = $current203();
    $current['rows'][1]['option_name'] = 'plugin_security';

    return $plan203(5, 1, null, $current);
};
$nonMonotonic203 = static function () use ($current203, $plan203): array {
    $current = $current203();
    $current['indexes'][0]['stat4Samples'][4]['nlt'] = '1 1';

    return $plan203(5, 1, null, $current);
};
$badSample203 = static function () use ($current203, $plan203): array {
    $current = $current203();
    $current['indexes'][0]['stat4Samples'][0]['nlt'] = 'bad';

    return $plan203(5, 1, null, $current);
};

$tests = [
    'planner stat4 expression partial current source next203 status ready' => static fn (TestRunner $t) => $t->same('stat4-expression-partial-current-source-next203-ready', $plan203()['status']),
    'planner stat4 expression partial current source next203 selected current' => static fn (TestRunner $t) => $t->same('current', $plan203()['selectedSource']),
    'planner stat4 expression partial current source next203 inherited peer ready' => static fn (TestRunner $t) => $t->same(true, $plan203()['selectedPlan']['next196Ready']),
    'planner stat4 expression partial current source next203 ready flag' => static fn (TestRunner $t) => $t->same(true, $plan203()['selectedPlan']['next203Ready']),
    'planner stat4 expression partial current source next203 selected index' => static fn (TestRunner $t) => $t->same('idx_wp_options_lower_boundary_partial_stat4_next203', $plan203()['selectedPlan']['name']),
    'planner stat4 expression partial current source next203 root page' => static fn (TestRunner $t) => $t->same(20388, $plan203()['selectedPlan']['rootPage']),
    'planner stat4 expression partial current source next203 matched rowids' => static fn (TestRunner $t) => $t->same([30, 50, 20, 21, 22], $plan203()['matchedRowids']),
    'planner stat4 expression partial current source next203 boundary ready' => static fn (TestRunner $t) => $t->same(true, $plan203()['stat4BoundaryFence']['ready']),
    'planner stat4 expression partial current source next203 boundary keys' => static fn (TestRunner $t) => $t->same(['plugin_seo', 'plugin_forms'], $plan203()['stat4BoundaryFence']['boundaryKeys']),
    'planner stat4 expression partial current source next203 selected boundary keys' => static fn (TestRunner $t) => $t->same(['plugin_seo', 'plugin_forms'], $plan203()['selectedPlan']['next203BoundaryKeys']),
    'planner stat4 expression partial current source next203 selected rowids' => static fn (TestRunner $t) => $t->same([30, 50, 20, 21, 22], $plan203()['stat4BoundaryFence']['selectedRowids']),
    'planner stat4 expression partial current source next203 selected keys' => static fn (TestRunner $t) => $t->same(['plugin_seo', 'plugin_mail', 'plugin_forms', 'plugin_forms', 'plugin_forms'], $plan203()['stat4BoundaryFence']['selectedExpressionKeys']),
    'planner stat4 expression partial current source next203 limit' => static fn (TestRunner $t) => $t->same(5, $plan203()['stat4BoundaryFence']['limit']),
    'planner stat4 expression partial current source next203 offset' => static fn (TestRunner $t) => $t->same(1, $plan203()['stat4BoundaryFence']['offset']),
    'planner stat4 expression partial current source next203 missing none' => static fn (TestRunner $t) => $t->same([], $plan203()['stat4BoundaryFence']['missingBoundaryKeys']),
    'planner stat4 expression partial current source next203 drift none' => static fn (TestRunner $t) => $t->same([], $plan203()['stat4BoundaryFence']['sampleKeyDriftRowids']),
    'planner stat4 expression partial current source next203 monotonic none' => static fn (TestRunner $t) => $t->same([], $plan203()['stat4BoundaryFence']['nonMonotonicSampleRowids']),
    'planner stat4 expression partial current source next203 check count' => static fn (TestRunner $t) => $t->same(2, count($plan203()['stat4BoundaryFence']['checks'])),
    'planner stat4 expression partial current source next203 seo sample rowid' => static fn (TestRunner $t) => $t->same(30, $plan203()['stat4BoundaryFence']['checks'][0]['sampleChecks'][0]['rowid']),
    'planner stat4 expression partial current source next203 forms sample rowid' => static fn (TestRunner $t) => $t->same(20, $plan203()['stat4BoundaryFence']['checks'][1]['sampleChecks'][0]['rowid']),
    'planner stat4 expression partial current source next203 forms sample nlt' => static fn (TestRunner $t) => $t->same(2, $plan203()['stat4BoundaryFence']['checks'][1]['sampleChecks'][0]['nlt']),
    'planner stat4 expression partial current source next203 sample actual key' => static fn (TestRunner $t) => $t->same('plugin_seo', $plan203()['stat4BoundaryFence']['checks'][0]['sampleChecks'][0]['actualExpressionKey']),
    'planner stat4 expression partial current source next203 sample ready' => static fn (TestRunner $t) => $t->same(true, $plan203()['stat4BoundaryFence']['checks'][0]['sampleChecks'][0]['ready']),
    'planner stat4 expression partial current source next203 peer order preserved' => static fn (TestRunner $t) => $t->same(true, $plan203()['peerOrderFence']['peerOrderStable']),
    'planner stat4 expression partial current source next203 covering preserved' => static fn (TestRunner $t) => $t->same(true, $plan203()['tableLookupElided']),
    'planner stat4 expression partial current source next203 projected payload' => static fn (TestRunner $t) => $t->same('forms-copy-b', $plan203()['projectedRows'][4]['option_value']),
    'planner stat4 expression partial current source next203 cursor opcode' => static fn (TestRunner $t) => $t->same('Stat4BoundarySampleFence', $plan203()['cursorProgram'][array_key_last($plan203()['cursorProgram'])]['opcode']),
    'planner stat4 expression partial current source next203 cursor mode' => static fn (TestRunner $t) => $t->same('next203-current-source-stat4-expression-partial-boundary', $plan203()['cursorProgram'][array_key_last($plan203()['cursorProgram'])]['mode']),
    'planner stat4 expression partial current source next203 cursor rowids' => static fn (TestRunner $t) => $t->same([30, 50, 20, 21, 22], $plan203()['cursorProgram'][array_key_last($plan203()['cursorProgram'])]['rowids']),
    'planner stat4 expression partial current source next203 cursor boundary keys' => static fn (TestRunner $t) => $t->same(['plugin_seo', 'plugin_forms'], $plan203()['cursorProgram'][array_key_last($plan203()['cursorProgram'])]['boundaryKeys']),
    'planner stat4 expression partial current source next203 signature length' => static fn (TestRunner $t) => $t->same(64, strlen($plan203()['stat4BoundaryFence']['signature'])),
    'planner stat4 expression partial current source next203 selected signature length' => static fn (TestRunner $t) => $t->same(64, strlen($plan203()['selectedPlan']['next203BoundarySignature'])),
    'planner stat4 expression partial current source next203 stat4 signature length' => static fn (TestRunner $t) => $t->same(64, strlen($plan203()['stat4Fence']['next203BoundarySignature'])),
    'planner stat4 expression partial current source next203 detail' => static fn (TestRunner $t) => $t->contains('NEXT203 BOUNDARY SAMPLE FENCE', $plan203()['detail']),
    'planner stat4 expression partial current source next203 dependency marker' => static fn (TestRunner $t) => $t->true(in_array('sqlite-sqlplanner-stat4-expression-partial-current-source-next203', $plan203()['dependencies'], true)),
    'planner stat4 expression partial current source next203 dependency closure' => static fn (TestRunner $t) => $t->contains('no new support component needed', $plan203()['dependency_closure']),
    'planner stat4 expression partial current source next203 non overlap' => static fn (TestRunner $t) => $t->contains('selected LIMIT/OFFSET window boundaries', $plan203()['non_overlap']),
    'planner stat4 expression partial current source next203 missing boundary blocked' => static fn (TestRunner $t) => $t->same('requires-current-source-stat4-boundary-reprepare', $missingBoundary203()['status']),
    'planner stat4 expression partial current source next203 missing boundary key' => static fn (TestRunner $t) => $t->same(['plugin_seo'], $missingBoundary203()['stat4BoundaryFence']['missingBoundaryKeys']),
    'planner stat4 expression partial current source next203 missing no cursor append' => static fn (TestRunner $t) => $t->same(false, in_array('Stat4BoundarySampleFence', array_column($missingBoundary203()['cursorProgram'], 'opcode'), true)),
    'planner stat4 expression partial current source next203 drift blocked' => static fn (TestRunner $t) => $t->same('requires-current-source-stat4-boundary-reprepare', $driftBoundary203()['status']),
    'planner stat4 expression partial current source next203 non monotonic blocked' => static fn (TestRunner $t) => $t->same('requires-current-source-stat4-boundary-reprepare', $nonMonotonic203()['status']),
    'planner stat4 expression partial current source next203 non monotonic rowid' => static fn (TestRunner $t) => $t->same([30], $nonMonotonic203()['stat4BoundaryFence']['nonMonotonicSampleRowids']),
    'planner stat4 expression partial current source next203 invalid sample nlt' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, $badSample203),
    'planner stat4 expression partial current source next203 invalid indexes' => static function (TestRunner $t) use ($current203, $plan203): void {
        $bad = $current203();
        $bad['indexes'] = 'bad';
        $t->throws(InvalidArgumentException::class, static fn () => $plan203(5, 1, null, $bad));
    },
    'planner stat4 expression partial current source next203 invalid rowid' => static function (TestRunner $t) use ($current203, $plan203): void {
        $bad = $current203();
        $bad['rows'][0]['rowid'] = 'bad';
        $t->throws(InvalidArgumentException::class, static fn () => $plan203(5, 1, null, $bad));
    },
];

foreach (range(1, 14) as $case) {
    $tests['planner stat4 expression partial current source next203 repeated boundary fence ' . $case] = static function (TestRunner $t) use ($plan203, $case): void {
        $plan = $plan203(1 + ($case % 5), $case % 4);
        $t->same(count($plan['stat4BoundaryFence']['boundaryKeys']), count($plan['stat4BoundaryFence']['checks']));
    };
}

return $tests;

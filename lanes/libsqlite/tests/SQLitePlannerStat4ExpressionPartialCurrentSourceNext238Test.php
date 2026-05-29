<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan;

$eq238 = static fn (string $column, mixed $right): array => ['left' => ['column' => $column], 'operator' => '=', 'right' => $right];
$like238 = static fn (string $column, string $right): array => ['left' => ['column' => $column], 'operator' => 'LIKE', 'right' => $right];
$notNull238 = static fn (string $column): array => ['left' => ['column' => $column], 'operator' => 'IS NOT NULL'];
$between238 = static fn (string $expression, mixed $lower, mixed $upper): array => ['left' => ['expression' => $expression], 'operator' => 'BETWEEN', 'lower' => $lower, 'upper' => $upper];

$payload238 = static fn (array $row): array => [
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

$prepared238 = static fn (): array => [
    'name' => 'prepared-wp-options-stat4-payload-next238',
    'schemaCookie' => 2380,
    'stat4Generation' => 238,
    'rows' => [
        ['rowid' => 10, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_alpha', 'option_value' => 'alpha-old', 'updated_at' => 10],
        ['rowid' => 20, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_forms', 'option_value' => 'forms-old', 'updated_at' => 20],
        ['rowid' => 30, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_seo', 'option_value' => 'seo-old', 'updated_at' => 30],
    ],
    'indexes' => [[
        'name' => 'idx_wp_options_stat4_payload_partial_next238',
        'rootPage' => 23801,
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
            ['neq' => '4 3', 'nlt' => '2 2', 'ndlt' => '2 2', 'sample' => ['plugin_forms', 1, 20]],
            ['neq' => '1 1', 'nlt' => '7 7', 'ndlt' => '4 5', 'sample' => ['plugin_seo', 1, 30]],
        ],
        'stat4ExpressionPayloads' => [],
    ]],
];

$current238 = static function () use ($prepared238, $payload238): array {
    $source = $prepared238();
    $source['name'] = 'current-wp-options-stat4-payload-next238';
    $source['schemaCookie'] = 2389;
    $source['stat4Generation'] = 438;
    $source['indexes'][0]['rootPage'] = 23888;
    $source['indexes'][0]['stat4Samples'] = [
        ['neq' => '1 1', 'nlt' => '0 0', 'ndlt' => '0 0', 'sample' => ['plugin_alpha', 1, 10]],
        ['neq' => '1 1', 'nlt' => '1 1', 'ndlt' => '1 1', 'sample' => ['plugin_cache', 1, 40]],
        ['neq' => '4 3', 'nlt' => '2 2', 'ndlt' => '2 2', 'sample' => ['plugin_forms', 1, 20]],
        ['neq' => '4 1', 'nlt' => '2 5', 'ndlt' => '2 3', 'sample' => ['plugin_forms', 2, 80]],
        ['neq' => '1 1', 'nlt' => '6 6', 'ndlt' => '3 4', 'sample' => ['plugin_mail', 1, 50]],
        ['neq' => '1 1', 'nlt' => '7 7', 'ndlt' => '4 5', 'sample' => ['plugin_seo', 1, 30]],
        ['neq' => '1 1', 'nlt' => '8 8', 'ndlt' => '5 6', 'sample' => ['plugin_zulu', 1, 60]],
    ];
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
    $source['indexes'][0]['stat4ExpressionPayloads'] = array_map($payload238, array_slice($source['rows'], 0, 9));

    return $source;
};

$terms238 = static fn (): array => [
    $between238('LOWER(option_name)', 'plugin_alpha', 'plugin_zulu'),
    $eq238('autoload', 'yes'),
    $notNull238('option_name'),
    $eq238('blog_id', 1),
    $like238('option_name', 'plugin_%'),
];
$plan238 = static fn (int $limit = 5, int $offset = 1, ?array $prepared = null, ?array $current = null, ?array $terms = null, ?array $needed = null): array => SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan::materializeNext238(
    $prepared ?? $prepared238(),
    $current ?? $current238(),
    $terms ?? $terms238(),
    $needed ?? ['option_name', 'option_value', 'updated_at', 'blog_id'],
    $limit,
    $offset,
);
$staleValue238 = static function () use ($current238, $plan238): array {
    $current = $current238();
    $current['indexes'][0]['stat4ExpressionPayloads'][4]['coveredValues']['option_value'] = 'forms-stale';

    return $plan238(5, 1, null, $current);
};
$staleExpression238 = static function () use ($current238, $plan238): array {
    $current = $current238();
    $current['indexes'][0]['stat4ExpressionPayloads'][2]['expressionKey'] = 'plugin_mail_old';

    return $plan238(5, 1, null, $current);
};
$missingPayload238 = static function () use ($current238, $plan238): array {
    $current = $current238();
    array_splice($current['indexes'][0]['stat4ExpressionPayloads'], 5, 1);

    return $plan238(5, 1, null, $current);
};
$stalePayload238 = static function () use ($current238, $payload238, $plan238): array {
    $current = $current238();
    $current['indexes'][0]['stat4ExpressionPayloads'][] = $payload238(['rowid' => 91, 'blog_id' => 1, 'autoload' => 'no', 'option_name' => 'plugin_old', 'option_value' => 'old', 'updated_at' => 91]);

    return $plan238(5, 1, null, $current);
};

$tests = [
    'planner stat4 expression partial current source next238 status ready' => static fn (TestRunner $t) => $t->same('stat4-expression-partial-current-source-next238-ready', $plan238()['status']),
    'planner stat4 expression partial current source next238 inherits next235' => static fn (TestRunner $t) => $t->same(true, $plan238()['selectedPlan']['next235Ready']),
    'planner stat4 expression partial current source next238 ready flag' => static fn (TestRunner $t) => $t->same(true, $plan238()['selectedPlan']['next238Ready']),
    'planner stat4 expression partial current source next238 selected index' => static fn (TestRunner $t) => $t->same('idx_wp_options_stat4_payload_partial_next238', $plan238()['selectedPlan']['name']),
    'planner stat4 expression partial current source next238 covered row count' => static fn (TestRunner $t) => $t->same(9, $plan238()['stat4CoveringPayloadFence']['coveredRowCount']),
    'planner stat4 expression partial current source next238 payload row count' => static fn (TestRunner $t) => $t->same(9, $plan238()['stat4CoveringPayloadFence']['payloadRowCount']),
    'planner stat4 expression partial current source next238 covering columns' => static fn (TestRunner $t) => $t->same(['option_name', 'option_value', 'updated_at', 'autoload', 'blog_id'], $plan238()['stat4CoveringPayloadFence']['coveringColumns']),
    'planner stat4 expression partial current source next238 proof rowids sorted' => static fn (TestRunner $t) => $t->same([10, 40, 20, 21, 22, 80, 50, 30, 60], array_column($plan238()['stat4CoveringPayloadFence']['payloadProofs'], 'rowid')),
    'planner stat4 expression partial current source next238 proof present flags' => static fn (TestRunner $t) => $t->same(array_fill(0, 9, true), array_column($plan238()['stat4CoveringPayloadFence']['payloadProofs'], 'payloadPresent')),
    'planner stat4 expression partial current source next238 proof match flags' => static fn (TestRunner $t) => $t->same(array_fill(0, 9, true), array_column($plan238()['stat4CoveringPayloadFence']['payloadProofs'], 'payloadMatchesCurrentRow')),
    'planner stat4 expression partial current source next238 proof expression keys' => static fn (TestRunner $t) => $t->same(['plugin_alpha', 'plugin_cache', 'plugin_forms', 'plugin_forms', 'plugin_forms', 'plugin_forms', 'plugin_mail', 'plugin_seo', 'plugin_zulu'], array_column($plan238()['stat4CoveringPayloadFence']['payloadProofs'], 'currentExpressionKey')),
    'planner stat4 expression partial current source next238 no mismatches' => static fn (TestRunner $t) => $t->same([], $plan238()['stat4CoveringPayloadFence']['payloadMismatchRowids']),
    'planner stat4 expression partial current source next238 no missing' => static fn (TestRunner $t) => $t->same([], $plan238()['stat4CoveringPayloadFence']['missingPayloadRowids']),
    'planner stat4 expression partial current source next238 no stale' => static fn (TestRunner $t) => $t->same([], $plan238()['stat4CoveringPayloadFence']['stalePayloadRowids']),
    'planner stat4 expression partial current source next238 all payloads match' => static fn (TestRunner $t) => $t->same(true, $plan238()['stat4CoveringPayloadFence']['allPayloadsMatchCurrentRows']),
    'planner stat4 expression partial current source next238 selected covered count' => static fn (TestRunner $t) => $t->same(9, $plan238()['selectedPlan']['next238CoveredRowCount']),
    'planner stat4 expression partial current source next238 selected mismatches' => static fn (TestRunner $t) => $t->same([], $plan238()['selectedPlan']['next238PayloadMismatchRowids']),
    'planner stat4 expression partial current source next238 selected missing' => static fn (TestRunner $t) => $t->same([], $plan238()['selectedPlan']['next238MissingPayloadRowids']),
    'planner stat4 expression partial current source next238 selected stale' => static fn (TestRunner $t) => $t->same([], $plan238()['selectedPlan']['next238StalePayloadRowids']),
    'planner stat4 expression partial current source next238 signature lengths' => static fn (TestRunner $t) => $t->same([64, 64], [strlen($plan238()['stat4CoveringPayloadFence']['payloadSignature']), strlen($plan238()['stat4CoveringPayloadFence']['proofSignature'])]),
    'planner stat4 expression partial current source next238 selected payload signature' => static fn (TestRunner $t) => $t->same($plan238()['stat4CoveringPayloadFence']['payloadSignature'], $plan238()['selectedPlan']['next238PayloadSignature']),
    'planner stat4 expression partial current source next238 selected proof signature' => static fn (TestRunner $t) => $t->same($plan238()['stat4CoveringPayloadFence']['proofSignature'], $plan238()['selectedPlan']['next238ProofSignature']),
    'planner stat4 expression partial current source next238 stat4 payload signature' => static fn (TestRunner $t) => $t->same($plan238()['stat4CoveringPayloadFence']['payloadSignature'], $plan238()['stat4Fence']['next238PayloadSignature']),
    'planner stat4 expression partial current source next238 stat4 proof signature' => static fn (TestRunner $t) => $t->same($plan238()['stat4CoveringPayloadFence']['proofSignature'], $plan238()['stat4Fence']['next238ProofSignature']),
    'planner stat4 expression partial current source next238 cursor appended' => static fn (TestRunner $t) => $t->same('VerifyCurrentStat4CoveringPayloads', $plan238()['cursorProgram'][array_key_last($plan238()['cursorProgram'])]['opcode']),
    'planner stat4 expression partial current source next238 cursor mode' => static fn (TestRunner $t) => $t->same('next238-current-source-stat4-expression-partial-covering-payloads', $plan238()['cursorProgram'][array_key_last($plan238()['cursorProgram'])]['mode']),
    'planner stat4 expression partial current source next238 cursor counts' => static fn (TestRunner $t) => $t->same([9, 9], [$plan238()['cursorProgram'][array_key_last($plan238()['cursorProgram'])]['coveredRowCount'], $plan238()['cursorProgram'][array_key_last($plan238()['cursorProgram'])]['payloadRowCount']]),
    'planner stat4 expression partial current source next238 cursor signature' => static fn (TestRunner $t) => $t->same($plan238()['stat4CoveringPayloadFence']['proofSignature'], $plan238()['cursorProgram'][array_key_last($plan238()['cursorProgram'])]['signature']),
    'planner stat4 expression partial current source next238 matched rowids preserved' => static fn (TestRunner $t) => $t->same([30, 50, 20, 21, 22], $plan238()['matchedRowids']),
    'planner stat4 expression partial current source next238 projected payload retained' => static fn (TestRunner $t) => $t->same('forms-copy-b', $plan238()['projectedRows'][4]['option_value']),
    'planner stat4 expression partial current source next238 dependency marker' => static fn (TestRunner $t) => $t->true(in_array('sqlite-sqlplanner-stat4-expression-partial-current-source-next238', $plan238()['dependencies'], true)),
    'planner stat4 expression partial current source next238 dependency closure' => static fn (TestRunner $t) => $t->contains('no new support component needed', $plan238()['dependency_closure']),
    'planner stat4 expression partial current source next238 non overlap' => static fn (TestRunner $t) => $t->contains('covering payload staleness', $plan238()['non_overlap']),
    'planner stat4 expression partial current source next238 detail' => static fn (TestRunner $t) => $t->contains('NEXT238 COVERING PAYLOAD FENCE', $plan238()['detail']),
    'planner stat4 expression partial current source next238 stale value blocked' => static fn (TestRunner $t) => $t->same('requires-current-source-stat4-covering-payload-reprepare', $staleValue238()['status']),
    'planner stat4 expression partial current source next238 stale value rowid' => static fn (TestRunner $t) => $t->same([20], $staleValue238()['stat4CoveringPayloadFence']['payloadMismatchRowids']),
    'planner stat4 expression partial current source next238 stale value column' => static fn (TestRunner $t) => $t->same(['option_value'], $staleValue238()['stat4CoveringPayloadFence']['payloadProofs'][2]['mismatchedColumns']),
    'planner stat4 expression partial current source next238 stale value no cursor' => static fn (TestRunner $t) => $t->same(false, in_array('VerifyCurrentStat4CoveringPayloads', array_column($staleValue238()['cursorProgram'], 'opcode'), true)),
    'planner stat4 expression partial current source next238 stale expression blocked' => static fn (TestRunner $t) => $t->same('requires-current-source-stat4-covering-payload-reprepare', $staleExpression238()['status']),
    'planner stat4 expression partial current source next238 stale expression rowid' => static fn (TestRunner $t) => $t->same([50], $staleExpression238()['stat4CoveringPayloadFence']['payloadMismatchRowids']),
    'planner stat4 expression partial current source next238 stale expression column' => static fn (TestRunner $t) => $t->same(['expressionKey'], $staleExpression238()['stat4CoveringPayloadFence']['payloadProofs'][6]['mismatchedColumns']),
    'planner stat4 expression partial current source next238 missing payload blocked' => static fn (TestRunner $t) => $t->same('requires-current-source-stat4-covering-payload-reprepare', $missingPayload238()['status']),
    'planner stat4 expression partial current source next238 missing payload rowid' => static fn (TestRunner $t) => $t->same([21], $missingPayload238()['stat4CoveringPayloadFence']['missingPayloadRowids']),
    'planner stat4 expression partial current source next238 missing payload proof' => static fn (TestRunner $t) => $t->same([false, false], [$missingPayload238()['stat4CoveringPayloadFence']['payloadProofs'][3]['payloadPresent'], $missingPayload238()['stat4CoveringPayloadFence']['payloadProofs'][3]['payloadMatchesCurrentRow']]),
    'planner stat4 expression partial current source next238 stale payload blocked' => static fn (TestRunner $t) => $t->same('requires-current-source-stat4-covering-payload-reprepare', $stalePayload238()['status']),
    'planner stat4 expression partial current source next238 stale payload rowid' => static fn (TestRunner $t) => $t->same([91], $stalePayload238()['stat4CoveringPayloadFence']['stalePayloadRowids']),
    'planner stat4 expression partial current source next238 stale payload count' => static fn (TestRunner $t) => $t->same(10, $stalePayload238()['stat4CoveringPayloadFence']['payloadRowCount']),
    'planner stat4 expression partial current source next238 invalid payload list' => static function (TestRunner $t) use ($current238, $plan238): void {
        $bad = $current238();
        $bad['indexes'][0]['stat4ExpressionPayloads'] = 'bad';
        $t->throws(InvalidArgumentException::class, static fn () => $plan238(5, 1, null, $bad));
    },
    'planner stat4 expression partial current source next238 invalid payload covered values' => static function (TestRunner $t) use ($current238, $plan238): void {
        $bad = $current238();
        $bad['indexes'][0]['stat4ExpressionPayloads'][0]['coveredValues'] = 'bad';
        $t->throws(InvalidArgumentException::class, static fn () => $plan238(5, 1, null, $bad));
    },
    'planner stat4 expression partial current source next238 duplicate payload rowid' => static function (TestRunner $t) use ($current238, $plan238): void {
        $bad = $current238();
        $bad['indexes'][0]['stat4ExpressionPayloads'][] = $bad['indexes'][0]['stat4ExpressionPayloads'][0];
        $t->throws(InvalidArgumentException::class, static fn () => $plan238(5, 1, null, $bad));
    },
    'planner stat4 expression partial current source next238 malformed covering column' => static function (TestRunner $t) use ($current238, $plan238): void {
        $bad = $current238();
        $bad['indexes'][0]['coveringColumns'][1] = '';
        $t->throws(InvalidArgumentException::class, static fn () => $plan238(5, 1, null, $bad));
    },
    'planner stat4 expression partial current source next238 unsupported partial op' => static function (TestRunner $t) use ($current238, $plan238): void {
        $bad = $current238();
        $bad['indexes'][0]['partialPredicateTerms'][0]['operator'] = 'GLOB';
        $t->throws(InvalidArgumentException::class, static fn () => $plan238(5, 1, null, $bad));
    },
];

foreach (range(1, 18) as $case) {
    $tests['planner stat4 expression partial current source next238 repeated payload proof ' . $case] = static function (TestRunner $t) use ($plan238, $case): void {
        $plan = $plan238(1 + ($case % 5), $case % 4);
        $t->same($plan['stat4CoveringPayloadFence']['proofSignature'], $plan['selectedPlan']['next238ProofSignature']);
    };
}

return $tests;

<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan;

$eq218 = static fn (string $column, mixed $right): array => ['left' => ['column' => $column], 'operator' => '=', 'right' => $right];
$like218 = static fn (string $column, string $right): array => ['left' => ['column' => $column], 'operator' => 'LIKE', 'right' => $right];
$notNull218 = static fn (string $column): array => ['left' => ['column' => $column], 'operator' => 'IS NOT NULL'];
$between218 = static fn (string $expression, mixed $lower, mixed $upper): array => ['left' => ['expression' => $expression], 'operator' => 'BETWEEN', 'lower' => $lower, 'upper' => $upper];

$payload218 = static fn (array $row): array => [
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

$prepared218 = static fn (): array => [
    'name' => 'prepared-wp-options-expression-payload-stat4-next218',
    'schemaCookie' => 2180,
    'stat4Generation' => 218,
    'rows' => [
        ['rowid' => 10, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_alpha', 'option_value' => 'alpha-old', 'updated_at' => 10],
        ['rowid' => 20, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_forms', 'option_value' => 'forms-old', 'updated_at' => 20],
        ['rowid' => 30, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_seo', 'option_value' => 'seo-old', 'updated_at' => 30],
    ],
    'indexes' => [[
        'name' => 'idx_wp_options_expr_payload_stat4_next218',
        'rootPage' => 21801,
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
        'partialGroupedOrPredicateArms' => [
            [
                ['left' => ['column' => 'blog_id'], 'operator' => '=', 'right' => 1],
                ['left' => ['column' => 'autoload'], 'operator' => '=', 'right' => 'yes'],
            ],
            [
                ['left' => ['column' => 'autoload'], 'operator' => '=', 'right' => 'critical'],
            ],
        ],
        'partialGroupedLikePredicateArms' => [
            [
                ['left' => ['column' => 'blog_id'], 'operator' => '=', 'right' => 1],
                ['left' => ['column' => 'option_name'], 'operator' => 'LIKE', 'right' => 'plugin_%'],
            ],
            [
                ['left' => ['column' => 'option_name'], 'operator' => 'LIKE', 'right' => 'network_%'],
            ],
        ],
        'coveringColumns' => ['option_name', 'option_value', 'updated_at', 'autoload', 'blog_id'],
        'stat4Samples' => [
            ['neq' => '1 1', 'nlt' => '0 0', 'ndlt' => '0 0', 'sample' => ['plugin_alpha', 10]],
            ['neq' => '1 1', 'nlt' => '1 1', 'ndlt' => '1 1', 'sample' => ['plugin_forms', 20]],
            ['neq' => '1 1', 'nlt' => '2 2', 'ndlt' => '2 2', 'sample' => ['plugin_seo', 30]],
        ],
        'stat4ExpressionPayloads' => [],
    ]],
];

$current218 = static function () use ($prepared218, $payload218): array {
    $source = $prepared218();
    $source['name'] = 'current-wp-options-expression-payload-stat4-next218';
    $source['schemaCookie'] = 2189;
    $source['stat4Generation'] = 286;
    $source['indexes'][0]['rootPage'] = 21888;
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
        ['rowid' => 80, 'blog_id' => 2, 'autoload' => 'yes', 'option_name' => 'plugin_forms', 'option_value' => 'other-blog', 'updated_at' => 80],
    ];
    $source['indexes'][0]['stat4ExpressionPayloads'] = array_map($payload218, array_slice($source['rows'], 0, 8));

    return $source;
};

$terms218 = static fn (): array => [
    $between218('LOWER(option_name)', 'plugin_alpha', 'plugin_zulu'),
    $eq218('autoload', 'yes'),
    $notNull218('option_name'),
    $eq218('blog_id', 1),
    $like218('option_name', 'plugin_%'),
];
$plan218 = static fn (int $limit = 5, int $offset = 1, ?array $prepared = null, ?array $current = null, ?array $terms = null, ?array $needed = null): array => SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan::materializeNext218(
    $prepared ?? $prepared218(),
    $current ?? $current218(),
    $terms ?? $terms218(),
    $needed ?? ['option_name', 'option_value', 'updated_at', 'blog_id'],
    $limit,
    $offset,
);
$stalePayload218 = static function () use ($current218, $plan218): array {
    $current = $current218();
    foreach ($current['indexes'][0]['stat4ExpressionPayloads'] as &$payload) {
        if (($payload['rowid'] ?? null) === 50) {
            $payload['expressionKey'] = 'plugin_mail_old';
        }
    }
    unset($payload);

    return $plan218(5, 1, null, $current);
};
$missingColumn218 = static function () use ($current218, $plan218): array {
    $current = $current218();
    $current['indexes'][0]['coveringColumns'] = ['option_name', 'updated_at', 'autoload', 'blog_id'];

    return $plan218(5, 1, null, $current);
};
$missingPayloadColumn218 = static function () use ($current218, $plan218): array {
    $current = $current218();
    foreach ($current['indexes'][0]['stat4ExpressionPayloads'] as &$payload) {
        if (($payload['rowid'] ?? null) === 20) {
            unset($payload['coveredValues']['option_value']);
        }
    }
    unset($payload);

    return $plan218(5, 1, null, $current);
};
$missingSamplePayload218 = static function () use ($current218, $plan218): array {
    $current = $current218();
    array_pop($current['indexes'][0]['stat4ExpressionPayloads']);

    return $plan218(5, 1, null, $current);
};

$tests = [
    'planner stat4 expression partial current source next218 status ready' => static fn (TestRunner $t) => $t->same('stat4-expression-partial-current-source-next218-ready', $plan218()['status']),
    'planner stat4 expression partial current source next218 selected current' => static fn (TestRunner $t) => $t->same('current', $plan218()['selectedSource']),
    'planner stat4 expression partial current source next218 inherited next212' => static fn (TestRunner $t) => $t->same(true, $plan218()['selectedPlan']['next212Ready']),
    'planner stat4 expression partial current source next218 ready flag' => static fn (TestRunner $t) => $t->same(true, $plan218()['selectedPlan']['next218Ready']),
    'planner stat4 expression partial current source next218 selected index' => static fn (TestRunner $t) => $t->same('idx_wp_options_expr_payload_stat4_next218', $plan218()['selectedPlan']['name']),
    'planner stat4 expression partial current source next218 root page' => static fn (TestRunner $t) => $t->same(21888, $plan218()['selectedPlan']['rootPage']),
    'planner stat4 expression partial current source next218 expression' => static fn (TestRunner $t) => $t->same('lower(option_name)', $plan218()['expressionPayloadFence']['expression']),
    'planner stat4 expression partial current source next218 collation' => static fn (TestRunner $t) => $t->same('BINARY', $plan218()['expressionPayloadFence']['collation']),
    'planner stat4 expression partial current source next218 covering columns' => static fn (TestRunner $t) => $t->same(['option_name', 'option_value', 'updated_at', 'autoload', 'blog_id'], $plan218()['expressionPayloadFence']['currentCoveringColumns']),
    'planner stat4 expression partial current source next218 needed columns' => static fn (TestRunner $t) => $t->same(['option_name', 'option_value', 'updated_at', 'blog_id'], $plan218()['expressionPayloadFence']['neededColumns']),
    'planner stat4 expression partial current source next218 covering complete' => static fn (TestRunner $t) => $t->same(true, $plan218()['expressionPayloadFence']['allNeededColumnsCoveredByCurrentIndex']),
    'planner stat4 expression partial current source next218 missing covered none' => static fn (TestRunner $t) => $t->same([], $plan218()['expressionPayloadFence']['missingCoveredColumns']),
    'planner stat4 expression partial current source next218 payload complete' => static fn (TestRunner $t) => $t->same(true, $plan218()['expressionPayloadFence']['allMatchedRowsHaveCurrentExpressionPayload']),
    'planner stat4 expression partial current source next218 stale none' => static fn (TestRunner $t) => $t->same([], $plan218()['expressionPayloadFence']['stalePayloadRowids']),
    'planner stat4 expression partial current source next218 sample payload complete' => static fn (TestRunner $t) => $t->same(true, $plan218()['expressionPayloadFence']['allStat4SamplePayloadsResolveToCurrentRows']),
    'planner stat4 expression partial current source next218 sample payload missing none' => static fn (TestRunner $t) => $t->same([], $plan218()['expressionPayloadFence']['missingSamplePayloadRowids']),
    'planner stat4 expression partial current source next218 matched rowids' => static fn (TestRunner $t) => $t->same([30, 50, 20, 21, 22], $plan218()['matchedRowids']),
    'planner stat4 expression partial current source next218 proof rowids' => static fn (TestRunner $t) => $t->same([30, 50, 20, 21, 22], array_column($plan218()['expressionPayloadFence']['matchedRowPayloadProofs'], 'rowid')),
    'planner stat4 expression partial current source next218 expected keys' => static fn (TestRunner $t) => $t->same(['plugin_seo', 'plugin_mail', 'plugin_forms', 'plugin_forms', 'plugin_forms'], array_column($plan218()['expressionPayloadFence']['matchedRowPayloadProofs'], 'expectedExpressionKey')),
    'planner stat4 expression partial current source next218 payload keys' => static fn (TestRunner $t) => $t->same(['plugin_seo', 'plugin_mail', 'plugin_forms', 'plugin_forms', 'plugin_forms'], array_column($plan218()['expressionPayloadFence']['matchedRowPayloadProofs'], 'payloadExpressionKey')),
    'planner stat4 expression partial current source next218 proof matches' => static fn (TestRunner $t) => $t->same([true, true, true, true, true], array_column($plan218()['expressionPayloadFence']['matchedRowPayloadProofs'], 'matchesCurrentExpressionPayload')),
    'planner stat4 expression partial current source next218 payload found' => static fn (TestRunner $t) => $t->same([true, true, true, true, true], array_column($plan218()['expressionPayloadFence']['matchedRowPayloadProofs'], 'payloadFound')),
    'planner stat4 expression partial current source next218 no missing payload columns' => static fn (TestRunner $t) => $t->same([[], [], [], [], []], array_column($plan218()['expressionPayloadFence']['matchedRowPayloadProofs'], 'missingPayloadColumns')),
    'planner stat4 expression partial current source next218 sample rowids' => static fn (TestRunner $t) => $t->same([10, 40, 20, 50, 30, 60], array_column($plan218()['expressionPayloadFence']['stat4SamplePayloadProofs'], 'rowid')),
    'planner stat4 expression partial current source next218 sample keys' => static fn (TestRunner $t) => $t->same(['plugin_alpha', 'plugin_cache', 'plugin_forms', 'plugin_mail', 'plugin_seo', 'plugin_zulu'], array_column($plan218()['expressionPayloadFence']['stat4SamplePayloadProofs'], 'sampleExpressionKey')),
    'planner stat4 expression partial current source next218 sample matches' => static fn (TestRunner $t) => $t->same([true, true, true, true, true, true], array_column($plan218()['expressionPayloadFence']['stat4SamplePayloadProofs'], 'samplePayloadMatchesCurrentRow')),
    'planner stat4 expression partial current source next218 payload retained' => static fn (TestRunner $t) => $t->same('forms-copy-b', $plan218()['projectedRows'][4]['option_value']),
    'planner stat4 expression partial current source next218 grouped like preserved' => static fn (TestRunner $t) => $t->same(true, $plan218()['groupedLikePredicateFence']['currentGroupedLikePredicateImplied']),
    'planner stat4 expression partial current source next218 grouped or preserved' => static fn (TestRunner $t) => $t->same(true, $plan218()['groupedPartialOrPredicateFence']['currentGroupedPartialOrPredicateImplied']),
    'planner stat4 expression partial current source next218 grouped like base count' => static fn (TestRunner $t) => $t->same(5, count($plan218()['groupedLikePredicateFence']['rowProofs'])),
    'planner stat4 expression partial current source next218 selected signature' => static fn (TestRunner $t) => $t->same($plan218()['expressionPayloadFence']['expressionPayloadSignature'], $plan218()['selectedPlan']['next218ExpressionPayloadSignature']),
    'planner stat4 expression partial current source next218 covering signature' => static fn (TestRunner $t) => $t->same($plan218()['expressionPayloadFence']['currentCoveringSignature'], $plan218()['selectedPlan']['next218CurrentCoveringSignature']),
    'planner stat4 expression partial current source next218 stat4 signature' => static fn (TestRunner $t) => $t->same($plan218()['expressionPayloadFence']['expressionPayloadSignature'], $plan218()['stat4Fence']['next218ExpressionPayloadSignature']),
    'planner stat4 expression partial current source next218 proof signature' => static fn (TestRunner $t) => $t->same($plan218()['expressionPayloadFence']['proofSignature'], $plan218()['stat4Fence']['next218PayloadProofSignature']),
    'planner stat4 expression partial current source next218 signature length' => static fn (TestRunner $t) => $t->same(64, strlen($plan218()['expressionPayloadFence']['expressionPayloadSignature'])),
    'planner stat4 expression partial current source next218 proof signature length' => static fn (TestRunner $t) => $t->same(64, strlen($plan218()['expressionPayloadFence']['proofSignature'])),
    'planner stat4 expression partial current source next218 cursor appended' => static fn (TestRunner $t) => $t->same('RecheckCurrentExpressionPayloadCoverage', $plan218()['cursorProgram'][array_key_last($plan218()['cursorProgram'])]['opcode']),
    'planner stat4 expression partial current source next218 cursor mode' => static fn (TestRunner $t) => $t->same('next218-current-source-stat4-expression-partial-payload-covering', $plan218()['cursorProgram'][array_key_last($plan218()['cursorProgram'])]['mode']),
    'planner stat4 expression partial current source next218 cursor rowids' => static fn (TestRunner $t) => $t->same([30, 50, 20, 21, 22], $plan218()['cursorProgram'][array_key_last($plan218()['cursorProgram'])]['rowids']),
    'planner stat4 expression partial current source next218 dependency marker' => static fn (TestRunner $t) => $t->true(in_array('sqlite-sqlplanner-stat4-expression-partial-current-source-next218', $plan218()['dependencies'], true)),
    'planner stat4 expression partial current source next218 dependency closure' => static fn (TestRunner $t) => $t->contains('no new support component needed', $plan218()['dependency_closure']),
    'planner stat4 expression partial current source next218 non overlap' => static fn (TestRunner $t) => $t->contains('expression payload and covering-column', $plan218()['non_overlap']),
    'planner stat4 expression partial current source next218 detail' => static fn (TestRunner $t) => $t->contains('NEXT218 EXPRESSION PAYLOAD COVERING FENCE', $plan218()['detail']),
    'planner stat4 expression partial current source next218 stale blocked' => static fn (TestRunner $t) => $t->same('requires-current-source-expression-payload-reprepare', $stalePayload218()['status']),
    'planner stat4 expression partial current source next218 stale rowid' => static fn (TestRunner $t) => $t->same([50], $stalePayload218()['expressionPayloadFence']['stalePayloadRowids']),
    'planner stat4 expression partial current source next218 stale selected rowid' => static fn (TestRunner $t) => $t->same([50], $stalePayload218()['selectedPlan']['next218StalePayloadRowids']),
    'planner stat4 expression partial current source next218 missing covering blocked' => static fn (TestRunner $t) => $t->same('requires-current-source-expression-payload-reprepare', $missingColumn218()['status']),
    'planner stat4 expression partial current source next218 missing covering column' => static fn (TestRunner $t) => $t->same(['option_value'], $missingColumn218()['expressionPayloadFence']['missingCoveredColumns']),
    'planner stat4 expression partial current source next218 missing selected covering column' => static fn (TestRunner $t) => $t->same(['option_value'], $missingColumn218()['selectedPlan']['next218MissingCoveredColumns']),
    'planner stat4 expression partial current source next218 missing payload column blocked' => static fn (TestRunner $t) => $t->same('requires-current-source-expression-payload-reprepare', $missingPayloadColumn218()['status']),
    'planner stat4 expression partial current source next218 missing payload rowid' => static fn (TestRunner $t) => $t->same([20], $missingPayloadColumn218()['expressionPayloadFence']['stalePayloadRowids']),
    'planner stat4 expression partial current source next218 missing sample payload blocked' => static fn (TestRunner $t) => $t->same('requires-current-source-expression-payload-reprepare', $missingSamplePayload218()['status']),
    'planner stat4 expression partial current source next218 missing sample payload rowid' => static fn (TestRunner $t) => $t->same([10], $missingSamplePayload218()['expressionPayloadFence']['missingSamplePayloadRowids']),
    'planner stat4 expression partial current source next218 invalid indexes' => static function (TestRunner $t) use ($current218, $plan218): void {
        $bad = $current218();
        $bad['indexes'] = 'bad';
        $t->throws(InvalidArgumentException::class, static fn () => $plan218(5, 1, null, $bad));
    },
    'planner stat4 expression partial current source next218 invalid expression' => static function (TestRunner $t) use ($current218, $plan218): void {
        $bad = $current218();
        $bad['indexes'][0]['expression'] = 'upper(option_name)';
        $t->throws(InvalidArgumentException::class, static fn () => $plan218(5, 1, null, $bad));
    },
    'planner stat4 expression partial current source next218 invalid collation' => static function (TestRunner $t) use ($current218, $plan218): void {
        $bad = $current218();
        $bad['indexes'][0]['collation'] = 'RTRIM';
        $t->throws(InvalidArgumentException::class, static fn () => $plan218(5, 1, null, $bad));
    },
    'planner stat4 expression partial current source next218 invalid payloads' => static function (TestRunner $t) use ($current218, $plan218): void {
        $bad = $current218();
        $bad['indexes'][0]['stat4ExpressionPayloads'] = 'bad';
        $t->throws(InvalidArgumentException::class, static fn () => $plan218(5, 1, null, $bad));
    },
    'planner stat4 expression partial current source next218 invalid payload entry' => static function (TestRunner $t) use ($current218, $plan218): void {
        $bad = $current218();
        $bad['indexes'][0]['stat4ExpressionPayloads'][0]['coveredValues'] = 'bad';
        $t->throws(InvalidArgumentException::class, static fn () => $plan218(5, 1, null, $bad));
    },
];

foreach (range(1, 12) as $case) {
    $tests['planner stat4 expression partial current source next218 repeated payload fence ' . $case] = static function (TestRunner $t) use ($plan218, $case): void {
        $plan = $plan218(1 + ($case % 5), $case % 4);
        $t->same(count($plan['matchedRows']), count($plan['expressionPayloadFence']['matchedRowPayloadProofs']));
    };
}

return $tests;

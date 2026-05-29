<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan;

$eq237 = static fn (string $column, mixed $right): array => ['left' => ['column' => $column], 'operator' => '=', 'right' => $right];
$like237 = static fn (string $column, string $right): array => ['left' => ['column' => $column], 'operator' => 'LIKE', 'right' => $right];
$notNull237 = static fn (string $column): array => ['left' => ['column' => $column], 'operator' => 'IS NOT NULL'];
$between237 = static fn (string $expression, mixed $lower, mixed $upper): array => ['left' => ['expression' => $expression], 'operator' => 'BETWEEN', 'lower' => $lower, 'upper' => $upper];

$payload237 = static fn (array $row): array => [
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

$prepared237 = static fn (): array => [
    'name' => 'prepared-wp-options-stat4-trailing-payload-next237',
    'schemaCookie' => 2370,
    'stat4Generation' => 237,
    'rows' => [
        ['rowid' => 10, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_alpha', 'option_value' => 'alpha-old', 'updated_at' => 10],
        ['rowid' => 20, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_forms', 'option_value' => 'forms-old', 'updated_at' => 20],
        ['rowid' => 30, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_seo', 'option_value' => 'seo-old', 'updated_at' => 30],
    ],
    'indexes' => [[
        'name' => 'idx_wp_options_stat4_trailing_payload_next237',
        'rootPage' => 23701,
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
            ['neq' => '1 1 1 1', 'nlt' => '0 0 0 0', 'ndlt' => '0 0 0 0', 'sample' => ['plugin_alpha', 10, 'yes', 1]],
            ['neq' => '1 1 1 1', 'nlt' => '1 1 1 1', 'ndlt' => '1 1 1 1', 'sample' => ['plugin_forms', 20, 'yes', 1]],
            ['neq' => '1 1 1 1', 'nlt' => '2 2 2 2', 'ndlt' => '2 2 2 2', 'sample' => ['plugin_seo', 30, 'yes', 1]],
        ],
        'stat4ExpressionPayloads' => [],
    ]],
];

$current237 = static function () use ($prepared237, $payload237): array {
    $source = $prepared237();
    $source['name'] = 'current-wp-options-stat4-trailing-payload-next237';
    $source['schemaCookie'] = 2379;
    $source['stat4Generation'] = 337;
    $source['indexes'][0]['rootPage'] = 23788;
    $source['indexes'][0]['stat4Samples'] = [
        ['neq' => '1 1 1 1', 'nlt' => '0 0 0 0', 'ndlt' => '0 0 0 0', 'sample' => ['plugin_alpha', 10, 'yes', 1]],
        ['neq' => '1 1 1 1', 'nlt' => '1 1 1 1', 'ndlt' => '1 1 1 1', 'sample' => ['plugin_cache', 40, 'yes', 1]],
        ['neq' => '3 1 1 1', 'nlt' => '2 2 2 2', 'ndlt' => '2 2 2 2', 'sample' => ['plugin_forms', 20, 'yes', 1]],
        ['neq' => '1 1 1 1', 'nlt' => '5 3 3 3', 'ndlt' => '3 3 3 3', 'sample' => ['plugin_mail', 50, 'yes', 1]],
        ['neq' => '1 1 1 1', 'nlt' => '6 4 4 4', 'ndlt' => '4 4 4 4', 'sample' => ['plugin_seo', 30, 'yes', 1]],
        ['neq' => '1 1 1 1', 'nlt' => '7 5 5 5', 'ndlt' => '5 5 5 5', 'sample' => ['plugin_zulu', 60, 'yes', 1]],
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
    $source['indexes'][0]['stat4ExpressionPayloads'] = array_map($payload237, array_slice($source['rows'], 0, 8));

    return $source;
};

$terms237 = static fn (): array => [
    $between237('LOWER(option_name)', 'plugin_alpha', 'plugin_zulu'),
    $eq237('autoload', 'yes'),
    $notNull237('option_name'),
    $eq237('blog_id', 1),
    $like237('option_name', 'plugin_%'),
];
$plan237 = static fn (int $limit = 5, int $offset = 1, ?array $prepared = null, ?array $current = null, ?array $terms = null, ?array $trailing = null): array => SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan::materializeNext237(
    $prepared ?? $prepared237(),
    $current ?? $current237(),
    $terms ?? $terms237(),
    ['option_name', 'option_value', 'updated_at', 'blog_id'],
    $trailing ?? ['autoload', 'blog_id'],
    $limit,
    $offset,
);
$staleAutoload237 = static function () use ($current237, $plan237): array {
    $current = $current237();
    foreach ($current['rows'] as &$row) {
        if ($row['rowid'] === 50) {
            $row['autoload'] = 'critical';
        }
    }
    unset($row);

    return $plan237(5, 1, null, $current);
};
$staleBlog237 = static function () use ($current237, $payload237, $plan237): array {
    $current = $current237();
    foreach ($current['rows'] as &$row) {
        if ($row['rowid'] === 20) {
            $row['blog_id'] = 2;
        }
    }
    unset($row);
    $current['indexes'][0]['stat4ExpressionPayloads'] = array_map($payload237, array_slice($current['rows'], 0, 8));

    return $plan237(5, 1, null, $current);
};
$missingPayload237 = static function () use ($current237, $plan237): array {
    $current = $current237();
    $current['rows'] = array_values(array_filter($current['rows'], static fn (array $row): bool => $row['rowid'] !== 40));

    return $plan237(5, 1, null, $current);
};
$badTrailing237 = static fn (): array => $plan237(5, 1, null, null, null, []);

$tests = [
    'planner stat4 expression partial current source next237 status ready' => static fn (TestRunner $t) => $t->same('stat4-expression-partial-current-source-next237-ready', $plan237()['status']),
    'planner stat4 expression partial current source next237 selected current' => static fn (TestRunner $t) => $t->same('current', $plan237()['selectedSource']),
    'planner stat4 expression partial current source next237 inherited next228' => static fn (TestRunner $t) => $t->same(true, $plan237()['selectedPlan']['next228Ready']),
    'planner stat4 expression partial current source next237 ready flag' => static fn (TestRunner $t) => $t->same(true, $plan237()['selectedPlan']['next237Ready']),
    'planner stat4 expression partial current source next237 selected index' => static fn (TestRunner $t) => $t->same('idx_wp_options_stat4_trailing_payload_next237', $plan237()['selectedPlan']['name']),
    'planner stat4 expression partial current source next237 root page' => static fn (TestRunner $t) => $t->same(23788, $plan237()['selectedPlan']['rootPage']),
    'planner stat4 expression partial current source next237 trailing columns' => static fn (TestRunner $t) => $t->same(['autoload', 'blog_id'], $plan237()['stat4TrailingPayloadFence']['trailingColumns']),
    'planner stat4 expression partial current source next237 selected trailing columns' => static fn (TestRunner $t) => $t->same(['autoload', 'blog_id'], $plan237()['selectedPlan']['next237TrailingColumns']),
    'planner stat4 expression partial current source next237 sample count' => static fn (TestRunner $t) => $t->same(6, $plan237()['stat4TrailingPayloadFence']['sampleRowCount']),
    'planner stat4 expression partial current source next237 sample rowids' => static fn (TestRunner $t) => $t->same([10, 40, 20, 50, 30, 60], array_column($plan237()['stat4TrailingPayloadFence']['sampleRowProofs'], 'sampleRowid')),
    'planner stat4 expression partial current source next237 matched rowids' => static fn (TestRunner $t) => $t->same([20, 50, 30], $plan237()['stat4TrailingPayloadFence']['matchedTrailingRowids']),
    'planner stat4 expression partial current source next237 selected matched rowids' => static fn (TestRunner $t) => $t->same([20, 50, 30], $plan237()['selectedPlan']['next237MatchedTrailingRowids']),
    'planner stat4 expression partial current source next237 all rows resolve' => static fn (TestRunner $t) => $t->same(true, $plan237()['stat4TrailingPayloadFence']['allTrailingPayloadRowsResolveToCurrentSource']),
    'planner stat4 expression partial current source next237 all trailing matches' => static fn (TestRunner $t) => $t->same(true, $plan237()['stat4TrailingPayloadFence']['allTrailingPayloadsMatchCurrentRows']),
    'planner stat4 expression partial current source next237 matched compatible' => static fn (TestRunner $t) => $t->same(true, $plan237()['stat4TrailingPayloadFence']['matchedSamplesRemainTrailingCompatible']),
    'planner stat4 expression partial current source next237 no missing' => static fn (TestRunner $t) => $t->same([], $plan237()['stat4TrailingPayloadFence']['missingCurrentTrailingPayloadRowids']),
    'planner stat4 expression partial current source next237 no rejected' => static fn (TestRunner $t) => $t->same([], $plan237()['stat4TrailingPayloadFence']['sampleRowidsRejectedByTrailingPayload']),
    'planner stat4 expression partial current source next237 no matched rejected' => static fn (TestRunner $t) => $t->same([], $plan237()['stat4TrailingPayloadFence']['matchedSampleRowidsRejectedByTrailingPayload']),
    'planner stat4 expression partial current source next237 first column proof names' => static fn (TestRunner $t) => $t->same(['autoload', 'blog_id'], array_column($plan237()['stat4TrailingPayloadFence']['sampleRowProofs'][0]['trailingColumnProofs'], 'column')),
    'planner stat4 expression partial current source next237 first sample values' => static fn (TestRunner $t) => $t->same(['yes', 1], array_column($plan237()['stat4TrailingPayloadFence']['sampleRowProofs'][0]['trailingColumnProofs'], 'sampleValue')),
    'planner stat4 expression partial current source next237 first current values' => static fn (TestRunner $t) => $t->same(['yes', 1], array_column($plan237()['stat4TrailingPayloadFence']['sampleRowProofs'][0]['trailingColumnProofs'], 'currentValue')),
    'planner stat4 expression partial current source next237 first proof flags' => static fn (TestRunner $t) => $t->same([true, true], array_column($plan237()['stat4TrailingPayloadFence']['sampleRowProofs'][0]['trailingColumnProofs'], 'matches')),
    'planner stat4 expression partial current source next237 all proof flags' => static fn (TestRunner $t) => $t->same([true, true, true, true, true, true], array_column($plan237()['stat4TrailingPayloadFence']['sampleRowProofs'], 'trailingPayloadMatchesCurrentRow')),
    'planner stat4 expression partial current source next237 matched flags' => static fn (TestRunner $t) => $t->same([false, false, true, true, true, false], array_column($plan237()['stat4TrailingPayloadFence']['sampleRowProofs'], 'matchedBySelectedPage')),
    'planner stat4 expression partial current source next237 neq vector' => static fn (TestRunner $t) => $t->same([3, 1, 1, 1], $plan237()['stat4TrailingPayloadFence']['sampleRowProofs'][2]['neqVector']),
    'planner stat4 expression partial current source next237 nlt vector' => static fn (TestRunner $t) => $t->same([5, 3, 3, 3], $plan237()['stat4TrailingPayloadFence']['sampleRowProofs'][3]['nltVector']),
    'planner stat4 expression partial current source next237 signature lengths' => static fn (TestRunner $t) => $t->same([64, 64], [strlen($plan237()['stat4TrailingPayloadFence']['trailingPayloadSignature']), strlen($plan237()['stat4TrailingPayloadFence']['proofSignature'])]),
    'planner stat4 expression partial current source next237 selected trailing signature' => static fn (TestRunner $t) => $t->same($plan237()['stat4TrailingPayloadFence']['trailingPayloadSignature'], $plan237()['selectedPlan']['next237TrailingPayloadSignature']),
    'planner stat4 expression partial current source next237 selected proof signature' => static fn (TestRunner $t) => $t->same($plan237()['stat4TrailingPayloadFence']['proofSignature'], $plan237()['selectedPlan']['next237ProofSignature']),
    'planner stat4 expression partial current source next237 stat4 trailing signature' => static fn (TestRunner $t) => $t->same($plan237()['stat4TrailingPayloadFence']['trailingPayloadSignature'], $plan237()['stat4Fence']['next237TrailingPayloadSignature']),
    'planner stat4 expression partial current source next237 stat4 proof signature' => static fn (TestRunner $t) => $t->same($plan237()['stat4TrailingPayloadFence']['proofSignature'], $plan237()['stat4Fence']['next237ProofSignature']),
    'planner stat4 expression partial current source next237 cursor appended' => static fn (TestRunner $t) => $t->same('RecheckCurrentStat4TrailingPayloads', $plan237()['cursorProgram'][array_key_last($plan237()['cursorProgram'])]['opcode']),
    'planner stat4 expression partial current source next237 cursor mode' => static fn (TestRunner $t) => $t->same('next237-current-source-stat4-expression-partial-trailing-payload', $plan237()['cursorProgram'][array_key_last($plan237()['cursorProgram'])]['mode']),
    'planner stat4 expression partial current source next237 cursor columns' => static fn (TestRunner $t) => $t->same(['autoload', 'blog_id'], $plan237()['cursorProgram'][array_key_last($plan237()['cursorProgram'])]['trailingColumns']),
    'planner stat4 expression partial current source next237 cursor sample rowids' => static fn (TestRunner $t) => $t->same([10, 40, 20, 50, 30, 60], $plan237()['cursorProgram'][array_key_last($plan237()['cursorProgram'])]['sampleRowids']),
    'planner stat4 expression partial current source next237 cursor matched rowids' => static fn (TestRunner $t) => $t->same([20, 50, 30], $plan237()['cursorProgram'][array_key_last($plan237()['cursorProgram'])]['matchedTrailingRowids']),
    'planner stat4 expression partial current source next237 cursor signature' => static fn (TestRunner $t) => $t->same($plan237()['stat4TrailingPayloadFence']['proofSignature'], $plan237()['cursorProgram'][array_key_last($plan237()['cursorProgram'])]['signature']),
    'planner stat4 expression partial current source next237 matched rowids preserved' => static fn (TestRunner $t) => $t->same([30, 50, 20, 21, 22], $plan237()['matchedRowids']),
    'planner stat4 expression partial current source next237 projected payload retained' => static fn (TestRunner $t) => $t->same('forms-copy-b', $plan237()['projectedRows'][4]['option_value']),
    'planner stat4 expression partial current source next237 dependency marker' => static fn (TestRunner $t) => $t->true(in_array('sqlite-sqlplanner-stat4-expression-partial-current-source-next237', $plan237()['dependencies'], true)),
    'planner stat4 expression partial current source next237 dependency closure' => static fn (TestRunner $t) => $t->contains('no new support component needed', $plan237()['dependency_closure']),
    'planner stat4 expression partial current source next237 non overlap' => static fn (TestRunner $t) => $t->contains('trailing-payload validation', $plan237()['non_overlap']),
    'planner stat4 expression partial current source next237 detail' => static fn (TestRunner $t) => $t->contains('NEXT237 TRAILING PAYLOAD FENCE', $plan237()['detail']),
    'planner stat4 expression partial current source next237 stale autoload blocked' => static fn (TestRunner $t) => $t->same('requires-current-source-stat4-trailing-payload-reprepare', $staleAutoload237()['status']),
    'planner stat4 expression partial current source next237 stale autoload rowid' => static fn (TestRunner $t) => $t->same([50], $staleAutoload237()['stat4TrailingPayloadFence']['sampleRowidsRejectedByTrailingPayload']),
    'planner stat4 expression partial current source next237 stale autoload matched rowid' => static fn (TestRunner $t) => $t->same([], $staleAutoload237()['stat4TrailingPayloadFence']['matchedSampleRowidsRejectedByTrailingPayload']),
    'planner stat4 expression partial current source next237 stale autoload proof' => static fn (TestRunner $t) => $t->same(['yes', 'critical', false], [$staleAutoload237()['stat4TrailingPayloadFence']['sampleRowProofs'][3]['trailingColumnProofs'][0]['sampleValue'], $staleAutoload237()['stat4TrailingPayloadFence']['sampleRowProofs'][3]['trailingColumnProofs'][0]['currentValue'], $staleAutoload237()['stat4TrailingPayloadFence']['sampleRowProofs'][3]['trailingColumnProofs'][0]['matches']]),
    'planner stat4 expression partial current source next237 stale autoload no cursor' => static fn (TestRunner $t) => $t->same(false, in_array('RecheckCurrentStat4TrailingPayloads', array_column($staleAutoload237()['cursorProgram'], 'opcode'), true)),
    'planner stat4 expression partial current source next237 stale blog blocked' => static fn (TestRunner $t) => $t->same('requires-current-source-stat4-trailing-payload-reprepare', $staleBlog237()['status']),
    'planner stat4 expression partial current source next237 stale blog rowid' => static fn (TestRunner $t) => $t->same([20], $staleBlog237()['stat4TrailingPayloadFence']['sampleRowidsRejectedByTrailingPayload']),
    'planner stat4 expression partial current source next237 stale blog selected rowid' => static fn (TestRunner $t) => $t->same([20], $staleBlog237()['selectedPlan']['next237RowsRejectedByTrailingPayload']),
    'planner stat4 expression partial current source next237 missing blocked' => static fn (TestRunner $t) => $t->same('requires-current-source-stat4-trailing-payload-reprepare', $missingPayload237()['status']),
    'planner stat4 expression partial current source next237 missing rowid' => static fn (TestRunner $t) => $t->same([40], $missingPayload237()['stat4TrailingPayloadFence']['missingCurrentTrailingPayloadRowids']),
    'planner stat4 expression partial current source next237 missing selected rowid' => static fn (TestRunner $t) => $t->same([40], $missingPayload237()['selectedPlan']['next237MissingCurrentTrailingPayloadRowids']),
    'planner stat4 expression partial current source next237 invalid trailing' => static function (TestRunner $t) use ($badTrailing237): void {
        $t->throws(InvalidArgumentException::class, $badTrailing237);
    },
];

foreach (range(1, 18) as $case) {
    $tests['planner stat4 expression partial current source next237 repeated trailing payload fence ' . $case] = static function (TestRunner $t) use ($plan237, $case): void {
        $plan = $plan237(1 + ($case % 5), $case % 4);
        $t->same($plan['stat4TrailingPayloadFence']['proofSignature'], $plan['selectedPlan']['next237ProofSignature']);
    };
}

return $tests;

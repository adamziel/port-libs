<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePlannerStat4ExpressionPartialCurrentSourceNext240Plan;

$eq240 = static fn (string $column, mixed $right): array => ['left' => ['column' => $column], 'operator' => '=', 'right' => $right];
$like240 = static fn (string $column, string $right): array => ['left' => ['column' => $column], 'operator' => 'LIKE', 'right' => $right];
$notNull240 = static fn (string $column): array => ['left' => ['column' => $column], 'operator' => 'IS NOT NULL'];
$between240 = static fn (string $expression, mixed $lower, mixed $upper): array => ['left' => ['expression' => $expression], 'operator' => 'BETWEEN', 'lower' => $lower, 'upper' => $upper];
$payload240 = static fn (array $row): array => [
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

$prepared240 = static fn (): array => [
    'name' => 'prepared-wp-options-stat4-partial-predicate-next240',
    'schemaCookie' => 2400,
    'stat4Generation' => 240,
    'rows' => [
        ['rowid' => 10, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_alpha', 'option_value' => 'alpha-old', 'updated_at' => 10, 'kind' => 'plugin'],
        ['rowid' => 20, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_forms', 'option_value' => 'forms-old', 'updated_at' => 20, 'kind' => 'plugin'],
        ['rowid' => 30, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_mail', 'option_value' => 'mail-old', 'updated_at' => 30, 'kind' => 'plugin'],
    ],
    'indexes' => [[
        'name' => 'idx_wp_options_stat4_current_partial_next240',
        'rootPage' => 24001,
        'expression' => 'lower(option_name)',
        'expressionColumn' => '__expr_lower_option_name',
        'collation' => 'BINARY',
        'descending' => true,
        'partialPredicateTerms' => [
            ['left' => ['expression' => 'lower(option_name)'], 'operator' => '>=', 'right' => 'plugin_alpha'],
            ['left' => ['expression' => 'lower(option_name)'], 'operator' => '<=', 'right' => 'plugin_zulu'],
            ['left' => ['column' => 'autoload'], 'operator' => '=', 'right' => 'yes'],
            ['left' => ['column' => 'option_name'], 'operator' => 'IS NOT NULL'],
            ['left' => ['column' => 'kind'], 'operator' => '=', 'right' => 'plugin'],
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
        ],
        'coveringColumns' => ['option_name', 'option_value', 'updated_at', 'autoload', 'blog_id'],
        'stat4Samples' => [
            ['neq' => '1 1 1 1', 'nlt' => '0 0 0 0', 'ndlt' => '0 0 0 0', 'sample' => ['plugin_alpha', 10, 'yes', 1]],
            ['neq' => '2 1 1 1', 'nlt' => '1 1 1 1', 'ndlt' => '1 1 1 1', 'sample' => ['plugin_forms', 20, 'yes', 1]],
            ['neq' => '1 1 1 1', 'nlt' => '3 2 2 2', 'ndlt' => '2 2 2 2', 'sample' => ['plugin_mail', 30, 'yes', 1]],
        ],
        'stat4ExpressionPayloads' => [],
    ]],
];

$current240 = static function () use ($prepared240, $payload240): array {
    $source = $prepared240();
    $source['name'] = 'current-wp-options-stat4-partial-predicate-next240';
    $source['schemaCookie'] = 2409;
    $source['stat4Generation'] = 340;
    $source['indexes'][0]['rootPage'] = 24088;
    $source['indexes'][0]['partialPredicateTerms'] = [
        ['left' => ['expression' => 'LOWER(option_name)'], 'operator' => '>=', 'right' => 'plugin_alpha'],
        ['left' => ['expression' => 'LOWER(option_name)'], 'operator' => '<=', 'right' => 'plugin_tango'],
        ['left' => ['column' => 'autoload'], 'operator' => '=', 'right' => 'yes'],
        ['left' => ['column' => 'option_name'], 'operator' => 'IS NOT NULL'],
        ['left' => ['column' => 'blog_id'], 'operator' => '=', 'right' => 1],
    ];
    $source['indexes'][0]['stat4Samples'] = [
        ['neq' => '1 1 1 1', 'nlt' => '0 0 0 0', 'ndlt' => '0 0 0 0', 'sample' => ['plugin_alpha', 10, 'yes', 1]],
        ['neq' => '2 1 1 1', 'nlt' => '1 1 1 1', 'ndlt' => '1 1 1 1', 'sample' => ['plugin_forms', 20, 'yes', 1]],
        ['neq' => '1 1 1 1', 'nlt' => '3 2 2 2', 'ndlt' => '2 2 2 2', 'sample' => ['plugin_mail', 30, 'yes', 1]],
        ['neq' => '1 1 1 1', 'nlt' => '4 3 3 3', 'ndlt' => '3 3 3 3', 'sample' => ['plugin_seo', 40, 'yes', 1]],
    ];
    $source['rows'] = [
        ['rowid' => 40, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_seo', 'option_value' => 'seo', 'updated_at' => 40, 'kind' => 'extension'],
        ['rowid' => 30, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_mail', 'option_value' => 'mail', 'updated_at' => 30, 'kind' => 'extension'],
        ['rowid' => 20, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'Plugin_Forms', 'option_value' => 'forms', 'updated_at' => 20, 'kind' => 'extension'],
        ['rowid' => 21, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_forms', 'option_value' => 'forms-copy', 'updated_at' => 21, 'kind' => 'extension'],
        ['rowid' => 10, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_alpha', 'option_value' => 'alpha', 'updated_at' => 10, 'kind' => 'extension'],
        ['rowid' => 70, 'blog_id' => 1, 'autoload' => 'no', 'option_name' => 'plugin_forms', 'option_value' => 'lazy', 'updated_at' => 70, 'kind' => 'extension'],
        ['rowid' => 80, 'blog_id' => 2, 'autoload' => 'yes', 'option_name' => 'plugin_forms', 'option_value' => 'other-blog', 'updated_at' => 80, 'kind' => 'extension'],
    ];
    $source['indexes'][0]['stat4ExpressionPayloads'] = array_map($payload240, array_slice($source['rows'], 0, 5));

    return $source;
};

$terms240 = static fn (): array => [
    $between240('LOWER(option_name)', 'plugin_alpha', 'plugin_tango'),
    $eq240('autoload', 'yes'),
    $notNull240('option_name'),
    $eq240('blog_id', 1),
    $like240('option_name', 'plugin_%'),
];
$plan240 = static fn (int $limit = 4, int $offset = 0, ?array $prepared = null, ?array $current = null, ?array $terms = null): array => SQLitePlannerStat4ExpressionPartialCurrentSourceNext240Plan::materialize(
    $prepared ?? $prepared240(),
    $current ?? $current240(),
    $terms ?? $terms240(),
    ['option_name', 'option_value', 'updated_at', 'blog_id'],
    ['autoload', 'blog_id'],
    $limit,
    $offset,
);
$missingCurrentPredicate240 = static function () use ($terms240, $plan240): array {
    $terms = array_values(array_filter($terms240(), static fn (array $term): bool => ($term['left']['column'] ?? null) !== 'blog_id'));

    return $plan240(4, 0, null, null, $terms);
};
$stalePreparedPredicate240 = static function () use ($terms240, $eq240, $plan240): array {
    $terms = $terms240();
    $terms[] = $eq240('kind', 'plugin');

    return $plan240(4, 0, null, null, $terms);
};
$badCurrentTerms240 = static function () use ($current240, $plan240): array {
    $current = $current240();
    $current['indexes'][0]['partialPredicateTerms'] = 'bad';

    return $plan240(4, 0, null, $current);
};

$tests = [
    'planner stat4 expression partial current source next240 status ready' => static fn (TestRunner $t) => $t->same('stat4-expression-partial-current-source-next240-ready', $plan240()['status']),
    'planner stat4 expression partial current source next240 selected current' => static fn (TestRunner $t) => $t->same('current', $plan240()['selectedSource']),
    'planner stat4 expression partial current source next240 inherited next237 payload fence' => static fn (TestRunner $t) => $t->same(true, $plan240()['stat4TrailingPayloadFence']['matchedSamplesRemainTrailingCompatible']),
    'planner stat4 expression partial current source next240 ready flag' => static fn (TestRunner $t) => $t->same(true, $plan240()['selectedPlan']['next240Ready']),
    'planner stat4 expression partial current source next240 selected index' => static fn (TestRunner $t) => $t->same('idx_wp_options_stat4_current_partial_next240', $plan240()['selectedPlan']['name']),
    'planner stat4 expression partial current source next240 root page' => static fn (TestRunner $t) => $t->same(24088, $plan240()['selectedPlan']['rootPage']),
    'planner stat4 expression partial current source next240 current implied' => static fn (TestRunner $t) => $t->same(true, $plan240()['stat4CurrentPartialPredicateFence']['currentPartialPredicateImplied']),
    'planner stat4 expression partial current source next240 current only count' => static fn (TestRunner $t) => $t->same(2, count($plan240()['stat4CurrentPartialPredicateFence']['currentOnlyPredicates'])),
    'planner stat4 expression partial current source next240 prepared only count' => static fn (TestRunner $t) => $t->same(2, count($plan240()['stat4CurrentPartialPredicateFence']['preparedOnlyPredicates'])),
    'planner stat4 expression partial current source next240 no unsupported' => static fn (TestRunner $t) => $t->same([], $plan240()['stat4CurrentPartialPredicateFence']['unsupportedCurrentPartialPredicates']),
    'planner stat4 expression partial current source next240 no stale prepared used' => static fn (TestRunner $t) => $t->same([], $plan240()['stat4CurrentPartialPredicateFence']['stalePreparedOnlyPredicatesUsed']),
    'planner stat4 expression partial current source next240 no missing current' => static fn (TestRunner $t) => $t->same([], $plan240()['stat4CurrentPartialPredicateFence']['missingCurrentPartialPredicates']),
    'planner stat4 expression partial current source next240 proof count' => static fn (TestRunner $t) => $t->same(5, count($plan240()['stat4CurrentPartialPredicateFence']['proofs'])),
    'planner stat4 expression partial current source next240 exact proof reason' => static fn (TestRunner $t) => $t->same('exact', $plan240()['stat4CurrentPartialPredicateFence']['proofs'][2]['reason']),
    'planner stat4 expression partial current source next240 range proof present' => static fn (TestRunner $t) => $t->true(in_array('between-implies-range', array_column($plan240()['stat4CurrentPartialPredicateFence']['proofs'], 'reason'), true)),
    'planner stat4 expression partial current source next240 not null proof accepted' => static fn (TestRunner $t) => $t->same(true, $plan240()['stat4CurrentPartialPredicateFence']['proofs'][3]['implied']),
    'planner stat4 expression partial current source next240 current only has upper' => static fn (TestRunner $t) => $t->true(in_array('plugin_tango', array_column($plan240()['stat4CurrentPartialPredicateFence']['currentOnlyPredicates'], 'right'), true)),
    'planner stat4 expression partial current source next240 current only has blog' => static fn (TestRunner $t) => $t->true(in_array('blog_id', array_map(static fn (array $term): ?string => $term['left']['column'] ?? null, $plan240()['stat4CurrentPartialPredicateFence']['currentOnlyPredicates']), true)),
    'planner stat4 expression partial current source next240 prepared only has upper' => static fn (TestRunner $t) => $t->true(in_array('plugin_zulu', array_column($plan240()['stat4CurrentPartialPredicateFence']['preparedOnlyPredicates'], 'right'), true)),
    'planner stat4 expression partial current source next240 prepared only has kind' => static fn (TestRunner $t) => $t->true(in_array('kind', array_map(static fn (array $term): ?string => $term['left']['column'] ?? null, $plan240()['stat4CurrentPartialPredicateFence']['preparedOnlyPredicates']), true)),
    'planner stat4 expression partial current source next240 signature lengths' => static fn (TestRunner $t) => $t->same([64, 64, 64, 64], [strlen($plan240()['stat4CurrentPartialPredicateFence']['preparedPartialSignature']), strlen($plan240()['stat4CurrentPartialPredicateFence']['currentPartialSignature']), strlen($plan240()['stat4CurrentPartialPredicateFence']['whereSignature']), strlen($plan240()['stat4CurrentPartialPredicateFence']['proofSignature'])]),
    'planner stat4 expression partial current source next240 selected current signature' => static fn (TestRunner $t) => $t->same($plan240()['stat4CurrentPartialPredicateFence']['currentPartialSignature'], $plan240()['selectedPlan']['next240CurrentPartialSignature']),
    'planner stat4 expression partial current source next240 selected prepared signature' => static fn (TestRunner $t) => $t->same($plan240()['stat4CurrentPartialPredicateFence']['preparedPartialSignature'], $plan240()['selectedPlan']['next240PreparedPartialSignature']),
    'planner stat4 expression partial current source next240 selected where signature' => static fn (TestRunner $t) => $t->same($plan240()['stat4CurrentPartialPredicateFence']['whereSignature'], $plan240()['selectedPlan']['next240WhereSignature']),
    'planner stat4 expression partial current source next240 selected proof signature' => static fn (TestRunner $t) => $t->same($plan240()['stat4CurrentPartialPredicateFence']['proofSignature'], $plan240()['selectedPlan']['next240ProofSignature']),
    'planner stat4 expression partial current source next240 stat4 ready flag' => static fn (TestRunner $t) => $t->same(true, $plan240()['stat4Fence']['next240CurrentPartialPredicateReady']),
    'planner stat4 expression partial current source next240 stat4 proof signature' => static fn (TestRunner $t) => $t->same($plan240()['stat4CurrentPartialPredicateFence']['proofSignature'], $plan240()['stat4Fence']['next240ProofSignature']),
    'planner stat4 expression partial current source next240 cursor appended' => static fn (TestRunner $t) => $t->same('RecheckCurrentPartialPredicateImplication', $plan240()['cursorProgram'][array_key_last($plan240()['cursorProgram'])]['opcode']),
    'planner stat4 expression partial current source next240 cursor mode' => static fn (TestRunner $t) => $t->same('next240-current-source-stat4-expression-partial-predicate', $plan240()['cursorProgram'][array_key_last($plan240()['cursorProgram'])]['mode']),
    'planner stat4 expression partial current source next240 cursor counts' => static fn (TestRunner $t) => $t->same([2, 2], [$plan240()['cursorProgram'][array_key_last($plan240()['cursorProgram'])]['currentOnlyCount'], $plan240()['cursorProgram'][array_key_last($plan240()['cursorProgram'])]['preparedOnlyCount']]),
    'planner stat4 expression partial current source next240 cursor signature' => static fn (TestRunner $t) => $t->same($plan240()['stat4CurrentPartialPredicateFence']['proofSignature'], $plan240()['cursorProgram'][array_key_last($plan240()['cursorProgram'])]['signature']),
    'planner stat4 expression partial current source next240 matched rowids' => static fn (TestRunner $t) => $t->same([10, 20, 21, 30, 40], $plan240()['matchedRowids']),
    'planner stat4 expression partial current source next240 projected current row' => static fn (TestRunner $t) => $t->same('mail', $plan240()['projectedRows'][3]['option_value']),
    'planner stat4 expression partial current source next240 trailing payload still ready' => static fn (TestRunner $t) => $t->same(true, $plan240()['stat4TrailingPayloadFence']['matchedSamplesRemainTrailingCompatible']),
    'planner stat4 expression partial current source next240 dependency marker' => static fn (TestRunner $t) => $t->true(in_array('sqlite-sqlplanner-stat4-expression-partial-current-source-next240', $plan240()['dependencies'], true)),
    'planner stat4 expression partial current source next240 dependency closure' => static fn (TestRunner $t) => $t->contains('no new support component needed', $plan240()['dependency_closure']),
    'planner stat4 expression partial current source next240 non overlap' => static fn (TestRunner $t) => $t->contains('partial predicate implication', $plan240()['non_overlap']),
    'planner stat4 expression partial current source next240 detail' => static fn (TestRunner $t) => $t->contains('NEXT240 PARTIAL PREDICATE FENCE', $plan240()['detail']),
    'planner stat4 expression partial current source next240 missing predicate blocked' => static fn (TestRunner $t) => $t->same('requires-current-source-partial-predicate-reprepare', $missingCurrentPredicate240()['status']),
    'planner stat4 expression partial current source next240 missing reason' => static fn (TestRunner $t) => $t->true(in_array('missing-exact-current-partial-term', array_column($missingCurrentPredicate240()['stat4CurrentPartialPredicateFence']['proofs'], 'reason'), true)),
    'planner stat4 expression partial current source next240 missing selected unsupported empty' => static fn (TestRunner $t) => $t->same([], $missingCurrentPredicate240()['selectedPlan']['next240UnsupportedCurrentPartialPredicates']),
    'planner stat4 expression partial current source next240 missing no cursor' => static fn (TestRunner $t) => $t->same(false, in_array('RecheckCurrentPartialPredicateImplication', array_column($missingCurrentPredicate240()['cursorProgram'], 'opcode'), true)),
    'planner stat4 expression partial current source next240 stale prepared blocked' => static fn (TestRunner $t) => $t->same('requires-current-source-partial-predicate-reprepare', $stalePreparedPredicate240()['status']),
    'planner stat4 expression partial current source next240 stale prepared column' => static fn (TestRunner $t) => $t->same('kind', $stalePreparedPredicate240()['stat4CurrentPartialPredicateFence']['stalePreparedOnlyPredicatesUsed'][0]['left']['column']),
    'planner stat4 expression partial current source next240 stale prepared selected column' => static fn (TestRunner $t) => $t->same('kind', $stalePreparedPredicate240()['selectedPlan']['next240StalePreparedOnlyPredicatesUsed'][0]['left']['column']),
    'planner stat4 expression partial current source next240 bad current terms throws' => static function (TestRunner $t) use ($badCurrentTerms240): void {
        $t->throws(InvalidArgumentException::class, $badCurrentTerms240);
    },
];

foreach (range(1, 18) as $case) {
    $tests['planner stat4 expression partial current source next240 repeated current predicate fence ' . $case] = static function (TestRunner $t) use ($plan240, $case): void {
        $plan = $plan240(1 + ($case % 4), $case % 3);
        $t->same($plan['stat4CurrentPartialPredicateFence']['proofSignature'], $plan['selectedPlan']['next240ProofSignature']);
    };
}

return $tests;

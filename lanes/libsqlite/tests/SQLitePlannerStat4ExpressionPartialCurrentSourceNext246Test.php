<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan;

$eq246 = static fn (string $column, mixed $right): array => ['left' => ['column' => $column], 'operator' => '=', 'right' => $right];
$like246 = static fn (string $column, string $right): array => ['left' => ['column' => $column], 'operator' => 'LIKE', 'right' => $right];
$notNull246 = static fn (string $column): array => ['left' => ['column' => $column], 'operator' => 'IS NOT NULL'];
$between246 = static fn (string $expression, mixed $lower, mixed $upper): array => ['left' => ['expression' => $expression], 'operator' => 'BETWEEN', 'lower' => $lower, 'upper' => $upper];
$payload246 = static fn (array $row): array => [
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

$prepared246 = static fn (): array => [
    'name' => 'prepared-wp-options-stat4-cardinality-next246',
    'schemaCookie' => 2460,
    'stat4Generation' => 246,
    'rows' => [
        ['rowid' => 10, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_alpha', 'option_value' => 'alpha-old', 'updated_at' => 10, 'kind' => 'plugin'],
        ['rowid' => 20, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_forms', 'option_value' => 'forms-old', 'updated_at' => 20, 'kind' => 'plugin'],
        ['rowid' => 30, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_mail', 'option_value' => 'mail-old', 'updated_at' => 30, 'kind' => 'plugin'],
    ],
    'indexes' => [[
        'name' => 'idx_wp_options_stat4_cardinality_next246',
        'rootPage' => 24601,
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
            ['neq' => '1 1 1 1', 'nlt' => '0 0 0 0', 'ndlt' => '0 0 0 0', 'sample' => ['plugin_alpha', 10, 'yes', 1]],
            ['neq' => '1 1 1 1', 'nlt' => '1 1 1 1', 'ndlt' => '1 1 1 1', 'sample' => ['plugin_forms', 20, 'yes', 1]],
            ['neq' => '1 1 1 1', 'nlt' => '2 2 2 2', 'ndlt' => '2 2 2 2', 'sample' => ['plugin_mail', 30, 'yes', 1]],
        ],
        'stat4ExpressionPayloads' => [],
    ]],
];

$current246 = static function (string $variant = 'ready') use ($prepared246, $payload246): array {
    $source = $prepared246();
    $source['name'] = 'current-wp-options-stat4-cardinality-next246';
    $source['schemaCookie'] = 2469;
    $source['stat4Generation'] = 346;
    $source['indexes'][0]['rootPage'] = 24688;
    $source['indexes'][0]['partialPredicateTerms'] = [
        ['left' => ['expression' => 'LOWER(option_name)'], 'operator' => '>=', 'right' => 'plugin_alpha'],
        ['left' => ['expression' => 'LOWER(option_name)'], 'operator' => '<=', 'right' => 'plugin_tango'],
        ['left' => ['column' => 'autoload'], 'operator' => '=', 'right' => 'yes'],
        ['left' => ['column' => 'option_name'], 'operator' => 'IS NOT NULL'],
        ['left' => ['column' => 'blog_id'], 'operator' => '=', 'right' => 1],
    ];
    $source['rows'] = [
        ['rowid' => 40, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_seo', 'option_value' => 'seo', 'updated_at' => 40, 'kind' => 'extension'],
        ['rowid' => 30, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_mail', 'option_value' => 'mail', 'updated_at' => 30, 'kind' => 'extension'],
        ['rowid' => 20, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'Plugin_Forms', 'option_value' => 'forms', 'updated_at' => 20, 'kind' => 'extension'],
        ['rowid' => 21, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_forms', 'option_value' => 'forms-copy-a', 'updated_at' => 21, 'kind' => 'extension'],
        ['rowid' => 22, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'PLUGIN_FORMS', 'option_value' => 'forms-copy-b', 'updated_at' => 22, 'kind' => 'extension'],
        ['rowid' => 10, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_alpha', 'option_value' => 'alpha', 'updated_at' => 10, 'kind' => 'extension'],
        ['rowid' => 70, 'blog_id' => 1, 'autoload' => 'no', 'option_name' => 'plugin_forms', 'option_value' => 'lazy', 'updated_at' => 70, 'kind' => 'extension'],
        ['rowid' => 80, 'blog_id' => 2, 'autoload' => 'yes', 'option_name' => 'plugin_forms', 'option_value' => 'other-blog', 'updated_at' => 80, 'kind' => 'extension'],
    ];
    $source['indexes'][0]['stat4Samples'] = [
        ['neq' => '1 1 1 1', 'nlt' => '0 0 0 0', 'ndlt' => '0 0 0 0', 'sample' => ['plugin_alpha', 10, 'yes', 1]],
        ['neq' => '3 1 1 1', 'nlt' => '1 1 1 1', 'ndlt' => '1 1 1 1', 'sample' => ['plugin_forms', 20, 'yes', 1]],
        ['neq' => '1 1 1 1', 'nlt' => '4 2 2 2', 'ndlt' => '2 2 2 2', 'sample' => ['plugin_mail', 30, 'yes', 1]],
        ['neq' => '1 1 1 1', 'nlt' => '5 3 3 3', 'ndlt' => '3 3 3 3', 'sample' => ['plugin_seo', 40, 'yes', 1]],
    ];
    if ($variant === 'stale-neq') {
        $source['indexes'][0]['stat4Samples'][1]['neq'] = '2 1 1 1';
    }
    if ($variant === 'missing-duplicate-sample') {
        array_splice($source['indexes'][0]['stat4Samples'], 1, 1);
    }
    if ($variant === 'malformed-neq') {
        $source['indexes'][0]['stat4Samples'][1]['neq'] = 'three 1 1 1';
    }
    if ($variant === 'no-duplicate-current') {
        $source['rows'] = array_values(array_filter(
            $source['rows'],
            static fn (array $row): bool => !in_array((int) $row['rowid'], [21, 22], true),
        ));
        $source['indexes'][0]['stat4Samples'][1]['neq'] = '1 1 1 1';
    }
    $source['indexes'][0]['stat4ExpressionPayloads'] = array_map($payload246, array_slice($source['rows'], 0, 6));

    return $source;
};

$terms246 = static fn (): array => [
    $between246('LOWER(option_name)', 'plugin_alpha', 'plugin_tango'),
    $eq246('autoload', 'yes'),
    $notNull246('option_name'),
    $eq246('blog_id', 1),
    $like246('option_name', 'plugin_%'),
];
$plan246 = static fn (string $variant = 'ready', int $limit = 6, int $offset = 0): array => SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan::materializeCurrentSourceDuplicateCardinalityValidation(
    $prepared246(),
    $current246($variant),
    $terms246(),
    ['option_name', 'option_value', 'updated_at', 'blog_id'],
    ['autoload', 'blog_id'],
    $limit,
    $offset,
);

$tests = [
    'planner stat4 expression partial current source next246 status ready' => static fn (TestRunner $t) => $t->same('stat4-expression-partial-current-source-next246-ready', $plan246()['status']),
    'planner stat4 expression partial current source next246 inherits next243' => static fn (TestRunner $t) => $t->same(true, $plan246()['selectedPlan']['next243Ready']),
    'planner stat4 expression partial current source next246 ready flag' => static fn (TestRunner $t) => $t->same(true, $plan246()['selectedPlan']['next246Ready']),
    'planner stat4 expression partial current source next246 selected current' => static fn (TestRunner $t) => $t->same('current', $plan246()['selectedSource']),
    'planner stat4 expression partial current source next246 selected index' => static fn (TestRunner $t) => $t->same('idx_wp_options_stat4_cardinality_next246', $plan246()['selectedPlan']['name']),
    'planner stat4 expression partial current source next246 root page' => static fn (TestRunner $t) => $t->same(24688, $plan246()['selectedPlan']['rootPage']),
    'planner stat4 expression partial current source next246 matched rowids' => static fn (TestRunner $t) => $t->same([10, 20, 21, 22, 30, 40], $plan246()['matchedRowids']),
    'planner stat4 expression partial current source next246 projected duplicate payload' => static fn (TestRunner $t) => $t->same('forms-copy-b', $plan246()['projectedRows'][3]['option_value']),
    'planner stat4 expression partial current source next246 duplicate keys' => static fn (TestRunner $t) => $t->same(['plugin_forms'], $plan246()['stat4DuplicateCardinalityFence']['duplicateExpressionKeys']),
    'planner stat4 expression partial current source next246 selected duplicate keys' => static fn (TestRunner $t) => $t->same(['plugin_forms'], $plan246()['selectedPlan']['next246DuplicateExpressionKeys']),
    'planner stat4 expression partial current source next246 actual alpha count' => static fn (TestRunner $t) => $t->same(1, $plan246()['stat4DuplicateCardinalityFence']['actualExpressionCounts']['plugin_alpha']),
    'planner stat4 expression partial current source next246 actual forms count' => static fn (TestRunner $t) => $t->same(3, $plan246()['stat4DuplicateCardinalityFence']['actualExpressionCounts']['plugin_forms']),
    'planner stat4 expression partial current source next246 stat4 forms count' => static fn (TestRunner $t) => $t->same(3, $plan246()['stat4DuplicateCardinalityFence']['stat4ExpressionCounts']['plugin_forms']),
    'planner stat4 expression partial current source next246 proof count' => static fn (TestRunner $t) => $t->same(1, count($plan246()['stat4DuplicateCardinalityFence']['duplicateCardinalityProofs'])),
    'planner stat4 expression partial current source next246 proof key' => static fn (TestRunner $t) => $t->same('plugin_forms', $plan246()['stat4DuplicateCardinalityFence']['duplicateCardinalityProofs'][0]['expressionKey']),
    'planner stat4 expression partial current source next246 proof actual' => static fn (TestRunner $t) => $t->same(3, $plan246()['stat4DuplicateCardinalityFence']['duplicateCardinalityProofs'][0]['actualMatchedDuplicateCount']),
    'planner stat4 expression partial current source next246 proof neq' => static fn (TestRunner $t) => $t->same(3, $plan246()['stat4DuplicateCardinalityFence']['duplicateCardinalityProofs'][0]['stat4NeqDuplicateCount']),
    'planner stat4 expression partial current source next246 proof matches' => static fn (TestRunner $t) => $t->same(true, $plan246()['stat4DuplicateCardinalityFence']['duplicateCardinalityProofs'][0]['matches']),
    'planner stat4 expression partial current source next246 all counts match' => static fn (TestRunner $t) => $t->same(true, $plan246()['stat4DuplicateCardinalityFence']['allCurrentStat4DuplicateCountsMatch']),
    'planner stat4 expression partial current source next246 no mismatches' => static fn (TestRunner $t) => $t->same([], $plan246()['stat4DuplicateCardinalityFence']['countMismatchExpressionKeys']),
    'planner stat4 expression partial current source next246 no missing' => static fn (TestRunner $t) => $t->same([], $plan246()['stat4DuplicateCardinalityFence']['missingSampleExpressionKeys']),
    'planner stat4 expression partial current source next246 selected mismatches empty' => static fn (TestRunner $t) => $t->same([], $plan246()['selectedPlan']['next246CountMismatchExpressionKeys']),
    'planner stat4 expression partial current source next246 selected missing empty' => static fn (TestRunner $t) => $t->same([], $plan246()['selectedPlan']['next246MissingSampleExpressionKeys']),
    'planner stat4 expression partial current source next246 signature length' => static fn (TestRunner $t) => $t->same(64, strlen($plan246()['stat4DuplicateCardinalityFence']['proofSignature'])),
    'planner stat4 expression partial current source next246 selected signature' => static fn (TestRunner $t) => $t->same($plan246()['stat4DuplicateCardinalityFence']['proofSignature'], $plan246()['selectedPlan']['next246DuplicateCardinalitySignature']),
    'planner stat4 expression partial current source next246 stat4 ready' => static fn (TestRunner $t) => $t->same(true, $plan246()['stat4Fence']['next246DuplicateCardinalityReady']),
    'planner stat4 expression partial current source next246 stat4 signature' => static fn (TestRunner $t) => $t->same($plan246()['stat4DuplicateCardinalityFence']['proofSignature'], $plan246()['stat4Fence']['next246DuplicateCardinalitySignature']),
    'planner stat4 expression partial current source next246 stat4 keys' => static fn (TestRunner $t) => $t->same(['plugin_forms'], $plan246()['stat4Fence']['next246DuplicateExpressionKeys']),
    'planner stat4 expression partial current source next246 cursor appended' => static fn (TestRunner $t) => $t->same('ValidateCurrentStat4DuplicateCardinality', $plan246()['cursorProgram'][array_key_last($plan246()['cursorProgram'])]['opcode']),
    'planner stat4 expression partial current source next246 cursor mode' => static fn (TestRunner $t) => $t->same('next246-current-source-stat4-expression-partial-duplicate-cardinality', $plan246()['cursorProgram'][array_key_last($plan246()['cursorProgram'])]['mode']),
    'planner stat4 expression partial current source next246 cursor keys' => static fn (TestRunner $t) => $t->same(['plugin_forms'], $plan246()['cursorProgram'][array_key_last($plan246()['cursorProgram'])]['duplicateExpressionKeys']),
    'planner stat4 expression partial current source next246 cursor signature' => static fn (TestRunner $t) => $t->same($plan246()['stat4DuplicateCardinalityFence']['proofSignature'], $plan246()['cursorProgram'][array_key_last($plan246()['cursorProgram'])]['proofSignature']),
    'planner stat4 expression partial current source next246 sample tape preserved' => static fn (TestRunner $t) => $t->same(true, $plan246()['stat4ExpressionSampleTapeFence']['ready']),
    'planner stat4 expression partial current source next246 sample tape expanded' => static fn (TestRunner $t) => $t->same([10, 20, 21, 22, 30, 40], $plan246()['stat4ExpressionSampleTapeFence']['expandedCurrentRowids']),
    'planner stat4 expression partial current source next246 sample tape current rowids preserved' => static fn (TestRunner $t) => $t->same([10, 20, 30, 40], $plan246()['stat4ExpressionSampleTapeFence']['currentSampleRowids']),
    'planner stat4 expression partial current source next246 sample tape proof signature preserved' => static fn (TestRunner $t) => $t->same($plan246()['stat4ExpressionSampleTapeFence']['proofSignature'], $plan246()['selectedPlan']['next243ProofSignature']),
    'planner stat4 expression partial current source next246 detail' => static fn (TestRunner $t) => $t->contains('NEXT246 DUPLICATE CARDINALITY FENCE', $plan246()['detail']),
    'planner stat4 expression partial current source next246 dependency marker' => static fn (TestRunner $t) => $t->true(in_array('sqlite-sqlplanner-stat4-expression-partial-current-source-next246', $plan246()['dependencies'], true)),
    'planner stat4 expression partial current source next246 dependency closure' => static fn (TestRunner $t) => $t->contains('no new support component needed', $plan246()['dependency_closure']),
    'planner stat4 expression partial current source next246 non overlap' => static fn (TestRunner $t) => $t->contains('duplicate-cardinality validation', $plan246()['non_overlap']),
    'planner stat4 expression partial current source next246 stale neq blocks' => static fn (TestRunner $t) => $t->same('requires-current-source-stat4-duplicate-cardinality-reprepare', $plan246('stale-neq')['status']),
    'planner stat4 expression partial current source next246 stale neq mismatch key' => static fn (TestRunner $t) => $t->same(['plugin_forms'], $plan246('stale-neq')['stat4DuplicateCardinalityFence']['countMismatchExpressionKeys']),
    'planner stat4 expression partial current source next246 stale neq selected false' => static fn (TestRunner $t) => $t->same(false, $plan246('stale-neq')['selectedPlan']['next246Ready']),
    'planner stat4 expression partial current source next246 stale neq cursor not appended' => static fn (TestRunner $t) => $t->same(false, in_array('ValidateCurrentStat4DuplicateCardinality', array_column($plan246('stale-neq')['cursorProgram'], 'opcode'), true)),
    'planner stat4 expression partial current source next246 missing duplicate sample blocks' => static fn (TestRunner $t) => $t->same('requires-current-source-stat4-duplicate-cardinality-reprepare', $plan246('missing-duplicate-sample')['status']),
    'planner stat4 expression partial current source next246 missing duplicate sample key' => static fn (TestRunner $t) => $t->same(['plugin_forms'], $plan246('missing-duplicate-sample')['stat4DuplicateCardinalityFence']['missingSampleExpressionKeys']),
    'planner stat4 expression partial current source next246 no duplicate current blocks' => static fn (TestRunner $t) => $t->same('requires-current-source-stat4-duplicate-cardinality-reprepare', $plan246('no-duplicate-current')['status']),
    'planner stat4 expression partial current source next246 no duplicate keys empty' => static fn (TestRunner $t) => $t->same([], $plan246('no-duplicate-current')['stat4DuplicateCardinalityFence']['duplicateExpressionKeys']),
    'planner stat4 expression partial current source next246 no duplicate all match false' => static fn (TestRunner $t) => $t->same(false, $plan246('no-duplicate-current')['stat4DuplicateCardinalityFence']['allCurrentStat4DuplicateCountsMatch']),
    'planner stat4 expression partial current source next246 malformed neq throws' => static function (TestRunner $t) use ($plan246): void {
        $t->throws(InvalidArgumentException::class, static fn () => $plan246('malformed-neq'));
    },
    'planner stat4 expression partial current source next246 invalid limit' => static function (TestRunner $t) use ($plan246): void {
        $t->throws(InvalidArgumentException::class, static fn () => $plan246('ready', -1));
    },
    'planner stat4 expression partial current source next246 invalid offset' => static function (TestRunner $t) use ($plan246): void {
        $t->throws(InvalidArgumentException::class, static fn () => $plan246('ready', 1, -1));
    },
];

foreach (range(1, 20) as $case) {
    $tests['planner stat4 expression partial current source next246 repeated cardinality proof ' . $case] = static function (TestRunner $t) use ($plan246, $case): void {
        $plan = $plan246('ready', 1 + ($case % 6), $case % 2);
        $t->same($plan['stat4DuplicateCardinalityFence']['proofSignature'], $plan['selectedPlan']['next246DuplicateCardinalitySignature']);
    };
}

return $tests;

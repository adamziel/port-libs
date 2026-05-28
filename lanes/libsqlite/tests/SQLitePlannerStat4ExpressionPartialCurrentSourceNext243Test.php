<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePlannerStat4ExpressionPartialCurrentSourceNext243Plan;

$eq243 = static fn (string $column, mixed $right): array => ['left' => ['column' => $column], 'operator' => '=', 'right' => $right];
$like243 = static fn (string $column, string $right): array => ['left' => ['column' => $column], 'operator' => 'LIKE', 'right' => $right];
$notNull243 = static fn (string $column): array => ['left' => ['column' => $column], 'operator' => 'IS NOT NULL'];
$between243 = static fn (string $expression, mixed $lower, mixed $upper): array => ['left' => ['expression' => $expression], 'operator' => 'BETWEEN', 'lower' => $lower, 'upper' => $upper];
$payload243 = static fn (array $row): array => [
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

$prepared243 = static fn (): array => [
    'name' => 'prepared-wp-options-stat4-sample-tape-next243',
    'schemaCookie' => 2430,
    'stat4Generation' => 243,
    'rows' => [
        ['rowid' => 10, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_alpha', 'option_value' => 'alpha-old', 'updated_at' => 10, 'kind' => 'plugin'],
        ['rowid' => 20, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_forms', 'option_value' => 'forms-old', 'updated_at' => 20, 'kind' => 'plugin'],
        ['rowid' => 30, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_mail', 'option_value' => 'mail-old', 'updated_at' => 30, 'kind' => 'plugin'],
    ],
    'indexes' => [[
        'name' => 'idx_wp_options_stat4_sample_tape_next243',
        'rootPage' => 24301,
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

$current243 = static function (string $variant = 'ready') use ($prepared243, $payload243): array {
    $source = $prepared243();
    $source['name'] = 'current-wp-options-stat4-sample-tape-next243';
    $source['schemaCookie'] = 2439;
    $source['stat4Generation'] = 343;
    $source['indexes'][0]['rootPage'] = 24388;
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
        ['rowid' => 21, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_forms', 'option_value' => 'forms-copy', 'updated_at' => 21, 'kind' => 'extension'],
        ['rowid' => 10, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_alpha', 'option_value' => 'alpha', 'updated_at' => 10, 'kind' => 'extension'],
        ['rowid' => 70, 'blog_id' => 1, 'autoload' => 'no', 'option_name' => 'plugin_forms', 'option_value' => 'lazy', 'updated_at' => 70, 'kind' => 'extension'],
        ['rowid' => 80, 'blog_id' => 2, 'autoload' => 'yes', 'option_name' => 'plugin_forms', 'option_value' => 'other-blog', 'updated_at' => 80, 'kind' => 'extension'],
    ];
    $source['indexes'][0]['stat4Samples'] = [
        ['neq' => '1 1 1 1', 'nlt' => '0 0 0 0', 'ndlt' => '0 0 0 0', 'sample' => ['plugin_alpha', 10, 'yes', 1]],
        ['neq' => '2 1 1 1', 'nlt' => '1 1 1 1', 'ndlt' => '1 1 1 1', 'sample' => ['plugin_forms', 20, 'yes', 1]],
        ['neq' => '1 1 1 1', 'nlt' => '3 2 2 2', 'ndlt' => '2 2 2 2', 'sample' => ['plugin_mail', 30, 'yes', 1]],
        ['neq' => '1 1 1 1', 'nlt' => '4 3 3 3', 'ndlt' => '3 3 3 3', 'sample' => ['plugin_seo', 40, 'yes', 1]],
    ];
    if ($variant === 'prepared-reused') {
        $source['indexes'][0]['stat4Samples'] = $prepared243()['indexes'][0]['stat4Samples'];
    }
    if ($variant === 'missing-sample-row') {
        $source['indexes'][0]['stat4Samples'][1]['sample'][1] = 999;
    }
    if ($variant === 'incomplete-tape') {
        array_splice($source['indexes'][0]['stat4Samples'], 2, 1);
    }
    if ($variant === 'malformed-sample') {
        $source['indexes'][0]['stat4Samples'][0]['sample'][1] = 'bad-rowid';
    }
    $source['indexes'][0]['stat4ExpressionPayloads'] = array_map($payload243, array_slice($source['rows'], 0, 5));

    return $source;
};

$terms243 = static fn (): array => [
    $between243('LOWER(option_name)', 'plugin_alpha', 'plugin_tango'),
    $eq243('autoload', 'yes'),
    $notNull243('option_name'),
    $eq243('blog_id', 1),
    $like243('option_name', 'plugin_%'),
];
$plan243 = static fn (string $variant = 'ready', int $limit = 5, int $offset = 0): array => SQLitePlannerStat4ExpressionPartialCurrentSourceNext243Plan::materialize(
    $prepared243(),
    $current243($variant),
    $terms243(),
    ['option_name', 'option_value', 'updated_at', 'blog_id'],
    ['autoload', 'blog_id'],
    $limit,
    $offset,
);

$tests = [
    'planner stat4 expression partial current source next243 status ready' => static fn (TestRunner $t) => $t->same('stat4-expression-partial-current-source-next243-ready', $plan243()['status']),
    'planner stat4 expression partial current source next243 inherits next240' => static fn (TestRunner $t) => $t->same(true, $plan243()['selectedPlan']['next240Ready']),
    'planner stat4 expression partial current source next243 ready flag' => static fn (TestRunner $t) => $t->same(true, $plan243()['selectedPlan']['next243Ready']),
    'planner stat4 expression partial current source next243 selected current' => static fn (TestRunner $t) => $t->same('current', $plan243()['selectedSource']),
    'planner stat4 expression partial current source next243 selected index' => static fn (TestRunner $t) => $t->same('idx_wp_options_stat4_sample_tape_next243', $plan243()['selectedPlan']['name']),
    'planner stat4 expression partial current source next243 root page' => static fn (TestRunner $t) => $t->same(24388, $plan243()['selectedPlan']['rootPage']),
    'planner stat4 expression partial current source next243 current sample rowids' => static fn (TestRunner $t) => $t->same([10, 20, 30, 40], $plan243()['stat4ExpressionSampleTapeFence']['currentSampleRowids']),
    'planner stat4 expression partial current source next243 prepared sample rowids' => static fn (TestRunner $t) => $t->same([10, 20, 30], $plan243()['stat4ExpressionSampleTapeFence']['preparedSampleRowids']),
    'planner stat4 expression partial current source next243 expanded rowids' => static fn (TestRunner $t) => $t->same([10, 20, 21, 30, 40], $plan243()['stat4ExpressionSampleTapeFence']['expandedCurrentRowids']),
    'planner stat4 expression partial current source next243 matched rowids' => static fn (TestRunner $t) => $t->same([10, 20, 21, 30, 40], $plan243()['matchedRowids']),
    'planner stat4 expression partial current source next243 missing empty' => static fn (TestRunner $t) => $t->same([], $plan243()['stat4ExpressionSampleTapeFence']['missingCurrentSampleRowids']),
    'planner stat4 expression partial current source next243 not prepared reused' => static fn (TestRunner $t) => $t->same(false, $plan243()['stat4ExpressionSampleTapeFence']['preparedTapeReused']),
    'planner stat4 expression partial current source next243 rejected reason null' => static fn (TestRunner $t) => $t->same(null, $plan243()['stat4ExpressionSampleTapeFence']['rejectedReason']),
    'planner stat4 expression partial current source next243 proof count' => static fn (TestRunner $t) => $t->same(4, count($plan243()['stat4ExpressionSampleTapeFence']['sampleProofs'])),
    'planner stat4 expression partial current source next243 duplicate expansion' => static fn (TestRunner $t) => $t->same([20, 21], $plan243()['stat4ExpressionSampleTapeFence']['sampleProofs'][1]['expandedRowids']),
    'planner stat4 expression partial current source next243 seo sample present' => static fn (TestRunner $t) => $t->same('plugin_seo', $plan243()['stat4ExpressionSampleTapeFence']['sampleProofs'][3]['expressionKey']),
    'planner stat4 expression partial current source next243 signature length' => static fn (TestRunner $t) => $t->same(64, strlen($plan243()['stat4ExpressionSampleTapeFence']['proofSignature'])),
    'planner stat4 expression partial current source next243 selected current rowids' => static fn (TestRunner $t) => $t->same($plan243()['stat4ExpressionSampleTapeFence']['currentSampleRowids'], $plan243()['selectedPlan']['next243CurrentSampleRowids']),
    'planner stat4 expression partial current source next243 selected prepared rowids' => static fn (TestRunner $t) => $t->same($plan243()['stat4ExpressionSampleTapeFence']['preparedSampleRowids'], $plan243()['selectedPlan']['next243PreparedSampleRowids']),
    'planner stat4 expression partial current source next243 selected expanded rowids' => static fn (TestRunner $t) => $t->same($plan243()['stat4ExpressionSampleTapeFence']['expandedCurrentRowids'], $plan243()['selectedPlan']['next243ExpandedCurrentRowids']),
    'planner stat4 expression partial current source next243 selected signature' => static fn (TestRunner $t) => $t->same($plan243()['stat4ExpressionSampleTapeFence']['proofSignature'], $plan243()['selectedPlan']['next243ProofSignature']),
    'planner stat4 expression partial current source next243 stat4 ready' => static fn (TestRunner $t) => $t->same(true, $plan243()['stat4Fence']['next243SampleTapeReady']),
    'planner stat4 expression partial current source next243 stat4 signature' => static fn (TestRunner $t) => $t->same($plan243()['stat4ExpressionSampleTapeFence']['proofSignature'], $plan243()['stat4Fence']['next243SampleTapeSignature']),
    'planner stat4 expression partial current source next243 cursor appended' => static fn (TestRunner $t) => $t->same('ValidateCurrentStat4ExpressionSampleTape', $plan243()['cursorProgram'][array_key_last($plan243()['cursorProgram'])]['opcode']),
    'planner stat4 expression partial current source next243 cursor mode' => static fn (TestRunner $t) => $t->same('next243-current-source-stat4-expression-partial-sample-tape', $plan243()['cursorProgram'][array_key_last($plan243()['cursorProgram'])]['mode']),
    'planner stat4 expression partial current source next243 cursor rowids' => static fn (TestRunner $t) => $t->same([10, 20, 30, 40], $plan243()['cursorProgram'][array_key_last($plan243()['cursorProgram'])]['currentSampleRowids']),
    'planner stat4 expression partial current source next243 cursor expanded' => static fn (TestRunner $t) => $t->same([10, 20, 21, 30, 40], $plan243()['cursorProgram'][array_key_last($plan243()['cursorProgram'])]['expandedCurrentRowids']),
    'planner stat4 expression partial current source next243 cursor signature' => static fn (TestRunner $t) => $t->same($plan243()['stat4ExpressionSampleTapeFence']['proofSignature'], $plan243()['cursorProgram'][array_key_last($plan243()['cursorProgram'])]['signature']),
    'planner stat4 expression partial current source next243 detail' => static fn (TestRunner $t) => $t->contains('NEXT243 SAMPLE TAPE FENCE', $plan243()['detail']),
    'planner stat4 expression partial current source next243 dependency marker' => static fn (TestRunner $t) => $t->true(in_array('sqlite-sqlplanner-stat4-expression-partial-current-source-next243', $plan243()['dependencies'], true)),
    'planner stat4 expression partial current source next243 dependency closure' => static fn (TestRunner $t) => $t->contains('no new support component needed', $plan243()['dependency_closure']),
    'planner stat4 expression partial current source next243 non overlap' => static fn (TestRunner $t) => $t->contains('sample-tape validation', $plan243()['non_overlap']),
    'planner stat4 expression partial current source next243 projected duplicate payload' => static fn (TestRunner $t) => $t->same('forms-copy', $plan243()['projectedRows'][2]['option_value']),
    'planner stat4 expression partial current source next243 prepared reused blocks' => static fn (TestRunner $t) => $t->same('requires-current-source-stat4-expression-sample-tape-reprepare', $plan243('prepared-reused')['status']),
    'planner stat4 expression partial current source next243 prepared reused reason' => static fn (TestRunner $t) => $t->same('prepared-stat4-sample-tape-reused', $plan243('prepared-reused')['stat4ExpressionSampleTapeFence']['rejectedReason']),
    'planner stat4 expression partial current source next243 prepared reused selected flag' => static fn (TestRunner $t) => $t->same(false, $plan243('prepared-reused')['selectedPlan']['next243Ready']),
    'planner stat4 expression partial current source next243 prepared reused no cursor' => static fn (TestRunner $t) => $t->same(false, in_array('ValidateCurrentStat4ExpressionSampleTape', array_column($plan243('prepared-reused')['cursorProgram'], 'opcode'), true)),
    'planner stat4 expression partial current source next243 missing sample blocks' => static fn (TestRunner $t) => $t->same('requires-current-source-stat4-expression-sample-tape-reprepare', $plan243('missing-sample-row')['status']),
    'planner stat4 expression partial current source next243 missing sample reason' => static fn (TestRunner $t) => $t->same('current-stat4-sample-row-missing', $plan243('missing-sample-row')['stat4ExpressionSampleTapeFence']['rejectedReason']),
    'planner stat4 expression partial current source next243 missing rowid listed' => static fn (TestRunner $t) => $t->same([999], $plan243('missing-sample-row')['stat4ExpressionSampleTapeFence']['missingCurrentSampleRowids']),
    'planner stat4 expression partial current source next243 incomplete tape blocks' => static fn (TestRunner $t) => $t->same('requires-current-source-stat4-expression-sample-tape-reprepare', $plan243('incomplete-tape')['status']),
    'planner stat4 expression partial current source next243 incomplete tape reason' => static fn (TestRunner $t) => $t->same('current-stat4-sample-tape-does-not-cover-matched-rowids', $plan243('incomplete-tape')['stat4ExpressionSampleTapeFence']['rejectedReason']),
    'planner stat4 expression partial current source next243 incomplete expanded rowids' => static fn (TestRunner $t) => $t->same([10, 20, 21, 40], $plan243('incomplete-tape')['stat4ExpressionSampleTapeFence']['expandedCurrentRowids']),
    'planner stat4 expression partial current source next243 malformed sample throws' => static function (TestRunner $t) use ($plan243): void {
        $t->throws(InvalidArgumentException::class, static fn () => $plan243('malformed-sample'));
    },
    'planner stat4 expression partial current source next243 invalid limit' => static function (TestRunner $t) use ($plan243): void {
        $t->throws(InvalidArgumentException::class, static fn () => $plan243('ready', -1));
    },
    'planner stat4 expression partial current source next243 invalid offset' => static function (TestRunner $t) use ($plan243): void {
        $t->throws(InvalidArgumentException::class, static fn () => $plan243('ready', 1, -1));
    },
];

foreach (range(1, 24) as $case) {
    $tests['planner stat4 expression partial current source next243 repeated sample tape proof ' . $case] = static function (TestRunner $t) use ($plan243, $case): void {
        $plan = $plan243('ready', 1 + ($case % 5), $case % 2);
        $t->same($plan['stat4ExpressionSampleTapeFence']['proofSignature'], $plan['selectedPlan']['next243ProofSignature']);
    };
}

return $tests;

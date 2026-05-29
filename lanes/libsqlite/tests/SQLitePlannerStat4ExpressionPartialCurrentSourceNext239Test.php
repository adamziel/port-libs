<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan;

$eq239 = static fn (string $column, mixed $right): array => ['left' => ['column' => $column], 'operator' => '=', 'right' => $right];
$like239 = static fn (string $column, string $right): array => ['left' => ['column' => $column], 'operator' => 'LIKE', 'right' => $right];
$notNull239 = static fn (string $column): array => ['left' => ['column' => $column], 'operator' => 'IS NOT NULL'];
$between239 = static fn (string $expression, mixed $lower, mixed $upper): array => ['left' => ['expression' => $expression], 'operator' => 'BETWEEN', 'lower' => $lower, 'upper' => $upper];

$prepared239 = static fn (): array => [
    'name' => 'prepared-wp-options-stat4-estimate-next239',
    'schemaCookie' => 2390,
    'stat4Generation' => 239,
    'rows' => [
        ['rowid' => 20, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_forms', 'option_value' => 'forms-old', 'updated_at' => 20],
        ['rowid' => 30, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_seo', 'option_value' => 'seo-old', 'updated_at' => 30],
        ['rowid' => 60, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_zulu', 'option_value' => 'zulu-old', 'updated_at' => 60],
    ],
    'indexes' => [[
        'name' => 'idx_wp_options_lower_estimate_next239',
        'rootPage' => 23901,
        'expression' => 'lower(option_name)',
        'expressionColumn' => '__expr_lower_option_name',
        'collation' => 'BINARY',
        'descending' => true,
        'estimatedPartialRows' => 3,
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
        'stat4Samples' => [
            ['neq' => '1 1', 'nlt' => '0 0', 'ndlt' => '0 0', 'sample' => ['plugin_forms', 20]],
            ['neq' => '1 1', 'nlt' => '1 1', 'ndlt' => '1 1', 'sample' => ['plugin_mail', 50]],
            ['neq' => '1 1', 'nlt' => '2 2', 'ndlt' => '2 2', 'sample' => ['plugin_seo', 30]],
            ['neq' => '1 1', 'nlt' => '3 3', 'ndlt' => '3 3', 'sample' => ['plugin_zulu', 60]],
        ],
    ]],
];

$current239 = static function (int|string|null $estimate = 6, ?array $rows = null) use ($prepared239): array {
    $source = $prepared239();
    $source['name'] = 'current-wp-options-stat4-estimate-next239';
    $source['schemaCookie'] = 2398;
    $source['stat4Generation'] = 398;
    $source['indexes'][0]['rootPage'] = 23988;
    unset($source['indexes'][0]['estimatedPartialRows']);
    if ($estimate !== null) {
        $source['indexes'][0]['stat1'] = ['rows' => $estimate];
    }
    $source['indexes'][0]['stat4Samples'] = [
        ['neq' => '3 1', 'nlt' => '0 0', 'ndlt' => '0 0', 'sample' => ['plugin_forms', 20]],
        ['neq' => '1 1', 'nlt' => '3 1', 'ndlt' => '1 1', 'sample' => ['plugin_mail', 50]],
        ['neq' => '1 1', 'nlt' => '4 2', 'ndlt' => '2 2', 'sample' => ['plugin_seo', 30]],
        ['neq' => '1 1', 'nlt' => '5 3', 'ndlt' => '3 3', 'sample' => ['plugin_zulu', 60]],
        ['neq' => '1 1', 'nlt' => '6 4', 'ndlt' => '4 4', 'sample' => ['theme_mods_current', 90]],
    ];
    $source['rows'] = $rows ?? [
        ['rowid' => 90, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'theme_mods_current', 'option_value' => 'theme', 'updated_at' => 90],
        ['rowid' => 60, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_zulu', 'option_value' => 'zulu', 'updated_at' => 60],
        ['rowid' => 30, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_seo', 'option_value' => 'seo', 'updated_at' => 30],
        ['rowid' => 50, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'Plugin_Mail', 'option_value' => 'mail', 'updated_at' => 50],
        ['rowid' => 20, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_forms', 'option_value' => 'forms-anchor', 'updated_at' => 20],
        ['rowid' => 21, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'Plugin_Forms', 'option_value' => 'forms-copy-a', 'updated_at' => 21],
        ['rowid' => 22, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'PLUGIN_FORMS', 'option_value' => 'forms-copy-b', 'updated_at' => 22],
        ['rowid' => 70, 'blog_id' => 1, 'autoload' => 'no', 'option_name' => 'plugin_forms', 'option_value' => 'lazy', 'updated_at' => 70],
        ['rowid' => 80, 'blog_id' => 2, 'autoload' => 'yes', 'option_name' => 'plugin_forms', 'option_value' => 'other-blog', 'updated_at' => 80],
    ];

    return $source;
};

$terms239 = static fn (): array => [
    $between239('LOWER(option_name)', 'plugin_forms', 'plugin_zulu'),
    $eq239('autoload', 'yes'),
    $notNull239('option_name'),
    $eq239('blog_id', 1),
    $like239('option_name', 'plugin_%'),
];

$plan239 = static fn (int|string|null $estimate = 6, ?array $rows = null, int $limit = 6, int $offset = 0): array => SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan::materializeNext239(
    $prepared239(),
    $current239($estimate, $rows),
    $terms239(),
    ['option_name', 'option_value', 'updated_at', 'blog_id'],
    $limit,
    $offset,
);

$under239 = static fn (): array => $plan239(5);
$over239 = static fn (): array => $plan239(7);
$stringEstimate239 = static fn (): array => $plan239('6 2 1');

$tests = [
    'planner stat4 expression partial current source next239 status ready' => static fn (TestRunner $t) => $t->same('stat4-expression-partial-current-source-next239-ready', $plan239()['status']),
    'planner stat4 expression partial current source next239 inherits next236' => static fn (TestRunner $t) => $t->same(true, $plan239()['selectedPlan']['next236Ready']),
    'planner stat4 expression partial current source next239 ready flag' => static fn (TestRunner $t) => $t->same(true, $plan239()['selectedPlan']['next239Ready']),
    'planner stat4 expression partial current source next239 selected current' => static fn (TestRunner $t) => $t->same('current', $plan239()['selectedSource']),
    'planner stat4 expression partial current source next239 selected index' => static fn (TestRunner $t) => $t->same('idx_wp_options_lower_estimate_next239', $plan239()['selectedPlan']['name']),
    'planner stat4 expression partial current source next239 root page' => static fn (TestRunner $t) => $t->same(23988, $plan239()['selectedPlan']['rootPage']),
    'planner stat4 expression partial current source next239 matched rowids' => static fn (TestRunner $t) => $t->same([60, 30, 50, 20, 21, 22], $plan239()['matchedRowids']),
    'planner stat4 expression partial current source next239 projected duplicate payload' => static fn (TestRunner $t) => $t->same('forms-copy-b', $plan239()['projectedRows'][5]['option_value']),
    'planner stat4 expression partial current source next239 estimated rows' => static fn (TestRunner $t) => $t->same(6, $plan239()['stat4PartialEstimateFence']['estimatedPartialRows']),
    'planner stat4 expression partial current source next239 actual rows' => static fn (TestRunner $t) => $t->same(6, $plan239()['stat4PartialEstimateFence']['actualPartialRows']),
    'planner stat4 expression partial current source next239 estimate delta' => static fn (TestRunner $t) => $t->same(0, $plan239()['stat4PartialEstimateFence']['estimateDelta']),
    'planner stat4 expression partial current source next239 rejected reason null' => static fn (TestRunner $t) => $t->same(null, $plan239()['stat4PartialEstimateFence']['rejectedReason']),
    'planner stat4 expression partial current source next239 partial rowids' => static fn (TestRunner $t) => $t->same([20, 21, 22, 50, 30, 60], $plan239()['stat4PartialEstimateFence']['partialRowids']),
    'planner stat4 expression partial current source next239 expression keys' => static fn (TestRunner $t) => $t->same(['plugin_forms', 'plugin_mail', 'plugin_seo', 'plugin_zulu'], $plan239()['stat4PartialEstimateFence']['partialExpressionKeys']),
    'planner stat4 expression partial current source next239 signature length' => static fn (TestRunner $t) => $t->same(64, strlen($plan239()['stat4PartialEstimateFence']['proofSignature'])),
    'planner stat4 expression partial current source next239 selected estimated' => static fn (TestRunner $t) => $t->same(6, $plan239()['selectedPlan']['next239EstimatedRows']),
    'planner stat4 expression partial current source next239 selected actual' => static fn (TestRunner $t) => $t->same(6, $plan239()['selectedPlan']['next239ActualRows']),
    'planner stat4 expression partial current source next239 selected delta' => static fn (TestRunner $t) => $t->same(0, $plan239()['selectedPlan']['next239EstimateDelta']),
    'planner stat4 expression partial current source next239 selected rejected reason' => static fn (TestRunner $t) => $t->same(null, $plan239()['selectedPlan']['next239RejectedReason']),
    'planner stat4 expression partial current source next239 selected proof signature' => static fn (TestRunner $t) => $t->same($plan239()['stat4PartialEstimateFence']['proofSignature'], $plan239()['selectedPlan']['next239ProofSignature']),
    'planner stat4 expression partial current source next239 stat4 ready' => static fn (TestRunner $t) => $t->same(true, $plan239()['stat4Fence']['next239PartialEstimateReady']),
    'planner stat4 expression partial current source next239 stat4 delta' => static fn (TestRunner $t) => $t->same(0, $plan239()['stat4Fence']['next239PartialEstimateDelta']),
    'planner stat4 expression partial current source next239 stat4 signature' => static fn (TestRunner $t) => $t->same($plan239()['stat4PartialEstimateFence']['proofSignature'], $plan239()['stat4Fence']['next239PartialEstimateSignature']),
    'planner stat4 expression partial current source next239 cursor appended' => static fn (TestRunner $t) => $t->same('ValidateCurrentSourcePartialIndexEstimate', $plan239()['cursorProgram'][array_key_last($plan239()['cursorProgram'])]['opcode']),
    'planner stat4 expression partial current source next239 cursor mode' => static fn (TestRunner $t) => $t->same('next239-current-source-stat4-expression-partial-estimate', $plan239()['cursorProgram'][array_key_last($plan239()['cursorProgram'])]['mode']),
    'planner stat4 expression partial current source next239 cursor estimate' => static fn (TestRunner $t) => $t->same(6, $plan239()['cursorProgram'][array_key_last($plan239()['cursorProgram'])]['estimatedPartialRows']),
    'planner stat4 expression partial current source next239 cursor actual' => static fn (TestRunner $t) => $t->same(6, $plan239()['cursorProgram'][array_key_last($plan239()['cursorProgram'])]['actualPartialRows']),
    'planner stat4 expression partial current source next239 cursor rowids' => static fn (TestRunner $t) => $t->same([20, 21, 22, 50, 30, 60], $plan239()['cursorProgram'][array_key_last($plan239()['cursorProgram'])]['partialRowids']),
    'planner stat4 expression partial current source next239 cursor signature' => static fn (TestRunner $t) => $t->same($plan239()['stat4PartialEstimateFence']['proofSignature'], $plan239()['cursorProgram'][array_key_last($plan239()['cursorProgram'])]['signature']),
    'planner stat4 expression partial current source next239 detail' => static fn (TestRunner $t) => $t->contains('NEXT239 PARTIAL ESTIMATE FENCE', $plan239()['detail']),
    'planner stat4 expression partial current source next239 dependency marker' => static fn (TestRunner $t) => $t->true(in_array('sqlite-sqlplanner-stat4-expression-partial-current-source-next239', $plan239()['dependencies'], true)),
    'planner stat4 expression partial current source next239 dependency closure' => static fn (TestRunner $t) => $t->contains('no new support component needed', $plan239()['dependency_closure']),
    'planner stat4 expression partial current source next239 non overlap' => static fn (TestRunner $t) => $t->contains('stale partial-index cardinality', $plan239()['non_overlap']),
    'planner stat4 expression partial current source next239 string estimate ready' => static fn (TestRunner $t) => $t->same('stat4-expression-partial-current-source-next239-ready', $stringEstimate239()['status']),
    'planner stat4 expression partial current source next239 string estimate parsed' => static fn (TestRunner $t) => $t->same(6, $stringEstimate239()['stat4PartialEstimateFence']['estimatedPartialRows']),
    'planner stat4 expression partial current source next239 undercount blocks' => static fn (TestRunner $t) => $t->same('requires-current-source-partial-estimate-reprepare', $under239()['status']),
    'planner stat4 expression partial current source next239 undercount reason' => static fn (TestRunner $t) => $t->same('partial-estimate-under-count', $under239()['stat4PartialEstimateFence']['rejectedReason']),
    'planner stat4 expression partial current source next239 undercount delta' => static fn (TestRunner $t) => $t->same(1, $under239()['stat4PartialEstimateFence']['estimateDelta']),
    'planner stat4 expression partial current source next239 undercount selected not ready' => static fn (TestRunner $t) => $t->same(false, $under239()['selectedPlan']['next239Ready']),
    'planner stat4 expression partial current source next239 undercount no cursor append' => static fn (TestRunner $t) => $t->same(false, in_array('ValidateCurrentSourcePartialIndexEstimate', array_column($under239()['cursorProgram'], 'opcode'), true)),
    'planner stat4 expression partial current source next239 overcount blocks' => static fn (TestRunner $t) => $t->same('requires-current-source-partial-estimate-reprepare', $over239()['status']),
    'planner stat4 expression partial current source next239 overcount reason' => static fn (TestRunner $t) => $t->same('partial-estimate-over-count', $over239()['stat4PartialEstimateFence']['rejectedReason']),
    'planner stat4 expression partial current source next239 overcount delta' => static fn (TestRunner $t) => $t->same(-1, $over239()['stat4PartialEstimateFence']['estimateDelta']),
    'planner stat4 expression partial current source next239 overcount stat4 not ready' => static fn (TestRunner $t) => $t->same(false, $over239()['stat4Fence']['next239PartialEstimateReady']),
    'planner stat4 expression partial current source next239 missing estimate' => static function (TestRunner $t) use ($plan239): void {
        $t->throws(InvalidArgumentException::class, static fn () => $plan239(null));
    },
    'planner stat4 expression partial current source next239 malformed estimate' => static function (TestRunner $t) use ($plan239): void {
        $t->throws(InvalidArgumentException::class, static fn () => $plan239('bad'));
    },
    'planner stat4 expression partial current source next239 invalid limit' => static function (TestRunner $t) use ($plan239): void {
        $t->throws(InvalidArgumentException::class, static fn () => $plan239(6, null, -1));
    },
    'planner stat4 expression partial current source next239 invalid offset' => static function (TestRunner $t) use ($plan239): void {
        $t->throws(InvalidArgumentException::class, static fn () => $plan239(6, null, 1, -1));
    },
];

foreach (range(1, 18) as $case) {
    $tests['planner stat4 expression partial current source next239 repeated estimate proof ' . $case] = static function (TestRunner $t) use ($plan239, $case): void {
        $plan = $plan239(6, null, 5 + ($case % 2));
        $t->same($plan['stat4PartialEstimateFence']['proofSignature'], $plan['selectedPlan']['next239ProofSignature']);
    };
}

return $tests;

<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan;

$eq245 = static fn (string $column, mixed $right): array => ['left' => ['column' => $column], 'operator' => '=', 'right' => $right];
$like245 = static fn (string $column, string $right): array => ['left' => ['column' => $column], 'operator' => 'LIKE', 'right' => $right];
$notNull245 = static fn (string $column): array => ['left' => ['column' => $column], 'operator' => 'IS NOT NULL'];
$between245 = static fn (string $expression, mixed $lower, mixed $upper): array => ['left' => ['expression' => $expression], 'operator' => 'BETWEEN', 'lower' => $lower, 'upper' => $upper];

$rows245 = static fn (): array => [
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

$samples245 = static fn (?array $override = null): array => $override ?? [
    ['neq' => '3 3', 'nlt' => '0 0', 'ndlt' => '0 0', 'sample' => ['plugin_forms', 20, 1]],
    ['neq' => '1 1', 'nlt' => '3 3', 'ndlt' => '1 1', 'sample' => ['plugin_mail', 50, 1]],
    ['neq' => '1 1', 'nlt' => '4 4', 'ndlt' => '2 2', 'sample' => ['plugin_seo', 30, 1]],
    ['neq' => '1 1', 'nlt' => '5 5', 'ndlt' => '3 3', 'sample' => ['plugin_zulu', 60, 1]],
];

$prepared245 = static fn (): array => [
    'name' => 'prepared-wp-options-stat4-anchor-stat4SampleAnchor',
    'schemaCookie' => 2450,
    'stat4Generation' => 245,
    'rows' => [
        ['rowid' => 20, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_forms', 'option_value' => 'forms-old', 'updated_at' => 20],
        ['rowid' => 30, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_seo', 'option_value' => 'seo-old', 'updated_at' => 30],
        ['rowid' => 60, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_zulu', 'option_value' => 'zulu-old', 'updated_at' => 60],
    ],
    'indexes' => [[
        'name' => 'idx_wp_options_lower_anchor_stat4SampleAnchor',
        'rootPage' => 24501,
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
            ['neq' => '1 1', 'nlt' => '0 0', 'ndlt' => '0 0', 'sample' => ['plugin_forms', 20, 1]],
            ['neq' => '1 1', 'nlt' => '1 1', 'ndlt' => '1 1', 'sample' => ['plugin_seo', 30, 1]],
            ['neq' => '1 1', 'nlt' => '2 2', 'ndlt' => '2 2', 'sample' => ['plugin_zulu', 60, 1]],
        ],
    ]],
];

$current245 = static function (?array $samples = null, ?array $rows = null) use ($prepared245, $rows245, $samples245): array {
    $source = $prepared245();
    $source['name'] = 'current-wp-options-stat4-anchor-stat4SampleAnchor';
    $source['schemaCookie'] = 2458;
    $source['stat4Generation'] = 548;
    $source['rows'] = $rows ?? $rows245();
    $source['indexes'][0]['rootPage'] = 24588;
    unset($source['indexes'][0]['estimatedPartialRows']);
    $source['indexes'][0]['stat1'] = ['rows' => '6 2 1'];
    $source['indexes'][0]['stat4Samples'] = $samples245($samples);

    return $source;
};

$terms245 = static fn (): array => [
    $between245('LOWER(option_name)', 'plugin_forms', 'plugin_zulu'),
    $eq245('autoload', 'yes'),
    $notNull245('option_name'),
    $eq245('blog_id', 1),
    $like245('option_name', 'plugin_%'),
];

$plan245 = static fn (?array $samples = null, ?array $rows = null, int $limit = 6, int $offset = 0): array => SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan::materializeStat4SampleAnchorFence(
    $prepared245(),
    $current245($samples, $rows),
    $terms245(),
    ['option_name', 'option_value', 'updated_at', 'blog_id'],
    $limit,
    $offset,
);

$staleRowid245 = static fn (): array => $plan245([
    ['neq' => '3 3', 'nlt' => '0 0', 'ndlt' => '0 0', 'sample' => ['plugin_forms', 50, 1]],
    ['neq' => '1 1', 'nlt' => '3 3', 'ndlt' => '1 1', 'sample' => ['plugin_mail', 50, 1]],
    ['neq' => '1 1', 'nlt' => '4 4', 'ndlt' => '2 2', 'sample' => ['plugin_seo', 30, 1]],
    ['neq' => '1 1', 'nlt' => '5 5', 'ndlt' => '3 3', 'sample' => ['plugin_zulu', 60, 1]],
]);
$staleKey245 = static fn (): array => $plan245([
    ['neq' => '3 3', 'nlt' => '0 0', 'ndlt' => '0 0', 'sample' => ['plugin_mail', 20, 1]],
    ['neq' => '1 1', 'nlt' => '3 3', 'ndlt' => '1 1', 'sample' => ['plugin_mail', 50, 1]],
    ['neq' => '1 1', 'nlt' => '4 4', 'ndlt' => '2 2', 'sample' => ['plugin_seo', 30, 1]],
    ['neq' => '1 1', 'nlt' => '5 5', 'ndlt' => '3 3', 'sample' => ['plugin_zulu', 60, 1]],
]);
$staleBlog245 = static fn (): array => $plan245([
    ['neq' => '3 3', 'nlt' => '0 0', 'ndlt' => '0 0', 'sample' => ['plugin_forms', 20, 2]],
    ['neq' => '1 1', 'nlt' => '3 3', 'ndlt' => '1 1', 'sample' => ['plugin_mail', 50, 1]],
    ['neq' => '1 1', 'nlt' => '4 4', 'ndlt' => '2 2', 'sample' => ['plugin_seo', 30, 1]],
    ['neq' => '1 1', 'nlt' => '5 5', 'ndlt' => '3 3', 'sample' => ['plugin_zulu', 60, 1]],
]);
$deletedAnchor245 = static function () use ($plan245, $rows245): array {
    $rows = array_values(array_filter($rows245(), static fn (array $row): bool => $row['rowid'] !== 50));

    return $plan245(null, $rows);
};

$tests = [
    'planner stat4 expression partial current source stat4SampleAnchor status ready' => static fn (TestRunner $t) => $t->same('stat4-expression-partial-current-source-stat4SampleAnchor-ready', $plan245()['status']),
    'planner stat4 expression partial current source stat4SampleAnchor inherits next242' => static fn (TestRunner $t) => $t->same(true, $plan245()['selectedPlan']['next242Ready']),
    'planner stat4 expression partial current source stat4SampleAnchor ready flag' => static fn (TestRunner $t) => $t->same(true, $plan245()['selectedPlan']['stat4SampleAnchorReady']),
    'planner stat4 expression partial current source stat4SampleAnchor selected current' => static fn (TestRunner $t) => $t->same('current', $plan245()['selectedSource']),
    'planner stat4 expression partial current source stat4SampleAnchor selected index' => static fn (TestRunner $t) => $t->same('idx_wp_options_lower_anchor_stat4SampleAnchor', $plan245()['selectedPlan']['name']),
    'planner stat4 expression partial current source stat4SampleAnchor root page' => static fn (TestRunner $t) => $t->same(24588, $plan245()['selectedPlan']['rootPage']),
    'planner stat4 expression partial current source stat4SampleAnchor matched rowids' => static fn (TestRunner $t) => $t->same([60, 30, 50, 20, 21, 22], $plan245()['matchedRowids']),
    'planner stat4 expression partial current source stat4SampleAnchor projected payload' => static fn (TestRunner $t) => $t->same('forms-copy-b', $plan245()['projectedRows'][5]['option_value']),
    'planner stat4 expression partial current source stat4SampleAnchor partial count' => static fn (TestRunner $t) => $t->same(6, $plan245()['stat4SampleAnchorFence']['partialRowCount']),
    'planner stat4 expression partial current source stat4SampleAnchor partial rowids' => static fn (TestRunner $t) => $t->same([60, 30, 50, 20, 21, 22], $plan245()['stat4SampleAnchorFence']['partialRowids']),
    'planner stat4 expression partial current source stat4SampleAnchor sample count' => static fn (TestRunner $t) => $t->same(4, $plan245()['stat4SampleAnchorFence']['sampleCount']),
    'planner stat4 expression partial current source stat4SampleAnchor anchored sample count' => static fn (TestRunner $t) => $t->same(4, $plan245()['stat4SampleAnchorFence']['anchoredSampleCount']),
    'planner stat4 expression partial current source stat4SampleAnchor anchored rowids' => static fn (TestRunner $t) => $t->same([20, 50, 30, 60], $plan245()['stat4SampleAnchorFence']['anchoredSampleRowids']),
    'planner stat4 expression partial current source stat4SampleAnchor no rejected rowids' => static fn (TestRunner $t) => $t->same([], $plan245()['stat4SampleAnchorFence']['rejectedSampleRowids']),
    'planner stat4 expression partial current source stat4SampleAnchor rejected reason null' => static fn (TestRunner $t) => $t->same(null, $plan245()['stat4SampleAnchorFence']['rejectedReason']),
    'planner stat4 expression partial current source stat4SampleAnchor first sample rowid' => static fn (TestRunner $t) => $t->same(20, $plan245()['stat4SampleAnchorFence']['sampleAnchorProofs'][0]['sampleRowid']),
    'planner stat4 expression partial current source stat4SampleAnchor first sample current key' => static fn (TestRunner $t) => $t->same('plugin_forms', $plan245()['stat4SampleAnchorFence']['sampleAnchorProofs'][0]['currentExpressionKey']),
    'planner stat4 expression partial current source stat4SampleAnchor first sample tenant' => static fn (TestRunner $t) => $t->same(1, $plan245()['stat4SampleAnchorFence']['sampleAnchorProofs'][0]['currentTenantId']),
    'planner stat4 expression partial current source stat4SampleAnchor first sample matches' => static fn (TestRunner $t) => $t->same(true, $plan245()['stat4SampleAnchorFence']['sampleAnchorProofs'][0]['matchesCurrentAnchor']),
    'planner stat4 expression partial current source stat4SampleAnchor mail sample key normalized' => static fn (TestRunner $t) => $t->same('plugin_mail', $plan245()['stat4SampleAnchorFence']['sampleAnchorProofs'][1]['currentExpressionKey']),
    'planner stat4 expression partial current source stat4SampleAnchor payload signature length' => static fn (TestRunner $t) => $t->same(64, strlen((string) $plan245()['stat4SampleAnchorFence']['sampleAnchorProofs'][1]['currentPayloadSignature'])),
    'planner stat4 expression partial current source stat4SampleAnchor anchor signature length' => static fn (TestRunner $t) => $t->same(64, strlen($plan245()['stat4SampleAnchorFence']['anchorSignature'])),
    'planner stat4 expression partial current source stat4SampleAnchor proof signature length' => static fn (TestRunner $t) => $t->same(64, strlen($plan245()['stat4SampleAnchorFence']['proofSignature'])),
    'planner stat4 expression partial current source stat4SampleAnchor selected anchors' => static fn (TestRunner $t) => $t->same([20, 50, 30, 60], $plan245()['selectedPlan']['stat4SampleAnchorAnchoredSampleRowids']),
    'planner stat4 expression partial current source stat4SampleAnchor selected rejected' => static fn (TestRunner $t) => $t->same([], $plan245()['selectedPlan']['stat4SampleAnchorRejectedSampleRowids']),
    'planner stat4 expression partial current source stat4SampleAnchor selected signature' => static fn (TestRunner $t) => $t->same($plan245()['stat4SampleAnchorFence']['proofSignature'], $plan245()['selectedPlan']['stat4SampleAnchorProofSignature']),
    'planner stat4 expression partial current source stat4SampleAnchor stat4 ready' => static fn (TestRunner $t) => $t->same(true, $plan245()['stat4Fence']['stat4SampleAnchorSampleAnchorReady']),
    'planner stat4 expression partial current source stat4SampleAnchor stat4 rejected' => static fn (TestRunner $t) => $t->same([], $plan245()['stat4Fence']['stat4SampleAnchorRejectedSampleRowids']),
    'planner stat4 expression partial current source stat4SampleAnchor stat4 signature' => static fn (TestRunner $t) => $t->same($plan245()['stat4SampleAnchorFence']['proofSignature'], $plan245()['stat4Fence']['stat4SampleAnchorSampleAnchorSignature']),
    'planner stat4 expression partial current source stat4SampleAnchor cursor appended' => static fn (TestRunner $t) => $t->same('ValidateCurrentSourceStat4SampleAnchors', $plan245()['cursorProgram'][array_key_last($plan245()['cursorProgram'])]['opcode']),
    'planner stat4 expression partial current source stat4SampleAnchor cursor mode' => static fn (TestRunner $t) => $t->same('stat4SampleAnchor-current-source-stat4-expression-partial-sample-anchors', $plan245()['cursorProgram'][array_key_last($plan245()['cursorProgram'])]['mode']),
    'planner stat4 expression partial current source stat4SampleAnchor cursor sample count' => static fn (TestRunner $t) => $t->same(4, $plan245()['cursorProgram'][array_key_last($plan245()['cursorProgram'])]['sampleCount']),
    'planner stat4 expression partial current source stat4SampleAnchor cursor anchored count' => static fn (TestRunner $t) => $t->same(4, $plan245()['cursorProgram'][array_key_last($plan245()['cursorProgram'])]['anchoredSampleCount']),
    'planner stat4 expression partial current source stat4SampleAnchor cursor rowids' => static fn (TestRunner $t) => $t->same([20, 50, 30, 60], $plan245()['cursorProgram'][array_key_last($plan245()['cursorProgram'])]['anchoredSampleRowids']),
    'planner stat4 expression partial current source stat4SampleAnchor cursor signature' => static fn (TestRunner $t) => $t->same($plan245()['stat4SampleAnchorFence']['proofSignature'], $plan245()['cursorProgram'][array_key_last($plan245()['cursorProgram'])]['signature']),
    'planner stat4 expression partial current source stat4SampleAnchor detail' => static fn (TestRunner $t) => $t->contains('STAT4 SAMPLE ANCHOR SAMPLE ANCHOR FENCE', $plan245()['detail']),
    'planner stat4 expression partial current source stat4SampleAnchor dependency marker' => static fn (TestRunner $t) => $t->true(in_array('sqlite-sqlplanner-stat4-expression-partial-current-source-stat4SampleAnchor', $plan245()['dependencies'], true)),
    'planner stat4 expression partial current source stat4SampleAnchor dependency closure' => static fn (TestRunner $t) => $t->contains('no new support component needed', $plan245()['dependency_closure']),
    'planner stat4 expression partial current source stat4SampleAnchor non overlap' => static fn (TestRunner $t) => $t->contains('sample-rowid anchor validation', $plan245()['non_overlap']),
    'planner stat4 expression partial current source stat4SampleAnchor stale rowid blocks' => static fn (TestRunner $t) => $t->same('requires-current-source-stat4-sample-anchor-reprepare', $staleRowid245()['status']),
    'planner stat4 expression partial current source stat4SampleAnchor stale rowid rejected' => static fn (TestRunner $t) => $t->same([50], $staleRowid245()['stat4SampleAnchorFence']['rejectedSampleRowids']),
    'planner stat4 expression partial current source stat4SampleAnchor stale rowid current key' => static fn (TestRunner $t) => $t->same('plugin_mail', $staleRowid245()['stat4SampleAnchorFence']['sampleAnchorProofs'][0]['currentExpressionKey']),
    'planner stat4 expression partial current source stat4SampleAnchor stale key blocks' => static fn (TestRunner $t) => $t->same('requires-current-source-stat4-sample-anchor-reprepare', $staleKey245()['status']),
    'planner stat4 expression partial current source stat4SampleAnchor stale key rejected' => static fn (TestRunner $t) => $t->same([20], $staleKey245()['stat4SampleAnchorFence']['rejectedSampleRowids']),
    'planner stat4 expression partial current source stat4SampleAnchor stale key proof mismatch' => static fn (TestRunner $t) => $t->same(false, $staleKey245()['stat4SampleAnchorFence']['sampleAnchorProofs'][0]['matchesCurrentAnchor']),
    'planner stat4 expression partial current source stat4SampleAnchor stale blog blocks' => static fn (TestRunner $t) => $t->same('requires-current-source-stat4-sample-anchor-reprepare', $staleBlog245()['status']),
    'planner stat4 expression partial current source stat4SampleAnchor stale blog rejected' => static fn (TestRunner $t) => $t->same([20], $staleBlog245()['stat4SampleAnchorFence']['rejectedSampleRowids']),
    'planner stat4 expression partial current source stat4SampleAnchor stale tenant proof current' => static fn (TestRunner $t) => $t->same(1, $staleBlog245()['stat4SampleAnchorFence']['sampleAnchorProofs'][0]['currentTenantId']),
    'planner stat4 expression partial current source stat4SampleAnchor deleted anchor blocks' => static fn (TestRunner $t) => $t->same('requires-current-source-stat4-sample-anchor-reprepare', $deletedAnchor245()['status']),
    'planner stat4 expression partial current source stat4SampleAnchor deleted anchor missing proof' => static fn (TestRunner $t) => $t->same(false, $deletedAnchor245()['stat4SampleAnchorFence']['sampleAnchorProofs'][1]['currentRowPresent']),
    'planner stat4 expression partial current source stat4SampleAnchor malformed missing blog' => static function (TestRunner $t) use ($plan245): void {
        $t->throws(InvalidArgumentException::class, static fn () => $plan245([
            ['neq' => '3 3', 'nlt' => '0 0', 'ndlt' => '0 0', 'sample' => ['plugin_forms', 20]],
        ]));
    },
    'planner stat4 expression partial current source stat4SampleAnchor malformed rowid' => static function (TestRunner $t) use ($plan245): void {
        $t->throws(InvalidArgumentException::class, static fn () => $plan245([
            ['neq' => '3 3', 'nlt' => '0 0', 'ndlt' => '0 0', 'sample' => ['plugin_forms', 'bad', 1]],
        ]));
    },
];

foreach (range(1, 28) as $case) {
    $tests['planner stat4 expression partial current source stat4SampleAnchor repeated sample anchor proof ' . $case] = static function (TestRunner $t) use ($plan245, $case): void {
        $plan = $plan245(null, null, 4 + ($case % 3), $case % 2);
        $t->same($plan['stat4SampleAnchorFence']['proofSignature'], $plan['selectedPlan']['stat4SampleAnchorProofSignature']);
    };
}

return $tests;

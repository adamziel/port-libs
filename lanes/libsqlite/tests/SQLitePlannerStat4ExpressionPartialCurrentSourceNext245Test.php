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
    'name' => 'prepared-wp-options-stat4-anchor-next245',
    'schemaCookie' => 2450,
    'stat4Generation' => 245,
    'rows' => [
        ['rowid' => 20, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_forms', 'option_value' => 'forms-old', 'updated_at' => 20],
        ['rowid' => 30, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_seo', 'option_value' => 'seo-old', 'updated_at' => 30],
        ['rowid' => 60, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_zulu', 'option_value' => 'zulu-old', 'updated_at' => 60],
    ],
    'indexes' => [[
        'name' => 'idx_wp_options_lower_anchor_next245',
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
    $source['name'] = 'current-wp-options-stat4-anchor-next245';
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

$plan245 = static fn (?array $samples = null, ?array $rows = null, int $limit = 6, int $offset = 0): array => SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan::materializeNext245(
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
    'planner stat4 expression partial current source next245 status ready' => static fn (TestRunner $t) => $t->same('stat4-expression-partial-current-source-next245-ready', $plan245()['status']),
    'planner stat4 expression partial current source next245 inherits next242' => static fn (TestRunner $t) => $t->same(true, $plan245()['selectedPlan']['next242Ready']),
    'planner stat4 expression partial current source next245 ready flag' => static fn (TestRunner $t) => $t->same(true, $plan245()['selectedPlan']['next245Ready']),
    'planner stat4 expression partial current source next245 selected current' => static fn (TestRunner $t) => $t->same('current', $plan245()['selectedSource']),
    'planner stat4 expression partial current source next245 selected index' => static fn (TestRunner $t) => $t->same('idx_wp_options_lower_anchor_next245', $plan245()['selectedPlan']['name']),
    'planner stat4 expression partial current source next245 root page' => static fn (TestRunner $t) => $t->same(24588, $plan245()['selectedPlan']['rootPage']),
    'planner stat4 expression partial current source next245 matched rowids' => static fn (TestRunner $t) => $t->same([60, 30, 50, 20, 21, 22], $plan245()['matchedRowids']),
    'planner stat4 expression partial current source next245 projected payload' => static fn (TestRunner $t) => $t->same('forms-copy-b', $plan245()['projectedRows'][5]['option_value']),
    'planner stat4 expression partial current source next245 partial count' => static fn (TestRunner $t) => $t->same(6, $plan245()['stat4SampleAnchorFence']['partialRowCount']),
    'planner stat4 expression partial current source next245 partial rowids' => static fn (TestRunner $t) => $t->same([60, 30, 50, 20, 21, 22], $plan245()['stat4SampleAnchorFence']['partialRowids']),
    'planner stat4 expression partial current source next245 sample count' => static fn (TestRunner $t) => $t->same(4, $plan245()['stat4SampleAnchorFence']['sampleCount']),
    'planner stat4 expression partial current source next245 anchored sample count' => static fn (TestRunner $t) => $t->same(4, $plan245()['stat4SampleAnchorFence']['anchoredSampleCount']),
    'planner stat4 expression partial current source next245 anchored rowids' => static fn (TestRunner $t) => $t->same([20, 50, 30, 60], $plan245()['stat4SampleAnchorFence']['anchoredSampleRowids']),
    'planner stat4 expression partial current source next245 no rejected rowids' => static fn (TestRunner $t) => $t->same([], $plan245()['stat4SampleAnchorFence']['rejectedSampleRowids']),
    'planner stat4 expression partial current source next245 rejected reason null' => static fn (TestRunner $t) => $t->same(null, $plan245()['stat4SampleAnchorFence']['rejectedReason']),
    'planner stat4 expression partial current source next245 first sample rowid' => static fn (TestRunner $t) => $t->same(20, $plan245()['stat4SampleAnchorFence']['sampleAnchorProofs'][0]['sampleRowid']),
    'planner stat4 expression partial current source next245 first sample current key' => static fn (TestRunner $t) => $t->same('plugin_forms', $plan245()['stat4SampleAnchorFence']['sampleAnchorProofs'][0]['currentExpressionKey']),
    'planner stat4 expression partial current source next245 first sample blog' => static fn (TestRunner $t) => $t->same(1, $plan245()['stat4SampleAnchorFence']['sampleAnchorProofs'][0]['currentBlogId']),
    'planner stat4 expression partial current source next245 first sample matches' => static fn (TestRunner $t) => $t->same(true, $plan245()['stat4SampleAnchorFence']['sampleAnchorProofs'][0]['matchesCurrentAnchor']),
    'planner stat4 expression partial current source next245 mail sample key normalized' => static fn (TestRunner $t) => $t->same('plugin_mail', $plan245()['stat4SampleAnchorFence']['sampleAnchorProofs'][1]['currentExpressionKey']),
    'planner stat4 expression partial current source next245 payload signature length' => static fn (TestRunner $t) => $t->same(64, strlen((string) $plan245()['stat4SampleAnchorFence']['sampleAnchorProofs'][1]['currentPayloadSignature'])),
    'planner stat4 expression partial current source next245 anchor signature length' => static fn (TestRunner $t) => $t->same(64, strlen($plan245()['stat4SampleAnchorFence']['anchorSignature'])),
    'planner stat4 expression partial current source next245 proof signature length' => static fn (TestRunner $t) => $t->same(64, strlen($plan245()['stat4SampleAnchorFence']['proofSignature'])),
    'planner stat4 expression partial current source next245 selected anchors' => static fn (TestRunner $t) => $t->same([20, 50, 30, 60], $plan245()['selectedPlan']['next245AnchoredSampleRowids']),
    'planner stat4 expression partial current source next245 selected rejected' => static fn (TestRunner $t) => $t->same([], $plan245()['selectedPlan']['next245RejectedSampleRowids']),
    'planner stat4 expression partial current source next245 selected signature' => static fn (TestRunner $t) => $t->same($plan245()['stat4SampleAnchorFence']['proofSignature'], $plan245()['selectedPlan']['next245ProofSignature']),
    'planner stat4 expression partial current source next245 stat4 ready' => static fn (TestRunner $t) => $t->same(true, $plan245()['stat4Fence']['next245SampleAnchorReady']),
    'planner stat4 expression partial current source next245 stat4 rejected' => static fn (TestRunner $t) => $t->same([], $plan245()['stat4Fence']['next245RejectedSampleRowids']),
    'planner stat4 expression partial current source next245 stat4 signature' => static fn (TestRunner $t) => $t->same($plan245()['stat4SampleAnchorFence']['proofSignature'], $plan245()['stat4Fence']['next245SampleAnchorSignature']),
    'planner stat4 expression partial current source next245 cursor appended' => static fn (TestRunner $t) => $t->same('ValidateCurrentSourceStat4SampleAnchors', $plan245()['cursorProgram'][array_key_last($plan245()['cursorProgram'])]['opcode']),
    'planner stat4 expression partial current source next245 cursor mode' => static fn (TestRunner $t) => $t->same('next245-current-source-stat4-expression-partial-sample-anchors', $plan245()['cursorProgram'][array_key_last($plan245()['cursorProgram'])]['mode']),
    'planner stat4 expression partial current source next245 cursor sample count' => static fn (TestRunner $t) => $t->same(4, $plan245()['cursorProgram'][array_key_last($plan245()['cursorProgram'])]['sampleCount']),
    'planner stat4 expression partial current source next245 cursor anchored count' => static fn (TestRunner $t) => $t->same(4, $plan245()['cursorProgram'][array_key_last($plan245()['cursorProgram'])]['anchoredSampleCount']),
    'planner stat4 expression partial current source next245 cursor rowids' => static fn (TestRunner $t) => $t->same([20, 50, 30, 60], $plan245()['cursorProgram'][array_key_last($plan245()['cursorProgram'])]['anchoredSampleRowids']),
    'planner stat4 expression partial current source next245 cursor signature' => static fn (TestRunner $t) => $t->same($plan245()['stat4SampleAnchorFence']['proofSignature'], $plan245()['cursorProgram'][array_key_last($plan245()['cursorProgram'])]['signature']),
    'planner stat4 expression partial current source next245 detail' => static fn (TestRunner $t) => $t->contains('NEXT245 SAMPLE ANCHOR FENCE', $plan245()['detail']),
    'planner stat4 expression partial current source next245 dependency marker' => static fn (TestRunner $t) => $t->true(in_array('sqlite-sqlplanner-stat4-expression-partial-current-source-next245', $plan245()['dependencies'], true)),
    'planner stat4 expression partial current source next245 dependency closure' => static fn (TestRunner $t) => $t->contains('no new support component needed', $plan245()['dependency_closure']),
    'planner stat4 expression partial current source next245 non overlap' => static fn (TestRunner $t) => $t->contains('sample-rowid anchor validation', $plan245()['non_overlap']),
    'planner stat4 expression partial current source next245 stale rowid blocks' => static fn (TestRunner $t) => $t->same('requires-current-source-stat4-sample-anchor-reprepare', $staleRowid245()['status']),
    'planner stat4 expression partial current source next245 stale rowid rejected' => static fn (TestRunner $t) => $t->same([50], $staleRowid245()['stat4SampleAnchorFence']['rejectedSampleRowids']),
    'planner stat4 expression partial current source next245 stale rowid current key' => static fn (TestRunner $t) => $t->same('plugin_mail', $staleRowid245()['stat4SampleAnchorFence']['sampleAnchorProofs'][0]['currentExpressionKey']),
    'planner stat4 expression partial current source next245 stale key blocks' => static fn (TestRunner $t) => $t->same('requires-current-source-stat4-sample-anchor-reprepare', $staleKey245()['status']),
    'planner stat4 expression partial current source next245 stale key rejected' => static fn (TestRunner $t) => $t->same([20], $staleKey245()['stat4SampleAnchorFence']['rejectedSampleRowids']),
    'planner stat4 expression partial current source next245 stale key proof mismatch' => static fn (TestRunner $t) => $t->same(false, $staleKey245()['stat4SampleAnchorFence']['sampleAnchorProofs'][0]['matchesCurrentAnchor']),
    'planner stat4 expression partial current source next245 stale blog blocks' => static fn (TestRunner $t) => $t->same('requires-current-source-stat4-sample-anchor-reprepare', $staleBlog245()['status']),
    'planner stat4 expression partial current source next245 stale blog rejected' => static fn (TestRunner $t) => $t->same([20], $staleBlog245()['stat4SampleAnchorFence']['rejectedSampleRowids']),
    'planner stat4 expression partial current source next245 stale blog proof current' => static fn (TestRunner $t) => $t->same(1, $staleBlog245()['stat4SampleAnchorFence']['sampleAnchorProofs'][0]['currentBlogId']),
    'planner stat4 expression partial current source next245 deleted anchor blocks' => static fn (TestRunner $t) => $t->same('requires-current-source-stat4-sample-anchor-reprepare', $deletedAnchor245()['status']),
    'planner stat4 expression partial current source next245 deleted anchor missing proof' => static fn (TestRunner $t) => $t->same(false, $deletedAnchor245()['stat4SampleAnchorFence']['sampleAnchorProofs'][1]['currentRowPresent']),
    'planner stat4 expression partial current source next245 malformed missing blog' => static function (TestRunner $t) use ($plan245): void {
        $t->throws(InvalidArgumentException::class, static fn () => $plan245([
            ['neq' => '3 3', 'nlt' => '0 0', 'ndlt' => '0 0', 'sample' => ['plugin_forms', 20]],
        ]));
    },
    'planner stat4 expression partial current source next245 malformed rowid' => static function (TestRunner $t) use ($plan245): void {
        $t->throws(InvalidArgumentException::class, static fn () => $plan245([
            ['neq' => '3 3', 'nlt' => '0 0', 'ndlt' => '0 0', 'sample' => ['plugin_forms', 'bad', 1]],
        ]));
    },
];

foreach (range(1, 28) as $case) {
    $tests['planner stat4 expression partial current source next245 repeated sample anchor proof ' . $case] = static function (TestRunner $t) use ($plan245, $case): void {
        $plan = $plan245(null, null, 4 + ($case % 3), $case % 2);
        $t->same($plan['stat4SampleAnchorFence']['proofSignature'], $plan['selectedPlan']['next245ProofSignature']);
    };
}

return $tests;

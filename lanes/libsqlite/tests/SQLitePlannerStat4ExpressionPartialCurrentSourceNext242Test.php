<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan;

$eq242 = static fn (string $column, mixed $right): array => ['left' => ['column' => $column], 'operator' => '=', 'right' => $right];
$like242 = static fn (string $column, string $right): array => ['left' => ['column' => $column], 'operator' => 'LIKE', 'right' => $right];
$notNull242 = static fn (string $column): array => ['left' => ['column' => $column], 'operator' => 'IS NOT NULL'];
$between242 = static fn (string $expression, mixed $lower, mixed $upper): array => ['left' => ['expression' => $expression], 'operator' => 'BETWEEN', 'lower' => $lower, 'upper' => $upper];

$rows242 = static fn (): array => [
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

$samples242 = static fn (?array $override = null): array => $override ?? [
    ['neq' => '3 3', 'nlt' => '0 0', 'ndlt' => '0 0', 'sample' => ['plugin_forms', 20, 1]],
    ['neq' => '1 1', 'nlt' => '3 3', 'ndlt' => '1 1', 'sample' => ['plugin_mail', 50, 1]],
    ['neq' => '1 1', 'nlt' => '4 4', 'ndlt' => '2 2', 'sample' => ['plugin_seo', 30, 1]],
    ['neq' => '1 1', 'nlt' => '5 5', 'ndlt' => '3 3', 'sample' => ['plugin_zulu', 60, 1]],
];

$prepared242 = static fn (): array => [
    'name' => 'prepared-wp-options-stat4-histogram-next242',
    'schemaCookie' => 2420,
    'stat4Generation' => 242,
    'rows' => [
        ['rowid' => 20, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_forms', 'option_value' => 'forms-old', 'updated_at' => 20],
        ['rowid' => 30, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_seo', 'option_value' => 'seo-old', 'updated_at' => 30],
        ['rowid' => 60, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_zulu', 'option_value' => 'zulu-old', 'updated_at' => 60],
    ],
    'indexes' => [[
        'name' => 'idx_wp_options_lower_histogram_next242',
        'rootPage' => 24201,
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

$current242 = static function (?array $samples = null, ?array $rows = null) use ($prepared242, $rows242, $samples242): array {
    $source = $prepared242();
    $source['name'] = 'current-wp-options-stat4-histogram-next242';
    $source['schemaCookie'] = 2428;
    $source['stat4Generation'] = 428;
    $source['rows'] = $rows ?? $rows242();
    $source['indexes'][0]['rootPage'] = 24288;
    unset($source['indexes'][0]['estimatedPartialRows']);
    $source['indexes'][0]['stat1'] = ['rows' => '6 2 1'];
    $source['indexes'][0]['stat4Samples'] = $samples242($samples);

    return $source;
};

$terms242 = static fn (): array => [
    $between242('LOWER(option_name)', 'plugin_forms', 'plugin_zulu'),
    $eq242('autoload', 'yes'),
    $notNull242('option_name'),
    $eq242('blog_id', 1),
    $like242('option_name', 'plugin_%'),
];

$plan242 = static fn (?array $samples = null, ?array $rows = null, int $limit = 6, int $offset = 0): array => SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan::materializeNext242(
    $prepared242(),
    $current242($samples, $rows),
    $terms242(),
    ['option_name', 'option_value', 'updated_at', 'blog_id'],
    $limit,
    $offset,
);

$staleNeq242 = static fn (): array => $plan242([
    ['neq' => '2 2', 'nlt' => '0 0', 'ndlt' => '0 0', 'sample' => ['plugin_forms', 20, 1]],
    ['neq' => '1 1', 'nlt' => '3 3', 'ndlt' => '1 1', 'sample' => ['plugin_mail', 50, 1]],
    ['neq' => '1 1', 'nlt' => '4 4', 'ndlt' => '2 2', 'sample' => ['plugin_seo', 30, 1]],
    ['neq' => '1 1', 'nlt' => '5 5', 'ndlt' => '3 3', 'sample' => ['plugin_zulu', 60, 1]],
]);
$staleNlt242 = static fn (): array => $plan242([
    ['neq' => '3 3', 'nlt' => '0 0', 'ndlt' => '0 0', 'sample' => ['plugin_forms', 20, 1]],
    ['neq' => '1 1', 'nlt' => '2 2', 'ndlt' => '1 1', 'sample' => ['plugin_mail', 50, 1]],
    ['neq' => '1 1', 'nlt' => '4 4', 'ndlt' => '2 2', 'sample' => ['plugin_seo', 30, 1]],
    ['neq' => '1 1', 'nlt' => '5 5', 'ndlt' => '3 3', 'sample' => ['plugin_zulu', 60, 1]],
]);
$staleNdlt242 = static fn (): array => $plan242([
    ['neq' => '3 3', 'nlt' => '0 0', 'ndlt' => '0 0', 'sample' => ['plugin_forms', 20, 1]],
    ['neq' => '1 1', 'nlt' => '3 3', 'ndlt' => '0 0', 'sample' => ['plugin_mail', 50, 1]],
    ['neq' => '1 1', 'nlt' => '4 4', 'ndlt' => '2 2', 'sample' => ['plugin_seo', 30, 1]],
    ['neq' => '1 1', 'nlt' => '5 5', 'ndlt' => '3 3', 'sample' => ['plugin_zulu', 60, 1]],
]);

$tests = [
    'planner stat4 expression partial current source next242 status ready' => static fn (TestRunner $t) => $t->same('stat4-expression-partial-current-source-next242-ready', $plan242()['status']),
    'planner stat4 expression partial current source next242 inherits next239' => static fn (TestRunner $t) => $t->same(true, $plan242()['selectedPlan']['next239Ready']),
    'planner stat4 expression partial current source next242 ready flag' => static fn (TestRunner $t) => $t->same(true, $plan242()['selectedPlan']['next242Ready']),
    'planner stat4 expression partial current source next242 selected current' => static fn (TestRunner $t) => $t->same('current', $plan242()['selectedSource']),
    'planner stat4 expression partial current source next242 selected index' => static fn (TestRunner $t) => $t->same('idx_wp_options_lower_histogram_next242', $plan242()['selectedPlan']['name']),
    'planner stat4 expression partial current source next242 root page' => static fn (TestRunner $t) => $t->same(24288, $plan242()['selectedPlan']['rootPage']),
    'planner stat4 expression partial current source next242 matched rowids' => static fn (TestRunner $t) => $t->same([60, 30, 50, 20, 21, 22], $plan242()['matchedRowids']),
    'planner stat4 expression partial current source next242 projected payload' => static fn (TestRunner $t) => $t->same('forms-copy-b', $plan242()['projectedRows'][5]['option_value']),
    'planner stat4 expression partial current source next242 partial count' => static fn (TestRunner $t) => $t->same(6, $plan242()['stat4HistogramFence']['partialRowCount']),
    'planner stat4 expression partial current source next242 sample count' => static fn (TestRunner $t) => $t->same(4, $plan242()['stat4HistogramFence']['sampleCount']),
    'planner stat4 expression partial current source next242 matched sample count' => static fn (TestRunner $t) => $t->same(4, $plan242()['stat4HistogramFence']['matchedSampleCount']),
    'planner stat4 expression partial current source next242 no rejected samples' => static fn (TestRunner $t) => $t->same([], $plan242()['stat4HistogramFence']['rejectedSamples']),
    'planner stat4 expression partial current source next242 rejected reason null' => static fn (TestRunner $t) => $t->same(null, $plan242()['stat4HistogramFence']['rejectedReason']),
    'planner stat4 expression partial current source next242 partial rowids' => static fn (TestRunner $t) => $t->same([20, 21, 22, 50, 30, 60], $plan242()['stat4HistogramFence']['partialRowids']),
    'planner stat4 expression partial current source next242 first expected neq' => static fn (TestRunner $t) => $t->same([3, 3], $plan242()['stat4HistogramFence']['sampleProofs'][0]['expected']['neq']),
    'planner stat4 expression partial current source next242 first actual neq' => static fn (TestRunner $t) => $t->same([3, 3], $plan242()['stat4HistogramFence']['sampleProofs'][0]['actual']['neq']),
    'planner stat4 expression partial current source next242 mail nlt' => static fn (TestRunner $t) => $t->same([3, 3], $plan242()['stat4HistogramFence']['sampleProofs'][1]['expected']['nlt']),
    'planner stat4 expression partial current source next242 seo ndlt' => static fn (TestRunner $t) => $t->same([2, 2], $plan242()['stat4HistogramFence']['sampleProofs'][2]['expected']['ndlt']),
    'planner stat4 expression partial current source next242 zulu nlt' => static fn (TestRunner $t) => $t->same([5, 5], $plan242()['stat4HistogramFence']['sampleProofs'][3]['expected']['nlt']),
    'planner stat4 expression partial current source next242 proof signature length' => static fn (TestRunner $t) => $t->same(64, strlen($plan242()['stat4HistogramFence']['proofSignature'])),
    'planner stat4 expression partial current source next242 histogram signature length' => static fn (TestRunner $t) => $t->same(64, strlen($plan242()['stat4HistogramFence']['histogramSignature'])),
    'planner stat4 expression partial current source next242 selected matched' => static fn (TestRunner $t) => $t->same(4, $plan242()['selectedPlan']['next242MatchedSamples']),
    'planner stat4 expression partial current source next242 selected rejected' => static fn (TestRunner $t) => $t->same([], $plan242()['selectedPlan']['next242RejectedSamples']),
    'planner stat4 expression partial current source next242 selected signature' => static fn (TestRunner $t) => $t->same($plan242()['stat4HistogramFence']['proofSignature'], $plan242()['selectedPlan']['next242ProofSignature']),
    'planner stat4 expression partial current source next242 stat4 ready' => static fn (TestRunner $t) => $t->same(true, $plan242()['stat4Fence']['next242HistogramReady']),
    'planner stat4 expression partial current source next242 stat4 rejected' => static fn (TestRunner $t) => $t->same([], $plan242()['stat4Fence']['next242RejectedSamples']),
    'planner stat4 expression partial current source next242 stat4 signature' => static fn (TestRunner $t) => $t->same($plan242()['stat4HistogramFence']['proofSignature'], $plan242()['stat4Fence']['next242HistogramSignature']),
    'planner stat4 expression partial current source next242 cursor appended' => static fn (TestRunner $t) => $t->same('ValidateCurrentSourceStat4Histogram', $plan242()['cursorProgram'][array_key_last($plan242()['cursorProgram'])]['opcode']),
    'planner stat4 expression partial current source next242 cursor mode' => static fn (TestRunner $t) => $t->same('next242-current-source-stat4-expression-partial-histogram', $plan242()['cursorProgram'][array_key_last($plan242()['cursorProgram'])]['mode']),
    'planner stat4 expression partial current source next242 cursor sample count' => static fn (TestRunner $t) => $t->same(4, $plan242()['cursorProgram'][array_key_last($plan242()['cursorProgram'])]['sampleCount']),
    'planner stat4 expression partial current source next242 cursor matched sample count' => static fn (TestRunner $t) => $t->same(4, $plan242()['cursorProgram'][array_key_last($plan242()['cursorProgram'])]['matchedSampleCount']),
    'planner stat4 expression partial current source next242 cursor signature' => static fn (TestRunner $t) => $t->same($plan242()['stat4HistogramFence']['proofSignature'], $plan242()['cursorProgram'][array_key_last($plan242()['cursorProgram'])]['signature']),
    'planner stat4 expression partial current source next242 detail' => static fn (TestRunner $t) => $t->contains('NEXT242 HISTOGRAM FENCE', $plan242()['detail']),
    'planner stat4 expression partial current source next242 dependency marker' => static fn (TestRunner $t) => $t->true(in_array('sqlite-sqlplanner-stat4-expression-partial-current-source-next242', $plan242()['dependencies'], true)),
    'planner stat4 expression partial current source next242 dependency closure' => static fn (TestRunner $t) => $t->contains('no new support component needed', $plan242()['dependency_closure']),
    'planner stat4 expression partial current source next242 non overlap' => static fn (TestRunner $t) => $t->contains('STAT4 neq/nlt/ndlt histogram validation', $plan242()['non_overlap']),
    'planner stat4 expression partial current source next242 stale neq blocks' => static fn (TestRunner $t) => $t->same('requires-current-source-stat4-histogram-reprepare', $staleNeq242()['status']),
    'planner stat4 expression partial current source next242 stale neq rejected sample' => static fn (TestRunner $t) => $t->same(['plugin_forms:1'], $staleNeq242()['stat4HistogramFence']['rejectedSamples']),
    'planner stat4 expression partial current source next242 stale neq expected' => static fn (TestRunner $t) => $t->same([3, 3], $staleNeq242()['stat4HistogramFence']['sampleProofs'][0]['expected']['neq']),
    'planner stat4 expression partial current source next242 stale neq actual' => static fn (TestRunner $t) => $t->same([2, 2], $staleNeq242()['stat4HistogramFence']['sampleProofs'][0]['actual']['neq']),
    'planner stat4 expression partial current source next242 stale nlt blocks' => static fn (TestRunner $t) => $t->same('requires-current-source-stat4-histogram-reprepare', $staleNlt242()['status']),
    'planner stat4 expression partial current source next242 stale nlt rejected sample' => static fn (TestRunner $t) => $t->same(['plugin_mail:1'], $staleNlt242()['stat4HistogramFence']['rejectedSamples']),
    'planner stat4 expression partial current source next242 stale ndlt blocks' => static fn (TestRunner $t) => $t->same('requires-current-source-stat4-histogram-reprepare', $staleNdlt242()['status']),
    'planner stat4 expression partial current source next242 stale ndlt rejected sample' => static fn (TestRunner $t) => $t->same(['plugin_mail:1'], $staleNdlt242()['stat4HistogramFence']['rejectedSamples']),
    'planner stat4 expression partial current source next242 stale no cursor append' => static fn (TestRunner $t) => $t->same(false, in_array('ValidateCurrentSourceStat4Histogram', array_column($staleNeq242()['cursorProgram'], 'opcode'), true)),
    'planner stat4 expression partial current source next242 malformed neq' => static function (TestRunner $t) use ($plan242): void {
        $t->throws(InvalidArgumentException::class, static fn () => $plan242([
            ['neq' => 'bad', 'nlt' => '0 0', 'ndlt' => '0 0', 'sample' => ['plugin_forms', 20, 1]],
        ]));
    },
    'planner stat4 expression partial current source next242 malformed sample' => static function (TestRunner $t) use ($plan242): void {
        $t->throws(InvalidArgumentException::class, static fn () => $plan242([
            ['neq' => '1 1', 'nlt' => '0 0', 'ndlt' => '0 0', 'sample' => ['plugin_forms']],
        ]));
    },
    'planner stat4 expression partial current source next242 empty samples' => static function (TestRunner $t) use ($plan242): void {
        $t->throws(InvalidArgumentException::class, static fn () => $plan242([]));
    },
];

foreach (range(1, 24) as $case) {
    $tests['planner stat4 expression partial current source next242 repeated histogram proof ' . $case] = static function (TestRunner $t) use ($plan242, $case): void {
        $plan = $plan242(null, null, 4 + ($case % 3), $case % 2);
        $t->same($plan['stat4HistogramFence']['proofSignature'], $plan['selectedPlan']['next242ProofSignature']);
    };
}

return $tests;

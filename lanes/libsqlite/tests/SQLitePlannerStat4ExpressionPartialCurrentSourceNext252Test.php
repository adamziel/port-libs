<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePlannerStat4ExpressionPartialCurrentSourceNext252Plan;

$eq252 = static fn (string $column, mixed $right): array => ['left' => ['column' => $column], 'operator' => '=', 'right' => $right];
$like252 = static fn (string $column, string $right): array => ['left' => ['column' => $column], 'operator' => 'LIKE', 'right' => $right];
$notNull252 = static fn (string $column): array => ['left' => ['column' => $column], 'operator' => 'IS NOT NULL'];
$between252 = static fn (string $expression, mixed $lower, mixed $upper): array => ['left' => ['expression' => $expression], 'operator' => 'BETWEEN', 'lower' => $lower, 'upper' => $upper];

$rows252 = static fn (): array => [
    ['rowid' => 60, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_zulu', 'option_value' => 'zulu', 'updated_at' => 60],
    ['rowid' => 30, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_seo', 'option_value' => 'seo', 'updated_at' => 30],
    ['rowid' => 50, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'Plugin_Mail', 'option_value' => 'mail', 'updated_at' => 50],
    ['rowid' => 20, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_forms', 'option_value' => 'forms-anchor', 'updated_at' => 20],
    ['rowid' => 21, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'Plugin_Forms', 'option_value' => 'forms-copy-a', 'updated_at' => 21],
    ['rowid' => 22, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'PLUGIN_FORMS', 'option_value' => 'forms-copy-b', 'updated_at' => 22],
    ['rowid' => 70, 'blog_id' => 1, 'autoload' => 'no', 'option_name' => 'plugin_forms', 'option_value' => 'lazy', 'updated_at' => 70],
    ['rowid' => 80, 'blog_id' => 2, 'autoload' => 'yes', 'option_name' => 'plugin_forms', 'option_value' => 'other-blog', 'updated_at' => 80],
];

$samples252 = static fn (?array $override = null): array => $override ?? [
    ['neq' => '3 3', 'nlt' => '0 0', 'ndlt' => '0 0', 'sample' => ['plugin_forms', 20, 1]],
    ['neq' => '1 1', 'nlt' => '3 3', 'ndlt' => '1 1', 'sample' => ['plugin_mail', 50, 1]],
    ['neq' => '1 1', 'nlt' => '4 4', 'ndlt' => '2 2', 'sample' => ['plugin_seo', 30, 1]],
    ['neq' => '1 1', 'nlt' => '5 5', 'ndlt' => '3 3', 'sample' => ['plugin_zulu', 60, 1]],
];

$prepared252 = static fn (): array => [
    'name' => 'prepared-wp-options-stat4-direction-next252',
    'schemaCookie' => 2520,
    'stat4Generation' => 252,
    'rows' => [
        ['rowid' => 20, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_forms', 'option_value' => 'forms-old', 'updated_at' => 20],
        ['rowid' => 30, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_seo', 'option_value' => 'seo-old', 'updated_at' => 30],
        ['rowid' => 60, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_zulu', 'option_value' => 'zulu-old', 'updated_at' => 60],
    ],
    'indexes' => [[
        'name' => 'idx_wp_options_lower_direction_next252',
        'rootPage' => 25201,
        'expression' => 'lower(option_name)',
        'expressionColumn' => '__expr_lower_option_name',
        'collation' => 'BINARY',
        'descending' => true,
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

$current252 = static function (?array $samples = null, ?array $rows = null, bool $descending = true) use ($prepared252, $rows252, $samples252): array {
    $source = $prepared252();
    $source['name'] = 'current-wp-options-stat4-direction-next252';
    $source['schemaCookie'] = 2528;
    $source['stat4Generation'] = 852;
    $source['rows'] = $rows ?? $rows252();
    $source['indexes'][0]['rootPage'] = 25288;
    $source['indexes'][0]['descending'] = $descending;
    $source['indexes'][0]['stat1'] = ['rows' => '6 2 1'];
    $source['indexes'][0]['stat4Samples'] = $samples252($samples);

    return $source;
};

$terms252 = static fn (): array => [
    $between252('LOWER(option_name)', 'plugin_forms', 'plugin_zulu'),
    $eq252('autoload', 'yes'),
    $notNull252('option_name'),
    $eq252('blog_id', 1),
    $like252('option_name', 'plugin_%'),
];

$plan252 = static fn (?array $samples = null, ?array $rows = null, int $limit = 6, int $offset = 0, bool $descending = true): array => SQLitePlannerStat4ExpressionPartialCurrentSourceNext252Plan::materialize(
    $prepared252(),
    $current252($samples, $rows, $descending),
    $terms252(),
    ['option_name', 'option_value', 'updated_at', 'blog_id'],
    $limit,
    $offset,
);

$shuffledSamples252 = static fn (): array => $plan252([
    ['neq' => '1 1', 'nlt' => '3 3', 'ndlt' => '3 3', 'sample' => ['plugin_zulu', 60, 1]],
    ['neq' => '3 3', 'nlt' => '0 0', 'ndlt' => '0 0', 'sample' => ['plugin_forms', 20, 1]],
    ['neq' => '1 1', 'nlt' => '3 3', 'ndlt' => '1 1', 'sample' => ['plugin_mail', 50, 1]],
    ['neq' => '1 1', 'nlt' => '4 4', 'ndlt' => '2 2', 'sample' => ['plugin_seo', 30, 1]],
]);
$staleAnchor252 = static fn (): array => $plan252([
    ['neq' => '3 3', 'nlt' => '0 0', 'ndlt' => '0 0', 'sample' => ['plugin_forms', 20, 1]],
    ['neq' => '1 1', 'nlt' => '3 3', 'ndlt' => '1 1', 'sample' => ['plugin_mail', 50, 1]],
    ['neq' => '1 1', 'nlt' => '4 4', 'ndlt' => '2 2', 'sample' => ['plugin_seo', 30, 1]],
]);

$tests = [
    'planner stat4 expression partial current source next252 status ready' => static fn (TestRunner $t) => $t->same('stat4-expression-partial-current-source-next252-ready', $plan252()['status']),
    'planner stat4 expression partial current source next252 inherits next249' => static fn (TestRunner $t) => $t->same(true, $plan252()['selectedPlan']['next249Ready']),
    'planner stat4 expression partial current source next252 ready flag' => static fn (TestRunner $t) => $t->same(true, $plan252()['selectedPlan']['next252Ready']),
    'planner stat4 expression partial current source next252 selected current' => static fn (TestRunner $t) => $t->same('current', $plan252()['selectedSource']),
    'planner stat4 expression partial current source next252 selected index' => static fn (TestRunner $t) => $t->same('idx_wp_options_lower_direction_next252', $plan252()['selectedPlan']['name']),
    'planner stat4 expression partial current source next252 root page' => static fn (TestRunner $t) => $t->same(25288, $plan252()['selectedPlan']['rootPage']),
    'planner stat4 expression partial current source next252 matched rowids' => static fn (TestRunner $t) => $t->same([60, 30, 50, 20, 21, 22], $plan252()['matchedRowids']),
    'planner stat4 expression partial current source next252 descending flag' => static fn (TestRunner $t) => $t->same(true, $plan252()['stat4ScanDirectionFence']['descending']),
    'planner stat4 expression partial current source next252 ascending stat4 rowids' => static fn (TestRunner $t) => $t->same([20, 50, 30, 60], $plan252()['stat4ScanDirectionFence']['ascendingStat4Rowids']),
    'planner stat4 expression partial current source next252 ascending stat4 keys' => static fn (TestRunner $t) => $t->same(['plugin_forms', 'plugin_mail', 'plugin_seo', 'plugin_zulu'], $plan252()['stat4ScanDirectionFence']['ascendingStat4Keys']),
    'planner stat4 expression partial current source next252 reverse anchors' => static fn (TestRunner $t) => $t->same([60, 30, 50, 20], $plan252()['stat4ScanDirectionFence']['reverseStat4AnchorRowids']),
    'planner stat4 expression partial current source next252 page anchors' => static fn (TestRunner $t) => $t->same([60, 30, 50, 20], $plan252()['stat4ScanDirectionFence']['pageAnchorRowids']),
    'planner stat4 expression partial current source next252 expected anchors' => static fn (TestRunner $t) => $t->same([60, 30, 50, 20], $plan252()['stat4ScanDirectionFence']['expectedPageAnchorRowids']),
    'planner stat4 expression partial current source next252 qualified prefix' => static fn (TestRunner $t) => $t->same([60, 30, 50, 20], $plan252()['stat4ScanDirectionFence']['qualifiedAnchorPrefix']),
    'planner stat4 expression partial current source next252 qualified rowids' => static fn (TestRunner $t) => $t->same([60, 30, 50, 20, 21, 22], $plan252()['stat4ScanDirectionFence']['qualifiedRowids']),
    'planner stat4 expression partial current source next252 qualified keys' => static fn (TestRunner $t) => $t->same(['plugin_zulu', 'plugin_seo', 'plugin_mail', 'plugin_forms', 'plugin_forms', 'plugin_forms'], $plan252()['stat4ScanDirectionFence']['qualifiedExpressionKeys']),
    'planner stat4 expression partial current source next252 no rejects' => static fn (TestRunner $t) => $t->same([], $plan252()['stat4ScanDirectionFence']['rejectedReasons']),
    'planner stat4 expression partial current source next252 rejected reason null' => static fn (TestRunner $t) => $t->same(null, $plan252()['stat4ScanDirectionFence']['rejectedReason']),
    'planner stat4 expression partial current source next252 sample count' => static fn (TestRunner $t) => $t->same(4, $plan252()['stat4ScanDirectionFence']['sampleCount']),
    'planner stat4 expression partial current source next252 matched row count' => static fn (TestRunner $t) => $t->same(6, $plan252()['stat4ScanDirectionFence']['matchedRowCount']),
    'planner stat4 expression partial current source next252 selected anchors' => static fn (TestRunner $t) => $t->same([60, 30, 50, 20], $plan252()['selectedPlan']['next252ReverseAnchorRowids']),
    'planner stat4 expression partial current source next252 selected page anchors' => static fn (TestRunner $t) => $t->same([60, 30, 50, 20], $plan252()['selectedPlan']['next252PageAnchorRowids']),
    'planner stat4 expression partial current source next252 selected rejected' => static fn (TestRunner $t) => $t->same([], $plan252()['selectedPlan']['next252RejectedReasons']),
    'planner stat4 expression partial current source next252 stat4 ready' => static fn (TestRunner $t) => $t->same(true, $plan252()['stat4Fence']['next252ScanDirectionReady']),
    'planner stat4 expression partial current source next252 proof signature length' => static fn (TestRunner $t) => $t->same(64, strlen($plan252()['stat4ScanDirectionFence']['proofSignature'])),
    'planner stat4 expression partial current source next252 selected signature' => static fn (TestRunner $t) => $t->same($plan252()['stat4ScanDirectionFence']['proofSignature'], $plan252()['selectedPlan']['next252ProofSignature']),
    'planner stat4 expression partial current source next252 stat4 signature' => static fn (TestRunner $t) => $t->same($plan252()['stat4ScanDirectionFence']['proofSignature'], $plan252()['stat4Fence']['next252ScanDirectionSignature']),
    'planner stat4 expression partial current source next252 cursor appended' => static fn (TestRunner $t) => $t->same('ValidateCurrentSourceStat4ScanDirection', $plan252()['cursorProgram'][array_key_last($plan252()['cursorProgram'])]['opcode']),
    'planner stat4 expression partial current source next252 cursor mode' => static fn (TestRunner $t) => $t->same('next252-current-source-stat4-expression-partial-scan-direction', $plan252()['cursorProgram'][array_key_last($plan252()['cursorProgram'])]['mode']),
    'planner stat4 expression partial current source next252 cursor ready' => static fn (TestRunner $t) => $t->same(true, $plan252()['cursorProgram'][array_key_last($plan252()['cursorProgram'])]['ready']),
    'planner stat4 expression partial current source next252 cursor anchors' => static fn (TestRunner $t) => $t->same([60, 30, 50, 20], $plan252()['cursorProgram'][array_key_last($plan252()['cursorProgram'])]['reverseStat4AnchorRowids']),
    'planner stat4 expression partial current source next252 cursor page anchors' => static fn (TestRunner $t) => $t->same([60, 30, 50, 20], $plan252()['cursorProgram'][array_key_last($plan252()['cursorProgram'])]['pageAnchorRowids']),
    'planner stat4 expression partial current source next252 cursor rejected' => static fn (TestRunner $t) => $t->same([], $plan252()['cursorProgram'][array_key_last($plan252()['cursorProgram'])]['rejectedReasons']),
    'planner stat4 expression partial current source next252 detail' => static fn (TestRunner $t) => $t->contains('NEXT252 SCAN DIRECTION FENCE', $plan252()['detail']),
    'planner stat4 expression partial current source next252 dependency marker' => static fn (TestRunner $t) => $t->true(in_array('sqlite-sqlplanner-stat4-expression-partial-current-source-next252', $plan252()['dependencies'], true)),
    'planner stat4 expression partial current source next252 dependency closure' => static fn (TestRunner $t) => $t->contains('no new support component needed', $plan252()['dependency_closure']),
    'planner stat4 expression partial current source next252 non overlap' => static fn (TestRunner $t) => $t->contains('scan-direction page-anchor validation', $plan252()['non_overlap']),
    'planner stat4 expression partial current source next252 shuffled blocks' => static fn (TestRunner $t) => $t->same('requires-current-source-stat4-scan-direction-reprepare', $shuffledSamples252()['status']),
    'planner stat4 expression partial current source next252 shuffled rejected sorted' => static fn (TestRunner $t) => $t->true(in_array('stat4-samples-not-ascending', $shuffledSamples252()['stat4ScanDirectionFence']['rejectedReasons'], true)),
    'planner stat4 expression partial current source next252 shuffled proof not ready' => static fn (TestRunner $t) => $t->same(false, $shuffledSamples252()['stat4ScanDirectionFence']['ready']),
    'planner stat4 expression partial current source next252 shuffled anchors' => static fn (TestRunner $t) => $t->same([30, 50, 20, 60], $shuffledSamples252()['stat4ScanDirectionFence']['reverseStat4AnchorRowids']),
    'planner stat4 expression partial current source next252 stale anchor blocks' => static fn (TestRunner $t) => $t->same('requires-current-source-stat4-scan-direction-reprepare', $staleAnchor252()['status']),
    'planner stat4 expression partial current source next252 stale anchor reason' => static fn (TestRunner $t) => $t->same(['descending-page-anchor-order'], $staleAnchor252()['stat4ScanDirectionFence']['rejectedReasons']),
    'planner stat4 expression partial current source next252 stale expected anchors' => static fn (TestRunner $t) => $t->same([60, 30, 50], $staleAnchor252()['stat4ScanDirectionFence']['expectedPageAnchorRowids']),
    'planner stat4 expression partial current source next252 stale page anchors' => static fn (TestRunner $t) => $t->same([30, 50, 20], $staleAnchor252()['stat4ScanDirectionFence']['pageAnchorRowids']),
    'planner stat4 expression partial current source next252 short page fence ready' => static fn (TestRunner $t) => $t->same(true, $plan252(null, null, 3)['stat4ScanDirectionFence']['ready']),
    'planner stat4 expression partial current source next252 short page anchors' => static fn (TestRunner $t) => $t->same([60, 30, 50], $plan252(null, null, 3)['stat4ScanDirectionFence']['pageAnchorRowids']),
    'planner stat4 expression partial current source next252 offset page fence ready' => static fn (TestRunner $t) => $t->same(true, $plan252(null, null, 4, 1)['stat4ScanDirectionFence']['ready']),
    'planner stat4 expression partial current source next252 offset anchors' => static fn (TestRunner $t) => $t->same([30, 50, 20], $plan252(null, null, 4, 1)['stat4ScanDirectionFence']['pageAnchorRowids']),
    'planner stat4 expression partial current source next252 malformed rows' => static function (TestRunner $t) use ($current252, $prepared252, $terms252): void {
        $bad = $current252();
        $bad['rows'] = 'bad';
        $t->throws(InvalidArgumentException::class, static fn () => SQLitePlannerStat4ExpressionPartialCurrentSourceNext252Plan::materialize($prepared252(), $bad, $terms252(), ['option_name'], 6));
    },
    'planner stat4 expression partial current source next252 malformed stat4' => static function (TestRunner $t) use ($plan252): void {
        $t->throws(InvalidArgumentException::class, static fn () => $plan252([
            ['neq' => '1 1', 'nlt' => '0 0', 'ndlt' => '0 0', 'sample' => ['plugin_forms']],
        ]));
    },
];

foreach (range(1, 16) as $case) {
    $tests['planner stat4 expression partial current source next252 repeated scan proof ' . $case] = static function (TestRunner $t) use ($plan252, $case): void {
        $plan = $plan252(null, null, 2 + ($case % 5), $case % 2);
        $t->same($plan['stat4ScanDirectionFence']['proofSignature'], $plan['selectedPlan']['next252ProofSignature']);
    };
}

return $tests;

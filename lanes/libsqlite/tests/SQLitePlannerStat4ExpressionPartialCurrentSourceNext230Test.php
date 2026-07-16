<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan;

$eq230 = static fn (string $column, mixed $right): array => ['left' => ['column' => $column], 'operator' => '=', 'right' => $right];
$like230 = static fn (string $column, string $right): array => ['left' => ['column' => $column], 'operator' => 'LIKE', 'right' => $right];
$notNull230 = static fn (string $column): array => ['left' => ['column' => $column], 'operator' => 'IS NOT NULL'];
$between230 = static fn (string $expression, mixed $lower, mixed $upper): array => ['left' => ['expression' => $expression], 'operator' => 'BETWEEN', 'lower' => $lower, 'upper' => $upper];

$prepared230 = static fn (): array => [
    'name' => 'prepared-wp-options-gap-density-stat4-expression-next230',
    'schemaCookie' => 2300,
    'stat4Generation' => 230,
    'rows' => [
        ['rowid' => 20, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_forms', 'option_value' => 'forms-old', 'updated_at' => 20],
        ['rowid' => 30, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_seo', 'option_value' => 'seo-old', 'updated_at' => 30],
        ['rowid' => 60, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_zulu', 'option_value' => 'zulu-old', 'updated_at' => 60],
    ],
    'indexes' => [[
        'name' => 'idx_wp_options_lower_gap_density_next230',
        'rootPage' => 23001,
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
            ['neq' => '1 1', 'nlt' => '0 0', 'ndlt' => '0 0', 'sample' => ['plugin_forms', 20]],
            ['neq' => '1 1', 'nlt' => '1 1', 'ndlt' => '1 1', 'sample' => ['plugin_mail', 50]],
            ['neq' => '1 1', 'nlt' => '2 2', 'ndlt' => '2 2', 'sample' => ['plugin_seo', 30]],
            ['neq' => '1 1', 'nlt' => '3 3', 'ndlt' => '3 3', 'sample' => ['plugin_zulu', 60]],
        ],
    ]],
];

$current230 = static function (array $overrides = []) use ($prepared230): array {
    $source = $prepared230();
    $source['name'] = 'current-wp-options-gap-density-stat4-expression-next230';
    $source['schemaCookie'] = 2308;
    $source['stat4Generation'] = 308;
    $source['indexes'][0]['rootPage'] = 23088;
    $source['indexes'][0]['stat4Samples'] = [
        ['neq' => '3 1', 'nlt' => '0 0', 'ndlt' => '0 0', 'sample' => ['plugin_forms', 20]],
        ['neq' => '1 1', 'nlt' => '3 1', 'ndlt' => '1 1', 'sample' => ['plugin_mail', 50]],
        ['neq' => '1 1', 'nlt' => '4 2', 'ndlt' => '2 2', 'sample' => ['plugin_seo', 30]],
        ['neq' => '1 1', 'nlt' => '5 3', 'ndlt' => '3 3', 'sample' => ['plugin_zulu', 60]],
        ['neq' => '1 1', 'nlt' => '6 4', 'ndlt' => '4 4', 'sample' => ['theme_mods_current', 90]],
    ];
    $source['rows'] = [
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
    foreach ($overrides as $key => $value) {
        $source[$key] = $value;
    }

    return $source;
};

$terms230 = static fn (): array => [
    $between230('LOWER(option_name)', 'plugin_forms', 'plugin_zulu'),
    $eq230('autoload', 'yes'),
    $notNull230('option_name'),
    $eq230('blog_id', 1),
    $like230('option_name', 'plugin_%'),
];
$plan230 = static fn (int $limit = 6, int $offset = 0, ?array $prepared = null, ?array $current = null, ?array $terms = null, ?array $needed = null): array => SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan::materializeCurrentSourceGapDensityFence(
    $prepared ?? $prepared230(),
    $current ?? $current230(),
    $terms ?? $terms230(),
    $needed ?? ['option_name', 'option_value', 'updated_at', 'blog_id'],
    $limit,
    $offset,
);
$unanchored230 = static function () use ($current230, $plan230): array {
    $current = $current230();
    $current['rows'][] = ['rowid' => 23, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_news', 'option_value' => 'news', 'updated_at' => 23];

    return $plan230(7, 0, null, $current);
};
$noGap230 = static function () use ($current230, $plan230): array {
    $current = $current230();
    $current['rows'] = array_values(array_filter($current['rows'], static fn (array $row): bool => !in_array($row['rowid'], [21, 22], true)));

    return $plan230(4, 0, null, $current);
};

$tests = [
    'planner stat4 expression partial current source next230 status ready' => static fn (TestRunner $t) => $t->same('stat4-expression-partial-current-source-next230-ready', $plan230()['status']),
    'planner stat4 expression partial current source next230 selected current' => static fn (TestRunner $t) => $t->same('current', $plan230()['selectedSource']),
    'planner stat4 expression partial current source next230 inherits next226' => static fn (TestRunner $t) => $t->same(true, $plan230()['selectedPlan']['next226Ready']),
    'planner stat4 expression partial current source next230 ready flag' => static fn (TestRunner $t) => $t->same(true, $plan230()['selectedPlan']['next230Ready']),
    'planner stat4 expression partial current source next230 selected index' => static fn (TestRunner $t) => $t->same('idx_wp_options_lower_gap_density_next230', $plan230()['selectedPlan']['name']),
    'planner stat4 expression partial current source next230 root page' => static fn (TestRunner $t) => $t->same(23088, $plan230()['selectedPlan']['rootPage']),
    'planner stat4 expression partial current source next230 matched rowids' => static fn (TestRunner $t) => $t->same([60, 30, 50, 20, 21, 22], $plan230()['matchedRowids']),
    'planner stat4 expression partial current source next230 sample rowids' => static fn (TestRunner $t) => $t->same([20, 50, 30, 60], $plan230()['stat4GapDensityFence']['sampleRowids']),
    'planner stat4 expression partial current source next230 gap rowids' => static fn (TestRunner $t) => $t->same([21, 22], $plan230()['stat4GapDensityFence']['gapRowids']),
    'planner stat4 expression partial current source next230 gap keys' => static fn (TestRunner $t) => $t->same(['plugin_forms'], $plan230()['stat4GapDensityFence']['gapKeys']),
    'planner stat4 expression partial current source next230 anchored samples' => static fn (TestRunner $t) => $t->same([20], $plan230()['stat4GapDensityFence']['anchoredSampleRowids']),
    'planner stat4 expression partial current source next230 gap count' => static fn (TestRunner $t) => $t->same(2, $plan230()['stat4GapDensityFence']['gapRowCount']),
    'planner stat4 expression partial current source next230 anchored count' => static fn (TestRunner $t) => $t->same(2, $plan230()['stat4GapDensityFence']['anchoredGapRowCount']),
    'planner stat4 expression partial current source next230 rejects none' => static fn (TestRunner $t) => $t->same([], $plan230()['stat4GapDensityFence']['rowidsRejectedByGapFence']),
    'planner stat4 expression partial current source next230 first gap bounded' => static fn (TestRunner $t) => $t->same(true, $plan230()['stat4GapDensityFence']['gapRows'][0]['boundedByPredicate']),
    'planner stat4 expression partial current source next230 first gap anchored' => static fn (TestRunner $t) => $t->same(true, $plan230()['stat4GapDensityFence']['gapRows'][0]['anchoredBySamplePeer']),
    'planner stat4 expression partial current source next230 second gap rowid' => static fn (TestRunner $t) => $t->same(22, $plan230()['stat4GapDensityFence']['gapRows'][1]['rowid']),
    'planner stat4 expression partial current source next230 second gap key' => static fn (TestRunner $t) => $t->same('plugin_forms', $plan230()['stat4GapDensityFence']['gapRows'][1]['expressionKey']),
    'planner stat4 expression partial current source next230 lower bound' => static fn (TestRunner $t) => $t->same('plugin_forms', $plan230()['stat4GapDensityFence']['lowerBound']),
    'planner stat4 expression partial current source next230 upper bound' => static fn (TestRunner $t) => $t->same('plugin_zulu', $plan230()['stat4GapDensityFence']['upperBound']),
    'planner stat4 expression partial current source next230 selected gap rowids' => static fn (TestRunner $t) => $t->same([21, 22], $plan230()['selectedPlan']['next230GapRowids']),
    'planner stat4 expression partial current source next230 selected anchored samples' => static fn (TestRunner $t) => $t->same([20], $plan230()['selectedPlan']['next230AnchoredSampleRowids']),
    'planner stat4 expression partial current source next230 selected gap keys' => static fn (TestRunner $t) => $t->same(['plugin_forms'], $plan230()['selectedPlan']['next230GapKeys']),
    'planner stat4 expression partial current source next230 signature length' => static fn (TestRunner $t) => $t->same(64, strlen($plan230()['stat4GapDensityFence']['gapSignature'])),
    'planner stat4 expression partial current source next230 proof length' => static fn (TestRunner $t) => $t->same(64, strlen($plan230()['stat4GapDensityFence']['proofSignature'])),
    'planner stat4 expression partial current source next230 selected signature' => static fn (TestRunner $t) => $t->same($plan230()['stat4GapDensityFence']['gapSignature'], $plan230()['selectedPlan']['next230GapSignature']),
    'planner stat4 expression partial current source next230 stat4 signature' => static fn (TestRunner $t) => $t->same($plan230()['stat4GapDensityFence']['gapSignature'], $plan230()['stat4Fence']['next230GapSignature']),
    'planner stat4 expression partial current source next230 stat4 proof signature' => static fn (TestRunner $t) => $t->same($plan230()['stat4GapDensityFence']['proofSignature'], $plan230()['stat4Fence']['next230ProofSignature']),
    'planner stat4 expression partial current source next230 stat4 ready' => static fn (TestRunner $t) => $t->same(true, $plan230()['stat4Fence']['next230GapReady']),
    'planner stat4 expression partial current source next230 cursor appended' => static fn (TestRunner $t) => $t->same('RecheckCurrentSourceStat4GapDensity', $plan230()['cursorProgram'][array_key_last($plan230()['cursorProgram'])]['opcode']),
    'planner stat4 expression partial current source next230 cursor mode' => static fn (TestRunner $t) => $t->same('next230-current-source-stat4-expression-partial-gap-density', $plan230()['cursorProgram'][array_key_last($plan230()['cursorProgram'])]['mode']),
    'planner stat4 expression partial current source next230 cursor gaps' => static fn (TestRunner $t) => $t->same([21, 22], $plan230()['cursorProgram'][array_key_last($plan230()['cursorProgram'])]['gapRowids']),
    'planner stat4 expression partial current source next230 cursor samples' => static fn (TestRunner $t) => $t->same([20], $plan230()['cursorProgram'][array_key_last($plan230()['cursorProgram'])]['anchoredSampleRowids']),
    'planner stat4 expression partial current source next230 cursor signature' => static fn (TestRunner $t) => $t->same($plan230()['stat4GapDensityFence']['proofSignature'], $plan230()['cursorProgram'][array_key_last($plan230()['cursorProgram'])]['signature']),
    'planner stat4 expression partial current source next230 window preserved' => static fn (TestRunner $t) => $t->same([20, 50, 30, 60], $plan230()['stat4SampleWindowFence']['currentWindowRowids']),
    'planner stat4 expression partial current source next230 peer preserved' => static fn (TestRunner $t) => $t->same(true, $plan230()['stat4PeerRunFence']['ready']),
    'planner stat4 expression partial current source next230 grouped like preserved' => static fn (TestRunner $t) => $t->same(true, $plan230()['groupedLikePredicateFence']['currentGroupedLikePredicateImplied']),
    'planner stat4 expression partial current source next230 grouped or preserved' => static fn (TestRunner $t) => $t->same(true, $plan230()['groupedPartialOrPredicateFence']['currentGroupedPartialOrPredicateImplied']),
    'planner stat4 expression partial current source next230 partial fence preserved' => static fn (TestRunner $t) => $t->same(true, $plan230()['partialPredicateFence']['currentPartialPredicateImplied']),
    'planner stat4 expression partial current source next230 projected gap payload' => static fn (TestRunner $t) => $t->same('forms-copy-b', $plan230()['projectedRows'][5]['option_value']),
    'planner stat4 expression partial current source next230 dependency marker' => static fn (TestRunner $t) => $t->true(in_array('sqlite-sqlplanner-stat4-expression-partial-current-source-next230', $plan230()['dependencies'], true)),
    'planner stat4 expression partial current source next230 dependency closure' => static fn (TestRunner $t) => $t->contains('no new support component needed', $plan230()['dependency_closure']),
    'planner stat4 expression partial current source next230 non overlap' => static fn (TestRunner $t) => $t->contains('gaps between STAT4 samples', $plan230()['non_overlap']),
    'planner stat4 expression partial current source next230 detail' => static fn (TestRunner $t) => $t->contains('NEXT230 GAP DENSITY', $plan230()['detail']),
    'planner stat4 expression partial current source next230 unanchored blocks' => static fn (TestRunner $t) => $t->same('requires-current-source-stat4-gap-reprepare', $unanchored230()['status']),
    'planner stat4 expression partial current source next230 unanchored row rejected' => static fn (TestRunner $t) => $t->same([23], $unanchored230()['stat4GapDensityFence']['rowidsRejectedByGapFence']),
    'planner stat4 expression partial current source next230 unanchored ready false' => static fn (TestRunner $t) => $t->same(false, $unanchored230()['selectedPlan']['next230Ready']),
    'planner stat4 expression partial current source next230 no gap blocks' => static fn (TestRunner $t) => $t->same('requires-current-source-stat4-gap-reprepare', $noGap230()['status']),
    'planner stat4 expression partial current source next230 no gap count zero' => static fn (TestRunner $t) => $t->same(0, $noGap230()['stat4GapDensityFence']['gapRowCount']),
    'planner stat4 expression partial current source next230 invalid limit' => static function (TestRunner $t) use ($plan230): void {
        $t->throws(InvalidArgumentException::class, static fn () => $plan230(-1, 0));
    },
    'planner stat4 expression partial current source next230 invalid offset' => static function (TestRunner $t) use ($plan230): void {
        $t->throws(InvalidArgumentException::class, static fn () => $plan230(1, -1));
    },
    'planner stat4 expression partial current source next230 missing sample rowids' => static function (TestRunner $t) use ($current230, $plan230): void {
        $current = $current230();
        unset($current['indexes'][0]['stat4Samples']);
        $t->throws(InvalidArgumentException::class, static fn () => $plan230(6, 0, null, $current));
    },
];

foreach (range(1, 14) as $case) {
    $tests['planner stat4 expression partial current source next230 repeated gap signature ' . $case] = static function (TestRunner $t) use ($plan230, $case): void {
        $plan = $plan230(5 + ($case % 2), 0);
        $t->same($plan['stat4GapDensityFence']['gapSignature'], $plan['selectedPlan']['next230GapSignature']);
    };
}

return $tests;

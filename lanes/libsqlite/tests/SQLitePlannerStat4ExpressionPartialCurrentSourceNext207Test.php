<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan;

$eq207 = static fn (string $column, mixed $right): array => ['left' => ['column' => $column], 'operator' => '=', 'right' => $right];
$notNull207 = static fn (string $column): array => ['left' => ['column' => $column], 'operator' => 'IS NOT NULL'];
$between207 = static fn (string $expression, mixed $lower, mixed $upper): array => ['left' => ['expression' => $expression], 'operator' => 'BETWEEN', 'lower' => $lower, 'upper' => $upper];

$prepared207 = static fn (): array => [
    'name' => 'prepared-wp-options-stat4-sample-partial-next207',
    'schemaCookie' => 2070,
    'stat4Generation' => 207,
    'rows' => [
        ['rowid' => 10, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_alpha', 'option_value' => 'alpha-old', 'updated_at' => 10],
        ['rowid' => 20, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_forms', 'option_value' => 'forms-old', 'updated_at' => 20],
        ['rowid' => 30, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_seo', 'option_value' => 'seo-old', 'updated_at' => 30],
    ],
    'indexes' => [[
        'name' => 'idx_wp_options_lower_partial_stat4_sample_next207',
        'rootPage' => 20701,
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
        'partialOrPredicateTerms' => [
            ['left' => ['column' => 'blog_id'], 'operator' => '=', 'right' => 1],
            ['left' => ['column' => 'autoload'], 'operator' => '=', 'right' => 'critical'],
        ],
        'coveringColumns' => ['option_name', 'option_value', 'updated_at', 'autoload', 'blog_id'],
        'stat4Samples' => [
            ['neq' => '1 1', 'nlt' => '0 0', 'ndlt' => '0 0', 'sample' => ['plugin_alpha', 10]],
            ['neq' => '1 1', 'nlt' => '1 1', 'ndlt' => '1 1', 'sample' => ['plugin_forms', 20]],
            ['neq' => '1 1', 'nlt' => '2 2', 'ndlt' => '2 2', 'sample' => ['plugin_seo', 30]],
        ],
    ]],
];

$current207 = static function () use ($prepared207): array {
    $source = $prepared207();
    $source['name'] = 'current-wp-options-stat4-sample-partial-next207';
    $source['schemaCookie'] = 2079;
    $source['stat4Generation'] = 247;
    $source['indexes'][0]['rootPage'] = 20788;
    $source['indexes'][0]['stat4Samples'] = [
        ['neq' => '1 1', 'nlt' => '0 0', 'ndlt' => '0 0', 'sample' => ['plugin_alpha', 10]],
        ['neq' => '1 1', 'nlt' => '1 1', 'ndlt' => '1 1', 'sample' => ['plugin_cache', 40]],
        ['neq' => '3 1', 'nlt' => '2 2', 'ndlt' => '2 2', 'sample' => ['plugin_forms', 20]],
        ['neq' => '1 1', 'nlt' => '5 3', 'ndlt' => '3 3', 'sample' => ['plugin_mail', 50]],
        ['neq' => '1 1', 'nlt' => '6 4', 'ndlt' => '4 4', 'sample' => ['plugin_seo', 30]],
        ['neq' => '1 1', 'nlt' => '7 5', 'ndlt' => '5 5', 'sample' => ['plugin_zulu', 60]],
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
        ['rowid' => 80, 'blog_id' => 2, 'autoload' => 'yes', 'option_name' => 'theme_mods_twentysix', 'option_value' => 'theme', 'updated_at' => 80],
    ];

    return $source;
};

$terms207 = static fn (): array => [
    $between207('LOWER(option_name)', 'plugin_alpha', 'plugin_zulu'),
    $eq207('autoload', 'yes'),
    $notNull207('option_name'),
    $eq207('blog_id', 1),
];
$plan207 = static fn (int $limit = 5, int $offset = 1, ?array $prepared = null, ?array $current = null, ?array $terms = null, ?array $needed = null): array => SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan::materializeNext207(
    $prepared ?? $prepared207(),
    $current ?? $current207(),
    $terms ?? $terms207(),
    $needed ?? ['option_name', 'option_value', 'updated_at', 'blog_id'],
    $limit,
    $offset,
);
$staleAutoloadSample207 = static function () use ($current207, $plan207): array {
    $current = $current207();
    foreach ($current['rows'] as &$row) {
        if (($row['rowid'] ?? null) === 40) {
            $row['autoload'] = 'no';
        }
    }
    unset($row);

    return $plan207(5, 1, null, $current);
};
$staleRangeSample207 = static function () use ($current207, $plan207): array {
    $current = $current207();
    $current['indexes'][0]['stat4Samples'][] = ['neq' => '1 1', 'nlt' => '8 6', 'ndlt' => '6 6', 'sample' => ['theme_mods_twentysix', 80]];

    return $plan207(5, 1, null, $current);
};
$missingSample207 = static function () use ($current207, $plan207): array {
    $current = $current207();
    $current['indexes'][0]['stat4Samples'][] = ['neq' => '1 1', 'nlt' => '8 6', 'ndlt' => '6 6', 'sample' => ['plugin_missing', 999]];

    return $plan207(5, 1, null, $current);
};

$tests = [
    'planner stat4 expression partial current source next207 status ready' => static fn (TestRunner $t) => $t->same('stat4-expression-partial-current-source-next207-ready', $plan207()['status']),
    'planner stat4 expression partial current source next207 selected current' => static fn (TestRunner $t) => $t->same('current', $plan207()['selectedSource']),
    'planner stat4 expression partial current source next207 inherited next206' => static fn (TestRunner $t) => $t->same(true, $plan207()['selectedPlan']['next206Ready']),
    'planner stat4 expression partial current source next207 ready flag' => static fn (TestRunner $t) => $t->same(true, $plan207()['selectedPlan']['next207Ready']),
    'planner stat4 expression partial current source next207 selected index' => static fn (TestRunner $t) => $t->same('idx_wp_options_lower_partial_stat4_sample_next207', $plan207()['selectedPlan']['name']),
    'planner stat4 expression partial current source next207 root page' => static fn (TestRunner $t) => $t->same(20788, $plan207()['selectedPlan']['rootPage']),
    'planner stat4 expression partial current source next207 sample count' => static fn (TestRunner $t) => $t->same(6, $plan207()['stat4SamplePredicateFence']['sampleCount']),
    'planner stat4 expression partial current source next207 sample rowids' => static fn (TestRunner $t) => $t->same([10, 40, 20, 50, 30, 60], $plan207()['stat4SamplePredicateFence']['sampleRowids']),
    'planner stat4 expression partial current source next207 sample expression keys' => static fn (TestRunner $t) => $t->same(['plugin_alpha', 'plugin_cache', 'plugin_forms', 'plugin_mail', 'plugin_seo', 'plugin_zulu'], $plan207()['stat4SamplePredicateFence']['sampleExpressionKeys']),
    'planner stat4 expression partial current source next207 all samples satisfy' => static fn (TestRunner $t) => $t->same(true, $plan207()['stat4SamplePredicateFence']['allSamplesSatisfyCurrentPartialPredicate']),
    'planner stat4 expression partial current source next207 rejected none' => static fn (TestRunner $t) => $t->same([], $plan207()['stat4SamplePredicateFence']['sampleRowidsRejectedByCurrentPartialPredicate']),
    'planner stat4 expression partial current source next207 missing none' => static fn (TestRunner $t) => $t->same([], $plan207()['stat4SamplePredicateFence']['missingSampleRowids']),
    'planner stat4 expression partial current source next207 selected rejected none' => static fn (TestRunner $t) => $t->same([], $plan207()['selectedPlan']['next207RejectedSampleRowids']),
    'planner stat4 expression partial current source next207 selected missing none' => static fn (TestRunner $t) => $t->same([], $plan207()['selectedPlan']['next207MissingSampleRowids']),
    'planner stat4 expression partial current source next207 row proof count' => static fn (TestRunner $t) => $t->same(6, count($plan207()['stat4SamplePredicateFence']['sampleProofs'])),
    'planner stat4 expression partial current source next207 proof ordinals' => static fn (TestRunner $t) => $t->same([0, 1, 2, 3, 4, 5], array_column($plan207()['stat4SamplePredicateFence']['sampleProofs'], 'ordinal')),
    'planner stat4 expression partial current source next207 rows found' => static fn (TestRunner $t) => $t->same([true, true, true, true, true, true], array_column($plan207()['stat4SamplePredicateFence']['sampleProofs'], 'rowFound')),
    'planner stat4 expression partial current source next207 sample flags true' => static fn (TestRunner $t) => $t->same([true, true, true, true, true, true], array_column($plan207()['stat4SamplePredicateFence']['sampleProofs'], 'satisfiesCurrentPartialPredicate')),
    'planner stat4 expression partial current source next207 first sample term flags' => static fn (TestRunner $t) => $t->same([true, true, true, true], array_column($plan207()['stat4SamplePredicateFence']['sampleProofs'][0]['termResults'], 'satisfied')),
    'planner stat4 expression partial current source next207 partial term keys' => static fn (TestRunner $t) => $t->same(['expression:lower(option_name)', 'expression:lower(option_name)', 'column:autoload', 'column:option_name'], array_column($plan207()['stat4SamplePredicateFence']['currentPartialPredicateTerms'], 'leftKey')),
    'planner stat4 expression partial current source next207 matched rowids preserved' => static fn (TestRunner $t) => $t->same([30, 50, 20, 21, 22], $plan207()['matchedRowids']),
    'planner stat4 expression partial current source next207 or fence preserved' => static fn (TestRunner $t) => $t->same(true, $plan207()['partialOrPredicateFence']['currentPartialOrPredicateImplied']),
    'planner stat4 expression partial current source next207 covering scan preserved' => static fn (TestRunner $t) => $t->same(true, $plan207()['selectedPlan']['covering']),
    'planner stat4 expression partial current source next207 cursor appended' => static fn (TestRunner $t) => $t->same('RecheckCurrentStat4SamplesAgainstPartialPredicate', $plan207()['cursorProgram'][array_key_last($plan207()['cursorProgram'])]['opcode']),
    'planner stat4 expression partial current source next207 cursor mode' => static fn (TestRunner $t) => $t->same('next207-current-source-stat4-expression-partial-samples', $plan207()['cursorProgram'][array_key_last($plan207()['cursorProgram'])]['mode']),
    'planner stat4 expression partial current source next207 cursor rowids' => static fn (TestRunner $t) => $t->same([10, 40, 20, 50, 30, 60], $plan207()['cursorProgram'][array_key_last($plan207()['cursorProgram'])]['sampleRowids']),
    'planner stat4 expression partial current source next207 sample signature length' => static fn (TestRunner $t) => $t->same(64, strlen($plan207()['stat4SamplePredicateFence']['sampleSignature'])),
    'planner stat4 expression partial current source next207 proof signature length' => static fn (TestRunner $t) => $t->same(64, strlen($plan207()['stat4SamplePredicateFence']['proofSignature'])),
    'planner stat4 expression partial current source next207 selected signature' => static fn (TestRunner $t) => $t->same($plan207()['stat4SamplePredicateFence']['sampleSignature'], $plan207()['selectedPlan']['next207Stat4SampleSignature']),
    'planner stat4 expression partial current source next207 stat4 signature' => static fn (TestRunner $t) => $t->same($plan207()['stat4SamplePredicateFence']['sampleSignature'], $plan207()['stat4Fence']['next207Stat4SampleSignature']),
    'planner stat4 expression partial current source next207 stat4 proof signature' => static fn (TestRunner $t) => $t->same($plan207()['stat4SamplePredicateFence']['proofSignature'], $plan207()['stat4Fence']['next207Stat4SampleProofSignature']),
    'planner stat4 expression partial current source next207 dependency marker' => static fn (TestRunner $t) => $t->true(in_array('sqlite-sqlplanner-stat4-expression-partial-current-source-next207', $plan207()['dependencies'], true)),
    'planner stat4 expression partial current source next207 dependency closure' => static fn (TestRunner $t) => $t->contains('no new support component needed', $plan207()['dependency_closure']),
    'planner stat4 expression partial current source next207 non overlap' => static fn (TestRunner $t) => $t->contains('current sqlite_stat4 samples', $plan207()['non_overlap']),
    'planner stat4 expression partial current source next207 detail' => static fn (TestRunner $t) => $t->contains('NEXT207 STAT4 SAMPLE PARTIAL PREDICATE FENCE', $plan207()['detail']),
    'planner stat4 expression partial current source next207 stale autoload blocked' => static fn (TestRunner $t) => $t->same('requires-current-source-stat4-sample-reprepare', $staleAutoloadSample207()['status']),
    'planner stat4 expression partial current source next207 stale autoload rejected rowid' => static fn (TestRunner $t) => $t->same([40], $staleAutoloadSample207()['stat4SamplePredicateFence']['sampleRowidsRejectedByCurrentPartialPredicate']),
    'planner stat4 expression partial current source next207 stale autoload selected rejected' => static fn (TestRunner $t) => $t->same([40], $staleAutoloadSample207()['selectedPlan']['next207RejectedSampleRowids']),
    'planner stat4 expression partial current source next207 stale autoload no cursor append' => static fn (TestRunner $t) => $t->same(false, in_array('RecheckCurrentStat4SamplesAgainstPartialPredicate', array_column($staleAutoloadSample207()['cursorProgram'], 'opcode'), true)),
    'planner stat4 expression partial current source next207 stale range blocked' => static fn (TestRunner $t) => $t->same('requires-current-source-stat4-sample-reprepare', $staleRangeSample207()['status']),
    'planner stat4 expression partial current source next207 stale range rejected rowid' => static fn (TestRunner $t) => $t->same([80], $staleRangeSample207()['stat4SamplePredicateFence']['sampleRowidsRejectedByCurrentPartialPredicate']),
    'planner stat4 expression partial current source next207 missing sample blocked' => static fn (TestRunner $t) => $t->same('requires-current-source-stat4-sample-reprepare', $missingSample207()['status']),
    'planner stat4 expression partial current source next207 missing sample rowid' => static fn (TestRunner $t) => $t->same([999], $missingSample207()['stat4SamplePredicateFence']['missingSampleRowids']),
    'planner stat4 expression partial current source next207 missing sample found flag' => static fn (TestRunner $t) => $t->same(false, $missingSample207()['stat4SamplePredicateFence']['sampleProofs'][6]['rowFound']),
    'planner stat4 expression partial current source next207 invalid samples type' => static function (TestRunner $t) use ($current207, $plan207): void {
        $bad = $current207();
        $bad['indexes'][0]['stat4Samples'] = 'bad';
        $t->throws(InvalidArgumentException::class, static fn () => $plan207(5, 1, null, $bad));
    },
    'planner stat4 expression partial current source next207 invalid sample entry' => static function (TestRunner $t) use ($current207, $plan207): void {
        $bad = $current207();
        $bad['indexes'][0]['stat4Samples'][] = 'bad';
        $t->throws(InvalidArgumentException::class, static fn () => $plan207(5, 1, null, $bad));
    },
    'planner stat4 expression partial current source next207 invalid sample vector' => static function (TestRunner $t) use ($current207, $plan207): void {
        $bad = $current207();
        $bad['indexes'][0]['stat4Samples'][0]['sample'] = ['plugin_alpha'];
        $t->throws(InvalidArgumentException::class, static fn () => $plan207(5, 1, null, $bad));
    },
    'planner stat4 expression partial current source next207 invalid rowid' => static function (TestRunner $t) use ($current207, $plan207): void {
        $bad = $current207();
        $bad['indexes'][0]['stat4Samples'][0]['sample'][1] = 'x';
        $t->throws(InvalidArgumentException::class, static fn () => $plan207(5, 1, null, $bad));
    },
    'planner stat4 expression partial current source next207 invalid current rows' => static function (TestRunner $t) use ($current207, $plan207): void {
        $bad = $current207();
        $bad['rows'] = 'bad';
        $t->throws(InvalidArgumentException::class, static fn () => $plan207(5, 1, null, $bad));
    },
];

foreach (range(1, 16) as $case) {
    $tests['planner stat4 expression partial current source next207 repeated sample fence ' . $case] = static function (TestRunner $t) use ($plan207, $case): void {
        $plan = $plan207(1 + ($case % 5), $case % 4);
        $t->same($plan['stat4SamplePredicateFence']['sampleCount'], count($plan['stat4SamplePredicateFence']['sampleProofs']));
    };
}

return $tests;

<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan;

$eq233 = static fn (string $column, mixed $right): array => ['left' => ['column' => $column], 'operator' => '=', 'right' => $right];
$like233 = static fn (string $column, string $right): array => ['left' => ['column' => $column], 'operator' => 'LIKE', 'right' => $right];
$notNull233 = static fn (string $column): array => ['left' => ['column' => $column], 'operator' => 'IS NOT NULL'];
$between233 = static fn (string $expression, mixed $lower, mixed $upper): array => ['left' => ['expression' => $expression], 'operator' => 'BETWEEN', 'lower' => $lower, 'upper' => $upper];

$prepared233 = static fn (): array => [
    'name' => 'prepared-wp-options-sample-row-stat4-expression-next233',
    'schemaCookie' => 2330,
    'stat4Generation' => 233,
    'rows' => [
        ['rowid' => 20, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_forms', 'option_value' => 'forms-old', 'updated_at' => 20],
        ['rowid' => 30, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_seo', 'option_value' => 'seo-old', 'updated_at' => 30],
        ['rowid' => 60, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_zulu', 'option_value' => 'zulu-old', 'updated_at' => 60],
    ],
    'indexes' => [[
        'name' => 'idx_wp_options_lower_sample_row_next233',
        'rootPage' => 23301,
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

$current233 = static function (array $overrides = []) use ($prepared233): array {
    $source = $prepared233();
    $source['name'] = 'current-wp-options-sample-row-stat4-expression-next233';
    $source['schemaCookie'] = 2338;
    $source['stat4Generation'] = 338;
    $source['indexes'][0]['rootPage'] = 23388;
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

$terms233 = static fn (): array => [
    $between233('LOWER(option_name)', 'plugin_forms', 'plugin_zulu'),
    $eq233('autoload', 'yes'),
    $notNull233('option_name'),
    $eq233('blog_id', 1),
    $like233('option_name', 'plugin_%'),
];

$plan233 = static fn (int $limit = 6, int $offset = 0, ?array $prepared = null, ?array $current = null, ?array $terms = null, ?array $needed = null): array => SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan::materializeCurrentSourceSampleRowGuardFence(
    $prepared ?? $prepared233(),
    $current ?? $current233(),
    $terms ?? $terms233(),
    $needed ?? ['option_name', 'option_value', 'updated_at', 'blog_id'],
    $limit,
    $offset,
);

$missingSampleRow233 = static function () use ($current233, $plan233): array {
    $current = $current233();
    $current['rows'] = array_values(array_filter($current['rows'], static fn (array $row): bool => $row['rowid'] !== 50));

    return $plan233(6, 0, null, $current);
};
$sampleKeyMoved233 = static function () use ($current233, $plan233): array {
    $current = $current233();
    foreach ($current['rows'] as &$row) {
        if ($row['rowid'] === 30) {
            $row['option_name'] = 'plugin_search';
            break;
        }
    }
    unset($row);

    return $plan233(6, 0, null, $current);
};
$partialPredicateMoved233 = static function () use ($current233, $plan233): array {
    $current = $current233();
    foreach ($current['rows'] as &$row) {
        if ($row['rowid'] === 60) {
            $row['autoload'] = 'no';
            break;
        }
    }
    unset($row);

    return $plan233(6, 0, null, $current);
};

$tests = [
    'planner stat4 expression partial current source next233 status ready' => static fn (TestRunner $t) => $t->same('stat4-expression-partial-current-source-next233-ready', $plan233()['status']),
    'planner stat4 expression partial current source next233 selected current' => static fn (TestRunner $t) => $t->same('current', $plan233()['selectedSource']),
    'planner stat4 expression partial current source next233 inherits next230' => static fn (TestRunner $t) => $t->same(true, $plan233()['selectedPlan']['next230Ready']),
    'planner stat4 expression partial current source next233 ready flag' => static fn (TestRunner $t) => $t->same(true, $plan233()['selectedPlan']['next233Ready']),
    'planner stat4 expression partial current source next233 selected index' => static fn (TestRunner $t) => $t->same('idx_wp_options_lower_sample_row_next233', $plan233()['selectedPlan']['name']),
    'planner stat4 expression partial current source next233 root page' => static fn (TestRunner $t) => $t->same(23388, $plan233()['selectedPlan']['rootPage']),
    'planner stat4 expression partial current source next233 base matched rowids' => static fn (TestRunner $t) => $t->same([60, 30, 50, 20, 21, 22], $plan233()['matchedRowids']),
    'planner stat4 expression partial current source next233 gap rowids preserved' => static fn (TestRunner $t) => $t->same([21, 22], $plan233()['stat4GapDensityFence']['gapRowids']),
    'planner stat4 expression partial current source next233 sample row count' => static fn (TestRunner $t) => $t->same(4, $plan233()['stat4SampleRowGuard']['sampleRowCount']),
    'planner stat4 expression partial current source next233 validated count' => static fn (TestRunner $t) => $t->same(4, $plan233()['stat4SampleRowGuard']['validatedSampleRowCount']),
    'planner stat4 expression partial current source next233 rejected count' => static fn (TestRunner $t) => $t->same(0, $plan233()['stat4SampleRowGuard']['rejectedSampleRowCount']),
    'planner stat4 expression partial current source next233 validated rowids' => static fn (TestRunner $t) => $t->same([20, 50, 30, 60], $plan233()['stat4SampleRowGuard']['validatedSampleRowids']),
    'planner stat4 expression partial current source next233 rejected rowids' => static fn (TestRunner $t) => $t->same([], $plan233()['stat4SampleRowGuard']['rejectedSampleRowids']),
    'planner stat4 expression partial current source next233 sample keys' => static fn (TestRunner $t) => $t->same(['plugin_forms', 'plugin_mail', 'plugin_seo', 'plugin_zulu'], $plan233()['stat4SampleRowGuard']['sampleKeys']),
    'planner stat4 expression partial current source next233 first sample row exists' => static fn (TestRunner $t) => $t->same(true, $plan233()['stat4SampleRowGuard']['sampleRows'][0]['rowExists']),
    'planner stat4 expression partial current source next233 first sample predicate' => static fn (TestRunner $t) => $t->same(true, $plan233()['stat4SampleRowGuard']['sampleRows'][0]['partialPredicateSatisfied']),
    'planner stat4 expression partial current source next233 first sample key match' => static fn (TestRunner $t) => $t->same(true, $plan233()['stat4SampleRowGuard']['sampleRows'][0]['sampleKeyMatchesCurrentRow']),
    'planner stat4 expression partial current source next233 third sample rowid' => static fn (TestRunner $t) => $t->same(30, $plan233()['stat4SampleRowGuard']['sampleRows'][2]['rowid']),
    'planner stat4 expression partial current source next233 third sample key' => static fn (TestRunner $t) => $t->same('plugin_seo', $plan233()['stat4SampleRowGuard']['sampleRows'][2]['sampleKey']),
    'planner stat4 expression partial current source next233 third current key' => static fn (TestRunner $t) => $t->same('plugin_seo', $plan233()['stat4SampleRowGuard']['sampleRows'][2]['currentRowKey']),
    'planner stat4 expression partial current source next233 selected rowids' => static fn (TestRunner $t) => $t->same([20, 50, 30, 60], $plan233()['selectedPlan']['next233ValidatedSampleRowids']),
    'planner stat4 expression partial current source next233 selected rejected' => static fn (TestRunner $t) => $t->same([], $plan233()['selectedPlan']['next233RejectedSampleRowids']),
    'planner stat4 expression partial current source next233 signature length' => static fn (TestRunner $t) => $t->same(64, strlen($plan233()['stat4SampleRowGuard']['sampleKeySignature'])),
    'planner stat4 expression partial current source next233 proof length' => static fn (TestRunner $t) => $t->same(64, strlen($plan233()['stat4SampleRowGuard']['proofSignature'])),
    'planner stat4 expression partial current source next233 selected signature' => static fn (TestRunner $t) => $t->same($plan233()['stat4SampleRowGuard']['sampleKeySignature'], $plan233()['selectedPlan']['next233SampleKeySignature']),
    'planner stat4 expression partial current source next233 selected proof signature' => static fn (TestRunner $t) => $t->same($plan233()['stat4SampleRowGuard']['proofSignature'], $plan233()['selectedPlan']['next233ProofSignature']),
    'planner stat4 expression partial current source next233 stat4 signature' => static fn (TestRunner $t) => $t->same($plan233()['stat4SampleRowGuard']['sampleKeySignature'], $plan233()['stat4Fence']['next233SampleKeySignature']),
    'planner stat4 expression partial current source next233 stat4 proof signature' => static fn (TestRunner $t) => $t->same($plan233()['stat4SampleRowGuard']['proofSignature'], $plan233()['stat4Fence']['next233ProofSignature']),
    'planner stat4 expression partial current source next233 stat4 ready' => static fn (TestRunner $t) => $t->same(true, $plan233()['stat4Fence']['next233SampleRowReady']),
    'planner stat4 expression partial current source next233 cursor appended' => static fn (TestRunner $t) => $t->same('ValidateCurrentSourceStat4SampleRows', $plan233()['cursorProgram'][array_key_last($plan233()['cursorProgram'])]['opcode']),
    'planner stat4 expression partial current source next233 cursor mode' => static fn (TestRunner $t) => $t->same('next233-current-source-stat4-expression-partial-sample-row-guard', $plan233()['cursorProgram'][array_key_last($plan233()['cursorProgram'])]['mode']),
    'planner stat4 expression partial current source next233 cursor rowids' => static fn (TestRunner $t) => $t->same([20, 50, 30, 60], $plan233()['cursorProgram'][array_key_last($plan233()['cursorProgram'])]['validatedSampleRowids']),
    'planner stat4 expression partial current source next233 cursor keys' => static fn (TestRunner $t) => $t->same(['plugin_forms', 'plugin_mail', 'plugin_seo', 'plugin_zulu'], $plan233()['cursorProgram'][array_key_last($plan233()['cursorProgram'])]['sampleKeys']),
    'planner stat4 expression partial current source next233 cursor signature' => static fn (TestRunner $t) => $t->same($plan233()['stat4SampleRowGuard']['proofSignature'], $plan233()['cursorProgram'][array_key_last($plan233()['cursorProgram'])]['signature']),
    'planner stat4 expression partial current source next233 projected payload preserved' => static fn (TestRunner $t) => $t->same('forms-copy-b', $plan233()['projectedRows'][5]['option_value']),
    'planner stat4 expression partial current source next233 table lookup preserved' => static fn (TestRunner $t) => $t->same(false, $plan233()['tableLookupRequired']),
    'planner stat4 expression partial current source next233 detail' => static fn (TestRunner $t) => $t->contains('NEXT233 SAMPLE ROW GUARD', $plan233()['detail']),
    'planner stat4 expression partial current source next233 dependency marker' => static fn (TestRunner $t) => $t->true(in_array('sqlite-sqlplanner-stat4-expression-partial-current-source-next233', $plan233()['dependencies'], true)),
    'planner stat4 expression partial current source next233 dependency closure' => static fn (TestRunner $t) => $t->contains('no new support component needed', $plan233()['dependency_closure']),
    'planner stat4 expression partial current source next233 non overlap' => static fn (TestRunner $t) => $t->contains('stale sqlite_stat4 sample rowids', $plan233()['non_overlap']),
    'planner stat4 expression partial current source next233 missing sample blocks' => static fn (TestRunner $t) => $t->same('requires-current-source-stat4-sample-row-reprepare', $missingSampleRow233()['status']),
    'planner stat4 expression partial current source next233 missing sample rejected' => static fn (TestRunner $t) => $t->same([50], $missingSampleRow233()['stat4SampleRowGuard']['rejectedSampleRowids']),
    'planner stat4 expression partial current source next233 missing sample row absent' => static fn (TestRunner $t) => $t->same(false, $missingSampleRow233()['stat4SampleRowGuard']['sampleRows'][1]['rowExists']),
    'planner stat4 expression partial current source next233 missing sample ready false' => static fn (TestRunner $t) => $t->same(false, $missingSampleRow233()['selectedPlan']['next233Ready']),
    'planner stat4 expression partial current source next233 moved key blocks' => static fn (TestRunner $t) => $t->same('requires-current-source-stat4-sample-row-reprepare', $sampleKeyMoved233()['status']),
    'planner stat4 expression partial current source next233 moved key rejected' => static fn (TestRunner $t) => $t->same([30], $sampleKeyMoved233()['stat4SampleRowGuard']['rejectedSampleRowids']),
    'planner stat4 expression partial current source next233 moved key current value' => static fn (TestRunner $t) => $t->same('plugin_search', $sampleKeyMoved233()['stat4SampleRowGuard']['sampleRows'][2]['currentRowKey']),
    'planner stat4 expression partial current source next233 moved key mismatch' => static fn (TestRunner $t) => $t->same(false, $sampleKeyMoved233()['stat4SampleRowGuard']['sampleRows'][2]['sampleKeyMatchesCurrentRow']),
    'planner stat4 expression partial current source next233 partial predicate blocks' => static fn (TestRunner $t) => $t->same('requires-current-source-stat4-sample-row-reprepare', $partialPredicateMoved233()['status']),
    'planner stat4 expression partial current source next233 partial predicate rejected' => static fn (TestRunner $t) => $t->same([60], $partialPredicateMoved233()['stat4SampleRowGuard']['rejectedSampleRowids']),
    'planner stat4 expression partial current source next233 partial predicate false' => static fn (TestRunner $t) => $t->same(false, $partialPredicateMoved233()['stat4SampleRowGuard']['sampleRows'][3]['partialPredicateSatisfied']),
    'planner stat4 expression partial current source next233 invalid limit' => static function (TestRunner $t) use ($plan233): void {
        $t->throws(InvalidArgumentException::class, static fn () => $plan233(-1, 0));
    },
    'planner stat4 expression partial current source next233 invalid offset' => static function (TestRunner $t) use ($plan233): void {
        $t->throws(InvalidArgumentException::class, static fn () => $plan233(1, -1));
    },
    'planner stat4 expression partial current source next233 missing rows' => static function (TestRunner $t) use ($current233, $plan233): void {
        $current = $current233();
        unset($current['rows']);
        $t->throws(InvalidArgumentException::class, static fn () => $plan233(6, 0, null, $current));
    },
    'planner stat4 expression partial current source next233 malformed sample' => static function (TestRunner $t) use ($current233, $plan233): void {
        $current = $current233();
        unset($current['indexes'][0]['stat4Samples'][1]['sample'][1]);
        $t->throws(InvalidArgumentException::class, static fn () => $plan233(6, 0, null, $current));
    },
];

foreach (range(1, 14) as $case) {
    $tests['planner stat4 expression partial current source next233 repeated proof signature ' . $case] = static function (TestRunner $t) use ($plan233, $case): void {
        $plan = $plan233(5 + ($case % 2), 0);
        $t->same($plan['stat4SampleRowGuard']['proofSignature'], $plan['selectedPlan']['next233ProofSignature']);
    };
}

return $tests;

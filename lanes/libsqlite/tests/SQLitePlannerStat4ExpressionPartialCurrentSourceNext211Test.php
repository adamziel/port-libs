<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan;

$eq211 = static fn (string $column, mixed $right): array => ['left' => ['column' => $column], 'operator' => '=', 'right' => $right];
$notNull211 = static fn (string $column): array => ['left' => ['column' => $column], 'operator' => 'IS NOT NULL'];
$between211 = static fn (string $expression, mixed $lower, mixed $upper): array => ['left' => ['expression' => $expression], 'operator' => 'BETWEEN', 'lower' => $lower, 'upper' => $upper];

$prepared211 = static fn (): array => [
    'name' => 'prepared-wp-options-stat4-seek-window-next211',
    'schemaCookie' => 2110,
    'stat4Generation' => 211,
    'rows' => [
        ['rowid' => 10, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_alpha', 'option_value' => 'alpha-old', 'updated_at' => 10],
        ['rowid' => 20, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_forms', 'option_value' => 'forms-old', 'updated_at' => 20],
        ['rowid' => 30, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_seo', 'option_value' => 'seo-old', 'updated_at' => 30],
    ],
    'indexes' => [[
        'name' => 'idx_wp_options_lower_stat4_seek_window_next211',
        'rootPage' => 21101,
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
        'partialGroupedOrPredicateArms' => [
            [
                ['left' => ['column' => 'blog_id'], 'operator' => '=', 'right' => 1],
                ['left' => ['column' => 'autoload'], 'operator' => '=', 'right' => 'yes'],
            ],
            [
                ['left' => ['column' => 'autoload'], 'operator' => '=', 'right' => 'critical'],
            ],
        ],
        'coveringColumns' => ['option_name', 'option_value', 'updated_at', 'autoload', 'blog_id'],
        'stat4Samples' => [
            ['neq' => '1 1', 'nlt' => '0 0', 'ndlt' => '0 0', 'sample' => ['plugin_alpha', 10]],
            ['neq' => '1 1', 'nlt' => '1 1', 'ndlt' => '1 1', 'sample' => ['plugin_forms', 20]],
            ['neq' => '1 1', 'nlt' => '2 2', 'ndlt' => '2 2', 'sample' => ['plugin_seo', 30]],
        ],
    ]],
];

$current211 = static function () use ($prepared211): array {
    $source = $prepared211();
    $source['name'] = 'current-wp-options-stat4-seek-window-next211';
    $source['schemaCookie'] = 2118;
    $source['stat4Generation'] = 266;
    $source['indexes'][0]['rootPage'] = 21188;
    $source['indexes'][0]['stat4Samples'] = [
        ['neq' => '1 1', 'nlt' => '0 0', 'ndlt' => '0 0', 'sample' => ['plugin_alpha', 10]],
        ['neq' => '1 1', 'nlt' => '1 1', 'ndlt' => '1 1', 'sample' => ['plugin_cache', 40]],
        ['neq' => '3 1', 'nlt' => '2 2', 'ndlt' => '2 2', 'sample' => ['plugin_forms', 20]],
        ['neq' => '1 1', 'nlt' => '5 3', 'ndlt' => '3 3', 'sample' => ['plugin_mail', 50]],
        ['neq' => '1 1', 'nlt' => '6 4', 'ndlt' => '4 4', 'sample' => ['plugin_seo', 30]],
        ['neq' => '1 1', 'nlt' => '7 5', 'ndlt' => '5 5', 'sample' => ['plugin_zulu', 60]],
    ];
    $source['indexes'][0]['stat4SeekWindows'] = [
        [
            'name' => 'plugin-seo-desc-window',
            'lowerSample' => ['rowid' => 60, 'key' => 'plugin_zulu'],
            'upperSample' => ['rowid' => 30, 'key' => 'plugin_seo'],
            'rowids' => [60, 30],
        ],
        [
            'name' => 'plugin-forms-desc-window',
            'lowerSample' => ['rowid' => 50, 'key' => 'plugin_mail'],
            'upperSample' => ['rowid' => 20, 'key' => 'plugin_forms'],
            'rowids' => [50, 20, 21, 22],
        ],
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
        ['rowid' => 80, 'blog_id' => 2, 'autoload' => 'yes', 'option_name' => 'plugin_forms', 'option_value' => 'other-blog', 'updated_at' => 80],
    ];

    return $source;
};

$terms211 = static fn (): array => [
    $between211('LOWER(option_name)', 'plugin_alpha', 'plugin_zulu'),
    $eq211('autoload', 'yes'),
    $notNull211('option_name'),
    $eq211('blog_id', 1),
];
$plan211 = static fn (int $limit = 5, int $offset = 1, ?array $prepared = null, ?array $current = null, ?array $terms = null, ?array $needed = null): array => SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan::materializeNext211(
    $prepared ?? $prepared211(),
    $current ?? $current211(),
    $terms ?? $terms211(),
    $needed ?? ['option_name', 'option_value', 'updated_at', 'blog_id'],
    $limit,
    $offset,
);
$staleSample211 = static function () use ($current211, $plan211): array {
    $current = $current211();
    $current['rows'][2]['option_name'] = 'plugin_mail_archived';

    return $plan211(5, 1, null, $current);
};
$missingWindow211 = static function () use ($current211, $plan211): array {
    $current = $current211();
    $current['indexes'][0]['stat4SeekWindows'][1]['rowids'][] = 99;

    return $plan211(5, 1, null, $current);
};
$outsideWindow211 = static function () use ($current211, $plan211): array {
    $current = $current211();
    $current['indexes'][0]['stat4SeekWindows'][1]['rowids'] = [50, 20, 21];

    return $plan211(5, 1, null, $current);
};

$tests = [
    'planner stat4 expression partial current source next211 status ready' => static fn (TestRunner $t) => $t->same('stat4-expression-partial-current-source-next211-ready', $plan211()['status']),
    'planner stat4 expression partial current source next211 selected current' => static fn (TestRunner $t) => $t->same('current', $plan211()['selectedSource']),
    'planner stat4 expression partial current source next211 inherited next209' => static fn (TestRunner $t) => $t->same(true, $plan211()['selectedPlan']['next209Ready']),
    'planner stat4 expression partial current source next211 ready flag' => static fn (TestRunner $t) => $t->same(true, $plan211()['selectedPlan']['next211Ready']),
    'planner stat4 expression partial current source next211 selected index' => static fn (TestRunner $t) => $t->same('idx_wp_options_lower_stat4_seek_window_next211', $plan211()['selectedPlan']['name']),
    'planner stat4 expression partial current source next211 root page' => static fn (TestRunner $t) => $t->same(21188, $plan211()['selectedPlan']['rootPage']),
    'planner stat4 expression partial current source next211 seek window count' => static fn (TestRunner $t) => $t->same(2, count($plan211()['stat4SeekWindowFence']['currentStat4SeekWindows'])),
    'planner stat4 expression partial current source next211 first window name' => static fn (TestRunner $t) => $t->same('plugin-seo-desc-window', $plan211()['stat4SeekWindowFence']['currentStat4SeekWindows'][0]['name']),
    'planner stat4 expression partial current source next211 second window name' => static fn (TestRunner $t) => $t->same('plugin-forms-desc-window', $plan211()['stat4SeekWindowFence']['currentStat4SeekWindows'][1]['name']),
    'planner stat4 expression partial current source next211 lower sample normalized' => static fn (TestRunner $t) => $t->same(['rowid' => 60, 'key' => 'plugin_zulu'], $plan211()['stat4SeekWindowFence']['currentStat4SeekWindows'][0]['lowerSample']),
    'planner stat4 expression partial current source next211 upper sample normalized' => static fn (TestRunner $t) => $t->same(['rowid' => 20, 'key' => 'plugin_forms'], $plan211()['stat4SeekWindowFence']['currentStat4SeekWindows'][1]['upperSample']),
    'planner stat4 expression partial current source next211 matched rowids' => static fn (TestRunner $t) => $t->same([30, 50, 20, 21, 22], $plan211()['stat4SeekWindowFence']['matchedRowids']),
    'planner stat4 expression partial current source next211 base matched rowids preserved' => static fn (TestRunner $t) => $t->same([30, 50, 20, 21, 22], $plan211()['matchedRowids']),
    'planner stat4 expression partial current source next211 matched keys preserved' => static fn (TestRunner $t) => $t->same(['plugin_seo', 'plugin_mail', 'plugin_forms', 'plugin_forms', 'plugin_forms'], $plan211()['matchedExpressionKeys']),
    'planner stat4 expression partial current source next211 projected payload preserved' => static fn (TestRunner $t) => $t->same('forms-copy-b', $plan211()['projectedRows'][4]['option_value']),
    'planner stat4 expression partial current source next211 grouped or preserved' => static fn (TestRunner $t) => $t->same(true, $plan211()['groupedPartialOrPredicateFence']['currentGroupedPartialOrPredicateImplied']),
    'planner stat4 expression partial current source next211 grouped arm preserved' => static fn (TestRunner $t) => $t->same(0, $plan211()['groupedPartialOrPredicateFence']['matchedGroupedOrArm']),
    'planner stat4 expression partial current source next211 sample resolve true' => static fn (TestRunner $t) => $t->same(true, $plan211()['stat4SeekWindowFence']['allStat4SeekSamplesResolveToCurrentSource']),
    'planner stat4 expression partial current source next211 rowids resolve true' => static fn (TestRunner $t) => $t->same(true, $plan211()['stat4SeekWindowFence']['allWindowRowidsResolveToCurrentSource']),
    'planner stat4 expression partial current source next211 selected rows inside windows' => static fn (TestRunner $t) => $t->same(true, $plan211()['stat4SeekWindowFence']['allSelectedRowsRemainInsideCurrentStat4Windows']),
    'planner stat4 expression partial current source next211 stale samples none' => static fn (TestRunner $t) => $t->same([], $plan211()['stat4SeekWindowFence']['staleStat4SampleRowids']),
    'planner stat4 expression partial current source next211 missing rows none' => static fn (TestRunner $t) => $t->same([], $plan211()['stat4SeekWindowFence']['missingCurrentWindowRowids']),
    'planner stat4 expression partial current source next211 outside rows none' => static fn (TestRunner $t) => $t->same([], $plan211()['stat4SeekWindowFence']['matchedRowidsOutsideCurrentStat4Windows']),
    'planner stat4 expression partial current source next211 first proof lower current key' => static fn (TestRunner $t) => $t->same('plugin_zulu', $plan211()['stat4SeekWindowFence']['windowProofs'][0]['lowerSampleProof']['currentKey']),
    'planner stat4 expression partial current source next211 first proof upper current key' => static fn (TestRunner $t) => $t->same('plugin_seo', $plan211()['stat4SeekWindowFence']['windowProofs'][0]['upperSampleProof']['currentKey']),
    'planner stat4 expression partial current source next211 second proof lower current key' => static fn (TestRunner $t) => $t->same('plugin_mail', $plan211()['stat4SeekWindowFence']['windowProofs'][1]['lowerSampleProof']['currentKey']),
    'planner stat4 expression partial current source next211 second proof upper current key' => static fn (TestRunner $t) => $t->same('plugin_forms', $plan211()['stat4SeekWindowFence']['windowProofs'][1]['upperSampleProof']['currentKey']),
    'planner stat4 expression partial current source next211 proof lower present' => static fn (TestRunner $t) => $t->same(true, $plan211()['stat4SeekWindowFence']['windowProofs'][0]['lowerSampleProof']['currentRowPresent']),
    'planner stat4 expression partial current source next211 proof upper match' => static fn (TestRunner $t) => $t->same(true, $plan211()['stat4SeekWindowFence']['windowProofs'][1]['upperSampleProof']['currentKeyMatchesSample']),
    'planner stat4 expression partial current source next211 first window covers selected' => static fn (TestRunner $t) => $t->same([30], $plan211()['stat4SeekWindowFence']['windowProofs'][0]['coversMatchedRowids']),
    'planner stat4 expression partial current source next211 second window covers selected' => static fn (TestRunner $t) => $t->same([50, 20, 21, 22], $plan211()['stat4SeekWindowFence']['windowProofs'][1]['coversMatchedRowids']),
    'planner stat4 expression partial current source next211 first window rowids' => static fn (TestRunner $t) => $t->same([60, 30], $plan211()['stat4SeekWindowFence']['windowProofs'][0]['rowids']),
    'planner stat4 expression partial current source next211 second window rowids' => static fn (TestRunner $t) => $t->same([50, 20, 21, 22], $plan211()['stat4SeekWindowFence']['windowProofs'][1]['rowids']),
    'planner stat4 expression partial current source next211 no missing first window' => static fn (TestRunner $t) => $t->same([], $plan211()['stat4SeekWindowFence']['windowProofs'][0]['missingCurrentRowids']),
    'planner stat4 expression partial current source next211 no missing second window' => static fn (TestRunner $t) => $t->same([], $plan211()['stat4SeekWindowFence']['windowProofs'][1]['missingCurrentRowids']),
    'planner stat4 expression partial current source next211 window signature length' => static fn (TestRunner $t) => $t->same(64, strlen($plan211()['stat4SeekWindowFence']['currentStat4SeekWindowSignature'])),
    'planner stat4 expression partial current source next211 proof signature length' => static fn (TestRunner $t) => $t->same(64, strlen($plan211()['stat4SeekWindowFence']['proofSignature'])),
    'planner stat4 expression partial current source next211 selected window signature' => static fn (TestRunner $t) => $t->same($plan211()['stat4SeekWindowFence']['currentStat4SeekWindowSignature'], $plan211()['selectedPlan']['next211CurrentStat4SeekWindowSignature']),
    'planner stat4 expression partial current source next211 selected proof signature' => static fn (TestRunner $t) => $t->same($plan211()['stat4SeekWindowFence']['proofSignature'], $plan211()['selectedPlan']['next211CurrentStat4SeekProofSignature']),
    'planner stat4 expression partial current source next211 stat4 window signature' => static fn (TestRunner $t) => $t->same($plan211()['stat4SeekWindowFence']['currentStat4SeekWindowSignature'], $plan211()['stat4Fence']['next211CurrentStat4SeekWindowSignature']),
    'planner stat4 expression partial current source next211 stat4 proof signature' => static fn (TestRunner $t) => $t->same($plan211()['stat4SeekWindowFence']['proofSignature'], $plan211()['stat4Fence']['next211CurrentStat4SeekProofSignature']),
    'planner stat4 expression partial current source next211 selected stale samples none' => static fn (TestRunner $t) => $t->same([], $plan211()['selectedPlan']['next211StaleStat4SampleRowids']),
    'planner stat4 expression partial current source next211 selected missing rows none' => static fn (TestRunner $t) => $t->same([], $plan211()['selectedPlan']['next211MissingCurrentWindowRowids']),
    'planner stat4 expression partial current source next211 cursor appended' => static fn (TestRunner $t) => $t->same('RecheckCurrentStat4SeekWindow', $plan211()['cursorProgram'][array_key_last($plan211()['cursorProgram'])]['opcode']),
    'planner stat4 expression partial current source next211 cursor mode' => static fn (TestRunner $t) => $t->same('next211-current-source-stat4-expression-partial-seek-window', $plan211()['cursorProgram'][array_key_last($plan211()['cursorProgram'])]['mode']),
    'planner stat4 expression partial current source next211 cursor rowids' => static fn (TestRunner $t) => $t->same([30, 50, 20, 21, 22], $plan211()['cursorProgram'][array_key_last($plan211()['cursorProgram'])]['rowids']),
    'planner stat4 expression partial current source next211 cursor signature' => static fn (TestRunner $t) => $t->same($plan211()['stat4SeekWindowFence']['currentStat4SeekWindowSignature'], $plan211()['cursorProgram'][array_key_last($plan211()['cursorProgram'])]['windowSignature']),
    'planner stat4 expression partial current source next211 dependency marker' => static fn (TestRunner $t) => $t->true(in_array('sqlite-sqlplanner-stat4-expression-partial-current-source-next211', $plan211()['dependencies'], true)),
    'planner stat4 expression partial current source next211 dependency closure' => static fn (TestRunner $t) => $t->contains('no new support component needed', $plan211()['dependency_closure']),
    'planner stat4 expression partial current source next211 non overlap' => static fn (TestRunner $t) => $t->contains('STAT4 seek-window probe samples', $plan211()['non_overlap']),
    'planner stat4 expression partial current source next211 detail' => static fn (TestRunner $t) => $t->contains('NEXT211 SEEK WINDOW FENCE', $plan211()['detail']),
    'planner stat4 expression partial current source next211 stale sample blocked' => static fn (TestRunner $t) => $t->same('requires-current-source-stat4-seek-window-reprepare', $staleSample211()['status']),
    'planner stat4 expression partial current source next211 stale sample rowid' => static fn (TestRunner $t) => $t->same([50], $staleSample211()['stat4SeekWindowFence']['staleStat4SampleRowids']),
    'planner stat4 expression partial current source next211 stale sample no cursor append' => static fn (TestRunner $t) => $t->same(false, in_array('RecheckCurrentStat4SeekWindow', array_column($staleSample211()['cursorProgram'], 'opcode'), true)),
    'planner stat4 expression partial current source next211 stale sample resolves false' => static fn (TestRunner $t) => $t->same(false, $staleSample211()['stat4SeekWindowFence']['allStat4SeekSamplesResolveToCurrentSource']),
    'planner stat4 expression partial current source next211 missing window blocked' => static fn (TestRunner $t) => $t->same('requires-current-source-stat4-seek-window-reprepare', $missingWindow211()['status']),
    'planner stat4 expression partial current source next211 missing window rowid' => static fn (TestRunner $t) => $t->same([99], $missingWindow211()['stat4SeekWindowFence']['missingCurrentWindowRowids']),
    'planner stat4 expression partial current source next211 missing window rows false' => static fn (TestRunner $t) => $t->same(false, $missingWindow211()['stat4SeekWindowFence']['allWindowRowidsResolveToCurrentSource']),
    'planner stat4 expression partial current source next211 outside window blocked' => static fn (TestRunner $t) => $t->same('requires-current-source-stat4-seek-window-reprepare', $outsideWindow211()['status']),
    'planner stat4 expression partial current source next211 outside window rowid' => static fn (TestRunner $t) => $t->same([22], $outsideWindow211()['stat4SeekWindowFence']['matchedRowidsOutsideCurrentStat4Windows']),
    'planner stat4 expression partial current source next211 outside window false' => static fn (TestRunner $t) => $t->same(false, $outsideWindow211()['stat4SeekWindowFence']['allSelectedRowsRemainInsideCurrentStat4Windows']),
    'planner stat4 expression partial current source next211 invalid current indexes' => static function (TestRunner $t) use ($current211, $plan211): void {
        $bad = $current211();
        $bad['indexes'] = 'bad';
        $t->throws(InvalidArgumentException::class, static fn () => $plan211(5, 1, null, $bad));
    },
    'planner stat4 expression partial current source next211 invalid seek windows' => static function (TestRunner $t) use ($current211, $plan211): void {
        $bad = $current211();
        $bad['indexes'][0]['stat4SeekWindows'] = 'bad';
        $t->throws(InvalidArgumentException::class, static fn () => $plan211(5, 1, null, $bad));
    },
    'planner stat4 expression partial current source next211 invalid seek window entry' => static function (TestRunner $t) use ($current211, $plan211): void {
        $bad = $current211();
        $bad['indexes'][0]['stat4SeekWindows'][] = 'bad';
        $t->throws(InvalidArgumentException::class, static fn () => $plan211(5, 1, null, $bad));
    },
    'planner stat4 expression partial current source next211 invalid sample' => static function (TestRunner $t) use ($current211, $plan211): void {
        $bad = $current211();
        $bad['indexes'][0]['stat4SeekWindows'][0]['lowerSample'] = 'bad';
        $t->throws(InvalidArgumentException::class, static fn () => $plan211(5, 1, null, $bad));
    },
    'planner stat4 expression partial current source next211 invalid rowid' => static function (TestRunner $t) use ($current211, $plan211): void {
        $bad = $current211();
        $bad['indexes'][0]['stat4SeekWindows'][0]['rowids'][] = 'bad-rowid';
        $t->throws(InvalidArgumentException::class, static fn () => $plan211(5, 1, null, $bad));
    },
];

foreach (range(1, 12) as $case) {
    $tests['planner stat4 expression partial current source next211 repeated seek window fence ' . $case] = static function (TestRunner $t) use ($plan211, $case): void {
        $plan = $plan211(1 + ($case % 5), $case % 4);
        $t->same($plan['matchedRowids'], $plan['stat4SeekWindowFence']['matchedRowids']);
    };
}

return $tests;

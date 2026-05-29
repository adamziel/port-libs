<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan;

$eq226 = static fn (string $column, mixed $right): array => ['left' => ['column' => $column], 'operator' => '=', 'right' => $right];
$like226 = static fn (string $column, string $right): array => ['left' => ['column' => $column], 'operator' => 'LIKE', 'right' => $right];
$notNull226 = static fn (string $column): array => ['left' => ['column' => $column], 'operator' => 'IS NOT NULL'];
$between226 = static fn (string $expression, mixed $lower, mixed $upper): array => ['left' => ['expression' => $expression], 'operator' => 'BETWEEN', 'lower' => $lower, 'upper' => $upper];

$prepared226 = static fn (): array => [
    'name' => 'prepared-wp-options-sample-window-stat4-expression-next226',
    'schemaCookie' => 2260,
    'stat4Generation' => 226,
    'rows' => [
        ['rowid' => 10, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_alpha', 'option_value' => 'alpha-old', 'updated_at' => 10],
        ['rowid' => 20, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_forms', 'option_value' => 'forms-old', 'updated_at' => 20],
        ['rowid' => 30, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_seo', 'option_value' => 'seo-old', 'updated_at' => 30],
    ],
    'indexes' => [[
        'name' => 'idx_wp_options_lower_sample_window_next226',
        'rootPage' => 22601,
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
        'partialGroupedOrPredicateArms' => [
            [
                ['left' => ['column' => 'blog_id'], 'operator' => '=', 'right' => 1],
                ['left' => ['column' => 'autoload'], 'operator' => '=', 'right' => 'yes'],
            ],
            [
                ['left' => ['column' => 'autoload'], 'operator' => '=', 'right' => 'critical'],
            ],
        ],
        'partialGroupedLikePredicateArms' => [
            [
                ['left' => ['column' => 'blog_id'], 'operator' => '=', 'right' => 1],
                ['left' => ['column' => 'option_name'], 'operator' => 'LIKE', 'right' => 'plugin_%'],
            ],
        ],
        'coveringColumns' => ['option_name', 'option_value', 'updated_at', 'autoload', 'blog_id'],
        'stat4Samples' => [
            ['neq' => '1 1', 'nlt' => '0 0', 'ndlt' => '0 0', 'sample' => ['plugin_forms', 20]],
            ['neq' => '1 1', 'nlt' => '1 1', 'ndlt' => '1 1', 'sample' => ['plugin_mail', 50]],
            ['neq' => '1 1', 'nlt' => '2 2', 'ndlt' => '2 2', 'sample' => ['plugin_seo', 30]],
            ['neq' => '1 1', 'nlt' => '3 3', 'ndlt' => '3 3', 'sample' => ['plugin_zulu', 60]],
        ],
    ]],
];

$current226 = static function (array $overrides = []) use ($prepared226): array {
    $source = $prepared226();
    $source['name'] = 'current-wp-options-sample-window-stat4-expression-next226';
    $source['schemaCookie'] = 2268;
    $source['stat4Generation'] = 286;
    $source['indexes'][0]['rootPage'] = 22688;
    $source['indexes'][0]['stat4Samples'] = [
        ['neq' => '1 1', 'nlt' => '0 0', 'ndlt' => '0 0', 'sample' => ['plugin_alpha', 10]],
        ['neq' => '3 1', 'nlt' => '1 1', 'ndlt' => '1 1', 'sample' => ['plugin_forms', 20]],
        ['neq' => '1 1', 'nlt' => '4 2', 'ndlt' => '2 2', 'sample' => ['plugin_mail', 50]],
        ['neq' => '1 1', 'nlt' => '5 3', 'ndlt' => '3 3', 'sample' => ['plugin_seo', 30]],
        ['neq' => '1 1', 'nlt' => '6 4', 'ndlt' => '4 4', 'sample' => ['plugin_zulu', 60]],
        ['neq' => '1 1', 'nlt' => '7 5', 'ndlt' => '5 5', 'sample' => ['theme_mods_current', 90]],
    ];
    $source['rows'] = [
        ['rowid' => 90, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'theme_mods_current', 'option_value' => 'theme', 'updated_at' => 90],
        ['rowid' => 60, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_zulu', 'option_value' => 'zulu', 'updated_at' => 60],
        ['rowid' => 30, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_seo', 'option_value' => 'seo', 'updated_at' => 30],
        ['rowid' => 50, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'Plugin_Mail', 'option_value' => 'mail', 'updated_at' => 50],
        ['rowid' => 20, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_forms', 'option_value' => 'forms-anchor', 'updated_at' => 20],
        ['rowid' => 21, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'Plugin_Forms', 'option_value' => 'forms-copy-a', 'updated_at' => 21],
        ['rowid' => 22, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'PLUGIN_FORMS', 'option_value' => 'forms-copy-b', 'updated_at' => 22],
        ['rowid' => 10, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_alpha', 'option_value' => 'alpha', 'updated_at' => 10],
        ['rowid' => 70, 'blog_id' => 1, 'autoload' => 'no', 'option_name' => 'plugin_forms', 'option_value' => 'lazy', 'updated_at' => 70],
        ['rowid' => 80, 'blog_id' => 2, 'autoload' => 'yes', 'option_name' => 'plugin_forms', 'option_value' => 'other-blog', 'updated_at' => 80],
    ];
    foreach ($overrides as $key => $value) {
        $source[$key] = $value;
    }

    return $source;
};

$terms226 = static fn (): array => [
    $between226('LOWER( option_name )', 'plugin_forms', 'plugin_zulu'),
    $eq226('autoload', 'yes'),
    $notNull226('option_name'),
    $eq226('blog_id', 1),
    $like226('option_name', 'plugin_%'),
];
$plan226 = static fn (int $limit = 4, int $offset = 0, ?array $prepared = null, ?array $current = null, ?array $terms = null, ?array $needed = null): array => SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan::materializeNext226(
    $prepared ?? $prepared226(),
    $current ?? $current226(),
    $terms ?? $terms226(),
    $needed ?? ['option_name', 'option_value', 'updated_at', 'blog_id'],
    $limit,
    $offset,
);
$missingUpper226 = static fn () => $plan226(4, 0, null, null, [
    $between226('LOWER( option_name )', 'plugin_forms', null),
    $eq226('autoload', 'yes'),
    $notNull226('option_name'),
    $eq226('blog_id', 1),
    $like226('option_name', 'plugin_%'),
]);
$brokenWindow226 = static function () use ($current226, $plan226): array {
    $current = $current226();
    $current['indexes'][0]['stat4Samples'][2]['sample'] = ['plugin_other', 55];

    return $plan226(4, 0, null, $current);
};
$badSample226 = static function () use ($current226, $plan226): array {
    $current = $current226();
    $current['indexes'][0]['stat4Samples'][] = ['neq' => '1'];

    return $plan226(4, 0, null, $current);
};

$tests = [
    'planner stat4 expression partial current source next226 status ready' => static fn (TestRunner $t) => $t->same('stat4-expression-partial-current-source-next226-ready', $plan226()['status']),
    'planner stat4 expression partial current source next226 selected current' => static fn (TestRunner $t) => $t->same('current', $plan226()['selectedSource']),
    'planner stat4 expression partial current source next226 inherits next219' => static fn (TestRunner $t) => $t->same(true, $plan226()['selectedPlan']['next219Ready']),
    'planner stat4 expression partial current source next226 ready flag' => static fn (TestRunner $t) => $t->same(true, $plan226()['selectedPlan']['next226Ready']),
    'planner stat4 expression partial current source next226 selected index' => static fn (TestRunner $t) => $t->same('idx_wp_options_lower_sample_window_next226', $plan226()['selectedPlan']['name']),
    'planner stat4 expression partial current source next226 root page' => static fn (TestRunner $t) => $t->same(22688, $plan226()['selectedPlan']['rootPage']),
    'planner stat4 expression partial current source next226 matched rowids' => static fn (TestRunner $t) => $t->same([60, 30, 50, 20], $plan226()['matchedRowids']),
    'planner stat4 expression partial current source next226 projected zulu' => static fn (TestRunner $t) => $t->same('zulu', $plan226()['projectedRows'][0]['option_value']),
    'planner stat4 expression partial current source next226 expression' => static fn (TestRunner $t) => $t->same('lower(option_name)', $plan226()['stat4SampleWindowFence']['expression']),
    'planner stat4 expression partial current source next226 lower bound' => static fn (TestRunner $t) => $t->same('plugin_forms', $plan226()['stat4SampleWindowFence']['lowerBound']),
    'planner stat4 expression partial current source next226 upper bound' => static fn (TestRunner $t) => $t->same('plugin_zulu', $plan226()['stat4SampleWindowFence']['upperBound']),
    'planner stat4 expression partial current source next226 window ready' => static fn (TestRunner $t) => $t->same(true, $plan226()['stat4SampleWindowFence']['ready']),
    'planner stat4 expression partial current source next226 prepared window rowids' => static fn (TestRunner $t) => $t->same([20, 50, 30, 60], $plan226()['stat4SampleWindowFence']['preparedWindowRowids']),
    'planner stat4 expression partial current source next226 current window rowids' => static fn (TestRunner $t) => $t->same([20, 50, 30, 60], $plan226()['stat4SampleWindowFence']['currentWindowRowids']),
    'planner stat4 expression partial current source next226 matched sample rowids' => static fn (TestRunner $t) => $t->same([60, 30, 50, 20], $plan226()['stat4SampleWindowFence']['matchedRowids']),
    'planner stat4 expression partial current source next226 outside rowids' => static fn (TestRunner $t) => $t->same([10, 90], $plan226()['stat4SampleWindowFence']['currentOutsideWindowRowids']),
    'planner stat4 expression partial current source next226 sample count' => static fn (TestRunner $t) => $t->same(4, $plan226()['stat4SampleWindowFence']['windowSampleCount']),
    'planner stat4 expression partial current source next226 outside count' => static fn (TestRunner $t) => $t->same(2, $plan226()['stat4SampleWindowFence']['outsideWindowSampleCount']),
    'planner stat4 expression partial current source next226 window signature length' => static fn (TestRunner $t) => $t->same(64, strlen($plan226()['stat4SampleWindowFence']['windowSignature'])),
    'planner stat4 expression partial current source next226 outside signature length' => static fn (TestRunner $t) => $t->same(64, strlen($plan226()['stat4SampleWindowFence']['outsideWindowSignature'])),
    'planner stat4 expression partial current source next226 selected expression' => static fn (TestRunner $t) => $t->same('lower(option_name)', $plan226()['selectedPlan']['next226Expression']),
    'planner stat4 expression partial current source next226 selected lower' => static fn (TestRunner $t) => $t->same('plugin_forms', $plan226()['selectedPlan']['next226LowerBound']),
    'planner stat4 expression partial current source next226 selected upper' => static fn (TestRunner $t) => $t->same('plugin_zulu', $plan226()['selectedPlan']['next226UpperBound']),
    'planner stat4 expression partial current source next226 selected window rowids' => static fn (TestRunner $t) => $t->same([20, 50, 30, 60], $plan226()['selectedPlan']['next226WindowRowids']),
    'planner stat4 expression partial current source next226 selected outside rowids' => static fn (TestRunner $t) => $t->same([10, 90], $plan226()['selectedPlan']['next226OutsideWindowRowids']),
    'planner stat4 expression partial current source next226 selected signature' => static fn (TestRunner $t) => $t->same($plan226()['stat4SampleWindowFence']['windowSignature'], $plan226()['selectedPlan']['next226WindowSignature']),
    'planner stat4 expression partial current source next226 stat4 signature' => static fn (TestRunner $t) => $t->same($plan226()['stat4SampleWindowFence']['windowSignature'], $plan226()['stat4Fence']['next226WindowSignature']),
    'planner stat4 expression partial current source next226 stat4 outside signature' => static fn (TestRunner $t) => $t->same($plan226()['stat4SampleWindowFence']['outsideWindowSignature'], $plan226()['stat4Fence']['next226OutsideWindowSignature']),
    'planner stat4 expression partial current source next226 stat4 ready' => static fn (TestRunner $t) => $t->same(true, $plan226()['stat4Fence']['next226WindowReady']),
    'planner stat4 expression partial current source next226 cursor appended' => static fn (TestRunner $t) => $t->same('RecheckCurrentSourceStat4SampleWindow', $plan226()['cursorProgram'][array_key_last($plan226()['cursorProgram'])]['opcode']),
    'planner stat4 expression partial current source next226 cursor mode' => static fn (TestRunner $t) => $t->same('next226-current-source-stat4-expression-partial-sample-window', $plan226()['cursorProgram'][array_key_last($plan226()['cursorProgram'])]['mode']),
    'planner stat4 expression partial current source next226 cursor lower' => static fn (TestRunner $t) => $t->same('plugin_forms', $plan226()['cursorProgram'][array_key_last($plan226()['cursorProgram'])]['lowerBound']),
    'planner stat4 expression partial current source next226 cursor upper' => static fn (TestRunner $t) => $t->same('plugin_zulu', $plan226()['cursorProgram'][array_key_last($plan226()['cursorProgram'])]['upperBound']),
    'planner stat4 expression partial current source next226 cursor rowids' => static fn (TestRunner $t) => $t->same([20, 50, 30, 60], $plan226()['cursorProgram'][array_key_last($plan226()['cursorProgram'])]['windowRowids']),
    'planner stat4 expression partial current source next226 cursor signature' => static fn (TestRunner $t) => $t->same($plan226()['stat4SampleWindowFence']['windowSignature'], $plan226()['cursorProgram'][array_key_last($plan226()['cursorProgram'])]['signature']),
    'planner stat4 expression partial current source next226 peer boundary preserved' => static fn (TestRunner $t) => $t->same('plugin_forms', $plan226()['stat4PeerRunFence']['boundaryKey']),
    'planner stat4 expression partial current source next226 peer proof preserved' => static fn (TestRunner $t) => $t->same(true, $plan226()['stat4PeerRunFence']['ready']),
    'planner stat4 expression partial current source next226 grouped like preserved' => static fn (TestRunner $t) => $t->same(true, $plan226()['groupedLikePredicateFence']['currentGroupedLikePredicateImplied']),
    'planner stat4 expression partial current source next226 grouped or preserved' => static fn (TestRunner $t) => $t->same(true, $plan226()['groupedPartialOrPredicateFence']['currentGroupedPartialOrPredicateImplied']),
    'planner stat4 expression partial current source next226 partial fence preserved' => static fn (TestRunner $t) => $t->same(true, $plan226()['partialPredicateFence']['currentPartialPredicateImplied']),
    'planner stat4 expression partial current source next226 dependency marker' => static fn (TestRunner $t) => $t->true(in_array('sqlite-sqlplanner-stat4-expression-partial-current-source-next226', $plan226()['dependencies'], true)),
    'planner stat4 expression partial current source next226 dependency closure' => static fn (TestRunner $t) => $t->contains('no new support component needed', $plan226()['dependency_closure']),
    'planner stat4 expression partial current source next226 non overlap' => static fn (TestRunner $t) => $t->contains('sample rows inside', $plan226()['non_overlap']),
    'planner stat4 expression partial current source next226 detail' => static fn (TestRunner $t) => $t->contains('NEXT226 SAMPLE WINDOW', $plan226()['detail']),
    'planner stat4 expression partial current source next226 missing upper rejects incomplete range' => static function (TestRunner $t) use ($missingUpper226): void {
        $t->throws(InvalidArgumentException::class, $missingUpper226);
    },
    'planner stat4 expression partial current source next226 broken window blocks' => static fn (TestRunner $t) => $t->same('requires-current-source-stat4-window-reprepare', $brokenWindow226()['status']),
    'planner stat4 expression partial current source next226 broken window ready false' => static fn (TestRunner $t) => $t->same(false, $brokenWindow226()['stat4SampleWindowFence']['ready']),
    'planner stat4 expression partial current source next226 broken window rowids differ' => static fn (TestRunner $t) => $t->same([20, 55, 30, 60], $brokenWindow226()['stat4SampleWindowFence']['currentWindowRowids']),
    'planner stat4 expression partial current source next226 invalid negative limit' => static function (TestRunner $t) use ($plan226): void {
        $t->throws(InvalidArgumentException::class, static fn () => $plan226(-1, 0));
    },
    'planner stat4 expression partial current source next226 invalid negative offset' => static function (TestRunner $t) use ($plan226): void {
        $t->throws(InvalidArgumentException::class, static fn () => $plan226(1, -1));
    },
    'planner stat4 expression partial current source next226 invalid sample' => static function (TestRunner $t) use ($badSample226): void {
        $t->throws(InvalidArgumentException::class, $badSample226);
    },
    'planner stat4 expression partial current source next226 missing index list' => static function (TestRunner $t) use ($current226, $plan226): void {
        $current = $current226();
        unset($current['indexes']);
        $t->throws(InvalidArgumentException::class, static fn () => $plan226(4, 0, null, $current));
    },
    'planner stat4 expression partial current source next226 detail names selected index' => static fn (TestRunner $t) => $t->contains('idx_wp_options_lower_sample_window_next226', $plan226()['detail']),
];

foreach (range(1, 12) as $case) {
    $tests['planner stat4 expression partial current source next226 repeated sample window signature ' . $case] = static function (TestRunner $t) use ($plan226, $case): void {
        $plan = $plan226(1 + ($case % 4), $case % 3);
        $t->same($plan['stat4SampleWindowFence']['windowSignature'], $plan['selectedPlan']['next226WindowSignature']);
    };
}

return $tests;

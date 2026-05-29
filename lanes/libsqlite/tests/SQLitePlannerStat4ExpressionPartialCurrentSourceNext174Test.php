<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan;

$eq174 = static fn (string $column, mixed $right): array => ['left' => ['column' => $column], 'operator' => '=', 'right' => $right];
$notNull174 = static fn (string $column): array => ['left' => ['column' => $column], 'operator' => 'IS NOT NULL'];
$exprRange174 = static fn (string $expression, string $operator, string $right): array => ['left' => ['expression' => $expression], 'operator' => $operator, 'right' => $right];

$prepared174 = static fn (): array => [
    'name' => 'prepared-wp-options-stat4-expression-partial-range-next174',
    'schemaCookie' => 1740,
    'stat4Generation' => 31,
    'rows' => [
        ['rowid' => 10, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'Plugin_Cache', 'option_value' => 'old-cache', 'updated_at' => 10],
        ['rowid' => 11, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'Plugin_Forms', 'option_value' => 'old-forms', 'updated_at' => 11],
    ],
    'indexes' => [[
        'name' => 'idx_wp_options_lower_plugin_partial_range_next174',
        'rootPage' => 17401,
        'expression' => 'lower(option_name)',
        'expressionColumn' => '__expr_lower_option_name',
        'collation' => 'BINARY',
        'partialPredicateTerms' => [
            ['left' => ['column' => 'autoload'], 'operator' => '=', 'right' => 'yes'],
            ['left' => ['column' => 'blog_id'], 'operator' => '=', 'right' => 1],
            ['left' => ['column' => 'option_name'], 'operator' => 'IS NOT NULL'],
        ],
        'coveringColumns' => ['option_name', 'option_value', 'updated_at', 'autoload', 'blog_id'],
        'stat4Samples' => [
            ['neq' => '2 1', 'nlt' => '0 0', 'ndlt' => '0 0', 'sample' => ['plugin_cache', 10]],
            ['neq' => '1 1', 'nlt' => '2 1', 'ndlt' => '1 1', 'sample' => ['plugin_forms', 11]],
            ['neq' => '1 1', 'nlt' => '3 2', 'ndlt' => '2 2', 'sample' => ['plugin_mail', 12]],
            ['neq' => '1 1', 'nlt' => '4 3', 'ndlt' => '3 3', 'sample' => ['plugin_seo', 13]],
            ['neq' => '4 1', 'nlt' => '5 4', 'ndlt' => '4 4', 'sample' => ['siteurl', 90]],
        ],
    ]],
];

$current174 = static function () use ($prepared174): array {
    $source = $prepared174();
    $source['name'] = 'current-wp-options-stat4-expression-partial-range-next174';
    $source['schemaCookie'] = 1745;
    $source['stat4Generation'] = 44;
    $source['indexes'][0]['rootPage'] = 17445;
    $source['indexes'][0]['stat4Samples'] = [
        ['neq' => '2 1', 'nlt' => '0 0', 'ndlt' => '0 0', 'sample' => ['plugin_cache', 20]],
        ['neq' => '1 1', 'nlt' => '2 1', 'ndlt' => '1 1', 'sample' => ['plugin_forms', 22]],
        ['neq' => '1 1', 'nlt' => '3 2', 'ndlt' => '2 2', 'sample' => ['plugin_mail', 24]],
        ['neq' => '1 1', 'nlt' => '4 3', 'ndlt' => '3 3', 'sample' => ['plugin_seo', 26]],
        ['neq' => '4 1', 'nlt' => '5 4', 'ndlt' => '4 4', 'sample' => ['siteurl', 90]],
    ];
    $source['rows'] = [
        ['rowid' => 20, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'Plugin_Cache', 'option_value' => 'cache-a', 'updated_at' => 20],
        ['rowid' => 21, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_cache', 'option_value' => 'cache-b', 'updated_at' => 21],
        ['rowid' => 22, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'Plugin_Forms', 'option_value' => 'forms', 'updated_at' => 22],
        ['rowid' => 24, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'Plugin_Mail', 'option_value' => 'mail', 'updated_at' => 24],
        ['rowid' => 26, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'Plugin_Seo', 'option_value' => 'seo', 'updated_at' => 26],
        ['rowid' => 30, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'siteurl', 'option_value' => 'https://example.test', 'updated_at' => 30],
        ['rowid' => 31, 'blog_id' => 2, 'autoload' => 'yes', 'option_name' => 'Plugin_Cache', 'option_value' => 'network-cache', 'updated_at' => 31],
        ['rowid' => 32, 'blog_id' => 1, 'autoload' => 'no', 'option_name' => 'Plugin_Forms', 'option_value' => 'lazy-forms', 'updated_at' => 32],
        ['rowid' => 33, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => null, 'option_value' => 'null-name', 'updated_at' => 33],
    ];

    return $source;
};

$terms174 = static fn (): array => [
    $exprRange174('LOWER( option_name )', '>=', 'plugin_cache'),
    $exprRange174('lower(option_name)', '<', 'plugin_t'),
    $eq174('autoload', 'yes'),
    $eq174('blog_id', 1),
    $notNull174('option_name'),
];
$needed174 = ['option_name', 'option_value', 'updated_at'];
$plan174 = static fn (?array $prepared = null, ?array $current = null, ?array $terms = null, ?array $next = null, ?array $needed = null): array => SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan::materializeNext174(
    $prepared ?? $prepared174(),
    $current ?? $current174(),
    $terms ?? $terms174(),
    $needed ?? $needed174,
    $next,
);

$outsideRangeNext174 = static function () use ($current174): array {
    $next = $current174();
    $next['name'] = 'next-wp-options-stat4-expression-partial-outside-range-next174';
    $next['rows'][] = ['rowid' => 40, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'siteurl', 'option_value' => 'https://next.example.test', 'updated_at' => 40];
    $next['rows'][] = ['rowid' => 41, 'blog_id' => 2, 'autoload' => 'yes', 'option_name' => 'Plugin_Cache', 'option_value' => 'network-cache-new', 'updated_at' => 41];
    $next['rows'][] = ['rowid' => 42, 'blog_id' => 1, 'autoload' => 'no', 'option_name' => 'Plugin_Forms', 'option_value' => 'lazy-forms-new', 'updated_at' => 42];

    return $next;
};
$insideRangeNext174 = static function () use ($current174): array {
    $next = $current174();
    $next['name'] = 'next-wp-options-stat4-expression-partial-inside-range-next174';
    $next['rows'][] = ['rowid' => 43, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'Plugin_Search', 'option_value' => 'search', 'updated_at' => 43];

    return $next;
};
$changedStat4Next174 = static function () use ($outsideRangeNext174): array {
    $next = $outsideRangeNext174();
    $next['stat4Generation'] = 45;

    return $next;
};
$changedSchemaNext174 = static function () use ($outsideRangeNext174): array {
    $next = $outsideRangeNext174();
    $next['schemaCookie'] = 1746;

    return $next;
};

$base174 = static fn (): array => $plan174(null, null, null, $outsideRangeNext174());
$inside174 = static fn (): array => $plan174(null, null, null, $insideRangeNext174());
$stat4174 = static fn (): array => $plan174(null, null, null, $changedStat4Next174());
$schema174 = static fn (): array => $plan174(null, null, null, $changedSchemaNext174());
$fresh174 = static function () use ($prepared174, $plan174): array {
    $source = $prepared174();

    return $plan174($source, $source, null, $source);
};

$tests = [
    'planner stat4 expression partial current source next174 status ready' => static fn (TestRunner $t) => $t->same('stat4-expression-partial-current-source-next174-ready', $base174()['status']),
    'planner stat4 expression partial current source next174 selected current' => static fn (TestRunner $t) => $t->same('current', $base174()['selectedSource']),
    'planner stat4 expression partial current source next174 stale prepared' => static fn (TestRunner $t) => $t->same(true, $base174()['stalePreparedStatement']),
    'planner stat4 expression partial current source next174 reprepare required for prepared' => static fn (TestRunner $t) => $t->same(true, $base174()['reprepareRequired']),
    'planner stat4 expression partial current source next174 schema changed' => static fn (TestRunner $t) => $t->same(true, $base174()['schemaCookieChanged']),
    'planner stat4 expression partial current source next174 stat4 changed' => static fn (TestRunner $t) => $t->same(true, $base174()['stat4GenerationChanged']),
    'planner stat4 expression partial current source next174 selected index' => static fn (TestRunner $t) => $t->same('idx_wp_options_lower_plugin_partial_range_next174', $base174()['selectedPlan']['name']),
    'planner stat4 expression partial current source next174 selected root' => static fn (TestRunner $t) => $t->same(17445, $base174()['selectedPlan']['rootPage']),
    'planner stat4 expression partial current source next174 expression normalized' => static fn (TestRunner $t) => $t->same('lower(option_name)', $base174()['selectedPlan']['expression']),
    'planner stat4 expression partial current source next174 partial flag' => static fn (TestRunner $t) => $t->same(true, $base174()['selectedPlan']['partial']),
    'planner stat4 expression partial current source next174 partial implied' => static fn (TestRunner $t) => $t->same(true, $base174()['selectedPlan']['partialPredicateImpliedByQuery']),
    'planner stat4 expression partial current source next174 lower' => static fn (TestRunner $t) => $t->same('plugin_cache', $base174()['selectedPlan']['rangeLower']),
    'planner stat4 expression partial current source next174 upper' => static fn (TestRunner $t) => $t->same('plugin_t', $base174()['selectedPlan']['rangeUpper']),
    'planner stat4 expression partial current source next174 lower inclusive' => static fn (TestRunner $t) => $t->same(true, $base174()['selectedPlan']['lowerInclusive']),
    'planner stat4 expression partial current source next174 upper exclusive' => static fn (TestRunner $t) => $t->same(false, $base174()['selectedPlan']['upperInclusive']),
    'planner stat4 expression partial current source next174 stat4 used' => static fn (TestRunner $t) => $t->same(true, $base174()['selectedPlan']['stat4Used']),
    'planner stat4 expression partial current source next174 stat4 keys' => static fn (TestRunner $t) => $t->same(['plugin_cache', 'plugin_forms', 'plugin_mail', 'plugin_seo'], $base174()['selectedPlan']['stat4MatchedKeys']),
    'planner stat4 expression partial current source next174 stat4 rowids' => static fn (TestRunner $t) => $t->same([20, 22, 24, 26], $base174()['selectedPlan']['stat4MatchedRowids']),
    'planner stat4 expression partial current source next174 estimate' => static fn (TestRunner $t) => $t->same(5, $base174()['selectedPlan']['estimatedRows']),
    'planner stat4 expression partial current source next174 covering' => static fn (TestRunner $t) => $t->same(true, $base174()['selectedPlan']['covering']),
    'planner stat4 expression partial current source next174 matched rowids' => static fn (TestRunner $t) => $t->same([20, 21, 22, 24, 26], $base174()['matchedRowids']),
    'planner stat4 expression partial current source next174 selected rowids' => static fn (TestRunner $t) => $t->same([20, 21, 22, 24, 26], $base174()['selectedPlan']['matchedRowids']),
    'planner stat4 expression partial current source next174 matched keys' => static fn (TestRunner $t) => $t->same(['plugin_cache', 'plugin_cache', 'plugin_forms', 'plugin_mail', 'plugin_seo'], $base174()['matchedExpressionKeys']),
    'planner stat4 expression partial current source next174 payload current cache' => static fn (TestRunner $t) => $t->same('cache-a', $base174()['matchedRows'][0]['payload']['option_value']),
    'planner stat4 expression partial current source next174 excludes siteurl' => static fn (TestRunner $t) => $t->same(false, in_array(30, $base174()['matchedRowids'], true)),
    'planner stat4 expression partial current source next174 excludes blog two' => static fn (TestRunner $t) => $t->same(false, in_array(31, $base174()['matchedRowids'], true)),
    'planner stat4 expression partial current source next174 excludes autoload no' => static fn (TestRunner $t) => $t->same(false, in_array(32, $base174()['matchedRowids'], true)),
    'planner stat4 expression partial current source next174 outside range admitted' => static fn (TestRunner $t) => $t->same(true, $base174()['next174Source']['admitted']),
    'planner stat4 expression partial current source next174 outside reasons clear' => static fn (TestRunner $t) => $t->same([], $base174()['next174Source']['replanReasons']),
    'planner stat4 expression partial current source next174 range rows stable' => static fn (TestRunner $t) => $t->same(true, $base174()['next174Source']['rangeRowsStable']),
    'planner stat4 expression partial current source next174 current range rowids' => static fn (TestRunner $t) => $t->same([20, 21, 22, 24, 26], $base174()['stat4Fence']['currentRangeRowids']),
    'planner stat4 expression partial current source next174 next range rowids stable' => static fn (TestRunner $t) => $t->same([20, 21, 22, 24, 26], $base174()['stat4Fence']['nextRangeRowids']),
    'planner stat4 expression partial current source next174 signature length' => static fn (TestRunner $t) => $t->same(64, strlen($base174()['stat4Fence']['currentRangeSignature'])),
    'planner stat4 expression partial current source next174 signatures equal' => static fn (TestRunner $t) => $t->same($base174()['stat4Fence']['currentRangeSignature'], $base174()['stat4Fence']['nextRangeSignature']),
    'planner stat4 expression partial current source next174 cursor open root' => static fn (TestRunner $t) => $t->same(17445, $base174()['cursorProgram'][0]['rootPage']),
    'planner stat4 expression partial current source next174 cursor seek lower' => static fn (TestRunner $t) => $t->same('plugin_cache', $base174()['cursorProgram'][1]['key']),
    'planner stat4 expression partial current source next174 cursor upper' => static fn (TestRunner $t) => $t->same('plugin_t', $base174()['cursorProgram'][2]['key']),
    'planner stat4 expression partial current source next174 cursor deferred stable' => static fn (TestRunner $t) => $t->same(true, $base174()['cursorProgram'][3]['next174RangeRowsStable']),
    'planner stat4 expression partial current source next174 cursor row count' => static fn (TestRunner $t) => $t->same(5, $base174()['cursorProgram'][4]['rowCount']),
    'planner stat4 expression partial current source next174 detail' => static fn (TestRunner $t) => $t->contains('RANGE ROWS STABLE', $base174()['detail']),
    'planner stat4 expression partial current source next174 dependency marker' => static fn (TestRunner $t) => $t->same(['sqlite-sqlplanner-stat4-expression-partial-current-source-next174'], $base174()['dependencies']),
    'planner stat4 expression partial current source next174 dependency closure' => static fn (TestRunner $t) => $t->contains('no new support component needed', $base174()['dependency_closure']),
    'planner stat4 expression partial current source next174 non overlap' => static fn (TestRunner $t) => $t->contains('next170 IN-bucket row churn', $base174()['non_overlap']),
    'planner stat4 expression partial current source next174 inside change blocks' => static fn (TestRunner $t) => $t->same('requires-current-source-reprepare', $inside174()['status']),
    'planner stat4 expression partial current source next174 inside not admitted' => static fn (TestRunner $t) => $t->same(false, $inside174()['next174Source']['admitted']),
    'planner stat4 expression partial current source next174 inside reason' => static fn (TestRunner $t) => $t->same(['range-row-signature'], $inside174()['next174Source']['replanReasons']),
    'planner stat4 expression partial current source next174 inside rowids changed' => static fn (TestRunner $t) => $t->same([20, 21, 22, 24, 43, 26], $inside174()['stat4Fence']['nextRangeRowids']),
    'planner stat4 expression partial current source next174 inside signature differs' => static fn (TestRunner $t) => $t->same(false, $inside174()['stat4Fence']['currentRangeSignature'] === $inside174()['stat4Fence']['nextRangeSignature']),
    'planner stat4 expression partial current source next174 stat4 change blocks' => static fn (TestRunner $t) => $t->same('requires-current-source-reprepare', $stat4174()['status']),
    'planner stat4 expression partial current source next174 stat4 reason' => static fn (TestRunner $t) => $t->same(['stat4-generation'], $stat4174()['next174Source']['replanReasons']),
    'planner stat4 expression partial current source next174 schema change blocks' => static fn (TestRunner $t) => $t->same('requires-current-source-reprepare', $schema174()['status']),
    'planner stat4 expression partial current source next174 schema reason' => static fn (TestRunner $t) => $t->same(['schema-cookie'], $schema174()['next174Source']['replanReasons']),
    'planner stat4 expression partial current source next174 fresh prepared source' => static fn (TestRunner $t) => $t->same('prepared', $fresh174()['selectedSource']),
    'planner stat4 expression partial current source next174 fresh rowids' => static fn (TestRunner $t) => $t->same([10, 11], $fresh174()['matchedRowids']),
    'planner stat4 expression partial current source next174 no next remains ready' => static fn (TestRunner $t) => $t->same('stat4-expression-partial-current-source-next174-ready', $plan174()['status']),
    'planner stat4 expression partial current source next174 invalid needed columns' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => $plan174(null, null, null, null, [])),
    'planner stat4 expression partial current source next174 invalid rows' => static function (TestRunner $t) use ($current174, $plan174): void {
        $bad = $current174();
        $bad['rows'][] = 'bad';
        $t->throws(InvalidArgumentException::class, static fn () => $plan174(null, $bad));
    },
    'planner stat4 expression partial current source next174 invalid stat4 integer' => static function (TestRunner $t) use ($current174, $plan174): void {
        $bad = $current174();
        $bad['indexes'][0]['stat4Samples'][0]['neq'] = 'bad';
        $t->throws(InvalidArgumentException::class, static fn () => $plan174(null, $bad));
    },
    'planner stat4 expression partial current source next174 missing range' => static function (TestRunner $t) use ($plan174, $eq174): void {
        $t->throws(InvalidArgumentException::class, static fn () => $plan174(null, null, [$eq174('autoload', 'yes')]));
    },
];

foreach (range(1, 8) as $case) {
    $tests['planner stat4 expression partial current source next174 repeated stable range fence ' . $case] = static function (TestRunner $t) use ($base174, $case): void {
        $plan = $base174();
        $t->same(true, $plan['selectedPlan']['estimatedRows'] >= $case - 3);
    };
}

return $tests;

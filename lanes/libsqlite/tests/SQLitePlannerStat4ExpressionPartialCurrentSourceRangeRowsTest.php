<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan;

$eqRangeRows = static fn (string $column, mixed $right): array => ['left' => ['column' => $column], 'operator' => '=', 'right' => $right];
$notNullRangeRows = static fn (string $column): array => ['left' => ['column' => $column], 'operator' => 'IS NOT NULL'];
$exprRangeRows = static fn (string $expression, string $operator, string $right): array => ['left' => ['expression' => $expression], 'operator' => $operator, 'right' => $right];

$preparedRangeRows = static fn (): array => [
    'name' => 'prepared-wp-options-stat4-expression-partial-range-rows',
    'schemaCookie' => 1740,
    'stat4Generation' => 31,
    'rows' => [
        ['rowid' => 10, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'Plugin_Cache', 'option_value' => 'old-cache', 'updated_at' => 10],
        ['rowid' => 11, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'Plugin_Forms', 'option_value' => 'old-forms', 'updated_at' => 11],
    ],
    'indexes' => [[
        'name' => 'idx_wp_options_lower_plugin_partial_range_rows',
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

$currentRangeRows = static function () use ($preparedRangeRows): array {
    $source = $preparedRangeRows();
    $source['name'] = 'current-wp-options-stat4-expression-partial-range-rows';
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

$termsRangeRows = static fn (): array => [
    $exprRangeRows('LOWER( option_name )', '>=', 'plugin_cache'),
    $exprRangeRows('lower(option_name)', '<', 'plugin_t'),
    $eqRangeRows('autoload', 'yes'),
    $eqRangeRows('blog_id', 1),
    $notNullRangeRows('option_name'),
];
$neededRangeRows = ['option_name', 'option_value', 'updated_at'];
$planRangeRows = static fn (?array $prepared = null, ?array $current = null, ?array $terms = null, ?array $next = null, ?array $needed = null): array => SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan::materializeRangeRows(
    $prepared ?? $preparedRangeRows(),
    $current ?? $currentRangeRows(),
    $terms ?? $termsRangeRows(),
    $needed ?? $neededRangeRows,
    $next,
);

$outsideRangeRangeRows = static function () use ($currentRangeRows): array {
    $next = $currentRangeRows();
    $next['name'] = 'next-wp-options-stat4-expression-partial-outside-range-rows';
    $next['rows'][] = ['rowid' => 40, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'siteurl', 'option_value' => 'https://next.example.test', 'updated_at' => 40];
    $next['rows'][] = ['rowid' => 41, 'blog_id' => 2, 'autoload' => 'yes', 'option_name' => 'Plugin_Cache', 'option_value' => 'network-cache-new', 'updated_at' => 41];
    $next['rows'][] = ['rowid' => 42, 'blog_id' => 1, 'autoload' => 'no', 'option_name' => 'Plugin_Forms', 'option_value' => 'lazy-forms-new', 'updated_at' => 42];

    return $next;
};
$insideRangeRangeRows = static function () use ($currentRangeRows): array {
    $next = $currentRangeRows();
    $next['name'] = 'next-wp-options-stat4-expression-partial-inside-range-rows';
    $next['rows'][] = ['rowid' => 43, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'Plugin_Search', 'option_value' => 'search', 'updated_at' => 43];

    return $next;
};
$changedStat4RangeRows = static function () use ($outsideRangeRangeRows): array {
    $next = $outsideRangeRangeRows();
    $next['stat4Generation'] = 45;

    return $next;
};
$changedSchemaRangeRows = static function () use ($outsideRangeRangeRows): array {
    $next = $outsideRangeRangeRows();
    $next['schemaCookie'] = 1746;

    return $next;
};

$baseRangeRows = static fn (): array => $planRangeRows(null, null, null, $outsideRangeRangeRows());
$insideRangeRowsPlan = static fn (): array => $planRangeRows(null, null, null, $insideRangeRangeRows());
$stat4RangeRowsPlan = static fn (): array => $planRangeRows(null, null, null, $changedStat4RangeRows());
$schemaRangeRowsPlan = static fn (): array => $planRangeRows(null, null, null, $changedSchemaRangeRows());
$freshRangeRows = static function () use ($preparedRangeRows, $planRangeRows): array {
    $source = $preparedRangeRows();

    return $planRangeRows($source, $source, null, $source);
};

$tests = [
    'planner stat4 expression partial current source range rows status ready' => static fn (TestRunner $t) => $t->same('stat4-expression-partial-current-source-range-rows-ready', $baseRangeRows()['status']),
    'planner stat4 expression partial current source range rows selected current' => static fn (TestRunner $t) => $t->same('current', $baseRangeRows()['selectedSource']),
    'planner stat4 expression partial current source range rows stale prepared' => static fn (TestRunner $t) => $t->same(true, $baseRangeRows()['stalePreparedStatement']),
    'planner stat4 expression partial current source range rows reprepare required for prepared' => static fn (TestRunner $t) => $t->same(true, $baseRangeRows()['reprepareRequired']),
    'planner stat4 expression partial current source range rows schema changed' => static fn (TestRunner $t) => $t->same(true, $baseRangeRows()['schemaCookieChanged']),
    'planner stat4 expression partial current source range rows stat4 changed' => static fn (TestRunner $t) => $t->same(true, $baseRangeRows()['stat4GenerationChanged']),
    'planner stat4 expression partial current source range rows selected index' => static fn (TestRunner $t) => $t->same('idx_wp_options_lower_plugin_partial_range_rows', $baseRangeRows()['selectedPlan']['name']),
    'planner stat4 expression partial current source range rows selected root' => static fn (TestRunner $t) => $t->same(17445, $baseRangeRows()['selectedPlan']['rootPage']),
    'planner stat4 expression partial current source range rows expression normalized' => static fn (TestRunner $t) => $t->same('lower(option_name)', $baseRangeRows()['selectedPlan']['expression']),
    'planner stat4 expression partial current source range rows partial flag' => static fn (TestRunner $t) => $t->same(true, $baseRangeRows()['selectedPlan']['partial']),
    'planner stat4 expression partial current source range rows partial implied' => static fn (TestRunner $t) => $t->same(true, $baseRangeRows()['selectedPlan']['partialPredicateImpliedByQuery']),
    'planner stat4 expression partial current source range rows lower' => static fn (TestRunner $t) => $t->same('plugin_cache', $baseRangeRows()['selectedPlan']['rangeLower']),
    'planner stat4 expression partial current source range rows upper' => static fn (TestRunner $t) => $t->same('plugin_t', $baseRangeRows()['selectedPlan']['rangeUpper']),
    'planner stat4 expression partial current source range rows lower inclusive' => static fn (TestRunner $t) => $t->same(true, $baseRangeRows()['selectedPlan']['lowerInclusive']),
    'planner stat4 expression partial current source range rows upper exclusive' => static fn (TestRunner $t) => $t->same(false, $baseRangeRows()['selectedPlan']['upperInclusive']),
    'planner stat4 expression partial current source range rows stat4 used' => static fn (TestRunner $t) => $t->same(true, $baseRangeRows()['selectedPlan']['stat4Used']),
    'planner stat4 expression partial current source range rows stat4 keys' => static fn (TestRunner $t) => $t->same(['plugin_cache', 'plugin_forms', 'plugin_mail', 'plugin_seo'], $baseRangeRows()['selectedPlan']['stat4MatchedKeys']),
    'planner stat4 expression partial current source range rows stat4 rowids' => static fn (TestRunner $t) => $t->same([20, 22, 24, 26], $baseRangeRows()['selectedPlan']['stat4MatchedRowids']),
    'planner stat4 expression partial current source range rows estimate' => static fn (TestRunner $t) => $t->same(5, $baseRangeRows()['selectedPlan']['estimatedRows']),
    'planner stat4 expression partial current source range rows covering' => static fn (TestRunner $t) => $t->same(true, $baseRangeRows()['selectedPlan']['covering']),
    'planner stat4 expression partial current source range rows matched rowids' => static fn (TestRunner $t) => $t->same([20, 21, 22, 24, 26], $baseRangeRows()['matchedRowids']),
    'planner stat4 expression partial current source range rows selected rowids' => static fn (TestRunner $t) => $t->same([20, 21, 22, 24, 26], $baseRangeRows()['selectedPlan']['matchedRowids']),
    'planner stat4 expression partial current source range rows matched keys' => static fn (TestRunner $t) => $t->same(['plugin_cache', 'plugin_cache', 'plugin_forms', 'plugin_mail', 'plugin_seo'], $baseRangeRows()['matchedExpressionKeys']),
    'planner stat4 expression partial current source range rows payload current cache' => static fn (TestRunner $t) => $t->same('cache-a', $baseRangeRows()['matchedRows'][0]['payload']['option_value']),
    'planner stat4 expression partial current source range rows excludes siteurl' => static fn (TestRunner $t) => $t->same(false, in_array(30, $baseRangeRows()['matchedRowids'], true)),
    'planner stat4 expression partial current source range rows excludes blog two' => static fn (TestRunner $t) => $t->same(false, in_array(31, $baseRangeRows()['matchedRowids'], true)),
    'planner stat4 expression partial current source range rows excludes autoload no' => static fn (TestRunner $t) => $t->same(false, in_array(32, $baseRangeRows()['matchedRowids'], true)),
    'planner stat4 expression partial current source range rows outside range admitted' => static fn (TestRunner $t) => $t->same(true, $baseRangeRows()['rangeRowsSource']['admitted']),
    'planner stat4 expression partial current source range rows outside reasons clear' => static fn (TestRunner $t) => $t->same([], $baseRangeRows()['rangeRowsSource']['replanReasons']),
    'planner stat4 expression partial current source range rows range rows stable' => static fn (TestRunner $t) => $t->same(true, $baseRangeRows()['rangeRowsSource']['rangeRowsStable']),
    'planner stat4 expression partial current source range rows current range rowids' => static fn (TestRunner $t) => $t->same([20, 21, 22, 24, 26], $baseRangeRows()['stat4Fence']['currentRangeRowids']),
    'planner stat4 expression partial current source range rows next range rowids stable' => static fn (TestRunner $t) => $t->same([20, 21, 22, 24, 26], $baseRangeRows()['stat4Fence']['nextRangeRowids']),
    'planner stat4 expression partial current source range rows signature length' => static fn (TestRunner $t) => $t->same(64, strlen($baseRangeRows()['stat4Fence']['currentRangeSignature'])),
    'planner stat4 expression partial current source range rows signatures equal' => static fn (TestRunner $t) => $t->same($baseRangeRows()['stat4Fence']['currentRangeSignature'], $baseRangeRows()['stat4Fence']['nextRangeSignature']),
    'planner stat4 expression partial current source range rows cursor open root' => static fn (TestRunner $t) => $t->same(17445, $baseRangeRows()['cursorProgram'][0]['rootPage']),
    'planner stat4 expression partial current source range rows cursor seek lower' => static fn (TestRunner $t) => $t->same('plugin_cache', $baseRangeRows()['cursorProgram'][1]['key']),
    'planner stat4 expression partial current source range rows cursor upper' => static fn (TestRunner $t) => $t->same('plugin_t', $baseRangeRows()['cursorProgram'][2]['key']),
    'planner stat4 expression partial current source range rows cursor deferred stable' => static fn (TestRunner $t) => $t->same(true, $baseRangeRows()['cursorProgram'][3]['rangeRowsStable']),
    'planner stat4 expression partial current source range rows cursor row count' => static fn (TestRunner $t) => $t->same(5, $baseRangeRows()['cursorProgram'][4]['rowCount']),
    'planner stat4 expression partial current source range rows detail' => static fn (TestRunner $t) => $t->contains('RANGE ROWS STABLE', $baseRangeRows()['detail']),
    'planner stat4 expression partial current source range rows dependency marker' => static fn (TestRunner $t) => $t->same(['sqlite-sqlplanner-stat4-expression-partial-current-source-range-rows'], $baseRangeRows()['dependencies']),
    'planner stat4 expression partial current source range rows dependency closure' => static fn (TestRunner $t) => $t->contains('no new support component needed', $baseRangeRows()['dependency_closure']),
    'planner stat4 expression partial current source range rows non overlap' => static fn (TestRunner $t) => $t->contains('STAT4 IN-bucket row churn', $baseRangeRows()['non_overlap']),
    'planner stat4 expression partial current source range rows inside change blocks' => static fn (TestRunner $t) => $t->same('requires-current-source-reprepare', $insideRangeRowsPlan()['status']),
    'planner stat4 expression partial current source range rows inside not admitted' => static fn (TestRunner $t) => $t->same(false, $insideRangeRowsPlan()['rangeRowsSource']['admitted']),
    'planner stat4 expression partial current source range rows inside reason' => static fn (TestRunner $t) => $t->same(['range-row-signature'], $insideRangeRowsPlan()['rangeRowsSource']['replanReasons']),
    'planner stat4 expression partial current source range rows inside rowids changed' => static fn (TestRunner $t) => $t->same([20, 21, 22, 24, 43, 26], $insideRangeRowsPlan()['stat4Fence']['nextRangeRowids']),
    'planner stat4 expression partial current source range rows inside signature differs' => static fn (TestRunner $t) => $t->same(false, $insideRangeRowsPlan()['stat4Fence']['currentRangeSignature'] === $insideRangeRowsPlan()['stat4Fence']['nextRangeSignature']),
    'planner stat4 expression partial current source range rows stat4 change blocks' => static fn (TestRunner $t) => $t->same('requires-current-source-reprepare', $stat4RangeRowsPlan()['status']),
    'planner stat4 expression partial current source range rows stat4 reason' => static fn (TestRunner $t) => $t->same(['stat4-generation'], $stat4RangeRowsPlan()['rangeRowsSource']['replanReasons']),
    'planner stat4 expression partial current source range rows schema change blocks' => static fn (TestRunner $t) => $t->same('requires-current-source-reprepare', $schemaRangeRowsPlan()['status']),
    'planner stat4 expression partial current source range rows schema reason' => static fn (TestRunner $t) => $t->same(['schema-cookie'], $schemaRangeRowsPlan()['rangeRowsSource']['replanReasons']),
    'planner stat4 expression partial current source range rows fresh prepared source' => static fn (TestRunner $t) => $t->same('prepared', $freshRangeRows()['selectedSource']),
    'planner stat4 expression partial current source range rows fresh rowids' => static fn (TestRunner $t) => $t->same([10, 11], $freshRangeRows()['matchedRowids']),
    'planner stat4 expression partial current source range rows no next remains ready' => static fn (TestRunner $t) => $t->same('stat4-expression-partial-current-source-range-rows-ready', $planRangeRows()['status']),
    'planner stat4 expression partial current source range rows invalid needed columns' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => $planRangeRows(null, null, null, null, [])),
    'planner stat4 expression partial current source range rows invalid rows' => static function (TestRunner $t) use ($currentRangeRows, $planRangeRows): void {
        $bad = $currentRangeRows();
        $bad['rows'][] = 'bad';
        $t->throws(InvalidArgumentException::class, static fn () => $planRangeRows(null, $bad));
    },
    'planner stat4 expression partial current source range rows invalid stat4 integer' => static function (TestRunner $t) use ($currentRangeRows, $planRangeRows): void {
        $bad = $currentRangeRows();
        $bad['indexes'][0]['stat4Samples'][0]['neq'] = 'bad';
        $t->throws(InvalidArgumentException::class, static fn () => $planRangeRows(null, $bad));
    },
    'planner stat4 expression partial current source range rows missing range' => static function (TestRunner $t) use ($planRangeRows, $eqRangeRows): void {
        $t->throws(InvalidArgumentException::class, static fn () => $planRangeRows(null, null, [$eqRangeRows('autoload', 'yes')]));
    },
];

foreach (range(1, 8) as $case) {
    $tests['planner stat4 expression partial current source range rows repeated stable range fence ' . $case] = static function (TestRunner $t) use ($baseRangeRows, $case): void {
        $plan = $baseRangeRows();
        $t->same(true, $plan['selectedPlan']['estimatedRows'] >= $case - 3);
    };
}

return $tests;

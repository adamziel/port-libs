<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan;

$term154 = static fn (string $column, string $operator, mixed $right = null): array => ['left' => ['column' => $column], 'operator' => $operator, 'right' => $right];
$exprEq154 = static fn (string $expression, mixed $right): array => ['left' => ['expression' => $expression], 'operator' => '=', 'right' => $right];
$exprIn154 = static fn (string $expression, array $values): array => ['left' => ['expression' => $expression], 'operator' => 'IN', 'values' => $values];
$exprBetween154 = static fn (string $expression, mixed $lower, mixed $upper): array => ['left' => ['expression' => $expression], 'operator' => 'BETWEEN', 'lower' => $lower, 'upper' => $upper];

$rows154 = static fn (): array => [
    ['rowid' => 1, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'SiteURL', 'option_value' => 'https://example.test', 'updated_at' => 10],
    ['rowid' => 2, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'Plugin_Cache', 'option_value' => 'warm', 'updated_at' => 40],
    ['rowid' => 3, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_cache', 'option_value' => 'fresh', 'updated_at' => 50],
    ['rowid' => 4, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_forms', 'option_value' => 'forms', 'updated_at' => 60],
    ['rowid' => 5, 'blog_id' => 1, 'autoload' => 'no', 'option_name' => 'plugin_cache', 'option_value' => 'lazy', 'updated_at' => 70],
    ['rowid' => 6, 'blog_id' => 2, 'autoload' => 'yes', 'option_name' => 'plugin_cache', 'option_value' => 'network', 'updated_at' => 80],
    ['rowid' => 7, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => null, 'option_value' => 'null-name', 'updated_at' => 90],
    ['rowid' => 8, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_seo', 'option_value' => 'seo', 'updated_at' => 100],
];

$prepared154 = static function (array $overrides = []) use ($rows154): array {
    return array_replace_recursive([
        'name' => 'prepared-wp-options-stat4-expression-partial-next154',
        'schemaCookie' => 1540,
        'stat4Generation' => 20,
        'rows' => array_slice($rows154(), 0, 5),
        'indexes' => [[
            'name' => 'idx_wp_options_lower_name_blog_autoload_stat4_next154',
            'rootPage' => 15401,
            'expression' => 'lower(option_name)',
            'expressionColumn' => '__expr_lower_option_name',
            'collation' => 'BINARY',
            'partialPredicateTerms' => [
                ['left' => ['column' => 'blog_id'], 'operator' => '=', 'right' => 1],
                ['left' => ['column' => 'autoload'], 'operator' => '=', 'right' => 'yes'],
                ['left' => ['column' => 'option_name'], 'operator' => 'IS NOT NULL'],
            ],
            'coveringColumns' => ['option_name'],
            'stat4Samples' => [
                ['neq' => '1 1', 'nlt' => '0 0', 'ndlt' => '0 0', 'sample' => ['plugin_cache', 2]],
                ['neq' => '1 1', 'nlt' => '1 1', 'ndlt' => '1 1', 'sample' => ['plugin_forms', 4]],
                ['neq' => '1 1', 'nlt' => '2 2', 'ndlt' => '2 2', 'sample' => ['siteurl', 1]],
            ],
        ], [
            'name' => 'idx_wp_options_plain_name_next154',
            'rootPage' => 15402,
            'expression' => 'option_name',
            'expressionColumn' => 'option_name',
            'collation' => 'BINARY',
            'partialPredicateTerms' => [],
            'coveringColumns' => ['option_name'],
            'stat4Samples' => [
                ['neq' => '9', 'nlt' => '0', 'ndlt' => '0', 'sample' => ['plugin_cache', 2]],
            ],
        ]],
    ], $overrides);
};

$current154 = static function (array $overrides = []) use ($rows154): array {
    return array_replace_recursive([
        'name' => 'current-wp-options-stat4-expression-partial-next154',
        'schemaCookie' => 1544,
        'stat4Generation' => 24,
        'rows' => $rows154(),
        'indexes' => [[
            'name' => 'idx_wp_options_lower_name_blog_autoload_stat4_next154',
            'rootPage' => 15431,
            'expression' => 'lower(option_name)',
            'expressionColumn' => '__expr_lower_option_name',
            'collation' => 'BINARY',
            'partialPredicateTerms' => [
                ['left' => ['column' => 'blog_id'], 'operator' => '=', 'right' => 1],
                ['left' => ['column' => 'autoload'], 'operator' => '=', 'right' => 'yes'],
                ['left' => ['column' => 'option_name'], 'operator' => 'IS NOT NULL'],
            ],
            'coveringColumns' => ['option_name'],
            'stat4Samples' => [
                ['neq' => '2 2', 'nlt' => '0 0', 'ndlt' => '0 0', 'sample' => ['plugin_cache', 2]],
                ['neq' => '1 1', 'nlt' => '2 2', 'ndlt' => '1 1', 'sample' => ['plugin_forms', 4]],
                ['neq' => '1 1', 'nlt' => '3 3', 'ndlt' => '2 2', 'sample' => ['plugin_seo', 8]],
                ['neq' => '1 1', 'nlt' => '4 4', 'ndlt' => '3 3', 'sample' => ['siteurl', 1]],
            ],
        ], [
            'name' => 'idx_wp_options_plain_name_next154',
            'rootPage' => 15402,
            'expression' => 'option_name',
            'expressionColumn' => 'option_name',
            'collation' => 'BINARY',
            'partialPredicateTerms' => [],
            'coveringColumns' => ['option_name'],
            'stat4Samples' => [
                ['neq' => '9', 'nlt' => '0', 'ndlt' => '0', 'sample' => ['plugin_cache', 2]],
            ],
        ]],
    ], $overrides);
};

$query154 = static fn (): array => [
    $exprEq154('LOWER( option_name )', 'plugin_cache'),
    $term154('blog_id', '=', 1),
    $term154('autoload', '=', 'yes'),
    $term154('option_name', 'IS NOT NULL'),
];
$needed154 = ['option_name', 'option_value', 'updated_at'];
$plan154 = static fn (?array $prepared = null, ?array $current = null, ?array $terms = null, ?array $needed = null): array => SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan::materializeNext154(
    $prepared ?? $prepared154(),
    $current ?? $current154(),
    $terms ?? $query154(),
    $needed ?? $needed154,
);
$fresh154 = static function () use ($prepared154, $plan154): array {
    $source = $prepared154();

    return $plan154($source, $source);
};
$inPlan154 = static fn (): array => $plan154(null, null, [
    $exprIn154('lower(option_name)', ['plugin_cache', 'plugin_seo']),
    $term154('blog_id', '=', 1),
    $term154('autoload', '=', 'yes'),
    $term154('option_name', 'IS NOT NULL'),
]);
$betweenPlan154 = static fn (): array => $plan154(null, null, [
    $exprBetween154('lower(option_name)', 'plugin_cache', 'plugin_seo'),
    $term154('blog_id', '=', 1),
    $term154('autoload', '=', 'yes'),
    $term154('option_name', 'IS NOT NULL'),
]);
$unproved154 = static fn (): array => $plan154(null, null, [
    $exprEq154('lower(option_name)', 'plugin_cache'),
    $term154('blog_id', '=', 1),
    $term154('autoload', '=', 'no'),
    $term154('option_name', 'IS NOT NULL'),
]);
$covered154 = static function () use ($current154, $plan154): array {
    $current = $current154();
    $current['indexes'][0]['coveringColumns'] = ['option_name', 'option_value', 'updated_at'];

    return $plan154(null, $current);
};

return [
    'planner stat4 expression partial current source next154 status ready' => static fn (TestRunner $t) => $t->same('stat4-expression-partial-current-source-next154-ready', $plan154()['status']),
    'planner stat4 expression partial current source next154 selects current' => static fn (TestRunner $t) => $t->same('current', $plan154()['selectedSource']),
    'planner stat4 expression partial current source next154 stale prepared' => static fn (TestRunner $t) => $t->same(true, $plan154()['stalePreparedStatement']),
    'planner stat4 expression partial current source next154 reprepare required' => static fn (TestRunner $t) => $t->same(true, $plan154()['reprepareRequired']),
    'planner stat4 expression partial current source next154 schema changed' => static fn (TestRunner $t) => $t->same(true, $plan154()['schemaCookieChanged']),
    'planner stat4 expression partial current source next154 stat4 changed' => static fn (TestRunner $t) => $t->same(true, $plan154()['stat4GenerationChanged']),
    'planner stat4 expression partial current source next154 index signature changed' => static fn (TestRunner $t) => $t->same(true, $plan154()['indexSignatureChanged']),
    'planner stat4 expression partial current source next154 selected index' => static fn (TestRunner $t) => $t->same('idx_wp_options_lower_name_blog_autoload_stat4_next154', $plan154()['selectedPlan']['name']),
    'planner stat4 expression partial current source next154 root page' => static fn (TestRunner $t) => $t->same(15431, $plan154()['selectedPlan']['rootPage']),
    'planner stat4 expression partial current source next154 expression' => static fn (TestRunner $t) => $t->same('lower(option_name)', $plan154()['selectedPlan']['expression']),
    'planner stat4 expression partial current source next154 expression column' => static fn (TestRunner $t) => $t->same('__expr_lower_option_name', $plan154()['selectedPlan']['expressionColumn']),
    'planner stat4 expression partial current source next154 partial implied' => static fn (TestRunner $t) => $t->same(true, $plan154()['selectedPlan']['partialPredicateImplied']),
    'planner stat4 expression partial current source next154 stat4 used' => static fn (TestRunner $t) => $t->same(true, $plan154()['selectedPlan']['stat4Used']),
    'planner stat4 expression partial current source next154 matched sample count' => static fn (TestRunner $t) => $t->same(1, $plan154()['selectedPlan']['stat4MatchedSamples']),
    'planner stat4 expression partial current source next154 stat4 estimate' => static fn (TestRunner $t) => $t->same(2, $plan154()['selectedPlan']['stat4Estimate']),
    'planner stat4 expression partial current source next154 estimated rows' => static fn (TestRunner $t) => $t->same(2, $plan154()['selectedPlan']['estimatedRows']),
    'planner stat4 expression partial current source next154 noncovering cost includes seek' => static fn (TestRunner $t) => $t->same(14, $plan154()['selectedPlan']['estimatedCost']),
    'planner stat4 expression partial current source next154 matched stat4 key' => static fn (TestRunner $t) => $t->same(['plugin_cache'], $plan154()['selectedPlan']['matchedStat4Keys']),
    'planner stat4 expression partial current source next154 matched stat4 rowid' => static fn (TestRunner $t) => $t->same([2], $plan154()['selectedPlan']['matchedStat4Rowids']),
    'planner stat4 expression partial current source next154 matched row count' => static fn (TestRunner $t) => $t->same(2, $plan154()['selectedPlan']['matchedRowCount']),
    'planner stat4 expression partial current source next154 matched rowids' => static fn (TestRunner $t) => $t->same([2, 3], $plan154()['selectedPlan']['matchedRowids']),
    'planner stat4 expression partial current source next154 row stream signature' => static fn (TestRunner $t) => $t->same(64, strlen($plan154()['selectedPlan']['rowStreamSignature'])),
    'planner stat4 expression partial current source next154 first row payload' => static fn (TestRunner $t) => $t->same('warm', $plan154()['matchedRows'][0]['payload']['option_value']),
    'planner stat4 expression partial current source next154 second row payload' => static fn (TestRunner $t) => $t->same(50, $plan154()['matchedRows'][1]['payload']['updated_at']),
    'planner stat4 expression partial current source next154 excludes autoload no' => static fn (TestRunner $t) => $t->same(false, in_array(5, $plan154()['selectedPlan']['matchedRowids'], true)),
    'planner stat4 expression partial current source next154 excludes blog two' => static fn (TestRunner $t) => $t->same(false, in_array(6, $plan154()['selectedPlan']['matchedRowids'], true)),
    'planner stat4 expression partial current source next154 excludes null expression' => static fn (TestRunner $t) => $t->same(false, in_array(7, $plan154()['selectedPlan']['matchedRowids'], true)),
    'planner stat4 expression partial current source next154 current next first row' => static fn (TestRunner $t) => $t->same(3, $plan154()['currentNextRows'][0]['next']['rowid']),
    'planner stat4 expression partial current source next154 current next eof' => static fn (TestRunner $t) => $t->same(null, $plan154()['currentNextRows'][1]['next']),
    'planner stat4 expression partial current source next154 stat4 current next count' => static fn (TestRunner $t) => $t->same(4, count($plan154()['selectedPlan']['stat4CurrentNext'])),
    'planner stat4 expression partial current source next154 stat4 current next first' => static fn (TestRunner $t) => $t->same('plugin_forms', $plan154()['selectedPlan']['stat4CurrentNext'][0]['next']['key']),
    'planner stat4 expression partial current source next154 stat4 matched current next eof' => static fn (TestRunner $t) => $t->same(null, $plan154()['selectedPlan']['stat4MatchedCurrentNext'][0]['next']),
    'planner stat4 expression partial current source next154 table lookup required' => static fn (TestRunner $t) => $t->same(true, $plan154()['tableLookupRequired']),
    'planner stat4 expression partial current source next154 residual predicate required' => static fn (TestRunner $t) => $t->same(true, $plan154()['residualPredicateRequired']),
    'planner stat4 expression partial current source next154 cursor open read' => static fn (TestRunner $t) => $t->same('OpenRead', $plan154()['cursorProgram'][0]['opcode']),
    'planner stat4 expression partial current source next154 cursor stat4 seek' => static fn (TestRunner $t) => $t->same('SeekStat4Expression', $plan154()['cursorProgram'][1]['opcode']),
    'planner stat4 expression partial current source next154 cursor deferred seek' => static fn (TestRunner $t) => $t->same('DeferredSeek', $plan154()['cursorProgram'][3]['opcode']),
    'planner stat4 expression partial current source next154 cursor reads table' => static fn (TestRunner $t) => $t->same(['table', 'table', 'table'], array_column(array_slice($plan154()['cursorProgram'], 4, 3), 'source')),
    'planner stat4 expression partial current source next154 cursor result count' => static fn (TestRunner $t) => $t->same(2, $plan154()['cursorProgram'][7]['rowCount']),
    'planner stat4 expression partial current source next154 cursor next' => static fn (TestRunner $t) => $t->same('Next', $plan154()['cursorProgram'][8]['opcode']),
    'planner stat4 expression partial current source next154 fence cookie' => static fn (TestRunner $t) => $t->same(1544, $plan154()['stat4Fence']['schemaCookie']),
    'planner stat4 expression partial current source next154 fence stat4 generation' => static fn (TestRunner $t) => $t->same(24, $plan154()['stat4Fence']['stat4Generation']),
    'planner stat4 expression partial current source next154 fence expression' => static fn (TestRunner $t) => $t->same('lower(option_name)', $plan154()['stat4Fence']['expressionSignature']),
    'planner stat4 expression partial current source next154 fence signatures' => static fn (TestRunner $t) => $t->same([64, 64, 64, 64], array_map('strlen', [$plan154()['stat4Fence']['sourceSignature'], $plan154()['stat4Fence']['partialPredicateSignature'], $plan154()['stat4Fence']['stat4SampleSignature'], $plan154()['stat4Fence']['rowStreamSignature']])),
    'planner stat4 expression partial current source next154 prepared summary' => static fn (TestRunner $t) => $t->same(15401, $plan154()['preparedSource']['rootPage']),
    'planner stat4 expression partial current source next154 current summary usable' => static fn (TestRunner $t) => $t->same(true, $plan154()['currentSource']['usable']),
    'planner stat4 expression partial current source next154 detail' => static fn (TestRunner $t) => $t->contains('STAT4 EXPRESSION PARTIAL CURRENT SOURCE NEXT154', $plan154()['detail']),
    'planner stat4 expression partial current source next154 dependency marker' => static fn (TestRunner $t) => $t->same(['sqlite-sqlplanner-stat4-expression-partial-current-source-next154'], $plan154()['dependencies']),
    'planner stat4 expression partial current source next154 dependency closure' => static fn (TestRunner $t) => $t->contains('no new support component needed', $plan154()['dependency_closure']),
    'planner stat4 expression partial current source next154 non overlap' => static fn (TestRunner $t) => $t->contains('non-covering partial expression index', $plan154()['non_overlap']),
    'planner stat4 expression partial current source next154 fresh reuses prepared' => static fn (TestRunner $t) => $t->same('prepared', $fresh154()['selectedSource']),
    'planner stat4 expression partial current source next154 fresh rowids' => static fn (TestRunner $t) => $t->same([2, 3], $fresh154()['selectedPlan']['matchedRowids']),
    'planner stat4 expression partial current source next154 in status ready' => static fn (TestRunner $t) => $t->same('stat4-expression-partial-current-source-next154-ready', $inPlan154()['status']),
    'planner stat4 expression partial current source next154 in rowids' => static fn (TestRunner $t) => $t->same([2, 3, 8], $inPlan154()['selectedPlan']['matchedRowids']),
    'planner stat4 expression partial current source next154 in stat4 keys' => static fn (TestRunner $t) => $t->same(['plugin_cache', 'plugin_seo'], $inPlan154()['selectedPlan']['matchedStat4Keys']),
    'planner stat4 expression partial current source next154 between rowids' => static fn (TestRunner $t) => $t->same([2, 3, 4, 8], $betweenPlan154()['selectedPlan']['matchedRowids']),
    'planner stat4 expression partial current source next154 between stat4 estimate' => static fn (TestRunner $t) => $t->same(4, $betweenPlan154()['selectedPlan']['stat4Estimate']),
    'planner stat4 expression partial current source next154 unproved falls back' => static fn (TestRunner $t) => $t->same('requires-next-stage', $unproved154()['status']),
    'planner stat4 expression partial current source next154 covered removes table lookup' => static fn (TestRunner $t) => $t->same(false, $covered154()['tableLookupRequired']),
    'planner stat4 expression partial current source next154 covered no deferred seek' => static fn (TestRunner $t) => $t->same('Noop', $covered154()['cursorProgram'][3]['opcode']),
    'planner stat4 expression partial current source next154 invalid empty terms' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => $plan154(null, null, [])),
    'planner stat4 expression partial current source next154 invalid needed columns' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => $plan154(null, null, null, [])),
    'planner stat4 expression partial current source next154 invalid stat4 sample' => static function (TestRunner $t) use ($current154, $plan154): void {
        $bad = $current154();
        $bad['indexes'][0]['stat4Samples'][0]['sample'] = [];
        $t->throws(InvalidArgumentException::class, static fn () => $plan154(null, $bad));
    },
    'planner stat4 expression partial current source next154 invalid row list' => static function (TestRunner $t) use ($current154, $plan154): void {
        $bad = $current154(['rows' => ['not-list' => []]]);
        $t->throws(InvalidArgumentException::class, static fn () => $plan154(null, $bad));
    },
];

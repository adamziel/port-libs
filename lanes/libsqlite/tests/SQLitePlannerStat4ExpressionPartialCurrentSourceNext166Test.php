<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan;

$term166 = static fn (string $column, string $operator, mixed $right = null): array => ['left' => ['column' => $column], 'operator' => $operator, 'right' => $right];
$exprIn166 = static fn (string $expression, array $values): array => ['left' => ['expression' => $expression], 'operator' => 'IN', 'values' => $values];

$prepared166 = static fn (): array => [
    'name' => 'prepared-wp-options-stat4-expression-partial-next166',
    'schemaCookie' => 1660,
    'stat4Generation' => 41,
    'rows' => [
        ['rowid' => 10, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'Plugin_Cache', 'option_value' => 'old-cache'],
        ['rowid' => 11, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'Plugin_Forms', 'option_value' => 'old-forms'],
        ['rowid' => 12, 'blog_id' => 2, 'autoload' => 'yes', 'option_name' => 'Plugin_Cache', 'option_value' => 'network-cache'],
        ['rowid' => 13, 'blog_id' => 1, 'autoload' => 'no', 'option_name' => 'Plugin_Forms', 'option_value' => 'lazy-forms'],
        ['rowid' => 14, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => null, 'option_value' => 'null-name'],
    ],
    'indexes' => [[
        'name' => 'idx_wp_options_lower_name_blog_partial_next166',
        'rootPage' => 16601,
        'expression' => 'lower(option_name)',
        'expressionColumn' => '__expr_lower_option_name',
        'collation' => 'BINARY',
        'partialPredicateTerms' => [
            ['left' => ['column' => 'autoload'], 'operator' => '=', 'right' => 'yes'],
            ['left' => ['column' => 'option_name'], 'operator' => 'IS NOT NULL'],
        ],
        'coveringColumns' => ['option_name'],
        'stat4Samples' => [
            ['neq' => '1', 'nlt' => '0', 'ndlt' => '0', 'sample' => ['plugin_cache', 10]],
            ['neq' => '1', 'nlt' => '1', 'ndlt' => '1', 'sample' => ['plugin_forms', 11]],
        ],
    ]],
];

$current166 = static function () use ($prepared166): array {
    $source = $prepared166();
    $source['name'] = 'current-wp-options-stat4-expression-partial-next166';
    $source['schemaCookie'] = 1664;
    $source['stat4Generation'] = 47;
    $source['rows'] = [
        ['rowid' => 20, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'Plugin_Cache', 'option_value' => 'current-cache-a'],
        ['rowid' => 21, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_cache', 'option_value' => 'current-cache-b'],
        ['rowid' => 22, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'Plugin_Forms', 'option_value' => 'current-forms'],
        ['rowid' => 23, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'Plugin_Seo', 'option_value' => 'current-seo'],
        ['rowid' => 24, 'blog_id' => 2, 'autoload' => 'yes', 'option_name' => 'Plugin_Cache', 'option_value' => 'network-cache'],
        ['rowid' => 25, 'blog_id' => 1, 'autoload' => 'no', 'option_name' => 'Plugin_Forms', 'option_value' => 'lazy-forms'],
        ['rowid' => 26, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => null, 'option_value' => 'null-name'],
        ['rowid' => 27, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'siteurl', 'option_value' => 'https://example.test'],
    ];
    $source['indexes'][0]['rootPage'] = 16641;
    $source['indexes'][0]['partialPredicateTerms'][] = ['left' => ['column' => 'blog_id'], 'operator' => '=', 'right' => 1];
    $source['indexes'][0]['stat4Samples'] = [
        ['neq' => '2', 'nlt' => '0', 'ndlt' => '0', 'sample' => ['plugin_cache', 20]],
        ['neq' => '1', 'nlt' => '2', 'ndlt' => '1', 'sample' => ['plugin_forms', 22]],
        ['neq' => '1', 'nlt' => '3', 'ndlt' => '2', 'sample' => ['plugin_seo', 23]],
        ['neq' => '1', 'nlt' => '4', 'ndlt' => '3', 'sample' => ['siteurl', 27]],
    ];

    return $source;
};

$query166 = static fn (): array => [
    $exprIn166('LOWER( option_name )', ['plugin_cache', 'plugin_forms', 'plugin_seo']),
    $term166('autoload', '=', 'yes'),
    $term166('blog_id', '=', 1),
    $term166('option_name', 'IS NOT NULL'),
];
$needed166 = ['option_name', 'option_value', 'blog_id'];

$plan166 = static fn (?array $prepared = null, ?array $current = null, ?array $query = null, ?array $next = null): array => SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan::materializeMultiValueInBucketFence(
    $prepared ?? $prepared166(),
    $current ?? $current166(),
    $query ?? $query166(),
    $GLOBALS['needed166'],
    $next,
);

$GLOBALS['needed166'] = $needed166;

$same166 = static fn (): array => $plan166($current166(), $current166());
$nextFresh166 = static fn (): array => $plan166(null, null, null, $current166());
$nextStale166 = static function () use ($current166, $plan166): array {
    $next = $current166();
    $next['name'] = 'next-wp-options-stat4-expression-partial-next166';
    $next['stat4Generation'] = 48;
    $next['rows'][] = ['rowid' => 28, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'Plugin_Cache', 'option_value' => 'late-cache'];

    return $plan166(null, null, null, $next);
};
$missingStat4166 = static function () use ($query166, $plan166): array {
    $query = $query166();
    $query[0]['values'][] = 'missing_plugin';

    return $plan166(null, null, $query);
};
$noDelta166 = static function () use ($prepared166, $current166, $plan166): array {
    $prepared = $prepared166();
    $current = $current166();
    $prepared['indexes'][0]['partialPredicateTerms'] = $current['indexes'][0]['partialPredicateTerms'];

    return $plan166($prepared, $current);
};
$equality166 = static function () use ($query166, $plan166): array {
    $query = $query166();
    $query[0] = ['left' => ['expression' => 'lower(option_name)'], 'operator' => '=', 'right' => 'plugin_cache'];

    return $plan166(null, null, $query);
};
$missingBlogProof166 = static function () use ($query166, $plan166): array {
    $query = array_values(array_filter($query166(), static fn (array $term): bool => ($term['left']['column'] ?? null) !== 'blog_id'));

    return $plan166(null, null, $query);
};

return [
    'planner stat4 expression partial current source next166 status ready' => static fn (TestRunner $t) => $t->same('stat4-expression-partial-current-source-next166-ready', $plan166()['status']),
    'planner stat4 expression partial current source next166 selects current' => static fn (TestRunner $t) => $t->same('current', $plan166()['selectedSource']),
    'planner stat4 expression partial current source next166 stale prepared' => static fn (TestRunner $t) => $t->same(true, $plan166()['stalePreparedStatement']),
    'planner stat4 expression partial current source next166 reprepare required' => static fn (TestRunner $t) => $t->same(true, $plan166()['reprepareRequired']),
    'planner stat4 expression partial current source next166 schema changed' => static fn (TestRunner $t) => $t->same(true, $plan166()['schemaCookieChanged']),
    'planner stat4 expression partial current source next166 stat4 changed' => static fn (TestRunner $t) => $t->same(true, $plan166()['stat4GenerationChanged']),
    'planner stat4 expression partial current source next166 index changed' => static fn (TestRunner $t) => $t->same(true, $plan166()['indexSignatureChanged']),
    'planner stat4 expression partial current source next166 partial changed' => static fn (TestRunner $t) => $t->same(true, $plan166()['partialPredicateChanged']),
    'planner stat4 expression partial current source next166 in values' => static fn (TestRunner $t) => $t->same(['plugin_cache', 'plugin_forms', 'plugin_seo'], $plan166()['inValues']),
    'planner stat4 expression partial current source next166 in value count' => static fn (TestRunner $t) => $t->same(3, $plan166()['inValueCount']),
    'planner stat4 expression partial current source next166 selected index' => static fn (TestRunner $t) => $t->same('idx_wp_options_lower_name_blog_partial_next166', $plan166()['selectedPlan']['name']),
    'planner stat4 expression partial current source next166 selected root' => static fn (TestRunner $t) => $t->same(16641, $plan166()['selectedPlan']['rootPage']),
    'planner stat4 expression partial current source next166 selected usable' => static fn (TestRunner $t) => $t->same(true, $plan166()['selectedPlan']['usable']),
    'planner stat4 expression partial current source next166 selected partial proof' => static fn (TestRunner $t) => $t->same(true, $plan166()['selectedPlan']['partialPredicateImplied']),
    'planner stat4 expression partial current source next166 selected stat4 used' => static fn (TestRunner $t) => $t->same(true, $plan166()['selectedPlan']['stat4Used']),
    'planner stat4 expression partial current source next166 selected operator' => static fn (TestRunner $t) => $t->same('IN', $plan166()['selectedPlan']['constraint']['operator']),
    'planner stat4 expression partial current source next166 stat4 samples matched' => static fn (TestRunner $t) => $t->same(3, $plan166()['selectedPlan']['stat4MatchedSamples']),
    'planner stat4 expression partial current source next166 stat4 estimate' => static fn (TestRunner $t) => $t->same(4, $plan166()['selectedPlan']['stat4Estimate']),
    'planner stat4 expression partial current source next166 matched row count' => static fn (TestRunner $t) => $t->same(4, $plan166()['selectedPlan']['matchedRowCount']),
    'planner stat4 expression partial current source next166 matched rowids' => static fn (TestRunner $t) => $t->same([20, 21, 22, 23], $plan166()['selectedPlan']['matchedRowids']),
    'planner stat4 expression partial current source next166 bucket count' => static fn (TestRunner $t) => $t->same(3, $plan166()['inBucketCount']),
    'planner stat4 expression partial current source next166 bucket rowids' => static fn (TestRunner $t) => $t->same([20, 21, 22, 23], $plan166()['inBucketRowids']),
    'planner stat4 expression partial current source next166 first bucket key' => static fn (TestRunner $t) => $t->same('plugin_cache', $plan166()['inBuckets'][0]['key']),
    'planner stat4 expression partial current source next166 first bucket rowids' => static fn (TestRunner $t) => $t->same([20, 21], $plan166()['inBuckets'][0]['rowids']),
    'planner stat4 expression partial current source next166 first bucket row count' => static fn (TestRunner $t) => $t->same(2, $plan166()['inBuckets'][0]['rowCount']),
    'planner stat4 expression partial current source next166 first bucket exact' => static fn (TestRunner $t) => $t->same(true, $plan166()['inBuckets'][0]['exact']),
    'planner stat4 expression partial current source next166 first bucket stat4 rowid' => static fn (TestRunner $t) => $t->same(20, $plan166()['inBuckets'][0]['stat4Rowid']),
    'planner stat4 expression partial current source next166 second bucket key' => static fn (TestRunner $t) => $t->same('plugin_forms', $plan166()['inBuckets'][1]['key']),
    'planner stat4 expression partial current source next166 third bucket key' => static fn (TestRunner $t) => $t->same('plugin_seo', $plan166()['inBuckets'][2]['key']),
    'planner stat4 expression partial current source next166 no missing stat4 values' => static fn (TestRunner $t) => $t->same([], $plan166()['missingStat4InValues']),
    'planner stat4 expression partial current source next166 current only rowids' => static fn (TestRunner $t) => $t->same([20, 21, 22, 23, 24, 25, 26, 27], $plan166()['currentSourceOnlyRowids']),
    'planner stat4 expression partial current source next166 blocks stale prepared rows' => static fn (TestRunner $t) => $t->same([12, 13, 14], $plan166()['stalePreparedRowidsBlockedByPartialDelta']),
    'planner stat4 expression partial current source next166 excludes network row' => static fn (TestRunner $t) => $t->same(false, in_array(24, $plan166()['selectedPlan']['matchedRowids'], true)),
    'planner stat4 expression partial current source next166 excludes autoload no' => static fn (TestRunner $t) => $t->same(false, in_array(25, $plan166()['selectedPlan']['matchedRowids'], true)),
    'planner stat4 expression partial current source next166 excludes null name' => static fn (TestRunner $t) => $t->same(false, in_array(26, $plan166()['selectedPlan']['matchedRowids'], true)),
    'planner stat4 expression partial current source next166 excludes outside in value' => static fn (TestRunner $t) => $t->same(false, in_array(27, $plan166()['selectedPlan']['matchedRowids'], true)),
    'planner stat4 expression partial current source next166 payload preserves case' => static fn (TestRunner $t) => $t->same('Plugin_Cache', $plan166()['matchedRows'][0]['payload']['option_name']),
    'planner stat4 expression partial current source next166 payload value' => static fn (TestRunner $t) => $t->same('current-forms', $plan166()['matchedRows'][2]['payload']['option_value']),
    'planner stat4 expression partial current source next166 current next first rowid' => static fn (TestRunner $t) => $t->same(21, $plan166()['currentNextRows'][0]['next']['rowid']),
    'planner stat4 expression partial current source next166 current next eof' => static fn (TestRunner $t) => $t->same(null, $plan166()['currentNextRows'][3]['next']),
    'planner stat4 expression partial current source next166 cursor opens index' => static fn (TestRunner $t) => $t->same('OpenRead', $plan166()['cursorProgram'][0]['opcode']),
    'planner stat4 expression partial current source next166 cursor fences delta' => static fn (TestRunner $t) => $t->same('FencePartialPredicateDelta', $plan166()['cursorProgram'][1]['opcode']),
    'planner stat4 expression partial current source next166 cursor rewinds in list' => static fn (TestRunner $t) => $t->same(['plugin_cache', 'plugin_forms', 'plugin_seo'], $plan166()['cursorProgram'][2]['values']),
    'planner stat4 expression partial current source next166 cursor seeks in rowids' => static fn (TestRunner $t) => $t->same([20, 21, 22, 23], $plan166()['cursorProgram'][3]['rowids']),
    'planner stat4 expression partial current source next166 cursor deferred table' => static fn (TestRunner $t) => $t->same('DeferredSeek', $plan166()['cursorProgram'][4]['opcode']),
    'planner stat4 expression partial current source next166 cursor result count' => static fn (TestRunner $t) => $t->same(4, $plan166()['cursorProgram'][8]['rowCount']),
    'planner stat4 expression partial current source next166 cursor next in list' => static fn (TestRunner $t) => $t->same('NextInList', $plan166()['cursorProgram'][9]['opcode']),
    'planner stat4 expression partial current source next166 selected ready flag' => static fn (TestRunner $t) => $t->same(true, $plan166()['selectedPlan']['next166Ready']),
    'planner stat4 expression partial current source next166 selected bucket count' => static fn (TestRunner $t) => $t->same(3, $plan166()['selectedPlan']['next166InBucketCount']),
    'planner stat4 expression partial current source next166 selected next admitted' => static fn (TestRunner $t) => $t->same(true, $plan166()['selectedPlan']['next166NextSourceAdmitted']),
    'planner stat4 expression partial current source next166 no next summary' => static fn (TestRunner $t) => $t->same(null, $plan166()['nextSource']),
    'planner stat4 expression partial current source next166 next fresh admitted' => static fn (TestRunner $t) => $t->same(true, $nextFresh166()['nextSourceAdmitted']),
    'planner stat4 expression partial current source next166 next fresh reasons' => static fn (TestRunner $t) => $t->same([], $nextFresh166()['nextSource']['replanReasons']),
    'planner stat4 expression partial current source next166 next stale blocked' => static fn (TestRunner $t) => $t->same(false, $nextStale166()['nextSourceAdmitted']),
    'planner stat4 expression partial current source next166 next stale status' => static fn (TestRunner $t) => $t->same('requires-current-source-reprepare', $nextStale166()['status']),
    'planner stat4 expression partial current source next166 next stale reasons' => static fn (TestRunner $t) => $t->same(['stat4-generation', 'row-signature'], $nextStale166()['nextSource']['replanReasons']),
    'planner stat4 expression partial current source next166 fence value signature' => static fn (TestRunner $t) => $t->same(64, strlen($plan166()['stat4Fence']['next166InValueSignature'])),
    'planner stat4 expression partial current source next166 fence bucket signature' => static fn (TestRunner $t) => $t->same(64, strlen($plan166()['stat4Fence']['next166InBucketSignature'])),
    'planner stat4 expression partial current source next166 selected partial signature' => static fn (TestRunner $t) => $t->same(64, strlen($plan166()['selectedPlan']['next166PartialPredicateSignature'])),
    'planner stat4 expression partial current source next166 detail' => static fn (TestRunner $t) => $t->contains('MULTI-IN BUCKETS WITH PARTIAL DELTA', $plan166()['detail']),
    'planner stat4 expression partial current source next166 dependency marker' => static fn (TestRunner $t) => $t->true(in_array('sqlite-sqlplanner-stat4-expression-partial-current-source-next166', $plan166()['dependencies'], true)),
    'planner stat4 expression partial current source next166 dependency closure' => static fn (TestRunner $t) => $t->contains('no new support component needed', $plan166()['dependency_closure']),
    'planner stat4 expression partial current source next166 non overlap' => static fn (TestRunner $t) => $t->contains('multi-key IN admission', $plan166()['non_overlap']),
    'planner stat4 expression partial current source next166 same source requires delta' => static fn (TestRunner $t) => $t->same('requires-current-source-reprepare', $same166()['status']),
    'planner stat4 expression partial current source next166 no delta requires current source' => static fn (TestRunner $t) => $t->same('requires-current-source-reprepare', $noDelta166()['status']),
    'planner stat4 expression partial current source next166 missing stat4 value blocks ready' => static fn (TestRunner $t) => $t->same('requires-current-source-reprepare', $missingStat4166()['status']),
    'planner stat4 expression partial current source next166 missing stat4 records value' => static fn (TestRunner $t) => $t->same(['missing_plugin'], $missingStat4166()['missingStat4InValues']),
    'planner stat4 expression partial current source next166 equality operator not this slice' => static fn (TestRunner $t) => $t->same('requires-current-source-reprepare', $equality166()['status']),
    'planner stat4 expression partial current source next166 missing blog proof falls back' => static fn (TestRunner $t) => $t->same('requires-current-source-reprepare', $missingBlogProof166()['status']),
    'planner stat4 expression partial current source next166 validates partial terms' => static function (TestRunner $t) use ($current166, $plan166): void {
        $current = $current166();
        $current['indexes'][0]['partialPredicateTerms'] = ['bad'];
        $t->throws(InvalidArgumentException::class, static fn () => $plan166(null, $current));
    },
    'planner stat4 expression partial current source next166 validates row list' => static function (TestRunner $t) use ($current166, $plan166): void {
        $current = $current166();
        $current['rows'][] = 'bad';
        $t->throws(InvalidArgumentException::class, static fn () => $plan166(null, $current));
    },
];

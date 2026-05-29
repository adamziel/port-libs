<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan;

$term162 = static fn (string $column, string $operator, mixed $right = null): array => ['left' => ['column' => $column], 'operator' => $operator, 'right' => $right];
$exprTerm162 = static fn (string $expression, string $operator, mixed $right = null): array => ['left' => ['expression' => $expression], 'operator' => $operator, 'right' => $right];

$prepared162 = static fn (): array => [
    'name' => 'prepared-wp-options-stat4-expression-partial-next162',
    'schemaCookie' => 1620,
    'stat4Generation' => 31,
    'rows' => [
        ['rowid' => 10, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'Plugin_Cache', 'option_value' => 'old-cache-a'],
        ['rowid' => 11, 'blog_id' => 2, 'autoload' => 'yes', 'option_name' => 'plugin_cache', 'option_value' => 'network-cache'],
        ['rowid' => 12, 'blog_id' => 1, 'autoload' => 'no', 'option_name' => 'plugin_cache', 'option_value' => 'lazy-cache'],
        ['rowid' => 13, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => null, 'option_value' => 'null-name'],
    ],
    'indexes' => [[
        'name' => 'idx_wp_options_lower_name_blog_partial_next162',
        'rootPage' => 16201,
        'expression' => 'lower(option_name)',
        'expressionColumn' => '__expr_lower_option_name',
        'collation' => 'BINARY',
        'partialPredicateTerms' => [
            ['left' => ['column' => 'autoload'], 'operator' => '=', 'right' => 'yes'],
            ['left' => ['column' => 'option_name'], 'operator' => 'IS NOT NULL'],
        ],
        'coveringColumns' => ['option_name'],
        'stat4Samples' => [
            ['neq' => '2', 'nlt' => '0', 'ndlt' => '0', 'sample' => ['plugin_cache', 10]],
            ['neq' => '1', 'nlt' => '2', 'ndlt' => '1', 'sample' => ['plugin_forms', 20]],
        ],
    ]],
];

$current162 = static function () use ($prepared162): array {
    $source = $prepared162();
    $source['name'] = 'current-wp-options-stat4-expression-partial-next162';
    $source['schemaCookie'] = 1624;
    $source['stat4Generation'] = 37;
    $source['rows'] = [
        ['rowid' => 20, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'Plugin_Cache', 'option_value' => 'current-cache-a'],
        ['rowid' => 21, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_cache', 'option_value' => 'current-cache-b'],
        ['rowid' => 22, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'PLUGIN_CACHE', 'option_value' => 'current-cache-c'],
        ['rowid' => 23, 'blog_id' => 2, 'autoload' => 'yes', 'option_name' => 'plugin_cache', 'option_value' => 'network-cache'],
        ['rowid' => 24, 'blog_id' => 1, 'autoload' => 'no', 'option_name' => 'plugin_cache', 'option_value' => 'lazy-cache'],
        ['rowid' => 25, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_forms', 'option_value' => 'forms'],
        ['rowid' => 26, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => null, 'option_value' => 'null-name'],
    ];
    $source['indexes'][0]['rootPage'] = 16241;
    $source['indexes'][0]['partialPredicateTerms'][] = ['left' => ['column' => 'blog_id'], 'operator' => '=', 'right' => 1];
    $source['indexes'][0]['stat4Samples'] = [
        ['neq' => '3', 'nlt' => '0', 'ndlt' => '0', 'sample' => ['plugin_cache', 20]],
        ['neq' => '1', 'nlt' => '3', 'ndlt' => '1', 'sample' => ['plugin_forms', 25]],
        ['neq' => '1', 'nlt' => '4', 'ndlt' => '2', 'sample' => ['siteurl', 30]],
    ];

    return $source;
};

$query162 = static fn (): array => [
    $exprTerm162('lower(option_name)', '=', 'plugin_cache'),
    $term162('autoload', '=', 'yes'),
    $term162('blog_id', '=', 1),
    $term162('option_name', 'IS NOT NULL'),
];
$needed162 = ['option_name', 'option_value', 'blog_id'];

$plan162 = static fn (?array $prepared = null, ?array $current = null, ?array $query = null, ?array $next = null): array => SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan::materializeNext162(
    $prepared ?? $prepared162(),
    $current ?? $current162(),
    $query ?? $query162(),
    $GLOBALS['needed162'],
    $next,
);

$GLOBALS['needed162'] = $needed162;

$same162 = static fn (): array => $plan162($current162(), $current162());
$nextFresh162 = static fn (): array => $plan162(null, null, null, $current162());
$nextStale162 = static function () use ($current162, $plan162): array {
    $next = $current162();
    $next['name'] = 'next-wp-options-stat4-expression-partial-next162';
    $next['schemaCookie'] = 1625;
    $next['stat4Generation'] = 38;
    $next['rows'][] = ['rowid' => 27, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_cache', 'option_value' => 'late-cache'];

    return $plan162(null, null, null, $next);
};
$noDelta162 = static function () use ($prepared162, $current162, $plan162): array {
    $prepared = $prepared162();
    $current = $current162();
    $prepared['indexes'][0]['partialPredicateTerms'] = $current['indexes'][0]['partialPredicateTerms'];

    return $plan162($prepared, $current);
};
$missingBlogProof162 = static function () use ($query162, $plan162): array {
    $query = array_values(array_filter($query162(), static fn (array $term): bool => ($term['left']['column'] ?? null) !== 'blog_id'));

    return $plan162(null, null, $query);
};

$tests = [
    'planner stat4 expression partial current source next162 status ready' => static fn (TestRunner $t) => $t->same('stat4-expression-partial-current-source-next162-ready', $plan162()['status']),
    'planner stat4 expression partial current source next162 selects current' => static fn (TestRunner $t) => $t->same('current', $plan162()['selectedSource']),
    'planner stat4 expression partial current source next162 stale prepared' => static fn (TestRunner $t) => $t->same(true, $plan162()['stalePreparedStatement']),
    'planner stat4 expression partial current source next162 reprepare required' => static fn (TestRunner $t) => $t->same(true, $plan162()['reprepareRequired']),
    'planner stat4 expression partial current source next162 schema changed' => static fn (TestRunner $t) => $t->same(true, $plan162()['schemaCookieChanged']),
    'planner stat4 expression partial current source next162 stat4 changed' => static fn (TestRunner $t) => $t->same(true, $plan162()['stat4GenerationChanged']),
    'planner stat4 expression partial current source next162 index changed' => static fn (TestRunner $t) => $t->same(true, $plan162()['indexSignatureChanged']),
    'planner stat4 expression partial current source next162 partial changed' => static fn (TestRunner $t) => $t->same(true, $plan162()['partialPredicateChanged']),
    'planner stat4 expression partial current source next162 prepared term count' => static fn (TestRunner $t) => $t->same(2, $plan162()['partialPredicateDelta']['preparedTermCount']),
    'planner stat4 expression partial current source next162 current term count' => static fn (TestRunner $t) => $t->same(3, $plan162()['partialPredicateDelta']['currentTermCount']),
    'planner stat4 expression partial current source next162 added blog term' => static fn (TestRunner $t) => $t->same('blog_id', $plan162()['partialPredicateDelta']['addedTerms'][0]['left']['column']),
    'planner stat4 expression partial current source next162 no removed terms' => static fn (TestRunner $t) => $t->same([], $plan162()['partialPredicateDelta']['removedTerms']),
    'planner stat4 expression partial current source next162 selected index' => static fn (TestRunner $t) => $t->same('idx_wp_options_lower_name_blog_partial_next162', $plan162()['selectedPlan']['name']),
    'planner stat4 expression partial current source next162 selected root' => static fn (TestRunner $t) => $t->same(16241, $plan162()['selectedPlan']['rootPage']),
    'planner stat4 expression partial current source next162 selected usable' => static fn (TestRunner $t) => $t->same(true, $plan162()['selectedPlan']['usable']),
    'planner stat4 expression partial current source next162 selected partial proof' => static fn (TestRunner $t) => $t->same(true, $plan162()['selectedPlan']['partialPredicateImplied']),
    'planner stat4 expression partial current source next162 selected stat4 used' => static fn (TestRunner $t) => $t->same(true, $plan162()['selectedPlan']['stat4Used']),
    'planner stat4 expression partial current source next162 selected operator' => static fn (TestRunner $t) => $t->same('=', $plan162()['selectedPlan']['constraint']['operator']),
    'planner stat4 expression partial current source next162 stat4 samples matched' => static fn (TestRunner $t) => $t->same(1, $plan162()['selectedPlan']['stat4MatchedSamples']),
    'planner stat4 expression partial current source next162 stat4 estimate' => static fn (TestRunner $t) => $t->same(3, $plan162()['selectedPlan']['stat4Estimate']),
    'planner stat4 expression partial current source next162 matched row count' => static fn (TestRunner $t) => $t->same(3, $plan162()['selectedPlan']['matchedRowCount']),
    'planner stat4 expression partial current source next162 matched rowids' => static fn (TestRunner $t) => $t->same([20, 21, 22], $plan162()['selectedPlan']['matchedRowids']),
    'planner stat4 expression partial current source next162 equality rowids' => static fn (TestRunner $t) => $t->same([20, 21, 22], $plan162()['exactEqualityRowids']),
    'planner stat4 expression partial current source next162 bucket count' => static fn (TestRunner $t) => $t->same(1, $plan162()['exactEqualityBucketCount']),
    'planner stat4 expression partial current source next162 bucket key' => static fn (TestRunner $t) => $t->same('plugin_cache', $plan162()['exactEqualityBuckets'][0]['key']),
    'planner stat4 expression partial current source next162 bucket row count' => static fn (TestRunner $t) => $t->same(3, $plan162()['exactEqualityBuckets'][0]['rowCount']),
    'planner stat4 expression partial current source next162 bucket exact' => static fn (TestRunner $t) => $t->same(true, $plan162()['exactEqualityBuckets'][0]['exact']),
    'planner stat4 expression partial current source next162 bucket stat4 rowid' => static fn (TestRunner $t) => $t->same(20, $plan162()['exactEqualityBuckets'][0]['stat4Rowid']),
    'planner stat4 expression partial current source next162 bucket next key' => static fn (TestRunner $t) => $t->same(null, $plan162()['exactEqualityBuckets'][0]['nextKey']),
    'planner stat4 expression partial current source next162 current only rowids' => static fn (TestRunner $t) => $t->same([20, 21, 22, 23, 24, 25, 26], $plan162()['currentSourceOnlyRowids']),
    'planner stat4 expression partial current source next162 blocks prepared stale rows' => static fn (TestRunner $t) => $t->same([11, 12, 13], $plan162()['stalePreparedRowidsBlockedByPartialDelta']),
    'planner stat4 expression partial current source next162 excludes network row' => static fn (TestRunner $t) => $t->same(false, in_array(23, $plan162()['selectedPlan']['matchedRowids'], true)),
    'planner stat4 expression partial current source next162 excludes autoload no' => static fn (TestRunner $t) => $t->same(false, in_array(24, $plan162()['selectedPlan']['matchedRowids'], true)),
    'planner stat4 expression partial current source next162 excludes null name' => static fn (TestRunner $t) => $t->same(false, in_array(26, $plan162()['selectedPlan']['matchedRowids'], true)),
    'planner stat4 expression partial current source next162 payload preserves case' => static fn (TestRunner $t) => $t->same('Plugin_Cache', $plan162()['matchedRows'][0]['payload']['option_name']),
    'planner stat4 expression partial current source next162 payload blog' => static fn (TestRunner $t) => $t->same(1, $plan162()['matchedRows'][1]['payload']['blog_id']),
    'planner stat4 expression partial current source next162 current next rowid' => static fn (TestRunner $t) => $t->same(21, $plan162()['currentNextRows'][0]['next']['rowid']),
    'planner stat4 expression partial current source next162 current next eof' => static fn (TestRunner $t) => $t->same(null, $plan162()['currentNextRows'][2]['next']),
    'planner stat4 expression partial current source next162 cursor opens index' => static fn (TestRunner $t) => $t->same('OpenRead', $plan162()['cursorProgram'][0]['opcode']),
    'planner stat4 expression partial current source next162 cursor fences delta' => static fn (TestRunner $t) => $t->same('FencePartialPredicateDelta', $plan162()['cursorProgram'][1]['opcode']),
    'planner stat4 expression partial current source next162 cursor seeks equality' => static fn (TestRunner $t) => $t->same(['opcode' => 'SeekStat4Eq', 'key' => 'plugin_cache'], $plan162()['cursorProgram'][2]),
    'planner stat4 expression partial current source next162 cursor filters rowids' => static fn (TestRunner $t) => $t->same([20, 21, 22], $plan162()['cursorProgram'][3]['rowids']),
    'planner stat4 expression partial current source next162 cursor deferred table' => static fn (TestRunner $t) => $t->same('DeferredSeek', $plan162()['cursorProgram'][4]['opcode']),
    'planner stat4 expression partial current source next162 cursor result count' => static fn (TestRunner $t) => $t->same(3, $plan162()['cursorProgram'][8]['rowCount']),
    'planner stat4 expression partial current source next162 selected ready flag' => static fn (TestRunner $t) => $t->same(true, $plan162()['selectedPlan']['next162Ready']),
    'planner stat4 expression partial current source next162 selected bucket count' => static fn (TestRunner $t) => $t->same(1, $plan162()['selectedPlan']['next162ExactEqualityBucketCount']),
    'planner stat4 expression partial current source next162 selected next admitted' => static fn (TestRunner $t) => $t->same(true, $plan162()['selectedPlan']['next162NextSourceAdmitted']),
    'planner stat4 expression partial current source next162 no next summary' => static fn (TestRunner $t) => $t->same(null, $plan162()['nextSource']),
    'planner stat4 expression partial current source next162 next fresh admitted' => static fn (TestRunner $t) => $t->same(true, $nextFresh162()['nextSourceAdmitted']),
    'planner stat4 expression partial current source next162 next fresh reasons' => static fn (TestRunner $t) => $t->same([], $nextFresh162()['nextSource']['replanReasons']),
    'planner stat4 expression partial current source next162 next stale blocked' => static fn (TestRunner $t) => $t->same(false, $nextStale162()['nextSourceAdmitted']),
    'planner stat4 expression partial current source next162 next stale status' => static fn (TestRunner $t) => $t->same('requires-current-source-reprepare', $nextStale162()['status']),
    'planner stat4 expression partial current source next162 next stale reasons' => static fn (TestRunner $t) => $t->same(['schema-cookie', 'stat4-generation', 'row-signature'], $nextStale162()['nextSource']['replanReasons']),
    'planner stat4 expression partial current source next162 fence delta signature' => static fn (TestRunner $t) => $t->same(64, strlen($plan162()['stat4Fence']['next162PartialPredicateDeltaSignature'])),
    'planner stat4 expression partial current source next162 fence bucket signature' => static fn (TestRunner $t) => $t->same(64, strlen($plan162()['stat4Fence']['next162EqualityBucketSignature'])),
    'planner stat4 expression partial current source next162 selected partial signature' => static fn (TestRunner $t) => $t->same(64, strlen($plan162()['selectedPlan']['next162PartialPredicateSignature'])),
    'planner stat4 expression partial current source next162 detail' => static fn (TestRunner $t) => $t->contains('EXACT EQUALITY BUCKETS WITH PARTIAL DELTA', $plan162()['detail']),
    'planner stat4 expression partial current source next162 dependency marker' => static fn (TestRunner $t) => $t->true(in_array('sqlite-sqlplanner-stat4-expression-partial-current-source-next162', $plan162()['dependencies'], true)),
    'planner stat4 expression partial current source next162 dependency closure' => static fn (TestRunner $t) => $t->contains('no new support component needed', $plan162()['dependency_closure']),
    'planner stat4 expression partial current source next162 non overlap' => static fn (TestRunner $t) => $t->contains('equality-bucket admission', $plan162()['non_overlap']),
    'planner stat4 expression partial current source next162 same source requires delta' => static fn (TestRunner $t) => $t->same('requires-current-source-reprepare', $same162()['status']),
    'planner stat4 expression partial current source next162 same source not stale' => static fn (TestRunner $t) => $t->same(false, $same162()['stalePreparedStatement']),
    'planner stat4 expression partial current source next162 no delta requires current source' => static fn (TestRunner $t) => $t->same('requires-current-source-reprepare', $noDelta162()['status']),
    'planner stat4 expression partial current source next162 no delta flag false' => static fn (TestRunner $t) => $t->same(false, $noDelta162()['partialPredicateChanged']),
    'planner stat4 expression partial current source next162 missing blog proof falls back' => static fn (TestRunner $t) => $t->same('requires-current-source-reprepare', $missingBlogProof162()['status']),
    'planner stat4 expression partial current source next162 missing blog not usable' => static fn (TestRunner $t) => $t->same(false, $missingBlogProof162()['selectedPlan']['usable']),
    'planner stat4 expression partial current source next162 validates partial terms' => static function (TestRunner $t) use ($current162, $plan162): void {
        $current = $current162();
        $current['indexes'][0]['partialPredicateTerms'] = ['bad'];
        $t->throws(InvalidArgumentException::class, static fn () => $plan162(null, $current));
    },
    'planner stat4 expression partial current source next162 validates row list' => static function (TestRunner $t) use ($current162, $plan162): void {
        $current = $current162();
        $current['rows'][] = 'bad';
        $t->throws(InvalidArgumentException::class, static fn () => $plan162(null, $current));
    },
];

return $tests;

<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan;

$term162 = static fn (string $column, string $operator, mixed $right = null): array => ['left' => ['column' => $column], 'operator' => $operator, 'right' => $right];
$exprTerm162 = static fn (string $expression, string $operator, mixed $right = null): array => ['left' => ['expression' => $expression], 'operator' => $operator, 'right' => $right];

$prepared162 = static fn (): array => [
    'name' => 'prepared-wp-options-stat4-expression-partial-partial-predicate-delta-equality',
    'schemaCookie' => 1620,
    'stat4Generation' => 31,
    'rows' => [
        ['rowid' => 10, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'Plugin_Cache', 'option_value' => 'old-cache-a'],
        ['rowid' => 11, 'blog_id' => 2, 'autoload' => 'yes', 'option_name' => 'plugin_cache', 'option_value' => 'network-cache'],
        ['rowid' => 12, 'blog_id' => 1, 'autoload' => 'no', 'option_name' => 'plugin_cache', 'option_value' => 'lazy-cache'],
        ['rowid' => 13, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => null, 'option_value' => 'null-name'],
    ],
    'indexes' => [[
        'name' => 'idx_wp_options_lower_name_blog_partial_partial-predicate-delta-equality',
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
    $source['name'] = 'current-wp-options-stat4-expression-partial-partial-predicate-delta-equality';
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

$plan162 = static fn (?array $prepared = null, ?array $current = null, ?array $query = null, ?array $next = null): array => SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan::materializeCurrentPartialPredicateDeltaEquality(
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
    $next['name'] = 'next-wp-options-stat4-expression-partial-partial-predicate-delta-equality';
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
    'planner stat4 expression partial current source partial-predicate-delta-equality status ready' => static fn (TestRunner $t) => $t->same('stat4-expression-partial-current-partial-predicate-delta-equality-ready', $plan162()['status']),
    'planner stat4 expression partial current source partial-predicate-delta-equality selects current' => static fn (TestRunner $t) => $t->same('current', $plan162()['selectedSource']),
    'planner stat4 expression partial current source partial-predicate-delta-equality stale prepared' => static fn (TestRunner $t) => $t->same(true, $plan162()['stalePreparedStatement']),
    'planner stat4 expression partial current source partial-predicate-delta-equality reprepare required' => static fn (TestRunner $t) => $t->same(true, $plan162()['reprepareRequired']),
    'planner stat4 expression partial current source partial-predicate-delta-equality schema changed' => static fn (TestRunner $t) => $t->same(true, $plan162()['schemaCookieChanged']),
    'planner stat4 expression partial current source partial-predicate-delta-equality stat4 changed' => static fn (TestRunner $t) => $t->same(true, $plan162()['stat4GenerationChanged']),
    'planner stat4 expression partial current source partial-predicate-delta-equality index changed' => static fn (TestRunner $t) => $t->same(true, $plan162()['indexSignatureChanged']),
    'planner stat4 expression partial current source partial-predicate-delta-equality partial changed' => static fn (TestRunner $t) => $t->same(true, $plan162()['partialPredicateChanged']),
    'planner stat4 expression partial current source partial-predicate-delta-equality prepared term count' => static fn (TestRunner $t) => $t->same(2, $plan162()['partialPredicateDelta']['preparedTermCount']),
    'planner stat4 expression partial current source partial-predicate-delta-equality current term count' => static fn (TestRunner $t) => $t->same(3, $plan162()['partialPredicateDelta']['currentTermCount']),
    'planner stat4 expression partial current source partial-predicate-delta-equality added blog term' => static fn (TestRunner $t) => $t->same('blog_id', $plan162()['partialPredicateDelta']['addedTerms'][0]['left']['column']),
    'planner stat4 expression partial current source partial-predicate-delta-equality no removed terms' => static fn (TestRunner $t) => $t->same([], $plan162()['partialPredicateDelta']['removedTerms']),
    'planner stat4 expression partial current source partial-predicate-delta-equality selected index' => static fn (TestRunner $t) => $t->same('idx_wp_options_lower_name_blog_partial_partial-predicate-delta-equality', $plan162()['selectedPlan']['name']),
    'planner stat4 expression partial current source partial-predicate-delta-equality selected root' => static fn (TestRunner $t) => $t->same(16241, $plan162()['selectedPlan']['rootPage']),
    'planner stat4 expression partial current source partial-predicate-delta-equality selected usable' => static fn (TestRunner $t) => $t->same(true, $plan162()['selectedPlan']['usable']),
    'planner stat4 expression partial current source partial-predicate-delta-equality selected partial proof' => static fn (TestRunner $t) => $t->same(true, $plan162()['selectedPlan']['partialPredicateImplied']),
    'planner stat4 expression partial current source partial-predicate-delta-equality selected stat4 used' => static fn (TestRunner $t) => $t->same(true, $plan162()['selectedPlan']['stat4Used']),
    'planner stat4 expression partial current source partial-predicate-delta-equality selected operator' => static fn (TestRunner $t) => $t->same('=', $plan162()['selectedPlan']['constraint']['operator']),
    'planner stat4 expression partial current source partial-predicate-delta-equality stat4 samples matched' => static fn (TestRunner $t) => $t->same(1, $plan162()['selectedPlan']['stat4MatchedSamples']),
    'planner stat4 expression partial current source partial-predicate-delta-equality stat4 estimate' => static fn (TestRunner $t) => $t->same(3, $plan162()['selectedPlan']['stat4Estimate']),
    'planner stat4 expression partial current source partial-predicate-delta-equality matched row count' => static fn (TestRunner $t) => $t->same(3, $plan162()['selectedPlan']['matchedRowCount']),
    'planner stat4 expression partial current source partial-predicate-delta-equality matched rowids' => static fn (TestRunner $t) => $t->same([20, 21, 22], $plan162()['selectedPlan']['matchedRowids']),
    'planner stat4 expression partial current source partial-predicate-delta-equality equality rowids' => static fn (TestRunner $t) => $t->same([20, 21, 22], $plan162()['exactEqualityRowids']),
    'planner stat4 expression partial current source partial-predicate-delta-equality bucket count' => static fn (TestRunner $t) => $t->same(1, $plan162()['exactEqualityBucketCount']),
    'planner stat4 expression partial current source partial-predicate-delta-equality bucket key' => static fn (TestRunner $t) => $t->same('plugin_cache', $plan162()['exactEqualityBuckets'][0]['key']),
    'planner stat4 expression partial current source partial-predicate-delta-equality bucket row count' => static fn (TestRunner $t) => $t->same(3, $plan162()['exactEqualityBuckets'][0]['rowCount']),
    'planner stat4 expression partial current source partial-predicate-delta-equality bucket exact' => static fn (TestRunner $t) => $t->same(true, $plan162()['exactEqualityBuckets'][0]['exact']),
    'planner stat4 expression partial current source partial-predicate-delta-equality bucket stat4 rowid' => static fn (TestRunner $t) => $t->same(20, $plan162()['exactEqualityBuckets'][0]['stat4Rowid']),
    'planner stat4 expression partial current source partial-predicate-delta-equality bucket next key' => static fn (TestRunner $t) => $t->same(null, $plan162()['exactEqualityBuckets'][0]['nextKey']),
    'planner stat4 expression partial current source partial-predicate-delta-equality current only rowids' => static fn (TestRunner $t) => $t->same([20, 21, 22, 23, 24, 25, 26], $plan162()['currentSourceOnlyRowids']),
    'planner stat4 expression partial current source partial-predicate-delta-equality blocks prepared stale rows' => static fn (TestRunner $t) => $t->same([11, 12, 13], $plan162()['stalePreparedRowidsBlockedByPartialDelta']),
    'planner stat4 expression partial current source partial-predicate-delta-equality excludes network row' => static fn (TestRunner $t) => $t->same(false, in_array(23, $plan162()['selectedPlan']['matchedRowids'], true)),
    'planner stat4 expression partial current source partial-predicate-delta-equality excludes autoload no' => static fn (TestRunner $t) => $t->same(false, in_array(24, $plan162()['selectedPlan']['matchedRowids'], true)),
    'planner stat4 expression partial current source partial-predicate-delta-equality excludes null name' => static fn (TestRunner $t) => $t->same(false, in_array(26, $plan162()['selectedPlan']['matchedRowids'], true)),
    'planner stat4 expression partial current source partial-predicate-delta-equality payload preserves case' => static fn (TestRunner $t) => $t->same('Plugin_Cache', $plan162()['matchedRows'][0]['payload']['option_name']),
    'planner stat4 expression partial current source partial-predicate-delta-equality payload blog' => static fn (TestRunner $t) => $t->same(1, $plan162()['matchedRows'][1]['payload']['blog_id']),
    'planner stat4 expression partial current source partial-predicate-delta-equality current next rowid' => static fn (TestRunner $t) => $t->same(21, $plan162()['currentNextRows'][0]['next']['rowid']),
    'planner stat4 expression partial current source partial-predicate-delta-equality current next eof' => static fn (TestRunner $t) => $t->same(null, $plan162()['currentNextRows'][2]['next']),
    'planner stat4 expression partial current source partial-predicate-delta-equality cursor opens index' => static fn (TestRunner $t) => $t->same('OpenRead', $plan162()['cursorProgram'][0]['opcode']),
    'planner stat4 expression partial current source partial-predicate-delta-equality cursor fences delta' => static fn (TestRunner $t) => $t->same('FencePartialPredicateDelta', $plan162()['cursorProgram'][1]['opcode']),
    'planner stat4 expression partial current source partial-predicate-delta-equality cursor seeks equality' => static fn (TestRunner $t) => $t->same(['opcode' => 'SeekStat4Eq', 'key' => 'plugin_cache'], $plan162()['cursorProgram'][2]),
    'planner stat4 expression partial current source partial-predicate-delta-equality cursor filters rowids' => static fn (TestRunner $t) => $t->same([20, 21, 22], $plan162()['cursorProgram'][3]['rowids']),
    'planner stat4 expression partial current source partial-predicate-delta-equality cursor deferred table' => static fn (TestRunner $t) => $t->same('DeferredSeek', $plan162()['cursorProgram'][4]['opcode']),
    'planner stat4 expression partial current source partial-predicate-delta-equality cursor result count' => static fn (TestRunner $t) => $t->same(3, $plan162()['cursorProgram'][8]['rowCount']),
    'planner stat4 expression partial current source partial-predicate-delta-equality selected ready flag' => static fn (TestRunner $t) => $t->same(true, $plan162()['selectedPlan']['partialPredicateDeltaEqualityReady']),
    'planner stat4 expression partial current source partial-predicate-delta-equality selected bucket count' => static fn (TestRunner $t) => $t->same(1, $plan162()['selectedPlan']['partialPredicateDeltaEqualityBucketCount']),
    'planner stat4 expression partial current source partial-predicate-delta-equality selected next admitted' => static fn (TestRunner $t) => $t->same(true, $plan162()['selectedPlan']['partialPredicateDeltaEqualityNextSourceAdmitted']),
    'planner stat4 expression partial current source partial-predicate-delta-equality no next summary' => static fn (TestRunner $t) => $t->same(null, $plan162()['nextSource']),
    'planner stat4 expression partial current source partial-predicate-delta-equality next fresh admitted' => static fn (TestRunner $t) => $t->same(true, $nextFresh162()['nextSourceAdmitted']),
    'planner stat4 expression partial current source partial-predicate-delta-equality next fresh reasons' => static fn (TestRunner $t) => $t->same([], $nextFresh162()['nextSource']['replanReasons']),
    'planner stat4 expression partial current source partial-predicate-delta-equality next stale blocked' => static fn (TestRunner $t) => $t->same(false, $nextStale162()['nextSourceAdmitted']),
    'planner stat4 expression partial current source partial-predicate-delta-equality next stale status' => static fn (TestRunner $t) => $t->same('requires-current-source-reprepare', $nextStale162()['status']),
    'planner stat4 expression partial current source partial-predicate-delta-equality next stale reasons' => static fn (TestRunner $t) => $t->same(['schema-cookie', 'stat4-generation', 'row-signature'], $nextStale162()['nextSource']['replanReasons']),
    'planner stat4 expression partial current source partial-predicate-delta-equality fence delta signature' => static fn (TestRunner $t) => $t->same(64, strlen($plan162()['stat4Fence']['partialPredicateDeltaEqualityDeltaSignature'])),
    'planner stat4 expression partial current source partial-predicate-delta-equality fence bucket signature' => static fn (TestRunner $t) => $t->same(64, strlen($plan162()['stat4Fence']['partialPredicateDeltaEqualityBucketSignature'])),
    'planner stat4 expression partial current source partial-predicate-delta-equality selected partial signature' => static fn (TestRunner $t) => $t->same(64, strlen($plan162()['selectedPlan']['partialPredicateDeltaEqualityPartialPredicateSignature'])),
    'planner stat4 expression partial current source partial-predicate-delta-equality detail' => static fn (TestRunner $t) => $t->contains('EXACT EQUALITY BUCKETS WITH PARTIAL DELTA', $plan162()['detail']),
    'planner stat4 expression partial current source partial-predicate-delta-equality dependency marker' => static fn (TestRunner $t) => $t->true(in_array('sqlite-sqlplanner-stat4-expression-partial-current-partial-predicate-delta-equality', $plan162()['dependencies'], true)),
    'planner stat4 expression partial current source partial-predicate-delta-equality dependency closure' => static fn (TestRunner $t) => $t->contains('no new support component needed', $plan162()['dependency_closure']),
    'planner stat4 expression partial current source partial-predicate-delta-equality non overlap' => static fn (TestRunner $t) => $t->contains('equality-bucket admission', $plan162()['non_overlap']),
    'planner stat4 expression partial current source partial-predicate-delta-equality same source requires delta' => static fn (TestRunner $t) => $t->same('requires-current-source-reprepare', $same162()['status']),
    'planner stat4 expression partial current source partial-predicate-delta-equality same source not stale' => static fn (TestRunner $t) => $t->same(false, $same162()['stalePreparedStatement']),
    'planner stat4 expression partial current source partial-predicate-delta-equality no delta requires current source' => static fn (TestRunner $t) => $t->same('requires-current-source-reprepare', $noDelta162()['status']),
    'planner stat4 expression partial current source partial-predicate-delta-equality no delta flag false' => static fn (TestRunner $t) => $t->same(false, $noDelta162()['partialPredicateChanged']),
    'planner stat4 expression partial current source partial-predicate-delta-equality missing blog proof falls back' => static fn (TestRunner $t) => $t->same('requires-current-source-reprepare', $missingBlogProof162()['status']),
    'planner stat4 expression partial current source partial-predicate-delta-equality missing blog not usable' => static fn (TestRunner $t) => $t->same(false, $missingBlogProof162()['selectedPlan']['usable']),
    'planner stat4 expression partial current source partial-predicate-delta-equality validates partial terms' => static function (TestRunner $t) use ($current162, $plan162): void {
        $current = $current162();
        $current['indexes'][0]['partialPredicateTerms'] = ['bad'];
        $t->throws(InvalidArgumentException::class, static fn () => $plan162(null, $current));
    },
    'planner stat4 expression partial current source partial-predicate-delta-equality validates row list' => static function (TestRunner $t) use ($current162, $plan162): void {
        $current = $current162();
        $current['rows'][] = 'bad';
        $t->throws(InvalidArgumentException::class, static fn () => $plan162(null, $current));
    },
];

return $tests;

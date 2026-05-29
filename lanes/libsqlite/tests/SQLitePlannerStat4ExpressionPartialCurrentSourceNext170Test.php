<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan;

$term170 = static fn (string $column, string $operator, mixed $right = null): array => ['left' => ['column' => $column], 'operator' => $operator, 'right' => $right];
$exprIn170 = static fn (string $expression, array $values): array => ['left' => ['expression' => $expression], 'operator' => 'IN', 'values' => $values];

$prepared170 = static fn (): array => [
    'name' => 'prepared-wp-options-stat4-expression-partial-next170',
    'schemaCookie' => 1700,
    'stat4Generation' => 41,
    'rows' => [
        ['rowid' => 10, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'Plugin_Cache', 'option_value' => 'old-cache'],
        ['rowid' => 11, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'Plugin_Forms', 'option_value' => 'old-forms'],
        ['rowid' => 12, 'blog_id' => 2, 'autoload' => 'yes', 'option_name' => 'Plugin_Cache', 'option_value' => 'network-cache'],
        ['rowid' => 13, 'blog_id' => 1, 'autoload' => 'no', 'option_name' => 'Plugin_Forms', 'option_value' => 'lazy-forms'],
    ],
    'indexes' => [[
        'name' => 'idx_wp_options_lower_name_blog_partial_next170',
        'rootPage' => 17001,
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

$current170 = static function () use ($prepared170): array {
    $source = $prepared170();
    $source['name'] = 'current-wp-options-stat4-expression-partial-next170';
    $source['schemaCookie'] = 1704;
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
    $source['indexes'][0]['rootPage'] = 17041;
    $source['indexes'][0]['partialPredicateTerms'][] = ['left' => ['column' => 'blog_id'], 'operator' => '=', 'right' => 1];
    $source['indexes'][0]['stat4Samples'] = [
        ['neq' => '2', 'nlt' => '0', 'ndlt' => '0', 'sample' => ['plugin_cache', 20]],
        ['neq' => '1', 'nlt' => '2', 'ndlt' => '1', 'sample' => ['plugin_forms', 22]],
        ['neq' => '1', 'nlt' => '3', 'ndlt' => '2', 'sample' => ['plugin_seo', 23]],
        ['neq' => '1', 'nlt' => '4', 'ndlt' => '3', 'sample' => ['siteurl', 27]],
    ];

    return $source;
};

$query170 = static fn (): array => [
    $exprIn170('LOWER( option_name )', ['plugin_cache', 'plugin_forms', 'plugin_seo']),
    $term170('autoload', '=', 'yes'),
    $term170('blog_id', '=', 1),
    $term170('option_name', 'IS NOT NULL'),
];
$needed170 = ['option_name', 'option_value', 'blog_id'];
$plan170 = static fn (?array $prepared = null, ?array $current = null, ?array $query = null, ?array $next = null): array => SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan::materializeNext170(
    $prepared ?? $prepared170(),
    $current ?? $current170(),
    $query ?? $query170(),
    $GLOBALS['needed170'],
    $next,
);
$GLOBALS['needed170'] = $needed170;

$unrelatedNext170 = static function () use ($current170): array {
    $next = $current170();
    $next['name'] = 'next-wp-options-stat4-expression-partial-unrelated-next170';
    $next['rows'][] = ['rowid' => 28, 'blog_id' => 2, 'autoload' => 'yes', 'option_name' => 'Plugin_Cache', 'option_value' => 'network-cache-new'];
    $next['rows'][] = ['rowid' => 29, 'blog_id' => 1, 'autoload' => 'no', 'option_name' => 'Plugin_Forms', 'option_value' => 'lazy-forms-new'];
    $next['rows'][] = ['rowid' => 30, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'siteurl', 'option_value' => 'https://next.example.test'];

    return $next;
};
$changedBucketNext170 = static function () use ($current170): array {
    $next = $current170();
    $next['name'] = 'next-wp-options-stat4-expression-partial-bucket-next170';
    $next['rows'][] = ['rowid' => 31, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'Plugin_Cache', 'option_value' => 'late-cache'];

    return $next;
};
$changedStat4Next170 = static function () use ($unrelatedNext170): array {
    $next = $unrelatedNext170();
    $next['stat4Generation'] = 48;

    return $next;
};
$base170 = static fn (): array => $plan170(null, null, null, $unrelatedNext170());
$bucket170 = static fn (): array => $plan170(null, null, null, $changedBucketNext170());
$stat4170 = static fn (): array => $plan170(null, null, null, $changedStat4Next170());

return [
    'planner stat4 expression partial current source next170 status ready' => static fn (TestRunner $t) => $t->same('stat4-expression-partial-current-source-next170-ready', $base170()['status']),
    'planner stat4 expression partial current source next170 selected current' => static fn (TestRunner $t) => $t->same('current', $base170()['selectedSource']),
    'planner stat4 expression partial current source next170 next admitted' => static fn (TestRunner $t) => $t->same(true, $base170()['nextSourceAdmitted']),
    'planner stat4 expression partial current source next170 next summary admitted' => static fn (TestRunner $t) => $t->same(true, $base170()['next170Source']['admitted']),
    'planner stat4 expression partial current source next170 only row signature changed' => static fn (TestRunner $t) => $t->same(true, $base170()['next170Source']['onlyRowSignatureChanged']),
    'planner stat4 expression partial current source next170 relevant stable' => static fn (TestRunner $t) => $t->same(true, $base170()['next170Source']['relevantRowSignatureStable']),
    'planner stat4 expression partial current source next170 clears reasons' => static fn (TestRunner $t) => $t->same([], $base170()['next170Source']['replanReasons']),
    'planner stat4 expression partial current source next170 raw next reason remains' => static fn (TestRunner $t) => $t->same(['row-signature'], $base170()['nextSource']['replanReasons']),
    'planner stat4 expression partial current source next170 current rowids' => static fn (TestRunner $t) => $t->same([20, 21, 22, 23], $base170()['next170Source']['currentRelevantRowids']),
    'planner stat4 expression partial current source next170 next rowids unchanged' => static fn (TestRunner $t) => $t->same([20, 21, 22, 23], $base170()['next170Source']['nextRelevantRowids']),
    'planner stat4 expression partial current source next170 selected rowids' => static fn (TestRunner $t) => $t->same([20, 21, 22, 23], $base170()['selectedPlan']['matchedRowids']),
    'planner stat4 expression partial current source next170 selected index' => static fn (TestRunner $t) => $t->same('idx_wp_options_lower_name_blog_partial_next170', $base170()['selectedPlan']['name']),
    'planner stat4 expression partial current source next170 selected root' => static fn (TestRunner $t) => $t->same(17041, $base170()['selectedPlan']['rootPage']),
    'planner stat4 expression partial current source next170 selected flag' => static fn (TestRunner $t) => $t->same(true, $base170()['selectedPlan']['next170Ready']),
    'planner stat4 expression partial current source next170 selected admitted flag' => static fn (TestRunner $t) => $t->same(true, $base170()['selectedPlan']['next170NextSourceAdmitted']),
    'planner stat4 expression partial current source next170 selected stable flag' => static fn (TestRunner $t) => $t->same(true, $base170()['selectedPlan']['next170RelevantRowSignatureStable']),
    'planner stat4 expression partial current source next170 selected relevant rowids' => static fn (TestRunner $t) => $t->same([20, 21, 22, 23], $base170()['selectedPlan']['next170CurrentRelevantRowids']),
    'planner stat4 expression partial current source next170 in values' => static fn (TestRunner $t) => $t->same(['plugin_cache', 'plugin_forms', 'plugin_seo'], $base170()['inValues']),
    'planner stat4 expression partial current source next170 bucket rowids' => static fn (TestRunner $t) => $t->same([20, 21, 22, 23], $base170()['inBucketRowids']),
    'planner stat4 expression partial current source next170 bucket count' => static fn (TestRunner $t) => $t->same(3, $base170()['inBucketCount']),
    'planner stat4 expression partial current source next170 first bucket unchanged' => static fn (TestRunner $t) => $t->same([20, 21], $base170()['inBuckets'][0]['rowids']),
    'planner stat4 expression partial current source next170 second bucket unchanged' => static fn (TestRunner $t) => $t->same([22], $base170()['inBuckets'][1]['rowids']),
    'planner stat4 expression partial current source next170 third bucket unchanged' => static fn (TestRunner $t) => $t->same([23], $base170()['inBuckets'][2]['rowids']),
    'planner stat4 expression partial current source next170 missing none' => static fn (TestRunner $t) => $t->same([], $base170()['missingStat4InValues']),
    'planner stat4 expression partial current source next170 fence stable' => static fn (TestRunner $t) => $t->same(true, $base170()['stat4Fence']['next170RelevantRowsStable']),
    'planner stat4 expression partial current source next170 current signature length' => static fn (TestRunner $t) => $t->same(64, strlen($base170()['stat4Fence']['next170CurrentRelevantRowSignature'])),
    'planner stat4 expression partial current source next170 next signature length' => static fn (TestRunner $t) => $t->same(64, strlen($base170()['stat4Fence']['next170NextRelevantRowSignature'])),
    'planner stat4 expression partial current source next170 signatures equal' => static fn (TestRunner $t) => $t->same($base170()['stat4Fence']['next170CurrentRelevantRowSignature'], $base170()['stat4Fence']['next170NextRelevantRowSignature']),
    'planner stat4 expression partial current source next170 current source only rowids' => static fn (TestRunner $t) => $t->same([20, 21, 22, 23, 24, 25, 26, 27], $base170()['currentSourceOnlyRowids']),
    'planner stat4 expression partial current source next170 next166 would block' => static fn (TestRunner $t) => $t->same(false, $base170()['selectedPlan']['next166NextSourceAdmitted']),
    'planner stat4 expression partial current source next170 next166 flag not ready' => static fn (TestRunner $t) => $t->same(false, $base170()['selectedPlan']['next166Ready']),
    'planner stat4 expression partial current source next170 cursor deferred seek' => static fn (TestRunner $t) => $t->same('DeferredSeek', $base170()['cursorProgram'][4]['opcode']),
    'planner stat4 expression partial current source next170 cursor result count' => static fn (TestRunner $t) => $t->same(4, $base170()['cursorProgram'][8]['rowCount']),
    'planner stat4 expression partial current source next170 detail' => static fn (TestRunner $t) => $t->contains('NEXT ROW CHURN OUTSIDE PARTIAL INDEX ADMITTED', $base170()['detail']),
    'planner stat4 expression partial current source next170 dependency marker' => static fn (TestRunner $t) => $t->true(in_array('sqlite-sqlplanner-stat4-expression-partial-current-source-next170', $base170()['dependencies'], true)),
    'planner stat4 expression partial current source next170 dependency closure' => static fn (TestRunner $t) => $t->contains('no new support component needed', $base170()['dependency_closure']),
    'planner stat4 expression partial current source next170 non overlap' => static fn (TestRunner $t) => $t->contains('row churn outside', $base170()['non_overlap']),
    'planner stat4 expression partial current source next170 bucket change blocks' => static fn (TestRunner $t) => $t->same('requires-current-source-reprepare', $bucket170()['status']),
    'planner stat4 expression partial current source next170 bucket next not admitted' => static fn (TestRunner $t) => $t->same(false, $bucket170()['next170Source']['admitted']),
    'planner stat4 expression partial current source next170 bucket stable false' => static fn (TestRunner $t) => $t->same(false, $bucket170()['next170Source']['relevantRowSignatureStable']),
    'planner stat4 expression partial current source next170 bucket reasons' => static fn (TestRunner $t) => $t->same(['row-signature'], $bucket170()['next170Source']['replanReasons']),
    'planner stat4 expression partial current source next170 bucket next rowids' => static fn (TestRunner $t) => $t->same([20, 21, 22, 23, 31], $bucket170()['next170Source']['nextRelevantRowids']),
    'planner stat4 expression partial current source next170 bucket fence stable false' => static fn (TestRunner $t) => $t->same(false, $bucket170()['stat4Fence']['next170RelevantRowsStable']),
    'planner stat4 expression partial current source next170 bucket signature differs' => static fn (TestRunner $t) => $t->same(false, $bucket170()['stat4Fence']['next170CurrentRelevantRowSignature'] === $bucket170()['stat4Fence']['next170NextRelevantRowSignature']),
    'planner stat4 expression partial current source next170 stat4 change blocks' => static fn (TestRunner $t) => $t->same('requires-current-source-reprepare', $stat4170()['status']),
    'planner stat4 expression partial current source next170 stat4 not admitted' => static fn (TestRunner $t) => $t->same(false, $stat4170()['next170Source']['admitted']),
    'planner stat4 expression partial current source next170 stat4 reasons' => static fn (TestRunner $t) => $t->same(['stat4-generation', 'row-signature'], $stat4170()['next170Source']['replanReasons']),
    'planner stat4 expression partial current source next170 stat4 relevant stable' => static fn (TestRunner $t) => $t->same(true, $stat4170()['next170Source']['relevantRowSignatureStable']),
    'planner stat4 expression partial current source next170 invalid row list' => static function (TestRunner $t) use ($current170, $plan170, $unrelatedNext170): void {
        $current = $current170();
        $current['rows'][] = 'bad';
        $t->throws(InvalidArgumentException::class, static fn () => $plan170(null, $current, null, $unrelatedNext170()));
    },
    'planner stat4 expression partial current source next170 invalid next row list' => static function (TestRunner $t) use ($plan170, $unrelatedNext170): void {
        $next = $unrelatedNext170();
        $next['rows'][] = 'bad';
        $t->throws(InvalidArgumentException::class, static fn () => $plan170(null, null, null, $next));
    },
    'planner stat4 expression partial current source next170 no next remains ready' => static fn (TestRunner $t) => $t->same('stat4-expression-partial-current-source-next170-ready', $plan170()['status']),
];

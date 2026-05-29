<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan;

$column165 = static fn (string $column, string $operator, mixed $right = null): array => ['left' => ['column' => $column], 'operator' => $operator, 'right' => $right];
$exprRange165 = static fn (string $expression, string $operator, mixed $right): array => ['left' => ['expression' => $expression], 'operator' => $operator, 'right' => $right];

$rows165 = static fn (): array => [
    ['rowid' => 1, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'SiteURL', 'option_value' => 'https://example.test', 'updated_at' => 10],
    ['rowid' => 2, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'Plugin_Cache', 'option_value' => 'warm', 'updated_at' => 40],
    ['rowid' => 3, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_cache', 'option_value' => 'fresh', 'updated_at' => 50],
    ['rowid' => 4, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_forms', 'option_value' => 'forms', 'updated_at' => 60],
    ['rowid' => 5, 'blog_id' => 1, 'autoload' => 'no', 'option_name' => 'plugin_cache', 'option_value' => 'lazy', 'updated_at' => 70],
    ['rowid' => 6, 'blog_id' => 2, 'autoload' => 'yes', 'option_name' => 'plugin_cache', 'option_value' => 'network', 'updated_at' => 80],
    ['rowid' => 7, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => null, 'option_value' => 'null-name', 'updated_at' => 90],
    ['rowid' => 8, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_seo', 'option_value' => 'seo', 'updated_at' => 100],
];

$source165 = static function (string $name, int $cookie, int $stat4, int $root, array $rows): array {
    return [
        'name' => $name,
        'schemaCookie' => $cookie,
        'stat4Generation' => $stat4,
        'rows' => $rows,
        'indexes' => [[
            'name' => 'idx_wp_options_lower_name_recent_autoload_stat4_next165',
            'rootPage' => $root,
            'expression' => 'lower(option_name)',
            'expressionColumn' => '__expr_lower_option_name',
            'collation' => 'BINARY',
            'partialPredicateTerms' => [
                ['left' => ['column' => 'blog_id'], 'operator' => '=', 'right' => 1],
                ['left' => ['column' => 'autoload'], 'operator' => '=', 'right' => 'yes'],
                ['left' => ['column' => 'option_name'], 'operator' => 'IS NOT NULL'],
                ['left' => ['column' => 'updated_at'], 'operator' => '>=', 'right' => 30],
                ['left' => ['column' => 'updated_at'], 'operator' => '<', 'right' => 100],
            ],
            'coveringColumns' => ['option_name'],
            'stat4Samples' => [
                ['neq' => '2 2', 'nlt' => '0 0', 'ndlt' => '0 0', 'sample' => ['plugin_cache', 2]],
                ['neq' => '1 1', 'nlt' => '2 2', 'ndlt' => '1 1', 'sample' => ['plugin_forms', 4]],
                ['neq' => '1 1', 'nlt' => '3 3', 'ndlt' => '2 2', 'sample' => ['plugin_seo', 8]],
                ['neq' => '1 1', 'nlt' => '4 4', 'ndlt' => '3 3', 'sample' => ['siteurl', 1]],
            ],
        ]],
    ];
};

$prepared165 = static fn (): array => $source165('prepared-wp-options-stat4-expression-partial-range-next165', 1650, 31, 16501, array_slice($rows165(), 0, 5));
$current165 = static fn (): array => $source165('current-wp-options-stat4-expression-partial-range-next165', 1654, 37, 16531, $rows165());
$query165 = static fn (): array => [
    $exprRange165('LOWER( option_name )', '>=', 'plugin_cache'),
    $column165('blog_id', '=', 1),
    $column165('autoload', '=', 'yes'),
    $column165('option_name', 'IS NOT NULL'),
    $column165('updated_at', '>=', 40),
    $column165('updated_at', '<', 90),
];
$plan165 = static fn (?array $prepared = null, ?array $current = null, ?array $query = null): array => SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan::materializeNext165(
    $prepared ?? $prepared165(),
    $current ?? $current165(),
    $query ?? $query165(),
    ['option_name', 'option_value', 'updated_at']
);
$fresh165 = static function () use ($current165, $plan165): array {
    $source = $current165();

    return $plan165($source, $source);
};
$unproved165 = static fn (): array => $plan165(null, null, [
    $exprRange165('lower(option_name)', '>=', 'plugin_cache'),
    $column165('blog_id', '=', 1),
    $column165('autoload', '=', 'yes'),
    $column165('option_name', 'IS NOT NULL'),
    $column165('updated_at', '>=', 20),
    $column165('updated_at', '<', 90),
]);
$upperPlan165 = static fn (): array => $plan165(null, null, [
    $exprRange165('lower(option_name)', '<', 'plugin_seo'),
    $column165('blog_id', '=', 1),
    $column165('autoload', '=', 'yes'),
    $column165('option_name', 'IS NOT NULL'),
    $column165('updated_at', '>=', 40),
    $column165('updated_at', '<', 90),
]);

return [
    'planner stat4 expression partial range current source next165 status ready' => static fn (TestRunner $t) => $t->same('stat4-expression-partial-range-current-source-next165-ready', $plan165()['status']),
    'planner stat4 expression partial range current source next165 selects current' => static fn (TestRunner $t) => $t->same('current', $plan165()['selectedSource']),
    'planner stat4 expression partial range current source next165 stale prepared' => static fn (TestRunner $t) => $t->same(true, $plan165()['stalePreparedStatement']),
    'planner stat4 expression partial range current source next165 reprepare required' => static fn (TestRunner $t) => $t->same(true, $plan165()['reprepareRequired']),
    'planner stat4 expression partial range current source next165 schema changed' => static fn (TestRunner $t) => $t->same(true, $plan165()['schemaCookieChanged']),
    'planner stat4 expression partial range current source next165 stat4 changed' => static fn (TestRunner $t) => $t->same(true, $plan165()['stat4GenerationChanged']),
    'planner stat4 expression partial range current source next165 signature changed' => static fn (TestRunner $t) => $t->same(true, $plan165()['indexSignatureChanged']),
    'planner stat4 expression partial range current source next165 selected index' => static fn (TestRunner $t) => $t->same('idx_wp_options_lower_name_recent_autoload_stat4_next165', $plan165()['selectedPlan']['name']),
    'planner stat4 expression partial range current source next165 root page' => static fn (TestRunner $t) => $t->same(16531, $plan165()['selectedPlan']['rootPage']),
    'planner stat4 expression partial range current source next165 expression' => static fn (TestRunner $t) => $t->same('lower(option_name)', $plan165()['selectedPlan']['expression']),
    'planner stat4 expression partial range current source next165 operator marker' => static fn (TestRunner $t) => $t->same(['>='], $plan165()['rangeConstraintOperators']),
    'planner stat4 expression partial range current source next165 partial range operators' => static fn (TestRunner $t) => $t->same(['>=', '<'], $plan165()['partialRangePredicateOperators']),
    'planner stat4 expression partial range current source next165 partial implied' => static fn (TestRunner $t) => $t->same(true, $plan165()['selectedPlan']['partialPredicateImplied']),
    'planner stat4 expression partial range current source next165 stat4 used' => static fn (TestRunner $t) => $t->same(true, $plan165()['selectedPlan']['stat4Used']),
    'planner stat4 expression partial range current source next165 matched sample count' => static fn (TestRunner $t) => $t->same(4, $plan165()['selectedPlan']['stat4MatchedSamples']),
    'planner stat4 expression partial range current source next165 stat4 estimate' => static fn (TestRunner $t) => $t->same(5, $plan165()['selectedPlan']['stat4Estimate']),
    'planner stat4 expression partial range current source next165 noncovering cost' => static fn (TestRunner $t) => $t->same(17, $plan165()['selectedPlan']['estimatedCost']),
    'planner stat4 expression partial range current source next165 matched keys' => static fn (TestRunner $t) => $t->same(['plugin_cache', 'plugin_forms', 'plugin_seo', 'siteurl'], $plan165()['selectedPlan']['matchedStat4Keys']),
    'planner stat4 expression partial range current source next165 matched stat4 rowids' => static fn (TestRunner $t) => $t->same([2, 4, 8, 1], $plan165()['selectedPlan']['matchedStat4Rowids']),
    'planner stat4 expression partial range current source next165 matched row count' => static fn (TestRunner $t) => $t->same(3, $plan165()['selectedPlan']['matchedRowCount']),
    'planner stat4 expression partial range current source next165 matched rowids' => static fn (TestRunner $t) => $t->same([2, 3, 4], $plan165()['selectedPlan']['matchedRowids']),
    'planner stat4 expression partial range current source next165 excludes stale siteurl' => static fn (TestRunner $t) => $t->same(false, in_array(1, $plan165()['selectedPlan']['matchedRowids'], true)),
    'planner stat4 expression partial range current source next165 excludes autoload no' => static fn (TestRunner $t) => $t->same(false, in_array(5, $plan165()['selectedPlan']['matchedRowids'], true)),
    'planner stat4 expression partial range current source next165 excludes blog two' => static fn (TestRunner $t) => $t->same(false, in_array(6, $plan165()['selectedPlan']['matchedRowids'], true)),
    'planner stat4 expression partial range current source next165 excludes null name' => static fn (TestRunner $t) => $t->same(false, in_array(7, $plan165()['selectedPlan']['matchedRowids'], true)),
    'planner stat4 expression partial range current source next165 excludes upper bound' => static fn (TestRunner $t) => $t->same(false, in_array(8, $plan165()['selectedPlan']['matchedRowids'], true)),
    'planner stat4 expression partial range current source next165 first payload' => static fn (TestRunner $t) => $t->same('warm', $plan165()['matchedRows'][0]['payload']['option_value']),
    'planner stat4 expression partial range current source next165 last payload' => static fn (TestRunner $t) => $t->same('forms', $plan165()['matchedRows'][2]['payload']['option_value']),
    'planner stat4 expression partial range current source next165 row stream signature' => static fn (TestRunner $t) => $t->same(64, strlen($plan165()['selectedPlan']['rowStreamSignature'])),
    'planner stat4 expression partial range current source next165 current next first' => static fn (TestRunner $t) => $t->same(3, $plan165()['currentNextRows'][0]['next']['rowid']),
    'planner stat4 expression partial range current source next165 current next final eof' => static fn (TestRunner $t) => $t->same(null, $plan165()['currentNextRows'][2]['next']),
    'planner stat4 expression partial range current source next165 stat4 current next count' => static fn (TestRunner $t) => $t->same(4, count($plan165()['selectedPlan']['stat4CurrentNext'])),
    'planner stat4 expression partial range current source next165 stat4 matched current next first' => static fn (TestRunner $t) => $t->same('plugin_forms', $plan165()['selectedPlan']['stat4MatchedCurrentNext'][0]['next']['key']),
    'planner stat4 expression partial range current source next165 stat4 matched eof' => static fn (TestRunner $t) => $t->same(null, $plan165()['selectedPlan']['stat4MatchedCurrentNext'][3]['next']),
    'planner stat4 expression partial range current source next165 table lookup required' => static fn (TestRunner $t) => $t->same(true, $plan165()['tableLookupRequired']),
    'planner stat4 expression partial range current source next165 residual required' => static fn (TestRunner $t) => $t->same(true, $plan165()['residualPredicateRequired']),
    'planner stat4 expression partial range current source next165 cursor open read' => static fn (TestRunner $t) => $t->same('OpenRead', $plan165()['cursorProgram'][0]['opcode']),
    'planner stat4 expression partial range current source next165 cursor seek' => static fn (TestRunner $t) => $t->same('SeekStat4Expression', $plan165()['cursorProgram'][1]['opcode']),
    'planner stat4 expression partial range current source next165 cursor deferred seek' => static fn (TestRunner $t) => $t->same('DeferredSeek', $plan165()['cursorProgram'][3]['opcode']),
    'planner stat4 expression partial range current source next165 cursor result count' => static fn (TestRunner $t) => $t->same(3, $plan165()['cursorProgram'][7]['rowCount']),
    'planner stat4 expression partial range current source next165 cursor next' => static fn (TestRunner $t) => $t->same('Next', $plan165()['cursorProgram'][8]['opcode']),
    'planner stat4 expression partial range current source next165 fence cookie' => static fn (TestRunner $t) => $t->same(1654, $plan165()['stat4Fence']['schemaCookie']),
    'planner stat4 expression partial range current source next165 fence stat4' => static fn (TestRunner $t) => $t->same(37, $plan165()['stat4Fence']['stat4Generation']),
    'planner stat4 expression partial range current source next165 fence expression' => static fn (TestRunner $t) => $t->same('lower(option_name)', $plan165()['stat4Fence']['expressionSignature']),
    'planner stat4 expression partial range current source next165 fence signatures' => static fn (TestRunner $t) => $t->same([64, 64, 64, 64], array_map('strlen', [$plan165()['stat4Fence']['sourceSignature'], $plan165()['stat4Fence']['partialPredicateSignature'], $plan165()['stat4Fence']['stat4SampleSignature'], $plan165()['stat4Fence']['rowStreamSignature']])),
    'planner stat4 expression partial range current source next165 prepared root' => static fn (TestRunner $t) => $t->same(16501, $plan165()['preparedSource']['rootPage']),
    'planner stat4 expression partial range current source next165 current usable' => static fn (TestRunner $t) => $t->same(true, $plan165()['currentSource']['usable']),
    'planner stat4 expression partial range current source next165 detail' => static fn (TestRunner $t) => $t->contains('NEXT165 RANGE', $plan165()['detail']),
    'planner stat4 expression partial range current source next165 dependency marker' => static fn (TestRunner $t) => $t->same(['sqlite-sqlplanner-stat4-expression-partial-range-current-source-next165'], $plan165()['dependencies']),
    'planner stat4 expression partial range current source next165 dependency closure' => static fn (TestRunner $t) => $t->contains('no new support component needed', $plan165()['dependency_closure']),
    'planner stat4 expression partial range current source next165 non overlap' => static fn (TestRunner $t) => $t->contains('one-sided range constraints', $plan165()['non_overlap']),
    'planner stat4 expression partial range current source next165 fresh reuses prepared' => static fn (TestRunner $t) => $t->same('prepared', $fresh165()['selectedSource']),
    'planner stat4 expression partial range current source next165 fresh rowids' => static fn (TestRunner $t) => $t->same([2, 3, 4], $fresh165()['selectedPlan']['matchedRowids']),
    'planner stat4 expression partial range current source next165 unproved falls back' => static fn (TestRunner $t) => $t->same('requires-next-stage', $unproved165()['status']),
    'planner stat4 expression partial range current source next165 upper range ready' => static fn (TestRunner $t) => $t->same('stat4-expression-partial-range-current-source-next165-ready', $upperPlan165()['status']),
    'planner stat4 expression partial range current source next165 upper range rowids' => static fn (TestRunner $t) => $t->same([2, 3, 4], $upperPlan165()['selectedPlan']['matchedRowids']),
    'planner stat4 expression partial range current source next165 upper range keys' => static fn (TestRunner $t) => $t->same(['plugin_cache', 'plugin_forms'], $upperPlan165()['selectedPlan']['matchedStat4Keys']),
];

<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan;

$columnTerm = static fn (string $column, string $operator, mixed $right = null): array => ['left' => ['column' => $column], 'operator' => $operator, 'right' => $right];
$exprRangeTerm = static fn (string $expression, string $operator, mixed $right): array => ['left' => ['expression' => $expression], 'operator' => $operator, 'right' => $right];

$rows = static fn (): array => [
 ['rowid' => 1, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'SiteURL', 'option_value' => 'https://example.test', 'updated_at' => 10],
 ['rowid' => 2, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'Plugin_Cache', 'option_value' => 'warm', 'updated_at' => 40],
 ['rowid' => 3, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_cache', 'option_value' => 'fresh', 'updated_at' => 50],
 ['rowid' => 4, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_forms', 'option_value' => 'forms', 'updated_at' => 60],
 ['rowid' => 5, 'blog_id' => 1, 'autoload' => 'no', 'option_name' => 'plugin_cache', 'option_value' => 'lazy', 'updated_at' => 70],
 ['rowid' => 6, 'blog_id' => 2, 'autoload' => 'yes', 'option_name' => 'plugin_cache', 'option_value' => 'network', 'updated_at' => 80],
 ['rowid' => 7, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => null, 'option_value' => 'null-name', 'updated_at' => 90],
 ['rowid' => 8, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_seo', 'option_value' => 'seo', 'updated_at' => 100],
];

$source = static function (string $name, int $cookie, int $stat4, int $root, array $rows): array {
 return [
 'name' => $name,
 'schemaCookie' => $cookie,
 'stat4Generation' => $stat4,
 'rows' => $rows,
 'indexes' => [[
 'name' => 'idx_wp_options_lower_name_recent_autoload_stat4_partial_range',
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

$preparedSource = static fn (): array => $source('prepared-wp-options-stat4-expression-partial-range-', 1650, 31, 16501, array_slice($rows(), 0, 5));
$currentSource = static fn (): array => $source('current-wp-options-stat4-expression-partial-range-', 1654, 37, 16531, $rows());
$queryTerms = static fn (): array => [
 $exprRangeTerm('LOWER( option_name )', '>=', 'plugin_cache'),
 $columnTerm('blog_id', '=', 1),
 $columnTerm('autoload', '=', 'yes'),
 $columnTerm('option_name', 'IS NOT NULL'),
 $columnTerm('updated_at', '>=', 40),
 $columnTerm('updated_at', '<', 90),
];
$plan = static fn (?array $prepared = null, ?array $current = null, ?array $queryInput = null): array => SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan::materializePartialRangeCurrentSource(
 $prepared ?? $preparedSource(),
 $current ?? $currentSource(),
 $queryInput ?? $queryTerms(),
 ['option_name', 'option_value', 'updated_at']
);
$freshPlan = static function () use ($currentSource, $plan): array {
 $source = $currentSource();

 return $plan($source, $source);
};
$unprovedPlan = static fn (): array => $plan(null, null, [
 $exprRangeTerm('lower(option_name)', '>=', 'plugin_cache'),
 $columnTerm('blog_id', '=', 1),
 $columnTerm('autoload', '=', 'yes'),
 $columnTerm('option_name', 'IS NOT NULL'),
 $columnTerm('updated_at', '>=', 20),
 $columnTerm('updated_at', '<', 90),
]);
$upperRangePlan = static fn (): array => $plan(null, null, [
 $exprRangeTerm('lower(option_name)', '<', 'plugin_seo'),
 $columnTerm('blog_id', '=', 1),
 $columnTerm('autoload', '=', 'yes'),
 $columnTerm('option_name', 'IS NOT NULL'),
 $columnTerm('updated_at', '>=', 40),
 $columnTerm('updated_at', '<', 90),
]);

return [
 'planner stat4 expression partial range current source status ready' => static fn (TestRunner $t) => $t->same('stat4-expression-partial-range-current-source-ready', $plan()['status']),
 'planner stat4 expression partial range current source selects current' => static fn (TestRunner $t) => $t->same('current', $plan()['selectedSource']),
 'planner stat4 expression partial range current source stale prepared' => static fn (TestRunner $t) => $t->same(true, $plan()['stalePreparedStatement']),
 'planner stat4 expression partial range current source reprepare required' => static fn (TestRunner $t) => $t->same(true, $plan()['reprepareRequired']),
 'planner stat4 expression partial range current source schema changed' => static fn (TestRunner $t) => $t->same(true, $plan()['schemaCookieChanged']),
 'planner stat4 expression partial range current source stat4 changed' => static fn (TestRunner $t) => $t->same(true, $plan()['stat4GenerationChanged']),
 'planner stat4 expression partial range current source signature changed' => static fn (TestRunner $t) => $t->same(true, $plan()['indexSignatureChanged']),
 'planner stat4 expression partial range current source selected index' => static fn (TestRunner $t) => $t->same('idx_wp_options_lower_name_recent_autoload_stat4_partial_range', $plan()['selectedPlan']['name']),
 'planner stat4 expression partial range current source root page' => static fn (TestRunner $t) => $t->same(16531, $plan()['selectedPlan']['rootPage']),
 'planner stat4 expression partial range current source expression' => static fn (TestRunner $t) => $t->same('lower(option_name)', $plan()['selectedPlan']['expression']),
 'planner stat4 expression partial range current source operator marker' => static fn (TestRunner $t) => $t->same(['>='], $plan()['rangeConstraintOperators']),
 'planner stat4 expression partial range current source partial range operators' => static fn (TestRunner $t) => $t->same(['>=', '<'], $plan()['partialRangePredicateOperators']),
 'planner stat4 expression partial range current source partial implied' => static fn (TestRunner $t) => $t->same(true, $plan()['selectedPlan']['partialPredicateImplied']),
 'planner stat4 expression partial range current source stat4 used' => static fn (TestRunner $t) => $t->same(true, $plan()['selectedPlan']['stat4Used']),
 'planner stat4 expression partial range current source matched sample count' => static fn (TestRunner $t) => $t->same(4, $plan()['selectedPlan']['stat4MatchedSamples']),
 'planner stat4 expression partial range current source stat4 estimate' => static fn (TestRunner $t) => $t->same(5, $plan()['selectedPlan']['stat4Estimate']),
 'planner stat4 expression partial range current source noncovering cost' => static fn (TestRunner $t) => $t->same(17, $plan()['selectedPlan']['estimatedCost']),
 'planner stat4 expression partial range current source matched keys' => static fn (TestRunner $t) => $t->same(['plugin_cache', 'plugin_forms', 'plugin_seo', 'siteurl'], $plan()['selectedPlan']['matchedStat4Keys']),
 'planner stat4 expression partial range current source matched stat4 rowids' => static fn (TestRunner $t) => $t->same([2, 4, 8, 1], $plan()['selectedPlan']['matchedStat4Rowids']),
 'planner stat4 expression partial range current source matched row count' => static fn (TestRunner $t) => $t->same(3, $plan()['selectedPlan']['matchedRowCount']),
 'planner stat4 expression partial range current source matched rowids' => static fn (TestRunner $t) => $t->same([2, 3, 4], $plan()['selectedPlan']['matchedRowids']),
 'planner stat4 expression partial range current source excludes stale siteurl' => static fn (TestRunner $t) => $t->same(false, in_array(1, $plan()['selectedPlan']['matchedRowids'], true)),
 'planner stat4 expression partial range current source excludes autoload no' => static fn (TestRunner $t) => $t->same(false, in_array(5, $plan()['selectedPlan']['matchedRowids'], true)),
 'planner stat4 expression partial range current source excludes blog two' => static fn (TestRunner $t) => $t->same(false, in_array(6, $plan()['selectedPlan']['matchedRowids'], true)),
 'planner stat4 expression partial range current source excludes null name' => static fn (TestRunner $t) => $t->same(false, in_array(7, $plan()['selectedPlan']['matchedRowids'], true)),
 'planner stat4 expression partial range current source excludes upper bound' => static fn (TestRunner $t) => $t->same(false, in_array(8, $plan()['selectedPlan']['matchedRowids'], true)),
 'planner stat4 expression partial range current source first payload' => static fn (TestRunner $t) => $t->same('warm', $plan()['matchedRows'][0]['payload']['option_value']),
 'planner stat4 expression partial range current source last payload' => static fn (TestRunner $t) => $t->same('forms', $plan()['matchedRows'][2]['payload']['option_value']),
 'planner stat4 expression partial range current source row stream signature' => static fn (TestRunner $t) => $t->same(64, strlen($plan()['selectedPlan']['rowStreamSignature'])),
 'planner stat4 expression partial range current source current next first' => static fn (TestRunner $t) => $t->same(3, $plan()['currentNextRows'][0]['next']['rowid']),
 'planner stat4 expression partial range current source current next final eof' => static fn (TestRunner $t) => $t->same(null, $plan()['currentNextRows'][2]['next']),
 'planner stat4 expression partial range current source stat4 current next count' => static fn (TestRunner $t) => $t->same(4, count($plan()['selectedPlan']['stat4CurrentNext'])),
 'planner stat4 expression partial range current source stat4 matched current next first' => static fn (TestRunner $t) => $t->same('plugin_forms', $plan()['selectedPlan']['stat4MatchedCurrentNext'][0]['next']['key']),
 'planner stat4 expression partial range current source stat4 matched eof' => static fn (TestRunner $t) => $t->same(null, $plan()['selectedPlan']['stat4MatchedCurrentNext'][3]['next']),
 'planner stat4 expression partial range current source table lookup required' => static fn (TestRunner $t) => $t->same(true, $plan()['tableLookupRequired']),
 'planner stat4 expression partial range current source residual required' => static fn (TestRunner $t) => $t->same(true, $plan()['residualPredicateRequired']),
 'planner stat4 expression partial range current source cursor open read' => static fn (TestRunner $t) => $t->same('OpenRead', $plan()['cursorProgram'][0]['opcode']),
 'planner stat4 expression partial range current source cursor seek' => static fn (TestRunner $t) => $t->same('SeekStat4Expression', $plan()['cursorProgram'][1]['opcode']),
 'planner stat4 expression partial range current source cursor deferred seek' => static fn (TestRunner $t) => $t->same('DeferredSeek', $plan()['cursorProgram'][3]['opcode']),
 'planner stat4 expression partial range current source cursor result count' => static fn (TestRunner $t) => $t->same(3, $plan()['cursorProgram'][7]['rowCount']),
 'planner stat4 expression partial range current source cursor next' => static fn (TestRunner $t) => $t->same('Next', $plan()['cursorProgram'][8]['opcode']),
 'planner stat4 expression partial range current source fence cookie' => static fn (TestRunner $t) => $t->same(1654, $plan()['stat4Fence']['schemaCookie']),
 'planner stat4 expression partial range current source fence stat4' => static fn (TestRunner $t) => $t->same(37, $plan()['stat4Fence']['stat4Generation']),
 'planner stat4 expression partial range current source fence expression' => static fn (TestRunner $t) => $t->same('lower(option_name)', $plan()['stat4Fence']['expressionSignature']),
 'planner stat4 expression partial range current source fence signatures' => static fn (TestRunner $t) => $t->same([64, 64, 64, 64], array_map('strlen', [$plan()['stat4Fence']['sourceSignature'], $plan()['stat4Fence']['partialPredicateSignature'], $plan()['stat4Fence']['stat4SampleSignature'], $plan()['stat4Fence']['rowStreamSignature']])),
 'planner stat4 expression partial range current source prepared root' => static fn (TestRunner $t) => $t->same(16501, $plan()['preparedSource']['rootPage']),
 'planner stat4 expression partial range current source current usable' => static fn (TestRunner $t) => $t->same(true, $plan()['currentSource']['usable']),
 'planner stat4 expression partial range current source detail' => static fn (TestRunner $t) => $t->contains('STAT4 PARTIAL RANGE', $plan()['detail']),
 'planner stat4 expression partial range current source dependency marker' => static fn (TestRunner $t) => $t->same(['sqlite-sqlplanner-stat4-expression-partial-range-current-source'], $plan()['dependencies']),
 'planner stat4 expression partial range current source dependency closure' => static fn (TestRunner $t) => $t->contains('no new support component needed', $plan()['dependency_closure']),
 'planner stat4 expression partial range current source non overlap' => static fn (TestRunner $t) => $t->contains('one-sided range constraints', $plan()['non_overlap']),
 'planner stat4 expression partial range current source fresh reuses prepared' => static fn (TestRunner $t) => $t->same('prepared', $freshPlan()['selectedSource']),
 'planner stat4 expression partial range current source fresh rowids' => static fn (TestRunner $t) => $t->same([2, 3, 4], $freshPlan()['selectedPlan']['matchedRowids']),
 'planner stat4 expression partial range current source unproved falls back' => static fn (TestRunner $t) => $t->same('requires-next-stage', $unprovedPlan()['status']),
 'planner stat4 expression partial range current source upper range ready' => static fn (TestRunner $t) => $t->same('stat4-expression-partial-range-current-source-ready', $upperRangePlan()['status']),
 'planner stat4 expression partial range current source upper range rowids' => static fn (TestRunner $t) => $t->same([2, 3, 4], $upperRangePlan()['selectedPlan']['matchedRowids']),
 'planner stat4 expression partial range current source upper range keys' => static fn (TestRunner $t) => $t->same(['plugin_cache', 'plugin_forms'], $upperRangePlan()['selectedPlan']['matchedStat4Keys']),
];

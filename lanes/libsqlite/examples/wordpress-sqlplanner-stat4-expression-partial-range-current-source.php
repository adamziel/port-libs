<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$prepared = [
 'name' => 'prepared-wp-options-stat4-expression-partial-range-',
 'schemaCookie' => 1650,
 'stat4Generation' => 31,
 'rows' => [
 ['rowid' => 2, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'Plugin_Cache', 'option_value' => 'warm', 'updated_at' => 40],
 ],
 'indexes' => [[
 'name' => 'idx_wp_options_lower_name_recent_autoload_stat4_partial_range',
 'rootPage' => 16501,
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
 ['neq' => '1', 'nlt' => '0', 'ndlt' => '0', 'sample' => ['plugin_cache', 2]],
 ],
 ]],
];

$current = $prepared;
$current['name'] = 'current-wp-options-stat4-expression-partial-range-';
$current['schemaCookie'] = 1654;
$current['stat4Generation'] = 37;
$current['rows'] = [
 ['rowid' => 1, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'SiteURL', 'option_value' => 'https://example.test', 'updated_at' => 10],
 ['rowid' => 2, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'Plugin_Cache', 'option_value' => 'warm', 'updated_at' => 40],
 ['rowid' => 3, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_cache', 'option_value' => 'fresh', 'updated_at' => 50],
 ['rowid' => 4, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_forms', 'option_value' => 'forms', 'updated_at' => 60],
 ['rowid' => 8, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_seo', 'option_value' => 'seo', 'updated_at' => 100],
];
$current['indexes'][0]['rootPage'] = 16531;
$current['indexes'][0]['stat4Samples'] = [
 ['neq' => '2', 'nlt' => '0', 'ndlt' => '0', 'sample' => ['plugin_cache', 2]],
 ['neq' => '1', 'nlt' => '2', 'ndlt' => '1', 'sample' => ['plugin_forms', 4]],
 ['neq' => '1', 'nlt' => '3', 'ndlt' => '2', 'sample' => ['plugin_seo', 8]],
 ['neq' => '1', 'nlt' => '4', 'ndlt' => '3', 'sample' => ['siteurl', 1]],
];

$plan = SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan::materializePartialRangeCurrentSource(
 $prepared,
 $current,
 [
 ['left' => ['expression' => 'LOWER(option_name)'], 'operator' => '>=', 'right' => 'plugin_cache'],
 ['left' => ['column' => 'blog_id'], 'operator' => '=', 'right' => 1],
 ['left' => ['column' => 'autoload'], 'operator' => '=', 'right' => 'yes'],
 ['left' => ['column' => 'option_name'], 'operator' => 'IS NOT NULL'],
 ['left' => ['column' => 'updated_at'], 'operator' => '>=', 'right' => 40],
 ['left' => ['column' => 'updated_at'], 'operator' => '<', 'right' => 90],
 ],
 ['option_name', 'option_value', 'updated_at']
);

if (in_array('--self-test', $argv, true)) {
 if (
 ($plan['status'] ?? null) !== 'stat4-expression-partial-range-current-source-ready'
 || ($plan['selectedPlan']['matchedRowids'] ?? []) !== [2, 3, 4]
 || ($plan['partialRangePredicateOperators'] ?? []) !== ['>=', '<']
 || ($plan['selectedSource'] ?? null) !== 'current'
 ) {
 fwrite(STDERR, "wordpress-sqlplanner-stat4-expression-partial-range-current-source self-test failed\n");
 exit(1);
 }

 echo "wordpress-sqlplanner-stat4-expression-partial-range-current-source self-test passed\n";
 exit(0);
}

echo json_encode([
 'scenario' => 'wordpress-sqlplanner-stat4-expression-partial-range-current-source',
 'status' => $plan['status'],
 'selectedSource' => $plan['selectedSource'],
 'rangeConstraintOperators' => $plan['rangeConstraintOperators'],
 'partialRangePredicateOperators' => $plan['partialRangePredicateOperators'],
 'estimatedRows' => $plan['selectedPlan']['estimatedRows'] ?? null,
 'matchedRowids' => $plan['selectedPlan']['matchedRowids'] ?? [],
 'wordpressUse' => 'Preview copied wp_options import diagnostics where one-sided LOWER(option_name) and updated_at ranges prove a partial expression index from the current STAT4 source after ANALYZE refresh, without requiring ext/sqlite.',
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;

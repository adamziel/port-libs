<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/SQLiteCreateIndex.php';
require_once __DIR__ . '/../src/SQLiteIndexColumn.php';
require_once __DIR__ . '/../src/SQLiteIndexPredicate.php';
require_once __DIR__ . '/../src/SQLiteSelectExpressionIndexPlan.php';
require_once __DIR__ . '/../src/SQLiteStat4ExpressionCoveringCurrentSourceNextPlan.php';
require_once __DIR__ . '/../src/SQLitePlannerStat4ExpressionCoveringCurrentSourceNextPlan.php';

use PortLibs\LibSqlite\SQLitePlannerStat4ExpressionCoveringCurrentSourceNextPlan;

$lower = ['function' => 'lower', 'column' => 'option_name'];
$predicate = [
    'operator' => 'AND',
    'terms' => [
        ['operator' => '=', 'left' => $lower, 'right' => 'plugin_cache'],
        ['operator' => '=', 'left' => ['column' => 'autoload'], 'right' => 'yes'],
    ],
];
$index = [
    'name' => 'idx_wp_options_lower_point_covering_stat4_point',
    'rootPage' => 14401,
    'estimatedRows' => 180,
    'coveringColumns' => ['option_name', 'autoload', 'option_value', 'option_id', 'blog_id'],
    'stat4Samples' => [
        ['neq' => '3 1', 'nlt' => '2 1', 'ndlt' => '1 1', 'sample' => ['plugin_cache', 20]],
    ],
    'sql' => "CREATE INDEX idx_wp_options_lower_point_covering_stat4_point ON wp_options(lower(option_name), option_id, option_value, blog_id) WHERE autoload = 'yes'",
];
$prepared = [
    'name' => 'prepared-application-stat4-expression-covering-current-source-point',
    'schemaCookie' => 1440,
    'stat4Generation' => 81,
    'indexes' => [$index],
    'rows' => [
        ['rowid' => 101, 'option_id' => 101, 'option_name' => 'Plugin_Cache', 'autoload' => 'yes', 'option_value' => 'prepared-cache-a', 'blog_id' => 1],
        ['rowid' => 102, 'option_id' => 102, 'option_name' => 'plugin_cache', 'autoload' => 'yes', 'option_value' => 'prepared-cache-b', 'blog_id' => 2],
    ],
];
$current = $prepared;
$current['name'] = 'current-application-stat4-expression-covering-current-source-point';
$current['schemaCookie'] = 1447;
$current['stat4Generation'] = 88;
$current['indexes'][0]['rootPage'] = 14477;
$current['indexes'][0]['estimatedRows'] = 42;
$current['indexes'][0]['stat4Samples'] = [
    ['neq' => '2 1', 'nlt' => '1 1', 'ndlt' => '1 1', 'sample' => ['plugin_cache', 202]],
];
$current['rows'] = [
    ['rowid' => 202, 'option_id' => 202, 'option_name' => 'plugin_cache', 'autoload' => 'yes', 'option_value' => 'current-cache-a', 'blog_id' => 7],
    ['rowid' => 203, 'option_id' => 203, 'option_name' => 'Plugin_Cache', 'autoload' => 'yes', 'option_value' => 'current-cache-b', 'blog_id' => 8],
];

$plan = SQLitePlannerStat4ExpressionCoveringCurrentSourceNextPlan::materializePointPredicateCurrentSource(
    $prepared,
    $current,
    $predicate,
    [$lower, ['column' => 'option_id']],
    ['option_name', 'autoload', 'option_value', 'option_id', 'blog_id'],
    [$lower],
);

$summary = [
    'scenario' => 'application-planner-stat4-expression-covering-current-source-point',
    'status' => $plan['status'],
    'selectedIndex' => $plan['selectedPlan']['name'] ?? null,
    'selectedRootPage' => $plan['selectedPlan']['rootPage'] ?? null,
    'preparedRowids' => $plan['preparedCoveringRowids'],
    'currentRowids' => $plan['currentCoveringRowids'],
    'staleRejectedRowids' => $plan['staleCoveringRejectedRowids'],
    'currentAdmittedRowids' => $plan['currentCoveringAdmittedRowids'],
    'tableLookupElided' => $plan['cursorTape']['tableLookupElidedAfterCurrentSourceRecheck'],
    'applicationUse' => 'Copied wp_options imports can reprepare a stale lower(option_name) STAT4 expression-covering point lookup after ANALYZE and stream current plugin_cache rows directly from the covering index without table rowid seeks.',
];

if (PHP_SAPI === 'cli' && basename(__FILE__) === basename($_SERVER['SCRIPT_FILENAME'] ?? '')) {
    if (($summary['status'] ?? null) !== 'stat4-expression-covering-current-source-point-ready') {
        fwrite(STDERR, "expected point STAT4 expression covering current-source plan\n");
        exit(1);
    }
    if (($summary['preparedRowids'] ?? []) !== [101, 102] || ($summary['currentRowids'] ?? []) !== [202, 203]) {
        fwrite(STDERR, "expected stale prepared row stream to be replaced by current source row stream\n");
        exit(1);
    }
    if (($summary['tableLookupElided'] ?? null) !== true) {
        fwrite(STDERR, "expected covering expression cursor without table lookup\n");
        exit(1);
    }

    echo "application-planner-stat4-expression-covering-current-source-point self-test passed\n";
}

return $summary;

<?php

declare(strict_types=1);

require_once dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\LibSqlite\SQLitePlannerSubqueryPartialIndexCurrentSourceNextPlan;

$prepared = [
    'name' => 'prepared-wp-options-plugin-subquery-current_source',
    'schemaCookie' => 104,
    'stat4Generation' => 41,
    'indexes' => [[
        'name' => 'idx_wp_options_lower_plugin_subquery_current_source',
        'rootPage' => 10601,
        'estimatedRows' => 80,
        'coveringColumns' => ['option_name', 'autoload', 'option_value'],
        'sql' => "CREATE INDEX idx_wp_options_lower_plugin_subquery_current_source ON wp_options(lower(option_name), autoload, option_value) WHERE lower(option_name) >= 'plugin_'",
    ]],
];

$current = $prepared;
$current['name'] = 'current-wp-options-plugin-subquery-current_source';
$current['schemaCookie'] = 106;
$current['stat4Generation'] = 43;
$current['indexes'][0]['rootPage'] = 10610;

$predicate = [
    'operator' => 'IN_SUBQUERY',
    'left' => ['function' => 'lower', 'column' => 'option_name'],
    'subquery' => [
        'sourceName' => 'active_plugin_names',
        'column' => 'selected_name',
        'rows' => [
            ['selected_name' => 'plugin_cache'],
            ['selected_name' => 'plugin_forms'],
            ['selected_name' => 'plugin_cache'],
            ['selected_name' => 'plugin_security'],
        ],
        'correlatedOuterColumns' => ['blog_id', 'autoload'],
    ],
];

$plan = SQLitePlannerSubqueryPartialIndexCurrentSourceNextPlan::materializeSubqueryPartialIndexPlan(
    $prepared,
    $current,
    $predicate,
    ['option_name', 'autoload', 'option_value']
);

$summary = [
    'scenario' => 'application-planner-subquery-partial-index-current_source-current_source',
    'status' => $plan['status'],
    'selectedSource' => $plan['selectedSource'],
    'selectedIndex' => $plan['selectedPlan']['name'] ?? null,
    'rootPage' => $plan['selectedPlan']['rootPage'] ?? null,
    'subqueryValues' => $plan['subquery']['values'],
    'duplicatesRemoved' => $plan['subquery']['duplicatesRemoved'],
    'partialPredicateImplied' => $plan['selectedPlan']['partialPredicateImplied'] ?? null,
    'tableLookupElided' => $plan['cursorTape']['tableLookupElided'] ?? null,
    'applicationUse' => 'Copied wp_options plugin scans can reprepare a stale statement and prove a lower(option_name) partial covering index from bounded IN-subquery values before import diagnostics, without ext/sqlite.',
    'dependencyClosure' => $plan['dependency_closure'],
];

if (in_array('--self-test', $argv, true)) {
    if (($summary['status'] ?? null) !== 'subquery-partial-index-current-source-ready') {
        fwrite(STDERR, "expected ready subquery partial-index plan\n");
        exit(1);
    }
    if (($summary['selectedSource'] ?? null) !== 'current' || ($summary['rootPage'] ?? null) !== 10610) {
        fwrite(STDERR, "expected current_source root page\n");
        exit(1);
    }
    if (($summary['subqueryValues'] ?? []) !== ['plugin_cache', 'plugin_forms', 'plugin_security']) {
        fwrite(STDERR, "expected deduped plugin subquery values\n");
        exit(1);
    }

    echo "application-planner-subquery-partial-index-current_source-current_source self-test passed\n";
    exit(0);
}

echo json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;

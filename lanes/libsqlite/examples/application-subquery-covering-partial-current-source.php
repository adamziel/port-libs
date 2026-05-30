<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePlannerSubqueryCoveringPartialCurrentSourceNextPlan;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$prepared = [
    'name' => 'prepared-wp-options-plugin-payload',
    'schemaCookie' => 114,
    'stat4Generation' => 51,
    'indexes' => [[
        'name' => 'idx_wp_options_plugin_payload_lower',
        'rootPage' => 11501,
        'estimatedRows' => 60,
        'coveringColumns' => ['option_name'],
        'sql' => "CREATE INDEX idx_wp_options_plugin_payload_lower ON wp_options(lower(option_name)) WHERE lower(option_name) >= 'plugin_'",
    ]],
];

$current = $prepared;
$current['name'] = 'current-wp-options-plugin-payload';
$current['schemaCookie'] = 115;
$current['stat4Generation'] = 52;
$current['indexes'][0]['rootPage'] = 11510;

$plan = SQLitePlannerSubqueryCoveringPartialCurrentSourceNextPlan::materializeSubqueryCoveringPartialCurrentSource(
    $prepared,
    $current,
    [
        'operator' => 'IN_SUBQUERY',
        'left' => ['function' => 'lower', 'column' => 'option_name'],
        'subquery' => [
            'sourceName' => 'active_plugin_option_payloads',
            'column' => 'selected_name',
            'projectedColumns' => ['selected_name', 'option_name', 'autoload', 'option_value'],
            'rows' => [
                ['selected_name' => 'plugin_cache', 'option_name' => 'plugin_cache', 'autoload' => 'yes', 'option_value' => '{"ttl":3600}'],
                ['selected_name' => 'plugin_forms', 'option_name' => 'plugin_forms', 'autoload' => 'no', 'option_value' => '{"enabled":true}'],
                ['selected_name' => 'plugin_security', 'option_name' => 'plugin_security', 'autoload' => 'yes', 'option_value' => '{"rules":4}'],
            ],
            'correlatedOuterColumns' => ['blog_id', 'autoload'],
        ],
    ],
    ['option_name', 'autoload', 'option_value'],
);

echo json_encode([
    'scenario' => 'application-subquery-covering-partial-current-source-next115',
    'status' => $plan['status'],
    'selectedSource' => $plan['selectedSource'],
    'selectedIndex' => $plan['selectedPlan']['name'] ?? null,
    'rootPage' => $plan['selectedPlan']['rootPage'] ?? null,
    'nextSource' => $plan['selectedPlan']['nextSource'] ?? null,
    'tableLookupElided' => $plan['cursorTape']['tableLookupElided'] ?? false,
    'subqueryValues' => $plan['subquery']['values'],
    'projectedColumns' => $plan['subquery']['projectedColumns'],
    'applicationUse' => 'Preview copied wp_options plugin payload imports where a current-source partial lower(option_name) index probes IN-subquery keys while the subquery projection supplies autoload and option_value without a table lookup.',
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;

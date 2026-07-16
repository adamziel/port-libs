<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/SQLiteStat4RangeOrderCurrentSourceNextPlan.php';

use PortLibs\LibSqlite\SQLiteStat4RangeOrderCurrentSourceNextPlan;

$prepared = [
    'name' => 'wp-options-before-analyze',
    'schemaCookie' => 21,
    'stat4Generation' => 7,
    'indexName' => 'idx_wp_options_option_name_stat4',
    'rangeColumn' => 'option_name',
    'lower' => 'plugin_',
    'upper' => 'plugin_theta',
    'upperInclusive' => true,
    'collation' => 'NOCASE',
    'rows' => [
        ['rowid' => 1, 'option_name' => 'plugin_alpha'],
        ['rowid' => 2, 'option_name' => 'plugin_beta'],
        ['rowid' => 3, 'option_name' => 'siteurl'],
        ['rowid' => 4, 'option_name' => 'plugin_gamma'],
    ],
    'stat4Samples' => [
        ['value' => 'plugin_alpha', 'nEq' => 3, 'nLt' => 0, 'nDLt' => 0],
        ['value' => 'plugin_beta', 'nEq' => 2, 'nLt' => 3, 'nDLt' => 1],
        ['value' => 'plugin_gamma', 'nEq' => 4, 'nLt' => 5, 'nDLt' => 2],
    ],
];

$current = $prepared;
$current['name'] = 'wp-options-after-analyze';
$current['schemaCookie'] = 22;
$current['stat4Generation'] = 8;
$current['rows'] = [
    ['rowid' => 1, 'option_name' => 'Plugin_Alpha'],
    ['rowid' => 2, 'option_name' => 'plugin_beta'],
    ['rowid' => 3, 'option_name' => 'siteurl'],
    ['rowid' => 4, 'option_name' => 'plugin_delta'],
    ['rowid' => 5, 'option_name' => 'plugin_gamma'],
    ['rowid' => 6, 'option_name' => 'plugin_theta'],
];
$current['stat4Samples'] = [
    ['value' => 'plugin_alpha', 'nEq' => 1, 'nLt' => 0, 'nDLt' => 0],
    ['value' => 'plugin_beta', 'nEq' => 1, 'nLt' => 1, 'nDLt' => 1],
    ['value' => 'plugin_delta', 'nEq' => 2, 'nLt' => 2, 'nDLt' => 2],
    ['value' => 'plugin_gamma', 'nEq' => 1, 'nLt' => 4, 'nDLt' => 3],
    ['value' => 'plugin_theta', 'nEq' => 1, 'nLt' => 5, 'nDLt' => 4],
];

$plan = SQLiteStat4RangeOrderCurrentSourceNextPlan::compareRangeOrder($prepared, $current, [['column' => 'option_name']]);

if (($argv[1] ?? '') === '--self-test') {
    assert($plan['selectedSource'] === 'current');
    assert($plan['selectedPlan']['rowids'] === [1, 2, 4, 5, 6]);
    assert($plan['selectedPlan']['orderByMode'] === 'range');
    assert($plan['selectedPlan']['stat4RangeSamples'] === 5);
    echo "application-planner-stat4-range-order-current-source-next97 self-test passed\n";
    return;
}

echo json_encode($plan, JSON_PRETTY_PRINT) . "\n";

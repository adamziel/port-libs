<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteJsonTablePlan;

$currentRows = [
    [
        'option_id' => 10,
        'option_name' => 'plugin_alpha_settings',
        'option_value' => '{"rules":["seo","cache"],"meta":{"enabled":true}}',
        'scan_root' => '$.rules',
    ],
    [
        'option_id' => 20,
        'option_name' => 'plugin_beta_settings',
        'option_value' => '{"rules":["forms"],"meta":{"enabled":false}}',
        'scan_root' => '$.rules',
    ],
];

$nextRows = [
    [
        'option_id' => 20,
        'option_name' => 'plugin_beta_settings',
        'option_value' => '{"rules":["forms","payments"],"meta":{"enabled":true}}',
        'scan_root' => '$.rules',
    ],
    [
        'option_id' => 10,
        'option_name' => 'plugin_alpha_settings',
        'option_value' => '{"rules":["seo","cache"],"meta":{"enabled":true}}',
        'scan_root' => '$.rules',
    ],
    [
        'option_id' => 30,
        'option_name' => 'plugin_gamma_settings',
        'option_value' => '{"rules":["media"],"meta":{"enabled":true}}',
        'scan_root' => '$.rules',
    ],
];

$plan = SQLiteJsonTablePlan::lateralCurrentSourcePlanner(
    $currentRows,
    $nextRows,
    'option_id',
    'option_value',
    'json_each',
    [['column' => 'type', 'operator' => '=', 'value' => 'text']],
    'scan_root',
    [['column' => 'key', 'direction' => 'ASC']],
);

if (in_array('--self-test', $argv, true)) {
    assert($plan['currentReaderPolicy'] === 'pin-current-lateral-json-source-by-host-key-until-cursor-reset');
    assert($plan['nextReaderPolicy'] === 'prepare-next-lateral-json-source-by-host-key');
    assert(array_column($plan['transitions'], 'hostKey') === ['10', '20', '30']);
    assert($plan['transitions'][0]['reason'] === 'lateral-current-source-host-row-reordered');
    assert($plan['transitions'][1]['reason'] === 'lateral-current-source-plan-changed');
    assert($plan['transitions'][2]['reason'] === 'next-lateral-current-source-host-row-added');
    assert(array_column($plan['transitions'][1]['next']['rows'], 'atom') === ['forms', 'payments']);
    echo "application-json-table-lateral-planner-current-source-next100 self-test passed\n";
    return;
}

echo json_encode([
    'currentReaderPolicy' => $plan['currentReaderPolicy'],
    'nextReaderPolicy' => $plan['nextReaderPolicy'],
    'replanReasons' => $plan['replanReasons'],
    'transitions' => array_map(
        static fn (array $transition): array => [
            'hostKey' => $transition['hostKey'],
            'currentHostIndex' => $transition['currentHostIndex'],
            'nextHostIndex' => $transition['nextHostIndex'],
            'hostReordered' => $transition['hostReordered'],
            'reason' => $transition['reason'],
            'currentRows' => $transition['currentRows'],
            'nextRows' => $transition['nextRows'],
        ],
        $plan['transitions'],
    ),
], JSON_PRETTY_PRINT) . "\n";

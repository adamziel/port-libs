<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteJsonTablePlan;

$currentOptions = [
    [
        'option_id' => 10,
        'option_name' => 'wp_plugin_alpha_rules',
        'option_value' => '{"rules":[{"slug":"seo","enabled":true},{"slug":"cache","enabled":false}]}',
        'scan_root' => '$.rules',
    ],
    [
        'option_id' => 20,
        'option_name' => 'wp_plugin_beta_rules',
        'option_value' => '{"rules":[]}',
        'scan_root' => '$.rules',
    ],
    [
        'option_id' => 30,
        'option_name' => 'wp_plugin_gamma_rules',
        'option_value' => '{"rules":[{"slug":"forms","enabled":true}]}',
        'scan_root' => '$.rules',
    ],
];

$nextOptions = [
    [
        'option_id' => 30,
        'option_name' => 'wp_plugin_gamma_rules',
        'option_value' => '{"rules":[{"slug":"forms","enabled":true}]}',
        'scan_root' => '$.rules',
    ],
    [
        'option_id' => 10,
        'option_name' => 'wp_plugin_alpha_rules',
        'option_value' => '{"rules":[{"slug":"seo","enabled":true},{"slug":"cache","enabled":true},{"slug":"media","enabled":true}]}',
        'scan_root' => '$.rules',
    ],
    [
        'option_id' => 40,
        'option_name' => 'wp_plugin_delta_rules',
        'option_value' => '{"rules":[{"slug":"shop","enabled":true}]}',
        'scan_root' => '$.rules',
    ],
    [
        'option_id' => 20,
        'option_name' => 'wp_plugin_beta_rules',
        'option_value' => '{"meta":{"version":2}}',
        'scan_root' => '$.meta',
    ],
];

$plan = SQLiteJsonTablePlan::lateralRowidHiddenCurrentSource(
    $currentOptions,
    $nextOptions,
    'option_id',
    'option_value',
    'json_each',
    [
        ['column' => 'root', 'operator' => '=', 'value' => '$.rules'],
        ['column' => '_rowid_', 'operator' => '>=', 'value' => 1],
        ['column' => 'type', 'operator' => '=', 'value' => 'object'],
    ],
    'scan_root',
    [['column' => 'id']],
    'left',
);

$summary = [
    'scenario' => 'application-json-table-lateral-rowid-hidden-current-source-next105',
    'hostOrderTransition' => $plan['hostOrderTransition'],
    'rowidTransitionsByOptionId' => array_map(
        static fn (array $transition): array => [
            'option_id' => $transition['hostKey'],
            'reason' => $transition['reason'],
            'currentRowids' => $transition['currentRowids'],
            'nextRowids' => $transition['nextRowids'],
            'ordinalChanged' => $transition['ordinalChanged'],
        ],
        $plan['rowidTransitions'],
    ),
    'replanReasons' => $plan['replanReasons'],
    'nextReaderPolicy' => $plan['nextReaderPolicy'],
    'dependencyClosure' => 'reuses the lane-local JSON table cursor, hidden constraint planner, and rowid alias provenance; no new support component required',
];

if (in_array('--self-test', $argv, true)) {
    assert($summary['hostOrderTransition']['current'] === [10, 20, 30]);
    assert($summary['hostOrderTransition']['next'] === [30, 10, 40, 20]);
    assert($summary['rowidTransitionsByOptionId'][0]['option_id'] === 10);
    assert($summary['rowidTransitionsByOptionId'][0]['currentRowids'] === [1, 2]);
    assert($summary['rowidTransitionsByOptionId'][0]['nextRowids'] === [1, 2, 3]);
    assert($summary['rowidTransitionsByOptionId'][2]['option_id'] === 30);
    assert($summary['rowidTransitionsByOptionId'][2]['reason'] === 'stable-lateral-hidden-rowid-source');
    assert($summary['rowidTransitionsByOptionId'][3]['reason'] === 'next-lateral-hidden-rowid-host-row-added');
    assert(in_array('lateral-hidden-rowid-tape-changed', $summary['replanReasons'], true));
    echo "application-json-table-lateral-rowid-hidden-current-source-next105 self-test passed\n";
    return;
}

echo json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;

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

$plan = SQLiteJsonTablePlan::lateralHiddenConstraintCurrentSource(
    $currentOptions,
    $nextOptions,
    'option_id',
    'option_value',
    'json_each',
    [
        ['column' => 'root', 'operator' => '=', 'value' => '$.rules'],
        ['column' => 'type', 'operator' => '=', 'value' => 'object'],
    ],
    'scan_root',
    [['column' => 'id']],
    'left',
);

$summary = [
    'scenario' => 'wordpress-json-table-lateral-hidden-constraint-current-source-next103',
    'hostOrderTransition' => $plan['hostOrderTransition'],
    'transitionReasonsByOptionId' => array_map(
        static fn (array $transition): array => [
            'option_id' => $transition['hostKey'],
            'reason' => $transition['reason'],
            'currentRows' => $transition['currentRows'],
            'nextRows' => $transition['nextRows'],
            'ordinalChanged' => $transition['ordinalChanged'],
        ],
        $plan['transitions'],
    ),
    'replanReasons' => $plan['replanReasons'],
    'nextReaderPolicy' => $plan['nextReaderPolicy'],
    'dependencyClosure' => 'reuses the native JSON table hidden-constraint planner and keyed host-row current/next source tracking; no new support component required',
];

if (in_array('--self-test', $argv, true)) {
    assert($summary['hostOrderTransition']['current'] === [10, 20, 30]);
    assert($summary['hostOrderTransition']['next'] === [30, 10, 40, 20]);
    assert($summary['transitionReasonsByOptionId'][0]['option_id'] === 10);
    assert($summary['transitionReasonsByOptionId'][0]['currentRows'] === 2);
    assert($summary['transitionReasonsByOptionId'][0]['nextRows'] === 3);
    assert($summary['transitionReasonsByOptionId'][2]['option_id'] === 30);
    assert($summary['transitionReasonsByOptionId'][2]['reason'] === 'stable-lateral-hidden-json-plan');
    assert($summary['transitionReasonsByOptionId'][3]['reason'] === 'next-lateral-hidden-host-row-added');
    assert(in_array('source-json-changed', $summary['replanReasons'], true));
}

echo json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;

<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteJsonTablePlan;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$currentOptions = [
    [
        'option_id' => 101,
        'option_name' => 'wp_plugin_rules',
        'option_value' => '{"rules":[{"slug":"seo","enabled":true},{"slug":"cache","enabled":false}]}',
        'json_root' => '$.rules',
        'wanted_type' => 'object',
    ],
    [
        'option_id' => 102,
        'option_name' => 'wp_theme_rules',
        'option_value' => '{"rules":[]}',
        'json_root' => '$.rules',
        'wanted_type' => 'object',
    ],
];

$nextOptions = [
    [
        'option_id' => 101,
        'option_name' => 'wp_plugin_rules',
        'option_value' => '{"rules":[{"slug":"seo","enabled":true},{"slug":"cache","enabled":true},{"slug":"media","enabled":true}]}',
        'json_root' => '$.rules',
        'wanted_type' => 'object',
    ],
    [
        'option_id' => 102,
        'option_name' => 'wp_theme_rules',
        'option_value' => '{"rules":[{"slug":"theme","enabled":true}]}',
        'json_root' => '$.missing',
        'wanted_type' => 'object',
    ],
    [
        'option_id' => 103,
        'option_name' => 'wp_shop_rules',
        'option_value' => '{"rules":[{"slug":"shop","enabled":true}]}',
        'json_root' => '$.rules',
        'wanted_type' => 'object',
    ],
];

$plan = SQLiteJsonTablePlan::lateralConstraintHidden(
    $currentOptions,
    $nextOptions,
    'option_id',
    'json_each',
    [
        ['column' => 'json', 'sourceColumn' => 'option_value'],
        ['column' => 'root', 'sourceColumn' => 'json_root'],
        ['column' => 'type', 'sourceColumn' => 'wanted_type'],
    ],
    [['column' => 'id']],
    'left',
);

$summary = [
    'scenario' => 'wordpress-json-table-lateral-constraint-hidden',
    'wordpressUse' => 'Copied wp_options diagnostics can replan a lateral json_each scan when hidden json/root constraints are sourced from the current option row, preserving left-join NULL extension, host additions, and current-to-next rule expansion without requiring ext/sqlite.',
    'currentReaderPolicy' => $plan['currentReaderPolicy'],
    'nextReaderPolicy' => $plan['nextReaderPolicy'],
    'replanRequired' => $plan['replanRequired'],
    'replanReasons' => $plan['replanReasons'],
    'hostOrderTransition' => $plan['hostOrderTransition'],
    'transitions' => array_map(
        static fn (array $transition): array => [
            'hostKey' => $transition['hostKey'],
            'reason' => $transition['reason'],
            'currentRows' => $transition['currentRows'],
            'nextRows' => $transition['nextRows'],
            'currentNullExtended' => $transition['currentNullExtended'],
            'nextNullExtended' => $transition['nextNullExtended'],
        ],
        $plan['transitions'],
    ),
];

if (($argv[1] ?? '') === '--self-test') {
    assert($summary['replanRequired'] === true);
    assert($summary['nextReaderPolicy'] === 'prepare-next-lateral-hidden-current-source-tape');
    assert($summary['transitions'][0]['currentRows'] === 2);
    assert($summary['transitions'][0]['nextRows'] === 3);
    assert($summary['transitions'][1]['nextNullExtended'] === true);
    assert($summary['transitions'][2]['reason'] === 'next-lateral-hidden-current-source-host-row-added');
    echo "wordpress-json-table-lateral-constraint-hidden self-test passed\n";
    return;
}

echo json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;

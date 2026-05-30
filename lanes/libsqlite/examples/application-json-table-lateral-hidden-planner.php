<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/SQLiteJsonTablePlan.php';
require_once __DIR__ . '/../src/SQLiteJsonEach.php';
require_once __DIR__ . '/../src/SQLiteJsonTree.php';
require_once __DIR__ . '/../src/SQLiteJsonInspection.php';
require_once __DIR__ . '/../src/SQLiteJsonCanonical.php';
require_once __DIR__ . '/../src/SQLiteJsonPath.php';
require_once __DIR__ . '/../src/SQLiteJson5Parser.php';
require_once __DIR__ . '/../src/SQLiteJsonB.php';
require_once __DIR__ . '/../src/SQLiteJsonValidity.php';
require_once __DIR__ . '/../src/SQLiteBlobValue.php';
require_once __DIR__ . '/../src/SQLiteJsonSubtypeValue.php';
require_once __DIR__ . '/../src/SQLiteSelectExpression.php';
require_once __DIR__ . '/../src/SQLiteSelectPredicate.php';
require_once __DIR__ . '/../src/SQLiteDatabase.php';

use PortLibs\LibSqlite\SQLiteJsonTablePlan;

$currentOptions = [
    [
        'option_id' => 1,
        'option_name' => 'plugin_alpha_settings',
        'option_value' => '{"rules":[{"name":"seo","enabled":true},{"name":"cache","enabled":false}]}',
        'scan_root' => '$.rules',
    ],
    [
        'option_id' => 2,
        'option_name' => 'plugin_beta_settings',
        'option_value' => '{"rules":[]}',
        'scan_root' => '$.rules',
    ],
];

$nextOptions = [
    [
        'option_id' => 1,
        'option_name' => 'plugin_alpha_settings',
        'option_value' => '{"rules":[{"name":"seo","enabled":true},{"name":"cache","enabled":true},{"name":"media","enabled":true}]}',
        'scan_root' => '$.rules',
    ],
    [
        'option_id' => 2,
        'option_name' => 'plugin_beta_settings',
        'option_value' => '{"rules":[{"name":"beta","enabled":true}]}',
        'scan_root' => '$.rules',
    ],
    [
        'option_id' => 3,
        'option_name' => 'plugin_delta_settings',
        'option_value' => '{"rules":[{"name":"delta","enabled":true}]}',
        'scan_root' => '$.rules',
    ],
];

$plan = SQLiteJsonTablePlan::lateralHiddenPlanner(
    $currentOptions,
    $nextOptions,
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

echo json_encode([
    'scenario' => 'application-json-table-lateral-hidden-planner',
    'function' => $plan['function'],
    'replanRequired' => $plan['replanRequired'],
    'replanReasons' => $plan['replanReasons'],
    'currentRowsByHost' => array_column($plan['current'], 'rowCount'),
    'nextRowsByHost' => array_column($plan['next'], 'rowCount'),
    'transitionReasons' => array_column($plan['transitions'], 'reason'),
    'nullExtensions' => array_map(
        static fn (array $transition): array => [
            'current' => $transition['currentNullExtended'],
            'next' => $transition['nextNullExtended'],
        ],
        $plan['transitions'],
    ),
    'dependencies' => $plan['dependencies'],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;

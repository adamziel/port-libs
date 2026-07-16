<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/SQLiteDatabase.php';
require_once __DIR__ . '/../src/SQLiteJson5Parser.php';
require_once __DIR__ . '/../src/SQLiteJsonB.php';
require_once __DIR__ . '/../src/SQLiteJsonCanonical.php';
require_once __DIR__ . '/../src/SQLiteJsonConstructor.php';
require_once __DIR__ . '/../src/SQLiteJsonInspection.php';
require_once __DIR__ . '/../src/SQLiteJsonPath.php';
require_once __DIR__ . '/../src/SQLiteJsonQuote.php';
require_once __DIR__ . '/../src/SQLiteJsonValidity.php';
require_once __DIR__ . '/../src/SQLiteBlobValue.php';
require_once __DIR__ . '/../src/SQLiteJsonSubtypeValue.php';
require_once __DIR__ . '/../src/SQLiteJsonEach.php';
require_once __DIR__ . '/../src/SQLiteJsonTree.php';
require_once __DIR__ . '/../src/SQLiteJsonTablePlan.php';

use PortLibs\LibSqlite\SQLiteJsonTablePlan;

$current = [
    'option_id' => 31,
    'option_name' => 'wp_plugin_nav_rules',
    'option_value' => '{"rules":[{"slug":"seo","enabled":true},{"slug":"cache","enabled":false},{"slug":"forms","enabled":true}]}',
    'json_root' => '$.rules',
];
$next = [
    'option_id' => 31,
    'option_name' => 'wp_plugin_nav_rules',
    'option_value' => '{"rules":[{"slug":"seo","enabled":true},{"slug":"forms","enabled":true},{"slug":"security","enabled":true}]}',
    'json_root' => '$.rules',
];

$plan = SQLiteJsonTablePlan::currentSourceConstraintPlanner(
    'json_each',
    $current,
    $next,
    'option_value',
    [
        ['column' => 'rowid', 'operator' => '=', 'value' => 2],
        ['column' => 'type', 'operator' => '=', 'value' => 'object'],
    ],
    'json_root',
);

$summary = [
    'option' => $current['option_name'],
    'idxStr' => $plan['current']['idxStr'],
    'rowidConstraintColumn' => $plan['current']['constraintUsage'][2]['column'],
    'currentRuleKey' => $plan['currentRows'][0]['key'] ?? null,
    'nextRuleKey' => $plan['nextRows'][0]['key'] ?? null,
    'currentEstimatedRows' => $plan['current']['estimatedRows'],
    'nextEstimatedRows' => $plan['next']['estimatedRows'],
    'replanRequired' => $plan['replanRequired'],
    'replanReasons' => $plan['replanReasons'],
];

if (in_array('--self-test', $argv, true)) {
    assert($summary['rowidConstraintColumn'] === 'id');
    assert($summary['idxStr'] === 'hidden:json:=|hidden:root:=|visible:id:=|visible:type:=');
    assert($summary['currentRuleKey'] === 1);
    assert($summary['nextRuleKey'] === 1);
    assert($summary['currentEstimatedRows'] === 1);
    assert($summary['nextEstimatedRows'] === 1);
    assert($summary['replanRequired'] === true);
    assert(in_array('source-json-changed', $summary['replanReasons'], true));
}

echo json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;

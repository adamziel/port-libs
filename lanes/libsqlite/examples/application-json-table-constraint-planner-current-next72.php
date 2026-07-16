<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteJsonTablePlan;

$currentSettings = '{"plugins":{"seo":{"enabled":true,"priority":2},"cache":{"enabled":false,"priority":7},"forms":{"enabled":true,"priority":4}}}';
$nextSettings = '{"plugins":{"seo":{"enabled":true,"priority":3},"cache":{"enabled":true,"priority":6},"forms":{"enabled":true,"priority":4},"shop":{"enabled":true,"priority":5}}}';

$currentConstraints = [
    ['column' => 'json', 'operator' => '=', 'value' => $currentSettings],
    ['column' => 'root', 'operator' => '=', 'value' => '$.plugins'],
    ['column' => 'key', 'operator' => 'IN', 'value' => ['seo', 'cache', 'forms']],
    ['column' => 'type', 'operator' => '=', 'value' => 'object'],
    ['column' => 'limit', 'operator' => '=', 'value' => 6],
];
$nextConstraints = [
    ['column' => 'json', 'operator' => '=', 'value' => $nextSettings],
    ['column' => 'root', 'operator' => '=', 'value' => '$.plugins'],
    ['column' => 'key', 'operator' => 'IN', 'value' => ['seo', 'cache', 'forms', 'shop']],
    ['column' => 'type', 'operator' => '=', 'value' => 'object'],
    ['column' => 'atom', 'operator' => 'IS NULL', 'value' => null],
    ['column' => 'limit', 'operator' => '=', 'value' => 8],
];

$plan = SQLiteJsonTablePlan::constraintPlannerComparison(
    'json_tree',
    $currentConstraints,
    $nextConstraints,
    [['column' => 'id']],
);

echo json_encode([
    'scenario' => 'application-json-table-constraint-planner-current-next72',
    'replanRequired' => $plan['replanRequired'],
    'replanReason' => $plan['replanReason'],
    'currentReaderPolicy' => $plan['currentReaderPolicy'],
    'nextReaderPolicy' => $plan['nextReaderPolicy'],
    'currentArguments' => count($plan['currentArguments']),
    'nextArguments' => count($plan['nextArguments']),
    'dependency' => $plan['dependencies'][0],
], JSON_PRETTY_PRINT) . PHP_EOL;

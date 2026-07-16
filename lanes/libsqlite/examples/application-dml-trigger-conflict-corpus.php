<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteDmlTriggerConflictPlan;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$options = [
    ['option_id' => 1, 'option_name' => 'siteurl'],
];
$audit = [
    ['option_name' => 'siteurl', 'audit' => 'old-siteurl'],
];
$incoming = [
    ['option_id' => 2, 'option_name' => 'home'],
    ['option_id' => 3, 'option_name' => 'siteurl'],
];
$triggers = [[
    'timing' => 'before',
    'event' => 'insert',
    'table' => 'side',
    'action' => 'insert',
    'conflict_action' => 'abort',
    'row' => ['option_name' => 'new.option_name', 'audit' => 'new.option_id'],
]];

$result = SQLiteDmlTriggerConflictPlan::insertRows($options, $audit, $incoming, $triggers, ['option_name'], 'replace');

echo json_encode([
    'applicationUse' => 'Preview wp_options import triggers where the outer INSERT OR REPLACE conflict policy controls side-table trigger conflicts without requiring ext/sqlite.',
    'insertedOptionIds' => array_column($result['inserted'], 'option_id'),
    'auditRows' => $result['side'],
    'triggerEffects' => $result['trigger_effects'],
    'changes' => $result['changes'],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";

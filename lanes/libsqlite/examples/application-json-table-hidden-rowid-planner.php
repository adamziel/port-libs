<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteJsonTablePlan;

$currentOption = [
    'option_name' => 'widget_plugin_settings',
    'option_value' => '{"plugins":[{"slug":"seo","enabled":true},{"slug":"cache","enabled":false}]}',
    'scan_root' => '$.plugins',
];

$nextOption = [
    'option_name' => 'widget_plugin_settings',
    'option_value' => '{"plugins":[{"slug":"seo","enabled":true},{"slug":"cache","enabled":true},{"slug":"forms","enabled":true}]}',
    'scan_root' => '$.plugins',
];

$plan = SQLiteJsonTablePlan::currentSourceHiddenRowidPlanner(
    'json_tree',
    $currentOption,
    $nextOption,
    'option_value',
    [['column' => 'rowid', 'operator' => '=', 'value' => 6]],
    'scan_root',
);

echo json_encode([
    'option' => $currentOption['option_name'],
    'dependency' => 'sqlite-json-table-hidden-rowid-planner',
    'currentRowids' => $plan['currentRowidSummary']['rowids'],
    'nextRowids' => $plan['nextRowidSummary']['rowids'],
    'transition' => $plan['rowTransitions'][0]['reason'] ?? null,
    'currentAtom' => $plan['rowTransitions'][0]['current']['atom'] ?? null,
    'nextAtom' => $plan['rowTransitions'][0]['next']['atom'] ?? null,
    'nextReaderPolicy' => $plan['nextReaderPolicy'],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;

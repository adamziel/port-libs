<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/SQLiteBlobValue.php';
require_once __DIR__ . '/../src/SQLiteJson5Parser.php';
require_once __DIR__ . '/../src/SQLiteJsonB.php';
require_once __DIR__ . '/../src/SQLiteJsonCanonical.php';
require_once __DIR__ . '/../src/SQLiteJsonEach.php';
require_once __DIR__ . '/../src/SQLiteJsonInspection.php';
require_once __DIR__ . '/../src/SQLiteJsonPath.php';
require_once __DIR__ . '/../src/SQLiteJsonSubtypeValue.php';
require_once __DIR__ . '/../src/SQLiteJsonTablePlan.php';
require_once __DIR__ . '/../src/SQLiteJsonTree.php';
require_once __DIR__ . '/../src/SQLiteJsonValidity.php';

use PortLibs\LibSqlite\SQLiteJsonTablePlan;

$currentRows = [
    ['option_id' => 1, 'option_name' => 'plugin_alpha_settings', 'option_value' => '{"rules":["seo","cache"]}', 'scan_root' => '$.rules'],
    ['option_id' => 2, 'option_name' => 'plugin_empty_settings', 'option_value' => '{"rules":[]}', 'scan_root' => '$.rules'],
];
$nextRows = [
    ['option_id' => 1, 'option_name' => 'plugin_alpha_settings', 'option_value' => '{"rules":["seo","shop","cache"]}', 'scan_root' => '$.rules'],
    ['option_id' => 3, 'option_name' => 'plugin_gamma_settings', 'option_value' => '{"rules":["gallery"]}', 'scan_root' => '$.rules'],
];

$plan = SQLiteJsonTablePlan::lateralRowidComparison(
    $currentRows,
    $nextRows,
    'option_value',
    'json_each',
    [['column' => 'atom', 'operator' => 'IS NOT NULL', 'value' => null]],
    'scan_root',
    ['key', 'atom', 'fullkey'],
    'left',
    'rule_',
);

$summary = [
    'scenario' => 'wordpress-json-table-lateral-rowid-current-next81',
    'currentRows' => count($plan['current']),
    'nextRows' => count($plan['next']),
    'nextPolicy' => $plan['nextReaderPolicy'],
    'currentRowids' => array_column($plan['current'], 'rule_rowid'),
    'nextRowids' => array_column($plan['next'], 'rule_rowid'),
    'transitionReasons' => array_column($plan['transitions'], 'reason'),
];

if (($argv[1] ?? null) === '--self-test') {
    if ($summary['currentRows'] !== 3 || $summary['nextRows'] !== 4) {
        throw new RuntimeException('Unexpected lateral JSON row count');
    }
    if ($summary['currentRowids'] !== [1, 2, null] || $summary['nextRowids'] !== [1, 2, 3, 1]) {
        throw new RuntimeException('Unexpected lateral JSON rowid tape');
    }
    echo "wordpress-json-table-lateral-rowid-current-next81 self-test passed\n";
    return;
}

echo json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;

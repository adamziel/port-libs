<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/SQLiteAffinityComparison.php';
require_once __DIR__ . '/../src/SQLiteBlobValue.php';
require_once __DIR__ . '/../src/SQLiteDatabase.php';
require_once __DIR__ . '/../src/SQLiteLikeCollationPlan.php';
require_once __DIR__ . '/../src/SQLiteEncodingCollationAffinityLikeCurrentSourceNextPlan.php';

use PortLibs\LibSqlite\SQLiteBlobValue;
use PortLibs\LibSqlite\SQLiteEncodingCollationAffinityLikeCurrentSourceNextPlan;

$current = [
    ['setting_id' => 1, 'key_name' => 'plugin_literal', 'key_value' => 'plugin_%alpha', 'load_policy' => 'yes'],
    ['setting_id' => 2, 'key_name' => 'plugin_upper', 'key_value' => 'Plugin_%Beta', 'load_policy' => 'yes'],
    ['setting_id' => 3, 'key_name' => 'plugin_false_percent', 'key_value' => 'pluginX%gamma', 'load_policy' => 'yes'],
    ['setting_id' => 4, 'key_name' => 'plugin_number', 'key_value' => 12.5, 'load_policy' => 'no'],
    ['setting_id' => 5, 'key_name' => 'plugin_blob', 'key_value' => new SQLiteBlobValue('plugin_%blob'), 'load_policy' => 'no'],
];

$next = [
    ['setting_id' => 1, 'key_name' => 'plugin_literal', 'key_value' => 'plugin_%alpha', 'load_policy' => 'yes'],
    ['setting_id' => 2, 'key_name' => 'plugin_upper', 'key_value' => 'Plugin_%Beta', 'load_policy' => 'yes'],
    ['setting_id' => 3, 'key_name' => 'plugin_false_percent', 'key_value' => 'plugin_%gamma', 'load_policy' => 'yes'],
    ['setting_id' => 4, 'key_name' => 'plugin_number', 'key_value' => 'plugin_%12.5', 'load_policy' => 'yes'],
    ['setting_id' => 5, 'key_name' => 'plugin_blob', 'key_value' => new SQLiteBlobValue('plugin_%blob'), 'load_policy' => 'no'],
    ['setting_id' => 6, 'key_name' => 'plugin_added', 'key_value' => 'plugin_%added', 'load_policy' => 'yes'],
];

$plan = SQLiteEncodingCollationAffinityLikeCurrentSourceNextPlan::keyValueRowValueEscapePlan($current, $next);

$summary = [
    'scenario' => 'application-key-value-like-escape-affinity-current-source-next237',
    'applicationUse' => 'Copied app_settings import checks can preserve SQLite LIKE ESCAPE semantics for literal underscore/percent key values while applying text affinity and NOCASE range invalidation between current and next sources without ext/sqlite.',
    'pattern' => $plan['pattern'],
    'escape' => $plan['escape'],
    'range' => [$plan['rangeLowerInclusive'], $plan['rangeUpperBound']],
    'currentMatchedRowids' => $plan['currentMatchedRowids'],
    'nextMatchedRowids' => $plan['nextMatchedRowids'],
    'enteredRowids' => $plan['enteredRowids'],
    'changedLikeTextRowids' => $plan['changedLikeTextRowids'],
    'cursorReusable' => $plan['cursorReusable'],
    'invalidationReasons' => $plan['invalidationReasons'],
    'dependencyClosure' => $plan['dependency_closure'],
];

if (PHP_SAPI === 'cli' && in_array('--self-test', $argv, true)) {
    if (
        $summary['range'] !== ['plugin_', 'plugin`']
        || $summary['currentMatchedRowids'] !== [1, 2]
        || $summary['nextMatchedRowids'] !== [4, 6, 1, 2, 3]
        || $summary['enteredRowids'] !== [4, 6, 3]
        || $summary['cursorReusable'] !== false
        || !in_array('affinity-text', $summary['invalidationReasons'], true)
    ) {
        throw new RuntimeException('Unexpected next237 LIKE ESCAPE affinity summary');
    }
    echo "application-key-value-like-escape-affinity-current-source-next237 self-test passed\n";
    return;
}

echo json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;

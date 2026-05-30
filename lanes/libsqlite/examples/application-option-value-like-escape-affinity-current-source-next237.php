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
    ['option_id' => 1, 'option_name' => 'plugin_literal', 'option_value' => 'plugin_%alpha', 'autoload' => 'yes'],
    ['option_id' => 2, 'option_name' => 'plugin_upper', 'option_value' => 'Plugin_%Beta', 'autoload' => 'yes'],
    ['option_id' => 3, 'option_name' => 'plugin_false_percent', 'option_value' => 'pluginX%gamma', 'autoload' => 'yes'],
    ['option_id' => 4, 'option_name' => 'plugin_number', 'option_value' => 12.5, 'autoload' => 'no'],
    ['option_id' => 5, 'option_name' => 'plugin_blob', 'option_value' => new SQLiteBlobValue('plugin_%blob'), 'autoload' => 'no'],
];

$next = [
    ['option_id' => 1, 'option_name' => 'plugin_literal', 'option_value' => 'plugin_%alpha', 'autoload' => 'yes'],
    ['option_id' => 2, 'option_name' => 'plugin_upper', 'option_value' => 'Plugin_%Beta', 'autoload' => 'yes'],
    ['option_id' => 3, 'option_name' => 'plugin_false_percent', 'option_value' => 'plugin_%gamma', 'autoload' => 'yes'],
    ['option_id' => 4, 'option_name' => 'plugin_number', 'option_value' => 'plugin_%12.5', 'autoload' => 'yes'],
    ['option_id' => 5, 'option_name' => 'plugin_blob', 'option_value' => new SQLiteBlobValue('plugin_%blob'), 'autoload' => 'no'],
    ['option_id' => 6, 'option_name' => 'plugin_added', 'option_value' => 'plugin_%added', 'autoload' => 'yes'],
];

$plan = SQLiteEncodingCollationAffinityLikeCurrentSourceNextPlan::optionRowValueEscapePlan($current, $next);

$summary = [
    'scenario' => 'application-option-value-like-escape-affinity-current-source-next237',
    'applicationUse' => 'Copied wp_options import checks can preserve SQLite LIKE ESCAPE semantics for literal underscore/percent option values while applying text affinity and NOCASE range invalidation between current and next sources without ext/sqlite.',
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
    echo "application-option-value-like-escape-affinity-current-source-next237 self-test passed\n";
    return;
}

echo json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;

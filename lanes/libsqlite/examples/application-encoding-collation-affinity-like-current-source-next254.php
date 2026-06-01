<?php

declare(strict_types=1);

require_once dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteBlobValue;
use PortLibs\LibSqlite\SQLiteEncodingCollationAffinityLikeCurrentSourceNextPlan;

$current = [
    ['setting_id' => 1, 'key_name' => 'plugin_cache', 'key_value' => 'plugin_cache'],
    ['setting_id' => 2, 'key_name' => 'plugin_literal', 'key_value' => 'plugin_%literal'],
    ['setting_id' => 3, 'key_name' => 'plugin_upper', 'key_value' => 'PLUGIN_cache'],
    ['setting_id' => 4, 'key_name' => 'plugin_blob', 'key_value' => new SQLiteBlobValue('plugin_blob')],
];

$next = [
    ['setting_id' => 1, 'key_name' => 'plugin_cache', 'key_value' => 'plugin_cache'],
    ['setting_id' => 2, 'key_name' => 'plugin_literal', 'key_value' => 'plugin_%literal'],
    ['setting_id' => 3, 'key_name' => 'plugin_upper', 'key_value' => 'PLUGIN_cache'],
    ['setting_id' => 5, 'key_name' => 'plugin_new', 'key_value' => 'plugin_new'],
];

$plan = SQLiteEncodingCollationAffinityLikeCurrentSourceNextPlan::applicationNullableEscapeLikePlan($current, $next);
$summary = [
    'status' => $plan['status'],
    'currentEscapeIsSqlNull' => $plan['currentEscapeIsSqlNull'],
    'nextEscapeText' => $plan['nextEscapeText'],
    'currentMatchedRowids' => $plan['currentMatchedRowids'],
    'nextMatchedRowids' => $plan['nextMatchedRowids'],
    'currentUnknownRowids' => $plan['currentUnknownRowids'],
    'enteredMatchedRowids' => $plan['enteredMatchedRowids'],
    'cursorInvalidated' => $plan['cursorInvalidated'],
    'invalidationReasons' => $plan['invalidationReasons'],
    'dependency_closure' => $plan['dependency_closure'],
];

if (($argv[1] ?? null) === '--self-test') {
    assert($summary['currentEscapeIsSqlNull'] === true);
    assert($summary['nextEscapeText'] === '!');
    assert($summary['currentMatchedRowids'] === []);
    assert($summary['nextMatchedRowids'] === [1, 2, 3, 5]);
    assert($summary['currentUnknownRowids'] === [1, 2, 3, 4]);
    assert(in_array('escape-nullability', $summary['invalidationReasons'], true));
    echo "application-encoding-collation-affinity-like-current-source-next254 self-test passed\n";
    return;
}

echo json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";

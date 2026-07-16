<?php

declare(strict_types=1);

require_once dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteBlobValue;
use PortLibs\LibSqlite\SQLiteEncodingCollationAffinityLikeCurrentSourceNextPlan;

$current = [
    ['setting_id' => 1, 'key_name' => 'plugin_cache', 'key_value' => 'plugin_cache'],
    ['setting_id' => 2, 'key_name' => 'plugin_upper', 'key_value' => 'PLUGIN_CACHE'],
    ['setting_id' => 3, 'key_name' => 'numeric_text', 'key_value' => '123'],
    ['setting_id' => 4, 'key_name' => 'numeric_int', 'key_value' => 123],
    ['setting_id' => 5, 'key_name' => 'blob_payload', 'key_value' => new SQLiteBlobValue('plugin_cache')],
];
$next = [
    ['setting_id' => 1, 'key_name' => 'plugin_cache', 'key_value' => 'plugin_cache'],
    ['setting_id' => 2, 'key_name' => 'plugin_upper', 'key_value' => 'PLUGIN_CACHE'],
    ['setting_id' => 3, 'key_name' => 'numeric_text', 'key_value' => '123'],
    ['setting_id' => 4, 'key_name' => 'numeric_int', 'key_value' => 123],
    ['setting_id' => 5, 'key_name' => 'blob_payload', 'key_value' => new SQLiteBlobValue('plugin_cache')],
    ['setting_id' => 6, 'key_name' => 'numeric_new', 'key_value' => '1234'],
];

$plan = SQLiteEncodingCollationAffinityLikeCurrentSourceNextPlan::applicationPatternAffinityPlan(
    $current,
    $next,
    'plugin%',
    '123%',
);

if (($argv[1] ?? null) === '--self-test') {
    assert($plan['currentMatchedRowids'] === [1, 2]);
    assert($plan['nextMatchedRowids'] === [3, 4, 6]);
    assert($plan['currentMalformedRowids'] === [5]);
    assert(in_array('pattern-text', $plan['invalidationReasons'], true));
    assert(in_array('matched-rowset', $plan['invalidationReasons'], true));
    echo "application-encoding-collation-affinity-like-current-source-next256 self-test passed\n";
    return;
}

echo json_encode([
    'status' => $plan['status'],
    'currentPattern' => $plan['currentPattern']['patternText'],
    'nextPattern' => $plan['nextPattern']['patternText'],
    'currentMatchedRowids' => $plan['currentMatchedRowids'],
    'nextMatchedRowids' => $plan['nextMatchedRowids'],
    'invalidationReasons' => $plan['invalidationReasons'],
], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR) . "\n";

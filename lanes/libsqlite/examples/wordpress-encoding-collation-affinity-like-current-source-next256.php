<?php

declare(strict_types=1);

require_once dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteBlobValue;
use PortLibs\LibSqlite\SQLiteEncodingCollationAffinityLikeCurrentSourceNext256Plan;

$current = [
    ['option_id' => 1, 'option_name' => 'plugin_cache', 'option_value' => 'plugin_cache'],
    ['option_id' => 2, 'option_name' => 'plugin_upper', 'option_value' => 'PLUGIN_CACHE'],
    ['option_id' => 3, 'option_name' => 'numeric_text', 'option_value' => '123'],
    ['option_id' => 4, 'option_name' => 'numeric_int', 'option_value' => 123],
    ['option_id' => 5, 'option_name' => 'blob_payload', 'option_value' => new SQLiteBlobValue('plugin_cache')],
];
$next = [
    ['option_id' => 1, 'option_name' => 'plugin_cache', 'option_value' => 'plugin_cache'],
    ['option_id' => 2, 'option_name' => 'plugin_upper', 'option_value' => 'PLUGIN_CACHE'],
    ['option_id' => 3, 'option_name' => 'numeric_text', 'option_value' => '123'],
    ['option_id' => 4, 'option_name' => 'numeric_int', 'option_value' => 123],
    ['option_id' => 5, 'option_name' => 'blob_payload', 'option_value' => new SQLiteBlobValue('plugin_cache')],
    ['option_id' => 6, 'option_name' => 'numeric_new', 'option_value' => '1234'],
];

$plan = SQLiteEncodingCollationAffinityLikeCurrentSourceNext256Plan::wordpressPatternAffinityPlan(
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
    echo "wordpress-encoding-collation-affinity-like-current-source-next256 self-test passed\n";
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

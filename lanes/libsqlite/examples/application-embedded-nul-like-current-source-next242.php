<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteEncodingCollationAffinityLikeCurrentSourceNextPlan;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$current = [
    ['setting_id' => 1, 'key_name' => 'nul_cache_exact', 'key_value' => "plugin\0cache_suffix"],
    ['setting_id' => 2, 'key_name' => 'nul_cache_upper', 'key_value' => "Plugin\0Cache_suffix"],
    ['setting_id' => 3, 'key_name' => 'plain_cache', 'key_value' => 'plugin_cache_suffix'],
];

$next = [
    ['setting_id' => 1, 'key_name' => 'nul_cache_exact', 'key_value' => "plugin\0cache_suffix2"],
    ['setting_id' => 2, 'key_name' => 'nul_cache_upper', 'key_value' => "Plugin\0Cache_suffix"],
    ['setting_id' => 4, 'key_name' => 'nul_cache_added', 'key_value' => "PLUGIN\0CACHE_added"],
];

$plan = SQLiteEncodingCollationAffinityLikeCurrentSourceNextPlan::applicationEmbeddedNulLikePlan($current, $next);

if (
    $plan['status'] !== 'encoding-collation-affinity-like-current-source-nexttwoFourTwo'
    || $plan['currentMatchedRowids'] !== [2, 1]
    || $plan['nextMatchedRowids'] !== [4, 2, 1]
    || $plan['enteredMatchedRowids'] !== [4]
    || $plan['prefixContainsNul'] !== true
    || !in_array('embedded-nul-text-bytes', $plan['invalidationReasons'], true)
) {
    fwrite(STDERR, "application-embedded-nul-like-current-source-next242 self-test failed\n");
    exit(1);
}

echo json_encode([
    'status' => $plan['status'],
    'patternHex' => $plan['patternHex'],
    'prefixHex' => $plan['prefixHex'],
    'currentMatchedRowids' => $plan['currentMatchedRowids'],
    'nextMatchedRowids' => $plan['nextMatchedRowids'],
    'enteredMatchedRowids' => $plan['enteredMatchedRowids'],
    'invalidationReasons' => $plan['invalidationReasons'],
    'dependency_closure' => $plan['dependency_closure'],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;

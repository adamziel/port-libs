<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBlobValue;
use PortLibs\LibSqlite\SQLiteEncodingCollationAffinityLikeCurrentSourceNextPlan;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$current = [
    ['setting_id' => 1, 'key_name' => 'plugin_cache'],
    ['setting_id' => 2, 'key_name' => 'Plugin_Cache'],
    ['setting_id' => 3, 'key_name' => 'plugin_cache!'],
    ['setting_id' => 4, 'key_name' => new SQLiteBlobValue('plugin_cache')],
];

$next = [
    ['setting_id' => 1, 'key_name' => 'plugin_cache2'],
    ['setting_id' => 2, 'key_name' => 'Plugin_Cache'],
    ['setting_id' => 3, 'key_name' => 'plugin_cache!'],
    ['setting_id' => 5, 'key_name' => 'PLUGIN_CACHE'],
];

$plan = SQLiteEncodingCollationAffinityLikeCurrentSourceNextPlan::applicationDanglingEscapeLikePlan($current, $next);

if (
    $plan['status'] !== 'encoding-collation-affinity-like-current-source-nexttwoFourFive'
    || $plan['currentCandidateRowids'] !== [1, 2, 3]
    || $plan['nextCandidateRowids'] !== [2, 5, 3, 1]
    || $plan['currentMatchedRowids'] !== []
    || $plan['nextMatchedRowids'] !== []
    || $plan['currentUnknownRowids'] !== [4]
    || !in_array('dangling-escape-residual', $plan['invalidationReasons'], true)
) {
    fwrite(STDERR, "application-dangling-escape-like-current-source-next245 self-test failed\n");
    exit(1);
}

echo json_encode([
    'status' => $plan['status'],
    'patternHex' => $plan['patternHex'],
    'prefix' => $plan['prefix'],
    'currentCandidateRowids' => $plan['currentCandidateRowids'],
    'nextCandidateRowids' => $plan['nextCandidateRowids'],
    'currentResidualRejectedRowids' => $plan['currentResidualRejectedRowids'],
    'nextResidualRejectedRowids' => $plan['nextResidualRejectedRowids'],
    'invalidationReasons' => $plan['invalidationReasons'],
    'dependency_closure' => $plan['dependency_closure'],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;

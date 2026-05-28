<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBlobValue;
use PortLibs\LibSqlite\SQLiteEncodingCollationAffinityLikeCurrentSourceNext245Plan;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$current = [
    ['option_id' => 1, 'option_name' => 'plugin_cache'],
    ['option_id' => 2, 'option_name' => 'Plugin_Cache'],
    ['option_id' => 3, 'option_name' => 'plugin_cache!'],
    ['option_id' => 4, 'option_name' => new SQLiteBlobValue('plugin_cache')],
];

$next = [
    ['option_id' => 1, 'option_name' => 'plugin_cache2'],
    ['option_id' => 2, 'option_name' => 'Plugin_Cache'],
    ['option_id' => 3, 'option_name' => 'plugin_cache!'],
    ['option_id' => 5, 'option_name' => 'PLUGIN_CACHE'],
];

$plan = SQLiteEncodingCollationAffinityLikeCurrentSourceNext245Plan::wordpressDanglingEscapeLikePlan($current, $next);

if (
    $plan['status'] !== 'encoding-collation-affinity-like-current-source-next245'
    || $plan['currentCandidateRowids'] !== [1, 2, 3]
    || $plan['nextCandidateRowids'] !== [2, 5, 3, 1]
    || $plan['currentMatchedRowids'] !== []
    || $plan['nextMatchedRowids'] !== []
    || $plan['currentUnknownRowids'] !== [4]
    || !in_array('dangling-escape-residual', $plan['invalidationReasons'], true)
) {
    fwrite(STDERR, "wordpress-dangling-escape-like-current-source-next245 self-test failed\n");
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

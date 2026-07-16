<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteEncodingCollationAffinityLikeCurrentSourceNextPlan;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$current = [
    ['setting_id' => 1, 'key_name' => 'plugin_cache_limit', 'key_value' => 40],
    ['setting_id' => 2, 'key_name' => 'plugin_cache_ratio', 'key_value' => 40.5],
    ['setting_id' => 3, 'key_name' => 'plugin_cache_text', 'key_value' => '40'],
];

$next = [
    ['setting_id' => 1, 'key_name' => 'plugin_cache_limit', 'key_value' => '40'],
    ['setting_id' => 2, 'key_name' => 'plugin_cache_ratio', 'key_value' => 40.5],
    ['setting_id' => 4, 'key_name' => 'plugin_cache_new', 'key_value' => 409],
];

$plan = SQLiteEncodingCollationAffinityLikeCurrentSourceNextPlan::applicationPreparedPatternAffinityPlan(
    $current,
    $next,
    40,
    '40',
);

if (($argv[1] ?? null) === '--self-test') {
    assert($plan['currentRowids'] === [1, 3]);
    assert($plan['nextRowids'] === [1]);
    assert($plan['currentPatternStorageClass'] === 'integer');
    assert($plan['nextPatternStorageClass'] === 'text');
    assert(in_array('pattern-storage-class', $plan['invalidationReasons'], true));
    assert($plan['cursorInvalidated'] === true);
    echo "application-prepared-pattern-affinity-like-current-source-next251 self-test passed\n";
    return;
}

echo json_encode([
    'status' => $plan['status'],
    'expression' => $plan['expression'],
    'currentPatternText' => $plan['currentPatternText'],
    'nextPatternText' => $plan['nextPatternText'],
    'currentPatternStorageClass' => $plan['currentPatternStorageClass'],
    'nextPatternStorageClass' => $plan['nextPatternStorageClass'],
    'currentRowids' => $plan['currentRowids'],
    'nextRowids' => $plan['nextRowids'],
    'changedValueStorageClassRowids' => $plan['changedValueStorageClassRowids'],
    'invalidationReasons' => $plan['invalidationReasons'],
    'dependency_closure' => $plan['dependency_closure'],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;

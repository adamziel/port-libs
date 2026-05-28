<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteEncodingCollationAffinityLikeCurrentSourceNext251Plan;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$current = [
    ['option_id' => 1, 'option_name' => 'plugin_cache_limit', 'option_value' => 40],
    ['option_id' => 2, 'option_name' => 'plugin_cache_ratio', 'option_value' => 40.5],
    ['option_id' => 3, 'option_name' => 'plugin_cache_text', 'option_value' => '40'],
];

$next = [
    ['option_id' => 1, 'option_name' => 'plugin_cache_limit', 'option_value' => '40'],
    ['option_id' => 2, 'option_name' => 'plugin_cache_ratio', 'option_value' => 40.5],
    ['option_id' => 4, 'option_name' => 'plugin_cache_new', 'option_value' => 409],
];

$plan = SQLiteEncodingCollationAffinityLikeCurrentSourceNext251Plan::wordpressPreparedPatternAffinityPlan(
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
    echo "wordpress-prepared-pattern-affinity-like-current-source-next251 self-test passed\n";
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

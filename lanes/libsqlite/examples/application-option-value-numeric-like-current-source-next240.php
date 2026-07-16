<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteEncodingCollationAffinityLikeCurrentSourceNextPlan;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$current = [
    ['setting_id' => 1, 'key_name' => 'rewrite_rules_version', 'key_value' => 404],
    ['setting_id' => 2, 'key_name' => 'rewrite_rules_preview', 'key_value' => 405.5],
    ['setting_id' => 3, 'key_name' => 'rewrite_rules_text', 'key_value' => '0404'],
    ['setting_id' => 4, 'key_name' => 'cache_enabled', 'key_value' => true],
];

$next = [
    ['setting_id' => 1, 'key_name' => 'rewrite_rules_version', 'key_value' => '404'],
    ['setting_id' => 2, 'key_name' => 'rewrite_rules_preview', 'key_value' => 405.5],
    ['setting_id' => 3, 'key_name' => 'rewrite_rules_text', 'key_value' => 404],
    ['setting_id' => 5, 'key_name' => 'rewrite_rules_new', 'key_value' => 409],
];

$plan = SQLiteEncodingCollationAffinityLikeCurrentSourceNextPlan::keyValueRowValueNumericLikePlan(
    $current,
    $next,
    '40%',
);

if (($argv[1] ?? null) === '--self-test') {
    assert($plan['currentRowids'] === [3, 1, 2]);
    assert($plan['nextRowids'] === [1, 3, 2, 5]);
    assert($plan['changedStorageClassRowids'] === [1, 3]);
    assert($plan['changedFormattedRowids'] === [3]);
    assert($plan['cursorInvalidated'] === true);
    echo "application-key-value-numeric-like-current-source-next240 self-test passed\n";
    return;
}

echo json_encode([
    'status' => $plan['status'],
    'pattern' => $plan['pattern'],
    'currentRowids' => $plan['currentRowids'],
    'nextRowids' => $plan['nextRowids'],
    'enteredRowids' => $plan['enteredRowids'],
    'changedFormattedRowids' => $plan['changedFormattedRowids'],
    'changedStorageClassRowids' => $plan['changedStorageClassRowids'],
    'invalidationReasons' => $plan['invalidationReasons'],
    'dependency_closure' => $plan['dependency_closure'],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;

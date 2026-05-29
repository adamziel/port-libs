<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteEncodingCollationAffinityLikeCurrentSourceNextPlan;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$current = [
    ['option_id' => 1, 'option_name' => 'rewrite_rules_version', 'option_value' => 404],
    ['option_id' => 2, 'option_name' => 'rewrite_rules_preview', 'option_value' => 405.5],
    ['option_id' => 3, 'option_name' => 'rewrite_rules_text', 'option_value' => '0404'],
    ['option_id' => 4, 'option_name' => 'cache_enabled', 'option_value' => true],
];

$next = [
    ['option_id' => 1, 'option_name' => 'rewrite_rules_version', 'option_value' => '404'],
    ['option_id' => 2, 'option_name' => 'rewrite_rules_preview', 'option_value' => 405.5],
    ['option_id' => 3, 'option_name' => 'rewrite_rules_text', 'option_value' => 404],
    ['option_id' => 5, 'option_name' => 'rewrite_rules_new', 'option_value' => 409],
];

$plan = SQLiteEncodingCollationAffinityLikeCurrentSourceNextPlan::wordpressOptionValueNumericLikePlan(
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
    echo "wordpress-option-value-numeric-like-current-source-next240 self-test passed\n";
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

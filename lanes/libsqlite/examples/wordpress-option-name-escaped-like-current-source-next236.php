<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteEncodingCollationAffinityLikeCurrentSourceNextPlan;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$current = [
    ['option_id' => 1, 'option_name' => 'wp_%_timeout'],
    ['option_id' => 2, 'option_name' => 'WP_%_TIMEOUT'],
    ['option_id' => 3, 'option_name' => 'wp_cache_timeout'],
    ['option_id' => 4, 'option_name' => 'wp_%_timeout_extra'],
    ['option_id' => 5, 'option_name' => 'wp_é_timeout'],
    ['option_id' => 6, 'option_name' => 'wp_É_timeout'],
];

$next = [
    ['option_id' => 1, 'option_name' => 'wp_%_timeout2'],
    ['option_id' => 2, 'option_name' => 'WP_%_TIMEOUT'],
    ['option_id' => 4, 'option_name' => 'wp_%_timeout_extra'],
    ['option_id' => 7, 'option_name' => 'wp_%_timeout'],
];

$plan = SQLiteEncodingCollationAffinityLikeCurrentSourceNextPlan::wordpressOptionNameEscapedLikePlan(
    $current,
    $next,
    'wp!_!%!_timeout%',
    '!',
);

echo json_encode([
    'status' => $plan['status'],
    'pattern' => $plan['pattern'],
    'escape' => $plan['escape'],
    'currentRowids' => $plan['currentRowids'],
    'nextRowids' => $plan['nextRowids'],
    'enteredRowids' => $plan['enteredRowids'],
    'changedNameBytesRowids' => $plan['changedNameBytesRowids'],
    'cursorInvalidated' => $plan['cursorInvalidated'],
    'invalidationReasons' => $plan['invalidationReasons'],
    'dependency_closure' => $plan['dependency_closure'],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;

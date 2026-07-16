<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteEncodingCollationAffinityLikeCurrentSourceNextPlan;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$current = [
    ['setting_id' => 1, 'key_name' => 'app_%_timeout'],
    ['setting_id' => 2, 'key_name' => 'APP_%_TIMEOUT'],
    ['setting_id' => 3, 'key_name' => 'app_cache_timeout'],
    ['setting_id' => 4, 'key_name' => 'app_%_timeout_extra'],
    ['setting_id' => 5, 'key_name' => 'app_é_timeout'],
    ['setting_id' => 6, 'key_name' => 'app_É_timeout'],
];

$next = [
    ['setting_id' => 1, 'key_name' => 'app_%_timeout2'],
    ['setting_id' => 2, 'key_name' => 'APP_%_TIMEOUT'],
    ['setting_id' => 4, 'key_name' => 'app_%_timeout_extra'],
    ['setting_id' => 7, 'key_name' => 'app_%_timeout'],
];

$plan = SQLiteEncodingCollationAffinityLikeCurrentSourceNextPlan::keyValueRowKeyEscapedLikePlan(
    $current,
    $next,
    'app!_!%!_timeout%',
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

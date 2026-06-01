<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteEncodingCollationAffinityLikeCurrentSourceNextPlan;

require_once __DIR__ . '/../src/SQLiteBlobValue.php';
require_once __DIR__ . '/../src/SQLiteDatabase.php';
require_once __DIR__ . '/../src/SQLiteEncodingCollationAffinityLikeCurrentSourceNextPlan.php';

$current = [
    ['setting_id' => 1, 'key_name' => "app_cache\0timeout"],
    ['setting_id' => 2, 'key_name' => "APP_CACHE\0TIMEOUT"],
    ['setting_id' => 3, 'key_name' => "app_cache\0timeout_old"],
    ['setting_id' => 4, 'key_name' => "app_cache_timeout"],
    ['setting_id' => 5, 'key_name' => "app_cache\xc3timeout"],
];

$next = [
    ['setting_id' => 1, 'key_name' => "app_cache\0timeout_v2"],
    ['setting_id' => 2, 'key_name' => "APP_CACHE\0TIMEOUT"],
    ['setting_id' => 3, 'key_name' => "app_cache\0timeout_old"],
    ['setting_id' => 5, 'key_name' => "app_cache\xc3timeout"],
    ['setting_id' => 6, 'key_name' => "app_cache\0timeout_new"],
];

$plan = SQLiteEncodingCollationAffinityLikeCurrentSourceNextPlan::keyValueRowKeyByteAwareLikePlan(
    $current,
    $next,
    "app!_cache\0timeout%",
    '!',
);

$summary = [
    'scenario' => 'application-encoding-collation-affinity-like-current-source-next241',
    'applicationUse' => 'Copied app_settings scans can keep embedded-NUL key_name bytes visible to LIKE residual matching while invalidating current-source cursors when byte-identical rows enter or change.',
    'currentMatchedRowids' => $plan['currentMatchedRowids'],
    'nextMatchedRowids' => $plan['nextMatchedRowids'],
    'enteredRowids' => $plan['enteredRowids'],
    'changedNameBytesRowids' => $plan['changedNameBytesRowids'],
    'currentMalformedRowids' => $plan['currentMalformedRowids'],
    'cursorInvalidated' => $plan['cursorInvalidated'],
    'dependencyClosure' => $plan['dependency_closure'],
    'nonOverlap' => $plan['non_overlap'],
];

if (
    $summary['currentMatchedRowids'] !== [2, 1, 3]
    || $summary['nextMatchedRowids'] !== [2, 6, 3, 1]
    || $summary['enteredRowids'] !== [6]
    || $summary['changedNameBytesRowids'] !== [1]
    || $summary['currentMalformedRowids'] !== [5]
    || $summary['cursorInvalidated'] !== true
) {
    throw new RuntimeException('Unexpected next241 byte-aware LIKE Application summary');
}

echo json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;

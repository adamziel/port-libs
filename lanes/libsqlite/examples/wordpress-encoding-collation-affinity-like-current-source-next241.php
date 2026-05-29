<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteEncodingCollationAffinityLikeCurrentSourceNextPlan;

require_once __DIR__ . '/../src/SQLiteBlobValue.php';
require_once __DIR__ . '/../src/SQLiteDatabase.php';
require_once __DIR__ . '/../src/SQLiteEncodingCollationAffinityLikeCurrentSourceNextPlan.php';

$current = [
    ['option_id' => 1, 'option_name' => "wp_cache\0timeout"],
    ['option_id' => 2, 'option_name' => "WP_CACHE\0TIMEOUT"],
    ['option_id' => 3, 'option_name' => "wp_cache\0timeout_old"],
    ['option_id' => 4, 'option_name' => "wp_cache_timeout"],
    ['option_id' => 5, 'option_name' => "wp_cache\xc3timeout"],
];

$next = [
    ['option_id' => 1, 'option_name' => "wp_cache\0timeout_v2"],
    ['option_id' => 2, 'option_name' => "WP_CACHE\0TIMEOUT"],
    ['option_id' => 3, 'option_name' => "wp_cache\0timeout_old"],
    ['option_id' => 5, 'option_name' => "wp_cache\xc3timeout"],
    ['option_id' => 6, 'option_name' => "wp_cache\0timeout_new"],
];

$plan = SQLiteEncodingCollationAffinityLikeCurrentSourceNextPlan::wordpressOptionNameByteAwareLikePlan(
    $current,
    $next,
    "wp!_cache\0timeout%",
    '!',
);

$summary = [
    'scenario' => 'wordpress-encoding-collation-affinity-like-current-source-next241',
    'wordpressUse' => 'Copied wp_options scans can keep embedded-NUL option_name bytes visible to LIKE residual matching while invalidating current-source cursors when byte-identical rows enter or change.',
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
    throw new RuntimeException('Unexpected next241 byte-aware LIKE WordPress summary');
}

echo json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;

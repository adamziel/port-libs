<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteBlobValue;
use PortLibs\LibSqlite\SQLiteEncodingCollationAffinityLikeCurrentSourceNextPlan;

$current = [
    ['option_id' => 1, 'option_name' => 'retry_timeout_real', 'option_value' => 100.0],
    ['option_id' => 2, 'option_name' => 'retry_timeout_integer', 'option_value' => 100],
    ['option_id' => 3, 'option_name' => 'retry_timeout_text', 'option_value' => '100.0'],
    ['option_id' => 4, 'option_name' => 'retry_timeout_blob', 'option_value' => new SQLiteBlobValue('100.0')],
    ['option_id' => 5, 'option_name' => 'retry_timeout_null', 'option_value' => null],
];

$next = [
    ['option_id' => 1, 'option_name' => 'retry_timeout_real', 'option_value' => 100.0],
    ['option_id' => 2, 'option_name' => 'retry_timeout_integer_promoted', 'option_value' => 100.0],
    ['option_id' => 3, 'option_name' => 'retry_timeout_text_changed', 'option_value' => '100'],
    ['option_id' => 4, 'option_name' => 'retry_timeout_blob', 'option_value' => new SQLiteBlobValue('100.0')],
    ['option_id' => 5, 'option_name' => 'retry_timeout_null', 'option_value' => null],
    ['option_id' => 6, 'option_name' => 'retry_timeout_new_real', 'option_value' => 100.25],
];

$plan = SQLiteEncodingCollationAffinityLikeCurrentSourceNextPlan::applicationRealTextAffinityLikePlan(
    $current,
    $next,
    '100.%'
);

echo json_encode([
    'status' => $plan['status'],
    'operator' => $plan['operator'],
    'currentMatchedRowids' => $plan['currentMatchedRowids'],
    'nextMatchedRowids' => $plan['nextMatchedRowids'],
    'currentUnknownRowids' => $plan['currentUnknownRowids'],
    'nextUnknownRowids' => $plan['nextUnknownRowids'],
    'changedTextRowids' => $plan['changedTextRowids'],
    'changedLikeTruthRowids' => $plan['changedLikeTruthRowids'],
    'invalidationReasons' => $plan['invalidationReasons'],
    'dependency_closure' => $plan['dependency_closure'],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;

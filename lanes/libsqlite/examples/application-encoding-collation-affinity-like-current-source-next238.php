<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteBlobValue;
use PortLibs\LibSqlite\SQLiteEncodingCollationAffinityLikeCurrentSourceNextPlan;

$current = [
    ['setting_id' => 1, 'key_name' => 'retry_timeout_real', 'key_value' => 100.0],
    ['setting_id' => 2, 'key_name' => 'retry_timeout_integer', 'key_value' => 100],
    ['setting_id' => 3, 'key_name' => 'retry_timeout_text', 'key_value' => '100.0'],
    ['setting_id' => 4, 'key_name' => 'retry_timeout_blob', 'key_value' => new SQLiteBlobValue('100.0')],
    ['setting_id' => 5, 'key_name' => 'retry_timeout_null', 'key_value' => null],
];

$next = [
    ['setting_id' => 1, 'key_name' => 'retry_timeout_real', 'key_value' => 100.0],
    ['setting_id' => 2, 'key_name' => 'retry_timeout_integer_promoted', 'key_value' => 100.0],
    ['setting_id' => 3, 'key_name' => 'retry_timeout_text_changed', 'key_value' => '100'],
    ['setting_id' => 4, 'key_name' => 'retry_timeout_blob', 'key_value' => new SQLiteBlobValue('100.0')],
    ['setting_id' => 5, 'key_name' => 'retry_timeout_null', 'key_value' => null],
    ['setting_id' => 6, 'key_name' => 'retry_timeout_new_real', 'key_value' => 100.25],
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

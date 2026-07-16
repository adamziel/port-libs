<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteBlobValue;
use PortLibs\LibSqlite\SQLiteEncodingCollationAffinityLikeCurrentSourceNextPlan;

$current = [
    ['setting_id' => 1, 'key_name' => 'legacy_plugin_payload', 'key_value' => "Plugin_\xe2legacy"],
    ['setting_id' => 2, 'key_name' => 'legacy_plugin_bad_pair', 'key_value' => "plugin_\xe2("],
    ['setting_id' => 3, 'key_name' => 'legacy_plugin_valid_euro', 'key_value' => "plugin_\xe2\x82\xac"],
    ['setting_id' => 4, 'key_name' => 'siteurl', 'key_value' => 'https://example.test'],
    ['setting_id' => 5, 'key_name' => 'payload_blob', 'key_value' => new SQLiteBlobValue("plugin_\xe2blob")],
    ['setting_id' => 6, 'key_name' => 'payload_null', 'key_value' => null],
];

$next = [
    ['setting_id' => 1, 'key_name' => 'legacy_plugin_payload', 'key_value' => "Plugin_\xe2legacy2"],
    ['setting_id' => 2, 'key_name' => 'legacy_plugin_bad_pair', 'key_value' => "plugin_\xe2("],
    ['setting_id' => 3, 'key_name' => 'legacy_plugin_valid_euro_now_truncated', 'key_value' => "plugin_\xe2\x82"],
    ['setting_id' => 4, 'key_name' => 'siteurl', 'key_value' => 'https://example.test'],
    ['setting_id' => 5, 'key_name' => 'payload_blob', 'key_value' => new SQLiteBlobValue("plugin_\xe2blob")],
    ['setting_id' => 6, 'key_name' => 'payload_null', 'key_value' => null],
    ['setting_id' => 7, 'key_name' => 'new_numeric_retry', 'key_value' => 123],
];

$plan = SQLiteEncodingCollationAffinityLikeCurrentSourceNextPlan::keyValueRowValueNotLikePlan(
    $current,
    $next,
    "plugin!_\xe2%",
    '!',
);

echo json_encode([
    'status' => $plan['status'],
    'operator' => $plan['operator'],
    'currentResultRowids' => $plan['currentResultRowids'],
    'nextResultRowids' => $plan['nextResultRowids'],
    'currentLikeRowids' => $plan['currentLikeRowids'],
    'nextLikeRowids' => $plan['nextLikeRowids'],
    'currentUnknownRowids' => $plan['currentUnknownRowids'],
    'nextUnknownRowids' => $plan['nextUnknownRowids'],
    'changedPredicateTruthRowids' => $plan['changedPredicateTruthRowids'],
    'invalidationReasons' => $plan['invalidationReasons'],
    'dependency_closure' => $plan['dependency_closure'],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;

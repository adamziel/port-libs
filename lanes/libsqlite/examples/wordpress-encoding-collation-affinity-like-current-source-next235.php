<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteBlobValue;
use PortLibs\LibSqlite\SQLiteEncodingCollationAffinityLikeCurrentSourceNextPlan;

$current = [
    ['option_id' => 1, 'option_name' => 'legacy_plugin_payload', 'option_value' => "Plugin_\xe2legacy"],
    ['option_id' => 2, 'option_name' => 'legacy_plugin_bad_pair', 'option_value' => "plugin_\xe2("],
    ['option_id' => 3, 'option_name' => 'legacy_plugin_valid_euro', 'option_value' => "plugin_\xe2\x82\xac"],
    ['option_id' => 4, 'option_name' => 'siteurl', 'option_value' => 'https://example.test'],
    ['option_id' => 5, 'option_name' => 'payload_blob', 'option_value' => new SQLiteBlobValue("plugin_\xe2blob")],
    ['option_id' => 6, 'option_name' => 'payload_null', 'option_value' => null],
];

$next = [
    ['option_id' => 1, 'option_name' => 'legacy_plugin_payload', 'option_value' => "Plugin_\xe2legacy2"],
    ['option_id' => 2, 'option_name' => 'legacy_plugin_bad_pair', 'option_value' => "plugin_\xe2("],
    ['option_id' => 3, 'option_name' => 'legacy_plugin_valid_euro_now_truncated', 'option_value' => "plugin_\xe2\x82"],
    ['option_id' => 4, 'option_name' => 'siteurl', 'option_value' => 'https://example.test'],
    ['option_id' => 5, 'option_name' => 'payload_blob', 'option_value' => new SQLiteBlobValue("plugin_\xe2blob")],
    ['option_id' => 6, 'option_name' => 'payload_null', 'option_value' => null],
    ['option_id' => 7, 'option_name' => 'new_numeric_retry', 'option_value' => 123],
];

$plan = SQLiteEncodingCollationAffinityLikeCurrentSourceNextPlan::wordpressOptionValueNotLikePlan(
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

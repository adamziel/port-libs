<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteEncodingCollationAffinityLikeCurrentSourceNextPlan;

$currentRows = [
    ['setting_id' => 1, 'key_name' => 'legacy_plugin_payload', 'key_value' => "Plugin_\xe2legacy"],
    ['setting_id' => 2, 'key_name' => 'legacy_plugin_prefix', 'key_value' => "plugin_\xe2"],
    ['setting_id' => 3, 'key_name' => 'legacy_plugin_valid_euro', 'key_value' => "plugin_\xe2\x82\xac"],
];

$nextRows = [
    ['setting_id' => 1, 'key_name' => 'legacy_plugin_payload', 'key_value' => "Plugin_\xe2legacy2"],
    ['setting_id' => 3, 'key_name' => 'legacy_plugin_truncated_euro', 'key_value' => "plugin_\xe2\x82"],
    ['setting_id' => 4, 'key_name' => 'new_legacy_plugin', 'key_value' => "plugin_\xe2new"],
];

$plan = SQLiteEncodingCollationAffinityLikeCurrentSourceNextPlan::keyValueRowValueMalformedByteLikePlan(
    $currentRows,
    $nextRows,
    "plugin!_\xe2%",
    '!',
);

if (($argv[1] ?? null) === '--self-test') {
    assert($plan['currentRowids'] === [1, 2]);
    assert($plan['nextRowids'] === [1, 4, 3]);
    assert($plan['enteredRowids'] === [4, 3]);
    assert($plan['exitedRowids'] === [2]);
    assert($plan['currentMalformedRowids'] === [1, 2]);
    assert($plan['nextMalformedRowids'] === [1, 4, 3]);
    echo "application-encoding-collation-affinity-like-current-source-next232 self-test passed\n";
    return;
}

echo json_encode($plan, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";

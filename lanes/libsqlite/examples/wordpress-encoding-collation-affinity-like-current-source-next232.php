<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteEncodingCollationAffinityLikeCurrentSourceNextPlan;

$currentRows = [
    ['option_id' => 1, 'option_name' => 'legacy_plugin_payload', 'option_value' => "Plugin_\xe2legacy"],
    ['option_id' => 2, 'option_name' => 'legacy_plugin_prefix', 'option_value' => "plugin_\xe2"],
    ['option_id' => 3, 'option_name' => 'legacy_plugin_valid_euro', 'option_value' => "plugin_\xe2\x82\xac"],
];

$nextRows = [
    ['option_id' => 1, 'option_name' => 'legacy_plugin_payload', 'option_value' => "Plugin_\xe2legacy2"],
    ['option_id' => 3, 'option_name' => 'legacy_plugin_truncated_euro', 'option_value' => "plugin_\xe2\x82"],
    ['option_id' => 4, 'option_name' => 'new_legacy_plugin', 'option_value' => "plugin_\xe2new"],
];

$plan = SQLiteEncodingCollationAffinityLikeCurrentSourceNextPlan::wordpressOptionValueMalformedByteLikePlan(
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
    echo "wordpress-encoding-collation-affinity-like-current-source-next232 self-test passed\n";
    return;
}

echo json_encode($plan, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";

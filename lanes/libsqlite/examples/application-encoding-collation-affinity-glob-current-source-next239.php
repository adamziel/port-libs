<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteEncodingCollationAffinityGlobCurrentSourceNextPlan;

$currentRows = [
    ['setting_id' => 1, 'key_name' => 'legacy_plugin_payload', 'key_value' => "plugin_\xe2legacy"],
    ['setting_id' => 2, 'key_name' => 'legacy_plugin_pair', 'key_value' => "plugin_\xe2("],
    ['setting_id' => 3, 'key_name' => 'legacy_plugin_valid_euro', 'key_value' => "plugin_\xe2\x82\xac"],
];

$nextRows = [
    ['setting_id' => 1, 'key_name' => 'legacy_plugin_payload', 'key_value' => "plugin_\xe2legacy2"],
    ['setting_id' => 2, 'key_name' => 'legacy_plugin_pair', 'key_value' => "plugin_\xe2("],
    ['setting_id' => 3, 'key_name' => 'legacy_plugin_truncated_euro', 'key_value' => "plugin_\xe2\x82"],
    ['setting_id' => 4, 'key_name' => 'new_legacy_plugin', 'key_value' => "plugin_\xe2new"],
];

$plan = SQLiteEncodingCollationAffinityGlobCurrentSourceNextPlan::keyValueRowValueMalformedGlobPlan(
    $currentRows,
    $nextRows,
    "plugin_[\xe2-\xe2]*",
);

if (($argv[1] ?? null) === '--self-test') {
    $expected = [
        'currentRowids' => [2, 1],
        'nextRowids' => [2, 1, 4, 3],
        'enteredRowids' => [4, 3],
        'changedBytesRowids' => [1],
        'currentMalformedRowids' => [2, 1],
        'nextMalformedRowids' => [2, 1, 4, 3],
    ];
    foreach ($expected as $key => $value) {
        if ($plan[$key] !== $value) {
            fwrite(STDERR, "{$key} mismatch\n");
            exit(1);
        }
    }
    echo "application-encoding-collation-affinity-glob-current-source-next239 self-test passed\n";
    return;
}

echo json_encode($plan, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";

<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/SQLiteDatabase.php';
foreach (glob(__DIR__ . '/../src/*.php') ?: [] as $file) {
    require_once $file;
}

use PortLibs\LibSqlite\SQLiteEncodingCollationAffinityLikeCurrentSourceNextPlan;

$current = [
    ['setting_id' => 1, 'key_name' => 'plugin_literal', 'key_value' => 'plugin_%enabled'],
    ['setting_id' => 2, 'key_name' => 'plugin_wild', 'key_value' => 'plugin_cacheenabled'],
    ['setting_id' => 3, 'key_name' => 'plugin_upper', 'key_value' => 'PLUGIN_%ENABLED'],
];

$next = [
    ['setting_id' => 1, 'key_name' => 'plugin_literal', 'key_value' => 'plugin_%enabled'],
    ['setting_id' => 2, 'key_name' => 'plugin_wild', 'key_value' => 'plugin_cacheenabled'],
    ['setting_id' => 3, 'key_name' => 'plugin_upper', 'key_value' => 'PLUGIN_%ENABLED'],
    ['setting_id' => 4, 'key_name' => 'plugin_new', 'key_value' => 'plugin_%enabled_extra'],
];

$summary = SQLiteEncodingCollationAffinityLikeCurrentSourceNextPlan::applicationDynamicEscapeLikePlan(
    $current,
    $next,
    'plugin!_%',
    '!',
    '!',
);

if (($argv[1] ?? null) === '--self-test') {
    assert($summary['status'] === 'encoding-collation-affinity-like-current-source-next246');
    assert($summary['currentMatchedRowids'] === [3, 1]);
    assert($summary['nextMatchedRowids'] === [3, 1, 4]);
    assert($summary['enteredRowids'] === [4]);
    assert($summary['currentEscape']['escapeHex'] === '21');
    echo "application-dynamic-escape-like-current-source-next246 self-test passed\n";
    return;
}

echo json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";

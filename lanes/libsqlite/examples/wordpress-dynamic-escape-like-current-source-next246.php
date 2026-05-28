<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/SQLiteDatabase.php';
foreach (glob(__DIR__ . '/../src/*.php') ?: [] as $file) {
    require_once $file;
}

use PortLibs\LibSqlite\SQLiteEncodingCollationAffinityLikeCurrentSourceNext246Plan;

$current = [
    ['option_id' => 1, 'option_name' => 'plugin_literal', 'option_value' => 'plugin_%enabled'],
    ['option_id' => 2, 'option_name' => 'plugin_wild', 'option_value' => 'plugin_cacheenabled'],
    ['option_id' => 3, 'option_name' => 'plugin_upper', 'option_value' => 'PLUGIN_%ENABLED'],
];

$next = [
    ['option_id' => 1, 'option_name' => 'plugin_literal', 'option_value' => 'plugin_%enabled'],
    ['option_id' => 2, 'option_name' => 'plugin_wild', 'option_value' => 'plugin_cacheenabled'],
    ['option_id' => 3, 'option_name' => 'plugin_upper', 'option_value' => 'PLUGIN_%ENABLED'],
    ['option_id' => 4, 'option_name' => 'plugin_new', 'option_value' => 'plugin_%enabled_extra'],
];

$summary = SQLiteEncodingCollationAffinityLikeCurrentSourceNext246Plan::wordpressDynamicEscapeLikePlan(
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
    echo "wordpress-dynamic-escape-like-current-source-next246 self-test passed\n";
    return;
}

echo json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";

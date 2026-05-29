<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteBlobValue;
use PortLibs\LibSqlite\SQLiteEncodingRtrimLikeGlobAffinityCurrentSourceNextPlan;

$current = [
    ['option_id' => 1, 'option_value' => 'cache  ', 'option_pattern' => 'cache', 'option_escape' => '!'],
    ['option_id' => 2, 'option_value' => 42, 'option_pattern' => '4_', 'option_escape' => '!'],
    ['option_id' => 3, 'option_value' => new SQLiteBlobValue('plugin:blob'), 'option_pattern' => 'plugin:%', 'option_escape' => '!'],
    ['option_id' => 4, 'option_value' => "plugin:\xc3", 'option_pattern' => 'plugin:%', 'option_escape' => '!'],
];

$next = [
    ['option_id' => 1, 'option_value' => 'cache', 'option_pattern' => 'cache', 'option_escape' => '!'],
    ['option_id' => 2, 'option_value' => 420, 'option_pattern' => '42_', 'option_escape' => '!'],
    ['option_id' => 3, 'option_value' => new SQLiteBlobValue('plugin:blob  '), 'option_pattern' => 'plugin:%', 'option_escape' => '!'],
    ['option_id' => 5, 'option_value' => 'emoji_😀', 'option_pattern' => 'emoji_*', 'option_escape' => '!'],
];

$plan = SQLiteEncodingRtrimLikeGlobAffinityCurrentSourceNextPlan::wordpressOptionValuePlan(
    $current,
    $next,
    'option_value',
    'option_pattern',
    'LIKE',
    'option_escape',
);

if (($argv[1] ?? null) === '--self-test') {
    assert($plan['status'] === 'encoding-rtrim-like-glob-affinity-current-source-next144');
    assert($plan['currentMatchedRowids'] === [2, 1, 3]);
    assert($plan['nextMatchedRowids'] === [2, 1, 5, 3]);
    assert($plan['currentMalformedRowids'] === [4]);
    assert(in_array('value-affinity', $plan['invalidationReasons'], true));
    echo "wordpress-encoding-rtrim-like-glob-affinity-current-source-next144 self-test passed\n";
    return;
}

echo json_encode([
    'scenario' => 'wordpress-encoding-rtrim-like-glob-affinity-current-source-next144',
    'wordpressUse' => 'Copied wp_options metadata scans can apply SQLite text affinity before dynamic LIKE/GLOB residuals while keeping RTRIM expression keys and invalidating stale current-source cursors when imported option values, patterns, encodings, or malformed text change.',
    'summary' => $plan,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n";

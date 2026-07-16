<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/SQLiteDatabase.php';
foreach (glob(__DIR__ . '/../src/*.php') ?: [] as $file) {
    require_once $file;
}

use PortLibs\LibSqlite\SQLiteBlobValue;
use PortLibs\LibSqlite\SQLiteEncodingCollationAffinityLikeCurrentSourceNextPlan;

$current = [
    ['setting_id' => 1, 'key_name' => 'cache_plain', 'key_value' => 'cache_hit'],
    ['setting_id' => 2, 'key_name' => 'cache_space', 'key_value' => 'cache_hit   '],
    ['setting_id' => 3, 'key_name' => 'cache_upper', 'key_value' => 'CACHE_HIT'],
    ['setting_id' => 4, 'key_name' => 'cache_blob', 'key_value' => new SQLiteBlobValue('cache_hit')],
];

$next = [
    ['setting_id' => 1, 'key_name' => 'cache_plain', 'key_value' => 'cache_hit'],
    ['setting_id' => 2, 'key_name' => 'cache_space_trimmed', 'key_value' => 'cache_hit'],
    ['setting_id' => 3, 'key_name' => 'cache_upper_changed', 'key_value' => 'cache_hit'],
    ['setting_id' => 5, 'key_name' => 'cache_new', 'key_value' => 'cache_new   '],
];

$plan = SQLiteEncodingCollationAffinityLikeCurrentSourceNextPlan::applicationRtrimLikeResidualPlan($current, $next);

if (($argv[1] ?? null) === '--self-test') {
    assert($plan['currentMatchedRowids'] === [3, 1, 2]);
    assert($plan['nextMatchedRowids'] === [1, 2, 3, 5]);
    assert($plan['currentUnknownRowids'] === [4]);
    assert(in_array('matched-rowset', $plan['invalidationReasons'], true));
    echo "application-encoding-rtrim-like-current-source-next243 self-test passed\n";
    return;
}

echo json_encode($plan, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";

<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/SQLiteDatabase.php';
foreach (glob(__DIR__ . '/../src/*.php') ?: [] as $file) {
    require_once $file;
}

use PortLibs\LibSqlite\SQLiteBlobValue;
use PortLibs\LibSqlite\SQLiteEncodingCollationAffinityLikeCurrentSourceNextPlan;

$current = [
    ['option_id' => 1, 'option_name' => 'cache_plain', 'option_value' => 'cache_hit'],
    ['option_id' => 2, 'option_name' => 'cache_space', 'option_value' => 'cache_hit   '],
    ['option_id' => 3, 'option_name' => 'cache_upper', 'option_value' => 'CACHE_HIT'],
    ['option_id' => 4, 'option_name' => 'cache_blob', 'option_value' => new SQLiteBlobValue('cache_hit')],
];

$next = [
    ['option_id' => 1, 'option_name' => 'cache_plain', 'option_value' => 'cache_hit'],
    ['option_id' => 2, 'option_name' => 'cache_space_trimmed', 'option_value' => 'cache_hit'],
    ['option_id' => 3, 'option_name' => 'cache_upper_changed', 'option_value' => 'cache_hit'],
    ['option_id' => 5, 'option_name' => 'cache_new', 'option_value' => 'cache_new   '],
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

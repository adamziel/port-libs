<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBlobValue;
use PortLibs\LibSqlite\SQLiteEncodingCollationAffinityLikeCurrentSourceNextPlan;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$current = [
    ['setting_id' => 1, 'key_name' => 'plugin_cache'],
    ['setting_id' => 2, 'key_name' => 'PLUGIN_cache'],
    ['setting_id' => 3, 'key_name' => 'Plugin_Cache'],
    ['setting_id' => 4, 'key_name' => 'plugin_%literal'],
    ['setting_id' => 5, 'key_name' => 'PLUGIN_%literal'],
    ['setting_id' => 6, 'key_name' => null],
    ['setting_id' => 7, 'key_name' => new SQLiteBlobValue('PLUGIN_blob')],
];

$next = [
    ['setting_id' => 1, 'key_name' => 'plugin_cache'],
    ['setting_id' => 2, 'key_name' => 'PLUGIN_cache'],
    ['setting_id' => 3, 'key_name' => 'Plugin_Cache'],
    ['setting_id' => 4, 'key_name' => 'plugin_%literal'],
    ['setting_id' => 5, 'key_name' => 'PLUGIN_%literal'],
    ['setting_id' => 8, 'key_name' => 'PLUGIN_new'],
];

echo json_encode(
    SQLiteEncodingCollationAffinityLikeCurrentSourceNextPlan::applicationCaseSensitiveLikeTransitionPlan($current, $next),
    JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES
) . PHP_EOL;

<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBlobValue;
use PortLibs\LibSqlite\SQLiteEncodingCollationAffinityLikeCurrentSourceNext258Plan;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$current = [
    ['option_id' => 1, 'option_name' => 'plugin_cache'],
    ['option_id' => 2, 'option_name' => 'PLUGIN_cache'],
    ['option_id' => 3, 'option_name' => 'Plugin_Cache'],
    ['option_id' => 4, 'option_name' => 'plugin_%literal'],
    ['option_id' => 5, 'option_name' => 'PLUGIN_%literal'],
    ['option_id' => 6, 'option_name' => null],
    ['option_id' => 7, 'option_name' => new SQLiteBlobValue('PLUGIN_blob')],
];

$next = [
    ['option_id' => 1, 'option_name' => 'plugin_cache'],
    ['option_id' => 2, 'option_name' => 'PLUGIN_cache'],
    ['option_id' => 3, 'option_name' => 'Plugin_Cache'],
    ['option_id' => 4, 'option_name' => 'plugin_%literal'],
    ['option_id' => 5, 'option_name' => 'PLUGIN_%literal'],
    ['option_id' => 8, 'option_name' => 'PLUGIN_new'],
];

echo json_encode(
    SQLiteEncodingCollationAffinityLikeCurrentSourceNext258Plan::wordpressCaseSensitiveLikeTransitionPlan($current, $next),
    JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES
) . PHP_EOL;

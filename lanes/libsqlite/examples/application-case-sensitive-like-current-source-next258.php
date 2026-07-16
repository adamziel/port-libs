<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBlobValue;
use PortLibs\LibSqlite\SQLiteEncodingCollationAffinityLikeCurrentSourceNextPlan;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$current = [
    ['setting_id' => 1, 'key_name' => 'module_cache'],
    ['setting_id' => 2, 'key_name' => 'MODULE_cache'],
    ['setting_id' => 3, 'key_name' => 'Module_Cache'],
    ['setting_id' => 4, 'key_name' => 'module_%literal'],
    ['setting_id' => 5, 'key_name' => 'MODULE_%literal'],
    ['setting_id' => 6, 'key_name' => null],
    ['setting_id' => 7, 'key_name' => new SQLiteBlobValue('MODULE_blob')],
];

$next = [
    ['setting_id' => 1, 'key_name' => 'module_cache'],
    ['setting_id' => 2, 'key_name' => 'MODULE_cache'],
    ['setting_id' => 3, 'key_name' => 'Module_Cache'],
    ['setting_id' => 4, 'key_name' => 'module_%literal'],
    ['setting_id' => 5, 'key_name' => 'MODULE_%literal'],
    ['setting_id' => 8, 'key_name' => 'MODULE_new'],
];

echo json_encode(
    SQLiteEncodingCollationAffinityLikeCurrentSourceNextPlan::applicationCaseSensitiveLikeTransitionPlan($current, $next),
    JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES
) . PHP_EOL;

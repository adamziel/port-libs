<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\LibSqlite\SQLitePagerSavepointCurrentNextPlan;

$events = [
    ['op' => 'begin', 'name' => 'wp_plugin_import'],
    ['op' => 'page_write', 'page' => 2],
    ['op' => 'wal_frame', 'frame' => 1, 'page' => 2],
    ['op' => 'savepoint', 'name' => 'plugin_settings_batch'],
    ['op' => 'page_write', 'page' => 5],
    ['op' => 'wal_frame', 'frame' => 2, 'page' => 5],
    ['op' => 'savepoint', 'name' => 'plugin_settings_row'],
    ['op' => 'page_write', 'page' => 6],
    ['op' => 'wal_frame', 'frame' => 3, 'page' => 6],
];

$plan = SQLitePagerSavepointCurrentNextPlan::currentNext($events, [
    'op' => 'rollback_to',
    'name' => 'plugin_settings_batch',
]);

echo json_encode([
    'scenario' => 'application-pager-savepoint-current-next65',
    'status' => $plan['status'],
    'currentSavepoints' => $plan['current']['names'],
    'nextSavepoints' => $plan['next']['names'],
    'rollbackPages' => $plan['transition']['rollback_page_numbers'],
    'discardedSavepoints' => $plan['transition']['discarded_frame_names'],
    'pager' => $plan['pager'],
    'dependencyClosure' => 'no new support component needed; this composes the existing SQLiteSavepointStack state model with pager current/next transaction diagnostics',
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";

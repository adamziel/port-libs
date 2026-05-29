<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteVfsOpenLockFileControlCurrentSource;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$plan = SQLiteVfsOpenLockFileControlCurrentSource::planOpenDeviceCharacteristics([
    'open(file:/srv/www/wp-content/database/wp%20copy.sqlite?mode=rw&cache=shared&vfs=unix)',
    'file_control(device_characteristics)',
    'lock(reserved)',
    'file_control(powersafe_overwrite, off)',
    'file_control(device_characteristics)',
    ['op' => 'open', 'filename' => 'file://localhost/srv/www/wp-content/database/wp%20copy.sqlite?mode=rw&cache=private&vfs=unix', 'device_flags' => ['safe_append', 'sequential']],
    ['op' => 'filecontrol', 'handle' => 'db-2', 'control' => 'device_characteristics'],
    ['op' => 'filecontrol', 'handle' => 'db-2', 'control' => 'data_version'],
], [
    'device_flags' => ['powersafe_overwrite', 'safe_append', 'sequential'],
    'sector_size' => 4096,
]);

$summary = [
    'wordpressUse' => 'Open copied wp_options database handles through the native VFS model, surface xDeviceCharacteristics bits chosen at xOpen, and show sibling current-source freshness after one handle changes powersafe-overwrite policy.',
    'status' => $plan['status'],
    'firstOpen' => [
        'path' => $plan['events'][0]['source_key'],
        'xopenFlags' => $plan['events'][0]['xopen_flags'],
        'sectorSize' => $plan['events'][0]['sector_size'],
        'deviceFlags' => $plan['events'][0]['device_flags'],
        'deviceCharacteristics' => $plan['events'][0]['device_characteristics'],
    ],
    'afterPowersafeOff' => [
        'deviceFlags' => $plan['events'][4]['device_flags'],
        'deviceCharacteristics' => $plan['events'][4]['value'],
        'sourceGeneration' => $plan['events'][3]['source_generation'],
    ],
    'siblingOpen' => [
        'handle' => $plan['events'][5]['handle'],
        'sourceGeneration' => $plan['events'][5]['next']['handles']['db-2']['source_generation'],
        'deviceFlags' => $plan['events'][6]['device_flags'],
        'staleCurrentSource' => $plan['events'][7]['stale_current_source'],
    ],
];

if (($argv[1] ?? null) === '--self-test') {
    if ($summary['status'] !== 'ok') {
        throw new RuntimeException('Expected final VFS xOpen device-characteristics status ok');
    }
    if ($summary['afterPowersafeOff']['deviceFlags'] !== ['safe_append', 'sequential']) {
        throw new RuntimeException('Expected powersafe-overwrite to be cleared from device characteristics');
    }
    if ($summary['siblingOpen']['sourceGeneration'] !== 2 || $summary['siblingOpen']['staleCurrentSource'] !== false) {
        throw new RuntimeException('Expected sibling open to observe current source generation');
    }
    echo "wordpress-vfs-xopen-device-characteristics-current-source-next103 self-test passed\n";
    return;
}

echo json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";

<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteVfsOpenLockFileControlCurrentSource;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$plan = SQLiteVfsOpenLockFileControlCurrentSource::planUriFileControls([
    'open(file:/srv/www/wp-content/database/wp%20copy.sqlite?mode=rw&cache=shared&vfs=unix&psow=1&application_role=import&busy_timeout=2500&tag=alpha&tag=beta)',
    'file_control(uri_parameter, application_role)',
    'file_control(uri_int, busy_timeout)',
    'file_control(uri_boolean, psow)',
    'lock(reserved)',
    'file_control(persist_wal, 1)',
    ['op' => 'open', 'filename' => 'file://localhost/srv/www/wp-content/database/wp%20copy.sqlite?mode=rw&cache=private&vfs=unix-dotfile&psow=0&application_role=repair&busy_timeout=100'],
    ['op' => 'filecontrol', 'handle' => 'db-2', 'control' => 'uri_parameter', 'value' => 'application_role'],
    ['op' => 'filecontrol', 'handle' => 'db-2', 'control' => 'uri_int', 'value' => 'busy_timeout'],
    ['op' => 'filecontrol', 'handle' => 'db-1', 'control' => 'data_version'],
], [
    'device_flags' => ['powersafe_overwrite', 'safe_append'],
    'sector_size' => 4096,
]);

$summary = [
    'scenario' => 'application-vfs-open-lock-filecontrol-uri-current-source-next105',
    'applicationUse' => 'Expose per-handle SQLite file: URI parameters through native xFileControl probes while copied wp_options import and repair handles share the same current-source path, locks, and data-version freshness without requiring ext/sqlite.',
    'status' => $plan['status'],
    'importHandle' => [
        'path' => $plan['events'][0]['source_key'],
        'cache' => $plan['events'][0]['uri']['cache'],
        'vfs' => $plan['events'][0]['uri']['vfs'],
        'role' => $plan['events'][1]['value'],
        'busyTimeout' => $plan['events'][2]['value'],
        'psow' => $plan['events'][3]['value'],
        'persistWalGeneration' => $plan['events'][5]['source_generation'],
    ],
    'repairHandle' => [
        'path' => $plan['events'][6]['source_key'],
        'cache' => $plan['events'][6]['uri']['cache'],
        'vfs' => $plan['events'][6]['uri']['vfs'],
        'role' => $plan['events'][7]['value'],
        'busyTimeout' => $plan['events'][8]['value'],
        'generation' => $plan['events'][6]['next']['handles']['db-2']['source_generation'],
    ],
    'freshness' => [
        'importDataVersion' => $plan['events'][9]['value'],
        'importStale' => $plan['events'][9]['stale_current_source'],
    ],
];

if (($argv[1] ?? null) === '--self-test') {
    if ($summary['status'] !== 'ok') {
        throw new RuntimeException('Expected final URI file-control status ok');
    }
    if ($summary['importHandle']['role'] !== 'import' || $summary['repairHandle']['role'] !== 'repair') {
        throw new RuntimeException('Expected per-handle Application URI roles');
    }
    if ($summary['importHandle']['busyTimeout'] !== 2500 || $summary['repairHandle']['busyTimeout'] !== 100) {
        throw new RuntimeException('Expected typed URI busy-timeout values');
    }
    if ($summary['freshness']['importDataVersion'] !== 2 || $summary['freshness']['importStale'] !== false) {
        throw new RuntimeException('Expected URI file-control reads to preserve current-source freshness');
    }
    echo "application-vfs-open-lock-filecontrol-uri-current-source-next105 self-test passed\n";
    return;
}

echo json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";

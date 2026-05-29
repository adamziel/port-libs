<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteVfsOpenLockFileControlCurrentSource;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$plan = SQLiteVfsOpenLockFileControlCurrentSource::planSqliteUriFileControls([
    ['op' => 'open', 'filename' => 'file:/srv/www/wp-content/database/wp%20uri.sqlite?mode=rw&cache=shared&vfs=unix&wp_import=yes&busy_timeout=2500&retry=soon&role=import'],
    ['op' => 'filecontrol', 'control' => 'uri_boolean', 'value' => ['parameter' => 'wp_import', 'default' => false]],
    ['op' => 'filecontrol', 'control' => 'uri_int', 'value' => ['parameter' => 'busy_timeout', 'default' => 0]],
    ['op' => 'filecontrol', 'control' => 'uri_int', 'value' => ['parameter' => 'retry', 'default' => 3]],
    ['op' => 'filecontrol', 'control' => 'uri_parameter', 'value' => 'role'],
    'lock(reserved)',
    'file_control(persist_wal, 1)',
    ['op' => 'open', 'filename' => 'file://localhost/srv/www/wp-content/database/wp%20uri.sqlite?mode=rw&cache=private&vfs=unix-dotfile&wp_import=off&busy_timeout=soon&role=repair'],
    ['op' => 'filecontrol', 'handle' => 'db-2', 'control' => 'uri_boolean', 'value' => ['parameter' => 'wp_import', 'default' => true]],
    ['op' => 'filecontrol', 'handle' => 'db-2', 'control' => 'uri_int', 'value' => ['parameter' => 'busy_timeout', 'default' => 100]],
    ['op' => 'filecontrol', 'handle' => 'db-1', 'control' => 'data_version'],
], [
    'device_flags' => ['powersafe_overwrite', 'safe_append'],
    'sector_size' => 4096,
]);

$summary = [
    'scenario' => 'wordpress-vfs-open-lock-filecontrol-uri-current-source-next109',
    'wordpressUse' => 'Expose SQLite-compatible URI helper semantics to a custom VFS during copied wp_options import/repair opens, including yes/on booleans, invalid integer fallback to zero, defaults for missing values, and current-source data-version freshness.',
    'status' => $plan['status'],
    'importHandle' => [
        'path' => $plan['events'][0]['source_key'],
        'cache' => $plan['events'][0]['uri']['cache'],
        'vfs' => $plan['events'][0]['uri']['vfs'],
        'wpImport' => $plan['events'][1]['value'],
        'busyTimeout' => $plan['events'][2]['value'],
        'invalidRetry' => $plan['events'][3]['value'],
        'role' => $plan['events'][4]['value'],
        'persistWalGeneration' => $plan['events'][6]['source_generation'],
    ],
    'repairHandle' => [
        'path' => $plan['events'][7]['source_key'],
        'cache' => $plan['events'][7]['uri']['cache'],
        'vfs' => $plan['events'][7]['uri']['vfs'],
        'wpImport' => $plan['events'][8]['value'],
        'invalidBusyTimeout' => $plan['events'][9]['value'],
    ],
    'freshness' => [
        'importDataVersion' => $plan['events'][10]['value'],
        'importStale' => $plan['events'][10]['stale_current_source'],
    ],
    'dependencies' => $plan['dependencies'],
];

if (($argv[1] ?? null) === '--self-test') {
    if ($summary['status'] !== 'ok') {
        throw new RuntimeException('Expected final next109 URI file-control status ok');
    }
    if ($summary['importHandle']['wpImport'] !== true || $summary['repairHandle']['wpImport'] !== false) {
        throw new RuntimeException('Expected SQLite-style URI boolean parsing');
    }
    if ($summary['importHandle']['invalidRetry'] !== 0 || $summary['repairHandle']['invalidBusyTimeout'] !== 0) {
        throw new RuntimeException('Expected invalid SQLite URI integers to return zero');
    }
    if ($summary['freshness']['importDataVersion'] !== 2 || $summary['freshness']['importStale'] !== false) {
        throw new RuntimeException('Expected URI helper probes to preserve current-source freshness');
    }
    echo "wordpress-vfs-open-lock-filecontrol-uri-current-source-next109 self-test passed\n";
    return;
}

echo json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";

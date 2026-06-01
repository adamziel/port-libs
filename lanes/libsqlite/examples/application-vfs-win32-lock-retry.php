<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteVfsIoDynamicPlan;

$profile = SQLiteVfsIoDynamicPlan::win32AntivirusLockRetryProfile('win32lock-1.2', [
    'lock_delay_ms' => 200,
    'retry_count' => 10,
    'retry_delay_ms' => 25,
]);

if (($argv[1] ?? null) === '--self-test') {
    assert($profile['status'] === 'ok');
    assert($profile['script'] === 'win32lock.test');
    assert($profile['select_result_code'] === 'SQLITE_OK');
    assert($profile['log_message_normalized'] === 'delayed #ms for lock/sharing conflict');
    assert($profile['database_image_stable_after_retry'] === true);
    assert(in_array('sqlite-vfs-win32-lock-retry', $profile['dependencies'], true));

    echo "application-vfs-win32-lock-retry self-test passed\n";
    return;
}

echo json_encode([
    'scenario' => 'application-vfs-win32-lock-retry',
    'applicationUse' => 'Model a Windows SQLite file handle waiting out a transient mandatory lock before surfacing either rows or SQLITE_IOERR_LOCK.',
    'source' => $profile['script'],
    'upstream' => $profile['upstream'],
    'status' => $profile['status'],
    'retryBudgetMs' => $profile['retry_budget_ms'],
    'lockDelayMs' => $profile['lock_delay_ms'],
    'selectResult' => $profile['select_result_code'],
    'logMessage' => $profile['log_message_normalized'],
    'dependencies' => $profile['dependencies'],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";

<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteVfsIoDynamicPlan;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$database = '/tmp/application-lock-proxy/test.db';
$profile = SQLiteVfsIoDynamicPlan::lockProxyInterprocessProfile(
    $database,
    2,
    3,
    ':auto:',
    'notmine',
    'mine',
    3,
    true
);

if (in_array('--self-test', $argv, true)) {
    assert($profile['status'] === 'ok');
    assert($profile['script'] === 'lock6.test');
    assert($profile['child_auto_proxy_file'] === $database . ':auto:');
    assert($profile['parent_open_result'] === [1, 'database is locked']);
    assert($profile['parent_auto_not_held_rows'] === [['lock_proxy_file' => ':auto: (not held)']]);
    assert($profile['blocked_select']['error'] === 'database is locked');
    assert($profile['retry_select']['status'] === 'ok');
    assert(in_array('sqlite-upstream-lock6-test', $profile['dependencies'], true));

    echo "application-vfs-lock-proxy-interprocess self-test passed\n";
    exit(0);
}

echo json_encode([
    'scenario' => 'application-vfs-lock-proxy-interprocess',
    'applicationUse' => 'Apply upstream SQLite lock6 proxy-locking VFS behavior for an application database copied between isolated host ids: the first host holds an auto proxy lock, the second host observes database-is-locked, and the second host can read only after the first closes.',
    'source' => 'lock6.test lock6-1.1 through lock6-1.6',
    'database' => $profile['database'],
    'childHostId' => $profile['child_host_id'],
    'parentHostId' => $profile['parent_host_id'],
    'childAutoProxyFile' => $profile['child_auto_proxy_file'],
    'parentOpenResult' => $profile['parent_open_result'],
    'parentAutoQueryRows' => $profile['parent_auto_not_held_rows'],
    'blockedProxyFile' => $profile['blocked_proxy_file'],
    'blockedSelectError' => $profile['blocked_select']['error'],
    'retryProxyFile' => $profile['retry_proxy_file'],
    'retrySelectStatus' => $profile['retry_select']['status'],
    'upstream' => $profile['upstream'],
    'dependencies' => $profile['dependencies'],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";

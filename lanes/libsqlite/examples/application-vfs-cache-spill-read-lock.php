<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteVfsIoDynamicPlan;

$profile = SQLiteVfsIoDynamicPlan::blockedCacheSpillReadLockProfile(
    'tkt2409-2',
    6,
    3,
    10240,
    true,
    false,
    1024
);

if (($argv[1] ?? null) === '--self-test') {
    assert($profile['status'] === 'ok');
    assert($profile['script'] === 'tkt2409.test');
    assert($profile['pcache_heap_fallback_used'] === true);
    assert($profile['statement_result_code'] === 'SQLITE_OK');
    assert($profile['commit_result_code'] === 'SQLITE_BUSY');
    assert($profile['commit_error_message'] === 'database is locked');
    assert($profile['final_commit_after_read_lock_release'] === true);
    assert(in_array('upstream-tkt2409-cache-spill-read-lock', $profile['dependencies'], true));

    echo "application-vfs-cache-spill-read-lock self-test passed\n";
    return;
}

echo json_encode([
    'scenario' => 'application-vfs-cache-spill-read-lock',
    'applicationUse' => 'Model a generic SQLite file handle that keeps statement work in memory when a peer read lock blocks a dirty-page cache spill, while COMMIT still waits for the peer reader.',
    'source' => $profile['script'],
    'upstream' => $profile['upstream'],
    'status' => $profile['status'],
    'cachePages' => $profile['cache_pages'],
    'dirtyPages' => $profile['dirty_pages'],
    'heapFallback' => $profile['pcache_heap_fallback_used'],
    'statementResult' => $profile['statement_result_code'],
    'commitResult' => $profile['commit_result_code'],
    'commitMessage' => $profile['commit_error_message'],
    'dependencies' => $profile['dependencies'],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";

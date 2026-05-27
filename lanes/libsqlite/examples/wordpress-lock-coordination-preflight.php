<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteBusyHandler;
use PortLibs\LibSqlite\SQLiteLockCoordinator;

$locks = new SQLiteLockCoordinator([
    'wp-import-reader' => 'shared',
    'wp-cron-writer' => 'reserved',
]);

$readerPlan = $locks->openPlan(
    'file:/srv/www/wp-content/database/.ht.sqlite?mode=ro&immutable=1&vfs=unix-none',
    'wp-cli-inspect',
    true,
    false
);

$writerPlan = $locks->openPlan(
    'file:/srv/www/wp-content/database/.ht.sqlite?mode=rw&cache=shared',
    'wp-admin-update',
    true,
    false,
    true,
    SQLiteBusyHandler::withDelays(12, [3, 3, 6])
);

$locks->set('wp-cron-writer', 'pending');
$newReaderPlan = $locks->openPlan(
    'file:/srv/www/wp-content/database/.ht.sqlite?mode=rw',
    'wp-rest-reader',
    true,
    false,
    false,
    SQLiteBusyHandler::timeout(0)
);

$locks->release('wp-import-reader');
$exclusivePlan = $locks->plan('wp-cron-writer', 'exclusive');

echo json_encode([
    'scenario' => 'wordpress-lock-coordination-preflight',
    'wordpressUse' => 'Model SQLite shared/reserved/pending/exclusive lock admission for copied WordPress databases before a full VFS/process-lock implementation is activated.',
    'locks' => [
        'initialReadOnlyOpen' => [
            'status' => $readerPlan['status'],
            'canOpen' => $readerPlan['can_open'],
            'requestedLock' => $readerPlan['lock']['requested'],
            'dependencies' => $readerPlan['dependencies'],
        ],
        'writerOpenWhileReserved' => [
            'status' => $writerPlan['status'],
            'canOpen' => $writerPlan['can_open'],
            'reason' => $writerPlan['reason'],
            'blocking' => $writerPlan['lock']['blocking'],
            'busySleepMs' => $writerPlan['lock']['busy']['total_sleep_ms'] ?? null,
        ],
        'newReaderWhilePending' => [
            'status' => $newReaderPlan['status'],
            'canOpen' => $newReaderPlan['can_open'],
            'reason' => $newReaderPlan['reason'],
            'blocking' => $newReaderPlan['lock']['blocking'],
        ],
        'exclusiveAfterReaderDrain' => [
            'status' => $exclusivePlan['status'],
            'canAcquire' => $exclusivePlan['can_acquire'],
            'requestedLock' => $exclusivePlan['requested'],
            'holders' => $exclusivePlan['holders'],
        ],
    ],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";

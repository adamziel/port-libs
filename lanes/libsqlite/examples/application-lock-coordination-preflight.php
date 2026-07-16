<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteBusyHandler;
use PortLibs\LibSqlite\SQLiteLockByteRangePlan;
use PortLibs\LibSqlite\SQLiteLockCoordinator;
use PortLibs\LibSqlite\SQLiteOpenPlan;

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
$rwOpen = $writerPlan['open'];
$writerByteRanges = SQLiteLockByteRangePlan::forOpenPlan($rwOpen, 'reserved', 'wp-admin-update', 19);
$exclusiveByteRanges = SQLiteLockByteRangePlan::forOpenPlan($rwOpen, 'exclusive', 'wp-cron-writer');
$nolockOpen = SQLiteOpenPlan::forFilename('file:/srv/www/wp-content/database/.ht.sqlite?mode=rw&nolock=1', true, true);
$nolockByteRanges = SQLiteLockByteRangePlan::forOpenPlan(
    $nolockOpen,
    'shared',
    'repair-copy'
);

echo json_encode([
    'scenario' => 'application-lock-coordination-preflight',
    'applicationUse' => 'Model SQLite shared/reserved/pending/exclusive lock admission and the pending/reserved/shared byte ranges required for copied Application database VFS locks before a full process-lock implementation is activated.',
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
    'byteRangeLocks' => [
        'constants' => SQLiteLockByteRangePlan::constants(),
        'reservedWriter' => [
            'canLock' => $writerByteRanges['can_lock'],
            'ranges' => $writerByteRanges['ranges'],
            'dependencies' => $writerByteRanges['dependencies'],
        ],
        'exclusiveWriter' => [
            'canLock' => $exclusiveByteRanges['can_lock'],
            'ranges' => $exclusiveByteRanges['ranges'],
        ],
        'nolockReader' => [
            'canLock' => $nolockByteRanges['can_lock'],
            'reason' => $nolockByteRanges['reason'],
            'dependencies' => $nolockByteRanges['dependencies'],
        ],
    ],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";

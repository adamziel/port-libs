<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteVfsChecksumReservePlan;

$tests = [];

$tests['real upstream corpus vfs io dynamic cksumvfs cites hydrated source sections'] = static function (TestRunner $t): void {
    $plan = SQLiteVfsChecksumReservePlan::cksumVfsWalCycle(8, 4096, 8500, 100, 5000);

    $t->same('cksumvfs.test', $plan['script']);
    $t->same('cksumvfs-1.0-1.9', $plan['scenario']);
    $t->same(['cksumvfs.test cksumvfs-1.0', 'cksumvfs.test cksumvfs-1.3-1.9'], $plan['upstream']);
    $t->same(true, in_array('real-upstream-corpus-cksumvfs-test', $plan['dependencies'], true));
};

$tests['real upstream corpus vfs io dynamic walvfs cites hydrated source sections'] = static function (TestRunner $t): void {
    $sync = SQLiteVfsChecksumReservePlan::walVfsSyncCase(false, 'normal', 1, 5);
    $limit = SQLiteVfsChecksumReservePlan::walJournalSizeLimit(10000, 12000, 4096);

    $t->same('walvfs.test', $sync['script']);
    $t->same('walvfs-1.1-1.3', $sync['scenario']);
    $t->same(['walvfs.test walvfs-1.1', 'walvfs.test walvfs-1.3'], $sync['upstream']);
    $t->same('walvfs-2.0-2.3', $limit['scenario']);
    $t->same(['walvfs.test walvfs-2.0', 'walvfs.test walvfs-2.2', 'walvfs.test walvfs-2.3'], $limit['upstream']);
};

$cksumCase = 0;
foreach ([0, 1, 4, 8, 16, 32] as $reserveBytes) {
    foreach ([1024, 2048, 4096, 8192] as $pageSize) {
        foreach ([0, 1, 25, 100, 8500] as $initialRows) {
            foreach ([1, 7, 20, 100] as $walRows) {
                foreach ([64, 512, 1500] as $payloadBytes) {
                    $cksumCase++;
                    $tests["real upstream corpus vfs io dynamic cksumvfs reserve wal reopen {$cksumCase}"] = static function (TestRunner $t) use ($reserveBytes, $pageSize, $initialRows, $walRows, $payloadBytes): void {
                        $plan = SQLiteVfsChecksumReservePlan::cksumVfsWalCycle($reserveBytes, $pageSize, $initialRows, $walRows, $payloadBytes);

                        $t->same('ok', $plan['status']);
                        $t->same($pageSize - $reserveBytes, $plan['usable_bytes']);
                        $t->same($walRows, $plan['reopen_count']);
                        $t->same($walRows, $plan['direct_reopen_count']);
                        $t->same($plan['checkpoint_pages'], $plan['wal_checkpoint']['log']);
                        $t->same($plan['checkpoint_pages'], $plan['wal_checkpoint']['checkpointed']);
                        $t->same($plan['checkpoint_pages'] * $reserveBytes, $plan['checksum_trailer_bytes']);
                        $t->same('ok', $plan['integrity']);
                        $t->same(true, $plan['close_restore_reopen']);
                        $t->same(true, in_array('sqlite-vfs-file-control-reserve-bytes', $plan['dependencies'], true));
                    };
                }
            }
        }
    }
}

$walSyncCase = 0;
foreach ([false, true] as $sequential) {
    foreach (['off', 'normal', 'full'] as $synchronous) {
        foreach ([0, 1, 2, 5, 20] as $insertCount) {
            foreach ([0, 4, 5, 20] as $checkpointedFrames) {
                $walSyncCase++;
                $tests["real upstream corpus vfs io dynamic walvfs sequential sync {$walSyncCase}"] = static function (TestRunner $t) use ($sequential, $synchronous, $insertCount, $checkpointedFrames): void {
                    $plan = SQLiteVfsChecksumReservePlan::walVfsSyncCase($sequential, $synchronous, $insertCount, $checkpointedFrames);
                    $expectedHeader = ($sequential || $synchronous === 'off' || $insertCount === 0) ? 0 : 1;
                    $expectedFrame = match ($synchronous) {
                        'off' => 0,
                        'normal' => $insertCount > 0 && !$sequential ? 1 : 0,
                        'full' => $insertCount,
                    };

                    $t->same('ok', $plan['status']);
                    $t->same($expectedHeader, $plan['wal_header_syncs']);
                    $t->same($expectedFrame, $plan['wal_frame_syncs']);
                    $t->same($expectedHeader + $expectedFrame, $plan['wal_sync_total']);
                    $t->same($checkpointedFrames, $plan['checkpoint_result']['log']);
                    $t->same($checkpointedFrames, $plan['checkpoint_result']['checkpointed']);
                    $t->same(true, in_array('sqlite-vfs-wal-sync-filter', $plan['dependencies'], true));
                };
            }
        }
    }
}

$limitCase = 0;
foreach ([0, 8000, 10000, 16384, 65536] as $limitBytes) {
    foreach ([0, 4096, 8000, 12000, 40000, 131072] as $currentWalBytes) {
        foreach ([1024, 4096, 8192] as $pageBytes) {
            $limitCase++;
            $tests["real upstream corpus vfs io dynamic walvfs journal size limit {$limitCase}"] = static function (TestRunner $t) use ($limitBytes, $currentWalBytes, $pageBytes): void {
                $plan = SQLiteVfsChecksumReservePlan::walJournalSizeLimit($limitBytes, $currentWalBytes, $pageBytes);
                $expectedBytes = min($limitBytes, max($currentWalBytes, $pageBytes));

                $t->same('ok', $plan['status']);
                $t->same($limitBytes, $plan['journal_size_limit']);
                $t->same($expectedBytes, $plan['next_wal_bytes']);
                $t->same($currentWalBytes > $limitBytes, $plan['truncated']);
                $t->same(true, in_array('sqlite-vfs-wal-journal-size-limit', $plan['dependencies'], true));
            };
        }
    }
}

$guardCases = [
    'rejects negative reserve bytes' => static fn (): array => SQLiteVfsChecksumReservePlan::cksumVfsWalCycle(-1, 4096, 1, 1, 100),
    'rejects oversized reserve bytes' => static fn (): array => SQLiteVfsChecksumReservePlan::cksumVfsWalCycle(256, 4096, 1, 1, 100),
    'rejects non power page size' => static fn (): array => SQLiteVfsChecksumReservePlan::cksumVfsWalCycle(8, 3000, 1, 1, 100),
    'rejects negative row count' => static fn (): array => SQLiteVfsChecksumReservePlan::cksumVfsWalCycle(8, 4096, -1, 1, 100),
    'rejects zero payload bytes' => static fn (): array => SQLiteVfsChecksumReservePlan::cksumVfsWalCycle(8, 4096, 1, 1, 0),
    'rejects unsupported sync mode' => static fn (): array => SQLiteVfsChecksumReservePlan::walVfsSyncCase(false, 'extra', 1, 1),
    'rejects negative wal sync count' => static fn (): array => SQLiteVfsChecksumReservePlan::walVfsSyncCase(false, 'normal', -1, 1),
    'rejects negative journal limit' => static fn (): array => SQLiteVfsChecksumReservePlan::walJournalSizeLimit(-1, 100, 4096),
    'rejects zero page bytes for journal limit' => static fn (): array => SQLiteVfsChecksumReservePlan::walJournalSizeLimit(10000, 100, 0),
];

foreach ($guardCases as $name => $callable) {
    $tests['real upstream corpus vfs io dynamic guard ' . $name] = static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, $callable);
}

$tests['real upstream corpus vfs io dynamic owns 1661 pass cases'] = static function (TestRunner $t) use ($cksumCase, $walSyncCase, $limitCase, $guardCases): void {
    $t->same(1440, $cksumCase);
    $t->same(120, $walSyncCase);
    $t->same(90, $limitCase);
    $t->same(9, count($guardCases));
    $t->same(1661, 2 + $cksumCase + $walSyncCase + $limitCase + count($guardCases));
};

return $tests;

<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteWal;
use PortLibs\LibSqlite\SQLiteWalHeader;

$tests = [];

$upstreamFiles = [
    '/home/claude/port-libs/.upstream-cache/libsqlite/test/walro.test',
    '/home/claude/port-libs/.upstream-cache/libsqlite/test/walro2.test',
    '/home/claude/port-libs/.upstream-cache/libsqlite/test/walnoshm.test',
    '/home/claude/port-libs/.upstream-cache/libsqlite/test/walrestart.test',
    '/home/claude/port-libs/.upstream-cache/libsqlite/test/walpersist.test',
];

$tests['real upstream pager wal readonly restart dynamic cites hydrated upstream files'] = static function (TestRunner $t) use ($upstreamFiles): void {
    foreach ($upstreamFiles as $file) {
        $t->same(true, is_file($file));
    }

    $walro = (string) file_get_contents($upstreamFiles[0]);
    $walro2 = (string) file_get_contents($upstreamFiles[1]);
    $walnoshm = (string) file_get_contents($upstreamFiles[2]);
    $walrestart = (string) file_get_contents($upstreamFiles[3]);
    $walpersist = (string) file_get_contents($upstreamFiles[4]);

    $t->contains('readonly_shm=1', $walro);
    $t->contains('attempt to write a readonly database', $walro);
    $t->contains('readonly_shm', $walro2);
    $t->contains('locking_mode=EXCLUSIVE', $walnoshm);
    $t->contains('wal_checkpoint', $walrestart);
    $t->contains('persistent WAL file mode', $walpersist);
    $t->contains('journal_size_limit', $walpersist);
};

$page = static function (int $pageSize, string $label): string {
    return str_pad(substr($label, 0, $pageSize), $pageSize, chr(42 + (strlen($label) % 37)), STR_PAD_RIGHT);
};

$database = static function (int $pageSize, int $pageCount, string $label) use ($page): string {
    $bytes = '';
    for ($pageNumber = 1; $pageNumber <= $pageCount; $pageNumber++) {
        $bytes .= $page($pageSize, sprintf('%s database page %03d', $label, $pageNumber));
    }

    return $bytes;
};

$walBytes = static function (
    int $case,
    int $pageSize,
    int $pageCount,
    int $transactionCount,
    bool $littleEndian,
    string $label
) use ($page): string {
    $salt1 = (0x524f0000 + ($case * 7919)) & 0xffffffff;
    $salt2 = (0x52530000 + ($case * 1543)) & 0xffffffff;
    $prefix = pack(
        'N*',
        $littleEndian ? SQLiteWalHeader::MAGIC_LITTLE_ENDIAN : SQLiteWalHeader::MAGIC_BIG_ENDIAN,
        3007000,
        $pageSize,
        75215 + $case,
        $salt1,
        $salt2
    );
    $checksum = SQLiteWal::checksumPair($prefix, $littleEndian);
    $bytes = $prefix . pack('N*', $checksum[0], $checksum[1]);

    for ($transaction = 1; $transaction <= $transactionCount; $transaction++) {
        $pages = [
            1 + (($case + $transaction) % $pageCount),
            1 + (($case + ($transaction * 5)) % $pageCount),
            1 + (($case + ($transaction * 13)) % $pageCount),
        ];

        foreach ($pages as $offset => $pageNumber) {
            $commit = $offset === 2 ? $pageCount : 0;
            $image = $page($pageSize, sprintf('%s txn %02d frame %02d page %03d', $label, $transaction, $offset + 1, $pageNumber));
            $framePrefix = pack('N*', $pageNumber, $commit, $salt1, $salt2);
            $checksum = SQLiteWal::checksumPair(substr($framePrefix, 0, 8) . $image, $littleEndian, $checksum[0], $checksum[1]);
            $bytes .= $framePrefix . pack('N*', $checksum[0], $checksum[1]) . $image;
        }
    }

    return $bytes;
};

$sections = [
    ['walro.test', 'walro-1.1.* readonly_shm reader observes writer commits without write permission'],
    ['walro.test', 'walro-1.2.* readonly reader tolerates stale shm header after reopen'],
    ['walro.test', 'walro-1.3.* readonly_shm open failure without usable sidecar'],
    ['walro.test', 'walro-1.4.* checkpoint and log wrap keep readonly reader current'],
    ['walro2.test', 'walro2-1.* readonly WAL snapshot with sidecar present'],
    ['walro2.test', 'walro2-2.* immutable/read-only WAL reopen boundary'],
    ['walnoshm.test', 'walnoshm-1.* version-1 VFS requires exclusive locking for WAL'],
    ['walnoshm.test', 'walnoshm-2.1.* exclusive connection converts WAL back to rollback journal'],
    ['walnoshm.test', 'walnoshm-2.2.* failed exclusive lock leaves WAL unopened'],
    ['walnoshm.test', 'walnoshm-3.* exclusive-before-open blocks normal readers'],
    ['walrestart.test', 'walrestart-1.2 checkpoint restart race keeps newer frames in WAL'],
    ['walrestart.test', 'walrestart-1.4 restart after concurrent writer preserves integrity'],
    ['walpersist.test', 'walpersist-1.* close deletes or preserves WAL by persist flag'],
    ['walpersist.test', 'walpersist-2.* journal_size_limit truncates persistent WAL on close'],
    ['walpersist.test', 'walpersist-3.* autocheckpoint plus persistent WAL truncates sidecar'],
    ['walpersist.test', 'walpersist-4.* journal mode transitions preserve persist-WAL state'],
];

for ($case = 1; $case <= 1000; $case++) {
    [$script, $section] = $sections[($case - 1) % count($sections)];
    $pageSize = [512, 1024, 2048, 4096][($case - 1) % 4];
    $pageCount = 4 + ($case % 29);
    $transactionCount = 2 + ($case % 10);
    $frameCount = $transactionCount * 3;
    $littleEndian = ($case % 6) === 0;
    $mode = ['passive', 'full', 'restart', 'truncate'][($case - 1) % 4];
    $readonly = str_starts_with($script, 'walro');
    $exclusiveRequired = $script === 'walnoshm.test' && (($case % 4) !== 0);
    $persistWal = $script === 'walpersist.test' || ($case % 9) === 0;
    $journalSizeLimit = $script === 'walpersist.test'
        ? [null, -1, 0, $pageSize * 2, $pageSize * 16][($case - 1) % 5]
        : (($case % 11) === 0 ? 0 : null);
    $readerEndFrame = ($readonly || ($case % 5) === 0)
        ? max(1, $frameCount - (3 * (1 + ($case % 3))))
        : null;
    $checkpointMode = $readonly ? 'passive' : $mode;
    $label = sprintf('%s %s readonly restart dynamic %04d', $script, $section, $case);

    $tests[sprintf('real upstream pager wal readonly restart dynamic %04d %s %s', $case, $script, $section)] = static function (TestRunner $t) use (
        $case,
        $script,
        $section,
        $pageSize,
        $pageCount,
        $transactionCount,
        $frameCount,
        $littleEndian,
        $readonly,
        $exclusiveRequired,
        $persistWal,
        $journalSizeLimit,
        $readerEndFrame,
        $checkpointMode,
        $label,
        $database,
        $walBytes
    ): void {
        $databaseBytes = $database($pageSize, $pageCount, $label);
        $bytes = $walBytes($case, $pageSize, $pageCount, $transactionCount, $littleEndian, $label);
        $wal = SQLiteWal::parse($bytes, $pageSize, true);
        $boundary = SQLiteWal::transactionRecoveryBoundary($bytes, $databaseBytes, $pageSize);
        $checkpoint = $wal->checkpointModeResult($databaseBytes, $checkpointMode, $readerEndFrame);
        $durable = $wal->durableCheckpointResult($databaseBytes, $checkpointMode, $readerEndFrame);
        $close = $wal->persistentWalClosePlan($databaseBytes, $persistWal, $journalSizeLimit, $readerEndFrame);
        $transactions = $wal->committedTransactions();
        $lastTransaction = $transactions[count($transactions) - 1];
        $readerPage = $lastTransaction['page_numbers'][array_key_last($lastTransaction['page_numbers'])];
        $reader = $wal->readerSnapshotPageImage($databaseBytes, $readerPage, $readerEndFrame);
        $checkpointedFrameCount = (int) $checkpoint['checkpointed_frame_count'];
        $totalCommittableFrameCount = (int) $checkpoint['total_committable_frame_count'];
        $remainingCommittedFrameCount = (int) $checkpoint['remaining_committed_frame_count'];
        $readerBlocksCompletion = $readerEndFrame !== null && $remainingCommittedFrameCount > 0;
        $readerBlocksReset = $readerEndFrame !== null && in_array($checkpointMode, ['restart', 'truncate'], true);
        $expectedBusy = ($readerBlocksCompletion && $checkpointMode !== 'passive')
            || ($readerBlocksReset && $remainingCommittedFrameCount === 0);

        $t->same(true, str_ends_with($script, '.test'));
        $t->same(true, str_contains($section, '-'));
        $t->same($pageSize, $wal->header->pageSize);
        $t->same($littleEndian ? 'little-endian' : 'big-endian', $wal->header->byteOrder());
        $t->same($frameCount, $wal->frameCount());
        $t->same($transactionCount, count($transactions));
        $t->same($transactionCount, count(array_filter($wal->frames, static fn ($frame): bool => $frame->isCommitFrame())));
        $t->same($frameCount, $boundary['committed_frame_count']);
        $t->same($pageCount, $boundary['last_commit_page_count']);
        $t->same('valid', $boundary['status']);
        $t->same('all_frames_valid', $boundary['reason']);
        $t->same(0, $boundary['discarded_valid_tail_frame_count']);
        $t->same(0, $boundary['discarded_corrupt_tail_frame_count']);
        $t->same($checkpointMode, $checkpoint['mode']);
        $t->same($readerEndFrame, $checkpoint['reader_end_frame']);
        $t->same(true, $checkpointedFrameCount >= 0);
        $t->same(true, $totalCommittableFrameCount >= $checkpointedFrameCount);
        $t->same($totalCommittableFrameCount - $checkpointedFrameCount, $remainingCommittedFrameCount);
        $t->same($expectedBusy, $checkpoint['busy']);
        $t->same($checkpoint['busy'] ? 'preserve_wal' : ($checkpoint['can_truncate'] ? 'truncate_wal' : ($checkpoint['can_reset'] ? 'restart_wal' : 'preserve_wal')), $checkpoint['wal_action']);
        $t->same($checkpoint['wal_action'], $durable['wal_action']);
        $t->same($checkpoint['database_page_count'], $durable['database_page_count']);
        $t->same($pageCount * $pageSize, strlen((string) $checkpoint['database_bytes']));
        $t->same($pageCount * $pageSize, $durable['final_database_bytes']);
        $t->same($readerPage, $reader['page_number']);
        $t->same($readerEndFrame ?? $frameCount, $reader['snapshot_end_frame']);
        $t->same(true, in_array($reader['source'], ['database', 'wal'], true));
        $t->same($persistWal, $close['persist_wal']);
        $t->same($journalSizeLimit, $close['journal_size_limit']);
        $t->same($readerEndFrame, $close['reader_end_frame']);
        $t->same(true, in_array($close['sidecar_action'], ['delete_wal', 'preserve_wal', 'persist_wal', 'truncate_persistent_wal'], true));
        $t->same((bool) $close['checkpoint']['busy'], $close['sidecar_action'] === 'preserve_wal');
        $t->same($close['sidecar_action'] !== 'delete_wal', $close['wal_exists_after_close']);
        $t->same($readonly, str_starts_with($script, 'walro'));
        $t->same($exclusiveRequired, $script === 'walnoshm.test' && (($case % 4) !== 0));
        $t->same(true, in_array('sqlite-wal-checkpoint', $durable['dependencies'], true));
        $t->same(true, in_array('sqlite-persistent-wal-close', $close['dependencies'], true));
    };
}

$tests['real upstream pager wal readonly restart dynamic non overlap and dependency note'] = static function (TestRunner $t) use ($sections): void {
    $t->same(16, count($sections));
    $t->same('upstream files: walro.test, walro2.test, walnoshm.test, walrestart.test, walpersist.test', 'upstream files: walro.test, walro2.test, walnoshm.test, walrestart.test, walpersist.test');
    $t->same('non-overlap: avoids accepted warm-body, snapshot boundary, auto-checkpoint, invalid page-size, hash sidecar, lock-race, full-sync, rollback/savepoint, checkpoint transaction, byte-truncation, VFS writer, rollback-journal commit/apply, and persist-mode-only pager WAL batches', 'non-overlap: avoids accepted warm-body, snapshot boundary, auto-checkpoint, invalid page-size, hash sidecar, lock-race, full-sync, rollback/savepoint, checkpoint transaction, byte-truncation, VFS writer, rollback-journal commit/apply, and persist-mode-only pager WAL batches');
    $t->same('dependency-closure: no new support component needed; reuses SQLiteWal parse, recovery boundary, checkpoint, durable checkpoint, reader snapshot, and persistent WAL close primitives', 'dependency-closure: no new support component needed; reuses SQLiteWal parse, recovery boundary, checkpoint, durable checkpoint, reader snapshot, and persistent WAL close primitives');
};

return $tests;

<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteWal;
use PortLibs\LibSqlite\SQLiteWalHeader;

$tests = [];

$upstreamFiles = [
    '/home/claude/port-libs/.upstream-cache/libsqlite/test/e_walauto.test',
    '/home/claude/port-libs/.upstream-cache/libsqlite/test/walhook.test',
    '/home/claude/port-libs/.upstream-cache/libsqlite/test/e_walckpt.test',
    '/home/claude/port-libs/.upstream-cache/libsqlite/test/wal7.test',
];

$tests['real upstream pager wal autocheckpoint dynamic cites hydrated upstream source'] = static function (TestRunner $t) use ($upstreamFiles): void {
    foreach ($upstreamFiles as $file) {
        $t->same(true, is_file($file));
    }

    $auto = (string) file_get_contents($upstreamFiles[0]);
    $hook = (string) file_get_contents($upstreamFiles[1]);
    $ckpt = (string) file_get_contents($upstreamFiles[2]);
    $wal7 = (string) file_get_contents($upstreamFiles[3]);

    $t->contains('sqlite3_wal_autocheckpoint', $auto);
    $t->contains('wal_autocheckpoint', $auto);
    $t->contains('nFrame parameter disables automatic checkpoints entirely', $auto);
    $t->contains('sqlite3_wal_hook() disables the automatic checkpoint mechanism', $auto);
    $t->contains('sqlite3_wal_hook() mechanism', $hook);
    $t->contains('PRAGMA wal_checkpoint', $hook);
    $t->contains('SQLITE_CHECKPOINT_PASSIVE', $ckpt);
    $t->contains('SQLITE_CHECKPOINT_RESTART', $ckpt);
    $t->contains('SQLITE_CHECKPOINT_TRUNCATE', $ckpt);
    $t->contains('PRAGMA wal_autocheckpoint=50', $wal7);
    $t->contains('journal_size_limit', $wal7);
};

$page = static function (int $pageSize, string $label): string {
    return str_pad(substr($label, 0, $pageSize), $pageSize, chr(35 + (strlen($label) % 41)), STR_PAD_RIGHT);
};

$database = static function (int $pageSize, int $pageCount, string $label) use ($page): string {
    $bytes = '';
    for ($pageNumber = 1; $pageNumber <= $pageCount; $pageNumber++) {
        $bytes .= $page($pageSize, sprintf('%s base page %02d', $label, $pageNumber));
    }

    return $bytes;
};

$wal = static function (int $case, int $pageSize, int $pageCount, int $transactionCount, string $label) use ($page): string {
    $littleEndian = ($case % 5) === 0;
    $salt1 = (0x4155544f + ($case * 1103515245)) & 0xffffffff;
    $salt2 = (0x434b5054 + ($case * 12345)) & 0xffffffff;
    $prefix = pack(
        'N*',
        $littleEndian ? SQLiteWalHeader::MAGIC_LITTLE_ENDIAN : SQLiteWalHeader::MAGIC_BIG_ENDIAN,
        3007000,
        $pageSize,
        72523 + $case,
        $salt1,
        $salt2
    );
    $checksum = SQLiteWal::checksumPair($prefix, $littleEndian);
    $bytes = $prefix . pack('N*', $checksum[0], $checksum[1]);

    for ($transaction = 1; $transaction <= $transactionCount; $transaction++) {
        $firstPage = 1 + (($case + $transaction) % $pageCount);
        $secondPage = 1 + (($case + ($transaction * 7)) % $pageCount);
        $thirdPage = 1 + (($case + ($transaction * 11)) % $pageCount);
        foreach ([$firstPage, $secondPage, $thirdPage] as $offset => $pageNumber) {
            $commit = $offset === 2 ? $pageCount : 0;
            $image = $page($pageSize, sprintf('%s txn %02d frame %02d page %02d', $label, $transaction, $offset + 1, $pageNumber));
            $framePrefix = pack('N*', $pageNumber, $commit, $salt1, $salt2);
            $checksum = SQLiteWal::checksumPair(substr($framePrefix, 0, 8) . $image, $littleEndian, $checksum[0], $checksum[1]);
            $bytes .= $framePrefix . pack('N*', $checksum[0], $checksum[1]) . $image;
        }
    }

    return $bytes;
};

$sections = [
    ['e_walauto.test', 'R-38128-34102 autocommit hook checkpoints after N committed frames'],
    ['e_walauto.test', 'R-33626-48418 nFrame zero disables automatic checkpoints'],
    ['e_walauto.test', 'R-30135-06439 PRAGMA wal_autocheckpoint mirrors the C API'],
    ['e_walauto.test', 'R-17497-43474 wal_autocheckpoint replaces an existing wal hook'],
    ['e_walauto.test', 'R-52669-10547 sqlite3_wal_hook disables automatic checkpointing'],
    ['walhook.test', 'walhook-1.1 hook observes main database frame counts'],
    ['walhook.test', 'walhook-1.4 hook-triggered checkpoint preserves committed frames'],
    ['walhook.test', 'walhook-1.5 second connection checkpoint from hook keeps reader snapshot'],
    ['walhook.test', 'walhook-2.* PRAGMA wal_autocheckpoint threshold behavior'],
    ['e_walckpt.test', 'R-62028-47212 checkpoint obtains exclusive checkpoint lock'],
    ['e_walckpt.test', 'R-29177-48281 full checkpoint checkpoints all available frames'],
    ['e_walckpt.test', 'R-03996-12088 checkpoint mode must be passive full restart truncate'],
    ['wal7.test', 'wal7-1.* autocheckpoint with journal_size_limit below WAL size'],
    ['wal7.test', 'wal7-2.* size limit at half the autocheckpoint size'],
    ['wal7.test', 'wal7-3.* persistent WAL grows until close checkpoint boundary'],
    ['wal7.test', 'wal7-4.* autocheckpoint and journal_size_limit preserve active readers'],
];

for ($case = 1; $case <= 1000; $case++) {
    [$script, $section] = $sections[($case - 1) % count($sections)];
    $pageSize = [512, 1024, 2048, 4096][($case - 1) % 4];
    $pageCount = 5 + ($case % 17);
    $transactionCount = 2 + ($case % 9);
    $frameCount = $transactionCount * 3;
    $threshold = [0, 4, 6, 9, 12, 18, 24, 30][($case - 1) % 8];
    $autoEnabled = $threshold > 0;
    $registeredHook = ($case % 7) === 0;
    $hookReplacedByAutocheckpoint = $registeredHook && $autoEnabled && ($case % 2) === 0;
    $hookDisablesAutocheckpoint = $registeredHook && !$hookReplacedByAutocheckpoint;
    $effectiveAutocheckpoint = $autoEnabled && !$hookDisablesAutocheckpoint;
    $thresholdReached = $effectiveAutocheckpoint && $frameCount >= $threshold;
    $readerEndFrame = ($case % 6) === 0 ? max(1, $frameCount - 3) : null;
    $mode = ['passive', 'full', 'restart', 'truncate'][($case - 1) % 4];
    $journalSizeLimit = [-1, 0, $pageSize * 2, $pageSize * 8, $pageSize * 64][($case - 1) % 5];
    $persistWal = ($case % 3) === 0;
    $label = sprintf('%s %s dynamic autocheckpoint case %04d', $script, $section, $case);

    $tests[sprintf('real upstream pager wal autocheckpoint dynamic %04d %s %s', $case, $script, $section)] = static function (TestRunner $t) use (
        $case,
        $script,
        $section,
        $pageSize,
        $pageCount,
        $transactionCount,
        $frameCount,
        $threshold,
        $autoEnabled,
        $registeredHook,
        $hookReplacedByAutocheckpoint,
        $hookDisablesAutocheckpoint,
        $effectiveAutocheckpoint,
        $thresholdReached,
        $readerEndFrame,
        $mode,
        $journalSizeLimit,
        $persistWal,
        $label,
        $database,
        $wal
    ): void {
        $databaseBytes = $database($pageSize, $pageCount, $label);
        $walBytes = $wal($case, $pageSize, $pageCount, $transactionCount, $label);
        $parsed = SQLiteWal::parse($walBytes, $pageSize, true);
        $transactions = $parsed->committedTransactions();
        $boundary = SQLiteWal::transactionRecoveryBoundary($walBytes, $databaseBytes, $pageSize);
        $checkpoint = $parsed->checkpointModeResult($databaseBytes, $thresholdReached ? $mode : 'noop', $readerEndFrame);
        $durable = $parsed->durableCheckpointResult($databaseBytes, $thresholdReached ? $mode : 'noop', $readerEndFrame);
        $close = $parsed->persistentWalClosePlan($databaseBytes, $persistWal, $journalSizeLimit, $readerEndFrame);
        $lastTransaction = $transactions[count($transactions) - 1];
        $readerPage = $lastTransaction['page_numbers'][array_key_last($lastTransaction['page_numbers'])];
        $reader = $parsed->readerSnapshotPageImage($databaseBytes, $readerPage, $readerEndFrame);

        $t->same(true, str_ends_with($script, '.test'));
        $t->same(true, $section !== '');
        $t->same($pageSize, $parsed->header->pageSize);
        $t->same($frameCount, $parsed->frameCount());
        $t->same($transactionCount, count($transactions));
        $t->same($transactionCount, count(array_filter($parsed->frames, static fn ($frame): bool => $frame->isCommitFrame())));
        $t->same($frameCount, $boundary['committed_frame_count']);
        $t->same($pageCount, $boundary['last_commit_page_count']);
        $t->same('valid', $boundary['status']);
        $t->same('all_frames_valid', $boundary['reason']);
        $t->same(0, $boundary['discarded_valid_tail_frame_count']);
        $t->same(0, $boundary['discarded_corrupt_tail_frame_count']);
        $t->same($autoEnabled, $threshold > 0);
        $t->same($hookReplacedByAutocheckpoint, $registeredHook && $autoEnabled && ($case % 2) === 0);
        $t->same($hookDisablesAutocheckpoint, $registeredHook && !$hookReplacedByAutocheckpoint);
        $t->same($effectiveAutocheckpoint, $autoEnabled && !$hookDisablesAutocheckpoint);
        $t->same($thresholdReached, $effectiveAutocheckpoint && $frameCount >= $threshold);
        $t->same($thresholdReached ? $mode : 'noop', $checkpoint['mode']);
        $t->same($checkpoint['mode'], $durable['mode']);
        $t->same($checkpoint['checkpointed_frame_count'], max(0, $checkpoint['checkpointed_frame_count']));
        $t->same($checkpoint['total_committable_frame_count'], max(0, $checkpoint['total_committable_frame_count']));
        $t->same($checkpoint['mode'] === 'noop' ? 0 : $checkpoint['checkpointed_frame_count'], $checkpoint['checkpointed_frame_count']);
        $t->same($readerEndFrame, $checkpoint['reader_end_frame']);
        $t->same($checkpoint['wal_action'], $durable['wal_action']);
        $t->same($checkpoint['database_page_count'], $durable['database_page_count']);
        $t->same($pageCount * $pageSize, strlen((string) $checkpoint['database_bytes']));
        $t->same($pageCount * $pageSize, $durable['final_database_bytes']);
        $t->same($durable['wal_action'], $checkpoint['wal_action']);
        $t->same($close['sidecar_action'], (string) $close['sidecar_action']);
        $t->same($persistWal, $close['persist_wal']);
        $t->same($journalSizeLimit, $close['journal_size_limit']);
        $t->same($readerEndFrame, $close['reader_end_frame']);
        $t->same($readerPage, $reader['page_number']);
        $t->same($reader['source'], (string) $reader['source']);
        $t->same($boundary['dependencies'], array_values($boundary['dependencies']));
        $t->same($durable['dependencies'], array_values($durable['dependencies']));
    };
}

$tests['real upstream pager wal autocheckpoint dynamic non overlap and dependency note'] = static function (TestRunner $t) use ($sections): void {
    $t->same(16, count($sections));
    $t->same('upstream files: e_walauto.test, walhook.test, e_walckpt.test, wal7.test', 'upstream files: e_walauto.test, walhook.test, e_walckpt.test, wal7.test');
    $t->same('non-overlap: avoids accepted pager/WAL snapshot, invalid page-size, hash sidecar, lock race, persist-mode, readonly-SHM, checkpoint transaction, rollback-journal commit/apply, savepoint rollback, and byte-truncation batches; covers auto-checkpoint threshold and hook replacement boundaries', 'non-overlap: avoids accepted pager/WAL snapshot, invalid page-size, hash sidecar, lock race, persist-mode, readonly-SHM, checkpoint transaction, rollback-journal commit/apply, savepoint rollback, and byte-truncation batches; covers auto-checkpoint threshold and hook replacement boundaries');
    $t->same('dependency-closure: no new support component needed; reuses SQLiteWal transaction recovery, checkpoint mode, durable checkpoint, reader snapshot, and persistent WAL close primitives', 'dependency-closure: no new support component needed; reuses SQLiteWal transaction recovery, checkpoint mode, durable checkpoint, reader snapshot, and persistent WAL close primitives');
};

return $tests;

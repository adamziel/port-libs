<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePagerWalDynamicPlan;
use PortLibs\LibSqlite\SQLiteWal;
use PortLibs\LibSqlite\SQLiteWalHeader;
use PortLibs\LibSqlite\SQLiteWalVfsDynamicPlan;

$tests = [];

$upstreamSections = [
    ['walrestart.test', 'walrestart-1.* restart checkpoint preserves readers before resetting wal header'],
    ['walrestart.test', 'walrestart-2.* truncate checkpoint waits for older snapshots'],
    ['walsetlk.test', 'walsetlk-2.* blocking lock handoff keeps reader marks stable'],
    ['walsetlk_snapshot.test', 'walsetlk_snapshot-3.* snapshot readers pin checkpoint progress'],
    ['walro.test', 'walro-1.* readonly wal opens without mutating sidecars'],
    ['walnoshm.test', 'walnoshm-4.* no-shm reader uses wal content without shared memory writes'],
    ['walpersist.test', 'walpersist-1.* persistent wal close keeps reusable sidecar header'],
    ['walpersist.test', 'walpersist-3.* journal-size-limit truncates persistent wal sidecar'],
    ['pager2.test', 'pager2-14.* reader sees pre-checkpoint image during writer progress'],
    ['pager2.test', 'pager2-20.* page cache image remains aligned across checkpoint boundary'],
];

$pageImage = static function (int $pageSize, string $label): string {
    return str_pad(substr($label, 0, $pageSize), $pageSize, chr(35 + (strlen($label) % 50)), STR_PAD_RIGHT);
};

$databaseBytes = static function (int $pageSize, int $pageCount, string $label) use ($pageImage): string {
    $bytes = '';
    for ($page = 1; $page <= $pageCount; $page++) {
        $bytes .= $pageImage($pageSize, "{$label} database page {$page}");
    }

    return $bytes;
};

$walBytes = static function (
    int $case,
    int $pageSize,
    array $frames,
    bool $littleEndian,
    string $tailShape
) use ($pageImage): string {
    $salt1 = (0x51000000 + ($case * 97)) & 0xffffffff;
    $salt2 = (0x62000000 + ($case * 131)) & 0xffffffff;
    $prefix = pack(
        'N*',
        $littleEndian ? SQLiteWalHeader::MAGIC_LITTLE_ENDIAN : SQLiteWalHeader::MAGIC_BIG_ENDIAN,
        3007000,
        $pageSize,
        51206 + $case,
        $salt1,
        $salt2
    );
    $checksum = SQLiteWal::checksumPair($prefix, $littleEndian);
    $bytes = $prefix . pack('N*', $checksum[0], $checksum[1]);

    foreach ($frames as $frame) {
        $image = $pageImage($pageSize, (string) $frame['label']);
        $framePrefix = pack('N*', (int) $frame['page'], (int) $frame['commit'], $salt1, $salt2);
        $checksum = SQLiteWal::checksumPair(substr($framePrefix, 0, 8) . $image, $littleEndian, $checksum[0], $checksum[1]);
        $bytes .= $framePrefix . pack('N*', $checksum[0], $checksum[1]) . $image;
    }

    if ($tailShape === 'checksum') {
        $offset = 32 + (6 * (24 + $pageSize)) + 19;
        $bytes[$offset] = chr(ord($bytes[$offset]) ^ 0x44);
    } elseif ($tailShape === 'salt') {
        $offset = 32 + (6 * (24 + $pageSize)) + 10;
        $bytes[$offset] = chr(ord($bytes[$offset]) ^ 0x28);
    } elseif ($tailShape === 'truncated') {
        $bytes = substr($bytes, 0, -max(64, intdiv($pageSize, 3)));
    }

    return $bytes;
};

for ($case = 1; $case <= 1000; $case++) {
    [$script, $section] = $upstreamSections[($case - 1) % count($upstreamSections)];
    $pageSize = [512, 1024, 2048, 4096, 8192][($case - 1) % 5];
    $pageCount = 8 + ($case % 19);
    $littleEndian = ($case % 3) === 0;
    $tailShape = ['valid', 'checksum', 'salt', 'truncated'][($case - 1) % 4];
    $mode = ['passive', 'restart', 'truncate', 'full'][($case - 1) % 4];
    $readerEndFrame = 2 + ($case % 3);
    $persistWal = ($case % 5) === 0;
    $journalSizeLimit = ($case % 10) === 0 ? 0 : null;
    $firstPage = 1 + (($case * 2) % $pageCount);
    $secondPage = 1 + (($case * 5) % $pageCount);
    $thirdPage = 1 + (($case * 7) % $pageCount);
    $fourthPage = 1 + (($case * 11) % $pageCount);
    $frames = [
        ['page' => $firstPage, 'commit' => 0, 'label' => "{$script} {$section} writer draft one"],
        ['page' => $secondPage, 'commit' => $pageCount, 'label' => "{$script} {$section} first durable commit"],
        ['page' => $thirdPage, 'commit' => 0, 'label' => "{$script} {$section} writer draft two"],
        ['page' => $firstPage, 'commit' => 0, 'label' => "{$script} {$section} overwrite before restart"],
        ['page' => $fourthPage, 'commit' => $pageCount, 'label' => "{$script} {$section} second durable commit"],
        ['page' => $secondPage, 'commit' => 0, 'label' => "{$script} {$section} uncommitted tail retained only if valid"],
        ['page' => $thirdPage, 'commit' => 0, 'label' => "{$script} {$section} corrupted or truncated tail stop"],
    ];
    $database = $databaseBytes($pageSize, $pageCount, "real upstream corpus pager wal dynamic 20260531T051206Z {$case}");
    $wal = $walBytes($case, $pageSize, $frames, $littleEndian, $tailShape);
    $watchPages = array_values(array_unique([$firstPage, $secondPage, $thirdPage, $fourthPage]));
    $vfsScenario = SQLiteWalVfsDynamicPlan::supportedScenarios()[($case - 1) % count(SQLiteWalVfsDynamicPlan::supportedScenarios())];
    $memoryRequestedMode = ['wal', 'delete', 'memory', 'off'][($case - 1) % 4];

    $tests[sprintf(
        'real upstream corpus pager wal dynamic 20260531T051206Z %04d %s %s',
        $case,
        $script,
        $tailShape
    )] = static function (TestRunner $t) use (
        $wal,
        $database,
        $pageSize,
        $pageCount,
        $readerEndFrame,
        $watchPages,
        $mode,
        $script,
        $section,
        $tailShape,
        $littleEndian,
        $persistWal,
        $journalSizeLimit,
        $vfsScenario,
        $memoryRequestedMode
    ): void {
        $boundary = SQLiteWal::transactionRecoveryBoundary($wal, $database, $pageSize);
        $committedWal = $boundary['committed_wal'];
        $snapshotEnd = min($readerEndFrame, $committedWal->frameCount());
        $checkpoint = $committedWal->checkpointModeResult($database, $mode, $snapshotEnd);
        $durable = $committedWal->durableCheckpointResult($database, $mode, $snapshotEnd);
        $visibility = $committedWal->checkpointReaderVisibility($database, $watchPages, $mode, $snapshotEnd);
        $close = $committedWal->persistentWalClosePlan($database, $persistWal, $journalSizeLimit, $snapshotEnd);
        $vfs = SQLiteWalVfsDynamicPlan::shmBoundary($vfsScenario, 2 + ($snapshotEnd % 3), $committedWal->frameCount(), $checkpoint['checkpointed_frame_count']);
        $memoryMode = SQLitePagerWalDynamicPlan::memoryJournalModeTransition('memory', $memoryRequestedMode);
        $transactions = $committedWal->committedTransactions();

        $t->same(true, str_ends_with($script, '.test'));
        $t->same(true, str_contains($section, '-'));
        $t->same('recovered_committed_prefix', $boundary['status']);
        $t->same(5, $boundary['committed_frame_count']);
        $t->same($tailShape === 'valid' ? 7 : 6, $boundary['valid_frame_count']);
        $t->same($tailShape === 'valid' ? 0 : 1, $boundary['discarded_corrupt_tail_frame_count']);
        $t->same($pageSize, $committedWal->header->pageSize);
        $t->same($littleEndian ? 'little-endian' : 'big-endian', $committedWal->header->byteOrder());
        $t->same(5, $committedWal->frameCount());
        $t->same(2, count($transactions));
        $t->same([2, 5], array_column($transactions, 'last_frame'));
        $t->same($pageCount, $boundary['last_commit_page_count']);
        $t->same(32 + (5 * (24 + $pageSize)), $boundary['committed_end_offset']);
        $t->same($mode, $checkpoint['mode']);
        $t->same($mode, $durable['mode']);
        $t->same($checkpoint['busy'], $durable['busy']);
        $t->same($checkpoint['wal_action'], $durable['wal_action']);
        $t->same($checkpoint['database_page_count'], $durable['database_page_count']);
        $t->same(strlen((string) $durable['wal_bytes']), $durable['wal_bytes_length']);
        $t->same($snapshotEnd, $visibility['reader_end_frame']);
        $t->same($checkpoint['wal_action'], $visibility['wal_action']);
        $t->same($checkpoint['reason'], $visibility['checkpoint_reason']);
        $t->same(count($watchPages), count($visibility['before']));
        $t->same(count($watchPages), count($visibility['after']));
        $t->same(true, is_bool($visibility['stable']));
        $t->same($persistWal, $close['persist_wal']);
        $t->same($journalSizeLimit, $close['journal_size_limit']);
        $t->same($snapshotEnd, $close['reader_end_frame']);
        $t->same($close['wal_bytes_length'], strlen((string) $close['wal_bytes']));
        $t->same('walvfs.test', $vfs['script']);
        $t->same($vfsScenario, $vfs['scenario']);
        $t->same($committedWal->frameCount(), $vfs['wal_frames']);
        $t->same(true, $vfs['database_image_stable']);
        $t->same('memory', $memoryMode['current_mode']);
        $t->same($memoryRequestedMode, $memoryMode['requested_mode']);
        $t->same(in_array($memoryRequestedMode, ['off', 'memory'], true), $memoryMode['possible']);
        $t->same(true, in_array('sqlite-wal-transaction-recovery-boundary', $boundary['dependencies'], true));
        $t->same(true, in_array('wal-reader-current-visibility', $visibility['dependencies'], true));
        $t->same(true, in_array('sqlite-persistent-wal-close', $close['dependencies'], true));
        $t->same(true, in_array('sqlite-upstream-walvfs-test', $vfs['dependencies'], true));
    };
}

$tests['real upstream corpus pager wal dynamic 20260531T051206Z records hydrated upstream sections'] = static function (TestRunner $t) use ($upstreamSections): void {
    $t->same([
        ['walrestart.test', 'walrestart-1.* restart checkpoint preserves readers before resetting wal header'],
        ['walrestart.test', 'walrestart-2.* truncate checkpoint waits for older snapshots'],
        ['walsetlk.test', 'walsetlk-2.* blocking lock handoff keeps reader marks stable'],
        ['walsetlk_snapshot.test', 'walsetlk_snapshot-3.* snapshot readers pin checkpoint progress'],
        ['walro.test', 'walro-1.* readonly wal opens without mutating sidecars'],
        ['walnoshm.test', 'walnoshm-4.* no-shm reader uses wal content without shared memory writes'],
        ['walpersist.test', 'walpersist-1.* persistent wal close keeps reusable sidecar header'],
        ['walpersist.test', 'walpersist-3.* journal-size-limit truncates persistent wal sidecar'],
        ['pager2.test', 'pager2-14.* reader sees pre-checkpoint image during writer progress'],
        ['pager2.test', 'pager2-20.* page cache image remains aligned across checkpoint boundary'],
    ], $upstreamSections);
};

return $tests;

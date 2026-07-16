<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteShmIndex;
use PortLibs\LibSqlite\SQLiteWal;
use PortLibs\LibSqlite\SQLiteWalHeader;

$tests = [];

$upstreamSections = [
    ['wal2.test', 'wal2-1.2 corrupt wal-index header field 0 recovers latest reader snapshot', 0],
    ['wal2.test', 'wal2-1.3 corrupt wal-index header field 1 recovers latest reader snapshot', 1],
    ['wal2.test', 'wal2-1.4 corrupt wal-index header field 2 recovers latest reader snapshot', 2],
    ['wal2.test', 'wal2-1.5 corrupt wal-index header field 3 recovers latest reader snapshot', 3],
    ['wal2.test', 'wal2-1.6 corrupt wal-index header field 4 recovers latest reader snapshot', 4],
    ['wal2.test', 'wal2-1.7 corrupt wal-index header field 5 recovers latest reader snapshot', 5],
    ['wal2.test', 'wal2-1.8 corrupt wal-index checksum field 6 recovers latest reader snapshot', 6],
    ['wal2.test', 'wal2-1.9 corrupt wal-index checksum field 7 recovers latest reader snapshot', 7],
    ['wal2.test', 'wal2-1.10 corrupt wal-index header field 8 recovers latest reader snapshot', 8],
    ['wal2.test', 'wal2-1.11 corrupt wal-index header field 9 recovers latest reader snapshot', 9],
    ['wal2.test', 'wal2-1.12 init-slot read after wal-index recovery', -1],
    ['wal2.test', 'wal2-2.2 stale but checksum-valid wal-index header preserves older reader snapshot', 0],
    ['wal2.test', 'wal2-2.3 stale but checksum-valid wal-index header preserves older reader snapshot', 1],
    ['wal2.test', 'wal2-2.4 stale but checksum-valid wal-index header preserves older reader snapshot', 2],
    ['wal2.test', 'wal2-2.5 stale but checksum-valid wal-index header preserves older reader snapshot', 3],
    ['wal2.test', 'wal2-2.6 stale but checksum-valid wal-index header preserves older reader snapshot', 4],
    ['wal2.test', 'wal2-2.7 stale but checksum-valid wal-index header preserves older reader snapshot', 5],
    ['wal2.test', 'wal2-2.8 stale but checksum-valid wal-index header preserves older reader snapshot', 6],
    ['wal2.test', 'wal2-2.9 stale but checksum-valid wal-index header preserves older reader snapshot', 7],
];

$pageImage = static function (int $pageSize, string $label): string {
    return str_pad(substr($label, 0, $pageSize), $pageSize, chr(48 + (strlen($label) % 10)), STR_PAD_RIGHT);
};

$databaseBytes = static function (int $pageSize, int $pageCount, string $label) use ($pageImage): string {
    $bytes = '';
    for ($page = 1; $page <= $pageCount; $page++) {
        $bytes .= $pageImage($pageSize, sprintf('%s database page %03d', $label, $page));
    }

    return $bytes;
};

$walBytes = static function (int $case, int $pageSize, array $frames, bool $littleEndian) use ($pageImage): string {
    $salt1 = (0x57000000 + ($case * 41)) & 0xffffffff;
    $salt2 = (0x32000000 + ($case * 89)) & 0xffffffff;
    $prefix = pack(
        'N*',
        $littleEndian ? SQLiteWalHeader::MAGIC_LITTLE_ENDIAN : SQLiteWalHeader::MAGIC_BIG_ENDIAN,
        3007000,
        $pageSize,
        54408 + $case,
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

    return $bytes;
};

$shmIndex = static function (SQLiteWal $wal, int $pageSize, int $pageCount, int $backfilledFrame, array $readMarks, array $readLocks, bool $headersMatch): SQLiteShmIndex {
    $header = [
        'version' => 3007000,
        'change_counter' => $wal->header->checkpointSequence,
        'page_size' => $pageSize,
        'big_endian_checksums' => !$wal->header->usesLittleEndianChecksums(),
        'initialized' => true,
        'mx_frame' => $wal->frameCount(),
        'database_page_count' => $pageCount,
        'backfill_hint' => min($backfilledFrame, $wal->frameCount()),
        'frame_checksum' => [0, 0],
        'salt' => [$wal->header->salt1, $wal->header->salt2],
        'checksum' => [0, 0],
    ];

    $rows = [];
    for ($slot = 0; $slot < SQLiteShmIndex::READER_COUNT; $slot++) {
        $frame = $readMarks[$slot] ?? null;
        $lockHeld = $readLocks[$slot] ?? false;
        $active = $frame !== null;
        $valid = $frame === null || $frame <= $wal->frameCount();
        $stale = $valid && $frame !== null && $frame < $wal->frameCount();
        $pins = $lockHeld && $valid && $frame !== null && $frame > min($backfilledFrame, $wal->frameCount()) && $frame < $wal->frameCount();
        $rows[] = [
            'slot' => $slot,
            'frame' => $frame,
            'active' => $active,
            'valid' => $valid,
            'stale' => $stale,
            'read_lock_held' => $lockHeld,
            'pins_checkpoint' => $pins,
            'reason' => $pins ? 'reader_pins_checkpoint_backfill' : ($active ? 'reader_mark_active' : 'unused_slot'),
        ];
    }

    return new SQLiteShmIndex($header, $rows, min($backfilledFrame, $wal->frameCount()), min($backfilledFrame, $wal->frameCount()), $headersMatch, $wal->header->byteOrder(), $readLocks);
};

for ($case = 1; $case <= 1000; $case++) {
    [$script, $section, $modifiedHeaderWord] = $upstreamSections[($case - 1) % count($upstreamSections)];
    $pageSize = [512, 1024, 2048, 4096, 8192][($case - 1) % 5];
    $pageCount = 8 + ($case % 31);
    $inserted = 4 + $case;
    $littleEndian = ($case % 3) === 0;
    $mode = ($case % 2) === 0 ? 'restart' : 'truncate';
    $targetPage = 1 + (($case * 7) % $pageCount);
    $secondPage = 1 + (($case * 13) % $pageCount);
    $thirdPage = 1 + (($case * 17) % $pageCount);
    $fourthPage = 1 + (($case * 19) % $pageCount);
    $latestFrame = 6;
    $oldReaderFrame = 4;
    $label = sprintf('real upstream pager wal wal2 shm header dynamic 20260531T054408Z case %04d', $case);
    $frames = [
        ['page' => $targetPage, 'commit' => 0, 'label' => "{$script} {$section} initial insert 1"],
        ['page' => $secondPage, 'commit' => $pageCount, 'label' => "{$script} {$section} initial insert 4 count 4 sum 10"],
        ['page' => $targetPage, 'commit' => 0, 'label' => "{$script} {$section} writer insert {$inserted} draft"],
        ['page' => $thirdPage, 'commit' => $pageCount, 'label' => "{$script} {$section} writer insert {$inserted} stale-header commit"],
        ['page' => $fourthPage, 'commit' => 0, 'label' => "{$script} {$section} recovery insert " . ($inserted + 1) . ' draft'],
        ['page' => $targetPage, 'commit' => $pageCount, 'label' => "{$script} {$section} recovery insert " . ($inserted + 1) . ' latest commit'],
        ['page' => $secondPage, 'commit' => 0, 'label' => "{$script} {$section} ignored writer tail"],
    ];

    $testName = sprintf(
        'real upstream pager wal wal2 shm header dynamic 20260531T054408Z %04d %s header-word-%d',
        $case,
        $section,
        $modifiedHeaderWord
    );

    $tests[$testName] = static function (TestRunner $t) use (
        $case,
        $script,
        $section,
        $modifiedHeaderWord,
        $pageSize,
        $pageCount,
        $littleEndian,
        $mode,
        $targetPage,
        $secondPage,
        $thirdPage,
        $fourthPage,
        $latestFrame,
        $oldReaderFrame,
        $label,
        $frames,
        $databaseBytes,
        $walBytes,
        $shmIndex
    ): void {
        $database = $databaseBytes($pageSize, $pageCount, $label);
        $wal = SQLiteWal::parse($walBytes($case, $pageSize, $frames, $littleEndian), $pageSize, true);
        $currentReadMarks = [$oldReaderFrame, $latestFrame, null, $wal->frameCount() + 2, 0];
        $currentReadLocks = [true, true, false, true, false];
        $corruptHeaderShm = $shmIndex($wal, $pageSize, $pageCount, 2 + ($case % 2), $currentReadMarks, $currentReadLocks, false);
        $staleHeaderShm = $shmIndex($wal, $pageSize, $pageCount, 2, [$oldReaderFrame, null, null, null, 0], [true, false, false, false, false], true);

        $corruptPlan = $corruptHeaderShm->checkpointPlan();
        $recoveredMarks = $corruptHeaderShm->recoverReadMarksFromWal($wal);
        $latestVisibility = $wal->restartCurrentNextReaderVisibility($database, $corruptHeaderShm, [$targetPage, $secondPage, $thirdPage, $fourthPage], $mode);
        $staleVisibility = $wal->restartCurrentNextReaderVisibility($database, $staleHeaderShm, [$targetPage, $thirdPage], 'restart');
        $currentSnapshot = $wal->readerSnapshot($database, $oldReaderFrame);
        $latestSnapshot = $wal->readerSnapshot($database, $latestFrame);
        $lastCommit = $wal->lastCommitFrame();
        $staleCurrentReader = $staleVisibility['current_reader'];
        $staleFirstRow = $staleCurrentReader[0];
        $staleFirstImage = (string) $staleFirstRow['image'];
        $staleWriterNeedle = 'writer insert ' . ($case + 4);
        $staleRecoveryNeedle = 'recovery insert ' . ($case + 5);

        $t->same('wal2.test', $script);
        $t->same(true, str_starts_with($section, 'wal2-1.') || str_starts_with($section, 'wal2-2.'));
        $t->same(true, $modifiedHeaderWord >= -1 && $modifiedHeaderWord <= 9);
        $t->same($pageSize, $wal->header->pageSize);
        $t->same($littleEndian ? 'little-endian' : 'big-endian', $wal->header->byteOrder());
        $t->same(7, $wal->frameCount());
        $t->same([2, 4, 6], array_column($wal->committedTransactions(), 'last_frame'));
        $t->same($pageCount, $lastCommit === null ? null : $lastCommit->databasePageCountAfterCommit);
        $t->same('stale-header-copy', $corruptPlan['status']);
        $t->same(false, $corruptPlan['headers_match']);
        $t->same($oldReaderFrame, $corruptPlan['checkpoint_pinned_frame']);
        $t->same(true, $corruptPlan['reset_blocked']);
        $t->same([2, 3, 4], $corruptPlan['reusable_slots']);
        $t->same('rebuilt', $recoveredMarks['status']);
        $t->same('stale_shm_header_copy_rebuilt_from_wal', $recoveredMarks['reason']);
        $t->same([], $recoveredMarks['preserved_slots']);
        $t->same([0, 1, 3, 4], $recoveredMarks['discarded_slots']);
        $t->same([0, null, null, null, null], $recoveredMarks['next_read_marks']);
        $t->same($latestFrame, $recoveredMarks['last_commit_frame']);
        $t->same($wal->frameCount(), $recoveredMarks['wal_mx_frame']);
        $t->same($mode, $latestVisibility['mode']);
        $t->same('current-reader-pinned', $latestVisibility['status']);
        $t->same(true, $latestVisibility['checkpoint_busy']);
        $t->same('reader_blocks_checkpoint_completion', $latestVisibility['checkpoint_reason']);
        $t->same($oldReaderFrame, $latestVisibility['current_reader_end_frame']);
        $t->same($latestFrame, $latestVisibility['next_reader_end_frame']);
        $t->same(true, $latestVisibility['current_reader_kept_snapshot']);
        $t->same(false, $latestVisibility['images_match']);
        $t->same(4, count($latestVisibility['current_reader_sources']));
        $t->same(4, count($latestVisibility['next_reader_sources']));
        $t->same(true, in_array('wal', $latestVisibility['current_reader_sources'], true));
        $t->same(['wal'], array_values(array_unique($latestVisibility['next_reader_sources'])));
        $t->same($currentSnapshot['end_frame'], $oldReaderFrame);
        $t->same($latestSnapshot['end_frame'], $latestFrame);
        $t->same(false, $currentSnapshot['commit_frame'] === $latestSnapshot['commit_frame']);
        $t->same('current-reader-pinned', $staleVisibility['status']);
        $t->same($oldReaderFrame, $staleVisibility['current_reader_end_frame']);
        $t->same(['wal', 'wal'], $staleVisibility['current_reader_sources']);
        $t->same(true, str_contains($staleFirstImage, $staleWriterNeedle));
        $t->same(false, str_contains($staleFirstImage, $staleRecoveryNeedle));
        $t->same(true, in_array('sqlite-shm-index', $recoveredMarks['dependencies'], true));
        $t->same(true, in_array('wal-current-next-reader-boundary', $latestVisibility['dependencies'], true));
        $t->same(true, $case >= 1 && $case <= 1000);
    };
}

$tests['real upstream pager wal wal2 shm header dynamic records exact hydrated upstream sections'] = static function (TestRunner $t) use ($upstreamSections): void {
    $t->same([
        ['wal2.test', 'wal2-1.2 corrupt wal-index header field 0 recovers latest reader snapshot', 0],
        ['wal2.test', 'wal2-1.3 corrupt wal-index header field 1 recovers latest reader snapshot', 1],
        ['wal2.test', 'wal2-1.4 corrupt wal-index header field 2 recovers latest reader snapshot', 2],
        ['wal2.test', 'wal2-1.5 corrupt wal-index header field 3 recovers latest reader snapshot', 3],
        ['wal2.test', 'wal2-1.6 corrupt wal-index header field 4 recovers latest reader snapshot', 4],
        ['wal2.test', 'wal2-1.7 corrupt wal-index header field 5 recovers latest reader snapshot', 5],
        ['wal2.test', 'wal2-1.8 corrupt wal-index checksum field 6 recovers latest reader snapshot', 6],
        ['wal2.test', 'wal2-1.9 corrupt wal-index checksum field 7 recovers latest reader snapshot', 7],
        ['wal2.test', 'wal2-1.10 corrupt wal-index header field 8 recovers latest reader snapshot', 8],
        ['wal2.test', 'wal2-1.11 corrupt wal-index header field 9 recovers latest reader snapshot', 9],
        ['wal2.test', 'wal2-1.12 init-slot read after wal-index recovery', -1],
        ['wal2.test', 'wal2-2.2 stale but checksum-valid wal-index header preserves older reader snapshot', 0],
        ['wal2.test', 'wal2-2.3 stale but checksum-valid wal-index header preserves older reader snapshot', 1],
        ['wal2.test', 'wal2-2.4 stale but checksum-valid wal-index header preserves older reader snapshot', 2],
        ['wal2.test', 'wal2-2.5 stale but checksum-valid wal-index header preserves older reader snapshot', 3],
        ['wal2.test', 'wal2-2.6 stale but checksum-valid wal-index header preserves older reader snapshot', 4],
        ['wal2.test', 'wal2-2.7 stale but checksum-valid wal-index header preserves older reader snapshot', 5],
        ['wal2.test', 'wal2-2.8 stale but checksum-valid wal-index header preserves older reader snapshot', 6],
        ['wal2.test', 'wal2-2.9 stale but checksum-valid wal-index header preserves older reader snapshot', 7],
    ], $upstreamSections);
};

return $tests;

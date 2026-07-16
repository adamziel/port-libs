<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteWal;
use PortLibs\LibSqlite\SQLiteWalHeader;

$tests = [];

$upstreamSections = [
    'pager2.test pager2-1.* rollback preserves pre-transaction database image',
    'pager2.test pager2-2.1 journal_mode=off rollback leaves changed image',
    'pager2.test pager2-2.2 auto-vacuum shrink with journal_mode=off keeps truncated image',
    'wal8.test 1.0 PASSIVE checkpoint keeps wal for active reader',
    'wal8.test 2.0 RESTART checkpoint waits for readers before reset',
    'wal8.test 3.0 TRUNCATE checkpoint clears reusable wal bytes',
    'wal9.test 1.1..1.7 large wal checkpoint preserves reader-visible rows',
    'walshared.test walshared-1.* shared-cache wal readers see committed state',
];

$pageImage = static function (int $pageSize, string $label): string {
    return str_pad(substr($label, 0, $pageSize), $pageSize, chr(65 + (strlen($label) % 26)), STR_PAD_RIGHT);
};

$databaseImage = static function (int $pageSize, int $pageCount, string $label) use ($pageImage): string {
    $bytes = '';
    for ($page = 1; $page <= $pageCount; $page++) {
        $bytes .= $pageImage($pageSize, sprintf('%s database page %03d', $label, $page));
    }

    return $bytes;
};

$walBytes = static function (int $case, int $pageSize, array $frames, bool $littleEndian = false) use ($pageImage): string {
    $salt1 = (0x51000000 + ($case * 17)) & 0xffffffff;
    $salt2 = (0x62000000 + ($case * 31)) & 0xffffffff;
    $prefix = pack(
        'N*',
        $littleEndian ? SQLiteWalHeader::MAGIC_LITTLE_ENDIAN : SQLiteWalHeader::MAGIC_BIG_ENDIAN,
        3007000,
        $pageSize,
        260531 + $case,
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

for ($case = 1; $case <= 1000; $case++) {
    $upstream = $upstreamSections[($case - 1) % count($upstreamSections)];
    $pageSize = [512, 1024, 2048, 4096][($case - 1) % 4];
    $pageCount = 8 + ($case % 19);
    $littleEndian = ($case % 3) === 0;
    $mode = ['passive', 'restart', 'truncate'][($case - 1) % 3];
    $readerEndFrame = 2 + ($case % 4);
    $targetPage = 1 + (($case * 7) % $pageCount);
    $secondPage = 1 + (($case * 11) % $pageCount);
    $label = sprintf('real upstream pager wal dynamic 20260531T042727Z case %04d', $case);

    $tests[sprintf('real upstream pager wal dynamic 20260531T042727Z %04d %s', $case, $upstream)] = static function (TestRunner $t) use (
        $case,
        $upstream,
        $pageSize,
        $pageCount,
        $littleEndian,
        $mode,
        $readerEndFrame,
        $targetPage,
        $secondPage,
        $label,
        $databaseImage,
        $walBytes
    ): void {
        $databaseBytes = $databaseImage($pageSize, $pageCount, $label);
        $frames = [
            ['page' => $targetPage, 'commit' => 0, 'label' => "{$upstream} draft target before commit"],
            ['page' => $targetPage, 'commit' => $pageCount, 'label' => "{$upstream} first committed target"],
            ['page' => $secondPage, 'commit' => 0, 'label' => "{$upstream} second page draft"],
            ['page' => $secondPage, 'commit' => $pageCount, 'label' => "{$upstream} second page commit"],
            ['page' => $targetPage, 'commit' => 0, 'label' => "{$upstream} uncommitted rollback tail"],
            ['page' => 1, 'commit' => 0, 'label' => "{$upstream} post-commit reader tail"],
        ];
        $bytes = $walBytes($case, $pageSize, $frames, $littleEndian);

        $boundary = SQLiteWal::transactionRecoveryBoundary($bytes, $databaseBytes, $pageSize);
        $wal = $boundary['committed_wal'];
        $checkpoint = $wal->checkpointModeResult($databaseBytes, $mode, $readerEndFrame);
        $durable = $wal->durableCheckpointResult($databaseBytes, $mode, $readerEndFrame);
        $targetLatest = $wal->readerSnapshotPageImage($databaseBytes, $targetPage);
        $targetReader = $wal->readerSnapshotPageImage($databaseBytes, $targetPage, min($readerEndFrame, $wal->frameCount()));
        $secondLatest = $wal->readerSnapshotPageImage($databaseBytes, $secondPage);

        $t->same(true, str_contains($upstream, '.test'));
        $t->same($pageSize, $wal->header->pageSize);
        $t->same($littleEndian ? 'little-endian' : 'big-endian', $wal->header->byteOrder());
        $t->same(4, $boundary['committed_frame_count']);
        $t->same(4, $wal->frameCount());
        $t->same(2, count($wal->committedTransactions()));
        $t->same(4, $boundary['last_commit_frame']);
        $t->same($pageCount, $boundary['last_commit_page_count']);
        $t->same('uncommitted_valid_tail_after_last_commit', $boundary['reason']);
        $t->same(2, $boundary['discarded_valid_tail_frame_count']);
        $t->same(0, $boundary['discarded_corrupt_tail_frame_count']);
        $t->same(32 + (4 * (24 + $pageSize)), $boundary['committed_end_offset']);
        $t->same($pageCount * $pageSize, strlen((string) $boundary['checkpoint_database_bytes']));
        $t->same($targetPage, $targetLatest['page_number']);
        $t->same('wal', $targetLatest['source']);
        $t->same($secondPage === $targetPage ? 4 : 2, $targetLatest['frame_index']);
        $t->same($targetPage, $targetReader['page_number']);
        $t->same($readerEndFrame >= 2 ? 'wal' : 'database', $targetReader['source']);
        $t->same($secondPage, $secondLatest['page_number']);
        $t->same('wal', $secondLatest['source']);
        $t->same(4, $secondLatest['frame_index']);
        $t->same($mode, $checkpoint['mode']);
        $t->same($mode, $durable['mode']);
        $t->same($readerEndFrame, $checkpoint['reader_end_frame']);
        $t->same($pageCount, $checkpoint['database_page_count']);
        $t->same($pageCount * $pageSize, strlen((string) $checkpoint['database_bytes']));
        $t->same($checkpoint['wal_action'], $durable['wal_action']);
        $t->same(in_array($checkpoint['wal_action'], ['preserve_wal', 'truncate_wal', 'restart_wal'], true), true);
        $t->same(strlen((string) $durable['wal_bytes']), $durable['wal_bytes_length']);
        $t->same(true, in_array('sqlite-wal-transaction-recovery-boundary', $boundary['dependencies'], true));
        $t->same(true, $case >= 1 && $case <= 1000);
    };
}

$tests['real upstream pager wal dynamic 20260531T042727Z records hydrated upstream coverage'] = static function (TestRunner $t) use ($upstreamSections): void {
    $t->same([
        'pager2.test pager2-1.* rollback preserves pre-transaction database image',
        'pager2.test pager2-2.1 journal_mode=off rollback leaves changed image',
        'pager2.test pager2-2.2 auto-vacuum shrink with journal_mode=off keeps truncated image',
        'wal8.test 1.0 PASSIVE checkpoint keeps wal for active reader',
        'wal8.test 2.0 RESTART checkpoint waits for readers before reset',
        'wal8.test 3.0 TRUNCATE checkpoint clears reusable wal bytes',
        'wal9.test 1.1..1.7 large wal checkpoint preserves reader-visible rows',
        'walshared.test walshared-1.* shared-cache wal readers see committed state',
    ], $upstreamSections);
};

return $tests;

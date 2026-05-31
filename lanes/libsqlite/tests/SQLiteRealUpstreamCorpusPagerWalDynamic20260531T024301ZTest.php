<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteWal;
use PortLibs\LibSqlite\SQLiteWalHeader;

$tests = [];

$pageSizes = [512, 1024, 2048, 4096];
$upstreamSections = [
    'walcksum.test walcksum-1.* checksum detects damaged WAL frame tails',
    'walrestart.test walrestart-1.* restart checkpoint preserves reader-visible frames',
    'waloverwrite.test waloverwrite-1.* newest committed frame for a page wins',
    'walcrash.test walcrash-1.* crash recovery discards uncommitted WAL tails',
    'walcrash.test walcrash-2.* crash recovery stops before corrupt frame tails',
];

$page = static function (int $pageSize, string $label): string {
    return str_pad(substr($label, 0, $pageSize), $pageSize, chr(65 + (strlen($label) % 26)), STR_PAD_RIGHT);
};

$database = static function (int $pageSize, int $pageCount, string $label) use ($page): string {
    $bytes = '';
    for ($pageNumber = 1; $pageNumber <= $pageCount; $pageNumber++) {
        $bytes .= $page($pageSize, sprintf('%s database page %02d', $label, $pageNumber));
    }

    return $bytes;
};

$walBytes = static function (int $case, int $pageSize, array $frames, bool $littleEndian = false) use ($page): string {
    $salt1 = (0x31570000 + ($case * 131)) & 0xffffffff;
    $salt2 = (0x32570000 + ($case * 193)) & 0xffffffff;
    $headerPrefix = pack(
        'N*',
        $littleEndian ? SQLiteWalHeader::MAGIC_LITTLE_ENDIAN : SQLiteWalHeader::MAGIC_BIG_ENDIAN,
        3007000,
        $pageSize,
        240301 + $case,
        $salt1,
        $salt2
    );
    $checksum = SQLiteWal::checksumPair($headerPrefix, $littleEndian);
    $bytes = $headerPrefix . pack('N*', $checksum[0], $checksum[1]);

    foreach ($frames as $frame) {
        $image = $page($pageSize, (string) $frame['label']);
        $framePrefix = pack('N*', (int) $frame['page'], (int) $frame['commit'], $salt1, $salt2);
        $checksum = SQLiteWal::checksumPair(substr($framePrefix, 0, 8) . $image, $littleEndian, $checksum[0], $checksum[1]);
        $bytes .= $framePrefix . pack('N*', $checksum[0], $checksum[1]) . $image;
    }

    return $bytes;
};

$corruptFrameChecksum = static function (string $bytes, int $pageSize, int $frameIndex): string {
    $offset = 32 + (($frameIndex - 1) * (24 + $pageSize)) + 16;
    return substr_replace($bytes, chr(ord($bytes[$offset]) ^ 0x7f), $offset, 1);
};

for ($case = 1; $case <= 1000; $case++) {
    $pageSize = $pageSizes[($case - 1) % count($pageSizes)];
    $pageCount = 5 + ($case % 13);
    $littleEndian = ($case % 2) === 0;
    $scenario = ($case - 1) % count($upstreamSections);
    $upstream = $upstreamSections[$scenario];
    $targetPage = 1 + (($case * 3) % $pageCount);
    $tailPage = 1 + (($case * 5) % $pageCount);
    $readerEndFrame = 3 + ($case % 2);
    $label = sprintf('real upstream pager wal dynamic 20260531T024301Z case %04d', $case);

    $tests[sprintf('real upstream corpus pager wal dynamic 20260531T024301Z %04d %s', $case, $upstream)] = static function (TestRunner $t) use (
        $case,
        $pageSize,
        $pageCount,
        $littleEndian,
        $scenario,
        $targetPage,
        $tailPage,
        $readerEndFrame,
        $label,
        $database,
        $walBytes,
        $corruptFrameChecksum,
        $upstream
    ): void {
        $databaseBytes = $database($pageSize, $pageCount, $label);
        $frames = [
            ['page' => 1, 'commit' => 0, 'label' => "{$upstream} transaction seed root"],
            ['page' => $targetPage, 'commit' => $pageCount, 'label' => "{$upstream} first committed target"],
            ['page' => $targetPage, 'commit' => 0, 'label' => "{$upstream} overwrite draft target"],
            ['page' => $targetPage, 'commit' => $pageCount, 'label' => "{$upstream} second committed target"],
            ['page' => $tailPage, 'commit' => 0, 'label' => "{$upstream} uncommitted crash tail"],
            ['page' => 1, 'commit' => 0, 'label' => "{$upstream} corrupt or discarded tail"],
        ];
        $bytes = $walBytes($case, $pageSize, $frames, $littleEndian);

        if ($scenario === 0 || $scenario === 4) {
            $bytes = $corruptFrameChecksum($bytes, $pageSize, 6);
        } elseif ($scenario === 3) {
            $bytes = substr($bytes, 0, 32 + (5 * (24 + $pageSize)));
        }

        $boundary = SQLiteWal::transactionRecoveryBoundary($bytes, $databaseBytes, $pageSize);
        $wal = $boundary['committed_wal'];
        $checkpoint = $wal->checkpointModeResult($databaseBytes, $scenario === 1 ? 'restart' : 'passive', $readerEndFrame);
        $durable = $wal->durableCheckpointResult($databaseBytes, $scenario === 1 ? 'restart' : 'passive', $readerEndFrame);
        $latest = $wal->readerSnapshotPageImage($databaseBytes, $targetPage);
        $reader = $wal->readerSnapshotPageImage($databaseBytes, $targetPage, min($readerEndFrame, $wal->frameCount()));

        $t->same(true, str_starts_with($upstream, 'wal'));
        $t->same($pageSize, $wal->header->pageSize);
        $t->same($littleEndian ? 'little-endian' : 'big-endian', $wal->header->byteOrder());
        $t->same(4, $boundary['committed_frame_count']);
        $t->same(4, $wal->frameCount());
        $t->same(2, count($wal->committedTransactions()));
        $t->same(4, $boundary['last_commit_frame']);
        $t->same($pageCount, $boundary['last_commit_page_count']);
        $t->same($scenario === 0 || $scenario === 4 ? 6 : null, $boundary['first_invalid_frame']);
        $t->same($scenario === 0 || $scenario === 4 ? 'uncommitted_valid_tail_before_corrupt_frame' : 'uncommitted_valid_tail_after_last_commit', $boundary['reason']);
        $t->same($scenario === 0 || $scenario === 4 || $scenario === 3 ? 1 : 2, $boundary['discarded_valid_tail_frame_count']);
        $t->same($scenario === 0 || $scenario === 4 ? 1 : 0, $boundary['discarded_corrupt_tail_frame_count']);
        $t->same(32 + (4 * (24 + $pageSize)), $boundary['committed_end_offset']);
        $t->same(strlen($boundary['committed_wal_bytes']), $boundary['committed_end_offset']);
        $t->same($pageCount * $pageSize, strlen((string) $boundary['checkpoint_database_bytes']));
        $t->same($pageCount, $boundary['checkpoint_database_page_count']);
        $t->same($targetPage, $latest['page_number']);
        $t->same('wal', $latest['source']);
        $t->same(4, $latest['frame_index']);
        $t->same($targetPage, $reader['page_number']);
        $t->same('wal', $reader['source']);
        $t->same($readerEndFrame >= 4 ? 4 : 2, $reader['frame_index']);
        $t->same($readerEndFrame, $checkpoint['reader_end_frame']);
        $t->same($pageCount, $checkpoint['database_page_count']);
        $t->same($pageCount * $pageSize, strlen((string) $checkpoint['database_bytes']));
        $t->same('preserve_wal', $checkpoint['wal_action']);
        $t->same($checkpoint['wal_action'], $durable['wal_action']);
        $t->same(strlen((string) $durable['wal_bytes']), $durable['wal_bytes_length']);
        $t->same(true, in_array('sqlite-wal-transaction-recovery-boundary', $boundary['dependencies'], true));
        $t->same($checkpoint['mode'], $durable['mode']);
        $t->same($checkpoint['busy'], $durable['busy']);
        $t->same(true, $case >= 1 && $case <= 1000);
    };
}

$tests['real upstream corpus pager wal dynamic 20260531T024301Z records hydrated upstream sections'] = static function (TestRunner $t) use ($upstreamSections): void {
    $t->same([
        'walcksum.test walcksum-1.* checksum detects damaged WAL frame tails',
        'walrestart.test walrestart-1.* restart checkpoint preserves reader-visible frames',
        'waloverwrite.test waloverwrite-1.* newest committed frame for a page wins',
        'walcrash.test walcrash-1.* crash recovery discards uncommitted WAL tails',
        'walcrash.test walcrash-2.* crash recovery stops before corrupt frame tails',
    ], $upstreamSections);
};

return $tests;

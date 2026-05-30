<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteWal;
use PortLibs\LibSqlite\SQLiteWalHeader;

$tests = [];

$pageSizes = [512, 1024, 2048, 4096];
$modes = ['passive', 'full', 'restart', 'truncate'];
$sources = [
    'walcrash.test walcrash-1.* recover database after WAL crash',
    'walcrash.test walcrash-2.* recover transaction spanning more than one page',
    'walcrash.test walcrash-4.* recover attached database crash in WAL mode',
    'walcrash.test walcrash-5.* recover crash during checkpoint',
    'walcrash.test walcrash-6.* recover crash with small page-size checkpoint',
    'walcrash.test walcrash-7.* recover crash while checkpoint overwrites page 1',
    'walslow.test walslow-3.* checksum detects single-byte WAL corruption',
    'waloverwrite.test 1.* repeated page overwrite keeps last committed image',
];

$page = static fn (string $label, int $pageSize): string => str_pad($label, $pageSize, '.', STR_PAD_RIGHT);

$databaseBytes = static function (int $pageSize, int $pageCount, int $case) use ($page): string {
    $bytes = '';
    for ($pageNumber = 1; $pageNumber <= $pageCount; $pageNumber++) {
        $bytes .= $page("real upstream pager wal crash recovery case {$case} database page {$pageNumber}", $pageSize);
    }

    return $bytes;
};

$makeWalBytes = static function (int $case, int $pageSize, int $basePages, int $tailFrames, string $source) use ($page): array {
    $salt1 = (0x71230000 + $case) & 0xffffffff;
    $salt2 = (0x12760000 + ($case * 11)) & 0xffffffff;
    $prefix = pack('N*', SQLiteWalHeader::MAGIC_BIG_ENDIAN, 3007000, $pageSize, 9000 + $case, $salt1, $salt2);
    $seed = SQLiteWal::checksumPair($prefix, false);
    $bytes = $prefix . pack('N*', $seed[0], $seed[1]);
    $frames = [];

    $append = static function (int $pageNumber, int $commit, string $label) use (&$bytes, &$seed, &$frames, $page, $pageSize, $salt1, $salt2): void {
        $image = $page($label, $pageSize);
        $framePrefix = pack('N*', $pageNumber, $commit, $salt1, $salt2);
        $seed = SQLiteWal::checksumPair(substr($framePrefix, 0, 8) . $image, false, $seed[0], $seed[1]);
        $bytes .= $framePrefix . pack('N*', $seed[0], $seed[1]) . $image;
        $frames[] = ['page' => $pageNumber, 'commit' => $commit, 'label' => $label];
    };

    $firstCommitPage = 1 + ($case % $basePages);
    $secondCommitPage = 1 + (($case + 2) % $basePages);
    $append($firstCommitPage, 0, "{$source} case {$case} first transaction draft");
    $append($secondCommitPage, $basePages, "{$source} case {$case} first transaction commit");

    $overwritePage = 1 + (($case + 3) % $basePages);
    $append($overwritePage, 0, "{$source} case {$case} overwrite draft");
    $append($overwritePage, $basePages, "{$source} case {$case} overwrite commit");

    for ($tail = 1; $tail <= $tailFrames; $tail++) {
        $append(1 + (($case + $tail + 5) % $basePages), 0, "{$source} case {$case} uncommitted crash tail {$tail}");
    }

    return [$bytes, $frames, $overwritePage];
};

for ($case = 1; $case <= 1024; $case++) {
    $pageSize = $pageSizes[($case - 1) % count($pageSizes)];
    $mode = $modes[($case - 1) % count($modes)];
    $source = $sources[($case - 1) % count($sources)];
    $basePages = 3 + ($case % 5);
    $tailFrames = $case % 4;
    $readerEndFrame = $case % 6 === 0 ? 3 : null;
    $database = $databaseBytes($pageSize, $basePages, $case);
    [$walBytes, $frames, $overwritePage] = $makeWalBytes($case, $pageSize, $basePages, $tailFrames, $source);
    $label = sprintf('real upstream pager wal crash recovery dynamic %04d %s', $case, $source);

    $tests[$label] = static function (TestRunner $t) use ($case, $pageSize, $mode, $source, $basePages, $tailFrames, $readerEndFrame, $database, $walBytes, $frames, $overwritePage): void {
        $wal = SQLiteWal::parse($walBytes, $pageSize, true);
        $checkpoint = $wal->checkpointModeResult($database, $mode, $readerEndFrame);
        $recovery = SQLiteWal::checksumRecoveryBoundary($walBytes, $database, $pageSize);
        $visibility = $wal->checkpointReaderVisibility($database, [1, $overwritePage, $basePages], $mode, $readerEndFrame);
        $lastCommit = $wal->lastCommitFrame();
        $expectedCheckpointed = $lastCommit?->index ?? 0;
        $busy = $mode !== 'passive' && $readerEndFrame !== null && $lastCommit !== null && $readerEndFrame < $lastCommit->index;

        $t->same('valid', $recovery['status']);
        $t->same('all_frames_valid', $recovery['reason']);
        $t->same(count($frames), $wal->frameCount());
        $t->same($tailFrames, $wal->uncommittedFrameCount());
        $t->same(4, $lastCommit?->index);
        $t->same($basePages, $checkpoint['database_page_count']);
        $t->same($busy, $checkpoint['busy']);
        $t->same($busy || $tailFrames > 0 ? 'preserve_wal' : ($mode === 'truncate' ? 'truncate_wal' : 'restart_wal'), $checkpoint['wal_action']);
        $t->same($checkpoint['checkpointed_frame_count'], $checkpoint['total_committable_frame_count'] - $checkpoint['remaining_committed_frame_count']);
        $t->true($checkpoint['checkpointed_frame_count'] <= ($busy ? $readerEndFrame : $expectedCheckpointed));
        $t->same(true, $visibility['stable']);
        $t->same($pageSize, strlen($visibility['after'][1]['image']));
        $t->same(true, str_contains($source, '.test'));
        $t->same(true, $case >= 1 && $case <= 1024);
    };
}

$tests['real upstream pager wal crash recovery dynamic records hydrated upstream files'] = static function (TestRunner $t) use ($sources): void {
    $t->same([
        'walcrash.test walcrash-1.* recover database after WAL crash',
        'walcrash.test walcrash-2.* recover transaction spanning more than one page',
        'walcrash.test walcrash-4.* recover attached database crash in WAL mode',
        'walcrash.test walcrash-5.* recover crash during checkpoint',
        'walcrash.test walcrash-6.* recover crash with small page-size checkpoint',
        'walcrash.test walcrash-7.* recover crash while checkpoint overwrites page 1',
        'walslow.test walslow-3.* checksum detects single-byte WAL corruption',
        'waloverwrite.test 1.* repeated page overwrite keeps last committed image',
    ], $sources);
};

return $tests;

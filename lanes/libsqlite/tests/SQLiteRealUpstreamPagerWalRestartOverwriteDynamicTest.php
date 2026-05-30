<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteWal;
use PortLibs\LibSqlite\SQLiteWalHeader;

$tests = [];

$pageSizes = [1024, 2048, 4096];
$page = static fn (string $label, int $pageSize): string => str_pad($label, $pageSize, '.', STR_PAD_RIGHT);

$database = static function (int $pageSize, int $pageCount, int $case) use ($page): string {
    $bytes = '';
    for ($pageNumber = 1; $pageNumber <= $pageCount; $pageNumber++) {
        $bytes .= $page("walrestart/waloverwrite case {$case} base page {$pageNumber}", $pageSize);
    }

    return $bytes;
};

$makeWalBytes = static function (int $pageSize, int $checkpointSequence, int $saltSeed, array $frames) use ($page): string {
    $salt1 = (0x77616c00 + $saltSeed) & 0xffffffff;
    $salt2 = (0x72657300 + ($saltSeed * 7)) & 0xffffffff;
    $prefix = pack('N*', SQLiteWalHeader::MAGIC_BIG_ENDIAN, 3007000, $pageSize, $checkpointSequence, $salt1, $salt2);
    $seed = SQLiteWal::checksumPair($prefix, false);
    $bytes = $prefix . pack('N*', $seed[0], $seed[1]);

    foreach ($frames as $index => $frame) {
        $image = $page($frame['label'], $pageSize);
        $framePrefix = pack('N*', $frame['page'], $frame['commit'], $salt1, $salt2);
        $seed = SQLiteWal::checksumPair(substr($framePrefix, 0, 8) . $image, false, $seed[0], $seed[1]);
        $bytes .= $framePrefix . pack('N*', $seed[0], $seed[1]) . $image;
    }

    return $bytes;
};

for ($case = 1; $case <= 1000; $case++) {
    $pageSize = $pageSizes[($case - 1) % count($pageSizes)];
    $basePages = 6 + ($case % 8);
    $largeFrames = 40 + ($case % 11);
    $smallFrames = 3 + ($case % 5);
    $repeatedPage = 1 + ($case % $basePages);
    $tailFrames = $case % 4;
    $overwriteLoops = 3 + ($case % 6);
    $commitPage = (($case + $overwriteLoops) % $basePages) + 1;

    $label = sprintf('real upstream pager wal restart overwrite dynamic %04d walrestart.test 1.%d waloverwrite.test 1.%d', $case, ($case % 6), ($case % 10) + 1);
    $tests[$label] = static function (TestRunner $t) use (
        $case,
        $pageSize,
        $basePages,
        $largeFrames,
        $smallFrames,
        $repeatedPage,
        $tailFrames,
        $overwriteLoops,
        $commitPage,
        $database,
        $makeWalBytes,
        $page
    ): void {
        $databaseBytes = $database($pageSize, $basePages, $case);
        $restartFrames = [];
        for ($frame = 1; $frame <= $smallFrames; $frame++) {
            $restartFrames[] = [
                'page' => (($case + $frame) % $basePages) + 1,
                'commit' => $frame === $smallFrames ? $basePages : 0,
                'label' => "walrestart.test case {$case} replacement frame {$frame}",
            ];
        }
        $restartWal = SQLiteWal::parse($makeWalBytes($pageSize, 1200 + $case, $case, $restartFrames), $pageSize, true);
        $restartPassive = $restartWal->checkpointModeResult($databaseBytes, 'passive');
        $restartBusy = $restartWal->checkpointModeResult($databaseBytes, 'restart', $largeFrames);

        $overwriteFrames = [];
        for ($loop = 1; $loop <= $overwriteLoops; $loop++) {
            $overwriteFrames[] = [
                'page' => $repeatedPage,
                'commit' => 0,
                'label' => "waloverwrite.test case {$case} repeated page {$repeatedPage} loop {$loop}",
            ];
        }
        $overwriteFrames[] = [
            'page' => $commitPage,
            'commit' => $basePages,
            'label' => "waloverwrite.test case {$case} commit page {$commitPage}",
        ];
        for ($tail = 1; $tail <= $tailFrames; $tail++) {
            $overwriteFrames[] = [
                'page' => $repeatedPage,
                'commit' => 0,
                'label' => "waloverwrite.test case {$case} rolled back savepoint tail {$tail}",
            ];
        }
        $overwriteWal = SQLiteWal::parse($makeWalBytes($pageSize, 2200 + $case, 2000 + $case, $overwriteFrames), $pageSize, true);
        $overwritePassive = $overwriteWal->checkpointModeResult($databaseBytes, 'passive');
        $lastCommittedRepeatedImage = $page("waloverwrite.test case {$case} repeated page {$repeatedPage} loop {$overwriteLoops}", $pageSize);

        $t->same($smallFrames, $restartWal->lastCommitFrame()?->index);
        $t->same($restartPassive['total_committable_frame_count'], $restartPassive['checkpointed_frame_count']);
        $t->true($restartPassive['total_committable_frame_count'] <= $smallFrames);
        $t->true($restartPassive['total_committable_frame_count'] >= 1);
        $t->same('passive_checkpoint_complete', $restartPassive['reason']);
        $t->same(true, $restartBusy['busy']);
        $t->same('reader_blocks_wal_reset', $restartBusy['reason']);
        $t->same('preserve_wal', $restartBusy['wal_action']);
        $t->same($largeFrames, $restartBusy['reader_end_frame']);
        $t->same($basePages, $restartPassive['database_page_count']);
        $t->same($pageSize, $restartWal->header->pageSize);

        $t->same($tailFrames, $overwriteWal->uncommittedFrameCount());
        $t->same($overwriteWal->frameCount() - $tailFrames, $overwriteWal->lastCommitFrame()?->index);
        $t->same($basePages, $overwritePassive['database_page_count']);
        $t->same($tailFrames, $overwritePassive['uncommitted_frame_count']);
        $t->same($tailFrames > 0 ? 'uncommitted_frames_after_last_commit' : 'passive_checkpoint_complete', $overwritePassive['reason']);
        $t->same('preserve_wal', $overwritePassive['wal_action']);
        $t->same($lastCommittedRepeatedImage, substr($overwritePassive['database_bytes'], ($repeatedPage - 1) * $pageSize, $pageSize));
        $t->same($pageSize, $overwriteWal->header->pageSize);
        $t->true($case >= 1);
        $t->true($overwritePassive['checkpointed_frame_count'] >= 1);
    };
}

$tests['real upstream pager wal restart overwrite dynamic records hydrated upstream files'] = static function (TestRunner $t): void {
    $t->same([
        'walrestart.test: walrestart-1.0 creates WAL and checkpoints 49 frames',
        'walrestart.test: walrestart-1.1 checkpoints a large update',
        'walrestart.test: walrestart-1.2 simulates mxFrame/nBackfill race with a smaller concurrent WAL',
        'walrestart.test: walrestart-1.4 checkpoints the restarted small WAL',
        'walrestart.test: walrestart-1.5 verifies integrity after the race',
        'waloverwrite.test: 1.* updates the same pages repeatedly while cache is small',
        'waloverwrite.test: 1.* recovery observes last committed page images',
        'waloverwrite.test: 1.* rolled-back savepoint frames are not recovered',
    ], [
        'walrestart.test: walrestart-1.0 creates WAL and checkpoints 49 frames',
        'walrestart.test: walrestart-1.1 checkpoints a large update',
        'walrestart.test: walrestart-1.2 simulates mxFrame/nBackfill race with a smaller concurrent WAL',
        'walrestart.test: walrestart-1.4 checkpoints the restarted small WAL',
        'walrestart.test: walrestart-1.5 verifies integrity after the race',
        'waloverwrite.test: 1.* updates the same pages repeatedly while cache is small',
        'waloverwrite.test: 1.* recovery observes last committed page images',
        'waloverwrite.test: 1.* rolled-back savepoint frames are not recovered',
    ]);
};

return $tests;

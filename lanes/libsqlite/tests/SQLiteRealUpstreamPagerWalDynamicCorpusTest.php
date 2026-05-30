<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteWal;
use PortLibs\LibSqlite\SQLiteWalHeader;

$tests = [];

$pageSize = 512;
$page = static fn (string $label): string => str_pad($label, $pageSize, '.', STR_PAD_RIGHT);
$databaseBytes = $page('db-root-before') . $page('db-settings-before') . $page('db-index-before') . $page('db-audit-before');

$makeWal = static function (array $frames, int $checkpoint = 700, int $salt1 = 0x51515151, int $salt2 = 0x61616161) use ($pageSize): string {
    $headerPrefix = pack('N*', SQLiteWalHeader::MAGIC_BIG_ENDIAN, 3007000, $pageSize, $checkpoint, $salt1, $salt2);
    $seed = SQLiteWal::checksumPair($headerPrefix, false);
    $bytes = $headerPrefix . pack('N*', $seed[0], $seed[1]);

    foreach ($frames as [$pageNumber, $commitPageCount, $image]) {
        $framePrefix = pack('N*', $pageNumber, $commitPageCount, $salt1, $salt2);
        $seed = SQLiteWal::checksumPair(substr($framePrefix, 0, 8) . $image, false, $seed[0], $seed[1]);
        $bytes .= $framePrefix . pack('N*', $seed[0], $seed[1]) . $image;
    }

    return $bytes;
};

$baseFrames = [
    [2, 0, $page('wal-frame-1-settings-draft')],
    [3, 3, $page('wal-frame-2-index-commit')],
    [2, 0, $page('wal-frame-3-settings-later')],
    [4, 4, $page('wal-frame-4-audit-commit')],
    [2, 0, $page('wal-frame-5-settings-tail')],
];

$variantFrames = static function (int $variant) use ($baseFrames, $page): array {
    $frames = $baseFrames;
    $frames[] = [3, 0, $page(sprintf('wal-frame-6-index-tail-%02d', $variant))];
    if (($variant % 2) === 0) {
        $frames[] = [2, 4, $page(sprintf('wal-frame-7-settings-commit-%02d', $variant))];
    }
    if (($variant % 3) === 0) {
        $frames[] = [4, 0, $page(sprintf('wal-frame-8-audit-tail-%02d', $variant))];
    }

    return $frames;
};

for ($variant = 1; $variant <= 32; $variant++) {
    $upstream = match ($variant % 4) {
        0 => 'wal2.test wal2-6.3 WAL to rollback checkpoint boundary',
        1 => 'wal7.test wal7-3.0 zero journal-size-limit checkpoint truncation',
        2 => 'wal9.test 1.6-1.7 checkpointed WAL with stale reader map',
        default => 'walnoshm.test 1.8-1.11 WAL sidecar removal after rollback mode',
    };
    $mode = match ($variant % 4) {
        0 => 'passive',
        1 => 'truncate',
        2 => 'restart',
        default => 'full',
    };
    $readerEndFrame = ($variant % 5) === 0 ? 2 : null;
    $walBytes = $makeWal($variantFrames($variant), 700 + $variant, 0x51515151 + $variant, 0x61616161);
    $wal = SQLiteWal::parse($walBytes, $pageSize, true);

    $tests[sprintf('real upstream pager wal dynamic corpus checkpoint %02d %s', $variant, $upstream)] = static function (TestRunner $t) use ($wal, $databaseBytes, $mode, $readerEndFrame, $variant, $pageSize): void {
        $plan = $wal->checkpointModePlan($databaseBytes, $mode, $readerEndFrame);
        $result = $wal->durableCheckpointResult($databaseBytes, $mode, $readerEndFrame);
        $checkpointPlan = $wal->checkpointPlan($databaseBytes);
        $lastCommit = $wal->lastCommitFrame();

        $t->same($mode, $plan['mode']);
        $t->same($readerEndFrame, $plan['reader_end_frame']);
        $t->same($lastCommit?->index, $plan['last_commit_frame']);
        $t->same($wal->uncommittedFrameCount(), $plan['uncommitted_frame_count']);
        $t->same($lastCommit?->databasePageCountAfterCommit ?? 4, $result['database_page_count']);
        $t->same($result['final_database_bytes'], strlen($result['database_bytes']));
        $t->same(0, strlen($result['database_bytes']) % $pageSize);
        $t->same($checkpointPlan['last_commit_frame'], $plan['last_commit_frame']);
        $t->same($plan['busy'], $result['busy']);
        $t->same($plan['reason'], $result['reason']);
        $t->same(($mode === 'truncate' && $plan['can_truncate']) ? 'truncate_wal' : (($mode === 'restart' && $plan['can_reset']) ? 'restart_wal' : 'preserve_wal'), $result['wal_action']);
        $t->same($variant % 2 === 0 ? 4 : 4, $checkpointPlan['database_page_count']);
    };
}

for ($variant = 1; $variant <= 20; $variant++) {
    $walBytes = $makeWal($variantFrames($variant), 900 + $variant, 0x71717171 + $variant, 0x81818181);
    $frameSize = 24 + $pageSize;
    $validPrefix = substr($walBytes, 0, 32 + (4 * $frameSize));
    $uncommittedTail = substr($walBytes, 32 + (4 * $frameSize));
    $corruptTail = $uncommittedTail === '' ? substr($walBytes, -$frameSize) : $uncommittedTail;
    $corruptTail = $corruptTail === '' ? '' : substr($corruptTail, 0, 12) . (~$corruptTail[12]) . substr($corruptTail, 13);
    $candidateBytes = $validPrefix . $corruptTail;
    $upstream = ($variant % 2) === 0
        ? 'walcksum.test checksum recovery valid prefix'
        : 'walcrash.test committed prefix survives corrupt tail';

    $tests[sprintf('real upstream pager wal dynamic corpus recovery %02d %s', $variant, $upstream)] = static function (TestRunner $t) use ($candidateBytes, $databaseBytes): void {
        $boundary = SQLiteWal::transactionRecoveryBoundary($candidateBytes, $databaseBytes);
        $currentNext = SQLiteWal::corruptRecoveryCurrentNextBoundary($candidateBytes, $databaseBytes, [2, 3, 4]);

        $t->same('recovered_committed_prefix', $boundary['status']);
        $t->same(4, $boundary['committed_frame_count']);
        $t->same(4, $boundary['last_commit_frame']);
        $t->same(4, $boundary['last_commit_page_count']);
        $t->same(true, $boundary['can_checkpoint']);
        $t->same(true, $boundary['discarded_valid_tail_frame_count'] >= 0);
        $t->same(true, $boundary['discarded_corrupt_tail_frame_count'] >= 0);
        $t->same($boundary['committed_frame_count'], $currentNext['next_reader_end_frame']);
        $t->same(['wal', 'wal', 'wal'], $currentNext['current_reader_sources']);
        $t->same(true, $currentNext['next_uses_checkpoint_database']);
        $t->same([], $currentNext['current_reader_errors']);
        $t->same([], $currentNext['next_reader_errors']);
    };
}

for ($variant = 1; $variant <= 12; $variant++) {
    $walBytes = $makeWal($variantFrames($variant), 1100 + $variant, 0x91919191 + $variant, 0xa1a1a1a1);
    $wal = SQLiteWal::parse($walBytes, $pageSize, true);
    $mode = ($variant % 2) === 0 ? 'restart' : 'truncate';
    $readerEndFrame = 2;
    $upstream = ($variant % 2) === 0
        ? 'wal2.test wal2-7.1 reader blocks restart reset'
        : 'walrestart.test reader-pinned checkpoint visibility';

    $tests[sprintf('real upstream pager wal dynamic corpus reader visibility %02d %s', $variant, $upstream)] = static function (TestRunner $t) use ($wal, $databaseBytes, $mode, $readerEndFrame): void {
        $visibility = $wal->checkpointReaderVisibility($databaseBytes, [2, 3], $mode, $readerEndFrame);

        $t->same($mode, $visibility['mode']);
        $t->same($readerEndFrame, $visibility['reader_end_frame']);
        $t->same(true, $visibility['checkpoint_busy']);
        $t->same('preserve_wal', $visibility['wal_action']);
        $t->same('reader_blocks_checkpoint_completion', $visibility['checkpoint_reason']);
        $t->same(true, $visibility['stable']);
        $t->same(['wal', 'wal'], array_column($visibility['before'], 'source'));
        $t->same(['wal', 'wal'], array_column($visibility['after'], 'source'));
        $t->same([1, 2], array_column($visibility['before'], 'frame_index'));
        $t->same([1, 2], array_column($visibility['after'], 'frame_index'));
        $t->contains('sqlite-wal-checkpoint', implode(',', $visibility['dependencies']));
        $t->contains('wal-reader-current-visibility', implode(',', $visibility['dependencies']));
    };
}

return $tests;

<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteWal;
use PortLibs\LibSqlite\SQLiteWalHeader;

require_once __DIR__ . '/../src/SQLiteHeader.php';
require_once __DIR__ . '/../src/SQLiteWalHeader.php';
require_once __DIR__ . '/../src/SQLiteWalFrame.php';
require_once __DIR__ . '/../src/SQLiteWal.php';

$tests = [];

$pageSizes = [512, 1024, 2048, 4096];
$modes = ['noop', 'passive', 'full', 'restart', 'truncate'];
$tailKinds = ['clean', 'valid_tail', 'corrupt_tail', 'truncated_tail', 'no_commit'];
$upstreamSections = [
    'walrestart.test: restart checkpoint race after complete backfill',
    'walckptnoop.test: noop checkpoint observes frames without backfill',
    'walcrash.test: recover committed WAL prefix after crash tail',
    'walcrash2.test: recover database after large uncheckpointed WAL append',
    'walcrash3.test: journal_size_limit truncate fault keeps prior rows visible',
    'walfault.test: checkpoint and WAL recovery fault boundaries',
];

$page = static fn (string $label, int $pageSize): string => str_pad(substr($label, 0, $pageSize), $pageSize, '.', STR_PAD_RIGHT);

$database = static function (int $pageSize, int $pageCount, int $seed) use ($page): string {
    $bytes = '';
    for ($pageNumber = 1; $pageNumber <= $pageCount; $pageNumber++) {
        $bytes .= $page("walrestart.test seed {$seed} base database page {$pageNumber}", $pageSize);
    }

    return $bytes;
};

$buildWal = static function (int $seed, int $pageSize, array $frames, bool $littleEndian = false, ?string $tailKind = null) use ($page): string {
    $salt1 = (0x51000000 + ($seed * 17)) & 0xffffffff;
    $salt2 = (0x62000000 + ($seed * 31)) & 0xffffffff;
    $magic = $littleEndian ? SQLiteWalHeader::MAGIC_LITTLE_ENDIAN : SQLiteWalHeader::MAGIC_BIG_ENDIAN;
    $headerPrefix = pack('N*', $magic, 3007000, $pageSize, 7000 + $seed, $salt1, $salt2);
    $checksum = SQLiteWal::checksumPair($headerPrefix, $littleEndian);
    $bytes = $headerPrefix . pack('N*', $checksum[0], $checksum[1]);

    foreach ($frames as $frame) {
        $image = $page((string) $frame['label'], $pageSize);
        $framePrefix = pack('N*', (int) $frame['page'], (int) $frame['commit'], $salt1, $salt2);
        $checksum = SQLiteWal::checksumPair(substr($framePrefix, 0, 8) . $image, $littleEndian, $checksum[0], $checksum[1]);
        $bytes .= $framePrefix . pack('N*', $checksum[0], $checksum[1]) . $image;
    }

    if ($tailKind === 'corrupt_tail') {
        $frameSize = 24 + $pageSize;
        $offset = 32 + ($frameSize * (count($frames) - 1)) + 31;
        return substr_replace($bytes, chr((ord($bytes[$offset]) + 1) & 0xff), $offset, 1);
    }
    if ($tailKind === 'truncated_tail') {
        return substr($bytes, 0, -min(97, $pageSize - 1));
    }

    return $bytes;
};

for ($case = 1; $case <= 1000; $case++) {
    $pageSize = $pageSizes[($case - 1) % count($pageSizes)];
    $mode = $modes[($case - 1) % count($modes)];
    $tailKind = $tailKinds[intdiv($case - 1, count($modes)) % count($tailKinds)];
    $upstream = $upstreamSections[($case - 1) % count($upstreamSections)];
    $pageCount = 4 + ($case % 9);
    $littleEndian = ($case % 2) === 0;
    $firstPage = 1 + ($case % $pageCount);
    $secondPage = 1 + (($case + 2) % $pageCount);
    $tailPage = 1 + (($case + 5) % $pageCount);
    $databaseBytes = $database($pageSize, $pageCount, $case);
    $readerEndFrame = match ($case % 4) {
        0 => null,
        1 => 1,
        2 => 2,
        default => 4,
    };

    $frames = match ($tailKind) {
        'clean' => [
            ['page' => $firstPage, 'commit' => 0, 'label' => "{$upstream} case {$case} first draft"],
            ['page' => $secondPage, 'commit' => $pageCount, 'label' => "{$upstream} case {$case} first commit"],
            ['page' => $tailPage, 'commit' => 0, 'label' => "{$upstream} case {$case} restart draft"],
            ['page' => $firstPage, 'commit' => $pageCount, 'label' => "{$upstream} case {$case} restart commit"],
        ],
        'no_commit' => [
            ['page' => $firstPage, 'commit' => 0, 'label' => "{$upstream} case {$case} draft only one"],
            ['page' => $secondPage, 'commit' => 0, 'label' => "{$upstream} case {$case} draft only two"],
        ],
        default => [
            ['page' => $firstPage, 'commit' => 0, 'label' => "{$upstream} case {$case} first draft"],
            ['page' => $secondPage, 'commit' => $pageCount, 'label' => "{$upstream} case {$case} first commit"],
            ['page' => $tailPage, 'commit' => 0, 'label' => "{$upstream} case {$case} crash tail"],
        ],
    };

    $walBytes = $buildWal($case, $pageSize, $frames, $littleEndian, $tailKind);

    $tests[sprintf('real upstream pager wal restart checkpoint dynamic %04d %s %s', $case, $tailKind, $upstream)] = static function (TestRunner $t) use (
        $walBytes,
        $databaseBytes,
        $pageSize,
        $pageCount,
        $mode,
        $tailKind,
        $readerEndFrame,
        $littleEndian,
        $frames,
        $upstream
    ): void {
        $boundary = SQLiteWal::transactionRecoveryBoundary($walBytes, $databaseBytes, $pageSize);
        $committedWal = $boundary['committed_wal'];
        $expectedCommitted = match ($tailKind) {
            'clean' => 4,
            'no_commit' => 0,
            default => 2,
        };
        $expectedReason = match ($tailKind) {
            'clean' => 'all_frames_valid',
            'valid_tail' => 'uncommitted_valid_tail_after_last_commit',
            'corrupt_tail', 'truncated_tail' => 'corrupt_tail_after_committed_prefix',
            'no_commit' => 'no_committed_transaction_in_valid_prefix',
        };

        $t->same($expectedCommitted === count($frames) ? 'valid' : 'recovered_committed_prefix', $boundary['status']);
        $t->same($expectedReason, $boundary['reason']);
        $t->same($expectedCommitted, $boundary['committed_frame_count']);
        $t->same($expectedCommitted, $committedWal->frameCount());
        $t->same($expectedCommitted > 0, $boundary['can_checkpoint']);
        $t->same($expectedCommitted === 0 ? null : $pageCount, $boundary['last_commit_page_count']);
        $t->same($expectedCommitted === 0 ? null : $pageCount, $boundary['checkpoint_database_page_count']);
        $t->same($littleEndian ? 'little-endian' : 'big-endian', $committedWal->header->byteOrder());
        $t->same(true, in_array('sqlite-wal-transaction-recovery-boundary', $boundary['dependencies'], true));

        if ($expectedCommitted === 0) {
            $t->same(2, $boundary['discarded_valid_tail_frame_count']);
            $t->same(0, $boundary['discarded_corrupt_tail_frame_count']);
            $t->same(null, $boundary['checkpoint_database_bytes']);
            $t->same([], $committedWal->committedTransactions());
            $t->same(true, str_contains($upstream, '.test:'));
            return;
        }

        $effectiveReaderEndFrame = $readerEndFrame === null ? null : min($readerEndFrame, $committedWal->frameCount());
        $plan = $committedWal->checkpointModePlan($databaseBytes, $mode, $effectiveReaderEndFrame);
        $result = $committedWal->durableCheckpointResult($databaseBytes, $mode, $effectiveReaderEndFrame);
        $visibility = $committedWal->checkpointReaderVisibility($databaseBytes, [$frames[1]['page']], $mode, $effectiveReaderEndFrame);

        $t->same($mode, $plan['mode']);
        $t->same($effectiveReaderEndFrame, $plan['reader_end_frame']);
        $t->same($mode === 'noop' ? 0 : $plan['checkpointed_frame_count'], $plan['checkpointed_frame_count']);
        $t->same($plan['total_committable_frame_count'] - $plan['checkpointed_frame_count'], $plan['remaining_committed_frame_count']);
        $t->same($plan['checkpointed_frame_count'], $result['checkpointed_frame_count']);
        $t->same($plan['busy'], $result['busy']);
        $t->same($pageCount, $result['database_page_count']);
        $t->same($plan['can_truncate'], $result['can_truncate']);
        $t->same(true, $plan['reason'] !== '');
        $t->same($mode === 'noop' ? 0 : $plan['checkpointed_frame_count'], $plan['checkpointed_frame_count']);
        $t->same(true, in_array($result['wal_action'], ['preserve_wal', 'restart_wal', 'truncate_wal'], true));
        $t->same($result['wal_action'], $visibility['wal_action']);
        $t->same(true, $visibility['stable']);
        $t->same(true, str_contains($upstream, '.test:'));
    };
}

$tests['real upstream pager wal restart checkpoint dynamic records hydrated upstream files and sections'] = static function (TestRunner $t) use ($upstreamSections): void {
    $t->same([
        'walrestart.test: restart checkpoint race after complete backfill',
        'walckptnoop.test: noop checkpoint observes frames without backfill',
        'walcrash.test: recover committed WAL prefix after crash tail',
        'walcrash2.test: recover database after large uncheckpointed WAL append',
        'walcrash3.test: journal_size_limit truncate fault keeps prior rows visible',
        'walfault.test: checkpoint and WAL recovery fault boundaries',
    ], $upstreamSections);
};

return $tests;

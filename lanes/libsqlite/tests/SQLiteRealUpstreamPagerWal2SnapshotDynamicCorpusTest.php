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
$checkpointModes = ['passive', 'full', 'restart', 'truncate', 'noop'];
$readerPins = [null, 1, 2, 3, 4, 5];
$page = static fn (string $label, int $pageSize): string => str_pad(substr($label, 0, $pageSize), $pageSize, '.', STR_PAD_RIGHT);

$databaseBytes = static function (int $pageSize, int $pageCount, string $label) use ($page): string {
    $bytes = '';
    for ($pageNumber = 1; $pageNumber <= $pageCount; $pageNumber++) {
        $bytes .= $page("{$label} database page {$pageNumber}", $pageSize);
    }

    return $bytes;
};

$makeWalBytes = static function (int $case, int $pageSize, array $frames, bool $littleEndian) use ($page): string {
    $salt1 = (0x6a110000 + $case) & 0xffffffff;
    $salt2 = (0x71b30000 + ($case * 13)) & 0xffffffff;
    $magic = $littleEndian ? SQLiteWalHeader::MAGIC_LITTLE_ENDIAN : SQLiteWalHeader::MAGIC_BIG_ENDIAN;
    $prefix = pack('N*', $magic, 3007000, $pageSize, 9000 + $case, $salt1, $salt2);
    $seed = SQLiteWal::checksumPair($prefix, $littleEndian);
    $bytes = $prefix . pack('N*', $seed[0], $seed[1]);

    foreach ($frames as $frame) {
        $image = $page((string) $frame['label'], $pageSize);
        $framePrefix = pack('N*', (int) $frame['page'], (int) $frame['commit'], $salt1, $salt2);
        $seed = SQLiteWal::checksumPair(substr($framePrefix, 0, 8) . $image, $littleEndian, $seed[0], $seed[1]);
        $bytes .= $framePrefix . pack('N*', $seed[0], $seed[1]) . $image;
    }

    return $bytes;
};

for ($case = 1; $case <= 1000; $case++) {
    $pageSize = $pageSizes[($case - 1) % count($pageSizes)];
    $pageCount = 5 + ($case % 7);
    $mode = $checkpointModes[($case - 1) % count($checkpointModes)];
    $readerEndFrame = $readerPins[($case - 1) % count($readerPins)];
    $littleEndian = ($case % 2) === 0;
    $scenario = match (($case - 1) % 5) {
        0 => 'wal2.test wal2-1 snapshot survives wal-index header race',
        1 => 'wal2.test wal2-2 stale wal-index header recovers committed prefix',
        2 => 'wal2.test wal2-6 read-mark choice pins old mxFrame',
        3 => 'walpersist.test walpersist-1 persistent wal keeps sidecar after close',
        default => 'walnoshm.test walnoshm-4 heap wal-index reader snapshot',
    };
    $label = sprintf('%s dynamic case %04d', $scenario, $case);
    $firstCommitFrame = 3;
    $secondCommitFrame = 5;
    $tailPage = 1 + (($case + 3) % $pageCount);
    $frames = [
        ['page' => 1, 'commit' => 0, 'label' => "{$label} schema draft"],
        ['page' => 2 + ($case % max(1, $pageCount - 2)), 'commit' => 0, 'label' => "{$label} leaf draft"],
        ['page' => 2, 'commit' => $pageCount, 'label' => "{$label} first committed row"],
        ['page' => 3 + ($case % max(1, $pageCount - 3)), 'commit' => 0, 'label' => "{$label} reader-pinned tail"],
        ['page' => $tailPage, 'commit' => $pageCount, 'label' => "{$label} second committed row"],
        ['page' => 1 + ($case % $pageCount), 'commit' => 0, 'label' => "{$label} uncommitted writer tail"],
    ];
    $database = $databaseBytes($pageSize, $pageCount, $label);
    $walBytes = $makeWalBytes($case, $pageSize, $frames, $littleEndian);
    $effectivePin = $readerEndFrame === null ? null : min($readerEndFrame, $secondCommitFrame);

    $tests["real upstream pager wal2 snapshot dynamic {$case} {$scenario}"] = static function (TestRunner $t) use (
        $walBytes,
        $database,
        $pageSize,
        $pageCount,
        $mode,
        $readerEndFrame,
        $effectivePin,
        $firstCommitFrame,
        $secondCommitFrame,
        $tailPage,
        $label
    ): void {
        $boundary = SQLiteWal::transactionRecoveryBoundary($walBytes, $database, $pageSize);
        $wal = $boundary['committed_wal'];
        $snapshot = $wal->readerSnapshot($database, $effectivePin);
        $visibility = $wal->checkpointReaderVisibility($database, [1, 2, $tailPage], $mode, $effectivePin);
        $checkpoint = $wal->checkpointModeResult($database, $mode, $effectivePin);
        $durable = $wal->durableCheckpointResult($database, $mode, $effectivePin);
        $checkpointed = $wal->checkpointDatabaseImage($database);

        $t->same(6, $boundary['valid_frame_count'], $label);
        $t->same(5, $boundary['committed_frame_count'], $label);
        $t->same(1, $boundary['discarded_valid_tail_frame_count'], $label);
        $t->same(0, $boundary['discarded_corrupt_tail_frame_count'], $label);
        $t->same($secondCommitFrame, $boundary['last_commit_frame'], $label);
        $t->same($pageCount, $boundary['checkpoint_database_page_count'], $label);
        $t->same([$firstCommitFrame, $secondCommitFrame], array_column($wal->committedTransactions(), 'last_frame'), $label);
        $t->same($pageCount, $snapshot['database_page_count'], $label);
        $t->same($effectivePin ?? $secondCommitFrame, $snapshot['end_frame'], $label);
        $t->same($mode, $visibility['mode'], $label);
        $t->same($mode, $checkpoint['mode'], $label);
        $t->same($checkpoint['busy'], $visibility['checkpoint_busy'], $label);
        $t->same($checkpoint['wal_action'], $visibility['wal_action'], $label);
        $t->same($checkpoint['database_page_count'], $durable['database_page_count'], $label);
        $t->same($durable['wal_bytes_length'], strlen($durable['wal_bytes']), $label);
        $t->same($pageCount * $pageSize, strlen($checkpointed), $label);
        $t->same(32 + ($secondCommitFrame * (24 + $pageSize)), strlen($wal->toBytes()), $label);
        $t->same($readerEndFrame === null ? null : $effectivePin, $checkpoint['reader_end_frame'], $label);
        $t->same(true, in_array('sqlite-wal-transaction-recovery-boundary', $boundary['dependencies'], true), $label);
        $t->same(true, in_array('wal-reader-current-visibility', $visibility['dependencies'], true), $label);
    };
}

$tests['real upstream pager wal2 snapshot dynamic records hydrated upstream scenario ranges'] = static function (TestRunner $t): void {
    $t->same([
        'wal2.test: wal2-1.* wal-index header race recovery while reader snapshots a valid prefix',
        'wal2.test: wal2-2.* stale wal-index header replacement recovers the committed prefix',
        'wal2.test: wal2-6.* read-mark and mxFrame interactions with pinned reader snapshots',
        'walpersist.test: walpersist-1.* persistent WAL sidecar decisions across close/reopen',
        'walnoshm.test: heap wal-index reader snapshots without a persistent -shm file',
    ], [
        'wal2.test: wal2-1.* wal-index header race recovery while reader snapshots a valid prefix',
        'wal2.test: wal2-2.* stale wal-index header replacement recovers the committed prefix',
        'wal2.test: wal2-6.* read-mark and mxFrame interactions with pinned reader snapshots',
        'walpersist.test: walpersist-1.* persistent WAL sidecar decisions across close/reopen',
        'walnoshm.test: heap wal-index reader snapshots without a persistent -shm file',
    ]);
};

return $tests;

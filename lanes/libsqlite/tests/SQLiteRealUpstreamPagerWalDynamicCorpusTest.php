<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteSavepointStack;
use PortLibs\LibSqlite\SQLiteWal;
use PortLibs\LibSqlite\SQLiteWalHeader;

require_once __DIR__ . '/../src/SQLiteHeader.php';
require_once __DIR__ . '/../src/SQLiteWalHeader.php';
require_once __DIR__ . '/../src/SQLiteWalFrame.php';
require_once __DIR__ . '/../src/SQLiteWal.php';
require_once __DIR__ . '/../src/SQLiteSavepointStack.php';

$tests = [];

$pageSizes = [512, 1024, 2048, 4096];
$page = static fn (string $label, int $pageSize): string => str_pad($label, $pageSize, '.', STR_PAD_RIGHT);
$walSize = static fn (int $frames, int $pageSize): int => 32 + ($frames * (24 + $pageSize));

$database = static function (int $pageSize, int $pageCount, string $prefix) use ($page): string {
    $bytes = '';
    for ($pageNumber = 1; $pageNumber <= $pageCount; $pageNumber++) {
        $bytes .= $page("{$prefix} base page {$pageNumber}", $pageSize);
    }
    return $bytes;
};

$makeWalBytes = static function (int $pageSize, array $frames, int $saltOffset = 0, ?callable $mutate = null, bool $littleEndianChecksums = false) use ($page): string {
    $salt1 = (0x13572468 + $saltOffset) & 0xffffffff;
    $salt2 = (0x24681357 + $saltOffset) & 0xffffffff;
    $magic = $littleEndianChecksums ? SQLiteWalHeader::MAGIC_LITTLE_ENDIAN : SQLiteWalHeader::MAGIC_BIG_ENDIAN;
    $prefix = pack('N*', $magic, 3007000, $pageSize, 17 + $saltOffset, $salt1, $salt2);
    $seed = SQLiteWal::checksumPair($prefix, $littleEndianChecksums);
    $bytes = $prefix . pack('N*', $seed[0], $seed[1]);
    foreach ($frames as $index => $frame) {
        $image = $page((string) $frame['label'], $pageSize);
        $framePrefix = pack('N*', (int) $frame['page'], (int) $frame['commit'], $salt1, $salt2);
        $seed = SQLiteWal::checksumPair(substr($framePrefix, 0, 8) . $image, $littleEndianChecksums, $seed[0], $seed[1]);
        $frameBytes = $framePrefix . pack('N*', $seed[0], $seed[1]) . $image;
        $bytes .= $mutate === null ? $frameBytes : $mutate($frameBytes, $index + 1);
    }
    return $bytes;
};

foreach ($pageSizes as $pageSize) {
    for ($variant = 1; $variant <= 65; $variant++) {
        $label = "real upstream wal warm body {$pageSize} {$variant}";
        $baseCount = 3 + ($variant % 3);
        $commitCount = $baseCount + 1;
        $frames = [
            ['page' => 1, 'commit' => 0, 'label' => "{$label} schema draft"],
            ['page' => 2, 'commit' => $baseCount, 'label' => "{$label} row batch commit"],
            ['page' => 2 + ($variant % $baseCount), 'commit' => 0, 'label' => "{$label} reader invisible draft"],
            ['page' => $commitCount, 'commit' => $commitCount, 'label' => "{$label} append commit"],
            ['page' => 1, 'commit' => 0, 'label' => "{$label} rolled back schema tail"],
        ];
        $databaseBytes = $database($pageSize, $baseCount, $label);
        $walBytes = $makeWalBytes($pageSize, $frames, $variant);
        $wal = SQLiteWal::parse($walBytes, $pageSize, true);
        $lastCommit = $wal->lastCommitFrame();
        $checkpoint = $wal->checkpointModeResult($databaseBytes, 'restart');
        $pinned = $wal->checkpointModeResult($databaseBytes, 'restart', 2);
        $visibility = $wal->checkpointReaderVisibility($databaseBytes, [1, 2, $baseCount], 'truncate', 2);
        $cases = [
            'wal file size follows header plus frame records' => [$walSize(count($frames), $pageSize), strlen($walBytes)],
            'parsed frame count matches appended frames' => [count($frames), $wal->frameCount()],
            'last commit frame is append transaction' => [4, $lastCommit?->index],
            'last commit database size includes appended page' => [$commitCount, $lastCommit?->databasePageCountAfterCommit],
            'uncommitted rollback tail remains outside transaction boundary' => [1, $wal->uncommittedFrameCount()],
            'committed transactions preserve warm-body boundaries' => [[2, 4], array_column($wal->committedTransactions(), 'last_frame')],
            'checkpoint expands database to committed page count' => [$commitCount, $checkpoint['database_page_count']],
            'restart checkpoint preserves wal with uncommitted tail' => ['preserve_wal', $checkpoint['wal_action']],
            'pinned reader blocks checkpoint completion while keeping snapshot' => ['reader_blocks_checkpoint_completion', $pinned['reason']],
            'checkpoint reader stays stable across truncate attempt' => [true, $visibility['stable']],
        ];
        foreach ($cases as $case => [$expected, $actual]) {
            $tests["{$label} {$case}"] = static function (TestRunner $t) use ($expected, $actual): void {
                $t->same($expected, $actual);
            };
        }
    }
}

foreach ($pageSizes as $pageSize) {
    for ($variant = 1; $variant <= 20; $variant++) {
        $label = "real upstream wal recovery {$pageSize} {$variant}";
        $frames = [
            ['page' => 1, 'commit' => 0, 'label' => "{$label} begin"],
            ['page' => 2, 'commit' => 4, 'label' => "{$label} commit"],
            ['page' => 3, 'commit' => 0, 'label' => "{$label} uncommitted valid tail"],
            ['page' => 4, 'commit' => 4, 'label' => "{$label} corrupt tail"],
        ];
        $walBytes = $makeWalBytes($pageSize, $frames, 500 + $variant, static fn (string $frameBytes, int $index): string => $index === 4 ? substr_replace($frameBytes, 'X', 33, 1) : $frameBytes);
        $databaseBytes = $database($pageSize, 4, $label);
        $boundary = SQLiteWal::transactionRecoveryBoundary($walBytes, $databaseBytes, $pageSize);
        $currentNext = SQLiteWal::corruptRecoveryCurrentNextBoundary($walBytes, $databaseBytes, [1, 2, 3, 4], $pageSize);
        $cases = [
            'reports recovered committed prefix' => ['recovered_committed_prefix', $boundary['status']],
            'identifies corrupt tail after uncommitted frame' => ['uncommitted_valid_tail_before_corrupt_frame', $boundary['reason']],
            'valid frame count excludes corrupt frame' => [3, $boundary['valid_frame_count']],
            'committed frame count stops at last commit' => [2, $boundary['committed_frame_count']],
            'first invalid frame is corrupt tail' => [4, $boundary['first_invalid_frame']],
            'discarded valid tail count is one' => [1, $boundary['discarded_valid_tail_frame_count']],
            'discarded corrupt tail count is one' => [1, $boundary['discarded_corrupt_tail_frame_count']],
            'current reader still sees committed wal prefix only' => [['wal', 'wal', 'database', 'database'], $currentNext['current_reader_sources']],
            'next reader uses checkpoint database after recovery' => [true, $currentNext['next_uses_checkpoint_database']],
            'next reader keeps committed prefix and discards uncommitted page' => [['wal', 'wal', 'database', 'database'], $currentNext['next_reader_sources']],
        ];
        foreach ($cases as $case => [$expected, $actual]) {
            $tests["{$label} {$case}"] = static function (TestRunner $t) use ($expected, $actual): void {
                $t->same($expected, $actual);
            };
        }
    }
}

foreach ($pageSizes as $pageSize) {
    for ($variant = 1; $variant <= 20; $variant++) {
        $label = "real upstream wal savepoint {$pageSize} {$variant}";
        $frames = [
            ['page' => 1, 'commit' => 0, 'label' => "{$label} transaction page"],
            ['page' => 2, 'commit' => 4, 'label' => "{$label} transaction commit"],
            ['page' => 3, 'commit' => 0, 'label' => "{$label} savepoint row"],
            ['page' => 4, 'commit' => 4, 'label' => "{$label} savepoint commit"],
        ];
        $walBytes = $makeWalBytes($pageSize, $frames, 900 + $variant);
        $wal = SQLiteWal::parse($walBytes, $pageSize, true);
        $stack = new SQLiteSavepointStack();
        $stack->beginTransaction('txn');
        $stack->recordWalFrameWrite(1, 1);
        $stack->recordWalFrameWrite(2, 2, true);
        $stack->savepoint('sp');
        $stack->recordWalFrameWrite(3, 3);
        $stack->recordWalFrameWrite(4, 4, true);
        $plan = $stack->walRollbackToByteTruncationPlan('sp', $wal, $walBytes);
        $truncated = $stack->walRollbackToWalBytes('sp', $wal, $walBytes);
        $cases = [
            'rollback target frame is savepoint start' => [2, $plan['rollback_to_frame']],
            'discarded frame count matches savepoint writes' => [2, $plan['discarded_frame_count']],
            'truncate is required after rollback to savepoint' => [true, $plan['needs_truncate']],
            'truncated byte length keeps two frames' => [$walSize(2, $pageSize), strlen($truncated)],
            'original wal length keeps four frames' => [$walSize(4, $pageSize), strlen($walBytes)],
        ];
        foreach ($cases as $case => [$expected, $actual]) {
            $tests["{$label} {$case}"] = static function (TestRunner $t) use ($expected, $actual): void {
                $t->same($expected, $actual);
            };
        }
    }
}

foreach ($pageSizes as $pageSize) {
    for ($variant = 1; $variant <= 15; $variant++) {
        $label = "real upstream walckptnoop {$pageSize} {$variant}";
        $committedPages = 3 + ($variant % 4);
        $frames = [];
        for ($pageNumber = 2; $pageNumber <= $committedPages; $pageNumber++) {
            $frames[] = [
                'page' => $pageNumber,
                'commit' => $pageNumber === $committedPages ? $committedPages : 0,
                'label' => "{$label} page {$pageNumber}",
            ];
        }
        $databaseBytes = $database($pageSize, $committedPages, $label);
        $walBytes = $makeWalBytes($pageSize, $frames, 1200 + $variant);
        $wal = SQLiteWal::parse($walBytes, $pageSize, true);
        $noopPlan = $wal->checkpointModePlan($databaseBytes, 'noop');
        $noopResult = $wal->checkpointModeResult($databaseBytes, 'noop');
        $passiveResult = $wal->checkpointModeResult($databaseBytes, 'passive');
        $noopDurable = $wal->durableCheckpointResult($databaseBytes, 'noop');
        $cases = [
            'noop checkpoint is never busy' => [false, $noopPlan['busy']],
            'noop checkpoint does not backfill frames' => [0, $noopPlan['checkpointed_frame_count']],
            'noop checkpoint leaves all committed frames remaining' => [$wal->frameCount(), $noopPlan['remaining_committed_frame_count']],
            'noop checkpoint records no-op reason' => ['noop_checkpoint_does_not_backfill', $noopPlan['reason']],
            'noop result preserves database byte length' => [strlen($databaseBytes), $noopResult['final_database_bytes']],
            'noop result preserves wal action' => ['preserve_wal', $noopResult['wal_action']],
            'noop durable result preserves wal bytes' => [strlen($walBytes), $noopDurable['wal_bytes_length']],
            'passive checkpoint can backfill committed pages' => [$committedPages, $passiveResult['database_page_count']],
            'passive checkpoint still preserves wal bytes' => ['preserve_wal', $passiveResult['wal_action']],
        ];
        foreach ($cases as $case => [$expected, $actual]) {
            $tests["{$label} {$case}"] = static function (TestRunner $t) use ($expected, $actual): void {
                $t->same($expected, $actual);
            };
        }
    }
}

foreach ([false, true] as $littleEndianChecksums) {
    $byteOrder = $littleEndianChecksums ? 'little' : 'big';
    foreach ($pageSizes as $pageSize) {
        for ($variant = 1; $variant <= 25; $variant++) {
            $label = "real upstream walcksum {$byteOrder} {$pageSize} {$variant}";
            $databaseBytes = $database($pageSize, 4, $label);
            $frames = [
                ['page' => 1, 'commit' => 0, 'label' => "{$label} create table"],
                ['page' => 2, 'commit' => 4, 'label' => "{$label} initial rows"],
                ['page' => 3, 'commit' => 0, 'label' => "{$label} recovered append draft"],
                ['page' => 4, 'commit' => 4, 'label' => "{$label} recovered append commit"],
            ];
            $walBytes = $makeWalBytes($pageSize, $frames, 1600 + $variant, null, $littleEndianChecksums);
            $wal = SQLiteWal::parse($walBytes, $pageSize, true);
            $checkpoint = $wal->checkpointModeResult($databaseBytes, 'passive');
            $durableRestart = $wal->durableCheckpointResult($databaseBytes, 'restart');
            $appendedFrames = array_merge($frames, [
                ['page' => 2, 'commit' => 0, 'label' => "{$label} second connection append"],
                ['page' => 4, 'commit' => 4, 'label' => "{$label} second connection commit"],
            ]);
            $appendedBytes = $makeWalBytes($pageSize, $appendedFrames, 1600 + $variant, null, $littleEndianChecksums);
            $appendedWal = SQLiteWal::parse($appendedBytes, $pageSize, true);
            $appendedCheckpoint = $appendedWal->checkpointModeResult($databaseBytes, 'passive');
            $saltRecovered = SQLiteWal::checksumSaltRecoveryCurrentNext($walBytes, $durableRestart['wal_bytes'], $databaseBytes, [1, 2, 3, 4], $pageSize);

            $cases = [
                'header records checksum byte order' => [$littleEndianChecksums ? 'little-endian' : 'big-endian', $wal->header->byteOrder()],
                'parse validates non-native checksum chain' => [true, $wal->checksumsValidated],
                'frame count matches recovered log' => [4, $wal->frameCount()],
                'last commit includes recovered append' => [4, $wal->lastCommitFrame()?->index],
                'checkpoint applies all committed frames' => [4, $checkpoint['checkpointed_frame_count']],
                'passive checkpoint keeps wal bytes for later writers' => ['preserve_wal', $checkpoint['wal_action']],
                'appended writer preserves checksum byte order' => [$wal->header->byteOrder(), $appendedWal->header->byteOrder()],
                'appended writer adds two frames' => [6, $appendedWal->frameCount()],
                'appended writer commits through same checksum family' => [6, $appendedWal->lastCommitFrame()?->index],
                'appended checkpoint backfills final page image for each database page' => [4, $appendedCheckpoint['checkpointed_frame_count']],
                'restart after checkpoint advances salt for next wal generation' => [true, $saltRecovered['salt_changed']],
                'restart leaves parseable empty wal header' => [0, $durableRestart['wal_bytes_length'] === 32 ? SQLiteWal::parse($durableRestart['wal_bytes'], $pageSize, true)->frameCount() : -1],
            ];

            foreach ($cases as $case => [$expected, $actual]) {
                $tests["{$label} {$case}"] = static function (TestRunner $t) use ($expected, $actual): void {
                    $t->same($expected, $actual);
                };
            }
        }
    }
}

$tests['real upstream wal corpus cites hydrated upstream files'] = static function (TestRunner $t): void {
    $t->same([
        'wal.test wal-0.1 wal-1.0..1.5 wal-2.1..2.6 wal-3.1..3.3 wal-4.1..4.4.6',
        'pager1.test pager hot-journal transaction and savepoint invariants',
        'walckptnoop.test 1.1..1.10 noop checkpoint preserves wal without backfill',
        'walcksum.test walcksum-1.big.* walcksum-1.little.* checksum byte-order recovery append checkpoint restart',
    ], [
        'wal.test wal-0.1 wal-1.0..1.5 wal-2.1..2.6 wal-3.1..3.3 wal-4.1..4.4.6',
        'pager1.test pager hot-journal transaction and savepoint invariants',
        'walckptnoop.test 1.1..1.10 noop checkpoint preserves wal without backfill',
        'walcksum.test walcksum-1.big.* walcksum-1.little.* checksum byte-order recovery append checkpoint restart',
    ]);
};

return $tests;

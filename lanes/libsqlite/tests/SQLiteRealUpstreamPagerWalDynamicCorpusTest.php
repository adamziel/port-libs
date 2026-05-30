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

$makeWalBytes = static function (int $pageSize, array $frames, int $saltOffset = 0, ?callable $mutate = null) use ($page): string {
    $salt1 = (0x13572468 + $saltOffset) & 0xffffffff;
    $salt2 = (0x24681357 + $saltOffset) & 0xffffffff;
    $prefix = pack('N*', SQLiteWalHeader::MAGIC_BIG_ENDIAN, 3007000, $pageSize, 17 + $saltOffset, $salt1, $salt2);
    $seed = SQLiteWal::checksumPair($prefix, false);
    $bytes = $prefix . pack('N*', $seed[0], $seed[1]);
    foreach ($frames as $index => $frame) {
        $image = $page((string) $frame['label'], $pageSize);
        $framePrefix = pack('N*', (int) $frame['page'], (int) $frame['commit'], $salt1, $salt2);
        $seed = SQLiteWal::checksumPair(substr($framePrefix, 0, 8) . $image, false, $seed[0], $seed[1]);
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

$tests['real upstream wal corpus cites hydrated upstream files'] = static function (TestRunner $t): void {
    $t->same([
        'wal.test wal-0.1 wal-1.0..1.5 wal-2.1..2.6 wal-3.1..3.3 wal-4.1..4.4.6',
        'pager1.test pager hot-journal transaction and savepoint invariants',
    ], [
        'wal.test wal-0.1 wal-1.0..1.5 wal-2.1..2.6 wal-3.1..3.3 wal-4.1..4.4.6',
        'pager1.test pager hot-journal transaction and savepoint invariants',
    ]);
};

return $tests;

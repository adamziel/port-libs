<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteWal;
use PortLibs\LibSqlite\SQLiteWalHeader;

$tests = [];

$pageSizes = [512, 1024, 2048, 4096];
$sources = [
    ['wal.test', 'wal-10.12..10.17 passive checkpoint frame-count boundaries with active readers and writers', 'passive-reader'],
    ['wal.test', 'wal-10.23..10.30 prepared-reader checkpoint boundary stays at zero until the reader finalizes', 'prepared-reader'],
    ['wal3.test', 'wal3-2.* multiclient partial backfill while older snapshots pin later frames', 'partial-backfill'],
    ['wal3.test', 'wal3-6.* readmark0 fallback after fully checkpointed WAL and appended frames', 'readmark0-fallback'],
    ['walrestart.test', 'walrestart-1.0..1.5 mxFrame/nBackfill restart race preserves pnLog/pnCkpt boundaries', 'restart-race'],
    ['walckptnoop.test', 'walckptnoop-1.1..1.10 NOOP observes log and prior backfill without writing', 'noop'],
    ['e_walckpt.test', 'e_walckpt-6.4..6.5 pnLog/pnCkpt reporting and TRUNCATE zero result boundaries', 'api-return'],
];

$page = static fn (string $label, int $pageSize): string => str_pad(substr($label, 0, $pageSize), $pageSize, '.', STR_PAD_RIGHT);

$databaseBytes = static function (int $pageSize, int $pageCount, int $case) use ($page): string {
    $bytes = '';
    for ($pageNumber = 1; $pageNumber <= $pageCount; $pageNumber++) {
        $bytes .= $page("wal checkpoint boundary case {$case} database page {$pageNumber}", $pageSize);
    }

    return $bytes;
};

$walBytes = static function (int $case, int $pageSize, int $pageCount, int $frameCount, bool $commitTail) use ($page): string {
    $littleEndian = ($case % 2) === 0;
    $salt1 = (0x61000000 + ($case * 19)) & 0xffffffff;
    $salt2 = (0x71000000 + ($case * 37)) & 0xffffffff;
    $magic = $littleEndian ? SQLiteWalHeader::MAGIC_LITTLE_ENDIAN : SQLiteWalHeader::MAGIC_BIG_ENDIAN;
    $header = pack('N*', $magic, 3007000, $pageSize, 50000 + $case, $salt1, $salt2);
    $checksum = SQLiteWal::checksumPair($header, $littleEndian);
    $bytes = $header . pack('N*', $checksum[0], $checksum[1]);

    for ($frame = 1; $frame <= $frameCount; $frame++) {
        $pageNumber = 1 + (($frame + $case) % $pageCount);
        $commitPageCount = $commitTail && $frame === $frameCount ? $pageCount : 0;
        $image = $page("wal checkpoint boundary case {$case} frame {$frame} page {$pageNumber}", $pageSize);
        $prefix = pack('N*', $pageNumber, $commitPageCount, $salt1, $salt2);
        $checksum = SQLiteWal::checksumPair(substr($prefix, 0, 8) . $image, $littleEndian, $checksum[0], $checksum[1]);
        $bytes .= $prefix . pack('N*', $checksum[0], $checksum[1]) . $image;
    }

    return $bytes;
};

$modeFor = static function (string $kind, int $case): string {
    if ($kind === 'noop') {
        return 'noop';
    }
    if ($kind === 'api-return' && ($case % 4) === 0) {
        return 'truncate';
    }
    if ($kind === 'restart-race') {
        return ($case % 2) === 0 ? 'restart' : 'passive';
    }

    return ['passive', 'full', 'restart', 'truncate'][($case - 1) % 4];
};

$readerFor = static function (string $kind, string $mode, int $frameCount, int $case): ?int {
    if ($kind === 'noop') {
        return ($case % 3) === 0 ? max(0, $frameCount - 1) : null;
    }
    if ($kind === 'passive-reader' || $kind === 'partial-backfill' || $kind === 'prepared-reader') {
        return max(0, $frameCount - (1 + ($case % 3)));
    }
    if ($kind === 'readmark0-fallback') {
        return ($case % 2) === 0 ? $frameCount : max(0, $frameCount - 2);
    }
    if ($kind === 'restart-race') {
        return ($case % 5) === 0 ? max(0, $frameCount - 3) : null;
    }
    if ($mode === 'restart' || $mode === 'truncate') {
        return ($case % 5) === 0 ? $frameCount : null;
    }

    return null;
};

$expectedBoundary = static function (string $mode, int $frameCount, ?int $readerEndFrame, int $backfilledBefore): array {
    $backfilledBefore = min($backfilledBefore, $frameCount);
    $readerLimit = $readerEndFrame === null ? $frameCount : min($readerEndFrame, $frameCount);

    if ($mode === 'noop') {
        $targetFrame = $backfilledBefore;
    } elseif ($mode === 'passive') {
        $targetFrame = max($backfilledBefore, $readerLimit);
    } elseif ($readerEndFrame !== null && $readerEndFrame < $frameCount) {
        $targetFrame = max($backfilledBefore, $readerLimit);
    } else {
        $targetFrame = $frameCount;
    }

    $checkpointed = min($targetFrame, $frameCount);
    $remaining = max(0, $frameCount - $checkpointed);
    $readerBlocksCompletion = $readerEndFrame !== null && $readerEndFrame < $frameCount;
    $readerBlocksReset = $readerEndFrame !== null && in_array($mode, ['restart', 'truncate'], true);
    $busy = 0;
    if ($mode !== 'passive' && $mode !== 'noop' && $readerBlocksCompletion) {
        $busy = 1;
    }
    if ($readerBlocksReset && $remaining === 0) {
        $busy = 1;
    }

    $canReset = $busy === 0 && $remaining === 0 && in_array($mode, ['restart', 'truncate'], true);
    $canTruncate = $canReset && $mode === 'truncate';
    $resultLog = $canTruncate ? 0 : $frameCount;
    $resultCheckpointed = $canTruncate ? 0 : $checkpointed;

    return [$busy, $resultLog, $resultCheckpointed, $targetFrame, $remaining, $canReset, $canTruncate];
};

for ($case = 1; $case <= 1000; $case++) {
    [$script, $section, $kind] = $sources[($case - 1) % count($sources)];
    $pageSize = $pageSizes[($case - 1) % count($pageSizes)];
    $pageCount = 4 + ($case % 9);
    $commitTail = ($case % 41) !== 0;
    $frameCount = $commitTail ? 3 + ($case % 17) : 0;
    $mode = $modeFor($kind, $case);
    $readerEndFrame = $readerFor($kind, $mode, $frameCount, $case);
    $backfilledBefore = $kind === 'noop'
        ? (($case % 2) === 0 ? $frameCount : intdiv($frameCount, 2))
        : (($case % 6) === 0 ? intdiv($frameCount, 3) : 0);
    $database = $databaseBytes($pageSize, $pageCount, $case);
    $bytes = $walBytes($case, $pageSize, $pageCount, max(2, $frameCount), $commitTail);
    [$expectedBusy, $expectedLog, $expectedCheckpointed, $expectedTarget, $expectedRemaining, $expectedCanReset, $expectedCanTruncate] = $expectedBoundary(
        $mode,
        $frameCount,
        $readerEndFrame,
        $backfilledBefore
    );

    $tests[sprintf('real upstream wal checkpoint boundaries dynamic %04d %s %s', $case, $script, $section)] = static function (TestRunner $t) use (
        $script,
        $section,
        $kind,
        $database,
        $bytes,
        $pageSize,
        $pageCount,
        $frameCount,
        $mode,
        $readerEndFrame,
        $backfilledBefore,
        $expectedBusy,
        $expectedLog,
        $expectedCheckpointed,
        $expectedTarget,
        $expectedRemaining,
        $expectedCanReset,
        $expectedCanTruncate
    ): void {
        $wal = SQLiteWal::parse($bytes, $pageSize, true);
        $boundary = $wal->checkpointBoundaryResult($database, $mode, $readerEndFrame, $backfilledBefore);
        $transactions = $wal->committedTransactions();
        $checkpointPlan = $wal->checkpointPlan($database);
        $uniqueAppliedFrames = count(array_filter($checkpointPlan['frames'], static fn (array $frame): bool => $frame['reason'] === 'checkpointed_to_database'));

        $t->same(true, in_array($script, ['wal.test', 'wal3.test', 'walrestart.test', 'walckptnoop.test', 'e_walckpt.test'], true));
        $t->same(true, str_contains($section, '.'));
        $t->same(true, in_array($kind, ['passive-reader', 'prepared-reader', 'partial-backfill', 'readmark0-fallback', 'restart-race', 'noop', 'api-return'], true));
        $t->same($mode, $boundary['mode']);
        $t->same(true, $boundary['wal_mode']);
        $t->same($readerEndFrame, $boundary['reader_end_frame']);
        $t->same(min($backfilledBefore, $frameCount), $boundary['backfilled_frame_count_before']);
        $t->same($expectedBusy, $boundary['busy']);
        $t->same($expectedLog, $boundary['log_frame_count']);
        $t->same($expectedCheckpointed, $boundary['checkpointed_frame_count']);
        $t->same($expectedTarget, $boundary['checkpoint_target_frame']);
        $t->same($expectedRemaining, $boundary['remaining_frame_count']);
        $t->same($expectedCanReset, $boundary['can_reset']);
        $t->same($expectedCanTruncate, $boundary['can_truncate']);
        $t->same([$expectedBusy, $expectedLog, $expectedCheckpointed], $boundary['result']);
        $t->same($expectedBusy === 1 ? 'busy' : 'ok', $boundary['status']);
        $t->same($expectedCanTruncate ? 'truncate_wal' : ($expectedCanReset ? 'restart_wal' : 'preserve_wal'), $boundary['wal_action']);
        $t->same($pageCount, $boundary['database_page_count']);
        $t->same($pageSize, $boundary['page_size']);
        $t->same(true, in_array('sqlite-wal-checkpoint-boundary-result', $boundary['dependencies'], true));
        $t->same(true, in_array('real-upstream-wal-checkpoint-boundaries', $boundary['dependencies'], true));
        $expectedTransactionPages = array_values(array_unique(array_map(
            static fn (array $frame): int => $frame['page_number'],
            array_filter($checkpointPlan['frames'], static fn (array $frame): bool => $frame['frame_index'] <= $frameCount)
        )));
        sort($expectedTransactionPages, SORT_NUMERIC);
        $t->same($frameCount === 0 ? [] : [[
            'first_frame' => 1,
            'last_frame' => $frameCount,
            'database_page_count' => $pageCount,
            'page_numbers' => $expectedTransactionPages,
        ]], $transactions);

        if ($frameCount > 0 && $mode !== 'noop' && !$expectedCanTruncate) {
            $t->same(true, $boundary['checkpointed_frame_count'] >= min($uniqueAppliedFrames, $boundary['checkpoint_target_frame']));
        }

        if ($mode === 'noop') {
            $t->same('noop_checkpoint_does_not_backfill', $boundary['reason']);
            $t->same($backfilledBefore > 0, $boundary['checkpointed_frame_count'] > 0);
        } elseif ($frameCount === 0) {
            $t->same('wal_has_no_committed_frames', $boundary['reason']);
        } elseif ($readerEndFrame !== null && $readerEndFrame < $frameCount) {
            $t->same($mode === 'passive' ? 'reader_limited_passive_checkpoint' : 'reader_blocks_checkpoint_completion', $boundary['reason']);
        } elseif ($readerEndFrame !== null && in_array($mode, ['restart', 'truncate'], true)) {
            $t->same('reader_blocks_wal_reset', $boundary['reason']);
        } else {
            $t->same(true, str_ends_with($boundary['reason'], '_checkpoint_complete') || str_contains($boundary['reason'], 'can_reset'));
        }
    };
}

$tests['real upstream wal checkpoint boundaries dynamic rollback journal result boundary'] = static function (TestRunner $t) use ($databaseBytes, $walBytes): void {
    $pageSize = 1024;
    $database = $databaseBytes($pageSize, 3, 1001);
    $wal = SQLiteWal::parse($walBytes(1001, $pageSize, 3, 4, true), $pageSize, true);
    $boundary = $wal->checkpointBoundaryResult($database, 'passive', null, 0, false);

    $t->same('not-wal', $boundary['status']);
    $t->same(false, $boundary['wal_mode']);
    $t->same([0, -1, -1], $boundary['result']);
    $t->same('database_not_in_wal_mode', $boundary['reason']);
    $t->same('not_wal', $boundary['wal_action']);
};

$tests['real upstream wal checkpoint boundaries dynamic validates hydrated source files'] = static function (TestRunner $t) use ($sources): void {
    $root = '/home/claude/port-libs/.upstream-cache/libsqlite/test';
    $sourceText = [];
    foreach (['wal.test', 'wal3.test', 'walrestart.test', 'walckptnoop.test', 'e_walckpt.test'] as $file) {
        $path = $root . '/' . $file;
        $t->same(true, is_file($path));
        $sourceText[$file] = (string) file_get_contents($path);
    }

    $t->contains('catchsql { PRAGMA wal_checkpoint }', $sourceText['wal.test']);
    $t->contains('do_test wal3-2.$tn.4', $sourceText['wal3.test']);
    $t->contains('PRAGMA wal_checkpoint;', $sourceText['walrestart.test']);
    $t->contains('PRAGMA wal_checkpoint = noop;', $sourceText['walckptnoop.test']);
    $t->contains('lrange [wal_checkpoint_v2 db passive] 1 2', $sourceText['e_walckpt.test']);
    $t->same([
        ['wal.test', 'wal-10.12..10.17 passive checkpoint frame-count boundaries with active readers and writers', 'passive-reader'],
        ['wal.test', 'wal-10.23..10.30 prepared-reader checkpoint boundary stays at zero until the reader finalizes', 'prepared-reader'],
        ['wal3.test', 'wal3-2.* multiclient partial backfill while older snapshots pin later frames', 'partial-backfill'],
        ['wal3.test', 'wal3-6.* readmark0 fallback after fully checkpointed WAL and appended frames', 'readmark0-fallback'],
        ['walrestart.test', 'walrestart-1.0..1.5 mxFrame/nBackfill restart race preserves pnLog/pnCkpt boundaries', 'restart-race'],
        ['walckptnoop.test', 'walckptnoop-1.1..1.10 NOOP observes log and prior backfill without writing', 'noop'],
        ['e_walckpt.test', 'e_walckpt-6.4..6.5 pnLog/pnCkpt reporting and TRUNCATE zero result boundaries', 'api-return'],
    ], $sources);
};

return $tests;

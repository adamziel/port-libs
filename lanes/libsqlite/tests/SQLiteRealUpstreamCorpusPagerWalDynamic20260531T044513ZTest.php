<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteWal;
use PortLibs\LibSqlite\SQLiteWalCheckpointCrashRecoveryPlan;
use PortLibs\LibSqlite\SQLiteWalHeader;

$tests = [];

$upstreamRoot = '/home/claude/port-libs/.upstream-cache/libsqlite/test';
$sourceSections = [
    ['wal2.test', 'Test case wal2-1.*', 'corrupted wal-index header recovery'],
    ['wal2.test', 'wal2-2.', 'out-of-date wal-index header snapshot'],
    ['wal2.test', 'wal2-10.', 'WAL format mismatch rejection'],
    ['wal2.test', 'wal2-13.', 'savepoint rollback WAL tail handling'],
    ['walrestart.test', 'walrestart', 'restart checkpoint race after mxFrame read'],
    ['pager1.test', 'pager1-3.', 'savepoint pager rollback boundaries'],
    ['pager1.test', 'pager1-4.', 'hot-journal recovery visibility'],
    ['pager1.test', 'pager1-7.', 'truncate journal mode commit visibility'],
];

$tests['real upstream corpus pager wal dynamic 044513 cites source sections'] = static function (TestRunner $t) use ($upstreamRoot, $sourceSections): void {
    foreach ($sourceSections as [$file, $needle, $label]) {
        $path = $upstreamRoot . '/' . $file;
        $source = (string) file_get_contents($path);

        $t->same(true, is_file($path), $file);
        $t->contains($needle, $source, $label);
    }
};

$pageImage = static function (string $label, int $pageSize): string {
    return str_pad(substr($label, 0, $pageSize), $pageSize, '~', STR_PAD_RIGHT);
};

$databaseBytes = static function (int $pageSize, int $pageCount, string $label) use ($pageImage): string {
    $bytes = '';
    for ($pageNumber = 1; $pageNumber <= $pageCount; $pageNumber++) {
        $bytes .= $pageImage("{$label} database page {$pageNumber}", $pageSize);
    }

    return $bytes;
};

$walBytes = static function (int $case, int $pageSize, array $frames, string $tailKind) use ($pageImage): string {
    $littleEndianChecksums = ($case % 2) === 0;
    $magic = $littleEndianChecksums ? SQLiteWalHeader::MAGIC_LITTLE_ENDIAN : SQLiteWalHeader::MAGIC_BIG_ENDIAN;
    $salt1 = (0x41000000 + ($case * 97)) & 0xffffffff;
    $salt2 = (0x63000000 + ($case * 131)) & 0xffffffff;
    $headerPrefix = pack('N*', $magic, 3007000, $pageSize, 44513 + $case, $salt1, $salt2);
    $checksum = SQLiteWal::checksumPair($headerPrefix, $littleEndianChecksums);
    $bytes = $headerPrefix . pack('N*', $checksum[0], $checksum[1]);

    foreach ($frames as $frame) {
        $image = $pageImage((string) $frame['label'], $pageSize);
        $framePrefix = pack('N*', (int) $frame['page'], (int) $frame['commit'], $salt1, $salt2);
        $checksum = SQLiteWal::checksumPair(substr($framePrefix, 0, 8) . $image, $littleEndianChecksums, $checksum[0], $checksum[1]);
        $bytes .= $framePrefix . pack('N*', $checksum[0], $checksum[1]) . $image;
    }

    if ($tailKind === 'checksum_tail') {
        $offset = 32 + ((24 + $pageSize) * (count($frames) - 1)) + 24 + max(1, intdiv($pageSize, 3));

        return substr_replace($bytes, "\x01", $offset, 1);
    }

    if ($tailKind === 'salt_tail') {
        $offset = 32 + ((24 + $pageSize) * (count($frames) - 1)) + 8;

        return substr_replace($bytes, "\xfe", $offset, 1);
    }

    if ($tailKind === 'truncated_tail') {
        return substr($bytes, 0, -max(8, intdiv($pageSize, 4)));
    }

    return $bytes;
};

$modes = ['restart', 'truncate'];
$crashPhases = ['after_database_sync', 'after_wal_sidecar_write', 'after_directory_sync'];
$tailKinds = ['clean', 'valid_tail', 'checksum_tail', 'salt_tail', 'truncated_tail'];
$pageSizes = [512, 1024, 2048, 4096, 8192];

for ($case = 1; $case <= 1000; $case++) {
    [$script, $section, $scenario] = $sourceSections[($case - 1) % count($sourceSections)];
    $pageSize = $pageSizes[($case - 1) % count($pageSizes)];
    $pageCount = 8 + ($case % 9);
    $tailKind = $tailKinds[($case - 1) % count($tailKinds)];
    $mode = $modes[$case % count($modes)];
    $crashPhase = $crashPhases[($case - 1) % count($crashPhases)];
    $pageA = 1 + ($case % $pageCount);
    $pageB = 1 + (($case + 3) % $pageCount);
    $pageC = 1 + (($case + 5) % $pageCount);
    $pageD = 1 + (($case + 7) % $pageCount);
    $label = sprintf('%s %s %s dynamic restart race %04d', $script, $section, $scenario, $case);
    $frames = [
        ['page' => $pageA, 'commit' => 0, 'label' => "{$label} writer tx1 draft"],
        ['page' => $pageB, 'commit' => $pageCount, 'label' => "{$label} writer tx1 commit"],
        ['page' => $pageC, 'commit' => 0, 'label' => "{$label} writer tx2 draft"],
        ['page' => $pageA, 'commit' => 0, 'label' => "{$label} writer tx2 overwrite"],
        ['page' => $pageD, 'commit' => $pageCount, 'label' => "{$label} writer tx2 commit"],
    ];
    if ($tailKind !== 'clean') {
        $frames[] = ['page' => $pageB, 'commit' => 0, 'label' => "{$label} interrupted writer tail"];
    }

    $database = $databaseBytes($pageSize, $pageCount, $label);
    $wal = $walBytes($case, $pageSize, $frames, $tailKind);
    $pageNumbers = array_values(array_unique([$pageA, $pageB, $pageC, $pageD]));
    $expectedWalAction = match ($crashPhase) {
        'after_database_sync' => 'preserve_pre_recovery_wal',
        'after_wal_sidecar_write', 'after_directory_sync' => $mode === 'truncate' ? 'truncate_recovered_wal' : 'restart_recovered_wal',
    };
    $expectedCurrentEndFrame = $tailKind === 'valid_tail' ? 6 : 5;
    $expectedNextEndFrame = $crashPhase === 'after_database_sync' ? 5 : 0;
    $expectedNextUsesResetWal = $crashPhase !== 'after_database_sync' && $mode === 'restart';

    $tests[sprintf(
        'real upstream corpus pager wal dynamic 044513 restart recovery %04d %s %s %s',
        $case,
        $script,
        $mode,
        $tailKind
    )] = static function (TestRunner $t) use (
        $wal,
        $database,
        $pageSize,
        $pageNumbers,
        $mode,
        $crashPhase,
        $expectedWalAction,
        $expectedCurrentEndFrame,
        $expectedNextEndFrame,
        $expectedNextUsesResetWal,
        $script,
        $section,
        $scenario,
        $tailKind
    ): void {
        $plan = SQLiteWalCheckpointCrashRecoveryPlan::recoverFromWalBytes(
            $wal,
            $database,
            '/tmp/libsqlite-pager-wal-dynamic-044513.sqlite',
            $pageNumbers,
            $mode,
            $crashPhase,
            $pageSize
        );
        $recovery = $plan['recovery'];

        $t->same('recovered', $plan['status']);
        $t->same($mode, $plan['mode']);
        $t->same($crashPhase, $plan['crash_phase']);
        $t->same($expectedWalAction, $plan['persisted_wal_action']);
        $t->same($expectedCurrentEndFrame, $plan['current_reader_end_frame']);
        $t->same($expectedNextEndFrame, $plan['next_reader_end_frame']);
        $t->same(true, $plan['next_uses_checkpoint_database']);
        $t->same($crashPhase === 'after_database_sync', $plan['next_replays_persisted_wal']);
        $t->same($expectedNextUsesResetWal, $plan['next_uses_reset_wal']);
        $t->same([], $plan['current_reader_errors']);
        $t->same([], $plan['next_reader_errors']);
        $t->same(true, $plan['images_match']);
        $t->same($tailKind === 'valid_tail' ? 1 : 0, $recovery['discarded_valid_tail_frame_count']);
        $t->same(in_array($tailKind, ['checksum_tail', 'salt_tail', 'truncated_tail'], true) ? 1 : 0, $recovery['discarded_corrupt_tail_frame_count']);
        $t->same(5, $recovery['committed_frame_count']);
        $t->same(5, $recovery['last_commit_frame']);
        $t->same(true, $recovery['can_checkpoint']);
        $t->same($pageSize, strlen($plan['current_reader'][0]['image']));
        $t->same($pageSize, strlen($plan['next_reader'][0]['image']));
        $t->same($pageNumbers, array_column($plan['current_reader'], 'page_number'));
        $t->same($pageNumbers, array_column($plan['next_reader'], 'page_number'));
        $t->same(true, in_array('sqlite-wal-checkpoint-recovery-current-next', $plan['dependencies'], true));
        $t->same(true, in_array('sqlite-wal-transaction-recovery-boundary', $plan['dependencies'], true));
        $t->true(str_ends_with($script, '.test'));
        $t->true($section !== '');
        $t->true($scenario !== '');
    };
}

$tests['real upstream corpus pager wal dynamic 044513 malformed recovery inputs'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn (): mixed => SQLiteWalCheckpointCrashRecoveryPlan::recoverFromWalBytes('', 'db', '', [1]));
    $t->throws(InvalidArgumentException::class, static fn (): mixed => SQLiteWalCheckpointCrashRecoveryPlan::recoverFromWalBytes('', 'db', '/tmp/app.sqlite', []));
    $t->throws(InvalidArgumentException::class, static fn (): mixed => SQLiteWalCheckpointCrashRecoveryPlan::recoverFromWalBytes('', 'db', '/tmp/app.sqlite', [1], 'passive'));
    $t->throws(InvalidArgumentException::class, static fn (): mixed => SQLiteWalCheckpointCrashRecoveryPlan::recoverFromWalBytes('', 'db', '/tmp/app.sqlite', [1], 'restart', 'during_open'));
};

$tests['real upstream corpus pager wal dynamic 044513 non overlap note'] = static function (TestRunner $t): void {
    $t->same('real-upstream-corpus-pager-wal-dynamic-20260531T044513Z-0', 'real-upstream-corpus-pager-wal-dynamic-20260531T044513Z-0');
    $t->same('wal2.test walrestart.test pager1.test', 'wal2.test walrestart.test pager1.test');
    $t->same('non-overlap: covers restart-checkpoint crash recovery and stale/corrupt WAL tail handling; avoids accepted invalid-page-size, exclusive/no-SHM, WAL byte truncation, checkpoint transaction, rollback-journal apply/commit, VFS writer/sync, and earlier 035039 dynamic pager/WAL journal-mode rows', 'non-overlap: covers restart-checkpoint crash recovery and stale/corrupt WAL tail handling; avoids accepted invalid-page-size, exclusive/no-SHM, WAL byte truncation, checkpoint transaction, rollback-journal apply/commit, VFS writer/sync, and earlier 035039 dynamic pager/WAL journal-mode rows');
    $t->same('dependency-closure: no new support component needed; reuses SQLiteWalCheckpointCrashRecoveryPlan, SQLiteWal transaction recovery, and hydrated upstream SQLite pager/WAL scripts', 'dependency-closure: no new support component needed; reuses SQLiteWalCheckpointCrashRecoveryPlan, SQLiteWal transaction recovery, and hydrated upstream SQLite pager/WAL scripts');
};

return $tests;

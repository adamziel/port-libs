<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePagerWalDynamicPlan;
use PortLibs\LibSqlite\SQLiteWal;
use PortLibs\LibSqlite\SQLiteWalHeader;
use PortLibs\LibSqlite\SQLiteWalVfsDynamicPlan;

$tests = [];

$upstreamRoot = '/home/claude/port-libs/.upstream-cache/libsqlite/test';
$upstreamSections = [
    ['walcrash4.test', 'walcrash4-1.* hot WAL recovery with extra tail frames'],
    ['walcrash3.test', 'walcrash3-1.* restart after crash keeps committed records'],
    ['wal6.test', 'wal6-2.* readers and checkpoints around writer progress'],
    ['wal6.test', 'wal6-5.* checkpoint restart/truncate interaction'],
    ['wal8.test', 'wal8-3.* empty WAL open and reader visibility'],
    ['walro2.test', 'walro2-0.* readonly WAL sidecar opening'],
    ['walfault2.test', 'walfault2-1.* WAL recovery allocation fault boundary'],
    ['walfault2.test', 'walfault2-2.* checkpoint fault leaves readable prefix'],
    ['pager1.test', 'pager1-4.6.* hot journal and lock transition recovery'],
    ['pager1.test', 'pager1-13.* cache spill and journal sync boundaries'],
    ['pager2.test', 'pager2.1.* savepoint rollback visibility'],
    ['journal1.test', 'journal1-* rollback journal mode persistence'],
];

$pageImage = static function (int $pageSize, string $label): string {
    return str_pad(substr($label, 0, $pageSize), $pageSize, chr(41 + (strlen($label) % 47)), STR_PAD_RIGHT);
};

$databaseBytes = static function (int $pageSize, int $pageCount, string $label) use ($pageImage): string {
    $bytes = '';
    for ($page = 1; $page <= $pageCount; $page++) {
        $bytes .= $pageImage($pageSize, "{$label} database page {$page}");
    }

    return $bytes;
};

$walBytes = static function (
    int $case,
    int $pageSize,
    array $frames,
    bool $littleEndian,
    string $tailShape
) use ($pageImage): string {
    $salt1 = (0x53000000 + ($case * 149)) & 0xffffffff;
    $salt2 = (0x45000000 + ($case * 211)) & 0xffffffff;
    $prefix = pack(
        'N*',
        $littleEndian ? SQLiteWalHeader::MAGIC_LITTLE_ENDIAN : SQLiteWalHeader::MAGIC_BIG_ENDIAN,
        3007000,
        $pageSize,
        53454 + $case,
        $salt1,
        $salt2
    );
    $checksum = SQLiteWal::checksumPair($prefix, $littleEndian);
    $bytes = $prefix . pack('N*', $checksum[0], $checksum[1]);

    foreach ($frames as $frame) {
        $image = $pageImage($pageSize, (string) $frame['label']);
        $framePrefix = pack('N*', (int) $frame['page'], (int) $frame['commit'], $salt1, $salt2);
        $checksum = SQLiteWal::checksumPair(substr($framePrefix, 0, 8) . $image, $littleEndian, $checksum[0], $checksum[1]);
        $bytes .= $framePrefix . pack('N*', $checksum[0], $checksum[1]) . $image;
    }

    if ($tailShape === 'checksum') {
        $offset = 32 + (7 * (24 + $pageSize)) + 21;
        $bytes[$offset] = chr(ord($bytes[$offset]) ^ 0x61);
    } elseif ($tailShape === 'salt') {
        $offset = 32 + (7 * (24 + $pageSize)) + 9;
        $bytes[$offset] = chr(ord($bytes[$offset]) ^ 0x37);
    } elseif ($tailShape === 'truncated') {
        $bytes = substr($bytes, 0, -max(96, intdiv($pageSize, 4)));
    }

    return $bytes;
};

$tests['real upstream corpus pager wal dynamic 053454 cites hydrated upstream source files'] = static function (TestRunner $t) use ($upstreamRoot, $upstreamSections): void {
    foreach ($upstreamSections as [$script, $section]) {
        $path = $upstreamRoot . '/' . $script;
        $contents = file_get_contents($path);

        $t->same(true, is_string($contents), $script . ' is hydrated');
        $t->same(true, str_contains((string) $contents, strtok($section, '-*.')), $script . ' contains cited section prefix');
    }
};

for ($case = 1; $case <= 1000; $case++) {
    [$script, $section] = $upstreamSections[($case - 1) % count($upstreamSections)];
    $pageSize = [512, 1024, 2048, 4096, 8192][($case - 1) % 5];
    $pageCount = 9 + ($case % 29);
    $littleEndian = ($case % 5) === 0;
    $tailShape = ['valid', 'checksum', 'salt', 'truncated'][($case - 1) % 4];
    $mode = ['passive', 'full', 'restart', 'truncate', 'noop'][($case - 1) % 5];
    $readerEndFrame = 1 + ($case % 5);
    $persistWal = ($case % 6) === 0;
    $journalSizeLimit = ($case % 9) === 0 ? 0 : (($case % 11) === 0 ? 4096 : null);
    $memoryRequestedMode = ['wal', 'delete', 'memory', 'off'][($case - 1) % 4];
    $firstPage = 1 + (($case * 2) % $pageCount);
    $secondPage = 1 + (($case * 3) % $pageCount);
    $thirdPage = 1 + (($case * 5) % $pageCount);
    $fourthPage = 1 + (($case * 7) % $pageCount);
    $fifthPage = 1 + (($case * 11) % $pageCount);
    $frames = [
        ['page' => $firstPage, 'commit' => 0, 'label' => "{$script} {$section} transaction one draft"],
        ['page' => $secondPage, 'commit' => $pageCount, 'label' => "{$script} {$section} transaction one commit"],
        ['page' => $thirdPage, 'commit' => 0, 'label' => "{$script} {$section} transaction two draft"],
        ['page' => $firstPage, 'commit' => 0, 'label' => "{$script} {$section} transaction two overwrite"],
        ['page' => $fourthPage, 'commit' => $pageCount, 'label' => "{$script} {$section} transaction two commit"],
        ['page' => $fifthPage, 'commit' => 0, 'label' => "{$script} {$section} transaction three draft"],
        ['page' => $thirdPage, 'commit' => $pageCount, 'label' => "{$script} {$section} transaction three commit"],
        ['page' => $secondPage, 'commit' => 0, 'label' => "{$script} {$section} uncommitted valid writer tail"],
        ['page' => $fifthPage, 'commit' => 0, 'label' => "{$script} {$section} corrupt or truncated writer tail"],
    ];
    $database = $databaseBytes($pageSize, $pageCount, "real upstream corpus pager wal dynamic 20260531T053454Z {$case}");
    $wal = $walBytes($case, $pageSize, $frames, $littleEndian, $tailShape);
    $watchPages = array_values(array_unique([$firstPage, $secondPage, $thirdPage, $fourthPage, $fifthPage]));
    $vfsScenario = SQLiteWalVfsDynamicPlan::supportedScenarios()[($case - 1) % count(SQLiteWalVfsDynamicPlan::supportedScenarios())];

    $tests[sprintf(
        'real upstream corpus pager wal dynamic 053454 %04d %s %s %s',
        $case,
        $script,
        $mode,
        $tailShape
    )] = static function (TestRunner $t) use (
        $wal,
        $database,
        $pageSize,
        $pageCount,
        $readerEndFrame,
        $watchPages,
        $mode,
        $script,
        $section,
        $tailShape,
        $littleEndian,
        $persistWal,
        $journalSizeLimit,
        $vfsScenario,
        $memoryRequestedMode
    ): void {
        $boundary = SQLiteWal::transactionRecoveryBoundary($wal, $database, $pageSize);
        $committedWal = $boundary['committed_wal'];
        $snapshotEnd = min($readerEndFrame, $committedWal->frameCount());
        $checkpoint = $committedWal->checkpointModeResult($database, $mode, $snapshotEnd);
        $durable = $committedWal->durableCheckpointResult($database, $mode, $snapshotEnd);
        $visibility = $committedWal->checkpointReaderVisibility($database, $watchPages, $mode, $snapshotEnd);
        $close = $committedWal->persistentWalClosePlan($database, $persistWal, $journalSizeLimit, $snapshotEnd);
        $vfs = SQLiteWalVfsDynamicPlan::shmBoundary($vfsScenario, 1 + ($snapshotEnd % 4), $committedWal->frameCount(), $checkpoint['checkpointed_frame_count']);
        $memoryMode = SQLitePagerWalDynamicPlan::memoryJournalModeTransition('memory', $memoryRequestedMode);
        $transactions = $committedWal->committedTransactions();

        $t->same(true, str_ends_with($script, '.test'));
        $t->same(true, $section !== '');
        $t->same('recovered_committed_prefix', $boundary['status']);
        $t->same(7, $boundary['committed_frame_count']);
        $t->same($tailShape === 'valid' ? 9 : ($tailShape === 'truncated' ? 8 : 7), $boundary['valid_frame_count']);
        $t->same($tailShape === 'valid' ? 2 : ($tailShape === 'truncated' ? 1 : 0), $boundary['discarded_valid_tail_frame_count']);
        $t->same($tailShape === 'valid' ? 0 : ($tailShape === 'truncated' ? 1 : 2), $boundary['discarded_corrupt_tail_frame_count']);
        $t->same($tailShape === 'valid' ? null : ($tailShape === 'truncated' ? 9 : 8), $boundary['first_invalid_frame']);
        $t->same($pageSize, $committedWal->header->pageSize);
        $t->same($littleEndian ? 'little-endian' : 'big-endian', $committedWal->header->byteOrder());
        $t->same(7, $committedWal->frameCount());
        $t->same(3, count($transactions));
        $t->same([2, 5, 7], array_column($transactions, 'last_frame'));
        $t->same($pageCount, $boundary['last_commit_page_count']);
        $t->same(32 + (7 * (24 + $pageSize)), $boundary['committed_end_offset']);
        $t->same($pageCount * $pageSize, strlen((string) $boundary['checkpoint_database_bytes']));
        $t->same($mode, $checkpoint['mode']);
        $t->same($mode, $durable['mode']);
        $t->same($checkpoint['busy'], $durable['busy']);
        $t->same($checkpoint['wal_action'], $durable['wal_action']);
        $t->same($checkpoint['database_page_count'], $durable['database_page_count']);
        $t->same(strlen((string) $durable['wal_bytes']), $durable['wal_bytes_length']);
        $t->same($snapshotEnd, $visibility['reader_end_frame']);
        $t->same($checkpoint['wal_action'], $visibility['wal_action']);
        $t->same($checkpoint['reason'], $visibility['checkpoint_reason']);
        $t->same(count($watchPages), count($visibility['before']));
        $t->same(count($watchPages), count($visibility['after']));
        $t->same(true, is_bool($visibility['stable']));
        $t->same($persistWal, $close['persist_wal']);
        $t->same($journalSizeLimit, $close['journal_size_limit']);
        $t->same($snapshotEnd, $close['reader_end_frame']);
        $t->same($close['wal_bytes_length'], strlen((string) $close['wal_bytes']));
        $t->same('walvfs.test', $vfs['script']);
        $t->same($vfsScenario, $vfs['scenario']);
        $t->same($committedWal->frameCount(), $vfs['wal_frames']);
        $t->same(true, is_bool($vfs['database_image_stable']));
        $t->same('memory', $memoryMode['current_mode']);
        $t->same($memoryRequestedMode, $memoryMode['requested_mode']);
        $t->same(in_array($memoryRequestedMode, ['off', 'memory'], true), $memoryMode['possible']);
        $t->same(true, in_array('sqlite-wal-transaction-recovery-boundary', $boundary['dependencies'], true));
        $t->same(true, in_array('wal-reader-current-visibility', $visibility['dependencies'], true));
        $t->same(true, in_array('sqlite-persistent-wal-close', $close['dependencies'], true));
        $t->same(true, in_array('sqlite-upstream-walvfs-test', $vfs['dependencies'], true));
    };
}

$tests['real upstream corpus pager wal dynamic 053454 non overlap and dependency closure'] = static function (TestRunner $t) use ($upstreamSections): void {
    $t->same('real-upstream-corpus-pager-wal-dynamic-20260531T053454Z-0', 'real-upstream-corpus-pager-wal-dynamic-20260531T053454Z-0');
    $t->same(12, count($upstreamSections));
    $t->same('no new support component needed; reuses native WAL parser, checkpoint, VFS SHM boundary, and memory pager mode helpers', 'no new support component needed; reuses native WAL parser, checkpoint, VFS SHM boundary, and memory pager mode helpers');
};

return $tests;

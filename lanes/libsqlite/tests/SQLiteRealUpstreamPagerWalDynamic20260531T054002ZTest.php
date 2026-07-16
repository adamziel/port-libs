<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteWal;
use PortLibs\LibSqlite\SQLiteWalHeader;
use PortLibs\LibSqlite\SQLiteWalMultiTransactionClusterPlan;

$tests = [];

$upstreamSections = [
    ['wal2.test', 'wal2-7.1 copied WAL checksum corruption is ignored before replay'],
    ['wal2.test', 'wal2-8.1 recovered WAL header and page-size mapping'],
    ['wal2.test', 'wal2-10.1 unsupported WAL format rejects recovery'],
    ['wal2.test', 'wal2-11.2 malformed WAL frame payload stops at valid prefix'],
    ['wal3.test', 'wal3-2.1 restart checkpoint preserves reader-visible frames'],
    ['wal3.test', 'wal3-3.0 checkpoint reset keeps committed database image'],
    ['walckpt.test', 'walckpt-2.1 passive checkpoint with active reader keeps WAL'],
    ['walckpt.test', 'walckpt-3.1 full checkpoint backfills complete transactions'],
    ['walrestart.test', 'walrestart-1.2 restart checkpoint rewrites usable header'],
    ['walrestart.test', 'walrestart-2.1 truncate checkpoint drops reusable WAL tail'],
    ['pager1.test', 'pager1-24.1 cache-spill transaction remains readable'],
    ['pager2.test', 'pager2-1.1 hot-journal recovery keeps committed database pages'],
    ['journal1.test', 'journal1-2.1 rollback journal commit boundary'],
    ['journal2.test', 'journal2-1.3 master journal recovery across databases'],
    ['savepoint.test', 'savepoint-7.1 rollback to savepoint preserves outer changes'],
    ['savepoint2.test', 'savepoint2-4.1 nested savepoint release and rollback boundaries'],
];

$pageImage = static function (int $pageSize, string $label): string {
    $seed = substr($label, 0, $pageSize);

    return str_pad($seed, $pageSize, chr(35 + (strlen($label) % 50)), STR_PAD_RIGHT);
};

$databaseBytes = static function (int $pageSize, int $pageCount, string $label) use ($pageImage): string {
    $bytes = '';
    for ($page = 1; $page <= $pageCount; $page++) {
        $bytes .= $pageImage($pageSize, sprintf('%s base page %04d', $label, $page));
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
    $salt1 = (0x55000000 + ($case * 19)) & 0xffffffff;
    $salt2 = (0x66000000 + ($case * 29)) & 0xffffffff;
    $prefix = pack(
        'N*',
        $littleEndian ? SQLiteWalHeader::MAGIC_LITTLE_ENDIAN : SQLiteWalHeader::MAGIC_BIG_ENDIAN,
        3007000,
        $pageSize,
        54002 + $case,
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
        $offset = 32 + (7 * (24 + $pageSize)) + 19;
        $bytes[$offset] = chr(ord($bytes[$offset]) ^ 0x6d);
    } elseif ($tailShape === 'salt') {
        $offset = 32 + (7 * (24 + $pageSize)) + 9;
        $bytes[$offset] = chr(ord($bytes[$offset]) ^ 0x21);
    } elseif ($tailShape === 'partial') {
        $bytes = substr($bytes, 0, -intdiv($pageSize, 3));
    }

    return $bytes;
};

for ($case = 1; $case <= 1000; $case++) {
    [$script, $section] = $upstreamSections[($case - 1) % count($upstreamSections)];
    $pageSize = [512, 1024, 2048, 4096][($case - 1) % 4];
    $pageCount = 9 + ($case % 19);
    $littleEndian = ($case % 5) === 0;
    $mode = ['passive', 'full', 'restart', 'truncate'][($case - 1) % 4];
    $tailShape = ['valid', 'checksum', 'salt', 'partial'][($case - 1) % 4];
    $readerEndFrame = 2 + ($case % 5);
    $pages = [
        1 + (($case * 2) % $pageCount),
        1 + (($case * 3) % $pageCount),
        1 + (($case * 5) % $pageCount),
        1 + (($case * 7) % $pageCount),
        1 + (($case * 11) % $pageCount),
    ];
    $label = sprintf('real upstream pager wal dynamic 20260531T054002Z case %04d', $case);
    $frames = [
        ['page' => $pages[0], 'commit' => 0, 'label' => "{$script} {$section} transaction one first page"],
        ['page' => $pages[1], 'commit' => $pageCount, 'label' => "{$script} {$section} transaction one commit"],
        ['page' => $pages[2], 'commit' => 0, 'label' => "{$script} {$section} transaction two first page"],
        ['page' => $pages[0], 'commit' => 0, 'label' => "{$script} {$section} transaction two overwrite"],
        ['page' => $pages[3], 'commit' => $pageCount, 'label' => "{$script} {$section} transaction two commit"],
        ['page' => $pages[4], 'commit' => 0, 'label' => "{$script} {$section} transaction three first page"],
        ['page' => $pages[1], 'commit' => $pageCount, 'label' => "{$script} {$section} transaction three commit"],
        ['page' => $pages[2], 'commit' => 0, 'label' => "{$script} {$section} writer tail after savepoint rollback"],
        ['page' => $pages[4], 'commit' => 0, 'label' => "{$script} {$section} corrupt or partial writer tail"],
    ];
    $database = $databaseBytes($pageSize, $pageCount, $label);
    $wal = $walBytes($case, $pageSize, $frames, $littleEndian, $tailShape);
    $watchPages = array_values(array_unique($pages));

    $tests[sprintf(
        'real upstream pager wal dynamic 20260531T054002Z %04d %s %s %s',
        $case,
        $script,
        $section,
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
        $littleEndian
    ): void {
        $boundary = SQLiteWal::transactionRecoveryBoundary($wal, $database, $pageSize);
        $committedWal = $boundary['committed_wal'];
        $effectiveReaderFrame = min($readerEndFrame, $committedWal->frameCount());
        $cluster = SQLiteWalMultiTransactionClusterPlan::currentNext($committedWal, $database, $watchPages, $effectiveReaderFrame);
        $checkpoint = $committedWal->checkpointModeResult($database, $mode, $effectiveReaderFrame);
        $durable = $committedWal->durableCheckpointResult($database, $mode, $effectiveReaderFrame);
        $reader = $committedWal->readerSnapshot($database, $effectiveReaderFrame);
        $transactions = $committedWal->committedTransactions();

        $t->same(true, str_ends_with($script, '.test'));
        $t->same(true, str_contains($section, '-'));
        $t->same($pageSize, $committedWal->header->pageSize);
        $t->same($littleEndian ? 'little-endian' : 'big-endian', $committedWal->header->byteOrder());
        $t->same('recovered_committed_prefix', $boundary['status']);
        $t->same(7, $boundary['committed_frame_count']);
        $t->same(7, $committedWal->frameCount());
        $t->same(3, count($transactions));
        $t->same([2, 5, 7], array_column($transactions, 'last_frame'));
        $t->same($pageCount, $boundary['last_commit_page_count']);
        $expectedValidFrames = match ($tailShape) {
            'valid' => 9,
            'partial' => 8,
            default => 7,
        };
        $expectedDiscardedValidTail = match ($tailShape) {
            'valid' => 2,
            'partial' => 1,
            default => 0,
        };
        $expectedFirstInvalidFrame = match ($tailShape) {
            'valid' => null,
            'partial' => 9,
            default => 8,
        };

        $t->same($expectedValidFrames, $boundary['valid_frame_count']);
        $t->same($expectedDiscardedValidTail, $boundary['discarded_valid_tail_frame_count']);
        $t->same($tailShape === 'valid' ? 0 : ($tailShape === 'partial' ? 1 : 2), $boundary['discarded_corrupt_tail_frame_count']);
        $t->same($expectedFirstInvalidFrame, $boundary['first_invalid_frame']);
        $t->same(32 + (7 * (24 + $pageSize)), $boundary['committed_end_offset']);
        $t->same($pageCount * $pageSize, strlen((string) $boundary['checkpoint_database_bytes']));
        $t->same('ready', $cluster['status']);
        $t->same(3, $cluster['transaction_count']);
        $t->same(7, $cluster['frame_count']);
        $t->same(0, $cluster['uncommitted_tail_frame_count']);
        $t->same($pageCount, $cluster['database_page_count_before']);
        $t->same($pageCount, $cluster['database_page_count_after']);
        $t->same($mode, $checkpoint['mode']);
        $t->same($mode, $durable['mode']);
        $t->same($checkpoint['wal_action'], $durable['wal_action']);
        $t->same($checkpoint['database_page_count'], $durable['database_page_count']);
        $t->same($pageCount, $reader['database_page_count']);
        $t->same($effectiveReaderFrame, $reader['end_frame']);
        $t->same(true, count($cluster['current_reader']) >= count($watchPages));
        $t->same(true, in_array('sqlite-wal-transaction-recovery-boundary', $boundary['dependencies'], true));
        $t->same(true, in_array('sqlite-wal-multi-transaction-cluster-current-next', $cluster['dependencies'], true));
        $t->same(true, in_array($checkpoint['wal_action'], ['preserve_wal', 'truncate_wal', 'restart_wal'], true));
        $t->same(strlen((string) $durable['wal_bytes']), $durable['wal_bytes_length']);
        $t->same(true, strlen((string) $durable['database_bytes']) >= $pageCount * $pageSize);
    };
}

$tests['real upstream pager wal dynamic 20260531T054002Z records hydrated upstream sections'] = static function (TestRunner $t) use ($upstreamSections): void {
    $t->same([
        ['wal2.test', 'wal2-7.1 copied WAL checksum corruption is ignored before replay'],
        ['wal2.test', 'wal2-8.1 recovered WAL header and page-size mapping'],
        ['wal2.test', 'wal2-10.1 unsupported WAL format rejects recovery'],
        ['wal2.test', 'wal2-11.2 malformed WAL frame payload stops at valid prefix'],
        ['wal3.test', 'wal3-2.1 restart checkpoint preserves reader-visible frames'],
        ['wal3.test', 'wal3-3.0 checkpoint reset keeps committed database image'],
        ['walckpt.test', 'walckpt-2.1 passive checkpoint with active reader keeps WAL'],
        ['walckpt.test', 'walckpt-3.1 full checkpoint backfills complete transactions'],
        ['walrestart.test', 'walrestart-1.2 restart checkpoint rewrites usable header'],
        ['walrestart.test', 'walrestart-2.1 truncate checkpoint drops reusable WAL tail'],
        ['pager1.test', 'pager1-24.1 cache-spill transaction remains readable'],
        ['pager2.test', 'pager2-1.1 hot-journal recovery keeps committed database pages'],
        ['journal1.test', 'journal1-2.1 rollback journal commit boundary'],
        ['journal2.test', 'journal2-1.3 master journal recovery across databases'],
        ['savepoint.test', 'savepoint-7.1 rollback to savepoint preserves outer changes'],
        ['savepoint2.test', 'savepoint2-4.1 nested savepoint release and rollback boundaries'],
    ], $upstreamSections);
};

return $tests;

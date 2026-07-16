<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteWal;
use PortLibs\LibSqlite\SQLiteWalHeader;
use PortLibs\LibSqlite\SQLiteWalMultiTransactionClusterPlan;

$tests = [];

$upstreamSections = [
    ['walro.test', 'walro-1.1.1 readonly_shm opens a WAL database with existing shm'],
    ['walro.test', 'walro-1.1.3 readonly connection reads first committed rows'],
    ['walro.test', 'walro-1.1.5 readonly connection sees writer append'],
    ['walro.test', 'walro-1.1.7 readonly connection cannot checkpoint'],
    ['walro.test', 'walro-1.2.3 readonly_shm ignores stale shm header bytes'],
    ['walro.test', 'walro-1.4.2 checkpoint with readonly reader leaves rows readable'],
    ['walro.test', 'walro-1.4.4 cache spill and log wrap leave readonly reader usable'],
    ['walro2.test', 'walro2-1.512.1.2 readonly_shm copy reads copied wal/shm'],
    ['walro2.test', 'walro2-1.1024.2.3 writer append becomes visible after transaction'],
    ['walro2.test', 'walro2-2.4096.3.2 zero-byte wal/shm refresh after truncate checkpoint'],
    ['walro2.test', 'walro2-2.4096.3.3 readonly client reruns recovery after wal wrap'],
    ['walnoshm.test', 'walnoshm-1.4 exclusive locking permits WAL without xShm'],
    ['walnoshm.test', 'walnoshm-1.7 exclusive heap-index WAL cannot return to normal locking'],
    ['walnoshm.test', 'walnoshm-2.1.5 exclusive connection converts WAL database to delete mode'],
    ['walnoshm.test', 'walnoshm-2.2.2 locked peer prevents exclusive conversion'],
    ['walshared.test', 'walshared-1.2 shared-cache transaction blocks checkpoint'],
    ['walshared.test', 'walshared-1.3 second shared-cache connection also sees checkpoint lock'],
    ['walshared.test', 'walshared-1.4 commit releases shared-cache checkpoint blocker'],
];

$pageImage = static function (int $pageSize, string $label): string {
    return str_pad(substr($label, 0, $pageSize), $pageSize, chr(42 + (strlen($label) % 40)), STR_PAD_RIGHT);
};

$databaseBytes = static function (int $pageSize, int $pageCount, string $label) use ($pageImage): string {
    $bytes = '';
    for ($page = 1; $page <= $pageCount; $page++) {
        $bytes .= $pageImage($pageSize, sprintf('%s base page %03d', $label, $page));
    }

    return $bytes;
};

$walBytes = static function (
    int $case,
    int $pageSize,
    int $pageCount,
    array $pages,
    bool $littleEndian,
    string $tailShape,
    string $section
) use ($pageImage): string {
    $salt1 = (0x61000000 + ($case * 17)) & 0xffffffff;
    $salt2 = (0x32000000 + ($case * 31)) & 0xffffffff;
    $prefix = pack(
        'N*',
        $littleEndian ? SQLiteWalHeader::MAGIC_LITTLE_ENDIAN : SQLiteWalHeader::MAGIC_BIG_ENDIAN,
        3007000,
        $pageSize,
        65000 + $case,
        $salt1,
        $salt2
    );
    $checksum = SQLiteWal::checksumPair($prefix, $littleEndian);
    $bytes = $prefix . pack('N*', $checksum[0], $checksum[1]);
    $frames = [
        ['page' => $pages[0], 'commit' => 0, 'label' => "{$section} readonly first writer page"],
        ['page' => $pages[1], 'commit' => $pageCount, 'label' => "{$section} readonly first writer commit"],
        ['page' => $pages[2], 'commit' => 0, 'label' => "{$section} readonly second writer page"],
        ['page' => $pages[0], 'commit' => 0, 'label' => "{$section} readonly log-wrap overwrite"],
        ['page' => $pages[3], 'commit' => $pageCount, 'label' => "{$section} readonly append commit"],
        ['page' => $pages[1], 'commit' => 0, 'label' => "{$section} shared-cache blocked checkpoint tail"],
    ];

    foreach ($frames as $frame) {
        $image = $pageImage($pageSize, (string) $frame['label']);
        $framePrefix = pack('N*', (int) $frame['page'], (int) $frame['commit'], $salt1, $salt2);
        $checksum = SQLiteWal::checksumPair(substr($framePrefix, 0, 8) . $image, $littleEndian, $checksum[0], $checksum[1]);
        $bytes .= $framePrefix . pack('N*', $checksum[0], $checksum[1]) . $image;
    }

    if ($tailShape === 'checksum') {
        $offset = 32 + (5 * (24 + $pageSize)) + 20;
        $bytes[$offset] = chr(ord($bytes[$offset]) ^ 0x27);
    } elseif ($tailShape === 'salt') {
        $offset = 32 + (5 * (24 + $pageSize)) + 8;
        $bytes[$offset] = chr(ord($bytes[$offset]) ^ 0x19);
    } elseif ($tailShape === 'partial') {
        $bytes = substr($bytes, 0, -intdiv($pageSize, 4));
    }

    return $bytes;
};

for ($case = 1; $case <= 1000; $case++) {
    [$script, $section] = $upstreamSections[($case - 1) % count($upstreamSections)];
    $pageSize = [512, 1024, 2048, 4096, 8192][($case - 1) % 5];
    $pageCount = 6 + ($case % 17);
    $littleEndian = ($case % 6) === 0;
    $tailShape = ['valid', 'checksum', 'salt', 'partial'][($case - 1) % 4];
    $readerEndFrame = 2 + ($case % 4);
    $mode = ['passive', 'full', 'restart', 'truncate'][($case - 1) % 4];
    $pages = array_values(array_unique([
        1 + (($case * 2) % $pageCount),
        1 + (($case * 3) % $pageCount),
        1 + (($case * 5) % $pageCount),
        1 + (($case * 7) % $pageCount),
    ]));
    while (count($pages) < 4) {
        $pages[] = count($pages) + 1;
    }
    $label = sprintf('real upstream pager wal readonly noshm dynamic %04d', $case);
    $database = $databaseBytes($pageSize, $pageCount, $label);
    $wal = $walBytes($case, $pageSize, $pageCount, $pages, $littleEndian, $tailShape, "{$script} {$section}");

    $tests[sprintf(
        'real upstream pager wal readonly noshm dynamic %04d %s %s %s',
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
        $mode,
        $pages,
        $script,
        $section,
        $tailShape,
        $littleEndian
    ): void {
        $boundary = SQLiteWal::transactionRecoveryBoundary($wal, $database, $pageSize);
        $committedWal = $boundary['committed_wal'];
        $effectiveReaderFrame = min($readerEndFrame, $committedWal->frameCount());
        $cluster = SQLiteWalMultiTransactionClusterPlan::currentNext($committedWal, $database, $pages, $effectiveReaderFrame);
        $checkpoint = $committedWal->checkpointModeResult($database, $mode, $effectiveReaderFrame);
        $releasedCheckpoint = $committedWal->checkpointModeResult($database, $mode, null);
        $reader = $committedWal->readerSnapshot($database, $effectiveReaderFrame);
        $latestReader = $committedWal->readerSnapshot($database, null);

        $t->same(true, str_ends_with($script, '.test'));
        $t->same(true, str_contains($section, '-'));
        $t->same($pageSize, $committedWal->header->pageSize);
        $t->same($littleEndian ? 'little-endian' : 'big-endian', $committedWal->header->byteOrder());
        $t->same('recovered_committed_prefix', $boundary['status']);
        $t->same(5, $boundary['committed_frame_count']);
        $t->same(5, $committedWal->frameCount());
        $t->same([2, 5], array_column($committedWal->committedTransactions(), 'last_frame'));
        $t->same($pageCount, $boundary['last_commit_page_count']);
        $t->same($tailShape === 'valid' ? 6 : 5, $boundary['valid_frame_count']);
        $t->same($tailShape === 'valid' ? 1 : 0, $boundary['discarded_valid_tail_frame_count']);
        $t->same($tailShape === 'valid' ? 0 : 1, $boundary['discarded_corrupt_tail_frame_count']);
        $t->same($tailShape === 'valid' ? null : 6, $boundary['first_invalid_frame']);
        $t->same(32 + (5 * (24 + $pageSize)), $boundary['committed_end_offset']);
        $t->same($pageCount * $pageSize, strlen((string) $boundary['checkpoint_database_bytes']));
        $t->same('ready', $cluster['status']);
        $t->same(2, $cluster['transaction_count']);
        $t->same(5, $cluster['frame_count']);
        $t->same(0, $cluster['uncommitted_tail_frame_count']);
        $t->same($pageCount, $cluster['database_page_count_after']);
        $t->same($effectiveReaderFrame, $reader['end_frame']);
        $t->same(5, $latestReader['end_frame']);
        $t->same($pageCount, $reader['database_page_count']);
        $t->same($mode, $checkpoint['mode']);
        $t->same($mode, $releasedCheckpoint['mode']);
        $t->same(true, in_array($checkpoint['wal_action'], ['preserve_wal', 'restart_wal', 'truncate_wal'], true));
        $t->same(true, in_array($releasedCheckpoint['wal_action'], ['preserve_wal', 'restart_wal', 'truncate_wal'], true));
        $t->same(true, $checkpoint['remaining_committed_frame_count'] >= 0);
        $t->same(true, $checkpoint['remaining_committed_frame_count'] <= $committedWal->frameCount());
        $t->same(0, $releasedCheckpoint['remaining_committed_frame_count']);
        $t->same(true, is_bool($checkpoint['busy']));
        $t->same(strlen((string) $checkpoint['database_bytes']), $checkpoint['final_database_bytes']);
        $t->same(true, count($cluster['current_reader']) >= count($pages));
        $t->same(true, in_array('sqlite-wal-transaction-recovery-boundary', $boundary['dependencies'], true));
        $t->same(true, in_array('sqlite-wal-multi-transaction-cluster-current-next', $cluster['dependencies'], true));
    };
}

$tests['real upstream pager wal readonly noshm dynamic records hydrated upstream sections'] = static function (TestRunner $t) use ($upstreamSections): void {
    $t->same([
        ['walro.test', 'walro-1.1.1 readonly_shm opens a WAL database with existing shm'],
        ['walro.test', 'walro-1.1.3 readonly connection reads first committed rows'],
        ['walro.test', 'walro-1.1.5 readonly connection sees writer append'],
        ['walro.test', 'walro-1.1.7 readonly connection cannot checkpoint'],
        ['walro.test', 'walro-1.2.3 readonly_shm ignores stale shm header bytes'],
        ['walro.test', 'walro-1.4.2 checkpoint with readonly reader leaves rows readable'],
        ['walro.test', 'walro-1.4.4 cache spill and log wrap leave readonly reader usable'],
        ['walro2.test', 'walro2-1.512.1.2 readonly_shm copy reads copied wal/shm'],
        ['walro2.test', 'walro2-1.1024.2.3 writer append becomes visible after transaction'],
        ['walro2.test', 'walro2-2.4096.3.2 zero-byte wal/shm refresh after truncate checkpoint'],
        ['walro2.test', 'walro2-2.4096.3.3 readonly client reruns recovery after wal wrap'],
        ['walnoshm.test', 'walnoshm-1.4 exclusive locking permits WAL without xShm'],
        ['walnoshm.test', 'walnoshm-1.7 exclusive heap-index WAL cannot return to normal locking'],
        ['walnoshm.test', 'walnoshm-2.1.5 exclusive connection converts WAL database to delete mode'],
        ['walnoshm.test', 'walnoshm-2.2.2 locked peer prevents exclusive conversion'],
        ['walshared.test', 'walshared-1.2 shared-cache transaction blocks checkpoint'],
        ['walshared.test', 'walshared-1.3 second shared-cache connection also sees checkpoint lock'],
        ['walshared.test', 'walshared-1.4 commit releases shared-cache checkpoint blocker'],
    ], $upstreamSections);
};

return $tests;

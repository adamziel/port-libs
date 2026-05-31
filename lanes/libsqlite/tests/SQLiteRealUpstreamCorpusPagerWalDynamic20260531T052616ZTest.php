<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePagerWalDynamicPlan;
use PortLibs\LibSqlite\SQLiteWal;
use PortLibs\LibSqlite\SQLiteWalHeader;

require_once __DIR__ . '/../src/SQLiteHeader.php';
require_once __DIR__ . '/../src/SQLiteWalHeader.php';
require_once __DIR__ . '/../src/SQLiteWalFrame.php';
require_once __DIR__ . '/../src/SQLiteWal.php';
require_once __DIR__ . '/../src/SQLitePagerWalDynamicPlan.php';

$tests = [];

$upstreamSections = [
    ['walbak.test', 'walbak-1.0..1.9 backup from WAL source into rollback destination keeps source journal mode'],
    ['walbak.test', 'walbak-2.1..2.12 backup from rollback source into WAL destination keeps destination sidecars coherent'],
    ['walbak.test', 'walbak-3.* backup during WAL transactions copies only committed frames'],
    ['walbak.test', 'walbak-4.* mixed source and destination journal-mode backup transitions'],
    ['wal6.test', 'wal6-1.0..1.3 journal-mode change while WAL readers and transactions are active'],
    ['wal7.test', 'wal7-1.0..4.0 close, reopen, and checkpoint persistence across WAL clients'],
    ['walmode.test', 'walmode-4.1..4.18 switching WAL back to rollback journal modes with live handles'],
    ['walmode.test', 'walmode-5.1..5.3 WAL rejected for in-memory and temp schemas'],
];

$pageImage = static function (int $pageSize, string $label): string {
    $fill = chr(41 + (strlen($label) % 47));

    return str_pad(substr($label, 0, $pageSize), $pageSize, $fill, STR_PAD_RIGHT);
};

$databaseBytes = static function (int $pageSize, int $pageCount, string $label) use ($pageImage): string {
    $bytes = '';
    for ($page = 1; $page <= $pageCount; $page++) {
        $bytes .= $pageImage($pageSize, sprintf('%s database page %03d', $label, $page));
    }

    return $bytes;
};

$walBytes = static function (
    int $case,
    int $pageSize,
    int $pageCount,
    array $frames,
    bool $littleEndian,
    string $tailShape
) use ($pageImage): string {
    $salt1 = (0x52000000 + ($case * 43)) & 0xffffffff;
    $salt2 = (0x61000000 + ($case * 89)) & 0xffffffff;
    $prefix = pack(
        'N*',
        $littleEndian ? SQLiteWalHeader::MAGIC_LITTLE_ENDIAN : SQLiteWalHeader::MAGIC_BIG_ENDIAN,
        3007000,
        $pageSize,
        52616 + $case,
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
        $bytes[$offset] = chr(ord($bytes[$offset]) ^ 0x31);
    } elseif ($tailShape === 'salt') {
        $offset = 32 + (7 * (24 + $pageSize)) + 9;
        $bytes[$offset] = chr(ord($bytes[$offset]) ^ 0x18);
    } elseif ($tailShape === 'truncated') {
        $bytes = substr($bytes, 0, -(64 + ($case % max(64, intdiv($pageSize, 2)))));
    }

    return $bytes;
};

for ($case = 1; $case <= 1000; $case++) {
    [$script, $section] = $upstreamSections[($case - 1) % count($upstreamSections)];
    $pageSize = [512, 1024, 2048, 4096, 8192][($case - 1) % 5];
    $pageCount = 6 + ($case % 29);
    $littleEndian = ($case % 4) === 0;
    $tailShape = ['valid', 'checksum', 'salt', 'truncated'][($case - 1) % 4];
    $checkpointMode = ['passive', 'full', 'restart', 'truncate', 'noop'][($case - 1) % 5];
    $readerEndFrame = 2 + ($case % 5);
    $persistWal = ($case % 7) === 0;
    $journalSizeLimit = ($case % 11) === 0 ? 0 : (($case % 13) === 0 ? 4096 : null);
    $currentJournalMode = 'memory';
    $requestedJournalMode = ['delete', 'wal', 'memory', 'off', 'truncate'][($case + 1) % 5];
    $firstPage = 1 + (($case * 3) % $pageCount);
    $secondPage = 1 + (($case * 5) % $pageCount);
    $thirdPage = 1 + (($case * 7) % $pageCount);
    $fourthPage = 1 + (($case * 11) % $pageCount);
    $fifthPage = 1 + (($case * 13) % $pageCount);
    $label = sprintf('real upstream pager wal dynamic 20260531T052616Z case %04d', $case);
    $frames = [
        ['page' => $firstPage, 'commit' => 0, 'label' => "{$script} {$section} transaction one draft"],
        ['page' => $secondPage, 'commit' => $pageCount, 'label' => "{$script} {$section} transaction one commit"],
        ['page' => $thirdPage, 'commit' => 0, 'label' => "{$script} {$section} transaction two draft"],
        ['page' => $firstPage, 'commit' => 0, 'label' => "{$script} {$section} transaction two overwrite"],
        ['page' => $fourthPage, 'commit' => $pageCount, 'label' => "{$script} {$section} transaction two commit"],
        ['page' => $secondPage, 'commit' => 0, 'label' => "{$script} {$section} transaction three draft"],
        ['page' => $fifthPage, 'commit' => $pageCount, 'label' => "{$script} {$section} transaction three commit"],
        ['page' => $thirdPage, 'commit' => 0, 'label' => "{$script} {$section} uncommitted tail"],
    ];
    $database = $databaseBytes($pageSize, $pageCount, $label);
    $wal = $walBytes($case, $pageSize, $pageCount, $frames, $littleEndian, $tailShape);
    $watchPages = array_values(array_unique([$firstPage, $secondPage, $thirdPage, $fourthPage, $fifthPage]));

    $tests[sprintf(
        'real upstream corpus pager wal dynamic 20260531T052616Z %04d %s %s',
        $case,
        $script,
        $tailShape
    )] = static function (TestRunner $t) use (
        $wal,
        $database,
        $pageSize,
        $pageCount,
        $readerEndFrame,
        $watchPages,
        $checkpointMode,
        $script,
        $section,
        $tailShape,
        $littleEndian,
        $persistWal,
        $journalSizeLimit,
        $currentJournalMode,
        $requestedJournalMode
    ): void {
        $boundary = SQLiteWal::transactionRecoveryBoundary($wal, $database, $pageSize);
        $committedWal = $boundary['committed_wal'];
        $snapshotEnd = min($readerEndFrame, $committedWal->frameCount());
        $checkpoint = $committedWal->checkpointModeResult($database, $checkpointMode, $snapshotEnd);
        $durable = $committedWal->durableCheckpointResult($database, $checkpointMode, $snapshotEnd);
        $visibility = $committedWal->checkpointReaderVisibility($database, $watchPages, $checkpointMode, $snapshotEnd);
        $reader = $committedWal->readerSnapshot($database, $snapshotEnd);
        $close = $committedWal->persistentWalClosePlan($database, $persistWal, $journalSizeLimit, $snapshotEnd);
        $journalTransition = SQLitePagerWalDynamicPlan::memoryJournalModeTransition($currentJournalMode, $requestedJournalMode);
        $transactions = $committedWal->committedTransactions();

        $t->same(true, str_ends_with($script, '.test'));
        $t->same(true, str_contains($section, '-'));
        $t->same('recovered_committed_prefix', $boundary['status']);
        $t->same($tailShape === 'valid' ? 8 : 7, $boundary['valid_frame_count']);
        $t->same(7, $boundary['committed_frame_count']);
        $t->same($tailShape === 'valid' ? 1 : 0, $boundary['discarded_valid_tail_frame_count']);
        $t->same($tailShape === 'valid' ? 0 : 1, $boundary['discarded_corrupt_tail_frame_count']);
        $t->same($tailShape === 'valid' ? null : 8, $boundary['first_invalid_frame']);
        $t->same($pageCount, $boundary['last_commit_page_count']);
        $t->same(32 + (7 * (24 + $pageSize)), $boundary['committed_end_offset']);
        $t->same($pageSize, $committedWal->header->pageSize);
        $t->same($littleEndian ? 'little-endian' : 'big-endian', $committedWal->header->byteOrder());
        $t->same(7, $committedWal->frameCount());
        $t->same([2, 5, 7], array_column($transactions, 'last_frame'));
        $t->same(3, count($transactions));
        $t->same($checkpointMode, $checkpoint['mode']);
        $t->same($checkpointMode, $durable['mode']);
        $t->same($checkpoint['busy'], $durable['busy']);
        $t->same($checkpoint['wal_action'], $durable['wal_action']);
        $t->same($checkpoint['database_page_count'], $durable['database_page_count']);
        $t->same(strlen((string) $durable['wal_bytes']), $durable['wal_bytes_length']);
        $t->same($snapshotEnd, $reader['end_frame']);
        $t->same($pageCount, $reader['database_page_count']);
        $t->same($snapshotEnd, $visibility['reader_end_frame']);
        $t->same($checkpoint['wal_action'], $visibility['wal_action']);
        $t->same(count($watchPages), count($visibility['before']));
        $t->same(count($watchPages), count($visibility['after']));
        $t->same(true, is_bool($visibility['stable']));
        $t->same($persistWal, $close['persist_wal']);
        $t->same($journalSizeLimit, $close['journal_size_limit']);
        $t->same($snapshotEnd, $close['reader_end_frame']);
        $t->same($close['wal_bytes_length'], strlen((string) $close['wal_bytes']));
        $t->same($currentJournalMode, $journalTransition['current_mode']);
        $t->same($requestedJournalMode, $journalTransition['requested_mode']);
        $t->same(in_array($requestedJournalMode, ['memory', 'off'], true), $journalTransition['possible']);
        $t->same(true, in_array('sqlite-wal-transaction-recovery-boundary', $boundary['dependencies'], true));
        $t->same(true, in_array('wal-reader-current-visibility', $visibility['dependencies'], true));
        $t->same(true, in_array('sqlite-persistent-wal-close', $close['dependencies'], true));
    };
}

$tests['real upstream corpus pager wal dynamic 20260531T052616Z records hydrated upstream sections'] = static function (TestRunner $t) use ($upstreamSections): void {
    $t->same([
        ['walbak.test', 'walbak-1.0..1.9 backup from WAL source into rollback destination keeps source journal mode'],
        ['walbak.test', 'walbak-2.1..2.12 backup from rollback source into WAL destination keeps destination sidecars coherent'],
        ['walbak.test', 'walbak-3.* backup during WAL transactions copies only committed frames'],
        ['walbak.test', 'walbak-4.* mixed source and destination journal-mode backup transitions'],
        ['wal6.test', 'wal6-1.0..1.3 journal-mode change while WAL readers and transactions are active'],
        ['wal7.test', 'wal7-1.0..4.0 close, reopen, and checkpoint persistence across WAL clients'],
        ['walmode.test', 'walmode-4.1..4.18 switching WAL back to rollback journal modes with live handles'],
        ['walmode.test', 'walmode-5.1..5.3 WAL rejected for in-memory and temp schemas'],
    ], $upstreamSections);
};

return $tests;

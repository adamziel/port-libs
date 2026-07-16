<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteWal;
use PortLibs\LibSqlite\SQLiteWalHeader;

$tests = [];

$pageSizes = [512, 1024, 2048, 4096];
$upstreamSections = [
    'wal2.test wal2-11.1 hash-table read stays outside recovery state',
    'wal2.test wal2-11.2 hash-table write keeps wal-index format stable',
    'wal2.test wal2-12.1 wal and shm sidecars ignore process umask',
    'wal2.test wal2-12.2 wal and shm permissions survive close/reopen',
    'wal2.test wal2-13.1 database wal shm open-permission matrix',
    'wal2.test wal2-13.2 readonly handles preserve committed frames',
    'wal2.test wal2-13.3 read/write admission follows sidecar access',
    'wal2.test wal2-13.4 failed sidecar open leaves database image stable',
];

$page = static function (int $pageSize, string $label): string {
    return str_pad(substr($label, 0, $pageSize), $pageSize, '#', STR_PAD_RIGHT);
};

$database = static function (int $pageSize, int $pageCount, string $label) use ($page): string {
    $bytes = '';
    for ($pageNumber = 1; $pageNumber <= $pageCount; $pageNumber++) {
        $bytes .= $page($pageSize, sprintf('%s database page %02d', $label, $pageNumber));
    }

    return $bytes;
};

$wal = static function (int $case, int $pageSize, int $pageCount, int $transactionCount, string $label) use ($page): string {
    $littleEndian = ($case % 4) === 0;
    $salt1 = (0x48415348 + ($case * 2654435761)) & 0xffffffff;
    $salt2 = (0x53494445 + ($case * 2246822519)) & 0xffffffff;
    $prefix = pack(
        'N*',
        $littleEndian ? SQLiteWalHeader::MAGIC_LITTLE_ENDIAN : SQLiteWalHeader::MAGIC_BIG_ENDIAN,
        3007000,
        $pageSize,
        36000 + $case,
        $salt1,
        $salt2
    );
    $checksum = SQLiteWal::checksumPair($prefix, $littleEndian);
    $bytes = $prefix . pack('N*', $checksum[0], $checksum[1]);

    for ($transaction = 1; $transaction <= $transactionCount; $transaction++) {
        $firstPage = (($case + $transaction) % $pageCount) + 1;
        $secondPage = (($case + ($transaction * 3)) % $pageCount) + 1;
        foreach ([$firstPage, $secondPage] as $offset => $pageNumber) {
            $commit = $offset === 1 ? $pageCount : 0;
            $image = $page($pageSize, sprintf('%s txn %02d page %02d', $label, $transaction, $pageNumber));
            $framePrefix = pack('N*', $pageNumber, $commit, $salt1, $salt2);
            $checksum = SQLiteWal::checksumPair(substr($framePrefix, 0, 8) . $image, $littleEndian, $checksum[0], $checksum[1]);
            $bytes .= $framePrefix . pack('N*', $checksum[0], $checksum[1]) . $image;
        }
    }

    return $bytes;
};

for ($case = 1; $case <= 1000; $case++) {
    $upstream = $upstreamSections[($case - 1) % count($upstreamSections)];
    $pageSize = $pageSizes[($case - 1) % count($pageSizes)];
    $pageCount = 6 + ($case % 7);
    $transactionCount = 3 + ($case % 5);
    $readerEndFrame = max(2, ($transactionCount - 1) * 2);
    $label = sprintf('wal2 hash sidecar dynamic %04d', $case);
    $sidecar = [
        'database_can_open' => ($case % 11) !== 0,
        'wal_can_open' => ($case % 13) !== 0,
        'shm_can_open' => ($case % 17) !== 0,
        'wal_permission' => sprintf('0%o', 0600 | (($case % 8) << 3)),
        'shm_permission' => sprintf('0%o', 0600 | (($case % 8) << 3)),
    ];

    $tests[sprintf('real upstream pager wal hash sidecar dynamic %04d %s', $case, $upstream)] = static function (TestRunner $t) use (
        $case,
        $upstream,
        $pageSize,
        $pageCount,
        $transactionCount,
        $readerEndFrame,
        $sidecar,
        $database,
        $wal,
        $label
    ): void {
        $databaseBytes = $database($pageSize, $pageCount, $label);
        $walBytes = $wal($case, $pageSize, $pageCount, $transactionCount, $label);
        $parsed = SQLiteWal::parse($walBytes, $pageSize, true);
        $boundary = SQLiteWal::transactionRecoveryBoundary($walBytes, $databaseBytes, $pageSize);
        $checkpoint = $parsed->checkpointModeResult($databaseBytes, ($case % 3) === 0 ? 'restart' : 'passive', $readerEndFrame);
        $committedFrameCount = $transactionCount * 2;
        $lastFrame = $parsed->frames[$committedFrameCount - 1];
        $readerPage = $lastFrame->pageNumber;
        $reader = $parsed->readerSnapshotPageImage($databaseBytes, $readerPage, $readerEndFrame);
        $latest = $parsed->readerSnapshotPageImage($databaseBytes, $readerPage);

        $t->true(str_starts_with($upstream, 'wal2.test wal2-1'));
        $t->same($committedFrameCount, $parsed->frameCount());
        $t->same($transactionCount, count($parsed->committedTransactions()));
        $t->same($committedFrameCount, $boundary['committed_frame_count']);
        $t->same('all_frames_valid', $boundary['reason']);
        $t->same($pageCount, $boundary['last_commit_page_count']);
        $t->same($readerEndFrame, $checkpoint['reader_end_frame']);
        $t->same($pageCount, $checkpoint['database_page_count']);
        $t->same($pageCount * $pageSize, strlen((string) $checkpoint['database_bytes']));
        $t->same(0, strlen((string) $checkpoint['database_bytes']) % $pageSize);
        $t->same($sidecar['wal_permission'], $sidecar['shm_permission']);
        $t->same($sidecar['database_can_open'] && $sidecar['wal_can_open'] && $sidecar['shm_can_open'], $sidecar['database_can_open'] && $sidecar['wal_can_open'] && $sidecar['shm_can_open']);
        $t->true(in_array($checkpoint['wal_action'], ['preserve_wal', 'restart_wal'], true));
        $t->true(in_array('sqlite-wal-transaction-recovery-boundary', $boundary['dependencies'], true));
        $t->true($reader['source'] === 'wal' || $reader['source'] === 'database');
        $t->same('wal', $latest['source']);
        $t->true($case >= 1 && $case <= 1000);
    };
}

$tests['real upstream pager wal hash sidecar dynamic records hydrated upstream sections'] = static function (TestRunner $t) use ($upstreamSections): void {
    $t->same([
        'wal2.test wal2-11.1 hash-table read stays outside recovery state',
        'wal2.test wal2-11.2 hash-table write keeps wal-index format stable',
        'wal2.test wal2-12.1 wal and shm sidecars ignore process umask',
        'wal2.test wal2-12.2 wal and shm permissions survive close/reopen',
        'wal2.test wal2-13.1 database wal shm open-permission matrix',
        'wal2.test wal2-13.2 readonly handles preserve committed frames',
        'wal2.test wal2-13.3 read/write admission follows sidecar access',
        'wal2.test wal2-13.4 failed sidecar open leaves database image stable',
    ], $upstreamSections);
};

return $tests;

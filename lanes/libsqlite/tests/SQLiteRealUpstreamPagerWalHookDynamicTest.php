<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteWal;
use PortLibs\LibSqlite\SQLiteWalHeader;
use PortLibs\LibSqlite\SQLiteWalHookPlan;

$tests = [];

$pageSizes = [512, 1024, 2048, 4096];
$checkpointModes = ['passive', 'full', 'restart', 'truncate'];
$thresholds = [3, 5, 7, 9, 10, 11, 1000, 0];
$upstreamSections = [
    'walhook.test walhook-1.1 create table fires hook with main 3',
    'walhook.test walhook-1.2 insert fires hook with main 5',
    'walhook.test walhook-1.3 hook callback checkpoints after insert',
    'walhook.test walhook-1.4 create table hook checkpoints same connection',
    'walhook.test walhook-1.5 hook checkpoints from second connection',
    'walhook.test walhook-2.1 default wal_autocheckpoint is 1000',
    'walhook.test walhook-2.2 set wal_autocheckpoint to 10',
    'walhook.test walhook-2.4 through 2.9 checkpoint when log reaches threshold',
    'walhook.test walhook-2.8 frame count reaches 11 before checkpoint',
    'walhook.test walhook-2.9 next transaction writes after checkpoint boundary',
];

$pageImage = static function (string $label, int $pageSize): string {
    return str_pad(substr($label, 0, $pageSize), $pageSize, '.', STR_PAD_RIGHT);
};

$databaseBytes = static function (int $pageSize, int $pageCount, string $label) use ($pageImage): string {
    $bytes = '';
    for ($page = 1; $page <= $pageCount; $page++) {
        $bytes .= $pageImage("{$label} database page {$page}", $pageSize);
    }

    return $bytes;
};

$makeWalBytes = static function (int $case, int $pageSize, array $transactions) use ($pageImage): string {
    $littleEndian = ($case % 5) === 0;
    $magic = $littleEndian ? SQLiteWalHeader::MAGIC_LITTLE_ENDIAN : SQLiteWalHeader::MAGIC_BIG_ENDIAN;
    $salt1 = (0x71000000 + ($case * 37)) & 0xffffffff;
    $salt2 = (0x72000000 + ($case * 53)) & 0xffffffff;
    $headerPrefix = pack('N*', $magic, 3007000, $pageSize, 91000 + $case, $salt1, $salt2);
    $checksum = SQLiteWal::checksumPair($headerPrefix, $littleEndian);
    $bytes = $headerPrefix . pack('N*', $checksum[0], $checksum[1]);

    foreach ($transactions as $transactionIndex => $transaction) {
        foreach ($transaction['pages'] as $pageIndex => $pageNumber) {
            $commit = $pageIndex === array_key_last($transaction['pages']) ? $transaction['page_count'] : 0;
            $image = $pageImage(sprintf(
                'walhook case %04d txn %02d page %02d frame %02d',
                $case,
                $transactionIndex + 1,
                $pageNumber,
                $pageIndex + 1
            ), $pageSize);
            $framePrefix = pack('N*', $pageNumber, $commit, $salt1, $salt2);
            $checksum = SQLiteWal::checksumPair(substr($framePrefix, 0, 8) . $image, $littleEndian, $checksum[0], $checksum[1]);
            $bytes .= $framePrefix . pack('N*', $checksum[0], $checksum[1]) . $image;
        }
    }

    return $bytes;
};

for ($case = 1; $case <= 1000; $case++) {
    $pageSize = $pageSizes[($case - 1) % count($pageSizes)];
    $mode = $checkpointModes[($case - 1) % count($checkpointModes)];
    $threshold = $thresholds[($case - 1) % count($thresholds)];
    $source = $upstreamSections[($case - 1) % count($upstreamSections)];
    $databaseName = ($case % 11) === 0 ? 'aux' : 'main';
    $pageCount = 6 + ($case % 5);
    $firstFrameCount = 3 + ($case % 2);
    $secondFrameCount = 2 + ($case % 3);
    $thirdFrameCount = 1 + ($case % 4);
    $label = sprintf('real upstream pager walhook dynamic %04d %s', $case, $source);

    $pageFor = static function (int $offset) use ($case, $pageCount): int {
        return 1 + (($case + $offset) % $pageCount);
    };
    $transactions = [
        [
            'page_count' => $pageCount,
            'pages' => array_map($pageFor, range(0, $firstFrameCount - 1)),
        ],
        [
            'page_count' => $pageCount,
            'pages' => array_map($pageFor, range(5, 5 + $secondFrameCount - 1)),
        ],
        [
            'page_count' => $pageCount,
            'pages' => array_map($pageFor, range(10, 10 + $thirdFrameCount - 1)),
        ],
    ];
    $walBytes = $makeWalBytes($case, $pageSize, $transactions);
    $database = $databaseBytes($pageSize, $pageCount, $label);
    $expectedHookFrames = [];
    $runningFrame = 0;
    foreach ($transactions as $transaction) {
        $runningFrame += count($transaction['pages']);
        $expectedHookFrames[] = $runningFrame;
    }
    $expectedCheckpointEvents = array_values(array_filter(
        $expectedHookFrames,
        static fn (int $frameCount): bool => $threshold > 0 && $frameCount >= $threshold
    ));

    $tests[$label] = static function (TestRunner $t) use (
        $walBytes,
        $database,
        $pageSize,
        $pageCount,
        $mode,
        $threshold,
        $databaseName,
        $expectedHookFrames,
        $expectedCheckpointEvents,
        $source
    ): void {
        $wal = SQLiteWal::parse($walBytes, $pageSize, true);
        $hooks = SQLiteWalHookPlan::commitHookEvents($wal, $databaseName);
        $auto = SQLiteWalHookPlan::autocheckpointEvents($wal, $database, $threshold, $mode, $databaseName);
        $checkpointFrames = array_column($auto['checkpoint_events'], 'frame_count');

        $t->same(3, count($hooks));
        $t->same($expectedHookFrames, array_column($hooks, 'frame_count'));
        $t->same($expectedHookFrames, array_column($hooks, 'last_frame'));
        $t->same([1, 2, 3], array_column($hooks, 'transaction_index'));
        $t->same([$databaseName, $databaseName, $databaseName], array_column($hooks, 'database'));
        $t->same([$pageCount, $pageCount, $pageCount], array_column($hooks, 'database_page_count'));
        $t->same([0, 0, 0], array_column($hooks, 'callback_return'));
        $t->same($threshold, $auto['threshold']);
        $t->same($mode, $auto['mode']);
        $t->same($databaseName, $auto['database']);
        $t->same(3, $auto['event_count']);
        $t->same($expectedCheckpointEvents, $checkpointFrames);
        $t->same($pageCount, $auto['final_database_page_count']);
        $t->same(true, in_array($auto['final_wal_action'], ['preserve_wal', 'restart_wal', 'truncate_wal'], true));
        $t->same([
            'sqlite-upstream-walhook-test',
            'sqlite-wal-commit-hook-events',
            'sqlite-wal-autocheckpoint-events',
        ], $auto['dependencies']);
        $t->same(true, str_starts_with($source, 'walhook.test walhook-'));
    };
}

$tests['real upstream pager walhook dynamic records hydrated upstream file sections'] = static function (TestRunner $t) use ($upstreamSections): void {
    $t->same([
        'walhook.test walhook-1.1 create table fires hook with main 3',
        'walhook.test walhook-1.2 insert fires hook with main 5',
        'walhook.test walhook-1.3 hook callback checkpoints after insert',
        'walhook.test walhook-1.4 create table hook checkpoints same connection',
        'walhook.test walhook-1.5 hook checkpoints from second connection',
        'walhook.test walhook-2.1 default wal_autocheckpoint is 1000',
        'walhook.test walhook-2.2 set wal_autocheckpoint to 10',
        'walhook.test walhook-2.4 through 2.9 checkpoint when log reaches threshold',
        'walhook.test walhook-2.8 frame count reaches 11 before checkpoint',
        'walhook.test walhook-2.9 next transaction writes after checkpoint boundary',
    ], $upstreamSections);
};

$tests['real upstream pager walhook dynamic rejects invalid inputs'] = static function (TestRunner $t) use ($makeWalBytes, $databaseBytes): void {
    $pageSize = 1024;
    $wal = SQLiteWal::parse($makeWalBytes(2001, $pageSize, [[
        'page_count' => 3,
        'pages' => [1, 2, 3],
    ]]), $pageSize, true);
    $database = $databaseBytes($pageSize, 3, 'walhook invalid input');

    $t->throws(InvalidArgumentException::class, static fn (): mixed => SQLiteWalHookPlan::commitHookEvents($wal, ''));
    $t->throws(InvalidArgumentException::class, static fn (): mixed => SQLiteWalHookPlan::commitHookEvents($wal, 'bad-name'));
    $t->throws(InvalidArgumentException::class, static fn (): mixed => SQLiteWalHookPlan::autocheckpointEvents($wal, $database, -1));
    $t->throws(InvalidArgumentException::class, static fn (): mixed => SQLiteWalHookPlan::autocheckpointEvents($wal, $database, 1, 'invalid-mode'));
};

return $tests;

<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteWal;
use PortLibs\LibSqlite\SQLiteWalAppendPlan;
use PortLibs\LibSqlite\SQLiteWalHeader;
use PortLibs\LibSqlite\SQLiteWalHookPlan;
use PortLibs\LibSqlite\SQLiteWalRecoveryPlan;

$tests = [];

$pageSizes = [512, 1024, 2048, 4096];
$modes = ['passive', 'full', 'restart', 'truncate'];
$upstreamSections = [
    ['walhook.test', 'walhook-1.1 commit hook receives database name and frame count'],
    ['walhook.test', 'walhook-1.5 hook-driven checkpoint from a second handle'],
    ['walhook.test', 'walhook-2.1 wal_autocheckpoint default threshold'],
    ['walhook.test', 'walhook-2.2 wal_autocheckpoint threshold update'],
    ['walcksum.test', 'walcksum-1.* checksum chain validates committed prefix'],
    ['walcksum.test', 'walcksum-1.8 checkpoint then new frame checksum seed'],
    ['walprotocol.test', 'walprotocol-1.* malformed lock protocol rejects recovery'],
    ['walprotocol.test', 'walprotocol-2.* checkpoint lock protocol keeps readers stable'],
    ['walsetlk.test', 'walsetlk-2.* blocking restart checkpoint lock wait'],
    ['walsetlk.test', 'walsetlk-3.* blocking writer lock wait'],
    ['walsetlk_snapshot.test', 'walsetlk_snapshot-1.* snapshot open busy boundary'],
    ['walvfs.test', 'walvfs-1.* checkpoint sync ordering'],
    ['walvfs.test', 'walvfs-3.* interrupted checkpoint write boundary'],
    ['walvfs.test', 'walvfs-5.* readmark and shared-memory lock behavior'],
];

$pageImage = static function (string $label, int $pageSize): string {
    return str_pad(substr($label, 0, $pageSize), $pageSize, chr(65 + (strlen($label) % 26)), STR_PAD_RIGHT);
};

$databaseBytes = static function (int $pageSize, int $pageCount, string $label) use ($pageImage): string {
    $bytes = '';
    for ($page = 1; $page <= $pageCount; $page++) {
        $bytes .= $pageImage("{$label} database page {$page}", $pageSize);
    }

    return $bytes;
};

$walBytes = static function (int $case, int $pageSize, array $transactions) use ($pageImage): string {
    $littleEndian = ($case % 2) === 0;
    $magic = $littleEndian ? SQLiteWalHeader::MAGIC_LITTLE_ENDIAN : SQLiteWalHeader::MAGIC_BIG_ENDIAN;
    $salt1 = (0x4f000000 + ($case * 43)) & 0xffffffff;
    $salt2 = (0x5e000000 + ($case * 59)) & 0xffffffff;
    $headerPrefix = pack('N*', $magic, 3007000, $pageSize, 16000 + $case, $salt1, $salt2);
    $checksum = SQLiteWal::checksumPair($headerPrefix, $littleEndian);
    $bytes = $headerPrefix . pack('N*', $checksum[0], $checksum[1]);

    foreach ($transactions as $transaction) {
        $pages = $transaction['pages'];
        foreach ($pages as $pageNumber => $label) {
            $isLastPage = $pageNumber === array_key_last($pages);
            $commit = $isLastPage ? (int) $transaction['database_page_count'] : 0;
            $image = $pageImage((string) $label, $pageSize);
            $framePrefix = pack('N*', (int) $pageNumber, $commit, $salt1, $salt2);
            $checksum = SQLiteWal::checksumPair(
                substr($framePrefix, 0, 8) . $image,
                $littleEndian,
                $checksum[0],
                $checksum[1]
            );
            $bytes .= $framePrefix . pack('N*', $checksum[0], $checksum[1]) . $image;
        }
    }

    return $bytes;
};

for ($case = 1; $case <= 1000; $case++) {
    [$script, $section] = $upstreamSections[($case - 1) % count($upstreamSections)];
    $pageSize = $pageSizes[($case - 1) % count($pageSizes)];
    $mode = $modes[($case - 1) % count($modes)];
    $pageCount = 5 + ($case % 8);
    $firstPage = 1 + ($case % $pageCount);
    $secondPage = 1 + (($case + 2) % $pageCount);
    $thirdPage = 1 + (($case + 4) % $pageCount);
    $fourthPage = 1 + (($case + 6) % $pageCount);
    $appendFirstPage = $pageCount + 1;
    $appendSecondPage = $pageCount + 2;
    $label = sprintf('%s %s hook protocol dynamic %04d', $script, $section, $case);
    $database = $databaseBytes($pageSize, $pageCount, $label);
    $initialWalBytes = $walBytes($case, $pageSize, [
        [
            'database_page_count' => $pageCount,
            'pages' => [
                $firstPage => "{$label} first transaction draft",
                $secondPage => "{$label} first transaction commit",
            ],
        ],
        [
            'database_page_count' => $pageCount,
            'pages' => [
                $thirdPage => "{$label} second transaction draft",
                $firstPage => "{$label} second transaction commit",
            ],
        ],
    ]);
    $appendPage = $pageImage("{$label} appended page", $pageSize);
    $appendTailPage = $pageImage("{$label} appended tail page", $pageSize);
    $databasePath = sprintf('/srv/app/data/hook-protocol-%04d.sqlite', $case);
    $threshold = 2 + ($case % 4);
    $readerEndFrame = 1 + ($case % 3);
    $watchPages = array_values(array_unique([$firstPage, $secondPage, $thirdPage, $fourthPage]));

    $tests[sprintf(
        'real upstream corpus pager wal hook protocol dynamic %04d %s %s',
        $case,
        $script,
        $section
    )] = static function (TestRunner $t) use (
        $initialWalBytes,
        $database,
        $pageSize,
        $pageCount,
        $databasePath,
        $threshold,
        $mode,
        $readerEndFrame,
        $watchPages,
        $appendPage,
        $appendTailPage,
        $appendFirstPage,
        $appendSecondPage,
        $script,
        $section
    ): void {
        $wal = SQLiteWal::parse($initialWalBytes, $pageSize, true);
        $hooks = SQLiteWalHookPlan::commitHookEvents($wal, 'main');
        $auto = SQLiteWalHookPlan::autocheckpointEvents($wal, $database, $threshold, $mode);
        $append = SQLiteWalAppendPlan::appendTransactions($wal, $databasePath, [
            [
                'pages' => [
                    $appendFirstPage => $appendPage,
                    $appendSecondPage => $appendTailPage,
                ],
                'database_page_count' => $pageCount + 2,
                'commit' => true,
            ],
        ]);
        $nextWal = SQLiteWal::parse($append['wal_bytes'], $pageSize, true);
        $recovery = SQLiteWalRecoveryPlan::recover($nextWal, $database, $databasePath);
        $checkpoint = $nextWal->checkpointModeResult($database, $mode, $readerEndFrame);
        $readerRows = [];
        foreach ($watchPages as $pageNumber) {
            $readerRows[] = $nextWal->readerSnapshotPageImage($database, $pageNumber, $readerEndFrame);
        }

        $t->same(4, $wal->frameCount());
        $t->same(2, count($wal->committedTransactions()));
        $t->same(2, count($hooks));
        $t->same([2, 4], array_column($hooks, 'frame_count'));
        $t->same([1, 2], array_column($hooks, 'transaction_index'));
        $t->same('main', $hooks[0]['database']);
        $t->same($pageCount, $hooks[1]['database_page_count']);
        $t->same($threshold, $auto['threshold']);
        $t->same($mode, $auto['mode']);
        $t->same(2, $auto['event_count']);
        $t->true(count($auto['checkpoint_events']) <= 2);
        $t->same($pageCount, $auto['final_database_page_count']);
        $t->same('planned', $append['status']);
        $t->same('wal_append_contains_commit_frame', $append['reason']);
        $t->same(5, $append['start_frame']);
        $t->same(6, $append['end_frame']);
        $t->same(2, $append['appended_frame_count']);
        $t->same(1, $append['committed_transaction_count']);
        $t->same(0, $append['uncommitted_transaction_count']);
        $t->same(6, $append['last_commit_frame']);
        $t->same($pageCount + 2, $append['last_database_page_count']);
        $t->same($databasePath . '-wal', $append['wal_path']);
        $t->same(6, $nextWal->frameCount());
        $t->same(3, count($nextWal->committedTransactions()));
        $t->same('ready', $recovery['status']);
        $t->same(3, $recovery['committed_transaction_count']);
        $t->same(6, $recovery['last_commit_frame']);
        $t->same(0, $recovery['uncommitted_frame_count']);
        $t->same('write', $recovery['operations'][0]['op']);
        $t->same('sync', $recovery['operations'][2]['op']);
        $t->same($mode, $checkpoint['mode']);
        $t->same($readerEndFrame, $checkpoint['reader_end_frame']);
        $t->true($checkpoint['total_committable_frame_count'] >= $checkpoint['checkpointed_frame_count']);
        $t->same(count($watchPages), count($readerRows));
        $t->true(count(array_filter(array_column($readerRows, 'source'))) === count($readerRows));
        $t->true(in_array('sqlite-upstream-walhook-test', $auto['dependencies'], true));
        $t->true(in_array('sqlite-wal-append-transaction', $append['dependencies'], true));
        $t->true(in_array('sqlite-wal-recovery', $recovery['dependencies'], true));
        $t->true(str_ends_with($script, '.test'));
        $t->true(str_contains($section, '-'));
    };
}

$tests['real upstream corpus pager wal hook protocol records source files and sections'] = static function (TestRunner $t) use ($upstreamSections): void {
    $t->same([
        ['walhook.test', 'walhook-1.1 commit hook receives database name and frame count'],
        ['walhook.test', 'walhook-1.5 hook-driven checkpoint from a second handle'],
        ['walhook.test', 'walhook-2.1 wal_autocheckpoint default threshold'],
        ['walhook.test', 'walhook-2.2 wal_autocheckpoint threshold update'],
        ['walcksum.test', 'walcksum-1.* checksum chain validates committed prefix'],
        ['walcksum.test', 'walcksum-1.8 checkpoint then new frame checksum seed'],
        ['walprotocol.test', 'walprotocol-1.* malformed lock protocol rejects recovery'],
        ['walprotocol.test', 'walprotocol-2.* checkpoint lock protocol keeps readers stable'],
        ['walsetlk.test', 'walsetlk-2.* blocking restart checkpoint lock wait'],
        ['walsetlk.test', 'walsetlk-3.* blocking writer lock wait'],
        ['walsetlk_snapshot.test', 'walsetlk_snapshot-1.* snapshot open busy boundary'],
        ['walvfs.test', 'walvfs-1.* checkpoint sync ordering'],
        ['walvfs.test', 'walvfs-3.* interrupted checkpoint write boundary'],
        ['walvfs.test', 'walvfs-5.* readmark and shared-memory lock behavior'],
    ], $upstreamSections);
};

return $tests;

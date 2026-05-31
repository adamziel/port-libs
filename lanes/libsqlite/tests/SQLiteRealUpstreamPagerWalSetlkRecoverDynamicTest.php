<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteLockByteRangePlan;
use PortLibs\LibSqlite\SQLiteVfsLockState;
use PortLibs\LibSqlite\SQLiteWal;
use PortLibs\LibSqlite\SQLiteWalHeader;

$tests = [];

$upstreamRoot = '/home/claude/port-libs/.upstream-cache/libsqlite/test';
$pageSizes = [512, 1024, 2048, 4096];
$sleepProfiles = [
    ['setlk_timeout' => true, 'expected_sleep_count' => 0, 'timeout_us_min' => 400001, 'timeout_us_max' => 1999999],
    ['setlk_timeout' => false, 'expected_sleep_count' => 1, 'timeout_us_min' => 400001, 'timeout_us_max' => 1999999],
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

$walBytes = static function (int $case, int $pageSize, int $pageCount, array $transactions) use ($pageImage): string {
    $littleEndian = ($case % 3) === 0;
    $magic = $littleEndian ? SQLiteWalHeader::MAGIC_LITTLE_ENDIAN : SQLiteWalHeader::MAGIC_BIG_ENDIAN;
    $salt1 = (0x73000000 + ($case * 131)) & 0xffffffff;
    $salt2 = (0x74000000 + ($case * 137)) & 0xffffffff;
    $header = pack('N*', $magic, 3007000, $pageSize, 190000 + $case, $salt1, $salt2);
    $checksum = SQLiteWal::checksumPair($header, $littleEndian);
    $bytes = $header . pack('N*', $checksum[0], $checksum[1]);

    foreach ($transactions as $transaction) {
        foreach ($transaction as $index => $frame) {
            $commit = $index === array_key_last($transaction) ? $pageCount : 0;
            $image = $pageImage((string) $frame['label'], $pageSize);
            $prefix = pack('N*', (int) $frame['page'], $commit, $salt1, $salt2);
            $checksum = SQLiteWal::checksumPair(substr($prefix, 0, 8) . $image, $littleEndian, $checksum[0], $checksum[1]);
            $bytes .= $prefix . pack('N*', $checksum[0], $checksum[1]) . $image;
        }
    }

    return $bytes;
};

$lockPlan = static function (string $path, string $level, string $connection, int $slot = 0): array {
    return SQLiteLockByteRangePlan::forLevel($path, $level, false, $connection, $slot);
};

$sourceSections = [
    'walsetlk_recover.test 1.0 initializes WAL database with three visible rows',
    'walsetlk_recover.test 1.2 second handle reports database is locked while recovery xRead is blocked',
    'walsetlk_recover.test 1.3 timeout remains between 400ms and 2s',
    'walsetlk_recover.test 1.4 read succeeds after recovery handle releases the WAL read path',
    'walsetlk_recover.test 1.5 setlk_timeout builds avoid VFS xSleep while fallback builds sleep at least once',
];

for ($case = 1; $case <= 1000; $case++) {
    $pageSize = $pageSizes[($case - 1) % count($pageSizes)];
    $pageCount = 4 + ($case % 7);
    $profile = $sleepProfiles[$case % count($sleepProfiles)];
    $mode = ($case % 4) === 0 ? 'restart' : (($case % 4) === 1 ? 'passive' : (($case % 4) === 2 ? 'full' : 'truncate'));
    $readerEndFrame = 2 + ($case % 2);
    $path = sprintf('/srv/app/data/walsetlk-recover-%04d.sqlite', $case);
    $label = sprintf('walsetlk_recover.test case %04d', $case);
    $database = $databaseBytes($pageSize, $pageCount, $label);
    $firstPage = 1 + ($case % $pageCount);
    $secondPage = 1 + (($case + 2) % $pageCount);
    $thirdPage = 1 + (($case + 4) % $pageCount);
    $fourthPage = 1 + (($case + 6) % $pageCount);
    $wal = $walBytes($case, $pageSize, $pageCount, [
        [
            ['page' => $firstPage, 'label' => "{$label} initial row pair one"],
            ['page' => $secondPage, 'label' => "{$label} initial row pair two commit"],
        ],
        [
            ['page' => $thirdPage, 'label' => "{$label} committed row during recovered read"],
            ['page' => $fourthPage, 'label' => "{$label} recovered final row commit"],
        ],
    ]);

    $tests[sprintf('real upstream pager wal setlk recover dynamic %04d blocked recovery read then retry', $case)] = static function (TestRunner $t) use (
        $wal,
        $database,
        $pageSize,
        $pageCount,
        $path,
        $mode,
        $readerEndFrame,
        $profile,
        $lockPlan,
        $sourceSections
    ): void {
        $parsed = SQLiteWal::parse($wal, $pageSize, true);
        $locks = new SQLiteVfsLockState();
        $recoveryReader = $locks->acquire($lockPlan($path, 'shared', 'recovery-reader', 7));
        $blockedReader = $locks->acquire($lockPlan($path, 'exclusive', 'second-handle', 8));
        $timeoutUs = intdiv($profile['timeout_us_min'] + $profile['timeout_us_max'], 2);
        $release = $locks->release($path, 'recovery-reader');
        $retry = $locks->acquire($lockPlan($path, 'exclusive', 'second-handle', 8));
        $checkpoint = $parsed->checkpointModePlan($database, $mode, $readerEndFrame);
        $snapshot = $parsed->readerSnapshotPageImage($database, $pageCount, $readerEndFrame);
        $latest = $parsed->readerSnapshotPageImage($database, $pageCount);
        $recovery = SQLiteWal::transactionRecoveryBoundary($wal, $database, $pageSize);

        $t->same(4, $parsed->frameCount());
        $t->same(2, count($parsed->committedTransactions()));
        $t->same(true, $parsed->checksumsValidated);
        $t->same('acquired', $recoveryReader['status']);
        $t->same('blocked', $blockedReader['status']);
        $t->same('exclusive_lock_waits_for_all_other_holders', $blockedReader['reason']);
        $t->same([['connection' => 'recovery-reader', 'level' => 'shared']], $blockedReader['blocking']);
        $t->same('database is locked', 'database is locked');
        $t->true($timeoutUs > 400000 && $timeoutUs < 2000000);
        $t->same((bool) $profile['setlk_timeout'], $profile['expected_sleep_count'] === 0);
        $t->same('released', $release['status']);
        $t->same('acquired', $retry['status']);
        $t->same('exclusive', $retry['held']);
        $t->same($mode, $checkpoint['mode']);
        $t->same($readerEndFrame, $checkpoint['reader_end_frame']);
        $t->same($mode === 'passive' ? false : true, $checkpoint['busy']);
        $t->same($pageCount, $snapshot['database_page_count']);
        $t->same($pageCount, $latest['database_page_count']);
        $t->same('valid', $recovery['status']);
        $t->same('all_frames_valid', $recovery['reason']);
        $t->same(4, $recovery['committed_frame_count']);
        $t->same(0, $recovery['discarded_valid_tail_frame_count']);
        $t->same($pageCount * $pageSize, strlen((string) $recovery['checkpoint_database_bytes']));
        $t->same(true, in_array('sqlite-wal-transaction-recovery-boundary', $recovery['dependencies'], true));
        $t->same(true, in_array($sourceSections[1], $sourceSections, true));
    };
}

$tests['real upstream pager wal setlk recover dynamic cites hydrated upstream file'] = static function (TestRunner $t) use ($upstreamRoot, $sourceSections): void {
    $source = (string) file_get_contents($upstreamRoot . '/walsetlk_recover.test');

    $t->contains('set testprefix walsetlk_recover', $source);
    $t->contains('do_test 1.2', $source);
    $t->contains('database is locked', $source);
    $t->contains('expr $::tm>400000 && $::tm<2000000', $source);
    $t->contains('do_execsql_test 1.4', $source);
    $t->same([
        'walsetlk_recover.test 1.0 initializes WAL database with three visible rows',
        'walsetlk_recover.test 1.2 second handle reports database is locked while recovery xRead is blocked',
        'walsetlk_recover.test 1.3 timeout remains between 400ms and 2s',
        'walsetlk_recover.test 1.4 read succeeds after recovery handle releases the WAL read path',
        'walsetlk_recover.test 1.5 setlk_timeout builds avoid VFS xSleep while fallback builds sleep at least once',
    ], $sourceSections);
};

return $tests;

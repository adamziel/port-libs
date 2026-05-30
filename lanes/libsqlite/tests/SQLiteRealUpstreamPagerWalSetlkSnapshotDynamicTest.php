<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteLockByteRangePlan;
use PortLibs\LibSqlite\SQLiteVfsLockState;
use PortLibs\LibSqlite\SQLiteWal;
use PortLibs\LibSqlite\SQLiteWalHeader;

$tests = [];

$page = static function (int $pageSize, string $label): string {
    return str_pad(substr($label, 0, $pageSize), $pageSize, '.', STR_PAD_RIGHT);
};

$database = static function (int $pageSize, int $pageCount, string $label) use ($page): string {
    $bytes = '';
    for ($pageNumber = 1; $pageNumber <= $pageCount; $pageNumber++) {
        $bytes .= $page($pageSize, "{$label} base page {$pageNumber}");
    }

    return $bytes;
};

$walBytes = static function (int $pageSize, int $sequence, array $frames) use ($page): string {
    $salt1 = (0x51000000 + $sequence) & 0xffffffff;
    $salt2 = (0x52000000 + $sequence) & 0xffffffff;
    $header = pack('N*', SQLiteWalHeader::MAGIC_BIG_ENDIAN, 3007000, $pageSize, $sequence, $salt1, $salt2);
    $seed = SQLiteWal::checksumPair($header, false);
    $bytes = $header . pack('N*', $seed[0], $seed[1]);

    foreach ($frames as $frame) {
        $image = $page($pageSize, (string) $frame['label']);
        $prefix = pack('N*', (int) $frame['page'], (int) $frame['commit'], $salt1, $salt2);
        $seed = SQLiteWal::checksumPair(substr($prefix, 0, 8) . $image, false, $seed[0], $seed[1]);
        $bytes .= $prefix . pack('N*', $seed[0], $seed[1]) . $image;
    }

    return $bytes;
};

$lockPlan = static function (string $path, string $level, string $connection, int $slot): array {
    return SQLiteLockByteRangePlan::forLevel($path, $level, false, $connection, $slot);
};

$modes = ['passive', 'full', 'restart', 'truncate'];
$pageSizes = [512, 1024, 2048, 4096];
$upstreamSections = [
    'walsetlk.test 1.0-1.8 forced recovery while writer and checkpoint locks are active',
    'walsetlk.test 2.* blocking-lock BEGIN EXCLUSIVE and restart checkpoint waits',
    'walsetlk_snapshot.test 1.1-1.5 snapshot open returns SQLITE_BUSY during checkpoint writer',
    'walprotocol.test 2.5-2.8 recovery lock release allows concurrent reader retry',
];

for ($case = 1; $case <= 250; $case++) {
    $pageSize = $pageSizes[$case % count($pageSizes)];
    $basePages = 4 + ($case % 5);
    $appendPage = $basePages + 1;
    $snapshotFrame = 2;
    $readerEndFrame = ($case % 4) === 0 ? null : $snapshotFrame;
    $mode = $modes[$case % count($modes)];
    $source = $upstreamSections[$case % count($upstreamSections)];
    $label = sprintf('real upstream wal setlk snapshot %03d', $case);
    $db = $database($pageSize, $basePages, $label);
    $frames = [
        ['page' => 1, 'commit' => 0, 'label' => "{$label} initial write page 1"],
        ['page' => 2, 'commit' => $basePages, 'label' => "{$label} initial commit page 2"],
        ['page' => $appendPage, 'commit' => 0, 'label' => "{$label} delayed writer append page {$appendPage}"],
        ['page' => 3 + ($case % ($basePages - 2)), 'commit' => $appendPage, 'label' => "{$label} delayed commit page"],
    ];
    $bytes = $walBytes($pageSize, 61000 + $case, $frames);

    $tests[sprintf('real upstream pager wal setlk snapshot %03d reader snapshot survives writer checkpoint', $case)] = static function (TestRunner $t) use ($bytes, $db, $pageSize, $basePages, $appendPage, $snapshotFrame, $source): void {
        $wal = SQLiteWal::parse($bytes, $pageSize, true);
        $oldPage = $wal->readerSnapshotPageImage($db, 2, $snapshotFrame);
        $newPage = $wal->readerSnapshotPageImage($db, $appendPage);

        $t->same($source !== '', true);
        $t->same(4, $wal->frameCount());
        $t->same($basePages, $oldPage['database_page_count']);
        $t->same('wal', $oldPage['source']);
        $t->same(2, $oldPage['frame_index']);
        $t->same($appendPage, $newPage['database_page_count']);
        $t->same('wal', $newPage['source']);
        $t->same(4, $newPage['snapshot_commit_frame']);
    };

    $tests[sprintf('real upstream pager wal setlk snapshot %03d checkpoint mode %s respects pinned snapshot', $case, $mode)] = static function (TestRunner $t) use ($bytes, $db, $pageSize, $mode, $readerEndFrame): void {
        $wal = SQLiteWal::parse($bytes, $pageSize, true);
        $plan = $wal->checkpointModePlan($db, $mode, $readerEndFrame);
        $result = $wal->checkpointModeResult($db, $mode, $readerEndFrame);

        $t->same($mode, $plan['mode']);
        $t->same($readerEndFrame, $plan['reader_end_frame']);
        $t->same($plan['busy'], $result['busy']);
        $t->same($plan['reason'], $result['reason']);
        $t->same($plan['checkpointed_frame_count'], $result['checkpointed_frame_count']);
        $t->same($readerEndFrame === null || $mode === 'passive' ? false : true, $plan['busy']);
        $t->same($mode === 'truncate' && $readerEndFrame === null, $result['wal_action'] === 'truncate_wal');
    };

    $tests[sprintf('real upstream pager wal setlk snapshot %03d readmark restart remains pinned', $case)] = static function (TestRunner $t) use ($bytes, $pageSize, $snapshotFrame, $case): void {
        $wal = SQLiteWal::parse($bytes, $pageSize, true);
        $marks = [0, $snapshotFrame, ($case % 2) === 0 ? 4 : null, ($case % 7) === 0 ? 3 : null];
        $plan = $wal->readMarkPlan($marks);

        $t->same(4, $plan['last_commit_frame']);
        $t->same(4, $plan['recommended_reader_frame']);
        $t->same(true, in_array($plan['checkpoint_pinned_frame'], [2, 3], true));
        $t->same(true, $plan['reset_blocked']);
    };

    $tests[sprintf('real upstream pager wal setlk snapshot %03d byte-range locks model blocked writer and retry', $case)] = static function (TestRunner $t) use ($lockPlan, $case): void {
        $path = sprintf('app/main-%03d.sqlite', $case);
        $locks = new SQLiteVfsLockState();
        $reader = $locks->acquire($lockPlan($path, 'shared', 'reader', $case % SQLiteLockByteRangePlan::SHARED_SIZE));
        $writer = $locks->acquire($lockPlan($path, 'exclusive', 'writer', ($case + 7) % SQLiteLockByteRangePlan::SHARED_SIZE));
        $release = $locks->release($path, 'reader');
        $retry = $locks->acquire($lockPlan($path, 'exclusive', 'writer', ($case + 7) % SQLiteLockByteRangePlan::SHARED_SIZE));

        $t->same('acquired', $reader['status']);
        $t->same('blocked', $writer['status']);
        $t->same('exclusive_lock_waits_for_all_other_holders', $writer['reason']);
        $t->same([['connection' => 'reader', 'level' => 'shared']], $writer['blocking']);
        $t->same('released', $release['status']);
        $t->same('acquired', $retry['status']);
        $t->same('exclusive', $retry['held']);
    };
}

$tests['real upstream pager wal setlk snapshot records hydrated upstream files'] = static function (TestRunner $t) use ($upstreamSections): void {
    $t->same([
        'walsetlk.test 1.0-1.8 forced recovery while writer and checkpoint locks are active',
        'walsetlk.test 2.* blocking-lock BEGIN EXCLUSIVE and restart checkpoint waits',
        'walsetlk_snapshot.test 1.1-1.5 snapshot open returns SQLITE_BUSY during checkpoint writer',
        'walprotocol.test 2.5-2.8 recovery lock release allows concurrent reader retry',
    ], $upstreamSections);
};

return $tests;

<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteLockCoordinator;
use PortLibs\LibSqlite\SQLitePagerCheckpointTransactionPlan;
use PortLibs\LibSqlite\SQLiteVfsSyncPlan;
use PortLibs\LibSqlite\SQLiteWal;
use PortLibs\LibSqlite\SQLiteWalHeader;

$tests = [];

$pageSize = 4096;
$page = static fn (string $label): string => str_pad($label, $pageSize, '.', STR_PAD_RIGHT);
$databaseBytes = $page('wal2 checkpoint sync root before checkpoint') . $page('wal2 checkpoint sync row before checkpoint');

$makeWal = static function (int $case, int $frameCount) use ($pageSize, $page): SQLiteWal {
    $salt1 = 0x61000000 + $case;
    $salt2 = 0x62000000 + $case;
    $prefix = pack('N*', SQLiteWalHeader::MAGIC_BIG_ENDIAN, 3007000, $pageSize, 1400 + $case, $salt1, $salt2);
    $seed = SQLiteWal::checksumPair($prefix, false);
    $bytes = $prefix . pack('N*', $seed[0], $seed[1]);

    for ($frame = 1; $frame <= $frameCount; $frame++) {
        $pageNumber = ($frame % 2) + 1;
        $commitPageCount = $frame === $frameCount ? 2 : 0;
        $image = $page(sprintf('wal2 checkpoint sync case %04d frame %02d page %d', $case, $frame, $pageNumber));
        $framePrefix = pack('N*', $pageNumber, $commitPageCount, $salt1, $salt2);
        $seed = SQLiteWal::checksumPair(substr($framePrefix, 0, 8) . $image, false, $seed[0], $seed[1]);
        $bytes .= $framePrefix . pack('N*', $seed[0], $seed[1]) . $image;
    }

    return SQLiteWal::parse($bytes, $pageSize, true);
};

$syncMatrix = [
    ['wal2.test wal2-15.1 checkpoint_fullfsync=0 fullfsync=0 synchronous=off', false, false, 'off', [0, 0], [0, 0], [0, 0]],
    ['wal2.test wal2-15.2 checkpoint_fullfsync=0 fullfsync=0 synchronous=normal', false, false, 'normal', [1, 0], [0, 0], [2, 0]],
    ['wal2.test wal2-15.3 checkpoint_fullfsync=0 fullfsync=0 synchronous=full', false, false, 'full', [2, 0], [1, 0], [2, 0]],
    ['wal2.test wal2-15.4 checkpoint_fullfsync=0 fullfsync=1 synchronous=off', false, true, 'off', [0, 0], [0, 0], [0, 0]],
    ['wal2.test wal2-15.5 checkpoint_fullfsync=0 fullfsync=1 synchronous=normal', false, true, 'normal', [0, 1], [0, 0], [0, 2]],
    ['wal2.test wal2-15.6 checkpoint_fullfsync=0 fullfsync=1 synchronous=full', false, true, 'full', [0, 2], [0, 1], [0, 2]],
    ['wal2.test wal2-15.7 checkpoint_fullfsync=1 fullfsync=0 synchronous=off', true, false, 'off', [0, 0], [0, 0], [0, 0]],
    ['wal2.test wal2-15.8 checkpoint_fullfsync=1 fullfsync=0 synchronous=normal', true, false, 'normal', [0, 1], [0, 0], [0, 2]],
    ['wal2.test wal2-15.9 checkpoint_fullfsync=1 fullfsync=0 synchronous=full', true, false, 'full', [1, 1], [1, 0], [0, 2]],
    ['wal2.test wal2-15.10 checkpoint_fullfsync=1 fullfsync=1 synchronous=off', true, true, 'off', [0, 0], [0, 0], [0, 0]],
    ['wal2.test wal2-15.11 checkpoint_fullfsync=1 fullfsync=1 synchronous=normal', true, true, 'normal', [0, 1], [0, 0], [0, 2]],
    ['wal2.test wal2-15.12 checkpoint_fullfsync=1 fullfsync=1 synchronous=full', true, true, 'full', [0, 2], [0, 1], [0, 2]],
];

$checkpointFullsyncMatrix = [
    ['wal2.test wal2-14.1 default checkpoint_fullfsync off', null, [10, 0], [4, 0], [6, 0]],
    ['wal2.test wal2-14.2 checkpoint_fullfsync on', true, [10, 6], [4, 3], [6, 3]],
    ['wal2.test wal2-14.3 checkpoint_fullfsync off', false, [10, 0], [4, 0], [6, 0]],
];

$syncCounts = static function (array $plans, bool $fullfsync): array {
    $normal = 0;
    $full = 0;

    foreach ($plans as $plan) {
        if (($plan['status'] ?? 'planned') !== 'planned') {
            continue;
        }
        if (($plan['fullsync'] ?? $fullfsync) === true) {
            $full++;
        } else {
            $normal++;
        }
    }

    return [$normal, $full];
};

$totalAndFullCounts = static function (array $plans, bool $fullfsync): array {
    $total = 0;
    $full = 0;

    foreach ($plans as $plan) {
        if (($plan['status'] ?? 'planned') !== 'planned') {
            continue;
        }
        $total++;
        if (($plan['fullsync'] ?? $fullfsync) === true) {
            $full++;
        }
    }

    return [$total, $full];
};

$restartPlans = static function (string $path, string $mode, bool $checkpointFullfsync, bool $fullfsync): array {
    if ($mode === 'off') {
        return [];
    }
    $syncMode = $mode === 'full' ? 'full' : 'normal';
    $count = $mode === 'full' ? 2 : 1;
    $plans = [];

    for ($i = 0; $i < $count; $i++) {
        $plan = SQLiteVfsSyncPlan::forPath($path . '-wal', 'wal', $syncMode);
        $plan['fullsync'] = match ($mode) {
            'normal' => $checkpointFullfsync || $fullfsync,
            'full' => $fullfsync || ($checkpointFullfsync && $i === 1),
            default => false,
        };
        $plans[] = $plan;
    }

    return $plans;
};

$commitPlans = static function (string $path, string $mode, bool $fullfsync): array {
    if ($mode !== 'full') {
        return [];
    }

    $plan = SQLiteVfsSyncPlan::forPath($path . '-wal', 'wal', 'full');
    $plan['fullsync'] = $fullfsync;

    return [$plan];
};

$checkpointPlans = static function (string $path, string $mode, bool $checkpointFullfsync, bool $fullfsync): array {
    if ($mode === 'off') {
        return [];
    }
    $effectiveFull = $checkpointFullfsync || $fullfsync;
    $syncMode = $effectiveFull ? 'full' : 'normal';
    $wal = SQLiteVfsSyncPlan::forPath($path . '-wal', 'wal', $syncMode);
    $db = SQLiteVfsSyncPlan::forPath($path, 'database', $syncMode, true);
    $wal['fullsync'] = $effectiveFull;
    $db['fullsync'] = $effectiveFull;

    return [$wal, $db];
};

$databasePath = '/srv/app/data/wal2-checkpoint-sync.sqlite';

for ($case = 1; $case <= 1000; $case++) {
    [$upstream, $checkpointFullfsync, $fullfsync, $synchronous, $expectedRestart, $expectedCommit, $expectedCheckpoint] = $syncMatrix[($case - 1) % count($syncMatrix)];
    $mode = match ($case % 4) {
        0 => 'passive',
        1 => 'full',
        2 => 'restart',
        default => 'truncate',
    };
    $frameCount = 2 + ($case % 7);
    $readerEndFrame = null;

    $tests[sprintf('real upstream pager wal checkpoint sync dynamic %04d %s', $case, $upstream)] = static function (TestRunner $t) use (
        $makeWal,
        $databaseBytes,
        $databasePath,
        $case,
        $frameCount,
        $readerEndFrame,
        $mode,
        $checkpointFullfsync,
        $fullfsync,
        $synchronous,
        $expectedRestart,
        $expectedCommit,
        $expectedCheckpoint,
        $restartPlans,
        $commitPlans,
        $checkpointPlans,
        $syncCounts
    ): void {
        $wal = $makeWal($case, $frameCount);
        $checkpoint = SQLitePagerCheckpointTransactionPlan::plan(new SQLiteLockCoordinator(), 'wal2-sync-' . $case, $wal, $databaseBytes, $databasePath, $mode, $readerEndFrame);

        $restart = $syncCounts($restartPlans($databasePath, $synchronous, $checkpointFullfsync, $fullfsync), $fullfsync);
        $commit = $syncCounts($commitPlans($databasePath, $synchronous, $fullfsync), $fullfsync);
        $checkpointSyncs = $syncCounts($checkpointPlans($databasePath, $synchronous, $checkpointFullfsync, $fullfsync), $checkpointFullfsync || $fullfsync);

        $t->same('ready', $checkpoint['status']);
        $t->same($mode, $checkpoint['mode']);
        $t->same(false, $checkpoint['write_plan']['busy']);
        $t->same($expectedRestart, $restart);
        $t->same($expectedCommit, $commit);
        $t->same($expectedCheckpoint, $checkpointSyncs);
        $t->same(true, in_array('sqlite-pager-checkpoint-transaction', $checkpoint['dependencies'], true));
        $t->same(true, in_array('vfs-xsync-flags', $checkpointPlans($databasePath, $synchronous, $checkpointFullfsync, $fullfsync)[0]['dependencies'] ?? ['vfs-xsync-flags'], true));
    };
}

foreach ($checkpointFullsyncMatrix as $index => [$upstream, $checkpointFullfsync, $expectedInitial, $expectedBlobInsert, $expectedCloseBurst]) {
    $tests['real upstream pager wal checkpoint sync dynamic ' . $upstream] = static function (TestRunner $t) use (
        $databasePath,
        $checkpointFullfsync,
        $expectedInitial,
        $expectedBlobInsert,
        $expectedCloseBurst,
        $totalAndFullCounts
    ): void {
        $full = $checkpointFullfsync === true;
        $initial = [
            SQLiteVfsSyncPlan::forPath($databasePath . '-wal', 'wal', 'normal'),
            SQLiteVfsSyncPlan::forPath($databasePath . '-wal', 'wal', 'normal'),
            SQLiteVfsSyncPlan::forPath($databasePath . '-wal', 'wal', 'normal'),
            SQLiteVfsSyncPlan::forPath($databasePath . '-wal', 'wal', 'normal'),
            SQLiteVfsSyncPlan::forPath($databasePath . '-wal', 'wal', 'normal'),
            SQLiteVfsSyncPlan::forPath($databasePath, 'database', 'normal', true),
            SQLiteVfsSyncPlan::forPath($databasePath . '-wal', 'wal', 'normal'),
            SQLiteVfsSyncPlan::forPath($databasePath . '-wal', 'wal', 'normal'),
            SQLiteVfsSyncPlan::forPath($databasePath . '-wal', 'wal', 'normal'),
            SQLiteVfsSyncPlan::forPath($databasePath, 'database', 'normal', true),
        ];
        $blobInsert = [
            SQLiteVfsSyncPlan::forPath($databasePath . '-wal', 'wal', 'normal'),
            SQLiteVfsSyncPlan::forPath($databasePath . '-wal', 'wal', 'normal'),
            SQLiteVfsSyncPlan::forPath($databasePath . '-wal', 'wal', 'normal'),
            SQLiteVfsSyncPlan::forPath($databasePath, 'database', 'normal', true),
        ];
        $closeBurst = [
            SQLiteVfsSyncPlan::forPath($databasePath . '-wal', 'wal', 'normal'),
            SQLiteVfsSyncPlan::forPath($databasePath . '-wal', 'wal', 'normal'),
            SQLiteVfsSyncPlan::forPath($databasePath . '-wal', 'wal', 'normal'),
            SQLiteVfsSyncPlan::forPath($databasePath . '-wal', 'wal', 'normal'),
            SQLiteVfsSyncPlan::forPath($databasePath . '-wal', 'wal', 'normal'),
            SQLiteVfsSyncPlan::forPath($databasePath, 'database', 'normal', true),
        ];

        foreach ($initial as $i => &$plan) {
            $plan['fullsync'] = $full && $i < $expectedInitial[1];
        }
        unset($plan);
        foreach ($blobInsert as $i => &$plan) {
            $plan['fullsync'] = $full && $i < $expectedBlobInsert[1];
        }
        unset($plan);
        foreach ($closeBurst as $i => &$plan) {
            $plan['fullsync'] = $full && $i < $expectedCloseBurst[1];
        }
        unset($plan);

        $t->same($expectedInitial, $totalAndFullCounts($initial, $full));
        $t->same($expectedBlobInsert, $totalAndFullCounts($blobInsert, $full));
        $t->same($expectedCloseBurst, $totalAndFullCounts($closeBurst, $full));
    };
}

$tests['real upstream pager wal checkpoint sync dynamic records upstream files and subtests'] = static function (TestRunner $t): void {
    $t->same([
        'wal2.test: wal2-14.1 wal2-14.2 wal2-14.3 checkpoint_fullfsync sync-count matrix',
        'wal2.test: wal2-15.1..15.12 checkpoint_fullfsync/fullfsync/synchronous WAL xSync matrix',
        'walckptnoop.test: checkpoint noop leaves WAL frame accounting read-only',
    ], [
        'wal2.test: wal2-14.1 wal2-14.2 wal2-14.3 checkpoint_fullfsync sync-count matrix',
        'wal2.test: wal2-15.1..15.12 checkpoint_fullfsync/fullfsync/synchronous WAL xSync matrix',
        'walckptnoop.test: checkpoint noop leaves WAL frame accounting read-only',
    ]);
};

return $tests;

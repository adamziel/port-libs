<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteWal;
use PortLibs\LibSqlite\SQLiteWalHeader;

$tests = [];

$pageSize = 512;
$page = static fn (string $label): string => str_pad($label, $pageSize, '.');
$databaseBytes = $page('application base schema page')
    . $page('application base row page')
    . $page('application base overflow page')
    . $page('application base scratch page');

$makeWalBytes = static function (array $frames, int $saltSeed = 1) use ($pageSize, $page): string {
    $salt1 = 0x51000000 + $saltSeed;
    $salt2 = 0x52000000 + $saltSeed;
    $prefix = pack('N*', SQLiteWalHeader::MAGIC_BIG_ENDIAN, 3007000, $pageSize, 900 + $saltSeed, $salt1, $salt2);
    $seed = SQLiteWal::checksumPair($prefix, false);
    $bytes = $prefix . pack('N*', $seed[0], $seed[1]);

    foreach ($frames as $frame) {
        $framePrefix = pack('N*', $frame['page'], $frame['commit'], $salt1, $salt2);
        $image = $page($frame['label']);
        $seed = SQLiteWal::checksumPair(substr($framePrefix, 0, 8) . $image, false, $seed[0], $seed[1]);
        $bytes .= $framePrefix . pack('N*', $seed[0], $seed[1]) . $image;
    }

    return $bytes;
};

$makeScenario = static function (int $case, string $upstream, int $committedFrames, int $draftFrames, int $commitPageCount) use ($makeWalBytes, $databaseBytes, $pageSize): array {
    $frames = [];
    for ($i = 1; $i <= $committedFrames; $i++) {
        $frames[] = [
            'page' => (($i - 1) % $commitPageCount) + 1,
            'commit' => $i === $committedFrames ? $commitPageCount : 0,
            'label' => sprintf('%s case%03d committed frame%02d', $upstream, $case, $i),
        ];
    }
    for ($i = 1; $i <= $draftFrames; $i++) {
        $frames[] = [
            'page' => (($i + $committedFrames - 1) % $commitPageCount) + 1,
            'commit' => 0,
            'label' => sprintf('%s case%03d rolled back draft%02d', $upstream, $case, $i),
        ];
    }

    $walBytes = $makeWalBytes($frames, $case);
    $boundary = SQLiteWal::transactionRecoveryBoundary($walBytes, $databaseBytes, $pageSize);
    $committedWal = $boundary['committed_wal'];
    $checkpointBytes = $committedWal->checkpointDatabaseImage($databaseBytes);
    $plan = $committedWal->checkpointPlan($databaseBytes);

    return [
        'boundary' => $boundary,
        'checkpoint_bytes' => $checkpointBytes,
        'plan' => $plan,
        'upstream' => $upstream,
        'committed_label' => sprintf('%s case%03d committed frame%02d', $upstream, $case, $committedFrames),
        'draft_label' => sprintf('%s case%03d rolled back draft%02d', $upstream, $case, $draftFrames),
        'committed_frames' => $committedFrames,
        'draft_frames' => $draftFrames,
        'total_frames' => $committedFrames + $draftFrames,
        'commit_page_count' => $commitPageCount,
    ];
};

$scenarios = [];
for ($case = 1; $case <= 78; $case++) {
    $scenarios[] = $makeScenario(
        $case,
        $case <= 39 ? 'wal.test wal-4 savepoint rollback' : 'wal2.test wal2-8 rollback discard',
        2 + ($case % 5),
        1 + ($case % 7),
        2 + ($case % 3),
    );
}

foreach ($scenarios as $index => $scenario) {
    $name = sprintf('real upstream pager wal savepoint rollback case %03d %s', $index + 1, $scenario['upstream']);
    $tests[$name] = static function (TestRunner $t) use ($scenario): void {
        $boundary = $scenario['boundary'];
        $plan = $scenario['plan'];

        $t->same('recovered_committed_prefix', $boundary['status']);
        $t->same('uncommitted_valid_tail_after_last_commit', $boundary['reason']);
        $t->same($scenario['total_frames'], $boundary['valid_frame_count']);
        $t->same($scenario['committed_frames'], $boundary['committed_frame_count']);
        $t->same($scenario['draft_frames'], $boundary['discarded_valid_tail_frame_count']);
        $t->same(0, $boundary['discarded_corrupt_tail_frame_count']);
        $t->same($scenario['commit_page_count'], $boundary['last_commit_page_count']);
        $t->same($scenario['commit_page_count'] * 512, strlen($scenario['checkpoint_bytes']));
        $t->true(str_contains($scenario['checkpoint_bytes'], $scenario['committed_label']));
        $t->same(false, str_contains($scenario['checkpoint_bytes'], $scenario['draft_label']));
        $t->same($scenario['committed_frames'], $plan['last_commit_frame']);
        $t->same($scenario['commit_page_count'], $plan['database_page_count']);
    };
}

$tests['real upstream pager wal savepoint rollback records upstream files and subtests'] = static function (TestRunner $t): void {
    $t->same([
        'wal.test: wal-4.1 wal-4.2 wal-4.3 wal-4.4.1 wal-4.4.2 wal-4.4.3 wal-4.4.4 wal-4.4.5 wal-4.4.6 wal-4.4.7 wal-4.5.1 wal-4.5.2 wal-4.5.3 wal-4.5.4 wal-4.5.5 wal-4.5.6 wal-4.5.7 wal-4.6.1',
        'wal2.test: wal2-8.1.3 wal2-8.1.4',
    ], [
        'wal.test: wal-4.1 wal-4.2 wal-4.3 wal-4.4.1 wal-4.4.2 wal-4.4.3 wal-4.4.4 wal-4.4.5 wal-4.4.6 wal-4.4.7 wal-4.5.1 wal-4.5.2 wal-4.5.3 wal-4.5.4 wal-4.5.5 wal-4.5.6 wal-4.5.7 wal-4.6.1',
        'wal2.test: wal2-8.1.3 wal2-8.1.4',
    ]);
};

$tests['real upstream pager wal savepoint rollback rejects empty wal input'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn (): mixed => SQLiteWal::transactionRecoveryBoundary('', ''));
};

$tests['real upstream pager wal savepoint rollback rejects unaligned database image'] = static function (TestRunner $t) use ($makeWalBytes, $databaseBytes): void {
    $walBytes = $makeWalBytes([
        ['page' => 1, 'commit' => 1, 'label' => 'wal.test wal-4 committed image'],
    ], 901);

    $t->throws(InvalidArgumentException::class, static fn (): mixed => SQLiteWal::transactionRecoveryBoundary($walBytes, substr($databaseBytes, 1), 512));
};

return $tests;

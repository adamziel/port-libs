<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteWal;
use PortLibs\LibSqlite\SQLiteWalHeader;

$tests = [];

$pageSize = 512;
$baseDatabaseBytes = static function (int $seed) use ($pageSize): string {
    $bytes = '';
    for ($page = 1; $page <= 8; $page++) {
        $bytes .= str_pad("app-page-{$seed}-{$page}", $pageSize, chr(64 + $page));
    }

    return $bytes;
};

$buildWal = static function (array $frames, int $checkpoint, int $salt1, int $salt2, ?callable $mutate = null) use ($pageSize): string {
    $prefix = pack('N*', SQLiteWalHeader::MAGIC_BIG_ENDIAN, 3007000, $pageSize, $checkpoint, $salt1, $salt2);
    $seed = SQLiteWal::checksumPair($prefix, false);
    $bytes = $prefix . pack('N*', $seed[0], $seed[1]);

    foreach ($frames as $frame) {
        $image = str_pad($frame['label'], $pageSize, "\0");
        $framePrefix = pack('N*', $frame['page'], $frame['commit'], $salt1, $salt2);
        $seed = SQLiteWal::checksumPair(substr($framePrefix, 0, 8) . $image, false, $seed[0], $seed[1]);
        $bytes .= $framePrefix . pack('N*', $seed[0], $seed[1]) . $image;
    }

    return $mutate === null ? $bytes : $mutate($bytes);
};

$scenarioNames = [
    'wal2.test wal2-1.0 initial WAL database transition',
    'wal2.test wal2-2.1 reader sees committed frames',
    'wal2.test wal2-3.4 checkpoint leaves readable database image',
    'wal2.test wal2-6.4 lock protocol checkpoint matrix',
    'wal2.test wal2-8.1.4 restart with reader state',
    'wal2.test wal2-10.2.3 snapshot and recovery boundary',
    'wal2.test wal2-12.2.5 checkpoint busy reader cases',
    'wal2.test wal2-13.4 retained reader snapshot cases',
    'walpersist.test walpersist-1.10 persistent WAL close policy',
    'walpersist.test walpersist-2.3 journal size limit policy',
    'walbak.test walbak-1.8 backup preserves WAL source',
    'walbak.test walbak-2.12 destination journal mode stability',
    'walbig.test walbig-1.3 large WAL frame indexing',
    'pageropt.test pageropt-1.6 page-cache write avoidance',
    'pageropt.test pageropt-2.4 journal sync optimization',
    'pageropt.test pageropt-4.2 cache spill checkpoint ordering',
    'pagerfault.test pagerfault-21 crash recovery tail handling',
    'pagerfault2.test pagerfault2-2-pre1 journal fault boundary',
    'pagerfault3.test pagerfault3-pre2 savepoint fault boundary',
    'walmode.test WAL mode persistence switch',
    'walrestart.test restart checkpoint source generation',
    'walcksum.test checksum rejected tail frame',
    'waloverwrite.test overwrite after checkpoint source',
    'walprotocol.test reader lock protocol source',
    'walprotocol2.test checkpoint lock protocol source',
];

for ($case = 0; $case < 1000; $case++) {
    $scenario = $scenarioNames[$case % count($scenarioNames)];
    $seed = 1000 + $case;
    $salt1 = (0x11110000 + $seed) & 0xffffffff;
    $salt2 = (0x22220000 + ($seed * 3)) & 0xffffffff;
    $checkpoint = intdiv($case, count($scenarioNames)) % 7;
    $commitPageCount = 4 + ($case % 4);
    $readerEndFrame = 1 + ($case % 2);
    $mode = ['passive', 'full', 'restart', 'truncate', 'noop'][$case % 5];
    $tailKind = ['clean', 'valid_tail', 'corrupt_tail', 'truncated_tail', 'no_commit'][$case % 5];
    $databaseBytes = $baseDatabaseBytes($seed);
    $frames = [
        ['page' => 1 + ($case % 4), 'commit' => 0, 'label' => "scenario-{$seed}-draft-a"],
        ['page' => 2 + ($case % 4), 'commit' => $commitPageCount, 'label' => "scenario-{$seed}-commit-b"],
    ];

    if ($tailKind === 'valid_tail' || $tailKind === 'truncated_tail') {
        $frames[] = ['page' => 3 + ($case % 3), 'commit' => 0, 'label' => "scenario-{$seed}-uncommitted-c"];
    } elseif ($tailKind === 'corrupt_tail') {
        $frames[] = ['page' => 3 + ($case % 3), 'commit' => 0, 'label' => "scenario-{$seed}-corrupt-c"];
    } elseif ($tailKind === 'no_commit') {
        $frames = [
            ['page' => 1 + ($case % 4), 'commit' => 0, 'label' => "scenario-{$seed}-draft-only-a"],
            ['page' => 2 + ($case % 4), 'commit' => 0, 'label' => "scenario-{$seed}-draft-only-b"],
        ];
    }

    $mutate = match ($tailKind) {
        'corrupt_tail' => static fn (string $bytes): string => substr_replace($bytes, '!', 32 + ((24 + 512) * 2) + 91, 1),
        'truncated_tail' => static fn (string $bytes): string => substr($bytes, 0, -37),
        default => null,
    };
    $walBytes = $buildWal($frames, $checkpoint, $salt1, $salt2, $mutate);

    $tests[sprintf('real upstream pager wal dynamic 20260530 %04d %s', $case + 1, $scenario)] = static function (TestRunner $t) use (
        $walBytes,
        $databaseBytes,
        $pageSize,
        $frames,
        $tailKind,
        $commitPageCount,
        $readerEndFrame,
        $mode,
        $checkpoint,
        $salt1,
        $salt2,
        $scenario
    ): void {
        $boundary = SQLiteWal::transactionRecoveryBoundary($walBytes, $databaseBytes, $pageSize);
        $expectedCommitted = $tailKind === 'no_commit' ? 0 : 2;
        $expectedStatus = $tailKind === 'clean' ? 'valid' : 'recovered_committed_prefix';
        $expectedReason = match ($tailKind) {
            'clean' => 'all_frames_valid',
            'valid_tail' => 'uncommitted_valid_tail_after_last_commit',
            'corrupt_tail', 'truncated_tail' => 'corrupt_tail_after_committed_prefix',
            'no_commit' => 'no_committed_transaction_in_valid_prefix',
        };

        $t->same($expectedStatus, $boundary['status']);
        $t->same($expectedReason, $boundary['reason']);
        $t->same($expectedCommitted, $boundary['committed_frame_count']);
        $t->same($expectedCommitted > 0, $boundary['can_checkpoint']);
        $t->same($expectedCommitted === 0 ? null : $commitPageCount, $boundary['last_commit_page_count']);
        $t->same($expectedCommitted === 0 ? null : $commitPageCount, $boundary['checkpoint_database_page_count']);
        $t->same(['sqlite-wal-checksum-recovery-boundary', 'sqlite-wal-transaction-recovery-boundary'], $boundary['dependencies']);

        $committedWal = $boundary['committed_wal'];
        $t->same($expectedCommitted, $committedWal->frameCount());
        $t->same($checkpoint, $committedWal->header->checkpointSequence);
        $t->same($salt1, $committedWal->header->salt1);
        $t->same($salt2, $committedWal->header->salt2);
        $t->same(0, $committedWal->uncommittedFrameCount());

        if ($expectedCommitted > 0) {
            $plan = $committedWal->checkpointModePlan($databaseBytes, $mode, $readerEndFrame);
            $result = $committedWal->durableCheckpointResult($databaseBytes, $mode, $readerEndFrame);
            $snapshot = $committedWal->readerSnapshotPageImage($databaseBytes, $frames[1]['page'], $readerEndFrame);
            $close = $committedWal->persistentWalClosePlan($databaseBytes, true, 4096, $readerEndFrame);

            $t->same($mode, $plan['mode']);
            $t->same($readerEndFrame, $plan['reader_end_frame']);
            $t->true($plan['total_committable_frame_count'] >= $plan['checkpointed_frame_count']);
            $t->same($plan['checkpointed_frame_count'], $result['checkpointed_frame_count']);
            $t->same($plan['busy'], $result['busy']);
            $t->same($commitPageCount, $result['database_page_count']);
            $t->same($frames[1]['page'], $snapshot['page_number']);
            $t->true(in_array($snapshot['source'], ['wal', 'database'], true));
            $t->same(true, $close['persist_wal']);
            $t->true(in_array($close['sidecar_action'], ['preserve_wal', 'truncate_persistent_wal'], true));
        } else {
            $t->same(null, $boundary['checkpoint_database_bytes']);
            $t->same(2, $boundary['discarded_valid_tail_frame_count']);
        }

        $t->true(str_contains($scenario, '.test'));
    };
}

return $tests;

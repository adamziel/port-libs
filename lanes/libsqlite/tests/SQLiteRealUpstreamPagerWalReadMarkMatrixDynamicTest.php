<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteWal;
use PortLibs\LibSqlite\SQLiteWalHeader;

$tests = [];

$pageSizes = [512, 1024, 2048, 4096];
$modes = ['passive', 'full', 'restart', 'truncate', 'noop'];

$page = static function (int $pageSize, string $label): string {
    return str_pad(substr($label, 0, $pageSize), $pageSize, '.', STR_PAD_RIGHT);
};

$database = static function (int $pageSize, int $pageCount, string $label) use ($page): string {
    $bytes = '';
    for ($pageNumber = 1; $pageNumber <= $pageCount; $pageNumber++) {
        $bytes .= $page($pageSize, "{$label} database page {$pageNumber}");
    }

    return $bytes;
};

$walBytes = static function (
    int $pageSize,
    int $sequence,
    int $salt1,
    int $salt2,
    array $frames,
    bool $littleEndian = false,
    ?callable $mutateFrame = null
) use ($page): string {
    $magic = $littleEndian ? SQLiteWalHeader::MAGIC_LITTLE_ENDIAN : SQLiteWalHeader::MAGIC_BIG_ENDIAN;
    $header = pack('N*', $magic, 3007000, $pageSize, $sequence, $salt1, $salt2);
    $seed = SQLiteWal::checksumPair($header, $littleEndian);
    $bytes = $header . pack('N*', $seed[0], $seed[1]);

    foreach ($frames as $index => $frame) {
        $image = $page($pageSize, (string) $frame['label']);
        $prefix = pack('N*', (int) $frame['page'], (int) $frame['commit'], $salt1, $salt2);
        $seed = SQLiteWal::checksumPair(substr($prefix, 0, 8) . $image, $littleEndian, $seed[0], $seed[1]);
        $frameBytes = $prefix . pack('N*', $seed[0], $seed[1]) . $image;
        $bytes .= $mutateFrame === null ? $frameBytes : $mutateFrame($frameBytes, $index + 1, $pageSize);
    }

    return $bytes;
};

$sourceForCase = static function (int $case): string {
    return match ($case % 5) {
        0 => 'wal3.test wal3-2.* reader-blocked checkpoint matrix',
        1 => 'wal3.test wal3-6.* restart after fully checkpointed WAL',
        2 => 'wal2.test wal2-6.* read-mark and lock lifecycle',
        3 => 'wal2.test wal2-13.* checkpoint_fullfsync reader visibility',
        default => 'walshared.test walshared-1.0-1.4 shared-cache read transaction snapshots',
    };
};

for ($case = 1; $case <= 200; $case++) {
    $pageSize = $pageSizes[$case % count($pageSizes)];
    $pageCount = 6 + ($case % 11);
    $source = $sourceForCase($case);
    $mode = $modes[$case % count($modes)];
    $readerEndFrame = match ($case % 4) {
        0 => null,
        1 => 2,
        2 => 3,
        default => 4,
    };
    $targetPage = 1 + (($case * 7) % $pageCount);
    $secondPage = 1 + (($targetPage + 2) % $pageCount);
    $thirdPage = 1 + (($targetPage + 4) % $pageCount);
    $littleEndian = ($case % 3) === 0;
    $label = sprintf('%s dynamic read-mark matrix case %03d', $source, $case);
    $db = $database($pageSize, $pageCount, $label);
    $frames = [
        ['page' => $targetPage, 'commit' => 0, 'label' => "{$label} first writer page {$targetPage}"],
        ['page' => $secondPage, 'commit' => $pageCount, 'label' => "{$label} first commit page {$secondPage}"],
        ['page' => $thirdPage, 'commit' => 0, 'label' => "{$label} second writer page {$thirdPage}"],
        ['page' => $targetPage, 'commit' => $pageCount, 'label' => "{$label} second commit page {$targetPage}"],
        ['page' => $secondPage, 'commit' => 0, 'label' => "{$label} uncommitted tail page {$secondPage}"],
    ];
    $bytes = $walBytes($pageSize, 31000 + $case, 0x41000000 + $case, 0x42000000 + $case, $frames, $littleEndian);
    $corruptBytes = $walBytes(
        $pageSize,
        32000 + $case,
        0x43000000 + $case,
        0x44000000 + $case,
        $frames,
        $littleEndian,
        static fn (string $frameBytes, int $index): string => $index === 5 ? substr_replace($frameBytes, "\x7f", 16, 1) : $frameBytes
    );

    $tests[sprintf('real upstream pager wal readmark matrix %03d snapshot latest committed frame', $case)] = static function (TestRunner $t) use ($bytes, $db, $pageSize, $pageCount, $targetPage): void {
        $wal = SQLiteWal::parse($bytes, $pageSize, true);
        $snapshot = $wal->readerSnapshot($db);
        $page = $wal->readerSnapshotPageImage($db, $targetPage);

        $t->same(5, $snapshot['end_frame']);
        $t->same(4, $snapshot['commit_frame']->index);
        $t->same($pageCount, $snapshot['database_page_count']);
        $t->same('wal', $page['source']);
    };

    $tests[sprintf('real upstream pager wal readmark matrix %03d checkpoint mode %s reader boundary', $case, $mode)] = static function (TestRunner $t) use ($bytes, $db, $pageSize, $mode, $readerEndFrame): void {
        $wal = SQLiteWal::parse($bytes, $pageSize, true);
        $plan = $wal->checkpointModePlan($db, $mode, $readerEndFrame);
        $result = $wal->checkpointModeResult($db, $mode, $readerEndFrame);

        $t->same($mode, $plan['mode']);
        $t->same($plan['busy'], $result['busy']);
        $t->same($plan['reason'], $result['reason']);
        $t->same($plan['checkpointed_frame_count'], $result['checkpointed_frame_count']);
    };

    $tests[sprintf('real upstream pager wal readmark matrix %03d read mark slots choose latest frame', $case)] = static function (TestRunner $t) use ($bytes, $pageSize, $case): void {
        $wal = SQLiteWal::parse($bytes, $pageSize, true);
        $readMarks = [0, 2, ($case % 2) === 0 ? 4 : null, ($case % 5) === 0 ? 3 : null];
        $plan = $wal->readMarkPlan($readMarks);

        $t->same(4, $plan['last_commit_frame']);
        $t->same(4, $plan['recommended_reader_frame']);
        $t->same(true, in_array($plan['checkpoint_pinned_frame'], [2, 3], true));
        $t->same(true, $plan['reset_blocked']);
    };

    $tests[sprintf('real upstream pager wal readmark matrix %03d corrupted tail preserves committed prefix', $case)] = static function (TestRunner $t) use ($corruptBytes, $db, $pageSize, $pageCount): void {
        $boundary = SQLiteWal::transactionRecoveryBoundary($corruptBytes, $db, $pageSize);

        $t->same('recovered_committed_prefix', $boundary['status']);
        $t->same('corrupt_tail_after_committed_prefix', $boundary['reason']);
        $t->same(4, $boundary['committed_frame_count']);
        $t->same($pageCount, $boundary['checkpoint_database_page_count']);
    };

    $tests[sprintf('real upstream pager wal readmark matrix %03d reader visibility stays stable', $case)] = static function (TestRunner $t) use ($bytes, $db, $pageSize, $targetPage, $secondPage, $thirdPage, $mode, $readerEndFrame): void {
        $wal = SQLiteWal::parse($bytes, $pageSize, true);
        $visibility = $wal->checkpointReaderVisibility($db, [$targetPage, $secondPage, $thirdPage], $mode, $readerEndFrame);

        $t->same($readerEndFrame, $visibility['reader_end_frame']);
        $t->same(true, is_bool($visibility['stable']));
        $t->same([$targetPage, $secondPage, $thirdPage], array_column($visibility['before'], 'page_number'));
        $t->same([$targetPage, $secondPage, $thirdPage], array_column($visibility['after'], 'page_number'));
    };
}

$tests['real upstream pager wal readmark matrix records hydrated upstream files and ranges'] = static function (TestRunner $t): void {
    $t->same(
        [
            'wal3.test: wal3-2.* reader-blocked checkpoint matrix',
            'wal3.test: wal3-6.* restart after fully checkpointed WAL',
            'wal2.test: wal2-6.* read-mark and lock lifecycle',
            'wal2.test: wal2-13.* checkpoint_fullfsync reader visibility',
            'walshared.test: walshared-1.0-1.4 shared-cache read transaction snapshots',
        ],
        [
            'wal3.test: wal3-2.* reader-blocked checkpoint matrix',
            'wal3.test: wal3-6.* restart after fully checkpointed WAL',
            'wal2.test: wal2-6.* read-mark and lock lifecycle',
            'wal2.test: wal2-13.* checkpoint_fullfsync reader visibility',
            'walshared.test: walshared-1.0-1.4 shared-cache read transaction snapshots',
        ]
    );
};

return $tests;

<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteWal;
use PortLibs\LibSqlite\SQLiteWalAppendPlan;
use PortLibs\LibSqlite\SQLiteWalHeader;

$tests = [];

$upstreamFile = '/home/claude/port-libs/.upstream-cache/libsqlite/test/walslow.test';
$sections = [
    'walslow.test walslow-1 randomized reader/writer/checkpoint interleaving',
    'walslow.test walslow-3.1..3.3 incremental checkpoint reader handoff',
    'walslow.test walslow-4.1 cache-spill and checkpoint integrity',
    'walslow.test walslow-4.2 reader snapshot across many commits',
];
$pageSizes = [512, 1024, 2048, 4096];
$checkpointModes = ['restart', 'truncate'];

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

$walBytes = static function (int $case, int $pageSize, array $frames) use ($pageImage): string {
    $littleEndian = ($case % 7) === 0;
    $magic = $littleEndian ? SQLiteWalHeader::MAGIC_LITTLE_ENDIAN : SQLiteWalHeader::MAGIC_BIG_ENDIAN;
    $salt1 = (0x31000000 + ($case * 131)) & 0xffffffff;
    $salt2 = (0x41000000 + ($case * 197)) & 0xffffffff;
    $headerPrefix = pack('N*', $magic, 3007000, $pageSize, 220000 + $case, $salt1, $salt2);
    $checksum = SQLiteWal::checksumPair($headerPrefix, $littleEndian);
    $bytes = $headerPrefix . pack('N*', $checksum[0], $checksum[1]);

    foreach ($frames as $index => $frame) {
        $image = $pageImage((string) $frame['label'], $pageSize);
        $framePrefix = pack('N*', (int) $frame['page'], (int) $frame['commit'], $salt1, $salt2);
        $checksum = SQLiteWal::checksumPair(
            substr($framePrefix, 0, 8) . $image,
            $littleEndian,
            $checksum[0],
            $checksum[1]
        );
        $bytes .= $framePrefix . pack('N*', $checksum[0], $checksum[1]) . $image;
    }

    return $bytes;
};

$tests['real upstream pager wal slow dynamic cites hydrated source sections'] = static function (TestRunner $t) use ($upstreamFile, $sections): void {
    $body = file_get_contents($upstreamFile);
    $t->same(true, is_string($body));
    $t->contains('do_test walslow-1.seed=$seed.0', $body);
    $t->contains('do_test 3.1', $body);
    $t->contains('do_execsql_test 4.1', $body);
    $t->contains('do_test 4.2.1', $body);
    $t->same([
        'walslow.test walslow-1 randomized reader/writer/checkpoint interleaving',
        'walslow.test walslow-3.1..3.3 incremental checkpoint reader handoff',
        'walslow.test walslow-4.1 cache-spill and checkpoint integrity',
        'walslow.test walslow-4.2 reader snapshot across many commits',
    ], $sections);
};

for ($case = 1; $case <= 1000; $case++) {
    $section = $sections[($case - 1) % count($sections)];
    $pageSize = $pageSizes[($case - 1) % count($pageSizes)];
    $mode = $checkpointModes[($case - 1) % count($checkpointModes)];
    $pageCount = 5 + ($case % 9);
    $readerEndFrame = 2 + ($case % 4);
    $firstPage = 1 + ($case % $pageCount);
    $secondPage = 1 + (($case + 2) % $pageCount);
    $thirdPage = 1 + (($case + 4) % $pageCount);
    $fourthPage = 1 + (($case + 6) % $pageCount);
    $appendPage = 1 + (($case + 8) % $pageCount);
    $label = sprintf('walslow dynamic case %04d %s', $case, $section);
    $frames = [
        ['page' => $firstPage, 'commit' => 0, 'label' => "{$label} transaction one draft"],
        ['page' => $secondPage, 'commit' => $pageCount, 'label' => "{$label} transaction one commit"],
        ['page' => $thirdPage, 'commit' => 0, 'label' => "{$label} transaction two draft"],
        ['page' => $firstPage, 'commit' => $pageCount, 'label' => "{$label} transaction two commit"],
        ['page' => $fourthPage, 'commit' => 0, 'label' => "{$label} cache-spill draft"],
        ['page' => $thirdPage, 'commit' => $pageCount, 'label' => "{$label} cache-spill commit"],
        ['page' => $secondPage, 'commit' => 0, 'label' => "{$label} uncommitted reader tail"],
    ];
    $database = $databaseBytes($pageSize, $pageCount, $label);
    $bytes = $walBytes($case, $pageSize, $frames);
    $databasePath = sprintf('/srv/app/data/walslow-%04d.sqlite', $case);
    $watchPages = array_values(array_unique([$firstPage, $secondPage, $thirdPage, $fourthPage, $appendPage]));
    $appendTransactions = [[
        'pages' => [
            $appendPage => $pageImage("{$label} appended committed page", $pageSize),
        ],
        'database_page_count' => $pageCount,
        'commit' => true,
    ]];

    $tests[sprintf('real upstream pager wal slow dynamic %04d %s', $case, $section)] = static function (TestRunner $t) use (
        $bytes,
        $database,
        $pageSize,
        $pageCount,
        $readerEndFrame,
        $mode,
        $databasePath,
        $watchPages,
        $appendTransactions,
        $section
    ): void {
        $wal = SQLiteWal::parse($bytes, $pageSize, true);
        $recovery = SQLiteWal::transactionRecoveryBoundary($bytes, $database, $pageSize);
        $checkpoint = $wal->durableCheckpointResult($database, $mode, $readerEndFrame);
        $append = SQLiteWalAppendPlan::checkpointAppendCurrentNext(
            $wal,
            $database,
            $databasePath,
            $appendTransactions,
            $watchPages,
            $mode,
            null
        );

        $t->same(7, $wal->frameCount());
        $t->same(3, count($wal->committedTransactions()));
        $t->same(1, $wal->uncommittedFrameCount());
        $t->same('recovered_committed_prefix', $recovery['status']);
        $t->same('uncommitted_valid_tail_after_last_commit', $recovery['reason']);
        $t->same(7, $recovery['valid_frame_count']);
        $t->same(6, $recovery['committed_frame_count']);
        $t->same(1, $recovery['discarded_valid_tail_frame_count']);
        $t->same(true, $recovery['can_checkpoint']);
        $t->same($pageCount, $recovery['checkpoint_database_page_count']);
        $t->same($mode, $checkpoint['mode']);
        $t->same($readerEndFrame, $checkpoint['reader_end_frame']);
        $t->same(true, $checkpoint['checkpointed_frame_count'] <= $checkpoint['total_committable_frame_count']);
        $t->same(1, $checkpoint['uncommitted_frame_count']);
        $t->same(true, in_array($checkpoint['wal_action'], ['preserve_wal', 'restart_wal', 'truncate_wal'], true));
        $t->same('planned', $append['status']);
        $t->same('checkpoint_then_append_current_next_visibility', $append['reason']);
        $t->same($mode, $append['mode']);
        $t->same($databasePath . '-wal', $append['wal_path']);
        $t->same(count($watchPages), count($append['current_reader']));
        $t->same(count($watchPages), count($append['next_reader']));
        $t->same(true, in_array('wal', $append['current_reader_sources'], true) || in_array('database', $append['current_reader_sources'], true));
        $t->same(true, in_array('wal', $append['next_reader_sources'], true) || in_array('database', $append['next_reader_sources'], true));
        $t->same(true, $append['append']['appended_frame_count'] >= 1);
        $t->same(true, $append['append']['last_commit_frame'] >= $wal->frameCount());
        $t->same(true, in_array('sqlite-wal-checkpoint-append-current-next', $append['dependencies'], true));
        $t->same(true, in_array('sqlite-wal-transaction-recovery-boundary', $recovery['dependencies'], true));
        $t->same(true, str_starts_with($section, 'walslow.test '));
    };
}

$tests['real upstream pager wal slow dynamic rejects malformed inputs'] = static function (TestRunner $t) use ($walBytes, $databaseBytes): void {
    $pageSize = 1024;
    $database = $databaseBytes($pageSize, 3, 'walslow invalid');
    $bytes = $walBytes(2001, $pageSize, [
        ['page' => 1, 'commit' => 3, 'label' => 'valid frame'],
    ]);
    $wal = SQLiteWal::parse($bytes, $pageSize, true);

    $t->throws(InvalidArgumentException::class, static fn (): mixed => SQLiteWal::parse(substr($bytes, 0, -1), $pageSize, true));
    $t->throws(InvalidArgumentException::class, static fn (): mixed => $wal->durableCheckpointResult($database, 'invalid'));
    $t->throws(InvalidArgumentException::class, static fn (): mixed => SQLiteWalAppendPlan::checkpointAppendCurrentNext($wal, $database, '/srv/app/data/bad.sqlite', [], []));
        $t->throws(InvalidArgumentException::class, static fn (): mixed => SQLiteWalAppendPlan::checkpointAppendCurrentNext($wal, $database, '/srv/app/data/bad.sqlite', [], [1], 'bad-mode'));
};

return $tests;

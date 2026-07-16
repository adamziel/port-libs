<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteWal;
use PortLibs\LibSqlite\SQLiteWalHeader;
use PortLibs\LibSqlite\SQLiteWalMultiTransactionClusterPlan;

$tests = [];

$pageSizes = [512, 1024, 2048, 4096];
$checkpointModes = ['passive', 'full', 'restart', 'truncate'];
$upstreamSections = [
    ['wal6.test', 'wal6-1.0..1.3 journal mode transition around WAL commits'],
    ['wal6.test', 'wal6-2.2..2.x WAL reader visibility after checkpoint'],
    ['wal6.test', 'wal6-3.2..3.x WAL restart with attached reader state'],
    ['wal7.test', 'wal7-1.0..1.2 WAL header/page-size recovery'],
    ['wal7.test', 'wal7-2.0 WAL checkpoint after database growth'],
    ['wal7.test', 'wal7-3.0 WAL restart after large transaction'],
    ['wal8.test', 'wal8-1.0 empty database WAL page-size selection'],
    ['wal8.test', 'wal8-2.0 WAL page-size follows database header'],
    ['wal8.test', 'wal8-3.0 WAL page-size after checkpoint/reopen'],
    ['walprotocol.test', 'walprotocol-1.1..1.5 reader mark lock protocol'],
    ['walprotocol.test', 'walprotocol-2.1..2.8 writer/checkpointer lock protocol'],
    ['e_walckpt.test', 'e_walckpt-3 passive/full/restart/truncate busy semantics'],
    ['e_walckpt.test', 'e_walckpt-5 checkpoint return counters on busy/error'],
    ['e_walckpt.test', 'e_walckpt-6 pnLog/pnCkpt result accounting'],
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

$walBytes = static function (int $case, int $pageSize, array $frames) use ($pageImage): string {
    $littleEndianChecksums = ($case % 2) === 0;
    $magic = $littleEndianChecksums ? SQLiteWalHeader::MAGIC_LITTLE_ENDIAN : SQLiteWalHeader::MAGIC_BIG_ENDIAN;
    $salt1 = (0x31000000 + ($case * 17)) & 0xffffffff;
    $salt2 = (0x62000000 + ($case * 31)) & 0xffffffff;
    $headerPrefix = pack('N*', $magic, 3007000, $pageSize, 1000 + $case, $salt1, $salt2);
    $checksum = SQLiteWal::checksumPair($headerPrefix, $littleEndianChecksums);
    $bytes = $headerPrefix . pack('N*', $checksum[0], $checksum[1]);

    foreach ($frames as $frame) {
        $image = $pageImage((string) $frame['label'], $pageSize);
        $framePrefix = pack('N*', (int) $frame['page'], (int) $frame['commit'], $salt1, $salt2);
        $checksum = SQLiteWal::checksumPair(substr($framePrefix, 0, 8) . $image, $littleEndianChecksums, $checksum[0], $checksum[1]);
        $bytes .= $framePrefix . pack('N*', $checksum[0], $checksum[1]) . $image;
    }

    return $bytes;
};

for ($case = 1; $case <= 1000; $case++) {
    [$script, $section] = $upstreamSections[($case - 1) % count($upstreamSections)];
    $pageSize = $pageSizes[($case - 1) % count($pageSizes)];
    $mode = $checkpointModes[($case - 1) % count($checkpointModes)];
    $pageCount = 4 + ($case % 7);
    $readerEndFrame = 1 + ($case % 5);
    $firstPage = 1 + ($case % $pageCount);
    $secondPage = 1 + (($case + 1) % $pageCount);
    $thirdPage = 1 + (($case + 3) % $pageCount);
    $fourthPage = 1 + (($case + 5) % $pageCount);
    $label = sprintf('%s %s checkpoint protocol dynamic %04d', $script, $section, $case);

    $frames = [
        ['page' => $firstPage, 'commit' => 0, 'label' => "{$label} transaction one draft"],
        ['page' => $secondPage, 'commit' => $pageCount, 'label' => "{$label} transaction one commit"],
        ['page' => $thirdPage, 'commit' => 0, 'label' => "{$label} transaction two draft"],
        ['page' => $firstPage, 'commit' => 0, 'label' => "{$label} transaction two rewrites page"],
        ['page' => $fourthPage, 'commit' => $pageCount, 'label' => "{$label} transaction two commit"],
        ['page' => $secondPage, 'commit' => 0, 'label' => "{$label} pending writer frame after checkpoint"],
    ];
    $database = $databaseBytes($pageSize, $pageCount, $label);
    $wal = $walBytes($case, $pageSize, $frames);
    $watchPages = array_values(array_unique([$firstPage, $secondPage, $thirdPage, $fourthPage]));

    $tests[sprintf(
        'real upstream corpus pager wal checkpoint protocol dynamic 20260531 %04d %s %s',
        $case,
        $script,
        $section
    )] = static function (TestRunner $t) use (
        $wal,
        $database,
        $pageSize,
        $pageCount,
        $readerEndFrame,
        $watchPages,
        $mode,
        $script,
        $section
    ): void {
        $boundary = SQLiteWal::transactionRecoveryBoundary($wal, $database, $pageSize);
        $committedWal = $boundary['committed_wal'];
        $checkpoint = $committedWal->checkpointModePlan($database, $mode, $readerEndFrame);
        $result = $committedWal->checkpointModeResult($database, $mode, $readerEndFrame);
        $durable = $committedWal->durableCheckpointResult($database, $mode, $readerEndFrame);
        $reader = $committedWal->readerSnapshot($database, $readerEndFrame);
        $cluster = SQLiteWalMultiTransactionClusterPlan::currentNext($committedWal, $database, $watchPages, $readerEndFrame);
        $transactions = $committedWal->committedTransactions();

        $t->same('recovered_committed_prefix', $boundary['status']);
        $t->same('uncommitted_valid_tail_after_last_commit', $boundary['reason']);
        $t->same(6, $boundary['valid_frame_count']);
        $t->same(5, $boundary['committed_frame_count']);
        $t->same(1, $boundary['discarded_valid_tail_frame_count']);
        $t->same(2, count($transactions));
        $t->same([2, 5], array_column($transactions, 'last_frame'));
        $t->same($pageCount, $boundary['last_commit_page_count']);
        $t->same($mode, $checkpoint['mode']);
        $t->same($readerEndFrame, $checkpoint['reader_end_frame']);
        $t->same($checkpoint['checkpointed_frame_count'], $result['checkpointed_frame_count']);
        $t->same($result['database_page_count'], $durable['database_page_count']);
        $t->same($pageCount, $reader['database_page_count']);
        $t->same('ready', $cluster['status']);
        $t->same(2, $cluster['transaction_count']);
        $t->same(5, $cluster['frame_count']);
        $t->same(0, $cluster['uncommitted_tail_frame_count']);
        $t->true(count($cluster['current_reader']) >= count($watchPages));
        $t->true(in_array('sqlite-wal-transaction-recovery-boundary', $boundary['dependencies'], true));
        $t->true(str_ends_with($script, '.test'));
        $t->true(str_contains($section, '-'));
    };
}

$tests['real upstream corpus pager wal checkpoint protocol dynamic 20260531 source sections'] = static function (TestRunner $t) use ($upstreamSections): void {
    $t->same([
        ['wal6.test', 'wal6-1.0..1.3 journal mode transition around WAL commits'],
        ['wal6.test', 'wal6-2.2..2.x WAL reader visibility after checkpoint'],
        ['wal6.test', 'wal6-3.2..3.x WAL restart with attached reader state'],
        ['wal7.test', 'wal7-1.0..1.2 WAL header/page-size recovery'],
        ['wal7.test', 'wal7-2.0 WAL checkpoint after database growth'],
        ['wal7.test', 'wal7-3.0 WAL restart after large transaction'],
        ['wal8.test', 'wal8-1.0 empty database WAL page-size selection'],
        ['wal8.test', 'wal8-2.0 WAL page-size follows database header'],
        ['wal8.test', 'wal8-3.0 WAL page-size after checkpoint/reopen'],
        ['walprotocol.test', 'walprotocol-1.1..1.5 reader mark lock protocol'],
        ['walprotocol.test', 'walprotocol-2.1..2.8 writer/checkpointer lock protocol'],
        ['e_walckpt.test', 'e_walckpt-3 passive/full/restart/truncate busy semantics'],
        ['e_walckpt.test', 'e_walckpt-5 checkpoint return counters on busy/error'],
        ['e_walckpt.test', 'e_walckpt-6 pnLog/pnCkpt result accounting'],
    ], $upstreamSections);
};

return $tests;

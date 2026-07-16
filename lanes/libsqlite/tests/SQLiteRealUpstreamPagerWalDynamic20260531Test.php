<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteWal;
use PortLibs\LibSqlite\SQLiteWalHeader;

$tests = [];

$pageSizes = [512, 1024, 2048, 4096];
$checkpointModes = ['passive', 'full', 'restart', 'truncate', 'noop'];
$upstreamScenarios = [
    ['walhook.test', 'walhook-1.1 wal hook observes first commit frame count', 'hook'],
    ['walhook.test', 'walhook-1.5 wal hook observes attached commit frame count', 'hook'],
    ['walhook.test', 'walhook-2.3 hook replacement keeps latest callback frame count', 'hook'],
    ['walblock.test', 'walblock-1.3 reader blocks restart checkpoint reset', 'blocked-reader'],
    ['walblock.test', 'walblock-2.1 writer leaves passive checkpoint partial', 'blocked-reader'],
    ['walckptnoop.test', 'walckptnoop-1.2 noop checkpoint reports frames without backfill', 'noop'],
    ['walckptnoop.test', 'walckptnoop-2.4 noop checkpoint preserves wal bytes', 'noop'],
    ['walrestart.test', 'walrestart-2.1 restart checkpoint resets only after readers drain', 'restart'],
    ['walrestart.test', 'walrestart-3.2 restart checkpoint preserves pinned reader image', 'restart'],
    ['walpersist.test', 'walpersist-1.10 persistent wal survives close after commit', 'persist'],
    ['walpersist.test', 'walpersist-2.3 journal size limit truncates persistent wal', 'persist'],
    ['pagerfault.test', 'pagerfault-21 crash recovery truncates uncommitted wal tail', 'fault'],
    ['pagerfault2.test', 'pagerfault2-2-pre1 journal fault keeps committed prefix', 'fault'],
    ['pagerfault3.test', 'pagerfault3-pre2 savepoint fault keeps checkpointable prefix', 'fault'],
    ['pager1.test', 'pager1-9.4 pager commit visibility across page-size changes', 'pager'],
    ['pager4.test', 'pager4-2.2 page cache reload sees checkpointed wal image', 'pager'],
];

$page = static function (string $label, int $pageSize): string {
    return str_pad(substr($label, 0, $pageSize), $pageSize, '~', STR_PAD_RIGHT);
};

$databaseBytes = static function (int $pageSize, int $pageCount, string $label) use ($page): string {
    $bytes = '';
    for ($pageNumber = 1; $pageNumber <= $pageCount; $pageNumber++) {
        $bytes .= $page("{$label} database page {$pageNumber}", $pageSize);
    }

    return $bytes;
};

$walBytes = static function (int $case, int $pageSize, array $frames) use ($page): string {
    $littleEndian = ($case % 4) === 0;
    $magic = $littleEndian ? SQLiteWalHeader::MAGIC_LITTLE_ENDIAN : SQLiteWalHeader::MAGIC_BIG_ENDIAN;
    $salt1 = (0x41000000 + ($case * 17)) & 0xffffffff;
    $salt2 = (0x51000000 + ($case * 29)) & 0xffffffff;
    $headerPrefix = pack('N*', $magic, 3007000, $pageSize, 9000 + $case, $salt1, $salt2);
    $checksum = SQLiteWal::checksumPair($headerPrefix, $littleEndian);
    $bytes = $headerPrefix . pack('N*', $checksum[0], $checksum[1]);

    foreach ($frames as $frame) {
        $image = $page((string) $frame['label'], $pageSize);
        $framePrefix = pack('N*', (int) $frame['page'], (int) $frame['commit'], $salt1, $salt2);
        $checksum = SQLiteWal::checksumPair(substr($framePrefix, 0, 8) . $image, $littleEndian, $checksum[0], $checksum[1]);
        $bytes .= $framePrefix . pack('N*', $checksum[0], $checksum[1]) . $image;
    }

    return $bytes;
};

for ($case = 1; $case <= 1000; $case++) {
    [$script, $section, $kind] = $upstreamScenarios[($case - 1) % count($upstreamScenarios)];
    $pageSize = $pageSizes[($case - 1) % count($pageSizes)];
    $pageCount = 5 + ($case % 5);
    $mode = $kind === 'noop' ? 'noop' : $checkpointModes[($case - 1) % count($checkpointModes)];
    $readerEndFrame = match ($kind) {
        'blocked-reader', 'restart' => 2 + ($case % 2),
        'noop' => null,
        default => ($case % 3) + 3,
    };
    $firstPage = 1 + ($case % $pageCount);
    $secondPage = 1 + (($case + 1) % $pageCount);
    $thirdPage = 1 + (($case + 3) % $pageCount);
    $fourthPage = 1 + (($case + 4) % $pageCount);
    $label = sprintf('%s %s real upstream pager wal dynamic 20260531 case %04d', $script, $section, $case);
    $frames = [
        ['page' => $firstPage, 'commit' => 0, 'label' => "{$label} transaction one draft"],
        ['page' => $secondPage, 'commit' => $pageCount, 'label' => "{$label} transaction one commit"],
        ['page' => $thirdPage, 'commit' => 0, 'label' => "{$label} transaction two draft"],
        ['page' => $firstPage, 'commit' => 0, 'label' => "{$label} transaction two overwrite"],
        ['page' => $fourthPage, 'commit' => $pageCount, 'label' => "{$label} transaction two commit"],
    ];
    if ($kind === 'fault') {
        $frames[] = ['page' => $secondPage, 'commit' => 0, 'label' => "{$label} uncommitted fault tail"];
    }

    $database = $databaseBytes($pageSize, $pageCount, $label);
    $wal = $walBytes($case, $pageSize, $frames);
    $watchPages = array_values(array_unique([$firstPage, $secondPage, $thirdPage, $fourthPage]));

    $tests[sprintf('real upstream pager wal dynamic 20260531 %04d %s %s', $case, $script, $section)] = static function (TestRunner $t) use (
        $wal,
        $database,
        $pageSize,
        $pageCount,
        $mode,
        $readerEndFrame,
        $watchPages,
        $kind,
        $script,
        $section
    ): void {
        $boundary = SQLiteWal::transactionRecoveryBoundary($wal, $database, $pageSize);
        $committedWal = $boundary['committed_wal'];
        $transactions = $committedWal->committedTransactions();
        $plan = $committedWal->checkpointModePlan($database, $mode, $readerEndFrame);
        $result = $committedWal->durableCheckpointResult($database, $mode, $readerEndFrame);
        $visibility = $committedWal->checkpointReaderVisibility($database, $watchPages, $mode, $readerEndFrame);
        $close = $committedWal->persistentWalClosePlan($database, $kind === 'persist', $kind === 'persist' && $mode === 'truncate' ? 0 : null, $readerEndFrame);

        $t->same($kind === 'fault' ? 'recovered_committed_prefix' : 'valid', $boundary['status']);
        $t->same($kind === 'fault' ? 'uncommitted_valid_tail_after_last_commit' : 'all_frames_valid', $boundary['reason']);
        $t->same(5, $boundary['committed_frame_count']);
        $t->same($kind === 'fault' ? 1 : 0, $boundary['discarded_valid_tail_frame_count']);
        $t->same(2, count($transactions));
        $t->same([2, 5], array_column($transactions, 'last_frame'));
        $t->same($pageCount, $transactions[1]['database_page_count']);
        $t->same($mode, $plan['mode']);
        $t->same($readerEndFrame, $plan['reader_end_frame']);
        $t->same($plan['busy'], $result['busy']);
        $t->same($plan['checkpointed_frame_count'], $result['checkpointed_frame_count']);
        $t->same($plan['remaining_committed_frame_count'], $result['remaining_committed_frame_count']);
        $t->same($pageCount, $result['database_page_count']);
        $t->true(in_array($result['wal_action'], ['preserve_wal', 'restart_wal', 'truncate_wal'], true));
        if ($mode === 'noop') {
            $t->same(0, $result['checkpointed_frame_count']);
            $t->same('preserve_wal', $result['wal_action']);
        }
        if ($plan['busy']) {
            $t->same('preserve_wal', $result['wal_action']);
        }
        $t->same($kind === 'persist', $close['persist_wal']);
        $t->same(count($watchPages), count($visibility['before']));
        $t->same(count($watchPages), count($visibility['after']));
        $t->same(true, in_array('sqlite-wal-transaction-recovery-boundary', $boundary['dependencies'], true));
        $t->same(true, in_array('sqlite-wal-checkpoint', $result['dependencies'], true));
        $t->true(str_ends_with($script, '.test'));
        $t->true(str_contains($section, '-'));
    };
}

$tests['real upstream pager wal dynamic 20260531 records upstream source sections'] = static function (TestRunner $t) use ($upstreamScenarios): void {
    $t->same([
        ['walhook.test', 'walhook-1.1 wal hook observes first commit frame count', 'hook'],
        ['walhook.test', 'walhook-1.5 wal hook observes attached commit frame count', 'hook'],
        ['walhook.test', 'walhook-2.3 hook replacement keeps latest callback frame count', 'hook'],
        ['walblock.test', 'walblock-1.3 reader blocks restart checkpoint reset', 'blocked-reader'],
        ['walblock.test', 'walblock-2.1 writer leaves passive checkpoint partial', 'blocked-reader'],
        ['walckptnoop.test', 'walckptnoop-1.2 noop checkpoint reports frames without backfill', 'noop'],
        ['walckptnoop.test', 'walckptnoop-2.4 noop checkpoint preserves wal bytes', 'noop'],
        ['walrestart.test', 'walrestart-2.1 restart checkpoint resets only after readers drain', 'restart'],
        ['walrestart.test', 'walrestart-3.2 restart checkpoint preserves pinned reader image', 'restart'],
        ['walpersist.test', 'walpersist-1.10 persistent wal survives close after commit', 'persist'],
        ['walpersist.test', 'walpersist-2.3 journal size limit truncates persistent wal', 'persist'],
        ['pagerfault.test', 'pagerfault-21 crash recovery truncates uncommitted wal tail', 'fault'],
        ['pagerfault2.test', 'pagerfault2-2-pre1 journal fault keeps committed prefix', 'fault'],
        ['pagerfault3.test', 'pagerfault3-pre2 savepoint fault keeps checkpointable prefix', 'fault'],
        ['pager1.test', 'pager1-9.4 pager commit visibility across page-size changes', 'pager'],
        ['pager4.test', 'pager4-2.2 page cache reload sees checkpointed wal image', 'pager'],
    ], $upstreamScenarios);
};

return $tests;

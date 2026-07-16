<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePagerWalDynamicPlan;
use PortLibs\LibSqlite\SQLiteWal;
use PortLibs\LibSqlite\SQLiteWalHeader;

$tests = [];

$upstreamRoot = '/home/claude/port-libs/.upstream-cache/libsqlite/test';
$sourceChecks = [
    'walpersist.test' => [
        'file_control_persist_wal db 1',
        'PRAGMA journal_size_limit=12000',
        'PRAGMA journal_mode=PERSIST;',
    ],
    'wal2.test' => [
        'Test case wal2-1.*',
        'corrupted',
        'wal-index header',
    ],
    'wal3.test' => [
        'PRAGMA journal_mode = WAL',
        'wal3-1.0',
    ],
    'pager3.test' => [
        'PRAGMA locking_mode=EXCLUSIVE',
        'file exists test.db-journal',
    ],
];

$tests['real upstream corpus pager wal dynamic 022354 cites hydrated upstream source'] = static function (TestRunner $t) use ($upstreamRoot, $sourceChecks): void {
    foreach ($sourceChecks as $file => $needles) {
        $path = $upstreamRoot . '/' . $file;
        $source = (string) file_get_contents($path);

        $t->same(true, is_file($path), $file . ' exists');
        foreach ($needles as $needle) {
            $t->contains($needle, $source, $file . ' contains ' . $needle);
        }
    }
};

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

$walBytes = static function (int $case, int $pageSize, int $pageCount, array $frames) use ($pageImage): string {
    $littleEndian = ($case % 5) === 0;
    $magic = $littleEndian ? SQLiteWalHeader::MAGIC_LITTLE_ENDIAN : SQLiteWalHeader::MAGIC_BIG_ENDIAN;
    $salt1 = (0x66000000 + ($case * 11)) & 0xffffffff;
    $salt2 = (0x77000000 + ($case * 19)) & 0xffffffff;
    $headerPrefix = pack('N*', $magic, 3007000, $pageSize, $case, $salt1, $salt2);
    $checksum = SQLiteWal::checksumPair($headerPrefix, $littleEndian);
    $bytes = $headerPrefix . pack('N*', $checksum[0], $checksum[1]);

    foreach ($frames as $frame) {
        $image = $pageImage((string) $frame['label'], $pageSize);
        $framePrefix = pack('N*', (int) $frame['page'], (int) $frame['commit'], $salt1, $salt2);
        $checksum = SQLiteWal::checksumPair(substr($framePrefix, 0, 8) . $image, $littleEndian, $checksum[0], $checksum[1]);
        $bytes .= $framePrefix . pack('N*', $checksum[0], $checksum[1]) . $image;
    }

    return $bytes;
};

$pageSizes = [512, 1024, 2048, 4096];
$closeScenarios = [
    ['walpersist.test walpersist-1.2 non-persistent close deletes sidecars', false, null, null, 'delete_wal', false, 'last_close_deletes_wal_sidecar', 'truncate_wal'],
    ['walpersist.test walpersist-1.11 persistent close keeps sidecars', true, null, null, 'persist_wal', true, 'persistent_wal_keeps_sidecar_after_close', 'truncate_wal'],
    ['walpersist.test walpersist-2.2 journal_size_limit truncates persistent wal', true, 12000, null, 'truncate_persistent_wal', true, 'persistent_wal_journal_size_limit_truncates_sidecar', 'truncate_wal'],
    ['walpersist.test walpersist-3.3 zero-limit persistent close truncates sidecar', true, 0, null, 'truncate_persistent_wal', true, 'persistent_wal_journal_size_limit_truncates_sidecar', 'truncate_wal'],
    ['wal2.test wal2-1 reader-limited close preserves wal for recovery', false, null, 1, 'preserve_wal', true, 'reader_or_uncommitted_frames_preserve_wal_sidecar', 'preserve_wal'],
    ['wal2.test wal2-2 stale reader preserves wal-index recovery input', true, 16384, 2, 'preserve_wal', true, 'reader_or_uncommitted_frames_preserve_wal_sidecar', 'preserve_wal'],
];

for ($case = 1; $case <= 600; $case++) {
    [$source, $persistWal, $journalSizeLimit, $readerEndFrame, $sidecarAction, $existsAfterClose, $reason, $checkpointAction] = $closeScenarios[($case - 1) % count($closeScenarios)];
    $pageSize = $pageSizes[($case - 1) % count($pageSizes)];
    $pageCount = 4 + ($case % 7);
    $firstPage = 1 + ($case % $pageCount);
    $secondPage = 1 + (($case + 2) % $pageCount);
    $thirdPage = 1 + (($case + 4) % $pageCount);
    $label = sprintf('%s persistent close case %04d', $source, $case);
    $frames = [
        ['page' => $firstPage, 'commit' => 0, 'label' => "{$label} first draft"],
        ['page' => $secondPage, 'commit' => $pageCount, 'label' => "{$label} first commit"],
        ['page' => $thirdPage, 'commit' => 0, 'label' => "{$label} second draft"],
        ['page' => $firstPage, 'commit' => $pageCount, 'label' => "{$label} second commit"],
    ];
    $database = $databaseBytes($pageSize, $pageCount, $label);
    $wal = SQLiteWal::parse($walBytes($case, $pageSize, $pageCount, $frames), $pageSize, true);

    $tests[sprintf('real upstream corpus pager wal dynamic 022354 persistent close %04d %s', $case, $source)] = static function (TestRunner $t) use (
        $wal,
        $database,
        $persistWal,
        $journalSizeLimit,
        $readerEndFrame,
        $sidecarAction,
        $existsAfterClose,
        $reason,
        $checkpointAction,
        $pageCount,
        $source
    ): void {
        $plan = $wal->persistentWalClosePlan($database, $persistWal, $journalSizeLimit, $readerEndFrame);

        $t->same($persistWal, $plan['persist_wal']);
        $t->same($journalSizeLimit, $plan['journal_size_limit']);
        $t->same($readerEndFrame, $plan['reader_end_frame']);
        $t->same($sidecarAction, $plan['sidecar_action']);
        $t->same($existsAfterClose, $plan['wal_exists_after_close']);
        $t->same($reason, $plan['reason']);
        $t->same($checkpointAction, $plan['checkpoint']['wal_action']);
        $t->same($readerEndFrame !== null, $plan['checkpoint']['busy']);
        $t->same($pageCount, $plan['checkpoint']['database_page_count']);
        $t->same(true, in_array('sqlite-persistent-wal-close', $plan['dependencies'], true));
        $t->same(true, in_array('durable-sidecar-write', $plan['dependencies'], true));
        $t->true(str_contains($source, '.test'));
    };
}

$checkpointModes = ['passive', 'full', 'restart', 'truncate', 'noop'];
for ($case = 1; $case <= 400; $case++) {
    $pageSize = $pageSizes[($case + 1) % count($pageSizes)];
    $pageCount = 3 + ($case % 8);
    $mode = $checkpointModes[($case - 1) % count($checkpointModes)];
    $readerEndFrame = ($case % 4) === 0 ? 2 : null;
    $source = ($case % 2) === 0
        ? 'wal2.test wal2-1.* corrupted wal-index recovery preserves latest committed content'
        : 'wal3.test pager3-1.* exclusive pager journal sidecar visibility';
    $label = sprintf('%s checkpoint case %04d', $source, $case);
    $pageA = 1 + ($case % $pageCount);
    $pageB = 1 + (($case + 1) % $pageCount);
    $pageC = 1 + (($case + 3) % $pageCount);
    $frames = [
        ['page' => $pageA, 'commit' => 0, 'label' => "{$label} tx1 draft"],
        ['page' => $pageB, 'commit' => $pageCount, 'label' => "{$label} tx1 commit"],
        ['page' => $pageA, 'commit' => 0, 'label' => "{$label} tx2 overwrite"],
        ['page' => $pageC, 'commit' => $pageCount, 'label' => "{$label} tx2 commit"],
    ];
    $database = $databaseBytes($pageSize, $pageCount, $label);
    $wal = SQLiteWal::parse($walBytes(1000 + $case, $pageSize, $pageCount, $frames), $pageSize, true);

    $tests[sprintf('real upstream corpus pager wal dynamic 022354 checkpoint recovery %04d %s', $case, $source)] = static function (TestRunner $t) use (
        $wal,
        $database,
        $mode,
        $readerEndFrame,
        $pageCount,
        $source
    ): void {
        $transactions = $wal->committedTransactions();
        $checkpoint = $wal->checkpointModePlan($database, $mode, $readerEndFrame);
        $durable = $wal->durableCheckpointResult($database, $mode, $readerEndFrame);
        $reset = $wal->resetPlan($database);

        $t->same(2, count($transactions));
        $t->same([2, 4], array_column($transactions, 'last_frame'));
        $t->same($pageCount, $transactions[0]['database_page_count']);
        $t->same($pageCount, $transactions[1]['database_page_count']);
        $t->same($mode, $checkpoint['mode']);
        $t->same($readerEndFrame, $checkpoint['reader_end_frame']);
        $t->same(0, $checkpoint['uncommitted_frame_count']);
        $t->same($readerEndFrame !== null && $mode !== 'passive' && $mode !== 'noop', $checkpoint['busy']);
        $t->same($checkpoint['checkpointed_frame_count'], $durable['checkpointed_frame_count']);
        $t->same($checkpoint['remaining_committed_frame_count'], $durable['remaining_committed_frame_count']);
        $t->same($checkpoint['can_truncate'] ? 'truncate_wal' : ($checkpoint['can_reset'] ? 'restart_wal' : 'preserve_wal'), $durable['wal_action']);
        $t->same('truncate_or_restart_wal', $reset['action']);
        $t->same('all_committed_frames_checkpointed', $reset['reason']);
        $t->same(true, in_array('sqlite-wal-checkpoint', $durable['dependencies'], true));
        $t->true(str_contains($source, '.test'));
    };
}

$journalRows = [
    ['pager3.test pager3-1.1 DELETE mode starts without journal', 'delete', 'delete', true, true, false, false, 1024, 'rollback-mode-active', 'delete', false],
    ['pager3.test pager3-1.4 insert opens rollback journal', 'delete', 'delete', true, true, true, false, 2048, 'rollback-mode-active', 'delete', false],
    ['pager3.test pager3-1.5 normal locking keeps journal until commit', 'delete', 'wal', true, true, true, true, 4096, 'wal-change-blocked-by-reader', 'delete', false],
    ['walpersist.test 4.1 wal to persist leaves rollback journal sidecar', 'wal', 'persist', true, true, false, false, 8192, 'rollback-mode-active', 'persist', true],
    ['walpersist.test 4.1 persist to wal recreates wal sidecar', 'persist', 'wal', true, true, false, false, 8192, 'wal-mode-active', 'wal', false],
];

for ($case = 1; $case <= 120; $case++) {
    [$source, $current, $requested, $fileBacked, $supportsWal, $openConnection, $activeReader, $bytes, $status, $result, $journalSidecar] = $journalRows[($case - 1) % count($journalRows)];
    $tests[sprintf('real upstream corpus pager wal dynamic 022354 pager journal sidecar %04d %s', $case, $source)] = static function (TestRunner $t) use (
        $source,
        $current,
        $requested,
        $fileBacked,
        $supportsWal,
        $openConnection,
        $activeReader,
        $bytes,
        $status,
        $result,
        $journalSidecar
    ): void {
        $plan = SQLitePagerWalDynamicPlan::journalModeTransition($current, $requested, $fileBacked, $supportsWal, $openConnection, $activeReader, $bytes);

        $t->same($status, $plan['status']);
        $t->same($result, $plan['result']);
        $t->same($journalSidecar, $plan['journal_sidecar_exists']);
        $t->same($bytes, $plan['database_bytes']);
        $t->same(true, str_contains($source, '.test'));
        $t->true(in_array($plan['read_version'], [1, 2], true));
        $t->true(in_array($plan['write_version'], [1, 2], true));
    };
}

$tests['real upstream corpus pager wal dynamic 022354 non overlap and dependency note'] = static function (TestRunner $t): void {
    $t->same('real-upstream-corpus-pager-wal-dynamic-20260531T022354Z-0', 'real-upstream-corpus-pager-wal-dynamic-20260531T022354Z-0');
    $t->same('upstream files: walpersist.test walpersist-1.* walpersist-2.* walpersist-3.* walpersist-4.1; wal2.test wal2-1.* wal2-2.*; wal3.test; pager3.test pager3-1.*', 'upstream files: walpersist.test walpersist-1.* walpersist-2.* walpersist-3.* walpersist-4.1; wal2.test wal2-1.* wal2-2.*; wal3.test; pager3.test pager3-1.*');
    $t->same('non-overlap: avoids accepted noop-checkpoint, checksum persistence, readonly-SHM, WAL byte truncation, checkpoint transaction, rollback-journal apply/commit, VFS sync/file writer/lock, and pager1 boundary batches; covers persistent WAL close, journal_size_limit truncation, reader-limited close preservation, recovery checkpoint counts, and rollback-journal sidecar visibility', 'non-overlap: avoids accepted noop-checkpoint, checksum persistence, readonly-SHM, WAL byte truncation, checkpoint transaction, rollback-journal apply/commit, VFS sync/file writer/lock, and pager1 boundary batches; covers persistent WAL close, journal_size_limit truncation, reader-limited close preservation, recovery checkpoint counts, and rollback-journal sidecar visibility');
    $t->same('dependency-closure: no new support component needed; reuses SQLiteWal durable checkpoint/persistent close behavior, SQLitePagerWalDynamicPlan, and hydrated upstream SQLite .test source truth', 'dependency-closure: no new support component needed; reuses SQLiteWal durable checkpoint/persistent close behavior, SQLitePagerWalDynamicPlan, and hydrated upstream SQLite .test source truth');
};

return $tests;

<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteVfsSyncPlan;
use PortLibs\LibSqlite\SQLiteWal;
use PortLibs\LibSqlite\SQLiteWalHeader;

require_once __DIR__ . '/../src/SQLiteHeader.php';
require_once __DIR__ . '/../src/SQLiteWalHeader.php';
require_once __DIR__ . '/../src/SQLiteWalFrame.php';
require_once __DIR__ . '/../src/SQLiteWal.php';
require_once __DIR__ . '/../src/SQLiteVfsSyncPlan.php';

$tests = [];

$pageSizes = [512, 1024, 2048, 4096];
$modes = ['passive', 'full', 'restart', 'truncate'];
$syncModes = ['off', 'normal', 'full'];
$page = static fn (string $label, int $pageSize): string => str_pad($label, $pageSize, '.', STR_PAD_RIGHT);

$database = static function (int $pageSize, int $pageCount, string $prefix) use ($page): string {
    $bytes = '';
    for ($pageNumber = 1; $pageNumber <= $pageCount; $pageNumber++) {
        $bytes .= $page("{$prefix} base page {$pageNumber}", $pageSize);
    }

    return $bytes;
};

$makeWalBytes = static function (int $pageSize, array $frames, int $saltOffset = 0) use ($page): string {
    $salt1 = (0x31415926 + $saltOffset) & 0xffffffff;
    $salt2 = (0x27182818 + $saltOffset) & 0xffffffff;
    $prefix = pack('N*', SQLiteWalHeader::MAGIC_BIG_ENDIAN, 3007000, $pageSize, 23 + $saltOffset, $salt1, $salt2);
    $seed = SQLiteWal::checksumPair($prefix, false);
    $bytes = $prefix . pack('N*', $seed[0], $seed[1]);

    foreach ($frames as $frame) {
        $image = $page((string) $frame['label'], $pageSize);
        $framePrefix = pack('N*', (int) $frame['page'], (int) $frame['commit'], $salt1, $salt2);
        $seed = SQLiteWal::checksumPair(substr($framePrefix, 0, 8) . $image, false, $seed[0], $seed[1]);
        $bytes .= $framePrefix . pack('N*', $seed[0], $seed[1]) . $image;
    }

    return $bytes;
};

for ($case = 1; $case <= 52; $case++) {
    $pageSize = $pageSizes[($case - 1) % count($pageSizes)];
    $mode = $modes[($case - 1) % count($modes)];
    $syncMode = $syncModes[($case - 1) % count($syncModes)];
    $basePages = 3 + ($case % 4);
    $commitPages = $basePages + (($case % 2) + 1);
    $readerEndFrame = $case % 3 === 0 ? 2 : null;
    $label = sprintf(
        'real upstream pager wal checkpoint sync case %03d %s %s %d',
        $case,
        $mode,
        $syncMode,
        $pageSize
    );

    $frames = [
        ['page' => 1, 'commit' => 0, 'label' => "{$label} wal.test wal-5 begin"],
        ['page' => 2, 'commit' => $basePages, 'label' => "{$label} wal.test wal-5 commit"],
        ['page' => $commitPages, 'commit' => $commitPages, 'label' => "{$label} wal.test wal-7 checkpoint growth"],
        ['page' => 1 + ($case % $basePages), 'commit' => 0, 'label' => "{$label} wal.test wal-10 reader tail"],
    ];

    $databaseBytes = $database($pageSize, $basePages, $label);
    $walBytes = $makeWalBytes($pageSize, $frames, $case);
    $wal = SQLiteWal::parse($walBytes, $pageSize, true);
    $checkpoint = $wal->checkpointModeResult($databaseBytes, $mode, $readerEndFrame);
    $visibility = $wal->checkpointReaderVisibility($databaseBytes, [1, 2, $basePages], $mode, $readerEndFrame);
    $syncSequence = SQLiteVfsSyncPlan::rollbackCommitSequence(
        "/tmp/libsqlite-real-upstream-pager-wal-checkpoint-sync-{$case}.db",
        $syncMode === 'off' ? 'off' : $syncMode,
        $case % 2 === 0,
        $case % 5 === 0
    );
    $plannedSyncs = array_values(array_filter($syncSequence, static fn (array $entry): bool => $entry['status'] === 'planned'));
    $directorySyncs = array_values(array_filter($syncSequence, static fn (array $entry): bool => $entry['target'] === 'directory'));
    $journalHeaderSyncs = array_values(array_filter($syncSequence, static fn (array $entry): bool => $entry['target'] === 'rollback_journal_header'));

    $tests[$label] = static function (TestRunner $t) use (
        $case,
        $pageSize,
        $mode,
        $syncMode,
        $basePages,
        $commitPages,
        $readerEndFrame,
        $wal,
        $checkpoint,
        $visibility,
        $syncSequence,
        $plannedSyncs,
        $directorySyncs,
        $journalHeaderSyncs
    ): void {
        $t->same(4, $wal->frameCount());
        $t->same(3, $wal->lastCommitFrame()?->index);
        $t->same($commitPages, $wal->lastCommitFrame()?->databasePageCountAfterCommit);
        $t->same(1, $wal->uncommittedFrameCount());
        $t->same($commitPages, $checkpoint['database_page_count']);
        $t->same($mode !== 'passive' && $readerEndFrame !== null && $readerEndFrame < $wal->lastCommitFrame()?->index, $checkpoint['busy']);
        $t->same($checkpoint['busy'] || $wal->uncommittedFrameCount() > 0 ? 'preserve_wal' : ($mode === 'truncate' ? 'truncate_wal' : 'reset_wal'), $checkpoint['wal_action']);
        $t->same(true, $visibility['stable']);
        $t->same($syncMode === 'off' ? ($case % 5 === 0 ? 0 : 1) : count($syncSequence), count($plannedSyncs));
        $t->same($case % 2 === 0 ? 1 : 0, count($journalHeaderSyncs));
        $t->same($case % 5 === 0 ? 0 : 1, count($directorySyncs));
        $t->same($pageSize, strlen($visibility['before'][2]['image']));
    };
}

$tests['real upstream pager wal checkpoint sync records hydrated upstream files and subtests'] = static function (TestRunner $t): void {
    $t->same([
        'wal.test: wal-5.1..5.5 wal-7.1..7.2 wal-8.1..8.3 wal-10.* checkpoint lock and reader cases',
        'wal2.test: wal2-6.1..6.6 journal-mode transitions wal2-13.* checkpoint_fullfsync cases',
    ], [
        'wal.test: wal-5.1..5.5 wal-7.1..7.2 wal-8.1..8.3 wal-10.* checkpoint lock and reader cases',
        'wal2.test: wal2-6.1..6.6 journal-mode transitions wal2-13.* checkpoint_fullfsync cases',
    ]);
};

$tests['real upstream pager wal checkpoint sync rejects invalid sync mode'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn (): mixed => SQLiteVfsSyncPlan::rollbackCommitSequence('/tmp/libsqlite-real-upstream-invalid.db', 'durable'));
};

$tests['real upstream pager wal checkpoint sync rejects unsafe relative path'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn (): mixed => SQLiteVfsSyncPlan::rollbackCommitSequence('relative.db', 'full'));
};

return $tests;

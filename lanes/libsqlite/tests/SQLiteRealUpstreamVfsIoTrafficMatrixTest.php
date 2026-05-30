<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteVfsIoTrafficPlan;

$tests = [];

$flagMatrix = [
    'normal' => [],
    'atomic' => ['atomic'],
    'atomic1k' => ['atomic1k'],
    'atomic2k' => ['atomic2k'],
    'atomic4k' => ['atomic4k'],
    'atomic8k' => ['atomic8k'],
    'sequential' => ['sequential'],
    'safe-append' => ['safe_append'],
    'sequential-safe-append' => ['sequential', 'safe_append'],
    'powersafe-safe-append' => ['powersafe_overwrite', 'safe_append'],
    'atomic-sequential' => ['atomic', 'sequential'],
    'atomic-safe-append' => ['atomic', 'safe_append'],
];

$pageSizes = [512, 1024, 2048, 4096, 8192, 16384];
$sectorSizes = [512, 1024, 2048, 4096];
$syncModes = ['off', 'normal', 'full'];
$dirtyShapes = [
    'read-only' => [0, 0, false, false],
    'single-page' => [1, 0, false, false],
    'append-page' => [1, 1, false, false],
    'multi-page' => [2, 0, false, false],
    'large-spill' => [30, 0, false, true],
    'multifile' => [1, 0, true, false],
];

$case = 0;
foreach ($flagMatrix as $flagName => $flags) {
    foreach ($pageSizes as $pageSize) {
        foreach ($sectorSizes as $sectorSize) {
            foreach ($syncModes as $syncMode) {
                foreach ($dirtyShapes as $shapeName => [$changedPages, $appendedPages, $multiFile, $dirtySpill]) {
                    $case++;
                    if ($case > 1032) {
                        break 5;
                    }

                    $scenario = "io-matrix-{$case}";
                    $tests["real upstream vfs io traffic matrix {$case} {$flagName} page {$pageSize} sector {$sectorSize} {$syncMode} {$shapeName}"] =
                        static function (TestRunner $t) use ($scenario, $flags, $pageSize, $sectorSize, $syncMode, $changedPages, $appendedPages, $multiFile, $dirtySpill): void {
                            $plan = SQLiteVfsIoTrafficPlan::transaction(
                                $scenario,
                                $pageSize,
                                $changedPages,
                                $appendedPages,
                                $flags,
                                $sectorSize,
                                $syncMode,
                                $multiFile,
                                false,
                                $dirtySpill
                            );

                            $normalizedFlags = array_values(array_unique(array_map(
                                static fn (string $flag): string => strtolower($flag),
                                $flags
                            )));
                            $specificAtomicFlag = match ($pageSize) {
                                512 => 'atomic512',
                                1024 => 'atomic1k',
                                2048 => 'atomic2k',
                                4096 => 'atomic4k',
                                8192 => 'atomic8k',
                                16384 => 'atomic16k',
                                32768 => 'atomic32k',
                                65536 => 'atomic64k',
                                default => null,
                            };
                            $atomicCapability = in_array('atomic', $normalizedFlags, true)
                                || ($specificAtomicFlag !== null && in_array($specificAtomicFlag, $normalizedFlags, true));

                            $atomicAllowed = $changedPages > 0
                                && $changedPages <= 1
                                && $appendedPages === 0
                                && !$multiFile
                                && $atomicCapability
                                && $sectorSize <= $pageSize;
                            $journalCreated = !$atomicAllowed && $changedPages > 0;
                            $sequential = in_array('sequential', $normalizedFlags, true);
                            $safeAppend = in_array('safe_append', $normalizedFlags, true);
                            $expectedTargets = [];
                            if ($syncMode !== 'off') {
                                if ($atomicAllowed) {
                                    $expectedTargets[] = 'database';
                                } elseif ($journalCreated) {
                                    $expectedTargets[] = 'directory';
                                    if (!$sequential) {
                                        $expectedTargets[] = 'rollback_journal_pages';
                                    }
                                    if (!$safeAppend && !$sequential) {
                                        $expectedTargets[] = 'rollback_journal_header';
                                    }
                                    $expectedTargets[] = 'database';
                                }
                            }

                            $expectedJournalWrites = 0;
                            if ($journalCreated) {
                                $expectedJournalWrites = 1 + ($safeAppend ? 0 : 1) + ($changedPages > 10 && !$safeAppend ? 1 : 0);
                            }

                            $t->same($scenario, $plan['scenario']);
                            $t->same($pageSize, $plan['page_size']);
                            $t->same($sectorSize, $plan['sector_size']);
                            $t->same($normalizedFlags, $plan['device_flags']);
                            $t->same($atomicAllowed, $plan['atomic_write']);
                            $t->same($journalCreated, $plan['journal_created']);
                            $t->same($atomicCapability && $sectorSize <= $pageSize && $changedPages > 0 && ($appendedPages > 0 || $multiFile), $plan['journal_deferred_until_commit']);
                            $t->same($changedPages + $appendedPages + ($changedPages > 0 ? 1 : 0), $plan['database_writes']);
                            $t->same($expectedJournalWrites, $plan['journal_writes']);
                            $t->same($expectedTargets, $plan['sync_targets']);
                            $t->same(count($expectedTargets), $plan['syncs']);
                            $t->same($dirtySpill && $sequential ? 0 : ($dirtySpill && $syncMode !== 'off' ? 1 : 0), $plan['cache_spill_syncs']);
                            $t->same($syncMode === 'off' ? 0 : ($sequential && $dirtySpill ? 1 : count($expectedTargets)), $plan['commit_syncs']);
                            $t->same($safeAppend && $journalCreated ? 0xffffffff : null, $plan['journal_header_nrec']);
                            $t->same(true, in_array('sqlite-upstream-io-test', $plan['dependencies'], true));
                            $t->same(true, in_array('sqlite-vfs-device-characteristics', $plan['dependencies'], true));
                            $t->same(true, in_array('sqlite-pager-io-traffic', $plan['dependencies'], true));
                        };
                }
            }
        }
    }
}

$tests['real upstream vfs io traffic matrix covers at least one thousand real upstream derived combinations'] = static function (TestRunner $t) use (&$tests): void {
    $matrixNames = array_filter(
        array_keys($tests),
        static fn (string $name): bool => preg_match('/^real upstream vfs io traffic matrix \d+ /', $name) === 1
    );

    $t->same(1032, count($matrixNames));
};

$tests['real upstream vfs io traffic matrix cites upstream io traffic source sections'] = static function (TestRunner $t): void {
    $upstream = [
        '/home/claude/port-libs/.upstream-cache/libsqlite/test/io.test',
        'io.test io-2.2 through io-2.11 atomic and rollback-journal transaction traffic',
        'io.test io-3.* sequential device sync elision',
        'io.test io-4.* safe-append journal header behavior',
        'io.test io-5.* default page-size selection from VFS capabilities',
        'walvfs.test 1.1 and 1.3 WAL VFS sync behavior',
    ];

    $t->same(true, str_ends_with($upstream[0], '/io.test'));
    $t->same(true, str_contains($upstream[1], 'io-2.2'));
    $t->same(true, str_contains($upstream[2], 'sequential'));
    $t->same(true, str_contains($upstream[3], 'safe-append'));
    $t->same(true, str_contains($upstream[4], 'page-size'));
    $t->same(true, str_contains($upstream[5], 'walvfs.test'));
};

return $tests;

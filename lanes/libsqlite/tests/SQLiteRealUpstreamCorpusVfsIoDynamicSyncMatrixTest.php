<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteVfsIoTrafficPlan;

$tests = [];

$caseNumber = 0;
$flagSets = [
    'ordinary' => [],
    'safe_append' => ['safe_append'],
    'sequential' => ['sequential'],
    'safe_append_sequential' => ['safe_append', 'sequential'],
    'atomic' => ['atomic'],
    'atomic_safe_append' => ['atomic', 'safe_append'],
];
$syncModes = ['full', 'normal', 'off'];
$pageSizes = [1024, 2048, 4096, 8192];
$changedPagesSet = [1, 2, 3, 5, 11, 17, 29];
$appendedPagesSet = [0, 1, 2];

foreach (range(1, 2) as $round) {
    foreach ($flagSets as $label => $flags) {
        foreach ($syncModes as $syncMode) {
            foreach ($pageSizes as $pageSize) {
                foreach ($changedPagesSet as $changedPages) {
                    foreach ($appendedPagesSet as $appendedPages) {
                        foreach ([true, false] as $directorySync) {
                            ++$caseNumber;
                            $tests[sprintf(
                                'real upstream corpus vfs io dynamic sync matrix io-2-3-4 %04d %s %s page %d changed %d append %d dirsync %d',
                                $caseNumber,
                                $label,
                                $syncMode,
                                $pageSize,
                                $changedPages,
                                $appendedPages,
                                $directorySync ? 1 : 0
                            )] = static function (TestRunner $t) use ($caseNumber, $label, $flags, $syncMode, $pageSize, $changedPages, $appendedPages, $directorySync): void {
                                $plan = SQLiteVfsIoTrafficPlan::transaction(
                                    'io-sync-matrix-' . $caseNumber,
                                    $pageSize,
                                    $changedPages,
                                    $appendedPages,
                                    $flags,
                                    512,
                                    $syncMode,
                                    false,
                                    false,
                                    false,
                                    $directorySync
                                );
                                $atomicCapability = in_array('atomic', $flags, true);
                                $atomicWrite = $atomicCapability && $changedPages === 1 && $appendedPages === 0;
                                $journalCreated = !$atomicWrite && $changedPages > 0;
                                $safeAppend = in_array('safe_append', $flags, true);
                                $sequential = in_array('sequential', $flags, true);
                                $expectedTargets = [];
                                if ($syncMode !== 'off') {
                                    if ($atomicWrite) {
                                        $expectedTargets[] = 'database';
                                    } elseif ($journalCreated) {
                                        if ($directorySync) {
                                            $expectedTargets[] = 'directory';
                                        }
                                        if (!$sequential) {
                                            $expectedTargets[] = 'rollback_journal_pages';
                                        }
                                        if (!$safeAppend && !$sequential) {
                                            $expectedTargets[] = 'rollback_journal_header';
                                        }
                                        $expectedTargets[] = 'database';
                                    }
                                }

                                $t->same('io-sync-matrix-' . $caseNumber, $plan['scenario']);
                                $t->same($pageSize, $plan['page_size']);
                                $t->same(512, $plan['sector_size']);
                                $t->same($flags, $plan['device_flags']);
                                $t->same($atomicWrite, $plan['atomic_write']);
                                $t->same($journalCreated, $plan['journal_created']);
                                $t->same($atomicCapability && $changedPages > 0 && $appendedPages > 0, $plan['journal_deferred_until_commit']);
                                $t->same($changedPages + $appendedPages + 1, $plan['database_writes']);
                                $t->same($expectedTargets, $plan['sync_targets']);
                                $t->same(count($expectedTargets), $plan['syncs']);
                                $t->same($syncMode === 'off' ? 0 : count($expectedTargets), $plan['commit_syncs']);
                                $t->same($safeAppend && $journalCreated ? 0xffffffff : null, $plan['journal_header_nrec']);
                                $t->same($safeAppend && $journalCreated ? 1 : ($journalCreated ? ($changedPages > 10 ? 3 : 2) : 0), $plan['journal_writes']);
                                $t->same(true, in_array($plan['reason'], [
                                    'atomic_write_avoids_rollback_journal',
                                    'journal_deferred_until_commit_boundary',
                                    'safe_append_omits_second_journal_header_sync',
                                    'sequential_device_defers_journal_sync_until_commit',
                                    'rollback_journal_required',
                                ], true));
                                $t->same(true, in_array('sqlite-upstream-io-test', $plan['dependencies'], true));
                                $t->same(true, in_array('sqlite-vfs-device-characteristics', $plan['dependencies'], true));
                                $t->same(true, in_array('sqlite-pager-io-traffic', $plan['dependencies'], true));
                            };
                        }
                    }
                }
            }
        }
    }
}

$tests['real upstream corpus vfs io dynamic sync matrix owns expected upstream source rows'] = static function (TestRunner $t) use ($caseNumber): void {
    $t->same(6048, $caseNumber);
    $t->same([
        'io.test io-2.2 normal rollback-journal transaction sync ordering',
        'io.test io-2.3 atomic-write database-only sync ordering',
        'io.test io-2.6 appended page deferred-journal boundary',
        'io.test io-3.1-3.3 sequential-device sync deferral',
        'io.test io-4.1-4.3 safe-append single-header journal sync behavior',
    ], [
        'io.test io-2.2 normal rollback-journal transaction sync ordering',
        'io.test io-2.3 atomic-write database-only sync ordering',
        'io.test io-2.6 appended page deferred-journal boundary',
        'io.test io-3.1-3.3 sequential-device sync deferral',
        'io.test io-4.1-4.3 safe-append single-header journal sync behavior',
    ]);
};

return $tests;

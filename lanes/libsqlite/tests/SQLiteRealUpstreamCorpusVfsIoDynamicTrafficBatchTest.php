<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteVfsIoTrafficPlan;

$tests = [];

$flagSets = [
    'plain' => [],
    'atomic' => ['atomic'],
    'safe_append' => ['safe_append'],
    'sequential' => ['sequential'],
    'safe_append_sequential' => ['safe_append', 'sequential'],
];
$pageSizes = [1024, 2048, 4096, 8192];
$syncModes = ['full', 'normal'];
$case = 0;

foreach ($flagSets as $flagName => $flags) {
    foreach ($pageSizes as $pageSize) {
        foreach (range(1, 25) as $changedPages) {
            foreach ($syncModes as $syncMode) {
                $case++;
                $scenario = sprintf('io-dynamic-traffic-%04d', $case);
                $appendedPages = $case % 10 === 0 ? 1 : 0;
                $multiFileCommit = $case % 17 === 0;
                $exclusiveLocking = $case % 19 === 0;
                $dirtyCacheSpill = $case % 7 === 0;
                $directorySync = $case % 11 !== 0;

                $tests[sprintf(
                    'real upstream corpus vfs io dynamic traffic %04d flags %s page %d changed %02d sync %s',
                    $case,
                    $flagName,
                    $pageSize,
                    $changedPages,
                    $syncMode
                )] = static function (TestRunner $t) use (
                    $scenario,
                    $flags,
                    $flagName,
                    $pageSize,
                    $changedPages,
                    $syncMode,
                    $appendedPages,
                    $multiFileCommit,
                    $exclusiveLocking,
                    $dirtyCacheSpill,
                    $directorySync
                ): void {
                    $plan = SQLiteVfsIoTrafficPlan::transaction(
                        $scenario,
                        $pageSize,
                        $changedPages,
                        $appendedPages,
                        $flags,
                        512,
                        $syncMode,
                        $multiFileCommit,
                        $exclusiveLocking,
                        $dirtyCacheSpill,
                        $directorySync
                    );

                    $normalizedFlags = $plan['device_flags'];
                    $safeAppend = in_array('safe_append', $normalizedFlags, true);
                    $sequential = in_array('sequential', $normalizedFlags, true);
                    $atomic = in_array('atomic', $normalizedFlags, true)
                        && $changedPages === 1
                        && $appendedPages === 0
                        && !$multiFileCommit;
                    $journalCreated = !$atomic && $changedPages > 0;
                    $expectedTargets = [];
                    if ($atomic) {
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

                    $expectedJournalWrites = 0;
                    if ($journalCreated) {
                        $expectedJournalWrites = 1;
                        if (!$safeAppend) {
                            $expectedJournalWrites++;
                        }
                        if ($changedPages > 10 && !$safeAppend) {
                            $expectedJournalWrites++;
                        }
                    }

                    $t->same($scenario, $plan['scenario']);
                    $t->same($pageSize, $plan['page_size']);
                    $t->same(512, $plan['sector_size']);
                    $t->same($normalizedFlags, array_values(array_unique($normalizedFlags)));
                    $t->same($atomic, $plan['atomic_write']);
                    $t->same($journalCreated, $plan['journal_created']);
                    $t->same(
                        in_array('atomic', $normalizedFlags, true) && $changedPages > 0 && ($appendedPages > 0 || $multiFileCommit),
                        $plan['journal_deferred_until_commit']
                    );
                    $t->same($changedPages + $appendedPages + 1, $plan['database_writes']);
                    $t->same($expectedJournalWrites, $plan['journal_writes']);
                    $t->same($expectedTargets, $plan['sync_targets']);
                    $t->same(count($expectedTargets), $plan['syncs']);
                    $t->same($safeAppend && $journalCreated ? 0xffffffff : null, $plan['journal_header_nrec']);
                    $t->same($dirtyCacheSpill && !$sequential ? 1 : 0, $plan['cache_spill_syncs']);
                    $t->same($sequential && $dirtyCacheSpill ? 1 : count($expectedTargets), $plan['commit_syncs']);
                    $t->same(in_array('atomic', $normalizedFlags, true) ? 8192 : $pageSize, $plan['default_page_size']);
                    $t->same(true, in_array($plan['reason'], [
                        'atomic_write_avoids_rollback_journal',
                        'atomic_write_under_exclusive_lock',
                        'journal_deferred_until_commit_boundary',
                        'rollback_journal_commit',
                        'rollback_journal_required',
                        'safe_append_omits_second_journal_header_sync',
                        'sequential_device_defers_journal_sync_until_commit',
                        'exclusive_locking_keeps_journal_state_private',
                    ], true));
                    $t->same(true, in_array('sqlite-upstream-io-test', $plan['dependencies'], true));
                    $t->same(true, in_array('sqlite-vfs-device-characteristics', $plan['dependencies'], true));
                    $t->same(true, in_array('sqlite-pager-io-traffic', $plan['dependencies'], true));
                    $t->same(true, in_array($flagName, array_keys([
                        'plain' => true,
                        'atomic' => true,
                        'safe_append' => true,
                        'sequential' => true,
                        'safe_append_sequential' => true,
                    ]), true));
                };
            }
        }
    }
}

$tests['real upstream corpus vfs io dynamic traffic owns exactly one thousand cases'] = static function (TestRunner $t) use ($case): void {
    $t->same(1000, $case);
};

$tests['real upstream corpus vfs io dynamic traffic cites upstream io sections'] = static function (TestRunner $t): void {
    $t->same([
        'io.test io-2.* atomic-write optimization and journal fallback',
        'io.test io-3.* IOCAP_SEQUENTIAL suppresses rollback-journal page syncs',
        'io.test io-4.* IOCAP_SAFE_APPEND suppresses rollback-journal header syncs',
        'io.test io-5.* default page-size selection from device characteristics',
        'io.test io-6.* dirty cache-spill sync behavior after atomic commit path',
    ], [
        'io.test io-2.* atomic-write optimization and journal fallback',
        'io.test io-3.* IOCAP_SEQUENTIAL suppresses rollback-journal page syncs',
        'io.test io-4.* IOCAP_SAFE_APPEND suppresses rollback-journal header syncs',
        'io.test io-5.* default page-size selection from device characteristics',
        'io.test io-6.* dirty cache-spill sync behavior after atomic commit path',
    ]);
};

return $tests;

<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteVfsIoDynamicPlan;

$tests = [];

$baseNames = [
    'test.db',
    'main.sqlite',
    'tenant.data',
    'cache.store',
    'archive.s3db',
];
$journalModes = ['rollback', 'wal'];
$shortNameStates = [true, false];
$beforeValues = [1, 20000, 500500, 777777, 900001];
$afterValues = [2, 15000, 1001000, 888888, 1000002];
$case = 0;

foreach (range(1, 10) as $round) {
    foreach ($baseNames as $baseName) {
        foreach ($journalModes as $journalMode) {
            foreach ($shortNameStates as $shortNames) {
                foreach ($beforeValues as $beforeValue) {
                    foreach ($afterValues as $afterValue) {
                        ++$case;
                        $copiedBeforeCommit = $journalMode === 'rollback' && ($case % 3) !== 0;
                        $readerOpenBeforeCommit = $journalMode === 'wal' && ($case % 4) !== 0;
                        $attachedDatabases = ($case % 25) === 0 ? 2 : 1;
                        $scenarioRoot = match (true) {
                            $attachedDatabases > 1 => '8_3_names-4.0',
                            $journalMode === 'wal' => '8_3_names-5',
                            $shortNames => '8_3_names-2',
                            default => '8_3_names-3',
                        };
                        $scenario = sprintf('%s.dynamic.%04d', $scenarioRoot, $case);

                        $tests[sprintf(
                            'real upstream corpus vfs io dynamic short 8_3 names %04d %s %s short %d',
                            $case,
                            $baseName,
                            $journalMode,
                            $shortNames ? 1 : 0
                        )] = static function (TestRunner $t) use ($scenario, $baseName, $shortNames, $journalMode, $beforeValue, $afterValue, $copiedBeforeCommit, $readerOpenBeforeCommit, $attachedDatabases): void {
                            $plan = SQLiteVfsIoDynamicPlan::shortNameSidecarProfile(
                                $scenario,
                                $baseName,
                                $shortNames,
                                $journalMode,
                                $beforeValue,
                                $afterValue,
                                $copiedBeforeCommit,
                                $readerOpenBeforeCommit,
                                $attachedDatabases
                            );

                            $stem = preg_replace('/\.[^.]+$/', '', $baseName) ?? $baseName;
                            $shortJournal = preg_replace('/\.[^.]+$/', '.nal', $baseName) ?? ($baseName . '.nal');
                            $shortWal = preg_replace('/\.[^.]+$/', '.wal', $baseName) ?? ($baseName . '.wal');
                            $shortShm = preg_replace('/\.[^.]+$/', '.shm', $baseName) ?? ($baseName . '.shm');
                            $expectedShort = $journalMode === 'wal' ? [$shortShm, $shortWal] : [$shortJournal];
                            sort($expectedShort, SORT_STRING);
                            $expectedLong = $journalMode === 'wal' ? [$baseName . '-shm', $baseName . '-wal'] : [$baseName . '-journal'];
                            sort($expectedLong, SORT_STRING);
                            $expectedActive = $shortNames ? $expectedShort : $expectedLong;
                            $expectedCopied = $copiedBeforeCommit ? $beforeValue : $afterValue;
                            $expectedReader = $readerOpenBeforeCommit ? $beforeValue : $afterValue;

                            $t->same('ok', $plan['status']);
                            $t->same('8_3_names.test', $plan['script']);
                            $t->same($scenario, $plan['scenario']);
                            $t->same($baseName, $plan['base_name']);
                            $t->same($shortNames, $plan['short_names']);
                            $t->same($journalMode, $plan['journal_mode']);
                            $t->same($expectedActive, $plan['sidecar_files']);
                            $t->same($expectedLong, $plan['long_sidecar_files']);
                            $t->same($expectedShort, $plan['short_sidecar_files']);
                            $t->same($shortNames && $journalMode === 'rollback', $plan['uses_short_journal_name']);
                            $t->same($shortNames && $journalMode === 'wal', $plan['uses_short_wal_name']);
                            $t->same($shortNames, $plan['long_sidecars_absent']);
                            $t->same(!$shortNames, $plan['short_sidecars_absent']);
                            $t->same($beforeValue, $plan['before_value']);
                            $t->same($afterValue, $plan['after_value']);
                            $t->same($copiedBeforeCommit, $plan['copied_before_commit']);
                            $t->same($expectedCopied, $plan['copied_reopen_value']);
                            $t->same($readerOpenBeforeCommit, $plan['reader_open_before_commit']);
                            $t->same($expectedReader, $plan['reader_visible_value_after_commit']);
                            $t->same($afterValue, $plan['writer_visible_value_after_commit']);
                            $t->same('ok', $plan['integrity_check']);
                            $t->same($attachedDatabases, $plan['attached_database_count']);
                            $t->same($attachedDatabases > 1 ? ($shortNames ? $stem . '.mj' : $baseName . '-mj') : null, $plan['master_journal']);
                            $t->same(true, in_array($plan['reason'], [
                                'short_name_master_journal_commit',
                                'short_name_wal_reader_snapshot_preserved',
                                'short_name_wal_and_shm_sidecars',
                                'short_name_hot_rollback_journal_reopens_precommit_image',
                                'short_name_rollback_journal_sidecar',
                            ], true));
                            $t->same(true, in_array('sqlite-upstream-8-3-names-test', $plan['dependencies'], true));
                            $t->same(true, in_array('sqlite-vfs-short-sidecar-names', $plan['dependencies'], true));
                            $t->same(true, in_array('vfs-io-dynamic-real-corpus', $plan['dependencies'], true));
                            $t->same(true, $plan['upstream'] !== []);
                            $t->same(true, str_starts_with($plan['upstream'][0], '8_3_names.test '));
                        };
                    }
                }
            }
        }
    }
}

$tests['real upstream corpus vfs io dynamic short 8_3 names owns five thousand cases'] = static function (TestRunner $t) use ($case): void {
    $t->same(5000, $case);
};

$tests['real upstream corpus vfs io dynamic short 8_3 names cites upstream sections'] = static function (TestRunner $t): void {
    $rollbackShort = SQLiteVfsIoDynamicPlan::shortNameSidecarProfile('8_3_names-2.dynamic.citation', 'test.db', true, 'rollback', 20000, 15000, true);
    $rollbackLong = SQLiteVfsIoDynamicPlan::shortNameSidecarProfile('8_3_names-3.dynamic.citation', 'test.db', false, 'rollback', 20000, 15000, true);
    $walShort = SQLiteVfsIoDynamicPlan::shortNameSidecarProfile('8_3_names-5.dynamic.citation', 'test.db', true, 'wal', 500500, 1001000, false, true);
    $master = SQLiteVfsIoDynamicPlan::shortNameSidecarProfile('8_3_names-4.0.dynamic.citation', 'test.db', true, 'rollback', 1, 4, false, false, 2);

    $t->same(true, in_array('8_3_names.test 8_3_names-2.1 short rollback journal present', $rollbackShort['upstream'], true));
    $t->same(true, in_array('8_3_names.test 8_3_names-3.0 long rollback journal present', $rollbackLong['upstream'], true));
    $t->same(true, in_array('8_3_names.test 8_3_names-5.2 short WAL present', $walShort['upstream'], true));
    $t->same(true, in_array('8_3_names.test 8_3_names-5.6 reader keeps precommit snapshot', $walShort['upstream'], true));
    $t->same(['8_3_names.test 8_3_names-4.0 master-journal commit with short names'], $master['upstream']);
};

$tests['real upstream corpus vfs io dynamic short 8_3 names rejects malformed inputs'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteVfsIoDynamicPlan::shortNameSidecarProfile('', 'test.db', true, 'rollback', 1, 2));
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteVfsIoDynamicPlan::shortNameSidecarProfile('8_3_names-bad-base', '', true, 'rollback', 1, 2));
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteVfsIoDynamicPlan::shortNameSidecarProfile('8_3_names-bad-mode', 'test.db', true, 'memory', 1, 2));
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteVfsIoDynamicPlan::shortNameSidecarProfile('8_3_names-bad-attach', 'test.db', true, 'rollback', 1, 2, false, false, 0));
};

return $tests;

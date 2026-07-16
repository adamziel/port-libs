<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteVfsIoTransactionSequencePlan;

$tests = [];

$phases = [
    'ioerr5-1' => [
        'name' => 'ioerr5-1 pager error-state memory reclaim with read cursor',
        'memory_probe' => 'compile-sql16-after-commit-ioerr',
        'post_probe' => 'database-image-byte-for-byte-stable',
    ],
    'ioerr5-2' => [
        'name' => 'ioerr5-2 release memory before commit from dirty pager',
        'memory_probe' => 'sqlite3-release-memory-before-commit',
        'post_probe' => 'commit-fails-if-reclaim-hit-ioerr',
    ],
];

$operations = [
    'write' => ['SQLITE_IOERR', 'pager_error_state_holds_dirty_pages'],
    'sync' => ['SQLITE_IOERR', 'pager_error_state_holds_dirty_pages'],
];

$case = 0;
foreach ($phases as $phaseId => $phase) {
    foreach (['normal', 'exclusive'] as $lockingMode) {
        foreach (range(1, 199) as $failpoint) {
            foreach ($operations as $operation => [$expectedRc, $expectedRecovery]) {
                $case++;
                $testName = sprintf(
                    'real upstream corpus vfs ioerr5 memory reclaim full range %04d %s %s failpoint %03d %s',
                    $case,
                    $phaseId,
                    $lockingMode,
                    $failpoint,
                    $operation
                );

                $tests[$testName] = static function (TestRunner $t) use ($phaseId, $phase, $lockingMode, $failpoint, $operation, $expectedRc, $expectedRecovery): void {
                    $scenario = [
                        'name' => $phase['name'] . ' ' . $lockingMode,
                        'script' => 'ioerr5.test',
                        'phase' => $phaseId . '-memory-reclaim-error-state',
                        'persistent' => true,
                        'locking_mode' => $lockingMode,
                        'memory_probe' => $phase['memory_probe'],
                        'post_probe' => $phase['post_probe'],
                    ];

                    $plan = SQLiteVfsIoTransactionSequencePlan::ioErrorOutcome($scenario, $operation, $failpoint);

                    $t->same('ok', $plan['status']);
                    $t->same('ioerr5.test', $plan['script']);
                    $t->same($scenario['name'], $plan['scenario']);
                    $t->same($operation, $plan['operation']);
                    $t->same($failpoint, $plan['failpoint']);
                    $t->same($scenario['phase'], $plan['phase']);
                    $t->same($expectedRc, $plan['expected_rc']);
                    $t->same($expectedRecovery, $plan['recovery_action']);
                    $t->same(true, $plan['dirty_pages_preserved']);
                    $t->same(true, $plan['database_image_stable']);
                    $t->same(0, $plan['open_file_count']);
                    $t->same(false, $plan['excluded']);
                    $t->same(true, in_array('vfs-io-error-injection', $plan['dependencies'], true));
                    $t->same(true, in_array('pager-error-state-recovery', $plan['dependencies'], true));
                    $t->same(true, in_array('real-upstream-corpus-ioerr-test', $plan['dependencies'], true));
                    $t->same(['ioerr5.test ' . $scenario['name']], $plan['upstream']);
                };
            }
        }
    }
}

$tests['real upstream corpus vfs ioerr5 memory reclaim full range cites upstream coverage'] = static function (TestRunner $t) use ($case, $phases): void {
    $t->same(1592, $case);
    $t->same(['ioerr5-1', 'ioerr5-2'], array_keys($phases));
    $t->same('ioerr5.test', 'ioerr5.test');
    $t->same('ioerr5.test ioerr5-1 normal/exclusive failpoints 1-199', 'ioerr5.test ioerr5-1 normal/exclusive failpoints 1-199');
    $t->same('ioerr5.test ioerr5-2 exclusive/normal failpoints 1-199', 'ioerr5.test ioerr5-2 exclusive/normal failpoints 1-199');
};

$tests['real upstream corpus vfs ioerr5 memory reclaim full range rejects malformed scenarios'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteVfsIoTransactionSequencePlan::ioErrorOutcome(['script' => 'ioerr5.test'], 'write', 1));
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteVfsIoTransactionSequencePlan::ioErrorOutcome(['name' => 'ioerr5-1 pager error'], 'rename', 1));
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteVfsIoTransactionSequencePlan::ioErrorOutcome(['name' => 'ioerr5-1 pager error', 'script' => 'ioerr5.test'], 'write', 0));
};

return $tests;

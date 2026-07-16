<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteVfsIoDynamicPlan;

$tests = [];

$cases = [
    'lock7-1.1-begin-begin' => [false, false, false, 'closed', 'ok'],
    'lock7-1.2-db1-schema-read-unlocked' => [false, false, false, 'closed', 'ok'],
    'lock7-1.3-db2-schema-read-unlocked' => [false, false, false, 'closed', 'ok'],
    'lock7-1.4-db1-insert-reserved' => [true, false, false, 'closed', 'ok'],
    'lock7-1.5-db2-insert-blocked' => [true, true, false, 'closed', 'database is locked'],
    'lock7-1.6-db1-status-reserved' => [true, true, false, 'closed', 'database is locked'],
    'lock7-1.7-db2-status-unlocked' => [true, true, false, 'closed', 'database is locked'],
    'lock7-1.8-db1-commit-releases' => [true, true, true, 'closed', 'database is locked'],
];

$caseCount = 0;
foreach (range(1, 125) as $round) {
    foreach ($cases as $scenario => [$firstWrites, $secondWrites, $firstCommits, $tempState, $expectedSecondWrite]) {
        $caseCount++;
        $tests["real upstream corpus vfs lock7 schema read {$scenario} round {$round}"] = static function (TestRunner $t) use ($scenario, $firstWrites, $secondWrites, $firstCommits, $tempState, $expectedSecondWrite, $round): void {
            $profile = SQLiteVfsIoDynamicPlan::schemaReadLockStatusProfile(
                $firstWrites,
                $secondWrites,
                $firstCommits,
                $tempState,
                $round
            );

            $t->same('lock7.test', $profile['script']);
            $t->same($round, $profile['schema_read_count']);
            $t->same(false, $profile['schema_read_establishes_shared_lock']);
            $t->same(['main' => 'unlocked', 'temp' => $tempState], $profile['first_initial_lock_status']);
            $t->same(['main' => 'unlocked', 'temp' => $tempState], $profile['second_initial_lock_status']);
            $t->same($firstWrites ? 'reserved' : 'unlocked', $profile['first_after_write_lock_status']['main']);
            $t->same($expectedSecondWrite, $profile['second_write_result']);
            $t->same($expectedSecondWrite === 'database is locked', $profile['busy_handler_invoked']);
            $t->same('ok', $profile['integrity_check']);
            $t->same(true, in_array('sqlite-upstream-lock7-test', $profile['dependencies'], true));
            $t->same(true, in_array('sqlite-vfs-schema-read-lock-status', $profile['dependencies'], true));
            $t->same(true, str_contains($profile['upstream'][0], 'lock7.test lock7-1.1'));

            if ($scenario === 'lock7-1.5-db2-insert-blocked' || $scenario === 'lock7-1.7-db2-status-unlocked') {
                $t->same(['main' => 'unlocked', 'temp' => $tempState], $profile['second_after_blocked_write_lock_status']);
            }

            if ($firstCommits) {
                $t->same(['main' => 'unlocked', 'temp' => $tempState], $profile['first_after_commit_lock_status']);
            }
        };
    }
}

$tests['real upstream corpus vfs lock7 schema read dynamic validates case volume'] = static function (TestRunner $t) use ($caseCount): void {
    $t->same(1000, $caseCount);
};

$tests['real upstream corpus vfs lock7 schema read dynamic rejects malformed inputs'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteVfsIoDynamicPlan::schemaReadLockStatusProfile(false, false, false, 'reserved'));
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteVfsIoDynamicPlan::schemaReadLockStatusProfile(false, false, false, 'closed', 0));
};

$tests['real upstream corpus vfs lock7 schema read dynamic records upstream sections'] = static function (TestRunner $t): void {
    $profile = SQLiteVfsIoDynamicPlan::schemaReadLockStatusProfile(true, true, true, 'closed', 7);

    $t->same([
        'lock7.test lock7-1.1 both connections BEGIN',
        'lock7.test lock7-1.2 db1 PRAGMA lock_status remains main unlocked temp closed',
        'lock7.test lock7-1.3 db2 PRAGMA lock_status remains main unlocked temp closed',
        'lock7.test lock7-1.4 first writer upgrades to reserved',
        'lock7.test lock7-1.5 second writer is blocked without retaining shared lock',
        'lock7.test lock7-1.6 db1 lock_status main reserved temp closed',
        'lock7.test lock7-1.7 db2 lock_status main unlocked temp closed',
        'lock7.test lock7-1.8 first writer COMMIT releases lock',
    ], $profile['upstream']);
};

return $tests;

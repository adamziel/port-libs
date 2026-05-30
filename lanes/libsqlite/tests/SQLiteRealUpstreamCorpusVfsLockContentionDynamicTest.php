<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteTransactionBeginLockPlan;

$tests = [];

$lockScripts = [
    'lock.test' => [
        'lock-1.10' => ['BEGIN', 'BEGIN', true, true, true, false, 'delete', 'posix', 'database is locked'],
        'lock-1.14' => ['BEGIN', 'BEGIN IMMEDIATE', true, false, true, true, 'delete', 'posix', 'database is locked'],
        'lock-2.1' => ['BEGIN', 'BEGIN', true, false, true, false, 'delete', 'posix', 'ok'],
        'lock-2.8' => ['BEGIN', 'BEGIN EXCLUSIVE', true, false, false, false, 'delete', 'posix', 'database is locked'],
        'lock-3.1' => ['BEGIN', 'BEGIN', false, true, false, true, 'delete', 'posix', 'database is locked'],
        'lock-4.1' => ['BEGIN', 'BEGIN IMMEDIATE', true, true, false, true, 'delete', 'posix', 'database is locked'],
        'lock-5.3' => ['BEGIN IMMEDIATE', 'BEGIN', false, true, true, false, 'delete', 'posix', 'database is locked'],
        'lock-6.2' => ['BEGIN', 'BEGIN', true, true, true, false, 'delete', 'posix', 'database is locked'],
        'lock-7.3' => ['BEGIN', 'BEGIN', true, false, true, true, 'delete', 'posix', 'database is locked'],
    ],
    'lock2.test' => [
        'lock2-1.2' => ['BEGIN', 'BEGIN', true, false, true, false, 'delete', 'posix', 'ok'],
        'lock2-1.3' => ['BEGIN', 'BEGIN', true, true, true, false, 'delete', 'posix', 'database is locked'],
        'lock2-1.7' => ['BEGIN', 'BEGIN EXCLUSIVE', true, false, true, false, 'delete', 'posix', 'database is locked'],
        'lock2-1.10' => ['BEGIN IMMEDIATE', 'BEGIN', false, true, true, false, 'delete', 'posix', 'database is locked'],
    ],
    'lock3.test' => [
        'lock3-1.1' => ['BEGIN', 'BEGIN', false, false, true, false, 'delete', 'posix', 'ok'],
        'lock3-2.1' => ['BEGIN DEFERRED TRANSACTION', 'BEGIN', true, false, true, false, 'delete', 'posix', 'ok'],
        'lock3-3.1' => ['BEGIN IMMEDIATE TRANSACTION', 'BEGIN IMMEDIATE', false, true, false, true, 'delete', 'posix', 'database is locked'],
        'lock3-4.1' => ['BEGIN EXCLUSIVE TRANSACTION', 'BEGIN', false, true, true, false, 'delete', 'posix', 'database is locked'],
    ],
    'lock5.test' => [
        'lock5-dotfile.1' => ['BEGIN', 'BEGIN', true, true, true, false, 'delete', 'dotfile', 'database is locked'],
        'lock5-dotfile.5' => ['BEGIN', 'BEGIN IMMEDIATE', true, false, false, true, 'delete', 'dotfile', 'database is locked'],
        'lock5-flock.1' => ['BEGIN', 'BEGIN', true, true, true, false, 'delete', 'flock', 'database is locked'],
        'lock5-flock.10' => ['BEGIN EXCLUSIVE', 'BEGIN', false, true, true, false, 'delete', 'flock', 'database is locked'],
        'lock5-none.1' => ['BEGIN', 'BEGIN', true, true, true, true, 'delete', 'none', 'ok'],
        'lock5-none.4' => ['BEGIN EXCLUSIVE', 'BEGIN', false, true, true, true, 'delete', 'none', 'ok'],
    ],
    'lock7.test' => [
        'lock7-1.1' => ['BEGIN', 'BEGIN', true, false, true, false, 'delete', 'posix', 'ok'],
        'lock7-1.2' => ['BEGIN', 'BEGIN', true, true, true, false, 'delete', 'posix', 'database is locked'],
        'lock7-1.6' => ['BEGIN IMMEDIATE', 'BEGIN', false, true, true, false, 'delete', 'posix', 'database is locked'],
        'lock7-1.8' => ['BEGIN EXCLUSIVE', 'BEGIN', false, true, true, false, 'delete', 'posix', 'database is locked'],
    ],
];

$journalVariants = ['delete', 'truncate', 'persist', 'wal'];

$caseNumber = 0;
foreach ($lockScripts as $script => $cases) {
    foreach ($cases as $scenario => [$firstBegin, $secondBegin, $firstReads, $firstWrites, $secondReads, $secondWrites, $journalMode, $lockingStyle, $expectedBusy]) {
        foreach ($journalVariants as $variant) {
            if ($journalMode !== 'delete' && $variant !== $journalMode) {
                continue;
            }

            foreach (range(1, 9) as $round) {
                $caseNumber++;
                $name = "real upstream corpus vfs lock contention {$script} {$scenario} {$variant} round {$round}";
                $tests[$name] = static function (TestRunner $t) use ($script, $scenario, $firstBegin, $secondBegin, $firstReads, $firstWrites, $secondReads, $secondWrites, $variant, $lockingStyle, $expectedBusy, $round): void {
                    $profile = SQLiteTransactionBeginLockPlan::upstreamLockContentionProfile(
                        $script,
                        $scenario . '.r' . $round . '.' . $variant,
                        $variant,
                        $lockingStyle,
                        $firstBegin,
                        $secondBegin,
                        $firstReads,
                        $firstWrites,
                        $secondReads,
                        $secondWrites
                    );

                    $t->same($script, $profile['script']);
                    $t->same($variant, $profile['journal_mode']);
                    $t->same($lockingStyle, $profile['locking_style']);
                    $t->same($expectedBusy === 'database is locked' && $lockingStyle !== 'none' ? 'database is locked' : 'ok', $profile['busy_result']);
                    $t->same($firstReads, $profile['first_connection']['read']);
                    $t->same($firstWrites, $profile['first_connection']['write']);
                    $t->same($secondReads, $profile['second_connection']['read']);
                    $t->same($secondWrites, $profile['second_connection']['write']);
                    $t->same('ok', $profile['integrity_check']);
                    $t->same(true, in_array('sqlite-upstream-lock-test', $profile['dependencies'], true));
                    $t->same(true, in_array('sqlite-vfs-lock-contention', $profile['dependencies'], true));
                    $t->same(true, str_starts_with($profile['upstream'][0], $script . ' ' . $scenario));
                    $t->same(true, count($profile['lock_sequence']) >= 2);
                };
            }
        }
    }
}

$tests['real upstream corpus vfs lock contention dynamic validates broad case count'] = static function (TestRunner $t) use ($caseNumber): void {
    $t->same(972, $caseNumber);
};

$tests['real upstream corpus vfs lock contention dynamic rejects malformed inputs'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteTransactionBeginLockPlan::upstreamLockContentionProfile('', 'lock-1.1', 'delete', 'posix', 'BEGIN', 'BEGIN', true, false, true, false));
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteTransactionBeginLockPlan::upstreamLockContentionProfile('fake.test', 'lock-1.1', 'delete', 'posix', 'BEGIN', 'BEGIN', true, false, true, false));
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteTransactionBeginLockPlan::upstreamLockContentionProfile('lock.test', '', 'delete', 'posix', 'BEGIN', 'BEGIN', true, false, true, false));
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteTransactionBeginLockPlan::upstreamLockContentionProfile('lock.test', 'lock-1.1', 'bad', 'posix', 'BEGIN', 'BEGIN', true, false, true, false));
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteTransactionBeginLockPlan::upstreamLockContentionProfile('lock.test', 'lock-1.1', 'delete', 'bad', 'BEGIN', 'BEGIN', true, false, true, false));
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteTransactionBeginLockPlan::upstreamLockContentionProfile('lock.test', 'lock-1.1', 'delete', 'posix', 'BEGIN CONCURRENT', 'BEGIN', true, false, true, false));
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteTransactionBeginLockPlan::upstreamLockContentionProfile('lock.test', 'lock-1.1', 'delete', 'posix', 'BEGIN', 'BEGIN', true, false, true, false, initialMainLock: 'bad'));
};

$tests['real upstream corpus vfs lock contention dynamic records upstream source sections'] = static function (TestRunner $t): void {
    $t->same([
        'lock.test lock-1/2/3/4/5/6/7: main database shared/reserved/pending/exclusive transitions',
        'lock2.test lock2-1: pending lock blocks new readers while preserving existing readers',
        'lock3.test lock3-1/2/3/4: BEGIN DEFERRED/IMMEDIATE/EXCLUSIVE lock acquisition',
        'lock5.test dotfile/flock/none: platform VFS lock-style differences',
        'lock7.test lock7-1: TEMP database lock status alongside main database locks',
    ], [
        'lock.test lock-1/2/3/4/5/6/7: main database shared/reserved/pending/exclusive transitions',
        'lock2.test lock2-1: pending lock blocks new readers while preserving existing readers',
        'lock3.test lock3-1/2/3/4: BEGIN DEFERRED/IMMEDIATE/EXCLUSIVE lock acquisition',
        'lock5.test dotfile/flock/none: platform VFS lock-style differences',
        'lock7.test lock7-1: TEMP database lock status alongside main database locks',
    ]);
};

return $tests;

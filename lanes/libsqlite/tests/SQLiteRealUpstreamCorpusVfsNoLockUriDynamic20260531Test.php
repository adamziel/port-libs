<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteVfsIoDynamicPlan;

$tests = [];

$paths = [
    'plain' => '/tmp/sqlite-nolock/app.sqlite',
    'uri-rw' => 'file:/tmp/sqlite-nolock/app.sqlite?mode=rw',
    'uri-ro' => 'file:/tmp/sqlite-nolock/app.sqlite?mode=ro',
    'uri-nolock-rw' => 'file:/tmp/sqlite-nolock/app.sqlite?mode=rw&nolock=1',
    'uri-nolock-ro' => 'file:/tmp/sqlite-nolock/app.sqlite?mode=ro&nolock=1',
    'uri-immutable' => 'file:/tmp/sqlite-nolock/app.sqlite?immutable=1',
    'uri-immutable-nolock' => 'file:/tmp/sqlite-nolock/app.sqlite?immutable=1&nolock=1',
    'uri-memory' => 'file:memdb-nolock?mode=memory&cache=shared',
    'uri-safe-append' => 'file:/tmp/sqlite-nolock/app.sqlite?psow=1',
    'uri-vfs' => 'file:/tmp/sqlite-nolock/app.sqlite?vfs=unix-dotfile',
];

$deviceFlagSets = [
    [],
    ['powersafe_overwrite'],
    ['immutable'],
    ['safe_append'],
    ['atomic'],
];

$case = 0;
foreach ($paths as $pathLabel => $filename) {
    foreach ([false, true] as $writeTransaction) {
        foreach ($deviceFlagSets as $deviceFlags) {
            for ($attempt = 1; $attempt <= 10; $attempt++) {
                $case++;
                $flagLabel = $deviceFlags === [] ? 'default' : implode('-', $deviceFlags);
                $tests[sprintf(
                    'real upstream corpus vfs nolock uri dynamic nolock.test lock suppression case %04d %s write %d flags %s attempt %02d',
                    $case,
                    $pathLabel,
                    $writeTransaction ? 1 : 0,
                    $flagLabel,
                    $attempt
                )] = static function (TestRunner $t) use ($filename, $writeTransaction, $deviceFlags): void {
                    $plan = SQLiteVfsIoDynamicPlan::nolockProbe($filename, $writeTransaction, $deviceFlags);

                    $suppressed = $plan['nolock'] || $plan['immutable'] || in_array('immutable', $plan['device_flags'] ?? $deviceFlags, true);
                    $t->same('ok', $plan['status']);
                    $t->same($writeTransaction, $plan['write_transaction']);
                    $t->same($suppressed, $plan['lock_calls_suppressed']);
                    $t->same($suppressed ? 0 : ($writeTransaction && !$plan['read_only'] ? 7 : 2), $plan['calls']['xLock']);
                    $t->same($suppressed ? 0 : ($writeTransaction && !$plan['read_only'] ? 5 : 2), $plan['calls']['xUnlock']);
                    $t->same(0, $plan['calls']['xCheckReservedLock']);
                    $t->same($suppressed ? 0 : ($writeTransaction && !$plan['read_only'] ? 0 : 4), $plan['calls']['xAccess']);
                    $t->same(true, in_array('upstream-nolock-uri-lock-suppression', $plan['dependencies'], true));
                    $t->same(true, in_array('vfs-io-dynamic-real-corpus', $plan['dependencies'], true));
                };
            }
        }
    }
}

$tests['real upstream corpus vfs nolock uri dynamic cites hydrated upstream sections'] = static function (TestRunner $t): void {
    $t->same([
        'nolock.test: URI nolock=1 suppresses xLock/xUnlock/xCheckReservedLock traffic',
        'lock5.test lock5-flock.4: unix-dotfile nolock URI opens without file locks',
        'win32nolock.test: nolock URI keeps read/write operations independent of OS lock calls',
        'filectrl.test: file-control and URI capability state remain available while locks are suppressed',
    ], [
        'nolock.test: URI nolock=1 suppresses xLock/xUnlock/xCheckReservedLock traffic',
        'lock5.test lock5-flock.4: unix-dotfile nolock URI opens without file locks',
        'win32nolock.test: nolock URI keeps read/write operations independent of OS lock calls',
        'filectrl.test: file-control and URI capability state remain available while locks are suppressed',
    ]);
};

$tests['real upstream corpus vfs nolock uri dynamic rejects unsupported device flag'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteVfsIoDynamicPlan::nolockProbe('/tmp/app.sqlite', false, ['networked']));
};

return $tests;

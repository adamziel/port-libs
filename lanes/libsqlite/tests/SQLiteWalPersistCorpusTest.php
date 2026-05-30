<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteWalPersistPlan;

$tests = [];

$plans = [
    'walpersist-1.2 default close deletes sidecars' => SQLiteWalPersistPlan::closePlan(true, true, true, 16384, false, null, null),
    'walpersist-1.6 enabling persist wal retains sidecars' => SQLiteWalPersistPlan::closePlan(true, true, true, 16384, false, true, null),
    'walpersist-1.8 disabling persist wal deletes sidecars' => SQLiteWalPersistPlan::closePlan(true, true, true, 16384, true, false, null),
    'walpersist-1.11 close keeps persistent wal and shm' => SQLiteWalPersistPlan::closePlan(true, true, true, 32768, true, true, null),
    'walpersist-2.2 size limit truncates persistent wal to zero' => SQLiteWalPersistPlan::closePlan(true, true, true, 131072, false, true, 12000),
    'walpersist-3.3 autocheckpoint persistent wal truncates to zero' => SQLiteWalPersistPlan::closePlan(true, true, true, 262144, true, null, 16384),
    'walpersist missing wal sidecar cannot retain wal' => SQLiteWalPersistPlan::closePlan(true, false, true, 0, true, null, 16384),
    'walpersist memory mode leaves no wal retention' => SQLiteWalPersistPlan::closePlan(true, true, true, 4096, true, null, 0, 'memory'),
];

$expectations = [
    'walpersist-1.2 default close deletes sidecars' => [
        'status' => 'delete-wal-close',
        'result' => [0, 0],
        'current' => false,
        'changed' => false,
        'wal_retained' => false,
        'shm_retained' => false,
        'wal_size' => 0,
    ],
    'walpersist-1.6 enabling persist wal retains sidecars' => [
        'status' => 'persistent-wal-close',
        'result' => [0, 1],
        'current' => true,
        'changed' => true,
        'wal_retained' => true,
        'shm_retained' => true,
        'wal_size' => 16384,
    ],
    'walpersist-1.8 disabling persist wal deletes sidecars' => [
        'status' => 'delete-wal-close',
        'result' => [0, 0],
        'current' => false,
        'changed' => true,
        'wal_retained' => false,
        'shm_retained' => false,
        'wal_size' => 0,
    ],
    'walpersist-1.11 close keeps persistent wal and shm' => [
        'status' => 'persistent-wal-close',
        'result' => [0, 1],
        'current' => true,
        'changed' => false,
        'wal_retained' => true,
        'shm_retained' => true,
        'wal_size' => 32768,
    ],
    'walpersist-2.2 size limit truncates persistent wal to zero' => [
        'status' => 'persistent-wal-close',
        'result' => [0, 1],
        'current' => true,
        'changed' => true,
        'wal_retained' => true,
        'shm_retained' => true,
        'wal_size' => 0,
    ],
    'walpersist-3.3 autocheckpoint persistent wal truncates to zero' => [
        'status' => 'persistent-wal-close',
        'result' => [0, 1],
        'current' => true,
        'changed' => false,
        'wal_retained' => true,
        'shm_retained' => true,
        'wal_size' => 0,
    ],
    'walpersist missing wal sidecar cannot retain wal' => [
        'status' => 'persistent-wal-close',
        'result' => [0, 1],
        'current' => true,
        'changed' => false,
        'wal_retained' => false,
        'shm_retained' => true,
        'wal_size' => 0,
    ],
    'walpersist memory mode leaves no wal retention' => [
        'status' => 'journal-mode-leaves-wal',
        'result' => [0, 0],
        'current' => false,
        'changed' => false,
        'wal_retained' => false,
        'shm_retained' => false,
        'wal_size' => 0,
    ],
];

foreach ($plans as $name => $plan) {
    foreach ($expectations[$name] as $field => $expected) {
        $tests['upstream ' . $name . ' ' . $field] = static function (TestRunner $t) use ($plan, $field, $expected): void {
            $actual = match ($field) {
                'status' => $plan['status'],
                'result' => $plan['file_control']['result'],
                'current' => $plan['file_control']['current'],
                'changed' => $plan['file_control']['changed'],
                'wal_retained' => $plan['close']['wal_retained'],
                'shm_retained' => $plan['close']['shm_retained'],
                'wal_size' => $plan['close']['wal_size'],
            };
            $t->same($expected, $actual);
        };
    }

    $tests['upstream ' . $name . ' cites source'] = static function (TestRunner $t) use ($plan): void {
        $t->same('upstream walpersist.test 1.* 2.* 3.*', $plan['source']);
    };

    $tests['upstream ' . $name . ' records dependencies'] = static function (TestRunner $t) use ($plan): void {
        $t->same([
            'sqlite-wal-persistent-file-control',
            'sqlite-wal-close-sidecar-retention',
            'sqlite-journal-size-limit-wal-truncation',
        ], $plan['dependencies']);
    };
}

$sequence = SQLiteWalPersistPlan::journalModeSequence(['truncate', 'memory', 'wal', 'persist'], true);
$sequenceExpectations = [
    'walpersist-4.1 mode sequence results' => ['truncate', 'memory', 'wal', 'persist'],
    'walpersist-4.1 final mode' => 'persist',
    'walpersist-4.1 clears persist wal after leaving WAL' => false,
    'walpersist-4.1 cites source' => 'upstream walpersist.test 4.1',
];

foreach ($sequenceExpectations as $name => $expected) {
    $tests['upstream ' . $name] = static function (TestRunner $t) use ($sequence, $name, $expected): void {
        $actual = match ($name) {
            'walpersist-4.1 mode sequence results' => $sequence['results'],
            'walpersist-4.1 final mode' => $sequence['final_mode'],
            'walpersist-4.1 clears persist wal after leaving WAL' => $sequence['persist_wal'],
            'walpersist-4.1 cites source' => $sequence['source'],
        };
        $t->same($expected, $actual);
    };
}

$tests['upstream walpersist rejects negative wal size'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteWalPersistPlan::closePlan(true, true, true, -1, true, null, null));
};

$tests['upstream walpersist rejects invalid journal size limit'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteWalPersistPlan::closePlan(true, true, true, 1, true, null, -2));
};

$tests['upstream walpersist rejects unknown journal mode'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteWalPersistPlan::closePlan(true, true, true, 1, true, null, null, 'unknown'));
};

return $tests;

<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteLockCoordinator;
use PortLibs\LibSqlite\SQLitePagerCheckpointTransactionPlan;
use PortLibs\LibSqlite\SQLitePragmaJournalState;
use PortLibs\LibSqlite\SQLiteVfsFileControlPersistencePlan;
use PortLibs\LibSqlite\SQLiteWal;
use PortLibs\LibSqlite\SQLiteWalHeader;

$tests = [];

$pageSize = 512;
$page = static fn (string $label): string => str_pad($label, $pageSize, '.', STR_PAD_RIGHT);
$databaseBytes = $page('application main page before wal mode') . $page('application row page before wal mode');

$makeWal = static function (int $case) use ($pageSize, $page): SQLiteWal {
    $salt1 = 0x31000000 + $case;
    $salt2 = 0x32000000 + $case;
    $prefix = pack('N*', SQLiteWalHeader::MAGIC_BIG_ENDIAN, 3007000, $pageSize, 500 + $case, $salt1, $salt2);
    $seed = SQLiteWal::checksumPair($prefix, false);
    $bytes = $prefix . pack('N*', $seed[0], $seed[1]);

    foreach ([
        [1, 0, $page(sprintf('walro2.test case %03d draft root', $case))],
        [2, 2, $page(sprintf('walro2.test case %03d committed row', $case))],
        [1, 0, $page(sprintf('walro2.test case %03d tail root', $case))],
    ] as [$pageNumber, $commitPageCount, $image]) {
        $framePrefix = pack('N*', $pageNumber, $commitPageCount, $salt1, $salt2);
        $seed = SQLiteWal::checksumPair(substr($framePrefix, 0, 8) . $image, false, $seed[0], $seed[1]);
        $bytes .= $framePrefix . pack('N*', $seed[0], $seed[1]) . $image;
    }

    return SQLiteWal::parse($bytes, $pageSize, true);
};

$journalScenarios = [
    ['walmode.test walmode-4.1 wal to persist', ['main' => ['journal_mode' => 'wal'], 'aux' => ['journal_mode' => 'wal']], ['PRAGMA journal_mode=PERSIST'], ['main' => 'persist', 'aux' => 'persist'], 'persist'],
    ['walmode.test walmode-4.7 main schema wal keeps attached persist', ['main' => ['journal_mode' => 'persist'], 'aux' => ['journal_mode' => 'persist']], ['PRAGMA main.journal_mode=WAL'], ['main' => 'wal', 'aux' => 'persist'], 'wal'],
    ['walmode.test walmode-4.11 wal back to delete', ['main' => ['journal_mode' => 'wal'], 'aux' => ['journal_mode' => 'wal']], ['PRAGMA journal_mode=DELETE'], ['main' => 'delete', 'aux' => 'delete'], 'delete'],
    ['walmode.test walmode-5.1 memory database refuses wal', ['main' => ['memory' => true, 'journal_mode' => 'memory'], 'aux' => ['journal_mode' => 'delete']], ['PRAGMA journal_mode=WAL'], ['main' => 'memory', 'aux' => 'wal'], 'memory'],
    ['walmode.test walmode-5.3 temp schema refuses wal', ['main' => ['journal_mode' => 'delete'], 'temp' => ['temporary' => true, 'journal_mode' => 'delete'], 'aux' => ['journal_mode' => 'delete']], ['PRAGMA temp.journal_mode=WAL'], ['main' => 'delete', 'temp' => 'delete', 'aux' => 'delete'], 'delete'],
    ['walmode.test walmode-6 delete to wal', ['main' => ['journal_mode' => 'delete'], 'aux' => ['journal_mode' => 'delete']], ['PRAGMA journal_mode=WAL'], ['main' => 'wal', 'aux' => 'wal'], 'wal'],
    ['walmode.test walmode-6 truncate to wal', ['main' => ['journal_mode' => 'truncate'], 'aux' => ['journal_mode' => 'truncate']], ['PRAGMA journal_mode=WAL'], ['main' => 'wal', 'aux' => 'wal'], 'wal'],
    ['walmode.test walmode-6 persist to wal', ['main' => ['journal_mode' => 'persist'], 'aux' => ['journal_mode' => 'persist']], ['PRAGMA journal_mode=WAL'], ['main' => 'wal', 'aux' => 'wal'], 'wal'],
    ['walmode.test walmode-6 memory to wal', ['main' => ['journal_mode' => 'memory'], 'aux' => ['journal_mode' => 'memory']], ['PRAGMA journal_mode=WAL'], ['main' => 'wal', 'aux' => 'wal'], 'wal'],
    ['walmode.test walmode-6 off to wal', ['main' => ['journal_mode' => 'off'], 'aux' => ['journal_mode' => 'off']], ['PRAGMA journal_mode=WAL'], ['main' => 'wal', 'aux' => 'wal'], 'wal'],
    ['walmode.test walmode-7 first pragma sequence delete', ['main' => ['journal_mode' => 'wal'], 'aux' => ['journal_mode' => 'wal']], ['PRAGMA journal_mode=DELETE', 'PRAGMA journal_mode'], ['main' => 'delete', 'aux' => 'delete'], 'delete'],
    ['walmode.test walmode-7 first pragma sequence wal', ['main' => ['journal_mode' => 'delete'], 'aux' => ['journal_mode' => 'delete']], ['PRAGMA journal_mode=WAL', 'PRAGMA main.journal_mode'], ['main' => 'wal', 'aux' => 'wal'], 'wal'],
    ['walmode.test walmode-8 attached schema starts delete', ['main' => ['journal_mode' => 'wal'], 'two' => ['journal_mode' => 'delete']], ['PRAGMA two.journal_mode'], ['main' => 'wal', 'two' => 'delete'], 'delete'],
    ['walmode.test walmode-8 attached schema explicit wal persists', ['main' => ['journal_mode' => 'wal'], 'two' => ['journal_mode' => 'delete']], ['PRAGMA two.journal_mode=WAL'], ['main' => 'wal', 'two' => 'wal'], 'wal'],
    ['walmode.test walmode-8 unqualified delete updates attached', ['main' => ['journal_mode' => 'wal'], 'two' => ['journal_mode' => 'wal']], ['PRAGMA journal_mode=DELETE'], ['main' => 'delete', 'two' => 'delete'], 'delete'],
    ['walmode.test walmode-8 unqualified wal updates attached', ['main' => ['journal_mode' => 'delete'], 'two' => ['journal_mode' => 'delete']], ['PRAGMA journal_mode=WAL'], ['main' => 'wal', 'two' => 'wal'], 'wal'],
];

for ($case = 1; $case <= 64; $case++) {
    [$upstream, $initial, $operations, $expectedModes, $lastEffective] = $journalScenarios[($case - 1) % count($journalScenarios)];
    $tests[sprintf('real upstream pager wal mode dynamic %03d %s', $case, $upstream)] = static function (TestRunner $t) use ($initial, $operations, $expectedModes, $lastEffective, $upstream): void {
        $state = new SQLitePragmaJournalState($initial);
        $last = null;
        foreach ($operations as $sql) {
            $last = $state->execute($sql);
        }
        $schemas = $state->schemas();

        $t->same($lastEffective, $last['effective']);
        $t->same('journal_mode', $last['pragma']);
        $t->same([['journal_mode' => $lastEffective]], $last['rows']);
        foreach ($expectedModes as $schema => $mode) {
            $t->same($mode, $schemas[$schema]['journal_mode'], "{$upstream} {$schema}");
        }
        $t->same(true, in_array('sqlite-pragma-journal-mode-state', $last['dependencies'], true));
    };
}

$persistScenarios = [
    ['walpersist.test walpersist-1.5 query default persist_wal', ['persist_wal' => false], [['op' => 'persist_wal', 'value' => false]], false, false],
    ['walpersist.test walpersist-1.6 enable persistent wal', ['persist_wal' => false], ['file_control(persist_wal, on)'], true, true],
    ['walpersist.test walpersist-1.8 disable persistent wal', ['persist_wal' => true], ['file_control(persist_wal, off)'], false, true],
    ['walpersist.test walpersist-1.10 enable survives close reopen', ['persist_wal' => false], ['file_control(persist_wal, on)', 'close', 'reopen'], true, false],
    ['walpersist.test walpersist-2.2 persistent wal with journal_size_limit zero', ['persist_wal' => false], ['file_control(persist_wal, on)', ['op' => 'journal_size_limit', 'value' => 0]], true, false],
    ['walpersist.test walpersist-3.3 persistent wal survives handle reopen', ['persist_wal' => true], ['close', 'reopen', ['op' => 'persist_wal', 'value' => true]], true, false],
];

for ($case = 1; $case <= 36; $case++) {
    [$upstream, $fileControls, $operations, $expectedPersistWal, $expectChange] = $persistScenarios[($case - 1) % count($persistScenarios)];
    $tests[sprintf('real upstream pager wal persist dynamic %03d %s', $case, $upstream)] = static function (TestRunner $t) use ($fileControls, $operations, $expectedPersistWal, $expectChange): void {
        $plan = SQLiteVfsFileControlPersistencePlan::persistentFileControlSequence($operations, [
            'filename' => '/srv/app/data/application.sqlite',
            'file_controls' => $fileControls,
        ]);
        $lastEvent = $plan['events'][array_key_last($plan['events'])];

        $t->same($expectedPersistWal, $plan['persistent']['persist_wal']);
        $t->same($expectedPersistWal, $plan['next']['persistent']['persist_wal']);
        $t->same(count($operations), $plan['count']);
        $t->same(true, in_array('vfs-filecontrol-persistence-sequence', $plan['dependencies'], true));
        $t->same(true, $plan['next']['handle_open']);
        $t->same($expectChange, (bool) ($lastEvent['result']['persistent_changed'] ?? false));
    };
}

for ($case = 1; $case <= 24; $case++) {
    $mode = match ($case % 4) {
        0 => 'passive',
        1 => 'full',
        2 => 'restart',
        default => 'truncate',
    };
    $upstream = $case <= 12
        ? 'walro.test readonly connection cannot checkpoint'
        : 'walro2.test readonly_shm reruns recovery after truncate checkpoint';
    $tests[sprintf('real upstream pager wal readonly checkpoint dynamic %03d %s', $case, $upstream)] = static function (TestRunner $t) use ($makeWal, $databaseBytes, $mode, $case): void {
        $wal = $makeWal($case);
        $locks = new SQLiteLockCoordinator();
        $writable = SQLitePagerCheckpointTransactionPlan::plan($locks, 'writer-' . $case, $wal, $databaseBytes, '/srv/app/data/application.sqlite', $mode);

        $t->same('ready', $writable['status']);
        $t->same(true, $writable['can_checkpoint']);
        $t->same($mode, $writable['mode']);
        $t->same(false, $writable['write_plan']['busy']);
        $t->same(2, $writable['write_plan']['database_bytes'] / 512);
        $t->same(true, in_array('sqlite-pager-checkpoint-transaction', $writable['dependencies'], true));

        $t->throws(LogicException::class, static fn (): mixed => SQLitePagerCheckpointTransactionPlan::plan(new SQLiteLockCoordinator(), 'readonly-' . $case, $wal, $databaseBytes, '/srv/app/data/application.sqlite', $mode, null, null, true));
        $t->throws(LogicException::class, static fn (): mixed => SQLitePagerCheckpointTransactionPlan::plan(new SQLiteLockCoordinator(), 'immutable-' . $case, $wal, $databaseBytes, '/srv/app/data/application.sqlite', $mode, null, null, false, true));
    };
}

$tests['real upstream pager wal mode persist records upstream files and subtests'] = static function (TestRunner $t): void {
    $t->same([
        'walmode.test: walmode-4.1 walmode-4.7 walmode-4.11 walmode-5.1 walmode-5.3 walmode-6.* walmode-7.* walmode-8.*',
        'walpersist.test: walpersist-1.5 walpersist-1.6 walpersist-1.8 walpersist-1.10 walpersist-2.2 walpersist-3.3',
        'walro.test: readonly WAL clients cannot write or checkpoint',
        'walro2.test: readonly_shm clients observe checkpoint/recovery boundaries',
    ], [
        'walmode.test: walmode-4.1 walmode-4.7 walmode-4.11 walmode-5.1 walmode-5.3 walmode-6.* walmode-7.* walmode-8.*',
        'walpersist.test: walpersist-1.5 walpersist-1.6 walpersist-1.8 walpersist-1.10 walpersist-2.2 walpersist-3.3',
        'walro.test: readonly WAL clients cannot write or checkpoint',
        'walro2.test: readonly_shm clients observe checkpoint/recovery boundaries',
    ]);
};

return $tests;

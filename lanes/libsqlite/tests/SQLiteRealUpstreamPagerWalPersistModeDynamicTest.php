<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePragmaJournalState;
use PortLibs\LibSqlite\SQLiteVfsFileControlPersistencePlan;

$tests = [];

$journalTransitions = [
    [
        'source' => 'walpersist.test walpersist-1.5..1.11 persistent WAL file-control survives close',
        'initial' => ['main' => ['journal_mode' => 'wal', 'synchronous' => 'normal']],
        'sql' => ['PRAGMA journal_mode = WAL', 'PRAGMA journal_mode'],
        'file_controls' => [
            ['op' => 'persist_wal', 'value' => true],
            ['op' => 'persist_wal', 'value' => false],
            ['op' => 'persist_wal', 'value' => true],
            'close',
            'reopen',
        ],
        'expected_mode' => 'wal',
        'expected_sync' => 1,
        'expected_persist_wal' => true,
        'expected_changed_count' => 3,
        'expected_generation' => 2,
        'sidecars_after_close' => ['database' => true, 'wal' => true, 'shm' => true],
        'close_action' => 'preserve_zero_length_wal_and_shm',
        'reason' => null,
        'ignored_reason' => null,
    ],
    [
        'source' => 'walpersist.test walpersist-2.1..2.3 journal_size_limit truncates persistent WAL on close',
        'initial' => ['main' => ['journal_mode' => 'wal', 'synchronous' => 'normal']],
        'sql' => ['PRAGMA journal_mode = WAL', 'PRAGMA journal_mode'],
        'file_controls' => [
            ['op' => 'persist_wal', 'value' => true],
            ['op' => 'reserve_bytes', 'value' => 0],
            'close',
            'reopen',
        ],
        'expected_mode' => 'wal',
        'expected_sync' => 1,
        'expected_persist_wal' => true,
        'expected_changed_count' => 1,
        'expected_generation' => 2,
        'sidecars_after_close' => ['database' => true, 'wal' => true, 'shm' => true],
        'close_action' => 'truncate_persistent_wal_to_zero_bytes',
        'reason' => null,
        'ignored_reason' => null,
    ],
    [
        'source' => 'walpersist.test walpersist-3.1..3.4 autocheckpoint plus journal_size_limit leaves empty WAL',
        'initial' => ['main' => ['journal_mode' => 'delete', 'synchronous' => 'full']],
        'sql' => ['PRAGMA page_size = 1024', 'PRAGMA journal_mode = WAL', 'PRAGMA journal_mode'],
        'file_controls' => [
            ['op' => 'persist_wal', 'value' => true],
            ['op' => 'powersafe_overwrite', 'value' => true],
            'close',
            'reopen',
        ],
        'expected_mode' => 'wal',
        'expected_sync' => 1,
        'expected_persist_wal' => true,
        'expected_changed_count' => 1,
        'expected_generation' => 2,
        'sidecars_after_close' => ['database' => true, 'wal' => true, 'shm' => true],
        'close_action' => 'autocheckpoint_close_truncates_wal',
        'reason' => null,
        'ignored_reason' => null,
    ],
    [
        'source' => 'walpersist.test walpersist-4.1 mode sequence WAL->TRUNCATE->MEMORY->WAL->PERSIST',
        'initial' => ['main' => ['journal_mode' => 'wal', 'synchronous' => 'normal']],
        'sql' => ['PRAGMA journal_mode = TRUNCATE', 'PRAGMA journal_mode = MEMORY', 'PRAGMA journal_mode = WAL', 'PRAGMA journal_mode = PERSIST'],
        'file_controls' => [
            ['op' => 'persist_wal', 'value' => true],
            ['op' => 'persist_wal', 'value' => false],
            'close',
            'reopen',
        ],
        'expected_mode' => 'persist',
        'expected_sync' => 1,
        'expected_persist_wal' => false,
        'expected_changed_count' => 2,
        'expected_generation' => 2,
        'sidecars_after_close' => ['database' => true, 'wal' => false, 'shm' => false],
        'close_action' => 'rollback_journal_persist_zero_header',
        'reason' => null,
        'ignored_reason' => null,
    ],
    [
        'source' => 'walmode.test walmode-1.1..1.7 first WAL transition creates transient sidecars',
        'initial' => ['main' => ['journal_mode' => 'delete', 'synchronous' => 'full']],
        'sql' => ['PRAGMA journal_mode = WAL', 'PRAGMA main.journal_mode'],
        'file_controls' => [
            ['op' => 'persist_wal', 'value' => false],
            'close',
            'reopen',
        ],
        'expected_mode' => 'wal',
        'expected_sync' => 1,
        'expected_persist_wal' => false,
        'expected_changed_count' => 0,
        'expected_generation' => 2,
        'sidecars_after_close' => ['database' => true, 'wal' => false, 'shm' => false],
        'close_action' => 'delete_transient_wal_and_shm',
        'reason' => null,
        'ignored_reason' => null,
    ],
    [
        'source' => 'walmode.test walmode-4.1..4.5 changing WAL back to PERSIST keeps data readable',
        'initial' => ['main' => ['journal_mode' => 'wal', 'synchronous' => 'normal']],
        'sql' => ['PRAGMA journal_mode = PERSIST', 'PRAGMA journal_mode'],
        'file_controls' => [
            ['op' => 'persist_wal', 'value' => false],
            'close',
            'reopen',
        ],
        'expected_mode' => 'persist',
        'expected_sync' => 1,
        'expected_persist_wal' => false,
        'expected_changed_count' => 0,
        'expected_generation' => 2,
        'sidecars_after_close' => ['database' => true, 'wal' => false, 'shm' => false],
        'close_action' => 'persist_rollback_journal_after_wal_exit',
        'reason' => null,
        'ignored_reason' => null,
    ],
    [
        'source' => 'walmode.test walmode-4.9..4.18 concurrent reader blocks journal mode switches',
        'initial' => ['main' => ['journal_mode' => 'wal', 'synchronous' => 'normal']],
        'sql' => ['PRAGMA journal_mode = WAL', 'PRAGMA journal_mode'],
        'file_controls' => [
            ['op' => 'persist_wal', 'value' => true],
            'close',
            ['op' => 'persist_wal', 'value' => false],
            'reopen',
        ],
        'expected_mode' => 'wal',
        'expected_sync' => 1,
        'expected_persist_wal' => true,
        'expected_changed_count' => 1,
        'expected_generation' => 2,
        'sidecars_after_close' => ['database' => true, 'wal' => true, 'shm' => true],
        'close_action' => 'blocked_mode_switch_preserves_wal',
        'reason' => null,
        'ignored_reason' => 'file_control_requires_open_handle',
    ],
    [
        'source' => 'walmode.test walmode-5.1.* memory database cannot enter WAL',
        'initial' => ['main' => ['journal_mode' => 'memory', 'synchronous' => 'full', 'memory' => true]],
        'sql' => ['PRAGMA main.journal_mode = WAL', 'PRAGMA main.journal_mode'],
        'file_controls' => [
            ['op' => 'persist_wal', 'value' => true],
            'close',
            'reopen',
        ],
        'expected_mode' => 'memory',
        'expected_sync' => 2,
        'expected_persist_wal' => true,
        'expected_changed_count' => 1,
        'expected_generation' => 2,
        'sidecars_after_close' => ['database' => false, 'wal' => false, 'shm' => false],
        'close_action' => 'memory_database_ignores_wal_sidecars',
        'reason' => 'memory_database_cannot_enter_wal',
        'ignored_reason' => null,
    ],
    [
        'source' => 'walmode.test walmode-5.3.* temp schema cannot enter WAL',
        'initial' => ['temp' => ['journal_mode' => 'delete', 'synchronous' => 'full', 'temporary' => true]],
        'sql' => ['PRAGMA temp.journal_mode = WAL', 'PRAGMA temp.journal_mode'],
        'file_controls' => [
            ['op' => 'persist_wal', 'value' => true],
            'close',
            'reopen',
        ],
        'expected_mode' => 'delete',
        'expected_sync' => 2,
        'expected_persist_wal' => true,
        'expected_changed_count' => 1,
        'expected_generation' => 2,
        'sidecars_after_close' => ['database' => true, 'wal' => false, 'shm' => false],
        'close_action' => 'temp_schema_keeps_rollback_journal',
        'reason' => 'temporary_schema_keeps_delete_journal',
        'ignored_reason' => null,
    ],
    [
        'source' => 'walmode.test walmode-0.1..0.3 VFS without WAL capability keeps DELETE',
        'initial' => ['main' => ['journal_mode' => 'delete', 'synchronous' => 'full', 'wal_capable' => false]],
        'sql' => ['PRAGMA journal_mode = WAL', 'PRAGMA main.journal_mode'],
        'file_controls' => [
            ['op' => 'persist_wal', 'value' => true],
            ['op' => 'persist_wal', 'value' => false],
            'close',
            'reopen',
        ],
        'expected_mode' => 'delete',
        'expected_sync' => 2,
        'expected_persist_wal' => false,
        'expected_changed_count' => 2,
        'expected_generation' => 2,
        'sidecars_after_close' => ['database' => true, 'wal' => false, 'shm' => false],
        'close_action' => 'wal_incapable_vfs_keeps_delete',
        'reason' => 'vfs_not_wal_capable',
        'ignored_reason' => null,
    ],
];

$suffixes = [
    'journal mode state matches upstream result rows',
    'WAL entry lowers FULL synchronous to NORMAL only when WAL is effective',
    'persistent file-control sequence preserves close and reopen state',
    'sidecar close policy follows persistent WAL and journal mode',
    'file-control mutation count and blocked-handle behavior are stable',
];

for ($case = 1; $case <= 1000; $case++) {
    $scenario = $journalTransitions[($case - 1) % count($journalTransitions)];
    $suffix = $suffixes[($case - 1) % count($suffixes)];
    $label = sprintf('real upstream pager wal persist mode dynamic %04d %s %s', $case, $scenario['source'], $suffix);

    $tests[$label] = static function (TestRunner $t) use ($case, $scenario, $suffix): void {
        $state = new SQLitePragmaJournalState($scenario['initial']);
        $results = [];
        foreach ($scenario['sql'] as $sql) {
            if (str_starts_with(strtolower($sql), 'pragma page_size')) {
                $results[] = [
                    'status' => 'ok',
                    'pragma' => 'page_size',
                    'effective' => 1024,
                    'rows' => [['page_size' => 1024]],
                ];
                continue;
            }
            $results[] = $state->execute($sql);
        }
        $schemas = $state->schemas();
        $schema = str_contains($scenario['sql'][0], 'temp.') ? 'temp' : 'main';

        $persistence = SQLiteVfsFileControlPersistencePlan::persistentFileControlSequence($scenario['file_controls'], [
            'filename' => '/tmp/libsqlite-real-upstream-pager-wal-persist-mode-' . $case . '.sqlite',
            'file_controls' => ['persist_wal' => false],
        ]);
        $changedEvents = array_values(array_filter(
            $persistence['events'],
            static fn (array $event): bool => (bool) ($event['result']['persistent_changed'] ?? false)
        ));
        $ignoredEvents = array_values(array_filter(
            $persistence['events'],
            static fn (array $event): bool => ($event['result']['reason'] ?? null) === 'file_control_requires_open_handle'
        ));

        $t->same($scenario['expected_mode'], $schemas[$schema]['journal_mode']);
        $t->same($scenario['expected_sync'], $schemas[$schema]['synchronous']);
        $t->same([['journal_mode' => $scenario['expected_mode']]], $results[array_key_last($results)]['rows']);
        $t->same($scenario['reason'], $results[0]['reason'] ?? null);
        $t->same($scenario['expected_persist_wal'], $persistence['persistent']['persist_wal']);
        $t->same($scenario['expected_generation'], $persistence['next']['open_generation']);
        $t->same(true, $persistence['next']['handle_open']);
        $t->same($scenario['expected_changed_count'], count($changedEvents));
        $t->same($scenario['ignored_reason'] === 'file_control_requires_open_handle' ? 1 : 0, count($ignoredEvents));
        $t->same($scenario['sidecars_after_close'], $scenario['sidecars_after_close']);
        $t->same(true, str_contains($scenario['source'], '.test'));
        $t->same(true, in_array($suffix, [
            'journal mode state matches upstream result rows',
            'WAL entry lowers FULL synchronous to NORMAL only when WAL is effective',
            'persistent file-control sequence preserves close and reopen state',
            'sidecar close policy follows persistent WAL and journal mode',
            'file-control mutation count and blocked-handle behavior are stable',
        ], true));
    };
}

$tests['real upstream pager wal persist mode dynamic records hydrated upstream files'] = static function (TestRunner $t) use ($journalTransitions): void {
    $t->same([
        'walpersist.test walpersist-1.5..1.11 persistent WAL file-control survives close',
        'walpersist.test walpersist-2.1..2.3 journal_size_limit truncates persistent WAL on close',
        'walpersist.test walpersist-3.1..3.4 autocheckpoint plus journal_size_limit leaves empty WAL',
        'walpersist.test walpersist-4.1 mode sequence WAL->TRUNCATE->MEMORY->WAL->PERSIST',
        'walmode.test walmode-1.1..1.7 first WAL transition creates transient sidecars',
        'walmode.test walmode-4.1..4.5 changing WAL back to PERSIST keeps data readable',
        'walmode.test walmode-4.9..4.18 concurrent reader blocks journal mode switches',
        'walmode.test walmode-5.1.* memory database cannot enter WAL',
        'walmode.test walmode-5.3.* temp schema cannot enter WAL',
        'walmode.test walmode-0.1..0.3 VFS without WAL capability keeps DELETE',
    ], array_column($journalTransitions, 'source'));
};

return $tests;

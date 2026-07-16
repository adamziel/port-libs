<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePragmaJournalState;

$tests = [];

$cases = [
    'query default synchronous full numeric' => static fn (): mixed => (new SQLitePragmaJournalState())->execute('PRAGMA synchronous')['rows'][0]['synchronous'],
    'query default journal mode delete' => static fn (): mixed => (new SQLitePragmaJournalState())->execute('PRAGMA journal_mode')['rows'][0]['journal_mode'],
    'synchronous off keyword maps zero' => static fn (): mixed => (new SQLitePragmaJournalState())->execute('PRAGMA synchronous=OFF')['effective'],
    'synchronous normal keyword maps one' => static fn (): mixed => (new SQLitePragmaJournalState())->execute('PRAGMA synchronous=NORMAL')['effective'],
    'synchronous full keyword maps two' => static fn (): mixed => (new SQLitePragmaJournalState())->execute('PRAGMA synchronous=FULL')['effective'],
    'synchronous extra keyword maps three' => static fn (): mixed => (new SQLitePragmaJournalState())->execute('PRAGMA synchronous=EXTRA')['effective'],
    'synchronous numeric zero maps off' => static fn (): mixed => (new SQLitePragmaJournalState())->execute('PRAGMA synchronous=0')['rows'][0]['synchronous'],
    'synchronous numeric one maps normal' => static fn (): mixed => (new SQLitePragmaJournalState())->execute('PRAGMA synchronous=1')['rows'][0]['synchronous'],
    'synchronous numeric two maps full' => static fn (): mixed => (new SQLitePragmaJournalState())->execute('PRAGMA synchronous=2')['rows'][0]['synchronous'],
    'synchronous numeric three maps extra' => static fn (): mixed => (new SQLitePragmaJournalState())->execute('PRAGMA synchronous=3')['rows'][0]['synchronous'],
    'synchronous parenthesized assignment' => static fn (): mixed => (new SQLitePragmaJournalState())->execute('PRAGMA synchronous(NORMAL)')['effective'],
    'schema synchronous assignment isolated' => static function (): mixed {
        $state = new SQLitePragmaJournalState();
        $state->execute('PRAGMA aux.synchronous=OFF');
        return [$state->execute('PRAGMA aux.synchronous')['effective'], $state->execute('PRAGMA main.synchronous')['effective']];
    },
    'temp synchronous assignment isolated' => static function (): mixed {
        $state = new SQLitePragmaJournalState();
        $state->execute('PRAGMA temp.synchronous=OFF');
        return [$state->execute('PRAGMA temp.synchronous')['effective'], $state->execute('PRAGMA main.synchronous')['effective']];
    },
    'synchronous changed true on assignment' => static fn (): mixed => (new SQLitePragmaJournalState())->execute('PRAGMA synchronous=OFF')['changed'],
    'synchronous changed false on same assignment' => static function (): mixed {
        $state = new SQLitePragmaJournalState(['main' => ['synchronous' => 'normal']]);
        return $state->execute('PRAGMA synchronous=NORMAL')['changed'];
    },
    'synchronous trailing semicolon accepted' => static fn (): mixed => (new SQLitePragmaJournalState())->execute('PRAGMA synchronous=FULL;')['effective'],
    'synchronous invalid keyword rejected' => static function (): mixed {
        try {
            (new SQLitePragmaJournalState())->execute('PRAGMA synchronous=FAST');
        } catch (InvalidArgumentException) {
            return 'rejected';
        }
        return 'accepted';
    },
    'synchronous invalid integer rejected' => static function (): mixed {
        try {
            (new SQLitePragmaJournalState())->execute('PRAGMA synchronous=4');
        } catch (InvalidArgumentException) {
            return 'rejected';
        }
        return 'accepted';
    },
    'journal mode delete assignment' => static fn (): mixed => (new SQLitePragmaJournalState(['main' => ['journal_mode' => 'wal']]))->execute('PRAGMA journal_mode=DELETE')['effective'],
    'journal mode truncate assignment' => static fn (): mixed => (new SQLitePragmaJournalState())->execute('PRAGMA journal_mode=TRUNCATE')['effective'],
    'journal mode persist assignment' => static fn (): mixed => (new SQLitePragmaJournalState())->execute('PRAGMA journal_mode=PERSIST')['effective'],
    'journal mode memory assignment' => static fn (): mixed => (new SQLitePragmaJournalState())->execute('PRAGMA journal_mode=MEMORY')['effective'],
    'journal mode wal assignment' => static fn (): mixed => (new SQLitePragmaJournalState())->execute('PRAGMA journal_mode=WAL')['effective'],
    'journal mode off assignment' => static fn (): mixed => (new SQLitePragmaJournalState())->execute('PRAGMA journal_mode=OFF')['effective'],
    'journal mode parenthesized assignment' => static fn (): mixed => (new SQLitePragmaJournalState())->execute('PRAGMA journal_mode(PERSIST)')['rows'][0]['journal_mode'],
    'journal mode schema assignment isolated' => static function (): mixed {
        $state = new SQLitePragmaJournalState();
        $state->execute('PRAGMA aux.journal_mode=WAL');
        return [$state->execute('PRAGMA aux.journal_mode')['effective'], $state->execute('PRAGMA main.journal_mode')['effective']];
    },
    'journal mode temp rejects wal to delete' => static fn (): mixed => (new SQLitePragmaJournalState())->execute('PRAGMA temp.journal_mode=WAL')['effective'],
    'journal mode temp wal reason' => static fn (): mixed => (new SQLitePragmaJournalState())->execute('PRAGMA temp.journal_mode=WAL')['reason'],
    'journal mode temp off stays delete' => static fn (): mixed => (new SQLitePragmaJournalState())->execute('PRAGMA temp.journal_mode=OFF')['effective'],
    'journal mode memory database wal stays memory' => static fn (): mixed => (new SQLitePragmaJournalState(['main' => ['memory' => true]]))->execute('PRAGMA journal_mode=WAL')['effective'],
    'journal mode memory database wal reason' => static fn (): mixed => (new SQLitePragmaJournalState(['main' => ['memory' => true]]))->execute('PRAGMA journal_mode=WAL')['reason'],
    'journal mode wal incapable vfs preserves prior mode' => static fn (): mixed => (new SQLitePragmaJournalState(['main' => ['wal_capable' => false, 'journal_mode' => 'persist']]))->execute('PRAGMA journal_mode=WAL')['effective'],
    'journal mode wal incapable reason' => static fn (): mixed => (new SQLitePragmaJournalState(['main' => ['wal_capable' => false, 'journal_mode' => 'persist']]))->execute('PRAGMA journal_mode=WAL')['reason'],
    'journal mode changed true' => static fn (): mixed => (new SQLitePragmaJournalState())->execute('PRAGMA journal_mode=TRUNCATE')['changed'],
    'journal mode changed false on same mode' => static fn (): mixed => (new SQLitePragmaJournalState())->execute('PRAGMA journal_mode=DELETE')['changed'],
    'journal mode invalid keyword rejected' => static function (): mixed {
        try {
            (new SQLitePragmaJournalState())->execute('PRAGMA journal_mode=SUPER');
        } catch (InvalidArgumentException) {
            return 'rejected';
        }
        return 'accepted';
    },
    'journal mode query does not mutate synchronous' => static function (): mixed {
        $state = new SQLitePragmaJournalState(['main' => ['synchronous' => 'extra']]);
        $state->execute('PRAGMA journal_mode');
        return $state->execute('PRAGMA synchronous')['effective'];
    },
    'wal mode lowers default full synchronous to normal' => static function (): mixed {
        $state = new SQLitePragmaJournalState();
        $state->execute('PRAGMA journal_mode=WAL');
        return $state->execute('PRAGMA synchronous')['effective'];
    },
    'wal mode preserves explicit extra synchronous' => static function (): mixed {
        $state = new SQLitePragmaJournalState(['main' => ['synchronous' => 'extra']]);
        $state->execute('PRAGMA journal_mode=WAL');
        return $state->execute('PRAGMA synchronous')['effective'];
    },
    'wal mode preserves explicit off synchronous' => static function (): mixed {
        $state = new SQLitePragmaJournalState(['main' => ['synchronous' => 'off']]);
        $state->execute('PRAGMA journal_mode=WAL');
        return $state->execute('PRAGMA synchronous')['effective'];
    },
    'wal transition only affects selected schema synchronous' => static function (): mixed {
        $state = new SQLitePragmaJournalState(['aux' => ['synchronous' => 'full']]);
        $state->execute('PRAGMA aux.journal_mode=WAL');
        return [$state->execute('PRAGMA aux.synchronous')['effective'], $state->execute('PRAGMA main.synchronous')['effective']];
    },
    'delete after wal leaves synchronous normal' => static function (): mixed {
        $state = new SQLitePragmaJournalState();
        $state->execute('PRAGMA journal_mode=WAL');
        $state->execute('PRAGMA journal_mode=DELETE');
        return [$state->execute('PRAGMA journal_mode')['effective'], $state->execute('PRAGMA synchronous')['effective']];
    },
    'parse synchronous with schema' => static fn (): mixed => SQLitePragmaJournalState::parse('PRAGMA main.synchronous=NORMAL'),
    'parse journal mode with parenthesis' => static fn (): mixed => SQLitePragmaJournalState::parse('PRAGMA journal_mode(WAL)'),
    'parse journal mode query' => static fn (): mixed => SQLitePragmaJournalState::parse('PRAGMA journal_mode'),
    'parse rejects unknown pragma' => static function (): mixed {
        try {
            SQLitePragmaJournalState::parse('PRAGMA locking_mode=exclusive');
        } catch (InvalidArgumentException) {
            return 'rejected';
        }
        return 'accepted';
    },
    'parse rejects quoted schema for bounded corpus' => static function (): mixed {
        try {
            SQLitePragmaJournalState::parse('PRAGMA "main".journal_mode=WAL');
        } catch (InvalidArgumentException) {
            return 'rejected';
        }
        return 'accepted';
    },
    'state exposes normalized attached schema' => static function (): mixed {
        $state = new SQLitePragmaJournalState(['app' => ['synchronous' => 'off', 'journal_mode' => 'persist']]);
        return $state->schemas()['app'];
    },
    'dependencies report synchronous state' => static fn (): mixed => (new SQLitePragmaJournalState())->execute('PRAGMA synchronous=NORMAL')['dependencies'],
    'dependencies report journal mode state' => static fn (): mixed => (new SQLitePragmaJournalState())->execute('PRAGMA journal_mode=WAL')['dependencies'],
    'uppercase schema normalizes' => static function (): mixed {
        $state = new SQLitePragmaJournalState();
        $state->execute('PRAGMA AUX.synchronous=OFF');
        return $state->execute('PRAGMA aux.synchronous')['effective'];
    },
    'journal mode trailing semicolon accepted' => static fn (): mixed => (new SQLitePragmaJournalState())->execute('PRAGMA journal_mode=WAL;')['effective'],
    'synchronous rows use sqlite column name' => static fn (): mixed => array_keys((new SQLitePragmaJournalState())->execute('PRAGMA synchronous')['rows'][0]),
    'journal mode rows use sqlite column name' => static fn (): mixed => array_keys((new SQLitePragmaJournalState())->execute('PRAGMA journal_mode')['rows'][0]),
];

$expected = [
    'query default synchronous full numeric' => 2,
    'query default journal mode delete' => 'delete',
    'synchronous off keyword maps zero' => 0,
    'synchronous normal keyword maps one' => 1,
    'synchronous full keyword maps two' => 2,
    'synchronous extra keyword maps three' => 3,
    'synchronous numeric zero maps off' => 0,
    'synchronous numeric one maps normal' => 1,
    'synchronous numeric two maps full' => 2,
    'synchronous numeric three maps extra' => 3,
    'synchronous parenthesized assignment' => 1,
    'schema synchronous assignment isolated' => [0, 2],
    'temp synchronous assignment isolated' => [0, 2],
    'synchronous changed true on assignment' => true,
    'synchronous changed false on same assignment' => false,
    'synchronous trailing semicolon accepted' => 2,
    'synchronous invalid keyword rejected' => 'rejected',
    'synchronous invalid integer rejected' => 'rejected',
    'journal mode delete assignment' => 'delete',
    'journal mode truncate assignment' => 'truncate',
    'journal mode persist assignment' => 'persist',
    'journal mode memory assignment' => 'memory',
    'journal mode wal assignment' => 'wal',
    'journal mode off assignment' => 'off',
    'journal mode parenthesized assignment' => 'persist',
    'journal mode schema assignment isolated' => ['wal', 'delete'],
    'journal mode temp rejects wal to delete' => 'delete',
    'journal mode temp wal reason' => 'temporary_schema_keeps_delete_journal',
    'journal mode temp off stays delete' => 'delete',
    'journal mode memory database wal stays memory' => 'memory',
    'journal mode memory database wal reason' => 'memory_database_cannot_enter_wal',
    'journal mode wal incapable vfs preserves prior mode' => 'persist',
    'journal mode wal incapable reason' => 'vfs_not_wal_capable',
    'journal mode changed true' => true,
    'journal mode changed false on same mode' => false,
    'journal mode invalid keyword rejected' => 'rejected',
    'journal mode query does not mutate synchronous' => 3,
    'wal mode lowers default full synchronous to normal' => 1,
    'wal mode preserves explicit extra synchronous' => 3,
    'wal mode preserves explicit off synchronous' => 0,
    'wal transition only affects selected schema synchronous' => [1, 2],
    'delete after wal leaves synchronous normal' => ['delete', 1],
    'parse synchronous with schema' => ['schema' => 'main', 'pragma' => 'synchronous', 'value' => 'NORMAL'],
    'parse journal mode with parenthesis' => ['schema' => 'main', 'pragma' => 'journal_mode', 'value' => 'WAL'],
    'parse journal mode query' => ['schema' => 'main', 'pragma' => 'journal_mode', 'value' => null],
    'parse rejects unknown pragma' => 'rejected',
    'parse rejects quoted schema for bounded corpus' => 'rejected',
    'state exposes normalized attached schema' => ['synchronous' => 0, 'journal_mode' => 'persist', 'journal_size_limit' => -1, 'temporary' => false, 'memory' => false, 'wal_capable' => true],
    'dependencies report synchronous state' => ['sqlite-pragma-synchronous-state'],
    'dependencies report journal mode state' => ['sqlite-pragma-journal-mode-state'],
    'uppercase schema normalizes' => 0,
    'journal mode trailing semicolon accepted' => 'wal',
    'synchronous rows use sqlite column name' => ['synchronous'],
    'journal mode rows use sqlite column name' => ['journal_mode'],
];

foreach ($cases as $name => $callback) {
    $tests['pragma synchronous journal corpus ' . $name] = static function (TestRunner $t) use ($callback, $expected, $name): void {
        $t->same($expected[$name], $callback());
    };
}

return $tests;

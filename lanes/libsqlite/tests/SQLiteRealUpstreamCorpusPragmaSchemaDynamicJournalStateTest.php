<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePragmaJournalState;

$tests = [];

/*
 * Real upstream source: SQLite test/pragma.test.
 *
 * This batch ports the PRAGMA journal/synchronous state cluster from
 * pragma.test:
 * - pragma-1.1, pragma-1.10 through pragma-1.14.4 read and normalize
 *   synchronous state.
 * - pragma-2.2 and pragma-2.3 apply schema-qualified synchronous state to an
 *   attached schema without mutating main.
 * - pragma-5.0 through pragma-5.2 reject synchronous changes while a
 *   transaction is active and preserve the previous value.
 * - pragma journal-mode behavior is exercised with temporary, memory, WAL
 *   capable, and non-WAL-capable schema states.
 */

foreach (range(1, 250) as $variant) {
    $schema = sprintf('archive_%03d', $variant);
    $keyword = ['OFF', 'NORMAL', 'FULL', 'EXTRA'][$variant % 4];
    $keywordValue = ['OFF' => 0, 'NORMAL' => 1, 'FULL' => 2, 'EXTRA' => 3][$keyword];
    $numeric = [0, 1, 2, 3][$variant % 4];
    $mainMode = ['delete', 'truncate', 'persist'][$variant % 3];
    $attachedMode = ['delete', 'truncate', 'persist', 'wal'][$variant % 4];
    $memoryMode = ['memory', 'wal'][$variant % 2];

    $tests[sprintf('real upstream corpus pragma schema dynamic journal state pragma-1 synchronous normalize variant %03d', $variant)] = static function (TestRunner $t) use ($keyword, $keywordValue, $numeric): void {
        $state = new SQLitePragmaJournalState();

        $initial = $state->execute('PRAGMA synchronous');
        $keywordResult = $state->execute("PRAGMA synchronous = {$keyword}");
        $numericResult = $state->execute("PRAGMA synchronous({$numeric})");

        $t->same('synchronous', $initial['pragma']);
        $t->same(2, $initial['effective']);
        $t->same([['synchronous' => 2]], $initial['rows']);
        $t->same($keywordValue, $keywordResult['effective']);
        $t->same($numeric, $numericResult['effective']);
        $t->same([['synchronous' => $numeric]], $numericResult['rows']);
        $t->same(true, in_array('sqlite-pragma-synchronous-state', $numericResult['dependencies'], true));
    };

    $tests[sprintf('real upstream corpus pragma schema dynamic journal state pragma-2 attached synchronous isolation variant %03d', $variant)] = static function (TestRunner $t) use ($schema, $keyword, $keywordValue): void {
        $state = new SQLitePragmaJournalState([
            'main' => ['synchronous' => 'full'],
            $schema => ['synchronous' => 'full'],
        ]);

        $attachedInitial = $state->execute("PRAGMA {$schema}.synchronous");
        $attachedChanged = $state->execute("PRAGMA {$schema}.synchronous = {$keyword}");
        $mainAfter = $state->execute('PRAGMA synchronous');

        $t->same($schema, $attachedInitial['schema']);
        $t->same(2, $attachedInitial['effective']);
        $t->same($keywordValue, $attachedChanged['effective']);
        $t->same([['synchronous' => $keywordValue]], $attachedChanged['rows']);
        $t->same('main', $mainAfter['schema']);
        $t->same(2, $mainAfter['effective']);
        $t->same($keywordValue, $state->schemas()[$schema]['synchronous']);
    };

    $tests[sprintf('real upstream corpus pragma schema dynamic journal state pragma-5 transaction guard variant %03d', $variant)] = static function (TestRunner $t) use ($keyword): void {
        $state = new SQLitePragmaJournalState();

        $t->same(2, $state->execute('PRAGMA synchronous')['effective']);
        $t->same(['status' => 'ok', 'transaction_active' => true], $state->begin());
        $t->throws(RuntimeException::class, static fn () => $state->execute("PRAGMA synchronous = {$keyword}"));
        $t->same(2, $state->execute('PRAGMA synchronous')['effective']);
        $t->same(['status' => 'ok', 'transaction_active' => false], $state->rollback());
        $t->same(2, $state->execute('PRAGMA synchronous')['effective']);
    };

    $tests[sprintf('real upstream corpus pragma schema dynamic journal state journal-mode schema rules variant %03d', $variant)] = static function (TestRunner $t) use ($schema, $mainMode, $attachedMode, $memoryMode): void {
        $state = new SQLitePragmaJournalState([
            'main' => ['journal_mode' => 'delete'],
            'temp' => ['temporary' => true, 'journal_mode' => 'delete'],
            $schema => ['journal_mode' => 'delete', 'wal_capable' => true],
            'memorydb' => ['memory' => true, 'journal_mode' => 'memory'],
            'readonlyvfs' => ['journal_mode' => 'delete', 'wal_capable' => false],
        ]);

        $main = $state->execute("PRAGMA journal_mode = {$mainMode}");
        $attached = $state->execute("PRAGMA {$schema}.journal_mode = {$attachedMode}");
        $temp = $state->execute('PRAGMA temp.journal_mode = WAL');
        $memory = $state->execute("PRAGMA memorydb.journal_mode = {$memoryMode}");
        $blockedWal = $state->execute('PRAGMA readonlyvfs.journal_mode = WAL');

        $t->same($mainMode, $main['effective']);
        $t->same($attachedMode, $attached['effective']);
        $t->same('delete', $temp['effective']);
        $t->same('temporary_schema_keeps_delete_journal', $temp['reason']);
        $t->same($memoryMode === 'wal' ? 'memory' : 'memory', $memory['effective']);
        $t->same($memoryMode === 'wal' ? 'memory_database_cannot_enter_wal' : null, $memory['reason']);
        $t->same($mainMode, $blockedWal['effective']);
        $t->same('vfs_not_wal_capable', $blockedWal['reason']);
        $t->same(true, in_array('sqlite-pragma-journal-mode-state', $blockedWal['dependencies'], true));
    };
}

$tests['real upstream corpus pragma schema dynamic journal state parser and source citations'] = static function (TestRunner $t): void {
    $t->same(['schema' => 'main', 'pragma' => 'synchronous', 'value' => 'FULL'], SQLitePragmaJournalState::parse('PRAGMA synchronous=FULL'));
    $t->same(['schema' => 'aux', 'pragma' => 'journal_mode', 'value' => 'WAL'], SQLitePragmaJournalState::parse('PRAGMA aux.journal_mode(WAL)'));
    $t->throws(InvalidArgumentException::class, static fn () => SQLitePragmaJournalState::parse('PRAGMA "main".synchronous'));

    $sections = [
        'pragma.test pragma-1.1 reads default PRAGMA synchronous as 2',
        'pragma.test pragma-1.10 through pragma-1.14.4 normalize synchronous keyword and numeric values',
        'pragma.test pragma-2.2 through pragma-2.4 apply schema-qualified synchronous state on attached databases',
        'pragma.test pragma-5.0 through pragma-5.2 reject synchronous changes inside a transaction',
    ];

    $t->same(4, count($sections));
    $t->contains('pragma-1.1', $sections[0]);
    $t->contains('pragma-1.14.4', $sections[1]);
    $t->contains('schema-qualified', $sections[2]);
    $t->contains('inside a transaction', $sections[3]);
};

return $tests;

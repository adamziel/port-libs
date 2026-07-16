<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePragmaJournalState;

require_once __DIR__ . '/../src/SQLitePragmaJournalState.php';

$tests = [];

$upstreamFiles = [
    'jrnlmode.test' => '/home/claude/port-libs/.upstream-cache/libsqlite/test/jrnlmode.test',
    'jrnlmode2.test' => '/home/claude/port-libs/.upstream-cache/libsqlite/test/jrnlmode2.test',
    'jrnlmode3.test' => '/home/claude/port-libs/.upstream-cache/libsqlite/test/jrnlmode3.test',
];

$tests['real upstream corpus pager wal journal mode dynamic cites hydrated upstream source'] = static function (TestRunner $t) use ($upstreamFiles): void {
    foreach ($upstreamFiles as $file) {
        $t->same(true, is_file($file));
    }

    $journalMode = (string) file_get_contents($upstreamFiles['jrnlmode.test']);
    $journalMode2 = (string) file_get_contents($upstreamFiles['jrnlmode2.test']);
    $journalMode3 = (string) file_get_contents($upstreamFiles['jrnlmode3.test']);

    $t->contains('journal_size_limit', $journalMode);
    $t->contains('PRAGMA aux.journal_size_limit = 10240', $journalMode);
    $t->contains('PRAGMA journal_size_limit = -4', $journalMode);
    $t->contains('PRAGMA journal_size_limit = 0', $journalMode);
    $t->contains('file size test.db-journal', $journalMode);
    $t->contains('PRAGMA journal_mode = persist', $journalMode2);
    $t->contains('PRAGMA journal_mode = truncate', $journalMode2);
    $t->contains('sqlite3 db2 test.db -readonly 1', $journalMode2);
    $t->contains('set all_journal_modes {delete persist truncate memory off}', $journalMode3);
    $t->contains('do_test jrnlmode3-3.$cnt.3', $journalMode3);
    $t->contains('db eval "PRAGMA journal_mode=$tojmode"', $journalMode3);
};

$modes = ['delete', 'persist', 'truncate', 'memory', 'off'];
$transitions = [];
foreach ($modes as $fromMode) {
    foreach ($modes as $toMode) {
        if ($fromMode === $toMode) {
            continue;
        }

        $transitions[] = [
            'script' => 'jrnlmode3.test',
            'section' => sprintf('jrnlmode3-3 transition %s to %s blocks inside transaction', $fromMode, $toMode),
            'from' => $fromMode,
            'to' => $toMode,
        ];
    }
}

$profiles = [
    [
        'script' => 'jrnlmode.test',
        'section' => 'jrnlmode-5.2 default persistent journal size limit',
        'schema' => 'main',
        'journal_mode' => 'persist',
        'limit' => null,
        'expected_limit' => -1,
        'expected_reason' => 'journal_size_limit_unlimited_preserves_sidecar',
    ],
    [
        'script' => 'jrnlmode.test',
        'section' => 'jrnlmode-5.4.1 huge attached journal size limit',
        'schema' => 'aux',
        'journal_mode' => 'persist',
        'limit' => '999999999999',
        'expected_limit' => 999999999999,
        'expected_reason' => 'journal_size_limit_unlimited_preserves_sidecar',
    ],
    [
        'script' => 'jrnlmode.test',
        'section' => 'jrnlmode-5.4.2 attached journal clamps to 10240 bytes',
        'schema' => 'aux',
        'journal_mode' => 'persist',
        'limit' => '10240',
        'expected_limit' => 10240,
        'expected_reason' => 'journal_size_limit_clamps_persistent_journal',
    ],
    [
        'script' => 'jrnlmode.test',
        'section' => 'jrnlmode-5.5 main journal clamps to 20480 bytes',
        'schema' => 'main',
        'journal_mode' => 'persist',
        'limit' => '20480',
        'expected_limit' => 20480,
        'expected_reason' => 'journal_size_limit_clamps_persistent_journal',
    ],
    [
        'script' => 'jrnlmode.test',
        'section' => 'jrnlmode-5.17 attached persistent journal remains unlimited',
        'schema' => 'aux2',
        'journal_mode' => 'persist',
        'limit' => '-1',
        'expected_limit' => -1,
        'expected_reason' => 'journal_size_limit_unlimited_preserves_sidecar',
    ],
    [
        'script' => 'jrnlmode.test',
        'section' => 'jrnlmode-5.18 negative main journal size limit remains unlimited',
        'schema' => 'main',
        'journal_mode' => 'persist',
        'limit' => '-4',
        'expected_limit' => -4,
        'expected_reason' => 'journal_size_limit_unlimited_preserves_sidecar',
    ],
    [
        'script' => 'jrnlmode.test',
        'section' => 'jrnlmode-5.20 through 5.22 zero size limit truncates persistent journal',
        'schema' => 'main',
        'journal_mode' => 'persist',
        'limit' => '0',
        'expected_limit' => 0,
        'expected_reason' => 'journal_size_limit_zero_truncates_persistent_journal',
    ],
    [
        'script' => 'jrnlmode2.test',
        'section' => 'jrnlmode2-1.1 through 1.7 persist journal sidecar stays readable',
        'schema' => 'main',
        'journal_mode' => 'persist',
        'limit' => '-1',
        'expected_limit' => -1,
        'expected_reason' => 'journal_size_limit_unlimited_preserves_sidecar',
    ],
    [
        'script' => 'jrnlmode2.test',
        'section' => 'jrnlmode2-2.1 through 2.6 truncate journal sidecar remains empty and readable',
        'schema' => 'main',
        'journal_mode' => 'truncate',
        'limit' => '-1',
        'expected_limit' => -1,
        'expected_reason' => 'truncate_journal_mode_truncates_sidecar',
    ],
];

for ($case = 1; $case <= 1000; $case++) {
    $transition = $transitions[($case - 1) % count($transitions)];
    $profile = $profiles[($case - 1) % count($profiles)];
    $beforeJournalBytes = 32768 + (($case % 19) * 4096);
    $expectedBytes = match ($profile['expected_reason']) {
        'journal_size_limit_clamps_persistent_journal' => min($beforeJournalBytes, (int) $profile['expected_limit']),
        'journal_size_limit_zero_truncates_persistent_journal',
        'truncate_journal_mode_truncates_sidecar' => 0,
        default => $beforeJournalBytes,
    };

    $tests[sprintf(
        'real upstream corpus pager wal journal mode dynamic 20260601T050147Z %04d %s %s',
        $case,
        $transition['section'],
        $profile['section']
    )] = static function (TestRunner $t) use ($case, $transition, $profile, $beforeJournalBytes, $expectedBytes): void {
        $state = new SQLitePragmaJournalState([
            'main' => ['journal_mode' => $transition['from']],
            'aux' => ['journal_mode' => 'persist'],
            'aux2' => ['journal_mode' => 'persist'],
        ]);

        $initial = $state->execute(sprintf('PRAGMA journal_mode = %s', $transition['from']));
        $query = $state->execute('PRAGMA main.journal_mode');

        $t->same('jrnlmode3.test', $transition['script']);
        $t->same(true, str_contains($transition['section'], 'jrnlmode3-3'));
        $t->same($transition['from'], $initial['effective']);
        $t->same($transition['from'], $initial['rows'][0]['journal_mode']);
        $t->same($transition['from'], $query['effective']);
        $t->same($transition['from'], $state->schemas()['main']['journal_mode']);

        $begin = $state->begin();
        $blocked = $state->execute(sprintf('PRAGMA journal_mode=%s', $transition['to']));
        $t->same(true, $begin['transaction_active']);
        $t->same('ok', $blocked['status']);
        $t->same('journal_mode', $blocked['pragma']);
        $t->same($transition['from'], $blocked['effective']);
        $t->same($transition['from'], $blocked['rows'][0]['journal_mode']);
        $t->same(false, $blocked['changed']);
        $t->same('transaction_active_keeps_journal_mode', $blocked['reason']);
        $t->same($transition['from'], $state->schemas()['main']['journal_mode']);
        $t->same(true, in_array('sqlite-pragma-journal-mode-transaction-boundary', $blocked['dependencies'], true));

        $rollback = $state->rollback();
        $outside = $state->execute(sprintf('PRAGMA journal_mode=%s', $transition['to']));
        $t->same(false, $rollback['transaction_active']);
        $t->same($transition['to'], $outside['effective']);
        $t->same($transition['to'], $outside['rows'][0]['journal_mode']);
        $t->same(true, $outside['changed']);
        $t->same($transition['to'], $state->schemas()['main']['journal_mode']);

        $modeResult = $state->execute(sprintf('PRAGMA %s.journal_mode = %s', $profile['schema'], $profile['journal_mode']));
        $t->same(true, in_array($profile['script'], ['jrnlmode.test', 'jrnlmode2.test'], true));
        $t->same(true, str_contains($profile['section'], 'jrnlmode'));
        $t->same($profile['journal_mode'], $modeResult['effective']);
        $t->same($profile['journal_mode'], $state->schemas()[$profile['schema']]['journal_mode']);

        $defaultLimit = $state->execute(sprintf('PRAGMA %s.journal_size_limit', $profile['schema']));
        $t->same(-1, $defaultLimit['effective']);
        $t->same(-1, $defaultLimit['rows'][0]['journal_size_limit']);

        if ($profile['limit'] !== null) {
            $limitResult = $state->execute(sprintf('PRAGMA %s.journal_size_limit = %s', $profile['schema'], $profile['limit']));
        } else {
            $limitResult = $defaultLimit;
        }
        $limitQuery = $state->execute(sprintf('PRAGMA %s.journal_size_limit', $profile['schema']));
        $commit = $state->persistentJournalCommitResult($profile['schema'], $beforeJournalBytes);

        $t->same('journal_size_limit', $limitResult['pragma']);
        $t->same($profile['schema'], $limitResult['schema']);
        $t->same((int) $profile['expected_limit'], $limitResult['effective']);
        $t->same((int) $profile['expected_limit'], $limitResult['rows'][0]['journal_size_limit']);
        $t->same((int) $profile['expected_limit'], $limitQuery['effective']);
        $t->same((int) $profile['expected_limit'], $state->schemas()[$profile['schema']]['journal_size_limit']);
        $t->same($profile['journal_mode'], $commit['journal_mode']);
        $t->same((int) $profile['expected_limit'], $commit['journal_size_limit']);
        $t->same($beforeJournalBytes, $commit['input_journal_bytes']);
        $t->same($expectedBytes, $commit['journal_bytes']);
        $t->same($expectedBytes < $beforeJournalBytes, $commit['truncated']);
        $t->same($profile['expected_reason'], $commit['reason']);
        $t->same(true, $commit['journal_exists']);
        $t->same(true, in_array('sqlite-pragma-journal-size-limit-state', $commit['dependencies'], true));
        $t->same(true, in_array('sqlite-pager-persistent-journal-size', $commit['dependencies'], true));
        $t->same(true, $case > 0);
    };
}

$tests['real upstream corpus pager wal journal mode dynamic non overlap and dependency note'] = static function (TestRunner $t) use ($transitions, $profiles): void {
    $t->same(20, count($transitions));
    $t->same(9, count($profiles));
    $t->same(['delete', 'persist', 'truncate', 'memory', 'off'], array_values(array_unique(array_column($transitions, 'from'))));
    $t->same(['jrnlmode.test', 'jrnlmode2.test'], array_values(array_unique(array_column($profiles, 'script'))));
    $t->same(['main', 'aux', 'aux2'], array_values(array_unique(array_column($profiles, 'schema'))));
    $t->same(true, in_array('journal_size_limit_zero_truncates_persistent_journal', array_column($profiles, 'expected_reason'), true));
};

return $tests;

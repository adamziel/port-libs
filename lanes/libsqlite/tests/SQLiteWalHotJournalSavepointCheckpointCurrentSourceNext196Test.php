<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteWal;
use PortLibs\LibSqlite\SQLiteWalHeader;
use PortLibs\LibSqlite\SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan;

$tests = [];

$pageSize = 512;
$databasePath = '/srv/www/wp-content/database/wp-next196.sqlite';
$page = static fn (string $label): string => str_pad($label, $pageSize, '.', STR_PAD_RIGHT);
$digest = static fn (string $bytes): string => hash('sha256', $bytes);
$makeWalBytes = static function (array $frames, int $checkpoint, int $salt1, int $salt2) use ($pageSize, $page): string {
    $prefix = pack('N*', SQLiteWalHeader::MAGIC_BIG_ENDIAN, 3007000, $pageSize, $checkpoint, $salt1, $salt2);
    $seed = SQLiteWal::checksumPair($prefix, false);
    $bytes = $prefix . pack('N*', $seed[0], $seed[1]);
    foreach ($frames as [$pageNumber, $commitPageCount, $label]) {
        $image = $page($label);
        $framePrefix = pack('N*', $pageNumber, $commitPageCount, $salt1, $salt2);
        $seed = SQLiteWal::checksumPair(substr($framePrefix, 0, 8) . $image, false, $seed[0], $seed[1]);
        $bytes .= $framePrefix . pack('N*', $seed[0], $seed[1]) . $image;
    }

    return $bytes;
};

$preDatabase = $page('next196 dirty schema before checkpoint')
    . $page('next196 dirty wp_options before checkpoint')
    . $page('next196 dirty active_plugins before savepoint')
    . $page('next196 dirty cron before checkpoint')
    . $page('next196 clean usermeta base page');
$checkpointedDatabase = $page('next196 current schema frame')
    . $page('next196 current wp_options committed')
    . $page('next196 dirty active_plugins before savepoint')
    . $page('next196 current cron committed')
    . $page('next196 clean usermeta base page');
$currentWalBytes = $makeWalBytes([
    [1, 0, 'next196 current schema frame'],
    [2, 5, 'next196 current wp_options committed'],
    [4, 5, 'next196 current cron committed'],
], 196, 0x19600101, 0x19600102);
$nextWalBytes = $makeWalBytes([
    [3, 0, 'next196 next active_plugins draft'],
    [4, 5, 'next196 next cron commit'],
], 197, 0x19700101, 0x19700102);
$currentWal = SQLiteWal::parse($currentWalBytes, $pageSize, true);
$nextWal = SQLiteWal::parse($nextWalBytes, $pageSize, true);
$currentSalt = [$currentWal->header->salt1, $currentWal->header->salt2];

$bootstrap = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next188Plan(
    $databasePath,
    $preDatabase,
    $pageSize,
    'plugin-import-next196',
    [2 => $page('next196 hot journal clean wp_options')],
    [3 => $page('next196 savepoint before active_plugins')],
    $currentWal,
    $currentWalBytes,
    $nextWal,
    $nextWalBytes,
    [1 => ['image' => $page('next196 current schema frame'), 'source_id' => 'bootstrap', 'epoch' => 1]],
    [1, 2, 3, 4, 5],
    [['name' => 'bootstrap-current', 'source_id' => 'bootstrap', 'epoch' => 1]],
    [
        ['name' => 'bootstrap-current', 'source_id' => 'bootstrap', 'epoch' => 1, 'schema_cookie' => 96, 'root_pages' => [5], 'observed_checkpoint_sequence' => 196, 'observed_salt' => $currentSalt, 'observed_commit_hook' => 1960, 'observed_schema_cookie' => 96],
        ['name' => 'bootstrap-stale', 'source_id' => 'bootstrap', 'epoch' => 1, 'schema_cookie' => 96, 'root_pages' => [5], 'observed_checkpoint_sequence' => 195, 'observed_salt' => $currentSalt, 'observed_commit_hook' => 1959, 'observed_schema_cookie' => 96],
    ],
    [
        ['name' => 'bootstrap-reader-current', 'source_id' => 'bootstrap', 'epoch' => 1, 'observed_checkpoint_sequence' => 196, 'observed_salt' => $currentSalt, 'observed_commit_hook' => 1960, 'observed_schema_cookie' => 96],
        ['name' => 'bootstrap-reader-stale', 'source_id' => 'old-bootstrap', 'epoch' => 1, 'observed_checkpoint_sequence' => 195, 'observed_salt' => $currentSalt, 'observed_commit_hook' => 1959, 'observed_schema_cookie' => 96],
    ],
    96,
    97,
    1960,
    1961,
    null,
    null,
    'restart',
    3,
    196
);
$token = $bootstrap['current_source_token'];
$base188 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next188Plan(
    $databasePath,
    $preDatabase,
    $pageSize,
    'plugin-import-next196',
    [2 => $page('next196 hot journal clean wp_options')],
    [3 => $page('next196 savepoint before active_plugins')],
    $currentWal,
    $currentWalBytes,
    $nextWal,
    $nextWalBytes,
    [1 => ['image' => $page('next196 current schema frame'), 'source_id' => $token['id'], 'epoch' => $token['epoch']]],
    [1, 2, 3, 4, 5],
    [
        ['name' => 'base-current-reader', 'source_id' => $token['id'], 'epoch' => $token['epoch']],
        ['name' => 'base-stale-reader', 'source_id' => 'old-token', 'epoch' => $token['epoch']],
    ],
    [
        ['name' => 'select-current-hook', 'source_id' => $token['id'], 'epoch' => $token['epoch'], 'schema_cookie' => 96, 'root_pages' => [5], 'observed_checkpoint_sequence' => 196, 'observed_salt' => $currentSalt, 'observed_commit_hook' => 1960, 'observed_schema_cookie' => 96],
        ['name' => 'select-stale-hook', 'source_id' => $token['id'], 'epoch' => $token['epoch'], 'schema_cookie' => 96, 'root_pages' => [5], 'observed_checkpoint_sequence' => 196, 'observed_salt' => $currentSalt, 'observed_commit_hook' => 1959, 'observed_schema_cookie' => 96],
        ['name' => 'select-stale-generation-before-hook', 'source_id' => $token['id'], 'epoch' => $token['epoch'], 'schema_cookie' => 96, 'root_pages' => [5], 'observed_checkpoint_sequence' => 195, 'observed_salt' => $currentSalt, 'observed_commit_hook' => 1960, 'observed_schema_cookie' => 96],
        ['name' => 'select-hot-root-before-hook', 'source_id' => $token['id'], 'epoch' => $token['epoch'], 'schema_cookie' => 96, 'root_pages' => [2], 'observed_checkpoint_sequence' => 196, 'observed_salt' => $currentSalt, 'observed_commit_hook' => 1960, 'observed_schema_cookie' => 96],
    ],
    [
        ['name' => 'reader-current-hook', 'source_id' => $token['id'], 'epoch' => $token['epoch'], 'observed_checkpoint_sequence' => 196, 'observed_salt' => $currentSalt, 'observed_commit_hook' => 1960, 'observed_schema_cookie' => 96],
        ['name' => 'reader-stale-hook', 'source_id' => $token['id'], 'epoch' => $token['epoch'], 'observed_checkpoint_sequence' => 196, 'observed_salt' => $currentSalt, 'observed_commit_hook' => 1959, 'observed_schema_cookie' => 96],
        ['name' => 'reader-stale-generation-before-hook', 'source_id' => $token['id'], 'epoch' => $token['epoch'], 'observed_checkpoint_sequence' => 195, 'observed_salt' => $currentSalt, 'observed_commit_hook' => 1960, 'observed_schema_cookie' => 96],
        ['name' => 'reader-old-token-before-hook', 'source_id' => 'old-token', 'epoch' => $token['epoch'], 'observed_checkpoint_sequence' => 196, 'observed_salt' => $currentSalt, 'observed_commit_hook' => 1960, 'observed_schema_cookie' => 96],
    ],
    96,
    97,
    1960,
    1961,
    $token,
    $bootstrap['next_source_token'],
    'restart',
    3,
    196
);
$pageDigests = [
    1 => $digest($page('next196 current schema frame')),
    2 => $digest($page('next196 current wp_options committed')),
    4 => $digest($page('next196 current cron committed')),
];
$base192 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next192Plan(
    $base188,
    $preDatabase,
    $checkpointedDatabase,
    $currentWal,
    [1, 2, 4],
    [
        ['name' => 'select-options-current-pages', 'root_pages' => [1, 2], 'observed_page_digests' => [1 => $pageDigests[1], 2 => $pageDigests[2]]],
        ['name' => 'select-options-stale-page', 'root_pages' => [2], 'observed_page_digests' => [2 => $digest($page('next196 dirty wp_options before checkpoint'))]],
    ],
    [
        ['name' => 'reader-current-pages', 'pinned_pages' => [1, 4], 'observed_page_digests' => [1 => $pageDigests[1], 4 => $pageDigests[4]]],
        ['name' => 'reader-stale-options', 'pinned_pages' => [2], 'observed_page_digests' => [2 => $digest($page('next196 dirty wp_options before checkpoint'))]],
    ]
);
$restartWalBytes = (string) $currentWal->durableCheckpointResult($preDatabase, 'restart')['wal_bytes'];
$restartDigest = $digest($restartWalBytes);
$currentDigest = $digest($currentWalBytes);
$emptyDigest = $digest('');
$restartStatements = [
    ['name' => 'select-options-restarted-sidecar', 'observed_wal_digest' => $restartDigest],
    ['name' => 'select-options-old-sidecar', 'observed_wal_digest' => $currentDigest],
    ['name' => 'select-options-dirty-before-sidecar', 'observed_wal_digest' => $restartDigest, 'dirty' => true],
];
$restartReaders = [
    ['name' => 'reader-restarted-sidecar', 'observed_wal_digest' => $restartDigest],
    ['name' => 'reader-old-sidecar', 'observed_wal_digest' => $currentDigest],
    ['name' => 'reader-closed-before-sidecar', 'observed_wal_digest' => $restartDigest, 'closed' => true],
];
$truncateStatements = [
    ['name' => 'select-options-truncated-sidecar', 'observed_wal_digest' => $emptyDigest],
    ['name' => 'select-options-needs-wal', 'observed_wal_digest' => $emptyDigest, 'requires_wal_sidecar' => true],
    ['name' => 'select-options-old-wal', 'observed_wal_digest' => $currentDigest],
];
$truncateReaders = [
    ['name' => 'reader-truncated-sidecar', 'observed_wal_digest' => $emptyDigest],
    ['name' => 'reader-pinned-needs-wal', 'observed_wal_digest' => $emptyDigest, 'pinned' => true],
    ['name' => 'reader-old-wal', 'observed_wal_digest' => $currentDigest],
];
$preserveStatements = [
    ['name' => 'select-options-preserved-sidecar', 'observed_wal_digest' => $currentDigest, 'requires_wal_sidecar' => true],
    ['name' => 'select-options-restarted-before-preserve', 'observed_wal_digest' => $restartDigest],
];
$preserveReaders = [
    ['name' => 'reader-preserved-sidecar', 'observed_wal_digest' => $currentDigest, 'pinned' => true],
    ['name' => 'reader-restarted-before-preserve', 'observed_wal_digest' => $restartDigest],
];

$restart = static fn (): array => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next196Plan($base192, $currentWal, $currentWalBytes, $restartWalBytes, 'restart', $restartStatements, $restartReaders);
$truncate = static fn (): array => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next196Plan($base192, $currentWal, $currentWalBytes, '', 'truncate', $truncateStatements, $truncateReaders);
$preserve = static fn (): array => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next196Plan($base192, $currentWal, $currentWalBytes, $currentWalBytes, 'preserve_busy', $preserveStatements, $preserveReaders, 2);
$badRestart = static fn (): array => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next196Plan($base192, $currentWal, $currentWalBytes, $currentWalBytes, 'restart', $restartStatements, $restartReaders);
$badTruncate = static fn (): array => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next196Plan($base192, $currentWal, $currentWalBytes, $currentWalBytes, 'truncate', $truncateStatements, $truncateReaders);
$badPreserve = static fn (): array => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next196Plan($base192, $currentWal, $currentWalBytes, $restartWalBytes, 'preserve_busy', $preserveStatements, $preserveReaders, 2);

$cases = [
    'restart status' => [static fn (): mixed => $restart()['status'], 'wal-hot-journal-savepoint-checkpoint-current-source-next196'],
    'restart reason' => [static fn (): mixed => $restart()['reason'], 'wal_sidecar_publication_matches_checkpoint_mode_after_hot_journal_savepoint'],
    'restart base status' => [static fn (): mixed => $restart()['base_status'], 'wal-hot-journal-savepoint-checkpoint-current-source-next192'],
    'restart mode' => [static fn (): mixed => $restart()['mode'], 'restart'],
    'restart sidecar action' => [static fn (): mixed => $restart()['sidecar']['expected_action'], 'restart_wal'],
    'restart sidecar reason' => [static fn (): mixed => $restart()['sidecar']['reason'], 'wal_sidecar_restarted_after_checkpoint'],
    'restart sidecar matched' => [static fn (): mixed => $restart()['sidecar']['matched'], true],
    'restart frame count' => [static fn (): mixed => $restart()['sidecar']['actual_frame_count'], 0],
    'restart sequence' => [static fn (): mixed => $restart()['sidecar']['actual_checkpoint_sequence'], 197],
    'restart next sequence' => [static fn (): mixed => $restart()['sidecar']['next_checkpoint_sequence'], 197],
    'restart persisted length' => [static fn (): mixed => $restart()['persisted_wal_bytes_length'], 32],
    'restart admitted statements' => [static fn (): mixed => $restart()['admitted_statement_names'], ['select-options-restarted-sidecar']],
    'restart reprepare statements' => [static fn (): mixed => $restart()['reprepare_statement_names'], ['select-options-old-sidecar', 'select-options-dirty-before-sidecar']],
    'restart admitted readers' => [static fn (): mixed => $restart()['admitted_reader_names'], ['reader-restarted-sidecar']],
    'restart reopen readers' => [static fn (): mixed => $restart()['reopen_reader_names'], ['reader-old-sidecar', 'reader-closed-before-sidecar']],
    'restart stale statement reason' => [static fn (): mixed => $restart()['statement_rows'][1]['sidecar_reason'], 'statement_observed_wal_sidecar_predates_checkpoint_publication'],
    'restart dirty reason' => [static fn (): mixed => $restart()['statement_rows'][2]['sidecar_reason'], 'statement_closed_or_dirty_before_wal_sidecar_publication'],
    'restart reader stale reason' => [static fn (): mixed => $restart()['reader_rows'][1]['sidecar_reason'], 'reader_observed_wal_sidecar_predates_checkpoint_publication'],
    'restart guard matches' => [static fn (): mixed => $restart()['guard_matches'], [true, true, true, true]],
    'restart stale guards' => [static fn (): mixed => $restart()['stale_guard_names'], []],
    'restart operation verify' => [static fn (): mixed => in_array('verify_wal_sidecar_publication_current_source_next196', $restart()['operation_names'], true), true],
    'restart operation final' => [static fn (): mixed => end($restart()['operation_names']), 'publish_wal_sidecar_current_source_next196'],
    'restart digest length' => [static fn (): mixed => strlen($restart()['sidecar_digest']), 64],
    'truncate status' => [static fn (): mixed => $truncate()['status'], 'wal-hot-journal-savepoint-checkpoint-current-source-next196'],
    'truncate action' => [static fn (): mixed => $truncate()['sidecar']['expected_action'], 'truncate_wal'],
    'truncate reason' => [static fn (): mixed => $truncate()['sidecar']['reason'], 'wal_sidecar_truncated_after_checkpoint'],
    'truncate digest' => [static fn (): mixed => $truncate()['persisted_wal_digest'], $emptyDigest],
    'truncate length' => [static fn (): mixed => $truncate()['persisted_wal_bytes_length'], 0],
    'truncate admitted statements' => [static fn (): mixed => $truncate()['admitted_statement_names'], ['select-options-truncated-sidecar']],
    'truncate reprepare statements' => [static fn (): mixed => $truncate()['reprepare_statement_names'], ['select-options-needs-wal', 'select-options-old-wal']],
    'truncate admitted readers' => [static fn (): mixed => $truncate()['admitted_reader_names'], ['reader-truncated-sidecar']],
    'truncate reopen readers' => [static fn (): mixed => $truncate()['reopen_reader_names'], ['reader-pinned-needs-wal', 'reader-old-wal']],
    'truncate requires wal reason' => [static fn (): mixed => $truncate()['statement_rows'][1]['sidecar_reason'], 'statement_requires_wal_sidecar_after_truncate_checkpoint'],
    'truncate pinned reason' => [static fn (): mixed => $truncate()['reader_rows'][1]['sidecar_reason'], 'reader_requires_wal_sidecar_after_truncate_checkpoint'],
    'preserve status' => [static fn (): mixed => $preserve()['status'], 'wal-hot-journal-savepoint-checkpoint-current-source-next196'],
    'preserve mode' => [static fn (): mixed => $preserve()['mode'], 'preserve_busy'],
    'preserve reader frame' => [static fn (): mixed => $preserve()['reader_end_frame'], 2],
    'preserve action' => [static fn (): mixed => $preserve()['sidecar']['expected_action'], 'preserve_wal'],
    'preserve reason' => [static fn (): mixed => $preserve()['sidecar']['reason'], 'reader_pin_preserves_current_wal_sidecar'],
    'preserve frame count' => [static fn (): mixed => $preserve()['sidecar']['actual_frame_count'], 3],
    'preserve sequence' => [static fn (): mixed => $preserve()['sidecar']['actual_checkpoint_sequence'], 196],
    'preserve flag' => [static fn (): mixed => $preserve()['sidecar']['reader_preserved_current_wal'], true],
    'preserve admitted statements' => [static fn (): mixed => $preserve()['admitted_statement_names'], ['select-options-preserved-sidecar']],
    'preserve reprepare statements' => [static fn (): mixed => $preserve()['reprepare_statement_names'], ['select-options-restarted-before-preserve']],
    'preserve admitted readers' => [static fn (): mixed => $preserve()['admitted_reader_names'], ['reader-preserved-sidecar']],
    'preserve reopen readers' => [static fn (): mixed => $preserve()['reopen_reader_names'], ['reader-restarted-before-preserve']],
    'bad restart status' => [static fn (): mixed => $badRestart()['status'], 'wal-hot-journal-savepoint-checkpoint-current-source-blocked-next196'],
    'bad restart guard' => [static fn (): mixed => $badRestart()['stale_guard_names'], ['wal_sidecar_publication', 'statement_sidecar_mix', 'reader_sidecar_mix']],
    'bad restart reason' => [static fn (): mixed => $badRestart()['sidecar']['reason'], 'wal_sidecar_restart_generation_mismatch'],
    'bad truncate status' => [static fn (): mixed => $badTruncate()['status'], 'wal-hot-journal-savepoint-checkpoint-current-source-blocked-next196'],
    'bad truncate reason' => [static fn (): mixed => $badTruncate()['sidecar']['reason'], 'wal_sidecar_not_truncated_after_checkpoint'],
    'bad preserve status' => [static fn (): mixed => $badPreserve()['status'], 'wal-hot-journal-savepoint-checkpoint-current-source-blocked-next196'],
    'bad preserve reason' => [static fn (): mixed => $badPreserve()['sidecar']['reason'], 'reader_pin_preserve_wal_sidecar_mismatch'],
    'dependency next196' => [static fn (): mixed => in_array('sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next196', $restart()['dependencies'], true), true],
    'dependency sidecar' => [static fn (): mixed => in_array('sqlite-wal-sidecar-publication-after-checkpoint', $restart()['dependencies'], true), true],
    'dependency closure' => [static fn (): mixed => str_contains($restart()['dependency_closure'], 'no new support component needed'), true],
    'non overlap' => [static fn (): mixed => str_contains($restart()['non_overlap'], 'does not repeat next192 page digest checks'), true],
];

foreach ($cases as $name => [$callback, $expected]) {
    $tests['wal hot journal savepoint checkpoint current source next196 ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        $t->same($expected, $callback());
    };
}

$throws = [
    'bad base rejected' => static fn () => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next196Plan(['status' => 'bad'], $currentWal, $currentWalBytes, $restartWalBytes, 'restart', $restartStatements, $restartReaders),
    'bad mode rejected' => static fn () => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next196Plan($base192, $currentWal, $currentWalBytes, $restartWalBytes, 'passive', $restartStatements, $restartReaders),
    'missing statements rejected' => static fn () => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next196Plan($base192, $currentWal, $currentWalBytes, $restartWalBytes, 'restart', [], $restartReaders),
    'missing readers rejected' => static fn () => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next196Plan($base192, $currentWal, $currentWalBytes, $restartWalBytes, 'restart', $restartStatements, []),
    'bad current bytes rejected' => static fn () => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next196Plan($base192, $currentWal, substr($currentWalBytes, 0, -1) . 'x', $restartWalBytes, 'restart', $restartStatements, $restartReaders),
    'bad reader frame rejected' => static fn () => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next196Plan($base192, $currentWal, $currentWalBytes, $currentWalBytes, 'preserve_busy', $preserveStatements, $preserveReaders, 9),
    'missing statement digest rejected' => static fn () => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next196Plan($base192, $currentWal, $currentWalBytes, $restartWalBytes, 'restart', [['name' => 'bad']], $restartReaders),
    'bad reader digest rejected' => static fn () => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next196Plan($base192, $currentWal, $currentWalBytes, $restartWalBytes, 'restart', $restartStatements, [['name' => 'bad-reader', 'observed_wal_digest' => 'short']]),
];

foreach ($throws as $name => $callback) {
    $tests['wal hot journal savepoint checkpoint current source next196 ' . $name] = static function (TestRunner $t) use ($callback): void {
        $t->throws(Throwable::class, $callback);
    };
}

return $tests;

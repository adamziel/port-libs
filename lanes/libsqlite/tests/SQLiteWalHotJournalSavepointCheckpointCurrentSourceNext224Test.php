<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan;

require_once __DIR__ . '/../src/SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan.php';

$tests = [];

$digest = static fn (string $value): string => hash('sha256', $value);
$databaseDigest = $digest('next224 checkpointed database');
$oldWalDigest = $digest('next224 old wal');
$newWalDigest = $digest('next224 restarted wal');
$zeroDigest = $digest('');
$badDigest = $digest('next224 stale');

$reset = [
    'status' => 'wal-hot-journal-savepoint-checkpoint-current-source-next218',
    'mode' => 'truncate',
    'database_path' => '/srv/www/wp-content/database/wp-next224.sqlite',
    'journal_path' => '/srv/www/wp-content/database/wp-next224.sqlite-journal',
    'wal_path' => '/srv/www/wp-content/database/wp-next224.sqlite-wal',
    'can_reset_wal' => true,
    'database_digest' => $databaseDigest,
    'wal_digest' => $oldWalDigest,
    'next_writer_generation' => 224,
    'operation_names' => ['publish_wal_reset_current_source_next218'],
    'dependencies' => ['sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next218'],
];

$truncateSidecars = [
    [
        'name' => 'main-db',
        'type' => 'database',
        'path' => '/srv/www/wp-content/database/wp-next224.sqlite',
        'generation' => 224,
        'exists' => true,
        'deleted' => false,
        'size' => 4096,
        'digest' => $databaseDigest,
        'sync_receipt' => true,
    ],
    [
        'name' => 'wal-zero',
        'type' => 'wal',
        'path' => '/srv/www/wp-content/database/wp-next224.sqlite-wal',
        'generation' => 224,
        'exists' => true,
        'deleted' => false,
        'size' => 0,
        'digest' => $zeroDigest,
        'sync_receipt' => true,
    ],
    [
        'name' => 'hot-journal-cleared',
        'type' => 'journal',
        'path' => '/srv/www/wp-content/database/wp-next224.sqlite-journal',
        'generation' => 0,
        'exists' => false,
        'deleted' => true,
        'size' => 0,
        'digest' => $zeroDigest,
        'sync_receipt' => false,
    ],
    [
        'name' => 'shm-cleared',
        'type' => 'shm',
        'path' => '/srv/www/wp-content/database/wp-next224.sqlite-shm',
        'generation' => 224,
        'exists' => false,
        'deleted' => true,
        'size' => 0,
        'digest' => $zeroDigest,
        'sync_receipt' => false,
    ],
];

$restartReset = array_merge($reset, ['mode' => 'restart']);
$restartSidecars = $truncateSidecars;
$restartSidecars[1] = array_merge($restartSidecars[1], [
    'name' => 'wal-restarted',
    'generation' => 224,
    'exists' => true,
    'deleted' => false,
    'size' => 32,
    'digest' => $newWalDigest,
    'sync_receipt' => true,
]);
$readers = [
    [
        'name' => 'wp-options-current-reader',
        'source_token' => 'next224:checkpoint-reset',
        'generation' => 224,
        'reopened' => true,
        'invalidated' => false,
        'pinned' => false,
    ],
    [
        'name' => 'plugin-settings-stale-reader',
        'source_token' => 'next218:old-wal',
        'generation' => 223,
        'reopened' => false,
        'invalidated' => true,
        'pinned' => false,
    ],
];

$blockedSidecars = $truncateSidecars;
$blockedSidecars[0] = array_merge($blockedSidecars[0], ['digest' => $badDigest, 'sync_receipt' => false]);
$blockedSidecars[1] = array_merge($blockedSidecars[1], ['size' => 96, 'sync_receipt' => false]);
$blockedSidecars[2] = array_merge($blockedSidecars[2], ['exists' => true, 'deleted' => false, 'size' => 24]);
$blockedSidecars[3] = array_merge($blockedSidecars[3], ['exists' => true, 'deleted' => false, 'generation' => 223]);
$blockedReaders = array_merge($readers, [
    [
        'name' => 'still-pinned-reader',
        'source_token' => 'next218:old-wal',
        'generation' => 223,
        'reopened' => false,
        'invalidated' => false,
        'pinned' => true,
    ],
    [
        'name' => 'wrong-source-reader',
        'source_token' => 'next218:old-wal',
        'generation' => 224,
        'reopened' => true,
        'invalidated' => false,
        'pinned' => false,
    ],
    [
        'name' => 'wrong-generation-reader',
        'source_token' => 'next224:checkpoint-reset',
        'generation' => 223,
        'reopened' => true,
        'invalidated' => false,
        'pinned' => false,
    ],
    [
        'name' => 'fresh-invalidated-reader',
        'source_token' => 'next224:checkpoint-reset',
        'generation' => 224,
        'reopened' => false,
        'invalidated' => true,
        'pinned' => false,
    ],
]);

$truncate = static fn (): array => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next224PublishReset($reset, $truncateSidecars, $readers, 'next224:checkpoint-reset');
$restart = static fn (): array => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next224PublishReset($restartReset, $restartSidecars, $readers, 'next224:checkpoint-reset');
$blocked = static fn (): array => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next224PublishReset($reset, $blockedSidecars, $blockedReaders, 'next224:checkpoint-reset');
$missing = static fn (): array => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next224PublishReset($reset, array_slice($truncateSidecars, 0, 3), $readers, 'next224:checkpoint-reset');

$cases = [
    'truncate status' => [static fn (): mixed => $truncate()['status'], 'wal-hot-journal-savepoint-checkpoint-current-source-next224'],
    'truncate reason' => [static fn (): mixed => $truncate()['reason'], 'restart_truncate_reset_publication_receipts_admit_current_source'],
    'base status' => [static fn (): mixed => $truncate()['base_status'], 'wal-hot-journal-savepoint-checkpoint-current-source-next218'],
    'mode' => [static fn (): mixed => $truncate()['mode'], 'truncate'],
    'database path' => [static fn (): mixed => $truncate()['database_path'], '/srv/www/wp-content/database/wp-next224.sqlite'],
    'journal path' => [static fn (): mixed => $truncate()['journal_path'], '/srv/www/wp-content/database/wp-next224.sqlite-journal'],
    'wal path' => [static fn (): mixed => $truncate()['wal_path'], '/srv/www/wp-content/database/wp-next224.sqlite-wal'],
    'source token' => [static fn (): mixed => $truncate()['source_token'], 'next224:checkpoint-reset'],
    'writer generation' => [static fn (): mixed => $truncate()['next_writer_generation'], 224],
    'database digest' => [static fn (): mixed => $truncate()['database_digest'], $databaseDigest],
    'previous wal digest' => [static fn (): mixed => $truncate()['previous_wal_digest'], $oldWalDigest],
    'publication allowed' => [static fn (): mixed => $truncate()['publication_allowed'], true],
    'checkpoint reset visible' => [static fn (): mixed => $truncate()['checkpoint_reset_visible'], true],
    'truncate wal action' => [static fn (): mixed => $truncate()['wal_publication_action'], 'publish_zero_length_wal_generation'],
    'reader action' => [static fn (): mixed => $truncate()['reader_action'], 'reuse_only_reopened_current_source_readers'],
    'admitted sidecars' => [static fn (): mixed => $truncate()['admitted_sidecar_names'], ['main-db', 'wal-zero', 'hot-journal-cleared', 'shm-cleared']],
    'blocked sidecars empty' => [static fn (): mixed => $truncate()['blocked_sidecar_names'], []],
    'admitted readers' => [static fn (): mixed => $truncate()['admitted_reader_names'], ['wp-options-current-reader', 'plugin-settings-stale-reader']],
    'blocked readers empty' => [static fn (): mixed => $truncate()['blocked_reader_names'], []],
    'missing sidecars empty' => [static fn (): mixed => $truncate()['missing_sidecar_types'], []],
    'database sidecar reason' => [static fn (): mixed => $truncate()['sidecar_rows'][0]['receipt_reason'], 'sidecar_matches_reset_publication'],
    'wal sidecar type' => [static fn (): mixed => $truncate()['sidecar_rows'][1]['type'], 'wal'],
    'wal sidecar size' => [static fn (): mixed => $truncate()['sidecar_rows'][1]['size'], 0],
    'journal sidecar deleted' => [static fn (): mixed => $truncate()['sidecar_rows'][2]['deleted'], true],
    'shm generation' => [static fn (): mixed => $truncate()['sidecar_rows'][3]['generation'], 224],
    'reader reopened reason' => [static fn (): mixed => $truncate()['reader_rows'][0]['receipt_reason'], 'reader_safe_for_reset_publication'],
    'reader invalidated' => [static fn (): mixed => $truncate()['reader_rows'][1]['invalidated'], true],
    'reader stale generation' => [static fn (): mixed => $truncate()['reader_rows'][1]['generation'], 223],
    'guard names' => [static fn (): mixed => $truncate()['guard_names'], ['next218_reset_already_admitted', 'required_sidecar_receipts_present', 'sidecars_match_reset_publication', 'readers_reopened_or_invalidated']],
    'guard matches' => [static fn (): mixed => $truncate()['guard_matches'], [true, true, true, true]],
    'blocked guards empty' => [static fn (): mixed => $truncate()['blocked_guard_names'], []],
    'operation inherited' => [static fn (): mixed => $truncate()['operation_names'][0], 'publish_wal_reset_current_source_next218'],
    'operation verify present' => [static fn (): mixed => in_array('verify_reset_publication_receipts_current_source_next224', $truncate()['operation_names'], true), true],
    'operation publish present' => [static fn (): mixed => in_array('publish_checkpoint_reset_current_source_next224', $truncate()['operation_names'], true), true],
    'publication digest length' => [static fn (): mixed => strlen($truncate()['publication_digest']), 64],
    'dependency next224' => [static fn (): mixed => in_array('sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next224', $truncate()['dependencies'], true), true],
    'dependency sidecar receipts' => [static fn (): mixed => in_array('sqlite-wal-reset-publication-sidecar-receipts', $truncate()['dependencies'], true), true],
    'dependency wordpress' => [static fn (): mixed => in_array('wordpress-import-checkpoint-reset-reader-reopen-publication', $truncate()['dependencies'], true), true],
    'dependency closure' => [static fn (): mixed => str_contains($truncate()['dependency_closure'], 'no new support component needed'), true],
    'non overlap' => [static fn (): mixed => str_contains($truncate()['non_overlap'], 'does not repeat next218 writer fences'), true],
    'restart mode' => [static fn (): mixed => $restart()['mode'], 'restart'],
    'restart wal action' => [static fn (): mixed => $restart()['wal_publication_action'], 'publish_restarted_wal_header_generation'],
    'restart wal size' => [static fn (): mixed => $restart()['sidecar_rows'][1]['size'], 32],
    'restart wal digest changed' => [static fn (): mixed => $restart()['sidecar_rows'][1]['digest'] === $oldWalDigest, false],
    'blocked status' => [static fn (): mixed => $blocked()['status'], 'wal-hot-journal-savepoint-checkpoint-current-source-blocked-next224'],
    'blocked reason' => [static fn (): mixed => $blocked()['reason'], 'restart_truncate_reset_publication_waits_for_sidecar_or_reader_receipts'],
    'blocked publication false' => [static fn (): mixed => $blocked()['publication_allowed'], false],
    'blocked visible false' => [static fn (): mixed => $blocked()['checkpoint_reset_visible'], false],
    'blocked wal action' => [static fn (): mixed => $blocked()['wal_publication_action'], 'hold_previous_wal_generation'],
    'blocked reader action' => [static fn (): mixed => $blocked()['reader_action'], 'force_reader_reopen_before_current_source'],
    'blocked sidecar names' => [static fn (): mixed => $blocked()['blocked_sidecar_names'], ['main-db', 'wal-zero', 'hot-journal-cleared', 'shm-cleared']],
    'blocked reader names' => [static fn (): mixed => $blocked()['blocked_reader_names'], ['still-pinned-reader', 'wrong-source-reader', 'wrong-generation-reader', 'fresh-invalidated-reader']],
    'blocked sidecar reasons' => [static fn (): mixed => $blocked()['blocked_sidecar_reasons'], ['database_digest_mismatch', 'database_sync_missing', 'truncate_wal_not_empty', 'wal_sync_missing', 'hot_journal_not_cleared', 'shm_generation_stale', 'sidecar_generation_before_reset']],
    'blocked reader reasons' => [static fn (): mixed => $blocked()['blocked_reader_reasons'], ['reader_still_pins_old_wal', 'reader_not_reopened_or_invalidated', 'reader_source_token_mismatch', 'reader_generation_mismatch', 'invalidated_reader_not_stale']],
    'blocked guards' => [static fn (): mixed => $blocked()['blocked_guard_names'], ['sidecars_match_reset_publication', 'readers_reopened_or_invalidated']],
    'missing status' => [static fn (): mixed => $missing()['status'], 'wal-hot-journal-savepoint-checkpoint-current-source-blocked-next224'],
    'missing sidecar type' => [static fn (): mixed => $missing()['missing_sidecar_types'], ['shm']],
    'missing blocked guard' => [static fn (): mixed => $missing()['blocked_guard_names'], ['required_sidecar_receipts_present']],
];

foreach ($cases as $name => [$callback, $expected]) {
    $tests['wal hot journal savepoint checkpoint current source next224 ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        $t->same($expected, $callback());
    };
}

$throws = [
    'bad base rejected' => static fn () => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next224PublishReset(['status' => 'bad'], $truncateSidecars, $readers, 'next224:checkpoint-reset'),
    'reset denied rejected' => static fn () => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next224PublishReset(array_merge($reset, ['can_reset_wal' => false]), $truncateSidecars, $readers, 'next224:checkpoint-reset'),
    'empty sidecars rejected' => static fn () => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next224PublishReset($reset, [], $readers, 'next224:checkpoint-reset'),
    'empty readers rejected' => static fn () => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next224PublishReset($reset, $truncateSidecars, [], 'next224:checkpoint-reset'),
    'bad source token rejected' => static fn () => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next224PublishReset($reset, $truncateSidecars, $readers, 'bad token'),
    'bad mode rejected' => static fn () => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next224PublishReset(array_merge($reset, ['mode' => 'passive']), $truncateSidecars, $readers, 'next224:checkpoint-reset'),
    'bad database digest rejected' => static fn () => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next224PublishReset(array_merge($reset, ['database_digest' => 'short']), $truncateSidecars, $readers, 'next224:checkpoint-reset'),
    'bad wal digest rejected' => static fn () => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next224PublishReset(array_merge($reset, ['wal_digest' => 'short']), $truncateSidecars, $readers, 'next224:checkpoint-reset'),
    'bad generation rejected' => static fn () => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next224PublishReset(array_merge($reset, ['next_writer_generation' => 0]), $truncateSidecars, $readers, 'next224:checkpoint-reset'),
    'bad sidecar name rejected' => static fn () => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next224PublishReset($reset, [array_merge($truncateSidecars[0], ['name' => ''])], $readers, 'next224:checkpoint-reset'),
    'bad sidecar type rejected' => static fn () => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next224PublishReset($reset, [array_merge($truncateSidecars[0], ['type' => 'temp'])], $readers, 'next224:checkpoint-reset'),
    'bad sidecar generation rejected' => static fn () => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next224PublishReset($reset, [array_merge($truncateSidecars[0], ['generation' => -1])], $readers, 'next224:checkpoint-reset'),
    'bad sidecar size rejected' => static fn () => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next224PublishReset($reset, [array_merge($truncateSidecars[0], ['size' => -1])], $readers, 'next224:checkpoint-reset'),
    'bad sidecar digest rejected' => static fn () => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next224PublishReset($reset, [array_merge($truncateSidecars[0], ['digest' => 'short'])], $readers, 'next224:checkpoint-reset'),
    'bad reader name rejected' => static fn () => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next224PublishReset($reset, $truncateSidecars, [array_merge($readers[0], ['name' => ''])], 'next224:checkpoint-reset'),
    'bad reader generation rejected' => static fn () => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next224PublishReset($reset, $truncateSidecars, [array_merge($readers[0], ['generation' => -1])], 'next224:checkpoint-reset'),
];

foreach ($throws as $name => $callback) {
    $tests['wal hot journal savepoint checkpoint current source next224 ' . $name] = static function (TestRunner $t) use ($callback): void {
        $t->throws(Throwable::class, $callback);
    };
}

return $tests;

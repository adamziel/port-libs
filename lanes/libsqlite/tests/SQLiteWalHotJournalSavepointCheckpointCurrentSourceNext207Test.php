<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan;

require_once __DIR__ . '/../src/SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan.php';

$tests = [];

$pageSize = 512;
$page = static fn (string $label): string => str_pad($label, $pageSize, '.', STR_PAD_RIGHT);
$digest = static fn (string $bytes): string => hash('sha256', $bytes);
$database = $page('next207 schema checkpoint')
    . $page('next207 wp_options checkpoint')
    . $page('next207 plugin checkpoint')
    . $page('next207 cron checkpoint');
$oldDatabase = $page('next207 schema old')
    . $page('next207 wp_options old')
    . $page('next207 plugin old')
    . $page('next207 cron old');
$databaseDigest = $digest($database);
$oldDatabaseDigest = $digest($oldDatabase);
$walDigest = $digest('next207 published wal generation');
$oldWalDigest = $digest('next207 old wal generation');
$lockToken = 'exclusive-wal-lock-next207';
$oldLockToken = 'stale-wal-lock-next207';
$hotJournalDigest = $digest('next207 stale hot journal');
$pageDigests = [
    1 => $digest($page('next207 schema checkpoint')),
    2 => $digest($page('next207 wp_options checkpoint')),
    3 => $digest($page('next207 plugin checkpoint')),
    4 => $digest($page('next207 cron checkpoint')),
];
$oldPageDigests = [
    1 => $digest($page('next207 schema old')),
    2 => $digest($page('next207 wp_options old')),
    3 => $digest($page('next207 plugin old')),
    4 => $digest($page('next207 cron old')),
];
$base = [
    'status' => 'wal-hot-journal-savepoint-checkpoint-current-source-next206',
    'database_path' => '/srv/www/wp-content/database/wp-next207.sqlite',
    'journal_path' => '/srv/www/wp-content/database/wp-next207.sqlite-journal',
    'wal_path' => '/srv/www/wp-content/database/wp-next207.sqlite-wal',
    'page_size' => $pageSize,
    'checkpointed_database_digest' => $databaseDigest,
    'expected_wal_digest' => $walDigest,
    'expected_page_digests' => $pageDigests,
    'admitted_consumer_names' => ['wp-options-select-current', 'cron-reader-current'],
    'blocked_guard_names' => [],
    'operation_names' => ['verify_reopened_statement_generation_current_source_next206'],
    'dependencies' => ['sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next206'],
];
$blockedBase = $base;
$blockedBase['blocked_guard_names'] = ['next203_page_cache_lease_fence'];
$missingPagesBase = $base;
unset($missingPagesBase['expected_page_digests']);
$missingConsumersBase = $base;
unset($missingConsumersBase['admitted_consumer_names']);

$cursors = [
    [
        'name' => 'wp-options-update-current',
        'consumer_name' => 'wp-options-select-current',
        'cursor_generation' => 207,
        'commit_generation' => 208,
        'write_lock_token' => $lockToken,
        'root_pages' => [1, 2],
        'observed_database_digest' => $databaseDigest,
        'observed_wal_digest' => $walDigest,
        'observed_page_digests' => [
            1 => $pageDigests[1],
            2 => $pageDigests[2],
        ],
    ],
    [
        'name' => 'cron-update-current',
        'consumer_name' => 'cron-reader-current',
        'cursor_generation' => 208,
        'commit_generation' => 209,
        'write_lock_token' => $lockToken,
        'root_pages' => [4],
        'observed_database_digest' => $databaseDigest,
        'observed_wal_digest' => $walDigest,
        'observed_page_digests' => [
            4 => $pageDigests[4],
        ],
    ],
    [
        'name' => 'blocked-consumer',
        'consumer_name' => 'old-statement-generation',
        'cursor_generation' => 207,
        'commit_generation' => 208,
        'write_lock_token' => $lockToken,
        'root_pages' => [2],
        'observed_database_digest' => $databaseDigest,
        'observed_wal_digest' => $walDigest,
        'observed_page_digests' => [2 => $pageDigests[2]],
    ],
    [
        'name' => 'old-commit-generation',
        'consumer_name' => 'wp-options-select-current',
        'cursor_generation' => 206,
        'commit_generation' => 206,
        'write_lock_token' => $lockToken,
        'root_pages' => [2],
        'observed_database_digest' => $databaseDigest,
        'observed_wal_digest' => $walDigest,
        'observed_page_digests' => [2 => $pageDigests[2]],
    ],
    [
        'name' => 'stale-lock-token',
        'consumer_name' => 'wp-options-select-current',
        'cursor_generation' => 207,
        'commit_generation' => 208,
        'write_lock_token' => $oldLockToken,
        'root_pages' => [2],
        'observed_database_digest' => $databaseDigest,
        'observed_wal_digest' => $walDigest,
        'observed_page_digests' => [2 => $pageDigests[2]],
    ],
    [
        'name' => 'old-database-digest',
        'consumer_name' => 'wp-options-select-current',
        'cursor_generation' => 207,
        'commit_generation' => 208,
        'write_lock_token' => $lockToken,
        'root_pages' => [2],
        'observed_database_digest' => $oldDatabaseDigest,
        'observed_wal_digest' => $walDigest,
        'observed_page_digests' => [2 => $pageDigests[2]],
    ],
    [
        'name' => 'old-wal-digest',
        'consumer_name' => 'wp-options-select-current',
        'cursor_generation' => 207,
        'commit_generation' => 208,
        'write_lock_token' => $lockToken,
        'root_pages' => [2],
        'observed_database_digest' => $databaseDigest,
        'observed_wal_digest' => $oldWalDigest,
        'observed_page_digests' => [2 => $pageDigests[2]],
    ],
    [
        'name' => 'stale-page-digest',
        'consumer_name' => 'wp-options-select-current',
        'cursor_generation' => 207,
        'commit_generation' => 208,
        'write_lock_token' => $lockToken,
        'root_pages' => [2],
        'observed_database_digest' => $databaseDigest,
        'observed_wal_digest' => $walDigest,
        'observed_page_digests' => [2 => $oldPageDigests[2]],
    ],
    [
        'name' => 'read-only-cursor',
        'consumer_name' => 'wp-options-select-current',
        'cursor_generation' => 207,
        'commit_generation' => 208,
        'write_lock_token' => $lockToken,
        'root_pages' => [1],
        'observed_database_digest' => $databaseDigest,
        'observed_wal_digest' => $walDigest,
        'observed_page_digests' => [1 => $pageDigests[1]],
        'read_only' => true,
    ],
    [
        'name' => 'savepoint-still-open',
        'consumer_name' => 'wp-options-select-current',
        'cursor_generation' => 207,
        'commit_generation' => 208,
        'write_lock_token' => $lockToken,
        'root_pages' => [3],
        'observed_database_digest' => $databaseDigest,
        'observed_wal_digest' => $walDigest,
        'observed_page_digests' => [3 => $pageDigests[3]],
        'pending_savepoint_depth' => 1,
    ],
    [
        'name' => 'hot-journal-retained',
        'consumer_name' => 'wp-options-select-current',
        'cursor_generation' => 207,
        'commit_generation' => 208,
        'write_lock_token' => $lockToken,
        'root_pages' => [3],
        'observed_database_digest' => $databaseDigest,
        'observed_wal_digest' => $walDigest,
        'observed_page_digests' => [3 => $pageDigests[3]],
        'hot_journal_digest' => $hotJournalDigest,
    ],
    [
        'name' => 'dirty-reader-cache',
        'consumer_name' => 'wp-options-select-current',
        'cursor_generation' => 207,
        'commit_generation' => 208,
        'write_lock_token' => $lockToken,
        'root_pages' => [1],
        'observed_database_digest' => $databaseDigest,
        'observed_wal_digest' => $walDigest,
        'observed_page_digests' => [1 => $pageDigests[1]],
        'dirty_reader_cache' => true,
    ],
    [
        'name' => 'page-outside-generation',
        'consumer_name' => 'wp-options-select-current',
        'cursor_generation' => 207,
        'commit_generation' => 208,
        'write_lock_token' => $lockToken,
        'root_pages' => [5],
        'observed_database_digest' => $databaseDigest,
        'observed_wal_digest' => $walDigest,
        'observed_page_digests' => [5 => $digest($page('next207 missing page'))],
    ],
];

$plan = static fn (): array => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next207Plan($base, $cursors, $lockToken, 208);
$blocked = static fn (): array => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next207Plan($blockedBase, [$cursors[0], $cursors[2]], $lockToken, 208);
$allCurrent = static fn (): array => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next207Plan($base, [$cursors[0], $cursors[1]], $lockToken, 208);

$cases = [
    'status' => [static fn (): mixed => $plan()['status'], 'wal-hot-journal-savepoint-checkpoint-current-source-next207'],
    'reason' => [static fn (): mixed => $plan()['reason'], 'write_cursors_match_checkpoint_generation_and_exclusive_lock'],
    'base status' => [static fn (): mixed => $plan()['base_status'], 'wal-hot-journal-savepoint-checkpoint-current-source-next206'],
    'database path' => [static fn (): mixed => $plan()['database_path'], '/srv/www/wp-content/database/wp-next207.sqlite'],
    'journal path' => [static fn (): mixed => $plan()['journal_path'], '/srv/www/wp-content/database/wp-next207.sqlite-journal'],
    'wal path' => [static fn (): mixed => $plan()['wal_path'], '/srv/www/wp-content/database/wp-next207.sqlite-wal'],
    'page size' => [static fn (): mixed => $plan()['page_size'], 512],
    'minimum commit generation' => [static fn (): mixed => $plan()['minimum_commit_generation'], 208],
    'lock token' => [static fn (): mixed => $plan()['expected_write_lock_token'], $lockToken],
    'database digest' => [static fn (): mixed => $plan()['checkpointed_database_digest'], $databaseDigest],
    'wal digest' => [static fn (): mixed => $plan()['expected_wal_digest'], $walDigest],
    'page two digest' => [static fn (): mixed => $plan()['expected_page_digests'][2], $pageDigests[2]],
    'admitted cursors' => [static fn (): mixed => $plan()['admitted_cursor_names'], ['wp-options-update-current', 'cron-update-current']],
    'blocked cursors' => [static fn (): mixed => $plan()['blocked_cursor_names'], ['blocked-consumer', 'old-commit-generation', 'stale-lock-token', 'old-database-digest', 'old-wal-digest', 'stale-page-digest', 'read-only-cursor', 'savepoint-still-open', 'hot-journal-retained', 'dirty-reader-cache', 'page-outside-generation']],
    'current reason' => [static fn (): mixed => $plan()['cursor_rows'][0]['cursor_reason'], 'write_cursor_matches_checkpoint_generation_and_lock'],
    'current page matched' => [static fn (): mixed => $plan()['cursor_rows'][0]['page_rows'][1]['matched'], true],
    'cron transition' => [static fn (): mixed => $plan()['cursor_rows'][1]['cursor_transition'], 'cron-update-current>admit-write-cursor'],
    'blocked consumer reason' => [static fn (): mixed => $plan()['cursor_rows'][2]['cursor_reason'], 'write_cursor_consumer_not_admitted_by_next206'],
    'old commit reason' => [static fn (): mixed => $plan()['cursor_rows'][3]['cursor_reason'], 'write_cursor_commit_generation_predates_checkpoint'],
    'old commit blocked reasons' => [static fn (): mixed => $plan()['cursor_rows'][3]['blocked_reasons'], ['write_cursor_commit_generation_predates_checkpoint']],
    'stale lock reason' => [static fn (): mixed => $plan()['cursor_rows'][4]['cursor_reason'], 'write_cursor_lock_token_mismatch'],
    'stale lock expected token' => [static fn (): mixed => $plan()['cursor_rows'][4]['expected_write_lock_token'], $lockToken],
    'old database reason' => [static fn (): mixed => $plan()['cursor_rows'][5]['cursor_reason'], 'write_cursor_database_digest_mismatch'],
    'old database expected digest' => [static fn (): mixed => $plan()['cursor_rows'][5]['expected_database_digest'], $databaseDigest],
    'old wal reason' => [static fn (): mixed => $plan()['cursor_rows'][6]['cursor_reason'], 'write_cursor_wal_digest_mismatch'],
    'old wal expected digest' => [static fn (): mixed => $plan()['cursor_rows'][6]['expected_wal_digest'], $walDigest],
    'stale page reason' => [static fn (): mixed => $plan()['cursor_rows'][7]['cursor_reason'], 'write_cursor_page_digest_mismatch'],
    'stale page list' => [static fn (): mixed => $plan()['cursor_rows'][7]['stale_pages'], [2]],
    'stale page row reason' => [static fn (): mixed => $plan()['cursor_rows'][7]['page_rows'][0]['reason'], 'write_cursor_checkpoint_page_stale'],
    'read only reason' => [static fn (): mixed => $plan()['cursor_rows'][8]['cursor_reason'], 'write_cursor_read_only_after_checkpoint'],
    'savepoint reason' => [static fn (): mixed => $plan()['cursor_rows'][9]['cursor_reason'], 'write_cursor_savepoint_scope_not_closed'],
    'hot journal reason' => [static fn (): mixed => $plan()['cursor_rows'][10]['cursor_reason'], 'write_cursor_retains_hot_journal_digest'],
    'hot journal retained flag' => [static fn (): mixed => $plan()['cursor_rows'][10]['hot_journal_retained'], true],
    'dirty reason' => [static fn (): mixed => $plan()['cursor_rows'][11]['cursor_reason'], 'write_cursor_dirty_reader_cache_after_checkpoint'],
    'missing page reason' => [static fn (): mixed => $plan()['cursor_rows'][12]['cursor_reason'], 'write_cursor_page_digest_mismatch'],
    'missing page list' => [static fn (): mixed => $plan()['cursor_rows'][12]['missing_pages'], [5]],
    'missing page row reason' => [static fn (): mixed => $plan()['cursor_rows'][12]['page_rows'][0]['reason'], 'write_cursor_page_outside_checkpoint_generation'],
    'guard names' => [static fn (): mixed => $plan()['guard_names'], ['next206_statement_generation_fence', 'write_cursor_commit_mix', 'exclusive_write_lock_token']],
    'guard matches' => [static fn (): mixed => $plan()['guard_matches'], [true, true, true]],
    'blocked guards' => [static fn (): mixed => $plan()['blocked_guard_names'], []],
    'operation inherited' => [static fn (): mixed => $plan()['operation_names'][0], 'verify_reopened_statement_generation_current_source_next206'],
    'operation verify present' => [static fn (): mixed => in_array('verify_write_cursor_generation_current_source_next207', $plan()['operation_names'], true), true],
    'admit operation count' => [static fn (): mixed => count(array_filter($plan()['operation_names'], static fn (string $name): bool => $name === 'admit_write_cursor_current_source_next207')), 2],
    'block operation count' => [static fn (): mixed => count(array_filter($plan()['operation_names'], static fn (string $name): bool => $name === 'block_stale_write_cursor_current_source_next207')), 11],
    'cursor digest length' => [static fn (): mixed => strlen($plan()['cursor_digest']), 64],
    'dependency next207' => [static fn (): mixed => in_array('sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next207', $plan()['dependencies'], true), true],
    'dependency cursor fence' => [static fn (): mixed => in_array('sqlite-wal-write-cursor-generation-fence', $plan()['dependencies'], true), true],
    'dependency wordpress reprepare' => [static fn (): mixed => in_array('wordpress-import-write-cursor-reprepare', $plan()['dependencies'], true), true],
    'dependency closure' => [static fn (): mixed => str_contains($plan()['dependency_closure'], 'no new support component needed'), true],
    'non overlap' => [static fn (): mixed => str_contains($plan()['non_overlap'], 'does not repeat WAL byte truncation'), true],
    'blocked status' => [static fn (): mixed => $blocked()['status'], 'wal-hot-journal-savepoint-checkpoint-current-source-blocked-next207'],
    'blocked reason' => [static fn (): mixed => $blocked()['reason'], 'write_cursors_wait_for_checkpoint_generation_or_lock_reprepare'],
    'blocked guard from base' => [static fn (): mixed => $blocked()['blocked_guard_names'], ['next206_statement_generation_fence']],
    'all current status blocked by missing mix' => [static fn (): mixed => $allCurrent()['status'], 'wal-hot-journal-savepoint-checkpoint-current-source-blocked-next207'],
    'all current guard' => [static fn (): mixed => $allCurrent()['blocked_guard_names'], ['write_cursor_commit_mix']],
];

foreach ($cases as $name => [$callback, $expected]) {
    $tests['wal hot journal savepoint checkpoint current source next207 ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        $t->same($expected, $callback());
    };
}

$throws = [
    'bad base rejected' => static fn () => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next207Plan(['status' => 'bad'], $cursors, $lockToken, 208),
    'empty cursors rejected' => static fn () => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next207Plan($base, [], $lockToken, 208),
    'empty lock token rejected' => static fn () => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next207Plan($base, $cursors, '', 208),
    'negative generation rejected' => static fn () => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next207Plan($base, $cursors, $lockToken, -1),
    'missing database digest rejected' => static fn () => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next207Plan(array_merge($base, ['checkpointed_database_digest' => 'short']), $cursors, $lockToken, 208),
    'missing wal digest rejected' => static fn () => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next207Plan(array_merge($base, ['expected_wal_digest' => 'short']), $cursors, $lockToken, 208),
    'missing page digests rejected' => static fn () => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next207Plan($missingPagesBase, $cursors, $lockToken, 208),
    'missing admitted consumers rejected' => static fn () => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next207Plan($missingConsumersBase, $cursors, $lockToken, 208),
    'missing name rejected' => static fn () => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next207Plan($base, [array_merge($cursors[0], ['name' => ''])], $lockToken, 208),
    'missing consumer rejected' => static fn () => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next207Plan($base, [array_merge($cursors[0], ['consumer_name' => ''])], $lockToken, 208),
    'bad generation row rejected' => static fn () => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next207Plan($base, [array_merge($cursors[0], ['commit_generation' => -1])], $lockToken, 208),
    'empty row lock token rejected' => static fn () => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next207Plan($base, [array_merge($cursors[0], ['write_lock_token' => ''])], $lockToken, 208),
    'bad observed digest rejected' => static fn () => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next207Plan($base, [array_merge($cursors[0], ['observed_wal_digest' => 'short'])], $lockToken, 208),
    'missing root pages rejected' => static fn () => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next207Plan($base, [array_merge($cursors[0], ['root_pages' => []])], $lockToken, 208),
    'bad root page rejected' => static fn () => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next207Plan($base, [array_merge($cursors[0], ['root_pages' => [0]])], $lockToken, 208),
    'bad page digest rejected' => static fn () => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next207Plan($base, [array_merge($cursors[0], ['observed_page_digests' => [1 => 'short']])], $lockToken, 208),
    'bad hot journal digest rejected' => static fn () => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next207Plan($base, [array_merge($cursors[0], ['hot_journal_digest' => 'short'])], $lockToken, 208),
];

foreach ($throws as $name => $callback) {
    $tests['wal hot journal savepoint checkpoint current source next207 ' . $name] = static function (TestRunner $t) use ($callback): void {
        $t->throws(Throwable::class, $callback);
    };
}

return $tests;

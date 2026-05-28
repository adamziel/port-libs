<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteWalHotJournalSavepointCheckpointCurrentSourceNext251Plan;

require_once __DIR__ . '/../src/SQLiteWalHotJournalSavepointCheckpointCurrentSourceNext251Plan.php';

$tests = [];

$hash = static fn (string $value): string => hash('sha256', $value);
$handoffPlan = [
    'status' => 'wal-hot-journal-savepoint-checkpoint-current-source-next246',
    'durable_handoff_admitted' => true,
    'database_path' => '/srv/www/wp-content/database/wp-next251.sqlite',
    'wal_path' => '/srv/www/wp-content/database/wp-next251.sqlite-wal',
    'journal_path' => '/srv/www/wp-content/database/wp-next251.sqlite-journal',
    'source_token' => 'wp-next251-current-source',
    'commit_generation' => 251,
    'schema_cookie' => 1251,
    'database_digest' => $hash('next251 checkpoint database image'),
    'page_cache_digest' => $hash('next251 checkpoint clean page cache'),
    'checkpoint_frame' => 36,
    'dirty_pages' => [1, 2, 7, 11],
    'commit_frames' => [32, 34, 36],
    'accepted_reader_names' => ['schema-reader', 'options-reader', 'terms-reader'],
    'operation_names' => ['admit_durable_current_source_handoff_next246'],
    'dependencies' => ['sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next246'],
];

$receipt = static function (string $name, string $operation, array $override = []) use ($handoffPlan): array {
    return array_replace([
        'name' => $name,
        'operation' => $operation,
        'path' => $handoffPlan['wal_path'],
        'source_token' => $handoffPlan['source_token'],
        'commit_generation' => $handoffPlan['commit_generation'],
        'checkpoint_frame' => $handoffPlan['checkpoint_frame'],
        'exclusive_lock_held' => true,
        'released_reader_names' => [],
        'next_wal_salt' => null,
        'restart_frame' => 0,
        'truncate_bytes' => 0,
        'retired_commit_frames' => [],
        'synced' => false,
        'io_error' => null,
    ], $override);
};

$receipts = [
    $receipt('release-schema-reader', 'release_readmark', ['released_reader_names' => ['schema-reader']]),
    $receipt('release-options-reader', 'release_readmark', ['released_reader_names' => ['options-reader', 'terms-reader']]),
    $receipt('rewrite-empty-wal-header', 'rewrite_wal_header', ['next_wal_salt' => ['next251-salt-a', 'next251-salt-b']]),
    $receipt('truncate-retired-wal-frames', 'truncate_wal', ['retired_commit_frames' => [32, 34, 36]]),
    $receipt('sync-empty-wal-sidecar', 'sync_wal', ['synced' => true]),
];

$plan = static fn (?array $inputPlan = null, ?array $inputReceipts = null): array => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNext251Plan::admitWalSidecarReset($inputPlan ?? $handoffPlan, $inputReceipts ?? $receipts);
$blocked = static fn (int $index, array $replace): array => $plan(null, array_replace($receipts, [$index => array_replace($receipts[$index], $replace)]));
$without = static fn (int $index): array => $plan(null, array_values(array_filter($receipts, static fn (array $_, int $key): bool => $key !== $index, ARRAY_FILTER_USE_BOTH)));

$cases = [
    'status' => [static fn (): mixed => $plan()['status'], 'wal-hot-journal-savepoint-checkpoint-current-source-next251'],
    'reason' => [static fn (): mixed => $plan()['reason'], 'wal_sidecar_reset_admitted_after_durable_checkpoint_handoff'],
    'base status' => [static fn (): mixed => $plan()['base_status'], 'wal-hot-journal-savepoint-checkpoint-current-source-next246'],
    'database path' => [static fn (): mixed => $plan()['database_path'], '/srv/www/wp-content/database/wp-next251.sqlite'],
    'wal path' => [static fn (): mixed => $plan()['wal_path'], '/srv/www/wp-content/database/wp-next251.sqlite-wal'],
    'source token' => [static fn (): mixed => $plan()['source_token'], 'wp-next251-current-source'],
    'commit generation' => [static fn (): mixed => $plan()['commit_generation'], 251],
    'checkpoint frame' => [static fn (): mixed => $plan()['checkpoint_frame'], 36],
    'commit frames' => [static fn (): mixed => $plan()['commit_frames'], [32, 34, 36]],
    'database digest' => [static fn (): mixed => $plan()['database_digest'], $hash('next251 checkpoint database image')],
    'page cache digest' => [static fn (): mixed => $plan()['page_cache_digest'], $hash('next251 checkpoint clean page cache')],
    'accepted reader names' => [static fn (): mixed => $plan()['accepted_reader_names'], ['schema-reader', 'options-reader', 'terms-reader']],
    'released reader names' => [static fn (): mixed => $plan()['released_reader_names'], ['options-reader', 'schema-reader', 'terms-reader']],
    'missing reader releases empty' => [static fn (): mixed => $plan()['missing_reader_releases'], []],
    'reset receipt names' => [static fn (): mixed => $plan()['reset_receipt_names'], ['release-schema-reader', 'release-options-reader', 'rewrite-empty-wal-header', 'truncate-retired-wal-frames', 'sync-empty-wal-sidecar']],
    'accepted reset receipts' => [static fn (): mixed => $plan()['accepted_reset_receipt_names'], ['release-schema-reader', 'release-options-reader', 'rewrite-empty-wal-header', 'truncate-retired-wal-frames', 'sync-empty-wal-sidecar']],
    'blocked reset receipts empty' => [static fn (): mixed => $plan()['blocked_reset_receipt_names'], []],
    'duplicate receipts empty' => [static fn (): mixed => $plan()['duplicate_reset_receipt_names'], []],
    'operation order' => [static fn (): mixed => $plan()['operation_order'], ['release_readmark', 'release_readmark', 'rewrite_wal_header', 'truncate_wal', 'sync_wal']],
    'operation order safe' => [static fn (): mixed => $plan()['operation_order_safe'], true],
    'next wal salt' => [static fn (): mixed => $plan()['next_wal_salt'], ['next251-salt-a', 'next251-salt-b']],
    'restart frame' => [static fn (): mixed => $plan()['restart_frame'], 0],
    'truncate bytes' => [static fn (): mixed => $plan()['truncate_bytes'], 0],
    'wal sync seen' => [static fn (): mixed => $plan()['wal_sync_seen'], true],
    'blocked reasons empty' => [static fn (): mixed => $plan()['blocked_reset_reasons'], []],
    'reset admitted' => [static fn (): mixed => $plan()['wal_reset_admitted'], true],
    'wal action' => [static fn (): mixed => $plan()['wal_action'], 'publish_empty_restarted_wal_after_reader_release'],
    'reader action' => [static fn (): mixed => $plan()['reader_action'], 'allow_new_readers_on_restarted_wal_generation_251'],
    'journal action' => [static fn (): mixed => $plan()['journal_action'], 'hot_journal_retirement_remains_durable'],
    'guard names' => [static fn (): mixed => $plan()['guard_names'], ['next246_durable_handoff_admitted', 'wal_reset_receipt_names_unique', 'all_checkpoint_readers_released', 'wal_header_rewritten_with_new_salt', 'wal_sidecar_truncated_to_empty', 'wal_reset_synced_after_truncate', 'all_wal_reset_receipts_accepted']],
    'guard matches' => [static fn (): mixed => $plan()['guard_matches'], [true, true, true, true, true, true, true]],
    'blocked guards empty' => [static fn (): mixed => $plan()['blocked_guard_names'], []],
    'row reason' => [static fn (): mixed => $plan()['reset_rows'][2]['receipt_reason'], 'wal_reset_receipt_matches_durable_checkpoint_handoff'],
    'row salt' => [static fn (): mixed => $plan()['reset_rows'][2]['next_wal_salt'], ['next251-salt-a', 'next251-salt-b']],
    'row retired frames' => [static fn (): mixed => $plan()['reset_rows'][3]['retired_commit_frames'], [32, 34, 36]],
    'digest length' => [static fn (): mixed => strlen($plan()['reset_digest']), 64],
    'operation inherited' => [static fn (): mixed => in_array('admit_durable_current_source_handoff_next246', $plan()['operation_names'], true), true],
    'operation added' => [static fn (): mixed => in_array('admit_wal_sidecar_reset_next251', $plan()['operation_names'], true), true],
    'dependency inherited' => [static fn (): mixed => in_array('sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next246', $plan()['dependencies'], true), true],
    'dependency next251' => [static fn (): mixed => in_array('sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next251', $plan()['dependencies'], true), true],
    'dependency wordpress' => [static fn (): mixed => in_array('wordpress-import-hot-journal-checkpoint-wal-reset', $plan()['dependencies'], true), true],
    'dependency closure' => [static fn (): mixed => str_contains($plan()['dependency_closure'], 'no new support component needed'), true],
    'non overlap' => [static fn (): mixed => str_contains($plan()['non_overlap'], 'does not repeat durable page writes'), true],
    'path mismatch blocked' => [static fn (): mixed => $blocked(2, ['path' => '/tmp/wrong-wal'])['blocked_reset_reasons'], ['wal_reset_path_mismatch', 'wal_reset_header_rewrite_missing', 'wal_reset_operation_order_unsafe']],
    'token mismatch blocked' => [static fn (): mixed => $blocked(2, ['source_token' => 'old-source'])['blocked_reset_reasons'], ['wal_reset_source_token_mismatch', 'wal_reset_header_rewrite_missing', 'wal_reset_operation_order_unsafe']],
    'generation mismatch blocked' => [static fn (): mixed => $blocked(2, ['commit_generation' => 250])['blocked_reset_reasons'], ['wal_reset_commit_generation_mismatch', 'wal_reset_header_rewrite_missing', 'wal_reset_operation_order_unsafe']],
    'checkpoint mismatch blocked' => [static fn (): mixed => $blocked(2, ['checkpoint_frame' => 35])['blocked_reset_reasons'], ['wal_reset_checkpoint_frame_mismatch', 'wal_reset_header_rewrite_missing', 'wal_reset_operation_order_unsafe']],
    'exclusive lock missing blocked' => [static fn (): mixed => $blocked(2, ['exclusive_lock_held' => false])['blocked_reset_reasons'], ['wal_reset_exclusive_lock_missing', 'wal_reset_header_rewrite_missing', 'wal_reset_operation_order_unsafe']],
    'io error blocked' => [static fn (): mixed => $blocked(2, ['io_error' => 'SQLITE_IOERR_TRUNCATE'])['blocked_reset_reasons'], ['wal_reset_io_error', 'wal_reset_header_rewrite_missing', 'wal_reset_operation_order_unsafe']],
    'unknown reader blocked' => [static fn (): mixed => $blocked(1, ['released_reader_names' => ['terms-reader', 'ghost-reader']])['blocked_reset_reasons'], ['wal_reset_unknown_reader_release', 'wal_reset_reader_release_missing']],
    'unknown frame blocked' => [static fn (): mixed => $blocked(3, ['retired_commit_frames' => [32, 99]])['blocked_reset_reasons'], ['wal_reset_unknown_retired_frame', 'wal_reset_truncate_missing', 'wal_reset_operation_order_unsafe']],
    'reader release empty blocked' => [static fn (): mixed => $blocked(0, ['released_reader_names' => []])['blocked_reset_reasons'], ['wal_reset_reader_release_empty', 'wal_reset_reader_release_missing']],
    'bad header blocked' => [static fn (): mixed => $blocked(2, ['restart_frame' => 1])['blocked_reset_reasons'], ['wal_reset_header_rewrite_invalid', 'wal_reset_header_rewrite_missing', 'wal_reset_operation_order_unsafe']],
    'bad truncate blocked' => [static fn (): mixed => $blocked(3, ['truncate_bytes' => 128])['blocked_reset_reasons'], ['wal_reset_truncate_invalid', 'wal_reset_truncate_missing', 'wal_reset_operation_order_unsafe']],
    'missing sync blocked' => [static fn (): mixed => $blocked(4, ['synced' => false])['blocked_reset_reasons'], ['wal_reset_sync_missing', 'wal_reset_operation_order_unsafe']],
    'without release blocked' => [static fn (): mixed => $without(0)['missing_reader_releases'], ['schema-reader']],
    'without header blocked' => [static fn (): mixed => $without(2)['blocked_guard_names'], ['wal_header_rewritten_with_new_salt', 'wal_reset_synced_after_truncate']],
    'without truncate blocked' => [static fn (): mixed => $without(3)['blocked_guard_names'], ['wal_sidecar_truncated_to_empty', 'wal_reset_synced_after_truncate']],
    'without sync blocked' => [static fn (): mixed => $without(4)['blocked_guard_names'], ['wal_reset_synced_after_truncate']],
    'unsafe order blocked' => [static fn (): mixed => $plan(null, array_merge([$receipts[2]], [$receipts[0], $receipts[1], $receipts[3], $receipts[4]]))['blocked_guard_names'], ['wal_reset_synced_after_truncate']],
    'duplicate names blocked' => [static fn (): mixed => $plan(null, array_replace($receipts, [1 => array_replace($receipts[1], ['name' => 'release-schema-reader'])]))['duplicate_reset_receipt_names'], ['release-schema-reader']],
    'blocked status' => [static fn (): mixed => $blocked(2, ['path' => '/tmp/wrong-wal'])['status'], 'wal-hot-journal-savepoint-checkpoint-current-source-blocked-next251'],
    'blocked reason' => [static fn (): mixed => $blocked(2, ['path' => '/tmp/wrong-wal'])['reason'], 'wal_sidecar_reset_held_after_durable_checkpoint_handoff'],
    'blocked wal action' => [static fn (): mixed => $blocked(2, ['path' => '/tmp/wrong-wal'])['wal_action'], 'preserve_wal_sidecar_until_reset_fences_match'],
    'blocked reader action' => [static fn (): mixed => $blocked(2, ['path' => '/tmp/wrong-wal'])['reader_action'], 'keep_readers_on_checkpoint_generation_until_release'],
    'blocked journal action' => [static fn (): mixed => $blocked(2, ['path' => '/tmp/wrong-wal'])['journal_action'], 'retain_hot_journal_recovery_metadata'],
];

foreach ($cases as $name => [$callback, $expected]) {
    $tests['wal hot journal savepoint checkpoint current source next251 ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        $t->same($expected, $callback());
    };
}

$throws = [
    'bad base rejected' => static fn () => $plan(array_replace($handoffPlan, ['status' => 'bad'])),
    'not admitted rejected' => static fn () => $plan(array_replace($handoffPlan, ['durable_handoff_admitted' => false])),
    'empty receipts rejected' => static fn () => $plan(null, []),
    'bad database path rejected' => static fn () => $plan(array_replace($handoffPlan, ['database_path' => ''])),
    'bad wal path rejected' => static fn () => $plan(array_replace($handoffPlan, ['wal_path' => ''])),
    'bad source token rejected' => static fn () => $plan(array_replace($handoffPlan, ['source_token' => 'bad token'])),
    'bad commit generation rejected' => static fn () => $plan(array_replace($handoffPlan, ['commit_generation' => 0])),
    'bad checkpoint frame rejected' => static fn () => $plan(array_replace($handoffPlan, ['checkpoint_frame' => -1])),
    'bad commit frames rejected' => static fn () => $plan(array_replace($handoffPlan, ['commit_frames' => [0]])),
    'bad reader names rejected' => static fn () => $plan(array_replace($handoffPlan, ['accepted_reader_names' => []])),
    'bad database digest rejected' => static fn () => $plan(array_replace($handoffPlan, ['database_digest' => 'short'])),
    'bad page cache digest rejected' => static fn () => $plan(array_replace($handoffPlan, ['page_cache_digest' => 'short'])),
    'bad receipt name rejected' => static fn () => $plan(null, [array_replace($receipts[0], ['name' => 'bad name'])]),
    'bad operation rejected' => static fn () => $plan(null, [array_replace($receipts[0], ['operation' => 'delete'])]),
    'bad receipt path rejected' => static fn () => $plan(null, [array_replace($receipts[0], ['path' => ''])]),
    'bad receipt token rejected' => static fn () => $plan(null, [array_replace($receipts[0], ['source_token' => 'bad token'])]),
    'bad receipt generation rejected' => static fn () => $plan(null, [array_replace($receipts[0], ['commit_generation' => 0])]),
    'bad receipt checkpoint rejected' => static fn () => $plan(null, [array_replace($receipts[0], ['checkpoint_frame' => -1])]),
    'bad reader release rejected' => static fn () => $plan(null, [array_replace($receipts[0], ['released_reader_names' => ['bad token']])]),
    'bad salt rejected' => static fn () => $plan(null, [array_replace($receipts[2], ['next_wal_salt' => ['only-one']])]),
    'bad restart frame rejected' => static fn () => $plan(null, [array_replace($receipts[2], ['restart_frame' => -1])]),
    'bad truncate bytes rejected' => static fn () => $plan(null, [array_replace($receipts[3], ['truncate_bytes' => -1])]),
    'bad retired frame rejected' => static fn () => $plan(null, [array_replace($receipts[3], ['retired_commit_frames' => [0]])]),
];

foreach ($throws as $name => $callback) {
    $tests['wal hot journal savepoint checkpoint current source next251 ' . $name] = static function (TestRunner $t) use ($callback): void {
        $t->throws(Throwable::class, $callback);
    };
}

return $tests;

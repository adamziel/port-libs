<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan;

require_once __DIR__ . '/../src/SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan.php';

$tests = [];

$digest = static fn (string $value): string => hash('sha256', $value);
$databaseDigest = $digest('wp post-checkpoint append admission checkpointed database pages');
$walDigest = $digest('wp post-checkpoint append admission post-checkpoint wal source');
$consumerDigest = $digest('wp post-checkpoint append admission current consumer source');
$oldDatabaseDigest = $digest('wp post-checkpoint append admission old database pages');
$oldWalDigest = $digest('wp post-checkpoint append admission old wal source');
$oldConsumerDigest = $digest('wp post-checkpoint append admission old consumer source');
$hotJournalDigest = $digest('wp post-checkpoint append admission stale hot journal');
$pageDigests = [
    2 => $digest('wp_options root after post-checkpoint append admission autoload update'),
    5 => $digest('plugin settings overflow after post-checkpoint append admission update'),
];

$writerPlan = [
    'status' => 'wal-hot-journal-savepoint-checkpoint-current-source-next209',
    'database_path' => '/srv/www/wp-content/database/wp-post-checkpoint-append.sqlite',
    'journal_path' => '/srv/www/wp-content/database/wp-post-checkpoint-append.sqlite-journal',
    'wal_path' => '/srv/www/wp-content/database/wp-post-checkpoint-append.sqlite-wal',
    'page_size' => 512,
    'minimum_statement_generation' => 209,
    'next_writer_generation' => 210,
    'checkpointed_database_digest' => $databaseDigest,
    'expected_wal_digest' => $walDigest,
    'consumer_digest' => $consumerDigest,
    'admitted_writer_names' => ['wp-options-autoload-update', 'wp-cron-option-update'],
    'reopen_writer_names' => ['stale-plugin-writer'],
    'blocked_guard_names' => [],
    'operation_names' => ['verify_post_checkpoint_writer_generation_current_source_next209'],
    'dependencies' => ['sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next209'],
];
$blockedWriterPlan = array_merge($writerPlan, ['blocked_guard_names' => ['next209_writer_generation_fence']]);

$batch = static fn (array $extra = []): array => array_merge([
    'name' => 'autoload-frame-batch',
    'writer_name' => 'wp-options-autoload-update',
    'writer_generation' => 210,
    'checkpoint_frame' => 18,
    'first_frame' => 19,
    'commit_frame' => 22,
    'observed_database_digest' => $databaseDigest,
    'observed_wal_digest' => $walDigest,
    'observed_consumer_digest' => $consumerDigest,
    'page_digests' => $pageDigests,
    'exclusive_lock_receipt' => true,
], $extra);

$batches = [
    $batch(),
    $batch(['name' => 'cron-frame-batch', 'writer_name' => 'wp-cron-option-update', 'page_digests' => [7 => $digest('cron root after post-checkpoint append admission update')]]),
    $batch(['name' => 'stale-plugin-batch', 'writer_name' => 'stale-plugin-writer']),
    $batch(['name' => 'unknown-writer-batch', 'writer_name' => 'unknown-plugin-writer']),
    $batch(['name' => 'old-generation-batch', 'writer_generation' => 209]),
    $batch(['name' => 'gap-frame-batch', 'first_frame' => 20]),
    $batch(['name' => 'wrong-commit-batch', 'commit_frame' => 23]),
    $batch(['name' => 'old-database-batch', 'observed_database_digest' => $oldDatabaseDigest]),
    $batch(['name' => 'old-wal-batch', 'observed_wal_digest' => $oldWalDigest]),
    $batch(['name' => 'old-consumer-batch', 'observed_consumer_digest' => $oldConsumerDigest]),
    $batch(['name' => 'hot-journal-batch', 'hot_journal_digest' => $hotJournalDigest]),
    $batch(['name' => 'open-savepoint-batch', 'savepoint_depth' => 1]),
    $batch(['name' => 'missing-lock-batch', 'exclusive_lock_receipt' => false]),
    $batch(['name' => 'dirty-cache-batch', 'dirty_before_append' => true]),
];

$plan = static fn (?array $base = null, ?array $rows = null, int $commit = 22): array => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::appendBatchCommitAdmissionPlan($base ?? $writerPlan, $rows ?? $batches, $commit);
$blocked = static fn (): array => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::appendBatchCommitAdmissionPlan($blockedWriterPlan, [$batches[0], $batches[2]], 22);
$allAccepted = static fn (): array => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::appendBatchCommitAdmissionPlan($writerPlan, [$batches[0], $batches[1]], 22);
$badCommit = static fn (): array => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::appendBatchCommitAdmissionPlan($writerPlan, [$batches[0], $batch(['name' => 'commit-before-first', 'commit_frame' => 17])], 22);

$cases = [
    'status' => [static fn (): mixed => $plan()['status'], 'wal-hot-journal-savepoint-checkpoint-post-checkpoint-append-admission'],
    'reason' => [static fn (): mixed => $plan()['reason'], 'post_checkpoint_wal_appends_follow_current_writer_generation'],
    'base status' => [static fn (): mixed => $plan()['base_status'], 'wal-hot-journal-savepoint-checkpoint-current-source-next209'],
    'database path' => [static fn (): mixed => $plan()['database_path'], '/srv/www/wp-content/database/wp-post-checkpoint-append.sqlite'],
    'journal path' => [static fn (): mixed => $plan()['journal_path'], '/srv/www/wp-content/database/wp-post-checkpoint-append.sqlite-journal'],
    'wal path' => [static fn (): mixed => $plan()['wal_path'], '/srv/www/wp-content/database/wp-post-checkpoint-append.sqlite-wal'],
    'page size' => [static fn (): mixed => $plan()['page_size'], 512],
    'minimum statement generation' => [static fn (): mixed => $plan()['minimum_statement_generation'], 209],
    'next writer generation' => [static fn (): mixed => $plan()['next_writer_generation'], 210],
    'next commit frame' => [static fn (): mixed => $plan()['next_commit_frame'], 22],
    'database digest' => [static fn (): mixed => $plan()['checkpointed_database_digest'], $databaseDigest],
    'wal digest' => [static fn (): mixed => $plan()['expected_wal_digest'], $walDigest],
    'consumer digest' => [static fn (): mixed => $plan()['consumer_digest'], $consumerDigest],
    'accepted names' => [static fn (): mixed => $plan()['accepted_append_batch_names'], ['autoload-frame-batch', 'cron-frame-batch']],
    'blocked names' => [static fn (): mixed => $plan()['blocked_append_batch_names'], ['stale-plugin-batch', 'unknown-writer-batch', 'old-generation-batch', 'gap-frame-batch', 'wrong-commit-batch', 'old-database-batch', 'old-wal-batch', 'old-consumer-batch', 'hot-journal-batch', 'open-savepoint-batch', 'missing-lock-batch', 'dirty-cache-batch']],
    'accepted reason' => [static fn (): mixed => $plan()['append_batch_rows'][0]['append_reason'], 'append_batch_matches_current_writer_generation'],
    'accepted transition' => [static fn (): mixed => $plan()['append_batch_rows'][0]['append_transition'], 'autoload-frame-batch>append-wal-frames'],
    'accepted page numbers sorted' => [static fn (): mixed => $plan()['append_batch_rows'][0]['page_numbers'], [2, 5]],
    'accepted page digest length' => [static fn (): mixed => strlen($plan()['append_batch_rows'][0]['page_digest']), 64],
    'cron page numbers' => [static fn (): mixed => $plan()['append_batch_rows'][1]['page_numbers'], [7]],
    'stale writer reason' => [static fn (): mixed => $plan()['append_batch_rows'][2]['append_reason'], 'append_writer_requires_reopen'],
    'stale writer reasons' => [static fn (): mixed => $plan()['append_batch_rows'][2]['blocked_reasons'], ['append_writer_requires_reopen']],
    'unknown writer reason' => [static fn (): mixed => $plan()['append_batch_rows'][3]['append_reason'], 'append_writer_not_admitted'],
    'old generation reason' => [static fn (): mixed => $plan()['append_batch_rows'][4]['append_reason'], 'append_writer_generation_mismatch'],
    'gap frame reason' => [static fn (): mixed => $plan()['append_batch_rows'][5]['append_reason'], 'append_first_frame_does_not_follow_checkpoint'],
    'wrong commit reason' => [static fn (): mixed => $plan()['append_batch_rows'][6]['append_reason'], 'append_commit_frame_mismatch'],
    'old database reason' => [static fn (): mixed => $plan()['append_batch_rows'][7]['append_reason'], 'append_database_digest_mismatch'],
    'old wal reason' => [static fn (): mixed => $plan()['append_batch_rows'][8]['append_reason'], 'append_wal_digest_mismatch'],
    'old consumer reason' => [static fn (): mixed => $plan()['append_batch_rows'][9]['append_reason'], 'append_consumer_digest_mismatch'],
    'hot journal reason' => [static fn (): mixed => $plan()['append_batch_rows'][10]['append_reason'], 'append_retains_hot_journal_digest'],
    'hot journal flag' => [static fn (): mixed => $plan()['append_batch_rows'][10]['hot_journal_retained'], true],
    'savepoint reason' => [static fn (): mixed => $plan()['append_batch_rows'][11]['append_reason'], 'append_savepoint_scope_not_closed'],
    'missing lock reason' => [static fn (): mixed => $plan()['append_batch_rows'][12]['append_reason'], 'append_missing_exclusive_lock_receipt'],
    'dirty reason' => [static fn (): mixed => $plan()['append_batch_rows'][13]['append_reason'], 'append_dirty_cache_before_frame_write'],
    'expected writer generation echoed' => [static fn (): mixed => $plan()['append_batch_rows'][0]['expected_writer_generation'], 210],
    'expected commit frame echoed' => [static fn (): mixed => $plan()['append_batch_rows'][0]['expected_commit_frame'], 22],
    'guard names' => [static fn (): mixed => $plan()['guard_names'], ['next209_writer_generation_fence', 'post_checkpoint_append_mix', 'commit_frame_advances_checkpoint', 'accepted_batches_hot_journal_free']],
    'guard matches' => [static fn (): mixed => $plan()['guard_matches'], [true, true, true, true]],
    'blocked guards' => [static fn (): mixed => $plan()['blocked_guard_names'], []],
    'operation inherited' => [static fn (): mixed => $plan()['operation_names'][0], 'verify_post_checkpoint_writer_generation_current_source_next209'],
    'operation verify present' => [static fn (): mixed => in_array('verify_post_checkpoint_wal_append_admission', $plan()['operation_names'], true), true],
    'accept operation count' => [static fn (): mixed => count(array_filter($plan()['operation_names'], static fn (string $name): bool => $name === 'accept_post_checkpoint_wal_append_batch')), 2],
    'block operation count' => [static fn (): mixed => count(array_filter($plan()['operation_names'], static fn (string $name): bool => $name === 'block_post_checkpoint_wal_append_batch')), 12],
    'append digest length' => [static fn (): mixed => strlen($plan()['append_digest']), 64],
    'dependency post-checkpoint append admission' => [static fn (): mixed => in_array('sqlite-wal-post-checkpoint-append-admission', $plan()['dependencies'], true), true],
    'dependency fence' => [static fn (): mixed => in_array('sqlite-post-checkpoint-wal-append-generation-fence', $plan()['dependencies'], true), true],
    'dependency wordpress append' => [static fn (): mixed => in_array('wordpress-import-post-checkpoint-wal-append-after-hot-journal', $plan()['dependencies'], true), true],
    'dependency closure' => [static fn (): mixed => str_contains($plan()['dependency_closure'], 'no new support component needed'), true],
    'non overlap' => [static fn (): mixed => str_contains($plan()['non_overlap'], 'does not repeat next208 reader-slot reuse'), true],
    'blocked status' => [static fn (): mixed => $blocked()['status'], 'wal-hot-journal-savepoint-checkpoint-post-checkpoint-append-admission-blocked'],
    'blocked guard from base' => [static fn (): mixed => $blocked()['blocked_guard_names'], ['next209_writer_generation_fence']],
    'all accepted blocked by missing mix' => [static fn (): mixed => $allAccepted()['status'], 'wal-hot-journal-savepoint-checkpoint-post-checkpoint-append-admission-blocked'],
    'all accepted guard' => [static fn (): mixed => $allAccepted()['blocked_guard_names'], ['post_checkpoint_append_mix']],
    'commit before first keeps mixed fence ready' => [static fn (): mixed => $badCommit()['status'], 'wal-hot-journal-savepoint-checkpoint-post-checkpoint-append-admission'],
    'commit before first keeps global frame guard' => [static fn (): mixed => $badCommit()['guard_matches'][2], true],
    'commit before first reason' => [static fn (): mixed => $badCommit()['append_batch_rows'][1]['blocked_reasons'], ['append_commit_frame_mismatch', 'append_commit_frame_before_first_frame']],
];

foreach ($cases as $name => [$callback, $expected]) {
    $tests['wal hot journal savepoint checkpoint post checkpoint append admission ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        $t->same($expected, $callback());
    };
}

$throws = [
    'bad base rejected' => static fn () => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::appendBatchCommitAdmissionPlan(['status' => 'bad'], $batches, 22),
    'empty batches rejected' => static fn () => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::appendBatchCommitAdmissionPlan($writerPlan, [], 22),
    'bad commit frame rejected' => static fn () => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::appendBatchCommitAdmissionPlan($writerPlan, $batches, 0),
    'bad statement generation rejected' => static fn () => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::appendBatchCommitAdmissionPlan(array_merge($writerPlan, ['minimum_statement_generation' => -1]), $batches, 22),
    'bad writer generation rejected' => static fn () => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::appendBatchCommitAdmissionPlan(array_merge($writerPlan, ['next_writer_generation' => 209]), $batches, 22),
    'bad database digest rejected' => static fn () => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::appendBatchCommitAdmissionPlan(array_merge($writerPlan, ['checkpointed_database_digest' => 'short']), $batches, 22),
    'bad wal digest rejected' => static fn () => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::appendBatchCommitAdmissionPlan(array_merge($writerPlan, ['expected_wal_digest' => 'short']), $batches, 22),
    'bad consumer digest rejected' => static fn () => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::appendBatchCommitAdmissionPlan(array_merge($writerPlan, ['consumer_digest' => 'short']), $batches, 22),
    'empty admitted writers rejected' => static fn () => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::appendBatchCommitAdmissionPlan(array_merge($writerPlan, ['admitted_writer_names' => []]), $batches, 22),
    'empty reopen writers rejected' => static fn () => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::appendBatchCommitAdmissionPlan(array_merge($writerPlan, ['reopen_writer_names' => []]), $batches, 22),
    'missing guard state rejected' => static fn () => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::appendBatchCommitAdmissionPlan(array_diff_key($writerPlan, ['blocked_guard_names' => true]), $batches, 22),
    'missing batch name rejected' => static fn () => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::appendBatchCommitAdmissionPlan($writerPlan, [$batch(['name' => ''])], 22),
    'missing writer name rejected' => static fn () => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::appendBatchCommitAdmissionPlan($writerPlan, [$batch(['writer_name' => ''])], 22),
    'bad batch generation rejected' => static fn () => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::appendBatchCommitAdmissionPlan($writerPlan, [$batch(['writer_generation' => -1])], 22),
    'bad batch checkpoint rejected' => static fn () => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::appendBatchCommitAdmissionPlan($writerPlan, [$batch(['checkpoint_frame' => -1])], 22),
    'bad first frame rejected' => static fn () => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::appendBatchCommitAdmissionPlan($writerPlan, [$batch(['first_frame' => -1])], 22),
    'bad batch commit rejected' => static fn () => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::appendBatchCommitAdmissionPlan($writerPlan, [$batch(['commit_frame' => -1])], 22),
    'bad observed database rejected' => static fn () => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::appendBatchCommitAdmissionPlan($writerPlan, [$batch(['observed_database_digest' => 'short'])], 22),
    'bad observed wal rejected' => static fn () => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::appendBatchCommitAdmissionPlan($writerPlan, [$batch(['observed_wal_digest' => 'short'])], 22),
    'bad observed consumer rejected' => static fn () => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::appendBatchCommitAdmissionPlan($writerPlan, [$batch(['observed_consumer_digest' => 'short'])], 22),
    'missing pages rejected' => static fn () => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::appendBatchCommitAdmissionPlan($writerPlan, [$batch(['page_digests' => []])], 22),
    'bad page rejected' => static fn () => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::appendBatchCommitAdmissionPlan($writerPlan, [$batch(['page_digests' => [0 => $digest('bad')]])], 22),
    'bad page digest rejected' => static fn () => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::appendBatchCommitAdmissionPlan($writerPlan, [$batch(['page_digests' => [2 => 'short']])], 22),
    'bad hot journal digest rejected' => static fn () => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::appendBatchCommitAdmissionPlan($writerPlan, [$batch(['hot_journal_digest' => 'short'])], 22),
];

foreach ($throws as $name => $callback) {
    $tests['wal hot journal savepoint checkpoint post checkpoint append admission ' . $name] = static function (TestRunner $t) use ($callback): void {
        $t->throws(Throwable::class, $callback);
    };
}

return $tests;

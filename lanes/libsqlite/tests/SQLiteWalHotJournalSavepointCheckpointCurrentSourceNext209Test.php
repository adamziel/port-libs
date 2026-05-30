<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan;

require_once __DIR__ . '/../src/SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan.php';

$tests = [];

$digest = static fn (string $value): string => hash('sha256', $value);
$databaseDigest = $digest('next209 checkpoint database');
$walDigest = $digest('next209 published wal');
$consumerDigest = $digest('next209 consumer generation');
$oldDatabaseDigest = $digest('next209 old database');
$oldWalDigest = $digest('next209 old wal');
$oldConsumerDigest = $digest('next209 old consumers');
$hotJournalDigest = $digest('next209 stale hot journal');

$base = [
    'status' => 'wal-hot-journal-savepoint-checkpoint-current-source-next206',
    'database_path' => '/srv/www/wp-content/database/wp-next209.sqlite',
    'journal_path' => '/srv/www/wp-content/database/wp-next209.sqlite-journal',
    'wal_path' => '/srv/www/wp-content/database/wp-next209.sqlite-wal',
    'page_size' => 512,
    'minimum_statement_generation' => 206,
    'checkpointed_database_digest' => $databaseDigest,
    'expected_wal_digest' => $walDigest,
    'consumer_digest' => $consumerDigest,
    'admitted_consumer_names' => ['wp-options-select-current', 'cron-reader-current'],
    'quarantined_consumer_names' => ['old-statement-generation', 'stale-page-digest'],
    'blocked_guard_names' => [],
    'operation_names' => ['verify_reopened_statement_generation_current_source_next206'],
    'dependencies' => ['sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next206'],
];

$writers = [
    [
        'name' => 'wp-options-autoload-writer',
        'kind' => 'writer',
        'writer_generation' => 207,
        'statement_generation' => 206,
        'observed_database_digest' => $databaseDigest,
        'observed_wal_digest' => $walDigest,
        'observed_consumer_digest' => $consumerDigest,
        'retains_consumers' => ['wp-options-select-current', 'cron-reader-current'],
        'reopens_consumers' => ['old-statement-generation', 'stale-page-digest'],
        'savepoint_depth' => 0,
    ],
    [
        'name' => 'schema-cookie-writer',
        'kind' => 'schema',
        'writer_generation' => 207,
        'statement_generation' => 207,
        'observed_database_digest' => $databaseDigest,
        'observed_wal_digest' => $walDigest,
        'observed_consumer_digest' => $consumerDigest,
        'retains_consumers' => ['cron-reader-current', 'wp-options-select-current'],
        'reopens_consumers' => ['stale-page-digest', 'old-statement-generation'],
    ],
    [
        'name' => 'old-writer-generation',
        'kind' => 'writer',
        'writer_generation' => 206,
        'statement_generation' => 206,
        'observed_database_digest' => $databaseDigest,
        'observed_wal_digest' => $walDigest,
        'observed_consumer_digest' => $consumerDigest,
        'retains_consumers' => ['wp-options-select-current', 'cron-reader-current'],
        'reopens_consumers' => ['old-statement-generation', 'stale-page-digest'],
    ],
    [
        'name' => 'old-statement-writer',
        'kind' => 'writer',
        'writer_generation' => 207,
        'statement_generation' => 205,
        'observed_database_digest' => $databaseDigest,
        'observed_wal_digest' => $walDigest,
        'observed_consumer_digest' => $consumerDigest,
        'retains_consumers' => ['wp-options-select-current', 'cron-reader-current'],
        'reopens_consumers' => ['old-statement-generation', 'stale-page-digest'],
    ],
    [
        'name' => 'old-database-writer',
        'kind' => 'writer',
        'writer_generation' => 207,
        'statement_generation' => 206,
        'observed_database_digest' => $oldDatabaseDigest,
        'observed_wal_digest' => $walDigest,
        'observed_consumer_digest' => $consumerDigest,
        'retains_consumers' => ['wp-options-select-current', 'cron-reader-current'],
        'reopens_consumers' => ['old-statement-generation', 'stale-page-digest'],
    ],
    [
        'name' => 'old-wal-writer',
        'kind' => 'writer',
        'writer_generation' => 207,
        'statement_generation' => 206,
        'observed_database_digest' => $databaseDigest,
        'observed_wal_digest' => $oldWalDigest,
        'observed_consumer_digest' => $consumerDigest,
        'retains_consumers' => ['wp-options-select-current', 'cron-reader-current'],
        'reopens_consumers' => ['old-statement-generation', 'stale-page-digest'],
    ],
    [
        'name' => 'old-consumer-writer',
        'kind' => 'checkpoint',
        'writer_generation' => 207,
        'statement_generation' => 206,
        'observed_database_digest' => $databaseDigest,
        'observed_wal_digest' => $walDigest,
        'observed_consumer_digest' => $oldConsumerDigest,
        'retains_consumers' => ['wp-options-select-current', 'cron-reader-current'],
        'reopens_consumers' => ['old-statement-generation', 'stale-page-digest'],
    ],
    [
        'name' => 'missing-current-consumer',
        'kind' => 'writer',
        'writer_generation' => 207,
        'statement_generation' => 206,
        'observed_database_digest' => $databaseDigest,
        'observed_wal_digest' => $walDigest,
        'observed_consumer_digest' => $consumerDigest,
        'retains_consumers' => ['wp-options-select-current'],
        'reopens_consumers' => ['old-statement-generation', 'stale-page-digest'],
    ],
    [
        'name' => 'missing-stale-consumer',
        'kind' => 'writer',
        'writer_generation' => 207,
        'statement_generation' => 206,
        'observed_database_digest' => $databaseDigest,
        'observed_wal_digest' => $walDigest,
        'observed_consumer_digest' => $consumerDigest,
        'retains_consumers' => ['wp-options-select-current', 'cron-reader-current'],
        'reopens_consumers' => ['old-statement-generation'],
    ],
    [
        'name' => 'hot-journal-writer',
        'kind' => 'writer',
        'writer_generation' => 207,
        'statement_generation' => 206,
        'observed_database_digest' => $databaseDigest,
        'observed_wal_digest' => $walDigest,
        'observed_consumer_digest' => $consumerDigest,
        'retains_consumers' => ['wp-options-select-current', 'cron-reader-current'],
        'reopens_consumers' => ['old-statement-generation', 'stale-page-digest'],
        'hot_journal_digest' => $hotJournalDigest,
    ],
    [
        'name' => 'savepoint-open-writer',
        'kind' => 'writer',
        'writer_generation' => 207,
        'statement_generation' => 206,
        'observed_database_digest' => $databaseDigest,
        'observed_wal_digest' => $walDigest,
        'observed_consumer_digest' => $consumerDigest,
        'retains_consumers' => ['wp-options-select-current', 'cron-reader-current'],
        'reopens_consumers' => ['old-statement-generation', 'stale-page-digest'],
        'savepoint_depth' => 1,
    ],
    [
        'name' => 'dirty-cache-writer',
        'kind' => 'writer',
        'writer_generation' => 207,
        'statement_generation' => 206,
        'observed_database_digest' => $databaseDigest,
        'observed_wal_digest' => $walDigest,
        'observed_consumer_digest' => $consumerDigest,
        'retains_consumers' => ['wp-options-select-current', 'cron-reader-current'],
        'reopens_consumers' => ['old-statement-generation', 'stale-page-digest'],
        'dirty' => true,
    ],
    [
        'name' => 'closed-writer',
        'kind' => 'writer',
        'writer_generation' => 207,
        'statement_generation' => 206,
        'observed_database_digest' => $databaseDigest,
        'observed_wal_digest' => $walDigest,
        'observed_consumer_digest' => $consumerDigest,
        'retains_consumers' => ['wp-options-select-current', 'cron-reader-current'],
        'reopens_consumers' => ['old-statement-generation', 'stale-page-digest'],
        'closed' => true,
    ],
];

$plan = static fn (): array => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::writerGenerationAdvancePlan($base, $writers, 207);
$blockedBase = array_merge($base, ['blocked_guard_names' => ['statement_generation_mix']]);
$blocked = static fn (): array => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::writerGenerationAdvancePlan($blockedBase, [$writers[0], $writers[2]], 207);
$allCurrent = static fn (): array => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::writerGenerationAdvancePlan($base, [$writers[0], $writers[1]], 207);

$cases = [
    'status' => [static fn (): mixed => $plan()['status'], 'wal-hot-journal-savepoint-checkpoint-current-source-next209'],
    'reason' => [static fn (): mixed => $plan()['reason'], 'post_checkpoint_writers_follow_current_statement_generation'],
    'base status' => [static fn (): mixed => $plan()['base_status'], 'wal-hot-journal-savepoint-checkpoint-current-source-next206'],
    'database path' => [static fn (): mixed => $plan()['database_path'], '/srv/www/wp-content/database/wp-next209.sqlite'],
    'journal path' => [static fn (): mixed => $plan()['journal_path'], '/srv/www/wp-content/database/wp-next209.sqlite-journal'],
    'wal path' => [static fn (): mixed => $plan()['wal_path'], '/srv/www/wp-content/database/wp-next209.sqlite-wal'],
    'page size' => [static fn (): mixed => $plan()['page_size'], 512],
    'minimum statement generation' => [static fn (): mixed => $plan()['minimum_statement_generation'], 206],
    'next writer generation' => [static fn (): mixed => $plan()['next_writer_generation'], 207],
    'database digest' => [static fn (): mixed => $plan()['checkpointed_database_digest'], $databaseDigest],
    'wal digest' => [static fn (): mixed => $plan()['expected_wal_digest'], $walDigest],
    'consumer digest' => [static fn (): mixed => $plan()['consumer_digest'], $consumerDigest],
    'admitted consumers' => [static fn (): mixed => $plan()['admitted_consumer_names'], ['wp-options-select-current', 'cron-reader-current']],
    'quarantined consumers' => [static fn (): mixed => $plan()['quarantined_consumer_names'], ['old-statement-generation', 'stale-page-digest']],
    'admitted writers' => [static fn (): mixed => $plan()['admitted_writer_names'], ['wp-options-autoload-writer', 'schema-cookie-writer']],
    'reopen writers' => [static fn (): mixed => $plan()['reopen_writer_names'], ['old-writer-generation', 'old-statement-writer', 'old-database-writer', 'old-wal-writer', 'old-consumer-writer', 'missing-current-consumer', 'missing-stale-consumer', 'hot-journal-writer', 'savepoint-open-writer', 'dirty-cache-writer', 'closed-writer']],
    'first writer admitted' => [static fn (): mixed => $plan()['writer_rows'][0]['admitted'], true],
    'first writer reason' => [static fn (): mixed => $plan()['writer_rows'][0]['writer_reason'], 'writer_matches_post_checkpoint_generation'],
    'schema kind preserved' => [static fn (): mixed => $plan()['writer_rows'][1]['kind'], 'schema'],
    'old generation reason' => [static fn (): mixed => $plan()['writer_rows'][2]['writer_reason'], 'writer_generation_mismatch'],
    'old generation blocked reasons' => [static fn (): mixed => $plan()['writer_rows'][2]['blocked_reasons'], ['writer_generation_mismatch']],
    'old statement reason' => [static fn (): mixed => $plan()['writer_rows'][3]['writer_reason'], 'writer_statement_generation_predates_checkpoint'],
    'old database expected digest' => [static fn (): mixed => $plan()['writer_rows'][4]['expected_database_digest'], $databaseDigest],
    'old database reason' => [static fn (): mixed => $plan()['writer_rows'][4]['writer_reason'], 'writer_database_digest_mismatch'],
    'old wal reason' => [static fn (): mixed => $plan()['writer_rows'][5]['writer_reason'], 'writer_wal_digest_mismatch'],
    'old consumer reason' => [static fn (): mixed => $plan()['writer_rows'][6]['writer_reason'], 'writer_consumer_digest_mismatch'],
    'missing current reason' => [static fn (): mixed => $plan()['writer_rows'][7]['writer_reason'], 'writer_current_consumers_not_retained'],
    'missing retained list' => [static fn (): mixed => $plan()['writer_rows'][7]['missing_retained_consumers'], ['cron-reader-current']],
    'missing stale reason' => [static fn (): mixed => $plan()['writer_rows'][8]['writer_reason'], 'writer_stale_consumers_not_reopened'],
    'missing reopened list' => [static fn (): mixed => $plan()['writer_rows'][8]['missing_reopened_consumers'], ['stale-page-digest']],
    'hot journal reason' => [static fn (): mixed => $plan()['writer_rows'][9]['writer_reason'], 'writer_retains_hot_journal_digest'],
    'hot journal flag' => [static fn (): mixed => $plan()['writer_rows'][9]['hot_journal_retained'], true],
    'savepoint reason' => [static fn (): mixed => $plan()['writer_rows'][10]['writer_reason'], 'writer_savepoint_scope_not_closed'],
    'dirty reason' => [static fn (): mixed => $plan()['writer_rows'][11]['writer_reason'], 'writer_cache_dirty_before_append'],
    'closed reason' => [static fn (): mixed => $plan()['writer_rows'][12]['writer_reason'], 'writer_handle_closed_before_append'],
    'guard names' => [static fn (): mixed => $plan()['guard_names'], ['next206_statement_generation_fence', 'current_and_stale_consumer_mix', 'writer_generation_mix', 'writer_rows_hot_journal_free']],
    'guard matches' => [static fn (): mixed => $plan()['guard_matches'], [true, true, true, true]],
    'blocked guard names' => [static fn (): mixed => $plan()['blocked_guard_names'], []],
    'operation inherited' => [static fn (): mixed => $plan()['operation_names'][0], 'verify_reopened_statement_generation_current_source_next206'],
    'operation verify present' => [static fn (): mixed => in_array('verify_post_checkpoint_writer_generation_current_source_next209', $plan()['operation_names'], true), true],
    'retain writer operation count' => [static fn (): mixed => count(array_filter($plan()['operation_names'], static fn (string $name): bool => $name === 'retain_post_checkpoint_writer_current_source_next209')), 2],
    'reopen writer operation count' => [static fn (): mixed => count(array_filter($plan()['operation_names'], static fn (string $name): bool => $name === 'reopen_post_checkpoint_writer_current_source_next209')), 11],
    'writer digest length' => [static fn (): mixed => strlen($plan()['writer_digest']), 64],
    'dependency next209' => [static fn (): mixed => in_array('sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next209', $plan()['dependencies'], true), true],
    'dependency writer fence' => [static fn (): mixed => in_array('sqlite-post-checkpoint-writer-generation-fence', $plan()['dependencies'], true), true],
    'dependency application' => [static fn (): mixed => in_array('application-import-post-checkpoint-writer-reopen', $plan()['dependencies'], true), true],
    'dependency closure' => [static fn (): mixed => str_contains($plan()['dependency_closure'], 'no new support component needed'), true],
    'non overlap' => [static fn (): mixed => str_contains($plan()['non_overlap'], 'does not repeat WAL byte truncation'), true],
    'blocked status' => [static fn (): mixed => $blocked()['status'], 'wal-hot-journal-savepoint-checkpoint-current-source-blocked-next209'],
    'blocked reason' => [static fn (): mixed => $blocked()['reason'], 'post_checkpoint_writers_wait_for_current_source_reopen'],
    'blocked guard from base' => [static fn (): mixed => $blocked()['blocked_guard_names'], ['next206_statement_generation_fence']],
    'all current blocked status' => [static fn (): mixed => $allCurrent()['status'], 'wal-hot-journal-savepoint-checkpoint-current-source-blocked-next209'],
    'all current blocked guard' => [static fn (): mixed => $allCurrent()['blocked_guard_names'], ['writer_generation_mix']],
];

foreach ($cases as $name => [$callback, $expected]) {
    $tests['wal hot journal savepoint checkpoint current source next209 ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        $t->same($expected, $callback());
    };
}

$throws = [
    'bad base rejected' => static fn () => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::writerGenerationAdvancePlan(['status' => 'bad'], $writers, 207),
    'empty writers rejected' => static fn () => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::writerGenerationAdvancePlan($base, [], 207),
    'bad writer generation rejected' => static fn () => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::writerGenerationAdvancePlan($base, $writers, 0),
    'writer generation before statement rejected' => static fn () => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::writerGenerationAdvancePlan($base, $writers, 206),
    'missing database digest rejected' => static fn () => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::writerGenerationAdvancePlan(array_merge($base, ['checkpointed_database_digest' => 'short']), $writers, 207),
    'missing consumer list rejected' => static fn () => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::writerGenerationAdvancePlan(array_merge($base, ['admitted_consumer_names' => [null]]), $writers, 207),
    'missing guard state rejected' => static fn () => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::writerGenerationAdvancePlan(array_merge($base, ['blocked_guard_names' => null]), $writers, 207),
    'missing writer name rejected' => static fn () => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::writerGenerationAdvancePlan($base, [array_merge($writers[0], ['name' => ''])], 207),
    'bad writer kind rejected' => static fn () => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::writerGenerationAdvancePlan($base, [array_merge($writers[0], ['kind' => 'reader'])], 207),
    'bad writer generation row rejected' => static fn () => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::writerGenerationAdvancePlan($base, [array_merge($writers[0], ['writer_generation' => -1])], 207),
    'bad digest row rejected' => static fn () => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::writerGenerationAdvancePlan($base, [array_merge($writers[0], ['observed_wal_digest' => 'short'])], 207),
    'bad consumer lists rejected' => static fn () => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::writerGenerationAdvancePlan($base, [array_merge($writers[0], ['retains_consumers' => null])], 207),
    'bad hot journal digest rejected' => static fn () => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::writerGenerationAdvancePlan($base, [array_merge($writers[0], ['hot_journal_digest' => 'short'])], 207),
];

foreach ($throws as $name => $callback) {
    $tests['wal hot journal savepoint checkpoint current source next209 ' . $name] = static function (TestRunner $t) use ($callback): void {
        $t->throws(Throwable::class, $callback);
    };
}

return $tests;

<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan;

require_once __DIR__ . '/../src/SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan.php';

$tests = [];

$pageSize = 512;
$page = static fn (string $label): string => str_pad($label, $pageSize, '.', STR_PAD_RIGHT);
$digest = static fn (string $bytes): string => hash('sha256', $bytes);
$checkpointedDatabase = $page('next206 schema checkpoint')
    . $page('next206 wp_options checkpoint')
    . $page('next206 plugin checkpoint')
    . $page('next206 cron checkpoint');
$oldDatabase = $page('next206 schema old')
    . $page('next206 wp_options old')
    . $page('next206 plugin old')
    . $page('next206 cron old');
$databaseDigest = $digest($checkpointedDatabase);
$oldDatabaseDigest = $digest($oldDatabase);
$walDigest = $digest('next206 published wal generation');
$oldWalDigest = $digest('next206 old wal generation');
$hotJournalDigest = $digest('next206 stale hot journal');
$pageDigests = [
    1 => $digest($page('next206 schema checkpoint')),
    2 => $digest($page('next206 wp_options checkpoint')),
    3 => $digest($page('next206 plugin checkpoint')),
    4 => $digest($page('next206 cron checkpoint')),
];
$oldPageDigests = [
    1 => $digest($page('next206 schema old')),
    2 => $digest($page('next206 wp_options old')),
    3 => $digest($page('next206 plugin old')),
    4 => $digest($page('next206 cron old')),
];
$base = [
    'status' => 'wal-hot-journal-savepoint-checkpoint-current-source-next203',
    'database_path' => '/srv/www/wp-content/database/wp-next206.sqlite',
    'journal_path' => '/srv/www/wp-content/database/wp-next206.sqlite-journal',
    'wal_path' => '/srv/www/wp-content/database/wp-next206.sqlite-wal',
    'page_size' => $pageSize,
    'checkpointed_database_digest' => $databaseDigest,
    'expected_wal_digest' => $walDigest,
    'expected_page_digests' => $pageDigests,
    'stale_guard_names' => [],
    'operation_names' => ['verify_checkpoint_page_cache_leases_current_source_next203'],
    'dependencies' => ['sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next203'],
];
$blockedBase = $base;
$blockedBase['stale_guard_names'] = ['base_wal_sidecar_publication'];
$missingPagesBase = $base;
unset($missingPagesBase['expected_page_digests']);

$consumers = [
    [
        'name' => 'wp-options-select-current',
        'reader_epoch' => 207,
        'statement_generation' => 206,
        'root_pages' => [1, 2],
        'observed_database_digest' => $databaseDigest,
        'observed_wal_digest' => $walDigest,
        'observed_page_digests' => [
            1 => $pageDigests[1],
            2 => $pageDigests[2],
        ],
        'savepoint_depth' => 0,
    ],
    [
        'name' => 'cron-reader-current',
        'reader_epoch' => 208,
        'statement_generation' => 207,
        'root_pages' => [4],
        'observed_database_digest' => $databaseDigest,
        'observed_wal_digest' => $walDigest,
        'observed_page_digests' => [
            4 => $pageDigests[4],
        ],
    ],
    [
        'name' => 'old-statement-generation',
        'reader_epoch' => 205,
        'statement_generation' => 205,
        'root_pages' => [2],
        'observed_database_digest' => $databaseDigest,
        'observed_wal_digest' => $walDigest,
        'observed_page_digests' => [
            2 => $pageDigests[2],
        ],
    ],
    [
        'name' => 'old-database-digest',
        'reader_epoch' => 207,
        'statement_generation' => 206,
        'root_pages' => [2],
        'observed_database_digest' => $oldDatabaseDigest,
        'observed_wal_digest' => $walDigest,
        'observed_page_digests' => [
            2 => $pageDigests[2],
        ],
    ],
    [
        'name' => 'old-wal-digest',
        'reader_epoch' => 207,
        'statement_generation' => 206,
        'root_pages' => [2],
        'observed_database_digest' => $databaseDigest,
        'observed_wal_digest' => $oldWalDigest,
        'observed_page_digests' => [
            2 => $pageDigests[2],
        ],
    ],
    [
        'name' => 'stale-page-digest',
        'reader_epoch' => 207,
        'statement_generation' => 206,
        'root_pages' => [2],
        'observed_database_digest' => $databaseDigest,
        'observed_wal_digest' => $walDigest,
        'observed_page_digests' => [
            2 => $oldPageDigests[2],
        ],
    ],
    [
        'name' => 'hot-journal-retained',
        'reader_epoch' => 207,
        'statement_generation' => 206,
        'root_pages' => [3],
        'observed_database_digest' => $databaseDigest,
        'observed_wal_digest' => $walDigest,
        'observed_page_digests' => [
            3 => $pageDigests[3],
        ],
        'hot_journal_digest' => $hotJournalDigest,
    ],
    [
        'name' => 'savepoint-still-open',
        'reader_epoch' => 207,
        'statement_generation' => 206,
        'root_pages' => [3],
        'observed_database_digest' => $databaseDigest,
        'observed_wal_digest' => $walDigest,
        'observed_page_digests' => [
            3 => $pageDigests[3],
        ],
        'savepoint_depth' => 1,
    ],
    [
        'name' => 'dirty-cache',
        'reader_epoch' => 207,
        'statement_generation' => 206,
        'root_pages' => [1],
        'observed_database_digest' => $databaseDigest,
        'observed_wal_digest' => $walDigest,
        'observed_page_digests' => [
            1 => $pageDigests[1],
        ],
        'dirty' => true,
    ],
    [
        'name' => 'page-outside-generation',
        'reader_epoch' => 207,
        'statement_generation' => 206,
        'root_pages' => [5],
        'observed_database_digest' => $databaseDigest,
        'observed_wal_digest' => $walDigest,
        'observed_page_digests' => [
            5 => $digest($page('next206 missing page')),
        ],
    ],
];

$plan = static fn (): array => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::statementConsumerAdmissionPlan($base, $consumers, 206);
$blocked = static fn (): array => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::statementConsumerAdmissionPlan($blockedBase, [$consumers[0], $consumers[2]], 206);
$allCurrent = static fn (): array => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::statementConsumerAdmissionPlan($base, [$consumers[0], $consumers[1]], 206);

$cases = [
    'status' => [static fn (): mixed => $plan()['status'], 'wal-hot-journal-savepoint-checkpoint-current-source-next206'],
    'reason' => [static fn (): mixed => $plan()['reason'], 'reopened_statement_consumers_match_checkpoint_generation'],
    'base status' => [static fn (): mixed => $plan()['base_status'], 'wal-hot-journal-savepoint-checkpoint-current-source-next203'],
    'database path' => [static fn (): mixed => $plan()['database_path'], '/srv/www/wp-content/database/wp-next206.sqlite'],
    'journal path' => [static fn (): mixed => $plan()['journal_path'], '/srv/www/wp-content/database/wp-next206.sqlite-journal'],
    'wal path' => [static fn (): mixed => $plan()['wal_path'], '/srv/www/wp-content/database/wp-next206.sqlite-wal'],
    'page size' => [static fn (): mixed => $plan()['page_size'], 512],
    'minimum generation' => [static fn (): mixed => $plan()['minimum_statement_generation'], 206],
    'database digest' => [static fn (): mixed => $plan()['checkpointed_database_digest'], $databaseDigest],
    'wal digest' => [static fn (): mixed => $plan()['expected_wal_digest'], $walDigest],
    'expected page two digest' => [static fn (): mixed => $plan()['expected_page_digests'][2], $pageDigests[2]],
    'admitted names' => [static fn (): mixed => $plan()['admitted_consumer_names'], ['wp-options-select-current', 'cron-reader-current']],
    'quarantined names' => [static fn (): mixed => $plan()['quarantined_consumer_names'], ['old-statement-generation', 'old-database-digest', 'old-wal-digest', 'stale-page-digest', 'hot-journal-retained', 'savepoint-still-open', 'dirty-cache', 'page-outside-generation']],
    'current reason' => [static fn (): mixed => $plan()['consumer_rows'][0]['consumer_reason'], 'statement_matches_checkpoint_generation'],
    'current page matched' => [static fn (): mixed => $plan()['consumer_rows'][0]['page_rows'][1]['matched'], true],
    'cron transition' => [static fn (): mixed => $plan()['consumer_rows'][1]['consumer_transition'], 'cron-reader-current>retain-statement'],
    'old generation reason' => [static fn (): mixed => $plan()['consumer_rows'][2]['consumer_reason'], 'statement_generation_predates_checkpoint_publication'],
    'old generation blocked reasons' => [static fn (): mixed => $plan()['consumer_rows'][2]['blocked_reasons'], ['statement_generation_predates_checkpoint_publication']],
    'old database reason' => [static fn (): mixed => $plan()['consumer_rows'][3]['consumer_reason'], 'statement_database_digest_mismatch'],
    'old database expected digest' => [static fn (): mixed => $plan()['consumer_rows'][3]['expected_database_digest'], $databaseDigest],
    'old wal reason' => [static fn (): mixed => $plan()['consumer_rows'][4]['consumer_reason'], 'statement_wal_digest_mismatch'],
    'old wal expected digest' => [static fn (): mixed => $plan()['consumer_rows'][4]['expected_wal_digest'], $walDigest],
    'stale page reason' => [static fn (): mixed => $plan()['consumer_rows'][5]['consumer_reason'], 'statement_page_digest_mismatch'],
    'stale page list' => [static fn (): mixed => $plan()['consumer_rows'][5]['stale_pages'], [2]],
    'stale page row reason' => [static fn (): mixed => $plan()['consumer_rows'][5]['page_rows'][0]['reason'], 'checkpoint_generation_page_stale'],
    'hot journal reason' => [static fn (): mixed => $plan()['consumer_rows'][6]['consumer_reason'], 'statement_retains_hot_journal_digest'],
    'hot journal retained flag' => [static fn (): mixed => $plan()['consumer_rows'][6]['hot_journal_retained'], true],
    'savepoint reason' => [static fn (): mixed => $plan()['consumer_rows'][7]['consumer_reason'], 'statement_savepoint_scope_not_closed'],
    'dirty reason' => [static fn (): mixed => $plan()['consumer_rows'][8]['consumer_reason'], 'statement_cache_dirty_before_checkpoint_publication'],
    'missing page reason' => [static fn (): mixed => $plan()['consumer_rows'][9]['consumer_reason'], 'statement_page_digest_mismatch'],
    'missing page list' => [static fn (): mixed => $plan()['consumer_rows'][9]['missing_pages'], [5]],
    'missing page row reason' => [static fn (): mixed => $plan()['consumer_rows'][9]['page_rows'][0]['reason'], 'page_outside_checkpoint_generation'],
    'guard names' => [static fn (): mixed => $plan()['guard_names'], ['next203_page_cache_lease_fence', 'statement_generation_mix', 'hot_journal_absent_from_admitted_consumers']],
    'guard matches' => [static fn (): mixed => $plan()['guard_matches'], [true, true, true]],
    'blocked guards' => [static fn (): mixed => $plan()['blocked_guard_names'], []],
    'operation inherited' => [static fn (): mixed => $plan()['operation_names'][0], 'verify_checkpoint_page_cache_leases_current_source_next203'],
    'operation verify present' => [static fn (): mixed => in_array('verify_reopened_statement_generation_current_source_next206', $plan()['operation_names'], true), true],
    'retain operation count' => [static fn (): mixed => count(array_filter($plan()['operation_names'], static fn (string $name): bool => $name === 'retain_reopened_statement_current_source_next206')), 2],
    'quarantine operation count' => [static fn (): mixed => count(array_filter($plan()['operation_names'], static fn (string $name): bool => $name === 'quarantine_stale_statement_current_source_next206')), 8],
    'consumer digest length' => [static fn (): mixed => strlen($plan()['consumer_digest']), 64],
    'dependency next206' => [static fn (): mixed => in_array('sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next206', $plan()['dependencies'], true), true],
    'dependency statement fence' => [static fn (): mixed => in_array('sqlite-reopened-statement-generation-fence', $plan()['dependencies'], true), true],
    'dependency application reprepare' => [static fn (): mixed => in_array('application-current-source-prepared-statement-reprepare', $plan()['dependencies'], true), true],
    'dependency closure' => [static fn (): mixed => str_contains($plan()['dependency_closure'], 'no new support component needed'), true],
    'non overlap' => [static fn (): mixed => str_contains($plan()['non_overlap'], 'does not repeat WAL byte truncation'), true],
    'blocked status' => [static fn (): mixed => $blocked()['status'], 'wal-hot-journal-savepoint-checkpoint-current-source-blocked-next206'],
    'blocked reason' => [static fn (): mixed => $blocked()['reason'], 'reopened_statement_consumers_wait_for_checkpoint_generation_reprepare'],
    'blocked guard from base' => [static fn (): mixed => $blocked()['blocked_guard_names'], ['next203_page_cache_lease_fence']],
    'all current status blocked by missing mix' => [static fn (): mixed => $allCurrent()['status'], 'wal-hot-journal-savepoint-checkpoint-current-source-blocked-next206'],
    'all current guard' => [static fn (): mixed => $allCurrent()['blocked_guard_names'], ['statement_generation_mix']],
];

foreach ($cases as $name => [$callback, $expected]) {
    $tests['wal hot journal savepoint checkpoint current source next206 ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        $t->same($expected, $callback());
    };
}

$throws = [
    'bad base rejected' => static fn () => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::statementConsumerAdmissionPlan(['status' => 'bad'], $consumers, 206),
    'empty consumers rejected' => static fn () => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::statementConsumerAdmissionPlan($base, [], 206),
    'negative generation rejected' => static fn () => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::statementConsumerAdmissionPlan($base, $consumers, -1),
    'missing database digest rejected' => static fn () => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::statementConsumerAdmissionPlan(array_merge($base, ['checkpointed_database_digest' => 'short']), $consumers, 206),
    'missing wal digest rejected' => static fn () => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::statementConsumerAdmissionPlan(array_merge($base, ['expected_wal_digest' => 'short']), $consumers, 206),
    'missing page digests rejected' => static fn () => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::statementConsumerAdmissionPlan($missingPagesBase, $consumers, 206),
    'missing name rejected' => static fn () => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::statementConsumerAdmissionPlan($base, [array_merge($consumers[0], ['name' => ''])], 206),
    'bad generation row rejected' => static fn () => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::statementConsumerAdmissionPlan($base, [array_merge($consumers[0], ['statement_generation' => -1])], 206),
    'bad observed digest rejected' => static fn () => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::statementConsumerAdmissionPlan($base, [array_merge($consumers[0], ['observed_wal_digest' => 'short'])], 206),
    'missing root pages rejected' => static fn () => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::statementConsumerAdmissionPlan($base, [array_merge($consumers[0], ['root_pages' => []])], 206),
    'bad root page rejected' => static fn () => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::statementConsumerAdmissionPlan($base, [array_merge($consumers[0], ['root_pages' => [0]])], 206),
    'bad page digest rejected' => static fn () => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::statementConsumerAdmissionPlan($base, [array_merge($consumers[0], ['observed_page_digests' => [1 => 'short']])], 206),
    'bad hot journal digest rejected' => static fn () => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::statementConsumerAdmissionPlan($base, [array_merge($consumers[0], ['hot_journal_digest' => 'short'])], 206),
];

foreach ($throws as $name => $callback) {
    $tests['wal hot journal savepoint checkpoint current source next206 ' . $name] = static function (TestRunner $t) use ($callback): void {
        $t->throws(Throwable::class, $callback);
    };
}

return $tests;

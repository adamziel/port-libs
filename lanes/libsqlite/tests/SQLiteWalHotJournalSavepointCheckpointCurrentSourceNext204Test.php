<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteWalHotJournalSavepointCheckpointCurrentSourceNext204Plan;

require_once __DIR__ . '/../src/SQLiteWalHotJournalSavepointCheckpointCurrentSourceNext204Plan.php';

$tests = [];

$digest = static fn (string $bytes): string => hash('sha256', $bytes);
$databaseDigest = $digest('next204 checkpointed database image');
$oldDatabaseDigest = $digest('next204 old database image');
$base = [
    'status' => 'wal-hot-journal-savepoint-checkpoint-current-source-next203',
    'database_path' => '/srv/www/wp-content/database/wp-next204.sqlite',
    'journal_path' => '/srv/www/wp-content/database/wp-next204.sqlite-journal',
    'wal_path' => '/srv/www/wp-content/database/wp-next204.sqlite-wal',
    'checkpoint_generation' => 204,
    'schema_cookie' => 9182,
    'checkpointed_page_count' => 7,
    'checkpointed_database_digest' => $databaseDigest,
    'operation_names' => ['verify_checkpoint_page_cache_leases_current_source_next203'],
    'dependencies' => [
        'sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next203',
        'sqlite-checkpoint-page-cache-lease-fence',
    ],
];
$missingFenceBase = $base;
$missingFenceBase['dependencies'] = ['sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next203'];
$blockedBase = $base;
$blockedBase['status'] = 'wal-hot-journal-savepoint-checkpoint-current-source-blocked-next203';

$leases = [
    [
        'name' => 'wp-options-reader-current-ticket',
        'observed_checkpoint_generation' => 204,
        'observed_schema_cookie' => 9182,
        'observed_page_count' => 7,
        'observed_database_digest' => $databaseDigest,
        'reader_epoch' => 204,
    ],
    [
        'name' => 'wp-cron-reader-newer-epoch',
        'observed_checkpoint_generation' => 204,
        'observed_schema_cookie' => 9182,
        'observed_page_count' => 7,
        'observed_database_digest' => $databaseDigest,
        'reader_epoch' => 206,
    ],
    [
        'name' => 'wp-options-reader-old-generation',
        'observed_checkpoint_generation' => 203,
        'observed_schema_cookie' => 9182,
        'observed_page_count' => 7,
        'observed_database_digest' => $databaseDigest,
        'reader_epoch' => 203,
    ],
    [
        'name' => 'wp-plugin-reader-schema-cookie-stale',
        'observed_checkpoint_generation' => 204,
        'observed_schema_cookie' => 9181,
        'observed_page_count' => 7,
        'observed_database_digest' => $databaseDigest,
    ],
    [
        'name' => 'wp-options-reader-page-count-stale',
        'observed_checkpoint_generation' => 204,
        'observed_schema_cookie' => 9182,
        'observed_page_count' => 6,
        'observed_database_digest' => $databaseDigest,
    ],
    [
        'name' => 'wp-plugin-reader-database-digest-stale',
        'observed_checkpoint_generation' => 204,
        'observed_schema_cookie' => 9182,
        'observed_page_count' => 7,
        'observed_database_digest' => $oldDatabaseDigest,
    ],
    [
        'name' => 'wp-options-reader-dirty-ticket',
        'observed_checkpoint_generation' => 204,
        'observed_schema_cookie' => 9182,
        'observed_page_count' => 7,
        'observed_database_digest' => $databaseDigest,
        'dirty' => true,
    ],
    [
        'name' => 'wp-options-reader-closed-ticket',
        'observed_checkpoint_generation' => 204,
        'observed_schema_cookie' => 9182,
        'observed_page_count' => 7,
        'observed_database_digest' => $databaseDigest,
        'closed' => true,
    ],
];

$plan = static fn (): array => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNext204Plan::plan($base, $leases);
$missingFence = static fn (): array => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNext204Plan::plan($missingFenceBase, $leases);
$allCurrent = static fn (): array => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNext204Plan::plan($base, [$leases[0], $leases[1]]);

$cases = [
    'status' => [static fn (): mixed => $plan()['status'], 'wal-hot-journal-savepoint-checkpoint-current-source-next204'],
    'reason' => [static fn (): mixed => $plan()['reason'], 'checkpoint_generation_tickets_admit_current_source_page_cache'],
    'base status' => [static fn (): mixed => $plan()['base_status'], 'wal-hot-journal-savepoint-checkpoint-current-source-next203'],
    'database path' => [static fn (): mixed => $plan()['database_path'], '/srv/www/wp-content/database/wp-next204.sqlite'],
    'journal path' => [static fn (): mixed => $plan()['journal_path'], '/srv/www/wp-content/database/wp-next204.sqlite-journal'],
    'wal path' => [static fn (): mixed => $plan()['wal_path'], '/srv/www/wp-content/database/wp-next204.sqlite-wal'],
    'generation' => [static fn (): mixed => $plan()['checkpoint_generation'], 204],
    'schema cookie' => [static fn (): mixed => $plan()['schema_cookie'], 9182],
    'page count' => [static fn (): mixed => $plan()['checkpointed_page_count'], 7],
    'database digest' => [static fn (): mixed => $plan()['checkpointed_database_digest'], $databaseDigest],
    'admitted leases' => [static fn (): mixed => $plan()['admitted_lease_names'], ['wp-options-reader-current-ticket', 'wp-cron-reader-newer-epoch']],
    'reopen leases' => [static fn (): mixed => $plan()['reopen_lease_names'], ['wp-options-reader-old-generation', 'wp-plugin-reader-schema-cookie-stale', 'wp-options-reader-page-count-stale', 'wp-plugin-reader-database-digest-stale', 'wp-options-reader-dirty-ticket', 'wp-options-reader-closed-ticket']],
    'current reason' => [static fn (): mixed => $plan()['lease_rows'][0]['lease_reason'], 'lease_generation_ticket_matches_checkpoint_current_source'],
    'current failed checks' => [static fn (): mixed => $plan()['lease_rows'][0]['failed_checks'], []],
    'newer epoch admitted' => [static fn (): mixed => $plan()['lease_rows'][1]['admitted'], true],
    'old generation failed' => [static fn (): mixed => $plan()['lease_rows'][2]['failed_checks'], ['checkpoint_generation', 'reader_epoch_current']],
    'old generation transition' => [static fn (): mixed => $plan()['lease_rows'][2]['transition'], 'wp-options-reader-old-generation>reopen-checkpoint-generation-ticket:next204'],
    'schema failed' => [static fn (): mixed => $plan()['lease_rows'][3]['failed_checks'], ['schema_cookie']],
    'page count failed' => [static fn (): mixed => $plan()['lease_rows'][4]['failed_checks'], ['page_count']],
    'database digest failed' => [static fn (): mixed => $plan()['lease_rows'][5]['failed_checks'], ['database_digest']],
    'dirty failed' => [static fn (): mixed => $plan()['lease_rows'][6]['failed_checks'], ['not_dirty']],
    'closed failed' => [static fn (): mixed => $plan()['lease_rows'][7]['failed_checks'], ['not_closed']],
    'expected generation on stale' => [static fn (): mixed => $plan()['lease_rows'][2]['expected_checkpoint_generation'], 204],
    'expected schema on stale' => [static fn (): mixed => $plan()['lease_rows'][3]['expected_schema_cookie'], 9182],
    'expected count on stale' => [static fn (): mixed => $plan()['lease_rows'][4]['expected_page_count'], 7],
    'expected digest on stale' => [static fn (): mixed => $plan()['lease_rows'][5]['expected_database_digest'], $databaseDigest],
    'guard names' => [static fn (): mixed => $plan()['guard_names'], ['next203_page_digest_leases', 'checkpoint_generation_ticket', 'generation_reuse_mix']],
    'guard matches' => [static fn (): mixed => $plan()['guard_matches'], [true, true, true]],
    'blocked guards empty' => [static fn (): mixed => $plan()['blocked_guard_names'], []],
    'operation inherited' => [static fn (): mixed => $plan()['operation_names'][0], 'verify_checkpoint_page_cache_leases_current_source_next203'],
    'operation verify' => [static fn (): mixed => in_array('verify_checkpoint_generation_tickets_current_source_next204', $plan()['operation_names'], true), true],
    'operation retain count' => [static fn (): mixed => count(array_filter($plan()['operation_names'], static fn (string $name): bool => $name === 'retain_checkpoint_generation_lease_next204')), 2],
    'operation reopen count' => [static fn (): mixed => count(array_filter($plan()['operation_names'], static fn (string $name): bool => $name === 'reopen_checkpoint_generation_lease_next204')), 6],
    'ticket digest length' => [static fn (): mixed => strlen($plan()['generation_ticket_digest']), 64],
    'dependency next204' => [static fn (): mixed => in_array('sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next204', $plan()['dependencies'], true), true],
    'dependency ticket fence' => [static fn (): mixed => in_array('sqlite-checkpoint-generation-ticket-fence', $plan()['dependencies'], true), true],
    'dependency closure' => [static fn (): mixed => str_contains($plan()['dependency_closure'], 'no new support component needed'), true],
    'non overlap' => [static fn (): mixed => str_contains($plan()['non_overlap'], 'does not repeat next203 WAL/page digest lease checks'), true],
    'missing fence status' => [static fn (): mixed => $missingFence()['status'], 'wal-hot-journal-savepoint-checkpoint-current-source-blocked-next204'],
    'missing fence reason' => [static fn (): mixed => $missingFence()['reason'], 'checkpoint_generation_tickets_block_current_source_page_cache'],
    'missing fence guard' => [static fn (): mixed => $missingFence()['blocked_guard_names'], ['next203_page_digest_leases']],
    'all current blocked' => [static fn (): mixed => $allCurrent()['status'], 'wal-hot-journal-savepoint-checkpoint-current-source-blocked-next204'],
    'all current blocked guard' => [static fn (): mixed => $allCurrent()['blocked_guard_names'], ['generation_reuse_mix']],
];

foreach ($cases as $name => [$callback, $expected]) {
    $tests['wal hot journal savepoint checkpoint current source next204 ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        $t->same($expected, $callback());
    };
}

$throws = [
    'bad base rejected' => static fn () => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNext204Plan::plan($blockedBase, $leases),
    'missing leases rejected' => static fn () => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNext204Plan::plan($base, []),
    'bad generation rejected' => static fn () => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNext204Plan::plan(array_merge($base, ['checkpoint_generation' => 0]), $leases),
    'bad schema rejected' => static fn () => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNext204Plan::plan(array_merge($base, ['schema_cookie' => 0]), $leases),
    'bad page count rejected' => static fn () => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNext204Plan::plan(array_merge($base, ['checkpointed_page_count' => 0]), $leases),
    'bad database digest rejected' => static fn () => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNext204Plan::plan(array_merge($base, ['checkpointed_database_digest' => 'short']), $leases),
    'missing lease name rejected' => static fn () => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNext204Plan::plan($base, [array_merge($leases[0], ['name' => ''])]),
    'bad observed generation rejected' => static fn () => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNext204Plan::plan($base, [array_merge($leases[0], ['observed_checkpoint_generation' => 0])]),
    'bad observed schema rejected' => static fn () => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNext204Plan::plan($base, [array_merge($leases[0], ['observed_schema_cookie' => 0])]),
    'bad observed page count rejected' => static fn () => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNext204Plan::plan($base, [array_merge($leases[0], ['observed_page_count' => 0])]),
    'bad observed digest rejected' => static fn () => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNext204Plan::plan($base, [array_merge($leases[0], ['observed_database_digest' => 'short'])]),
    'bad reader epoch rejected' => static fn () => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNext204Plan::plan($base, [array_merge($leases[0], ['reader_epoch' => 0])]),
];

foreach ($throws as $name => $callback) {
    $tests['wal hot journal savepoint checkpoint current source next204 ' . $name] = static function (TestRunner $t) use ($callback): void {
        $t->throws(Throwable::class, $callback);
    };
}

return $tests;

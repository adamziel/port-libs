<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan;

require_once __DIR__ . '/../src/SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan.php';

$tests = [];

$pageSize = 512;
$page = static fn (string $label): string => str_pad($label, $pageSize, '.', STR_PAD_RIGHT);
$digest = static fn (string $bytes): string => hash('sha256', $bytes);
$checkpointedDatabase = $page('next203 schema checkpoint')
    . $page('next203 wp_options checkpoint')
    . $page('next203 plugin checkpoint')
    . $page('next203 cron checkpoint');
$oldDatabase = $page('next203 schema old')
    . $page('next203 wp_options old')
    . $page('next203 plugin old')
    . $page('next203 cron old');
$restartWalDigest = $digest('next203 restarted wal header');
$oldWalDigest = $digest('next203 old wal frames');
$emptyWalDigest = $digest('');
$base = [
    'status' => 'wal-hot-journal-savepoint-checkpoint-current-source-next196',
    'database_path' => '/srv/www/wp-content/database/wp-next203.sqlite',
    'journal_path' => '/srv/www/wp-content/database/wp-next203.sqlite-journal',
    'wal_path' => '/srv/www/wp-content/database/wp-next203.sqlite-wal',
    'page_size' => $pageSize,
    'mode' => 'restart',
    'sidecar' => [
        'matched' => true,
        'actual_digest' => $restartWalDigest,
    ],
    'persisted_wal_digest' => $restartWalDigest,
    'operation_names' => ['publish_wal_sidecar_current_source_next196'],
    'dependencies' => ['sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next196'],
];
$blockedBase = $base;
$blockedBase['sidecar']['matched'] = false;
$missingDigestBase = $base;
$missingDigestBase['sidecar'] = ['matched' => true];
unset($missingDigestBase['persisted_wal_digest']);

$leases = [
    [
        'name' => 'select-options-current-page-cache',
        'root_pages' => [1, 2],
        'observed_wal_digest' => $restartWalDigest,
        'observed_page_digests' => [
            1 => $digest($page('next203 schema checkpoint')),
            2 => $digest($page('next203 wp_options checkpoint')),
        ],
    ],
    [
        'name' => 'reader-cron-current-page-cache',
        'root_pages' => [4],
        'observed_wal_digest' => $restartWalDigest,
        'observed_page_digests' => [
            4 => $digest($page('next203 cron checkpoint')),
        ],
    ],
    [
        'name' => 'select-options-old-wal',
        'root_pages' => [2],
        'observed_wal_digest' => $oldWalDigest,
        'observed_page_digests' => [
            2 => $digest($page('next203 wp_options checkpoint')),
        ],
    ],
    [
        'name' => 'select-options-stale-page-cache',
        'root_pages' => [2],
        'observed_wal_digest' => $restartWalDigest,
        'observed_page_digests' => [
            2 => $digest($page('next203 wp_options old')),
        ],
    ],
    [
        'name' => 'reader-closed-before-page-publication',
        'root_pages' => [3],
        'observed_wal_digest' => $restartWalDigest,
        'observed_page_digests' => [
            3 => $digest($page('next203 plugin checkpoint')),
        ],
        'closed' => true,
    ],
    [
        'name' => 'reader-page-outside-image',
        'root_pages' => [5],
        'observed_wal_digest' => $restartWalDigest,
        'observed_page_digests' => [
            5 => $digest($page('next203 missing page')),
        ],
    ],
    [
        'name' => 'select-truncated-sidecar-required',
        'root_pages' => [1],
        'observed_wal_digest' => $emptyWalDigest,
        'observed_page_digests' => [
            1 => $digest($page('next203 schema checkpoint')),
        ],
        'requires_wal_sidecar' => true,
    ],
];

$plan = static fn (): array => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::checkpointPageCacheLeasePlan($base, $checkpointedDatabase, $leases);
$blocked = static fn (): array => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::checkpointPageCacheLeasePlan($blockedBase, $checkpointedDatabase, [$leases[0], $leases[2]]);
$allStale = static fn (): array => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::checkpointPageCacheLeasePlan($base, $checkpointedDatabase, [$leases[2], $leases[3]]);

$cases = [
    'status' => [static fn (): mixed => $plan()['status'], 'wal-hot-journal-savepoint-checkpoint-current-source-next203'],
    'reason' => [static fn (): mixed => $plan()['reason'], 'wal_sidecar_and_checkpoint_page_cache_leases_match_current_source'],
    'base status' => [static fn (): mixed => $plan()['base_status'], 'wal-hot-journal-savepoint-checkpoint-current-source-next196'],
    'database path' => [static fn (): mixed => $plan()['database_path'], '/srv/www/wp-content/database/wp-next203.sqlite'],
    'journal path' => [static fn (): mixed => $plan()['journal_path'], '/srv/www/wp-content/database/wp-next203.sqlite-journal'],
    'wal path' => [static fn (): mixed => $plan()['wal_path'], '/srv/www/wp-content/database/wp-next203.sqlite-wal'],
    'page size' => [static fn (): mixed => $plan()['page_size'], 512],
    'mode' => [static fn (): mixed => $plan()['mode'], 'restart'],
    'database digest' => [static fn (): mixed => $plan()['checkpointed_database_digest'], $digest($checkpointedDatabase)],
    'page count' => [static fn (): mixed => $plan()['checkpointed_page_count'], 4],
    'expected wal digest' => [static fn (): mixed => $plan()['expected_wal_digest'], $restartWalDigest],
    'expected page one digest' => [static fn (): mixed => $plan()['expected_page_digests'][1], $digest($page('next203 schema checkpoint'))],
    'expected page four digest' => [static fn (): mixed => $plan()['expected_page_digests'][4], $digest($page('next203 cron checkpoint'))],
    'admitted leases' => [static fn (): mixed => $plan()['admitted_lease_names'], ['select-options-current-page-cache', 'reader-cron-current-page-cache']],
    'reopen leases' => [static fn (): mixed => $plan()['reopen_lease_names'], ['select-options-old-wal', 'select-options-stale-page-cache', 'reader-closed-before-page-publication', 'reader-page-outside-image', 'select-truncated-sidecar-required']],
    'current lease reason' => [static fn (): mixed => $plan()['lease_rows'][0]['lease_reason'], 'lease_matches_wal_sidecar_and_checkpoint_pages'],
    'current lease page matched' => [static fn (): mixed => $plan()['lease_rows'][0]['page_rows'][1]['matched'], true],
    'cron lease transition' => [static fn (): mixed => $plan()['lease_rows'][1]['lease_transition'], 'reader-cron-current-page-cache>retain-checkpoint-page-cache'],
    'old wal reason' => [static fn (): mixed => $plan()['lease_rows'][2]['lease_reason'], 'lease_observed_wal_sidecar_predates_checkpoint_publication'],
    'old wal expected digest' => [static fn (): mixed => $plan()['lease_rows'][2]['expected_wal_digest'], $restartWalDigest],
    'stale page reason' => [static fn (): mixed => $plan()['lease_rows'][3]['lease_reason'], 'lease_observed_checkpoint_page_digest_is_stale'],
    'stale page list' => [static fn (): mixed => $plan()['lease_rows'][3]['stale_pages'], [2]],
    'stale page row reason' => [static fn (): mixed => $plan()['lease_rows'][3]['page_rows'][0]['reason'], 'checkpoint_page_digest_stale'],
    'closed lease reason' => [static fn (): mixed => $plan()['lease_rows'][4]['lease_reason'], 'lease_closed_or_dirty_before_checkpoint_page_publication'],
    'missing page reason' => [static fn (): mixed => $plan()['lease_rows'][5]['lease_reason'], 'lease_observed_checkpoint_page_digest_is_stale'],
    'missing page list' => [static fn (): mixed => $plan()['lease_rows'][5]['missing_pages'], [5]],
    'missing page row reason' => [static fn (): mixed => $plan()['lease_rows'][5]['page_rows'][0]['reason'], 'page_outside_checkpointed_database_image'],
    'requires wal reason stays stale sidecar first' => [static fn (): mixed => $plan()['lease_rows'][6]['lease_reason'], 'lease_observed_wal_sidecar_predates_checkpoint_publication'],
    'guard names' => [static fn (): mixed => $plan()['guard_names'], ['base_wal_sidecar_publication', 'checkpointed_database_image', 'lease_reuse_mix']],
    'guard matches' => [static fn (): mixed => $plan()['guard_matches'], [true, true, true]],
    'stale guards' => [static fn (): mixed => $plan()['stale_guard_names'], []],
    'operation inherited' => [static fn (): mixed => $plan()['operation_names'][0], 'publish_wal_sidecar_current_source_next196'],
    'operation verify' => [static fn (): mixed => in_array('verify_checkpoint_page_cache_leases_current_source_next203', $plan()['operation_names'], true), true],
    'operation retain count' => [static fn (): mixed => count(array_filter($plan()['operation_names'], static fn (string $name): bool => $name === 'retain_checkpoint_page_cache_lease_next203')), 2],
    'operation reopen count' => [static fn (): mixed => count(array_filter($plan()['operation_names'], static fn (string $name): bool => $name === 'reopen_checkpoint_page_cache_lease_next203')), 5],
    'lease digest length' => [static fn (): mixed => strlen($plan()['lease_digest']), 64],
    'dependency next203' => [static fn (): mixed => in_array('sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next203', $plan()['dependencies'], true), true],
    'dependency fence' => [static fn (): mixed => in_array('sqlite-checkpoint-page-cache-lease-fence', $plan()['dependencies'], true), true],
    'dependency closure' => [static fn (): mixed => str_contains($plan()['dependency_closure'], 'no new support component needed'), true],
    'non overlap' => [static fn (): mixed => str_contains($plan()['non_overlap'], 'does not repeat next196 sidecar'), true],
    'blocked status' => [static fn (): mixed => $blocked()['status'], 'wal-hot-journal-savepoint-checkpoint-current-source-blocked-next203'],
    'blocked reason' => [static fn (): mixed => $blocked()['reason'], 'wal_sidecar_or_checkpoint_page_cache_leases_block_current_source_reuse'],
    'blocked stale guards' => [static fn (): mixed => $blocked()['stale_guard_names'], ['base_wal_sidecar_publication']],
    'all stale status' => [static fn (): mixed => $allStale()['status'], 'wal-hot-journal-savepoint-checkpoint-current-source-blocked-next203'],
    'all stale guard' => [static fn (): mixed => $allStale()['stale_guard_names'], ['lease_reuse_mix']],
];

foreach ($cases as $name => [$callback, $expected]) {
    $tests['wal hot journal savepoint checkpoint current source next203 ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        $t->same($expected, $callback());
    };
}

$throws = [
    'bad base rejected' => static fn () => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::checkpointPageCacheLeasePlan(['status' => 'bad'], $checkpointedDatabase, $leases),
    'bad page size rejected' => static fn () => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::checkpointPageCacheLeasePlan(array_merge($base, ['page_size' => 0]), $checkpointedDatabase, $leases),
    'partial database rejected' => static fn () => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::checkpointPageCacheLeasePlan($base, $checkpointedDatabase . 'x', $leases),
    'missing leases rejected' => static fn () => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::checkpointPageCacheLeasePlan($base, $checkpointedDatabase, []),
    'missing digest rejected' => static fn () => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::checkpointPageCacheLeasePlan($missingDigestBase, $checkpointedDatabase, $leases),
    'missing lease name rejected' => static fn () => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::checkpointPageCacheLeasePlan($base, $checkpointedDatabase, [array_merge($leases[0], ['name' => ''])]),
    'bad wal digest rejected' => static fn () => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::checkpointPageCacheLeasePlan($base, $checkpointedDatabase, [array_merge($leases[0], ['observed_wal_digest' => 'short'])]),
    'missing root pages rejected' => static fn () => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::checkpointPageCacheLeasePlan($base, $checkpointedDatabase, [array_merge($leases[0], ['root_pages' => []])]),
    'missing page digests rejected' => static fn () => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::checkpointPageCacheLeasePlan($base, $checkpointedDatabase, [array_merge($leases[0], ['observed_page_digests' => []])]),
    'bad root page rejected' => static fn () => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::checkpointPageCacheLeasePlan($base, $checkpointedDatabase, [array_merge($leases[0], ['root_pages' => [0]])]),
    'bad page digest rejected' => static fn () => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::checkpointPageCacheLeasePlan($base, $checkpointedDatabase, [array_merge($leases[0], ['observed_page_digests' => [1 => 'short']])]),
];

foreach ($throws as $name => $callback) {
    $tests['wal hot journal savepoint checkpoint current source next203 ' . $name] = static function (TestRunner $t) use ($callback): void {
        $t->throws(Throwable::class, $callback);
    };
}

return $tests;

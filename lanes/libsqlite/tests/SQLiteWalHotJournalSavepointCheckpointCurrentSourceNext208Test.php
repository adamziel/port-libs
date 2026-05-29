<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan;

require_once __DIR__ . '/../src/SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan.php';

$tests = [];

$pageSize = 512;
$page = static fn (string $label): string => str_pad($label, $pageSize, '.', STR_PAD_RIGHT);
$digest = static fn (string $bytes): string => hash('sha256', $bytes);
$databaseDigest = $digest($page('next208 schema checkpoint') . $page('next208 wp_options checkpoint') . $page('next208 plugin checkpoint'));
$oldDatabaseDigest = $digest($page('next208 schema old') . $page('next208 wp_options old') . $page('next208 plugin old'));
$walDigest = $digest('next208 checkpoint wal generation');
$oldWalDigest = $digest('next208 old wal generation');
$hotJournalDigest = $digest('next208 stale hot journal');
$pageDigests = [
    1 => $digest($page('next208 schema checkpoint')),
    2 => $digest($page('next208 wp_options checkpoint')),
    3 => $digest($page('next208 plugin checkpoint')),
];
$oldPageDigests = [
    1 => $digest($page('next208 schema old')),
    2 => $digest($page('next208 wp_options old')),
    3 => $digest($page('next208 plugin old')),
];

$base = [
    'status' => 'wal-hot-journal-savepoint-checkpoint-current-source-next206',
    'database_path' => '/srv/www/wp-content/database/wp-next208.sqlite',
    'journal_path' => '/srv/www/wp-content/database/wp-next208.sqlite-journal',
    'wal_path' => '/srv/www/wp-content/database/wp-next208.sqlite-wal',
    'page_size' => $pageSize,
    'minimum_statement_generation' => 206,
    'checkpointed_database_digest' => $databaseDigest,
    'expected_wal_digest' => $walDigest,
    'expected_page_digests' => $pageDigests,
    'admitted_consumer_names' => ['wp-options-select-current', 'cron-reader-current'],
    'blocked_guard_names' => [],
    'operation_names' => ['verify_reopened_statement_generation_current_source_next206'],
    'dependencies' => ['sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next206'],
];
$blockedBase = $base;
$blockedBase['blocked_guard_names'] = ['hot_journal_absent_from_admitted_consumers'];
$missingPagesBase = $base;
unset($missingPagesBase['expected_page_digests']);

$slots = [
    [
        'name' => 'slot-options-current',
        'consumer_name' => 'wp-options-select-current',
        'read_mark' => 12,
        'reader_epoch' => 208,
        'checkpoint_frame' => 12,
        'root_pages' => [1, 2],
        'observed_database_digest' => $databaseDigest,
        'observed_wal_digest' => $walDigest,
        'observed_page_digests' => [
            1 => $pageDigests[1],
            2 => $pageDigests[2],
        ],
        'lock_receipt' => true,
    ],
    [
        'name' => 'slot-cron-current',
        'consumer_name' => 'cron-reader-current',
        'read_mark' => 11,
        'reader_epoch' => 207,
        'checkpoint_frame' => 11,
        'root_pages' => [3],
        'observed_database_digest' => $databaseDigest,
        'observed_wal_digest' => $walDigest,
        'observed_page_digests' => [
            3 => $pageDigests[3],
        ],
        'lock_receipt' => true,
    ],
    [
        'name' => 'slot-consumer-quarantined',
        'consumer_name' => 'old-statement-generation',
        'read_mark' => 12,
        'reader_epoch' => 208,
        'checkpoint_frame' => 12,
        'root_pages' => [2],
        'observed_database_digest' => $databaseDigest,
        'observed_wal_digest' => $walDigest,
        'observed_page_digests' => [
            2 => $pageDigests[2],
        ],
        'lock_receipt' => true,
    ],
    [
        'name' => 'slot-old-epoch',
        'consumer_name' => 'wp-options-select-current',
        'read_mark' => 10,
        'reader_epoch' => 205,
        'checkpoint_frame' => 10,
        'root_pages' => [2],
        'observed_database_digest' => $databaseDigest,
        'observed_wal_digest' => $walDigest,
        'observed_page_digests' => [
            2 => $pageDigests[2],
        ],
        'lock_receipt' => true,
    ],
    [
        'name' => 'slot-frame-after-checkpoint',
        'consumer_name' => 'wp-options-select-current',
        'read_mark' => 13,
        'reader_epoch' => 208,
        'checkpoint_frame' => 13,
        'root_pages' => [2],
        'observed_database_digest' => $databaseDigest,
        'observed_wal_digest' => $walDigest,
        'observed_page_digests' => [
            2 => $pageDigests[2],
        ],
        'lock_receipt' => true,
    ],
    [
        'name' => 'slot-old-database',
        'consumer_name' => 'wp-options-select-current',
        'read_mark' => 12,
        'reader_epoch' => 208,
        'checkpoint_frame' => 12,
        'root_pages' => [2],
        'observed_database_digest' => $oldDatabaseDigest,
        'observed_wal_digest' => $walDigest,
        'observed_page_digests' => [
            2 => $pageDigests[2],
        ],
        'lock_receipt' => true,
    ],
    [
        'name' => 'slot-old-wal',
        'consumer_name' => 'wp-options-select-current',
        'read_mark' => 12,
        'reader_epoch' => 208,
        'checkpoint_frame' => 12,
        'root_pages' => [2],
        'observed_database_digest' => $databaseDigest,
        'observed_wal_digest' => $oldWalDigest,
        'observed_page_digests' => [
            2 => $pageDigests[2],
        ],
        'lock_receipt' => true,
    ],
    [
        'name' => 'slot-stale-page',
        'consumer_name' => 'wp-options-select-current',
        'read_mark' => 12,
        'reader_epoch' => 208,
        'checkpoint_frame' => 12,
        'root_pages' => [2],
        'observed_database_digest' => $databaseDigest,
        'observed_wal_digest' => $walDigest,
        'observed_page_digests' => [
            2 => $oldPageDigests[2],
        ],
        'lock_receipt' => true,
    ],
    [
        'name' => 'slot-missing-page',
        'consumer_name' => 'wp-options-select-current',
        'read_mark' => 12,
        'reader_epoch' => 208,
        'checkpoint_frame' => 12,
        'root_pages' => [4],
        'observed_database_digest' => $databaseDigest,
        'observed_wal_digest' => $walDigest,
        'observed_page_digests' => [
            4 => $digest($page('next208 missing page')),
        ],
        'lock_receipt' => true,
    ],
    [
        'name' => 'slot-hot-journal',
        'consumer_name' => 'cron-reader-current',
        'read_mark' => 12,
        'reader_epoch' => 208,
        'checkpoint_frame' => 12,
        'root_pages' => [3],
        'observed_database_digest' => $databaseDigest,
        'observed_wal_digest' => $walDigest,
        'observed_page_digests' => [
            3 => $pageDigests[3],
        ],
        'hot_journal_digest' => $hotJournalDigest,
        'lock_receipt' => true,
    ],
    [
        'name' => 'slot-savepoint-open',
        'consumer_name' => 'cron-reader-current',
        'read_mark' => 12,
        'reader_epoch' => 208,
        'checkpoint_frame' => 12,
        'root_pages' => [3],
        'observed_database_digest' => $databaseDigest,
        'observed_wal_digest' => $walDigest,
        'observed_page_digests' => [
            3 => $pageDigests[3],
        ],
        'savepoint_depth' => 1,
        'lock_receipt' => true,
    ],
    [
        'name' => 'slot-missing-lock',
        'consumer_name' => 'cron-reader-current',
        'read_mark' => 12,
        'reader_epoch' => 208,
        'checkpoint_frame' => 12,
        'root_pages' => [3],
        'observed_database_digest' => $databaseDigest,
        'observed_wal_digest' => $walDigest,
        'observed_page_digests' => [
            3 => $pageDigests[3],
        ],
    ],
    [
        'name' => 'slot-dirty-cache',
        'consumer_name' => 'cron-reader-current',
        'read_mark' => 12,
        'reader_epoch' => 208,
        'checkpoint_frame' => 12,
        'root_pages' => [3],
        'observed_database_digest' => $databaseDigest,
        'observed_wal_digest' => $walDigest,
        'observed_page_digests' => [
            3 => $pageDigests[3],
        ],
        'lock_receipt' => true,
        'dirty' => true,
    ],
];

$plan = static fn (): array => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::readerSlotCheckpointAdmissionPlan($base, $slots, 12);
$blocked = static fn (): array => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::readerSlotCheckpointAdmissionPlan($blockedBase, [$slots[0], $slots[2]], 12);
$allCurrent = static fn (): array => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::readerSlotCheckpointAdmissionPlan($base, [$slots[0], $slots[1]], 12);

$cases = [
    'status' => [static fn (): mixed => $plan()['status'], 'wal-hot-journal-savepoint-checkpoint-current-source-next208'],
    'reason' => [static fn (): mixed => $plan()['reason'], 'checkpoint_reader_slots_match_current_source_generation'],
    'base status' => [static fn (): mixed => $plan()['base_status'], 'wal-hot-journal-savepoint-checkpoint-current-source-next206'],
    'database path' => [static fn (): mixed => $plan()['database_path'], '/srv/www/wp-content/database/wp-next208.sqlite'],
    'journal path' => [static fn (): mixed => $plan()['journal_path'], '/srv/www/wp-content/database/wp-next208.sqlite-journal'],
    'wal path' => [static fn (): mixed => $plan()['wal_path'], '/srv/www/wp-content/database/wp-next208.sqlite-wal'],
    'page size' => [static fn (): mixed => $plan()['page_size'], 512],
    'checkpoint frame' => [static fn (): mixed => $plan()['checkpoint_frame'], 12],
    'minimum generation' => [static fn (): mixed => $plan()['minimum_statement_generation'], 206],
    'database digest' => [static fn (): mixed => $plan()['checkpointed_database_digest'], $databaseDigest],
    'wal digest' => [static fn (): mixed => $plan()['expected_wal_digest'], $walDigest],
    'expected page two digest' => [static fn (): mixed => $plan()['expected_page_digests'][2], $pageDigests[2]],
    'retained names' => [static fn (): mixed => $plan()['retained_reader_slot_names'], ['slot-options-current', 'slot-cron-current']],
    'reopened names' => [static fn (): mixed => $plan()['reopened_reader_slot_names'], ['slot-consumer-quarantined', 'slot-old-epoch', 'slot-frame-after-checkpoint', 'slot-old-database', 'slot-old-wal', 'slot-stale-page', 'slot-missing-page', 'slot-hot-journal', 'slot-savepoint-open', 'slot-missing-lock', 'slot-dirty-cache']],
    'current reason' => [static fn (): mixed => $plan()['slot_rows'][0]['slot_reason'], 'reader_slot_matches_checkpoint_generation'],
    'current transition' => [static fn (): mixed => $plan()['slot_rows'][0]['slot_transition'], 'slot-options-current>retain-reader-slot'],
    'current page matched' => [static fn (): mixed => $plan()['slot_rows'][0]['page_rows'][1]['matched'], true],
    'cron reason' => [static fn (): mixed => $plan()['slot_rows'][1]['slot_reason'], 'reader_slot_matches_checkpoint_generation'],
    'consumer reason' => [static fn (): mixed => $plan()['slot_rows'][2]['slot_reason'], 'reader_slot_consumer_not_admitted_by_next206'],
    'consumer blocked reasons' => [static fn (): mixed => $plan()['slot_rows'][2]['blocked_reasons'], ['reader_slot_consumer_not_admitted_by_next206']],
    'old epoch reason' => [static fn (): mixed => $plan()['slot_rows'][3]['slot_reason'], 'reader_slot_epoch_predates_checkpoint_generation'],
    'frame reason' => [static fn (): mixed => $plan()['slot_rows'][4]['slot_reason'], 'reader_slot_frame_exceeds_checkpoint_publication'],
    'old database reason' => [static fn (): mixed => $plan()['slot_rows'][5]['slot_reason'], 'reader_slot_database_digest_mismatch'],
    'old database expected' => [static fn (): mixed => $plan()['slot_rows'][5]['expected_database_digest'], $databaseDigest],
    'old wal reason' => [static fn (): mixed => $plan()['slot_rows'][6]['slot_reason'], 'reader_slot_wal_digest_mismatch'],
    'old wal expected' => [static fn (): mixed => $plan()['slot_rows'][6]['expected_wal_digest'], $walDigest],
    'stale page reason' => [static fn (): mixed => $plan()['slot_rows'][7]['slot_reason'], 'reader_slot_page_digest_mismatch'],
    'stale page list' => [static fn (): mixed => $plan()['slot_rows'][7]['stale_pages'], [2]],
    'stale page row reason' => [static fn (): mixed => $plan()['slot_rows'][7]['page_rows'][0]['reason'], 'checkpoint_reader_page_stale'],
    'missing page reason' => [static fn (): mixed => $plan()['slot_rows'][8]['slot_reason'], 'reader_slot_page_digest_mismatch'],
    'missing page list' => [static fn (): mixed => $plan()['slot_rows'][8]['missing_pages'], [4]],
    'missing page row reason' => [static fn (): mixed => $plan()['slot_rows'][8]['page_rows'][0]['reason'], 'page_outside_checkpoint_reader_generation'],
    'hot journal reason' => [static fn (): mixed => $plan()['slot_rows'][9]['slot_reason'], 'reader_slot_retains_hot_journal_digest'],
    'hot journal retained flag' => [static fn (): mixed => $plan()['slot_rows'][9]['hot_journal_retained'], true],
    'savepoint reason' => [static fn (): mixed => $plan()['slot_rows'][10]['slot_reason'], 'reader_slot_savepoint_scope_not_closed'],
    'missing lock reason' => [static fn (): mixed => $plan()['slot_rows'][11]['slot_reason'], 'reader_slot_missing_shared_lock_receipt'],
    'dirty reason' => [static fn (): mixed => $plan()['slot_rows'][12]['slot_reason'], 'reader_slot_dirty_before_checkpoint_publication'],
    'guard names' => [static fn (): mixed => $plan()['guard_names'], ['next206_statement_consumer_fence', 'reader_slot_reuse_mix', 'checkpoint_frame_not_exceeded']],
    'guard matches' => [static fn (): mixed => $plan()['guard_matches'], [true, true, true]],
    'blocked guards' => [static fn (): mixed => $plan()['blocked_guard_names'], []],
    'operation inherited' => [static fn (): mixed => $plan()['operation_names'][0], 'verify_reopened_statement_generation_current_source_next206'],
    'operation verify present' => [static fn (): mixed => in_array('verify_checkpoint_reader_slots_current_source_next208', $plan()['operation_names'], true), true],
    'retain operation count' => [static fn (): mixed => count(array_filter($plan()['operation_names'], static fn (string $name): bool => $name === 'retain_checkpoint_reader_slot_current_source_next208')), 2],
    'reopen operation count' => [static fn (): mixed => count(array_filter($plan()['operation_names'], static fn (string $name): bool => $name === 'reopen_checkpoint_reader_slot_current_source_next208')), 11],
    'reader slot digest length' => [static fn (): mixed => strlen($plan()['reader_slot_digest']), 64],
    'dependency next208' => [static fn (): mixed => in_array('sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next208', $plan()['dependencies'], true), true],
    'dependency slot map' => [static fn (): mixed => in_array('sqlite-checkpoint-reader-slot-current-source-map', $plan()['dependencies'], true), true],
    'dependency wordpress reopen' => [static fn (): mixed => in_array('wordpress-import-reader-slot-reopen-after-checkpoint', $plan()['dependencies'], true), true],
    'dependency closure' => [static fn (): mixed => str_contains($plan()['dependency_closure'], 'no new support component needed'), true],
    'non overlap' => [static fn (): mixed => str_contains($plan()['non_overlap'], 'does not repeat next206 prepared-statement quarantine'), true],
    'blocked status' => [static fn (): mixed => $blocked()['status'], 'wal-hot-journal-savepoint-checkpoint-current-source-blocked-next208'],
    'blocked reason' => [static fn (): mixed => $blocked()['reason'], 'checkpoint_reader_slots_wait_for_current_source_reopen'],
    'blocked guard from base' => [static fn (): mixed => $blocked()['blocked_guard_names'], ['next206_statement_consumer_fence']],
    'all current blocked by missing mix' => [static fn (): mixed => $allCurrent()['status'], 'wal-hot-journal-savepoint-checkpoint-current-source-blocked-next208'],
    'all current guard' => [static fn (): mixed => $allCurrent()['blocked_guard_names'], ['reader_slot_reuse_mix']],
];

foreach ($cases as $name => [$callback, $expected]) {
    $tests['wal hot journal savepoint checkpoint current source next208 ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        $t->same($expected, $callback());
    };
}

$throws = [
    'bad base rejected' => static fn () => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::readerSlotCheckpointAdmissionPlan(['status' => 'bad'], $slots, 12),
    'empty slots rejected' => static fn () => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::readerSlotCheckpointAdmissionPlan($base, [], 12),
    'negative checkpoint rejected' => static fn () => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::readerSlotCheckpointAdmissionPlan($base, $slots, -1),
    'missing database digest rejected' => static fn () => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::readerSlotCheckpointAdmissionPlan(array_merge($base, ['checkpointed_database_digest' => 'short']), $slots, 12),
    'missing wal digest rejected' => static fn () => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::readerSlotCheckpointAdmissionPlan(array_merge($base, ['expected_wal_digest' => 'short']), $slots, 12),
    'missing pages rejected' => static fn () => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::readerSlotCheckpointAdmissionPlan($missingPagesBase, $slots, 12),
    'missing admitted consumers rejected' => static fn () => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::readerSlotCheckpointAdmissionPlan(array_merge($base, ['admitted_consumer_names' => []]), $slots, 12),
    'missing name rejected' => static fn () => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::readerSlotCheckpointAdmissionPlan($base, [array_merge($slots[0], ['name' => ''])], 12),
    'missing consumer rejected' => static fn () => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::readerSlotCheckpointAdmissionPlan($base, [array_merge($slots[0], ['consumer_name' => ''])], 12),
    'bad read mark rejected' => static fn () => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::readerSlotCheckpointAdmissionPlan($base, [array_merge($slots[0], ['read_mark' => -1])], 12),
    'bad checkpoint frame rejected' => static fn () => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::readerSlotCheckpointAdmissionPlan($base, [array_merge($slots[0], ['checkpoint_frame' => -1])], 12),
    'bad observed database digest rejected' => static fn () => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::readerSlotCheckpointAdmissionPlan($base, [array_merge($slots[0], ['observed_database_digest' => 'short'])], 12),
    'bad observed wal digest rejected' => static fn () => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::readerSlotCheckpointAdmissionPlan($base, [array_merge($slots[0], ['observed_wal_digest' => 'short'])], 12),
    'missing root pages rejected' => static fn () => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::readerSlotCheckpointAdmissionPlan($base, [array_merge($slots[0], ['root_pages' => []])], 12),
    'bad root page rejected' => static fn () => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::readerSlotCheckpointAdmissionPlan($base, [array_merge($slots[0], ['root_pages' => [0]])], 12),
    'bad page digest rejected' => static fn () => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::readerSlotCheckpointAdmissionPlan($base, [array_merge($slots[0], ['observed_page_digests' => [1 => 'short']])], 12),
    'bad hot journal digest rejected' => static fn () => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::readerSlotCheckpointAdmissionPlan($base, [array_merge($slots[0], ['hot_journal_digest' => 'short'])], 12),
];

foreach ($throws as $name => $callback) {
    $tests['wal hot journal savepoint checkpoint current source next208 ' . $name] = static function (TestRunner $t) use ($callback): void {
        $t->throws(Throwable::class, $callback);
    };
}

return $tests;

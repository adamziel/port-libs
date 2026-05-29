<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan;

require_once __DIR__ . '/../src/SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan.php';

$tests = [];

$hash = static fn (string $value): string => hash('sha256', $value);
$databaseDigest = $hash('next232 checkpoint database');
$previousWalDigest = $hash('next232 previous wal');
$currentWalDigest = $hash('next232 current wal');
$schemaCookie = 23277;
$walSalt = '2320abcd2320dcba';

$handlePlan = [
    'status' => 'wal-hot-journal-savepoint-checkpoint-current-source-next229',
    'current_source_admitted' => true,
    'database_path' => '/srv/www/wp-content/database/wp-next232.sqlite',
    'journal_path' => '/srv/www/wp-content/database/wp-next232.sqlite-journal',
    'wal_path' => '/srv/www/wp-content/database/wp-next232.sqlite-wal',
    'source_token' => 'wp-next232-current-source',
    'next_writer_generation' => 232,
    'database_digest' => $databaseDigest,
    'previous_wal_digest' => $previousWalDigest,
    'expected_page_numbers' => [1, 2, 3],
    'covered_page_numbers' => [1, 2, 3],
    'operation_names' => ['admit_checkpoint_current_source_next229'],
    'dependencies' => ['sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next229'],
];

$slot = static function (string $name, array $pages, array $override = []) use ($handlePlan, $databaseDigest, $currentWalDigest, $schemaCookie, $walSalt): array {
    return array_replace([
        'name' => $name,
        'source_token' => $handlePlan['source_token'],
        'generation' => $handlePlan['next_writer_generation'],
        'database_digest' => $databaseDigest,
        'wal_digest' => $currentWalDigest,
        'schema_cookie' => $schemaCookie,
        'wal_salt' => $walSalt,
        'page_numbers' => $pages,
        'read_mark_frame' => 0,
        'lock_receipt' => true,
    ], $override);
};

$slots = [
    $slot('wp-schema-slot', [1]),
    $slot('wp-options-slot', [2]),
    $slot('wp-autoload-slot', [3]),
];

$plan = static fn (): array => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next232AdmitReaderSlots($handlePlan, $slots, $schemaCookie, $walSalt);
$blockedSlot = static fn (array $override, array $pages = [2]): array => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next232AdmitReaderSlots(
    $handlePlan,
    [
        $slots[0],
        $slot('wp-blocked-slot', $pages, $override),
        $slots[2],
    ],
    $schemaCookie,
    $walSalt
);
$missingPages = static function () use ($handlePlan, $slots, $schemaCookie, $walSalt): array {
    $bad = $handlePlan;
    $bad['covered_page_numbers'] = [1, 2];
    return SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next232AdmitReaderSlots($bad, $slots, $schemaCookie, $walSalt);
};

$cases = [
    'status' => [static fn (): mixed => $plan()['status'], 'wal-hot-journal-savepoint-checkpoint-current-source-next232'],
    'reason' => [static fn (): mixed => $plan()['reason'], 'reader_slots_admit_reopened_checkpoint_current_source'],
    'base status' => [static fn (): mixed => $plan()['base_status'], 'wal-hot-journal-savepoint-checkpoint-current-source-next229'],
    'database path' => [static fn (): mixed => $plan()['database_path'], '/srv/www/wp-content/database/wp-next232.sqlite'],
    'journal path' => [static fn (): mixed => $plan()['journal_path'], '/srv/www/wp-content/database/wp-next232.sqlite-journal'],
    'wal path' => [static fn (): mixed => $plan()['wal_path'], '/srv/www/wp-content/database/wp-next232.sqlite-wal'],
    'source token' => [static fn (): mixed => $plan()['source_token'], 'wp-next232-current-source'],
    'writer generation' => [static fn (): mixed => $plan()['next_writer_generation'], 232],
    'database digest' => [static fn (): mixed => $plan()['database_digest'], $databaseDigest],
    'previous wal digest' => [static fn (): mixed => $plan()['previous_wal_digest'], $previousWalDigest],
    'schema cookie' => [static fn (): mixed => $plan()['expected_schema_cookie'], $schemaCookie],
    'wal salt' => [static fn (): mixed => $plan()['expected_wal_salt'], $walSalt],
    'expected pages' => [static fn (): mixed => $plan()['expected_page_numbers'], [1, 2, 3]],
    'covered pages' => [static fn (): mixed => $plan()['covered_page_numbers'], [1, 2, 3]],
    'missing pages empty' => [static fn (): mixed => $plan()['missing_page_numbers'], []],
    'reader slot count' => [static fn (): mixed => $plan()['reader_slot_count'], 3],
    'admitted slot names' => [static fn (): mixed => $plan()['admitted_reader_slot_names'], ['wp-schema-slot', 'wp-options-slot', 'wp-autoload-slot']],
    'blocked slot names empty' => [static fn (): mixed => $plan()['blocked_reader_slot_names'], []],
    'blocked reasons empty' => [static fn (): mixed => $plan()['blocked_reader_slot_reasons'], []],
    'readable' => [static fn (): mixed => $plan()['current_source_readable'], true],
    'reader action' => [static fn (): mixed => $plan()['reader_action'], 'serve_checkpoint_pages_from_reopened_slots'],
    'wal action' => [static fn (): mixed => $plan()['wal_action'], 'allow_restarted_wal_readers'],
    'guard names' => [static fn (): mixed => $plan()['guard_names'], ['next229_handles_admitted', 'checkpoint_pages_still_covered', 'all_reader_slots_current', 'at_least_one_reader_slot_admitted']],
    'guard matches' => [static fn (): mixed => $plan()['guard_matches'], [true, true, true, true]],
    'blocked guards empty' => [static fn (): mixed => $plan()['blocked_guard_names'], []],
    'slot row reason' => [static fn (): mixed => $plan()['reader_slot_rows'][1]['slot_reason'], 'reader_slot_matches_checkpoint_current_source'],
    'slot row pages' => [static fn (): mixed => $plan()['reader_slot_rows'][1]['page_numbers'], [2]],
    'slot row read mark' => [static fn (): mixed => $plan()['reader_slot_rows'][1]['read_mark_frame'], 0],
    'slot row lock receipt' => [static fn (): mixed => $plan()['reader_slot_rows'][1]['lock_receipt'], true],
    'digest length' => [static fn (): mixed => strlen($plan()['reader_slot_digest']), 64],
    'operation inherited' => [static fn (): mixed => in_array('admit_checkpoint_current_source_next229', $plan()['operation_names'], true), true],
    'operation added' => [static fn (): mixed => in_array('admit_reopened_checkpoint_reader_slots_next232', $plan()['operation_names'], true), true],
    'dependency inherited' => [static fn (): mixed => in_array('sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next229', $plan()['dependencies'], true), true],
    'dependency next232' => [static fn (): mixed => in_array('sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next232', $plan()['dependencies'], true), true],
    'dependency wordpress' => [static fn (): mixed => in_array('wordpress-import-hot-journal-checkpoint-reader-slots', $plan()['dependencies'], true), true],
    'dependency closure' => [static fn (): mixed => str_contains($plan()['dependency_closure'], 'no new support component needed'), true],
    'non overlap' => [static fn (): mixed => str_contains($plan()['non_overlap'], 'does not repeat reset publication'), true],
    'source token blocked' => [static fn (): mixed => $blockedSlot(['source_token' => 'old-source'])['blocked_reader_slot_reasons'], ['slot_source_token_mismatch']],
    'generation blocked' => [static fn (): mixed => $blockedSlot(['generation' => 231])['blocked_reader_slot_reasons'], ['slot_generation_mismatch']],
    'database digest blocked' => [static fn (): mixed => $blockedSlot(['database_digest' => $hash('old db')])['blocked_reader_slot_reasons'], ['slot_database_digest_mismatch']],
    'previous wal blocked' => [static fn (): mixed => $blockedSlot(['wal_digest' => $previousWalDigest])['blocked_reader_slot_reasons'], ['slot_reuses_previous_wal_digest']],
    'schema cookie blocked' => [static fn (): mixed => $blockedSlot(['schema_cookie' => 23276])['blocked_reader_slot_reasons'], ['slot_schema_cookie_mismatch']],
    'wal salt blocked' => [static fn (): mixed => $blockedSlot(['wal_salt' => '0000abcd2320dcba'])['blocked_reader_slot_reasons'], ['slot_wal_salt_mismatch']],
    'page outside checkpoint blocked' => [static fn (): mixed => $blockedSlot([], [4])['blocked_reader_slot_reasons'], ['slot_page_not_in_checkpoint_set']],
    'read mark blocked' => [static fn (): mixed => $blockedSlot(['read_mark_frame' => 10])['blocked_reader_slot_reasons'], ['slot_read_mark_not_reset']],
    'hot journal blocked' => [static fn (): mixed => $blockedSlot(['hot_journal_visible' => true])['blocked_reader_slot_reasons'], ['slot_hot_journal_visible']],
    'savepoint open blocked' => [static fn (): mixed => $blockedSlot(['savepoint_depth' => 1])['blocked_reader_slot_reasons'], ['slot_savepoint_scope_open']],
    'dirty cache blocked' => [static fn (): mixed => $blockedSlot(['dirty_cache' => true])['blocked_reader_slot_reasons'], ['slot_dirty_cache']],
    'lock missing blocked' => [static fn (): mixed => $blockedSlot(['lock_receipt' => false])['blocked_reader_slot_reasons'], ['slot_lock_receipt_missing']],
    'combined reasons unique' => [static fn (): mixed => $blockedSlot(['source_token' => 'old-source', 'dirty_cache' => true, 'lock_receipt' => false])['blocked_reader_slot_reasons'], ['slot_source_token_mismatch', 'slot_dirty_cache', 'slot_lock_receipt_missing']],
    'blocked status' => [static fn (): mixed => $blockedSlot(['source_token' => 'old-source'])['status'], 'wal-hot-journal-savepoint-checkpoint-current-source-blocked-next232'],
    'blocked action' => [static fn (): mixed => $blockedSlot(['source_token' => 'old-source'])['reader_action'], 'force_reader_slot_reopen'],
    'blocked guard' => [static fn (): mixed => $blockedSlot(['source_token' => 'old-source'])['blocked_guard_names'], ['all_reader_slots_current']],
    'missing covered pages' => [static fn (): mixed => $missingPages()['missing_page_numbers'], [3]],
    'missing pages guard' => [static fn (): mixed => $missingPages()['blocked_guard_names'], ['checkpoint_pages_still_covered']],
];

foreach ($cases as $name => [$callback, $expected]) {
    $tests['wal hot journal savepoint checkpoint current source next232 ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        $t->same($expected, $callback());
    };
}

$throws = [
    'bad status rejected' => static fn () => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next232AdmitReaderSlots(array_merge($handlePlan, ['status' => 'bad']), $slots, $schemaCookie, $walSalt),
    'not admitted rejected' => static fn () => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next232AdmitReaderSlots(array_merge($handlePlan, ['current_source_admitted' => false]), $slots, $schemaCookie, $walSalt),
    'empty slots rejected' => static fn () => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next232AdmitReaderSlots($handlePlan, [], $schemaCookie, $walSalt),
    'bad schema cookie rejected' => static fn () => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next232AdmitReaderSlots($handlePlan, $slots, 0, $walSalt),
    'bad salt rejected' => static fn () => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next232AdmitReaderSlots($handlePlan, $slots, $schemaCookie, 'short'),
    'bad source token rejected' => static fn () => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next232AdmitReaderSlots(array_merge($handlePlan, ['source_token' => 'bad token']), $slots, $schemaCookie, $walSalt),
    'bad generation rejected' => static fn () => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next232AdmitReaderSlots(array_merge($handlePlan, ['next_writer_generation' => 0]), $slots, $schemaCookie, $walSalt),
    'bad digest rejected' => static fn () => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next232AdmitReaderSlots(array_merge($handlePlan, ['database_digest' => 'short']), $slots, $schemaCookie, $walSalt),
    'bad expected pages rejected' => static fn () => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next232AdmitReaderSlots(array_merge($handlePlan, ['expected_page_numbers' => [0]]), $slots, $schemaCookie, $walSalt),
    'bad slot name rejected' => static fn () => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next232AdmitReaderSlots($handlePlan, [array_merge($slots[0], ['name' => ''])], $schemaCookie, $walSalt),
    'bad slot page rejected' => static fn () => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next232AdmitReaderSlots($handlePlan, [array_merge($slots[0], ['page_numbers' => [0]])], $schemaCookie, $walSalt),
];

foreach ($throws as $name => $callback) {
    $tests['wal hot journal savepoint checkpoint current source next232 ' . $name] = static function (TestRunner $t) use ($callback): void {
        $t->throws(Throwable::class, $callback);
    };
}

return $tests;

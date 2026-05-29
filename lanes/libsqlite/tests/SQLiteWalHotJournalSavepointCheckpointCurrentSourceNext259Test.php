<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan;

require_once __DIR__ . '/../src/SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan.php';

$tests = [];

$hash = static fn (string $value): string => hash('sha256', $value);
$databaseDigest = $hash('next259 post truncate writer generation');
$sealedPlan = [
    'status' => 'wal-hot-journal-savepoint-checkpoint-current-source-next252',
    'post_truncate_source_sealed' => true,
    'database_path' => '/srv/www/wp-content/database/wp-next259.sqlite',
    'wal_path' => '/srv/www/wp-content/database/wp-next259.sqlite-wal',
    'journal_path' => '/srv/www/wp-content/database/wp-next259.sqlite-journal',
    'source_token' => 'wp-next259-current-source',
    'writer_generation' => 259,
    'next_source_generation' => 260,
    'database_digest' => $databaseDigest,
    'released_reader_names' => ['wp-next259-front-page', 'wp-next259-options-import', 'wp-next259-plugin-cache'],
    'covered_page_numbers' => [1, 2, 4, 8, 16, 32],
    'operation_names' => ['verify_post_truncate_source_seal_current_source_next252'],
    'dependencies' => ['sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next252'],
];

$receipt = static function (string $name, string $kind, array $overrides = []) use ($sealedPlan, $databaseDigest): array {
    return array_replace([
        'name' => $name,
        'kind' => $kind,
        'database_path' => $sealedPlan['database_path'],
        'wal_path' => $sealedPlan['wal_path'],
        'journal_path' => $sealedPlan['journal_path'],
        'source_token' => $sealedPlan['source_token'],
        'writer_generation' => $sealedPlan['writer_generation'],
        'source_generation' => $sealedPlan['next_source_generation'],
        'database_digest' => $databaseDigest,
        'wal_salt' => ['salt259a', 'salt259b'],
        'schema_cookie_after' => 260,
        'shm_mx_frame_after' => 0,
        'readmarks_after' => [1],
        'wal_size_before_append' => 32,
        'hot_journal_exists' => false,
        'exclusive_lock_held_until_release' => true,
        'writer_lock_released' => false,
        'durable' => true,
        'io_error' => null,
    ], $overrides);
};

$receipts = [
    $receipt('wp-next259-wal-header', 'wal-header-publish'),
    $receipt('wp-next259-shm-generation', 'shm-generation-publish'),
    $receipt('wp-next259-readmark-reset', 'reader-generation-reset'),
    $receipt('wp-next259-schema-cookie', 'database-header-cookie'),
    $receipt('wp-next259-writer-release', 'writer-lock-release', ['writer_lock_released' => true]),
];

$plan = static fn (?array $inputPlan = null, ?array $inputReceipts = null): array =>
    SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next259AdmitWriterAfterPostTruncateSeal($inputPlan ?? $sealedPlan, $inputReceipts ?? $receipts);
$blocked = static fn (int $index, array $overrides): array => $plan(null, array_map(
    static fn (array $row, int $rowIndex): array => $rowIndex === $index ? array_replace($row, $overrides) : $row,
    $receipts,
    array_keys($receipts)
));

$cases = [
    'status' => [static fn (): mixed => $plan()['status'], 'wal-hot-journal-savepoint-checkpoint-current-source-next259'],
    'reason' => [static fn (): mixed => $plan()['reason'], 'post_truncate_writer_generation_admitted'],
    'base status' => [static fn (): mixed => $plan()['base_status'], 'wal-hot-journal-savepoint-checkpoint-current-source-next252'],
    'database path' => [static fn (): mixed => $plan()['database_path'], $sealedPlan['database_path']],
    'wal path' => [static fn (): mixed => $plan()['wal_path'], $sealedPlan['wal_path']],
    'journal path' => [static fn (): mixed => $plan()['journal_path'], $sealedPlan['journal_path']],
    'source token' => [static fn (): mixed => $plan()['source_token'], $sealedPlan['source_token']],
    'writer generation' => [static fn (): mixed => $plan()['writer_generation'], 259],
    'next source generation' => [static fn (): mixed => $plan()['next_source_generation'], 260],
    'database digest' => [static fn (): mixed => $plan()['database_digest'], $databaseDigest],
    'released readers' => [static fn (): mixed => $plan()['released_reader_names'], ['wp-next259-front-page', 'wp-next259-options-import', 'wp-next259-plugin-cache']],
    'covered pages' => [static fn (): mixed => $plan()['covered_page_numbers'], [1, 2, 4, 8, 16, 32]],
    'receipt names' => [static fn (): mixed => $plan()['writer_receipt_names'], ['wp-next259-wal-header', 'wp-next259-shm-generation', 'wp-next259-readmark-reset', 'wp-next259-schema-cookie', 'wp-next259-writer-release']],
    'duplicates empty' => [static fn (): mixed => $plan()['duplicate_writer_receipt_names'], []],
    'accepted kinds' => [static fn (): mixed => $plan()['accepted_writer_kinds'], ['database-header-cookie', 'reader-generation-reset', 'shm-generation-publish', 'wal-header-publish', 'writer-lock-release']],
    'missing kinds empty' => [static fn (): mixed => $plan()['missing_writer_kinds'], []],
    'operation order' => [static fn (): mixed => $plan()['operation_order'], ['wal-header-publish', 'shm-generation-publish', 'reader-generation-reset', 'database-header-cookie', 'writer-lock-release']],
    'order safe' => [static fn (): mixed => $plan()['operation_order_safe'], true],
    'wal salt' => [static fn (): mixed => $plan()['wal_salts'][0], ['salt259a', 'salt259b']],
    'schema cookie after' => [static fn (): mixed => $plan()['schema_cookies_after'], [260, 260, 260, 260, 260]],
    'shm mx frame reset' => [static fn (): mixed => $plan()['shm_mx_frames_after'], [0]],
    'readmarks reset' => [static fn (): mixed => $plan()['readmarks_after'], [1]],
    'blocked names empty' => [static fn (): mixed => $plan()['blocked_writer_receipt_names'], []],
    'blocked reasons empty' => [static fn (): mixed => $plan()['blocked_reasons'], []],
    'guard names' => [static fn (): mixed => $plan()['guard_names'], ['next252_post_truncate_source_sealed', 'writer_receipt_names_unique', 'required_writer_receipts_present', 'writer_receipt_order_safe', 'fresh_wal_salt_published', 'schema_cookie_advanced', 'shm_and_readmarks_reset', 'all_writer_receipts_current']],
    'guard matches' => [static fn (): mixed => $plan()['guard_matches'], [true, true, true, true, true, true, true, true]],
    'blocked guards empty' => [static fn (): mixed => $plan()['blocked_guard_names'], []],
    'writer admitted' => [static fn (): mixed => $plan()['writer_generation_admitted'], true],
    'writer action' => [static fn (): mixed => $plan()['writer_action'], 'allow_first_writer_on_source_generation_260'],
    'wal action' => [static fn (): mixed => $plan()['wal_action'], 'append_new_frames_after_fresh_wal_header'],
    'reader action' => [static fn (): mixed => $plan()['reader_action'], 'new_readers_use_reset_readmark_generation'],
    'journal action' => [static fn (): mixed => $plan()['journal_action'], 'hot_journal_absence_confirmed_before_writer_release'],
    'digest length' => [static fn (): mixed => strlen($plan()['writer_digest']), 64],
    'operation inherited' => [static fn (): mixed => in_array('verify_post_truncate_source_seal_current_source_next252', $plan()['operation_names'], true), true],
    'operation verify' => [static fn (): mixed => in_array('verify_post_truncate_writer_generation_current_source_next259', $plan()['operation_names'], true), true],
    'operation admit' => [static fn (): mixed => in_array('admit_post_truncate_writer_generation_next259', $plan()['operation_names'], true), true],
    'dependency inherited' => [static fn (): mixed => in_array('sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next252', $plan()['dependencies'], true), true],
    'dependency next259' => [static fn (): mixed => in_array('sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next259', $plan()['dependencies'], true), true],
    'dependency wordpress' => [static fn (): mixed => in_array('wordpress-import-wal-checkpoint-writer-generation', $plan()['dependencies'], true), true],
    'dependency closure' => [static fn (): mixed => str_contains($plan()['dependency_closure'], 'no new support component needed'), true],
    'non overlap' => [static fn (): mixed => str_contains($plan()['non_overlap'], 'does not repeat next252 sealing'), true],
    'first row accepted' => [static fn (): mixed => $plan()['writer_rows'][0]['accepted'], true],
    'first row reason' => [static fn (): mixed => $plan()['writer_rows'][0]['acceptance_reason'], 'post_truncate_writer_generation_current'],
    'first row kind' => [static fn (): mixed => $plan()['writer_rows'][0]['kind'], 'wal-header-publish'],
    'first row wal size' => [static fn (): mixed => $plan()['writer_rows'][0]['wal_size_before_append'], 32],
    'release row lock released' => [static fn (): mixed => $plan()['writer_rows'][4]['writer_lock_released'], true],
    'blocked status' => [static fn (): mixed => $blocked(0, ['wal_salt' => null])['status'], 'wal-hot-journal-savepoint-checkpoint-current-source-blocked-next259'],
    'blocked writer action' => [static fn (): mixed => $blocked(0, ['wal_salt' => null])['writer_action'], 'hold_writer_until_post_truncate_fences_match'],
    'blocked wal action' => [static fn (): mixed => $blocked(0, ['wal_salt' => null])['wal_action'], 'keep_wal_empty_after_checkpoint_reset'],
    'blocked reader action' => [static fn (): mixed => $blocked(2, ['readmarks_after' => [1, 2]])['reader_action'], 'keep_readers_on_sealed_checkpoint_generation'],
    'blocked journal action' => [static fn (): mixed => $blocked(4, ['hot_journal_exists' => true])['journal_action'], 'retain_hot_journal_absence_guard'],
    'database path block' => [static fn (): mixed => $blocked(0, ['database_path' => '/tmp/other.sqlite'])['writer_rows'][0]['blocked_reasons'], ['writer_generation_database_path_mismatch']],
    'wal path block' => [static fn (): mixed => $blocked(0, ['wal_path' => '/tmp/other.sqlite-wal'])['writer_rows'][0]['blocked_reasons'], ['writer_generation_wal_path_mismatch']],
    'journal path block' => [static fn (): mixed => $blocked(0, ['journal_path' => '/tmp/other.sqlite-journal'])['writer_rows'][0]['blocked_reasons'], ['writer_generation_journal_path_mismatch']],
    'source token block' => [static fn (): mixed => $blocked(0, ['source_token' => 'old-source'])['writer_rows'][0]['blocked_reasons'], ['writer_generation_source_token_mismatch']],
    'writer generation block' => [static fn (): mixed => $blocked(0, ['writer_generation' => 258])['writer_rows'][0]['blocked_reasons'], ['writer_generation_writer_generation_mismatch']],
    'source generation block' => [static fn (): mixed => $blocked(0, ['source_generation' => 259])['writer_rows'][0]['blocked_reasons'], ['writer_generation_source_generation_mismatch']],
    'digest block' => [static fn (): mixed => $blocked(0, ['database_digest' => $hash('stale next259 image')])['writer_rows'][0]['blocked_reasons'], ['writer_generation_database_digest_mismatch']],
    'wal salt block' => [static fn (): mixed => $blocked(0, ['wal_salt' => null])['writer_rows'][0]['blocked_reasons'], ['writer_generation_wal_header_not_fresh']],
    'wal size block' => [static fn (): mixed => $blocked(0, ['wal_size_before_append' => 0])['writer_rows'][0]['blocked_reasons'], ['writer_generation_wal_header_not_fresh']],
    'shm block' => [static fn (): mixed => $blocked(1, ['shm_mx_frame_after' => 3])['writer_rows'][1]['blocked_reasons'], ['writer_generation_shm_mxframe_not_reset']],
    'readmark block' => [static fn (): mixed => $blocked(2, ['readmarks_after' => [1, 2]])['writer_rows'][2]['blocked_reasons'], ['writer_generation_readmarks_not_reset']],
    'schema cookie block' => [static fn (): mixed => $blocked(3, ['schema_cookie_after' => 259])['writer_rows'][3]['blocked_reasons'], ['writer_generation_schema_cookie_not_advanced']],
    'lock release block' => [static fn (): mixed => $blocked(4, ['writer_lock_released' => false])['writer_rows'][4]['blocked_reasons'], ['writer_generation_lock_not_released']],
    'hot journal block' => [static fn (): mixed => $blocked(4, ['hot_journal_exists' => true])['writer_rows'][4]['blocked_reasons'], ['writer_generation_hot_journal_still_exists']],
    'exclusive lock block' => [static fn (): mixed => $blocked(0, ['exclusive_lock_held_until_release' => false])['writer_rows'][0]['blocked_reasons'], ['writer_generation_exclusive_lock_missing']],
    'durable block' => [static fn (): mixed => $blocked(0, ['durable' => false])['writer_rows'][0]['blocked_reasons'], ['writer_generation_receipt_not_durable']],
    'io block' => [static fn (): mixed => $blocked(0, ['io_error' => 'SQLITE_IOERR_WRITE'])['writer_rows'][0]['blocked_reasons'], ['writer_generation_io_error']],
    'combined block' => [static fn (): mixed => $blocked(0, ['wal_salt' => null, 'durable' => false, 'exclusive_lock_held_until_release' => false])['writer_rows'][0]['blocked_reasons'], ['writer_generation_wal_header_not_fresh', 'writer_generation_exclusive_lock_missing', 'writer_generation_receipt_not_durable']],
    'missing kind' => [static fn (): mixed => $plan(null, [$receipts[0], $receipts[1], $receipts[2], $receipts[3]])['missing_writer_kinds'], ['writer-lock-release']],
    'missing kind guard' => [static fn (): mixed => in_array('required_writer_receipts_present', $plan(null, [$receipts[0], $receipts[1], $receipts[2], $receipts[3]])['blocked_guard_names'], true), true],
    'duplicate receipt name' => [static fn (): mixed => $plan(null, [$receipts[0], array_replace($receipts[1], ['name' => $receipts[0]['name']]), $receipts[2], $receipts[3], $receipts[4]])['duplicate_writer_receipt_names'], ['wp-next259-wal-header']],
    'duplicate reason' => [static fn (): mixed => in_array('writer_generation_receipt_name_duplicate:wp-next259-wal-header', $plan(null, [$receipts[0], array_replace($receipts[1], ['name' => $receipts[0]['name']]), $receipts[2], $receipts[3], $receipts[4]])['blocked_reasons'], true), true],
    'unsafe order guard' => [static fn (): mixed => in_array('writer_receipt_order_safe', $plan(null, [$receipts[3], $receipts[0], $receipts[1], $receipts[2], $receipts[4]])['blocked_guard_names'], true), true],
    'readmark current receipt guard' => [static fn (): mixed => in_array('all_writer_receipts_current', $blocked(2, ['readmarks_after' => [1, 2]])['blocked_guard_names'], true), true],
];

foreach ($cases as $name => [$callback, $expected]) {
    $tests['wal hot journal savepoint checkpoint current source next259 ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        $t->same($expected, $callback());
    };
}

$throws = [
    'bad base status rejected' => static fn () => $plan(array_replace($sealedPlan, ['status' => 'bad'])),
    'not sealed rejected' => static fn () => $plan(array_replace($sealedPlan, ['post_truncate_source_sealed' => false])),
    'empty receipts rejected' => static fn () => $plan(null, []),
    'bad database path rejected' => static fn () => $plan(array_replace($sealedPlan, ['database_path' => ''])),
    'bad source token rejected' => static fn () => $plan(array_replace($sealedPlan, ['source_token' => 'bad token'])),
    'bad writer generation rejected' => static fn () => $plan(array_replace($sealedPlan, ['writer_generation' => 0])),
    'bad next generation rejected' => static fn () => $plan(array_replace($sealedPlan, ['next_source_generation' => 0])),
    'bad digest rejected' => static fn () => $plan(array_replace($sealedPlan, ['database_digest' => 'short'])),
    'bad readers rejected' => static fn () => $plan(array_replace($sealedPlan, ['released_reader_names' => []])),
    'bad covered pages rejected' => static fn () => $plan(array_replace($sealedPlan, ['covered_page_numbers' => [0]])),
    'bad receipt name rejected' => static fn () => $plan(null, [array_replace($receipts[0], ['name' => 'bad name'])]),
    'bad receipt kind rejected' => static fn () => $plan(null, [array_replace($receipts[0], ['kind' => 'bad-kind'])]),
    'bad receipt source generation rejected' => static fn () => $plan(null, [array_replace($receipts[0], ['source_generation' => 0])]),
    'bad receipt digest rejected' => static fn () => $plan(null, [array_replace($receipts[0], ['database_digest' => 'short'])]),
    'bad receipt salt rejected' => static fn () => $plan(null, [array_replace($receipts[0], ['wal_salt' => ['only-one']])]),
    'bad schema cookie rejected' => static fn () => $plan(null, [array_replace($receipts[3], ['schema_cookie_after' => -1])]),
    'bad shm frame rejected' => static fn () => $plan(null, [array_replace($receipts[1], ['shm_mx_frame_after' => -1])]),
    'bad readmark rejected' => static fn () => $plan(null, [array_replace($receipts[2], ['readmarks_after' => [0]])]),
    'bad wal size rejected' => static fn () => $plan(null, [array_replace($receipts[0], ['wal_size_before_append' => -1])]),
];

foreach ($throws as $name => $callback) {
    $tests['wal hot journal savepoint checkpoint current source next259 ' . $name] = static function (TestRunner $t) use ($callback): void {
        $t->throws(Throwable::class, $callback);
    };
}

return $tests;

<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan;

require_once __DIR__ . '/../src/SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan.php';

$tests = [];

$hash = static fn (string $value): string => hash('sha256', $value);
$databaseDigest = $hash('next252 post truncate current source');
$truncationPlan = [
    'status' => 'wal-hot-journal-savepoint-checkpoint-current-source-next248',
    'checkpoint_truncation_admitted' => true,
    'database_path' => '/srv/www/wp-content/database/wp-next252.sqlite',
    'wal_path' => '/srv/www/wp-content/database/wp-next252.sqlite-wal',
    'journal_path' => '/srv/www/wp-content/database/wp-next252.sqlite-journal',
    'source_token' => 'wp-next252-current-source',
    'writer_generation' => 252,
    'next_source_generation' => 253,
    'database_digest' => $databaseDigest,
    'covered_page_numbers' => [1, 2, 3, 4, 5, 8, 13, 21],
    'released_reader_names' => ['wp-next252-front-page', 'wp-next252-options-import', 'wp-next252-plugin-cache'],
    'operation_names' => ['truncate_checkpoint_wal_current_source_next248'],
    'dependencies' => ['sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next248'],
];

$seal = static function (string $name, string $kind, array $overrides = []) use ($truncationPlan, $databaseDigest): array {
    return array_replace([
        'name' => $name,
        'kind' => $kind,
        'database_path' => $truncationPlan['database_path'],
        'wal_path' => $truncationPlan['wal_path'],
        'journal_path' => $truncationPlan['journal_path'],
        'source_token' => $truncationPlan['source_token'],
        'writer_generation' => $truncationPlan['writer_generation'],
        'source_generation' => $truncationPlan['next_source_generation'],
        'database_digest' => $databaseDigest,
        'reader_names' => $truncationPlan['released_reader_names'],
        'page_numbers' => $truncationPlan['covered_page_numbers'],
        'wal_size_after' => 0,
        'shm_mx_frame_after' => 0,
        'readmarks_after' => [1],
        'journal_exists_after' => false,
        'directory_synced' => true,
        'durable' => true,
        'exclusive_lock_held' => true,
        'pending_savepoint_depth' => 0,
    ], $overrides);
};

$seals = [
    $seal('wp-next252-wal-truncate', 'wal-truncate'),
    $seal('wp-next252-shm-reset', 'shm-reset'),
    $seal('wp-next252-readmark-reset', 'readmark-reset'),
    $seal('wp-next252-journal-unlink', 'journal-unlink'),
    $seal('wp-next252-directory-sync', 'directory-sync'),
];

$plan = static fn (?array $inputPlan = null, ?array $inputSeals = null): array =>
    SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next252SealPostTruncateSource($inputPlan ?? $truncationPlan, $inputSeals ?? $seals);
$blocked = static fn (int $index, array $overrides): array => $plan(null, array_map(
    static fn (array $row, int $rowIndex): array => $rowIndex === $index ? array_replace($row, $overrides) : $row,
    $seals,
    array_keys($seals)
));

$cases = [
    'status' => [static fn (): mixed => $plan()['status'], 'wal-hot-journal-savepoint-checkpoint-current-source-next252'],
    'reason' => [static fn (): mixed => $plan()['reason'], 'post_truncate_current_source_sealed_after_reader_release'],
    'base status' => [static fn (): mixed => $plan()['base_status'], 'wal-hot-journal-savepoint-checkpoint-current-source-next248'],
    'database path' => [static fn (): mixed => $plan()['database_path'], $truncationPlan['database_path']],
    'wal path' => [static fn (): mixed => $plan()['wal_path'], $truncationPlan['wal_path']],
    'journal path' => [static fn (): mixed => $plan()['journal_path'], $truncationPlan['journal_path']],
    'source token' => [static fn (): mixed => $plan()['source_token'], $truncationPlan['source_token']],
    'writer generation' => [static fn (): mixed => $plan()['writer_generation'], 252],
    'next source generation' => [static fn (): mixed => $plan()['next_source_generation'], 253],
    'database digest' => [static fn (): mixed => $plan()['database_digest'], $databaseDigest],
    'reader names sorted' => [static fn (): mixed => $plan()['released_reader_names'], ['wp-next252-front-page', 'wp-next252-options-import', 'wp-next252-plugin-cache']],
    'covered pages' => [static fn (): mixed => $plan()['covered_page_numbers'], [1, 2, 3, 4, 5, 8, 13, 21]],
    'seal receipt names' => [static fn (): mixed => $plan()['seal_receipt_names'], ['wp-next252-wal-truncate', 'wp-next252-shm-reset', 'wp-next252-readmark-reset', 'wp-next252-journal-unlink', 'wp-next252-directory-sync']],
    'duplicate names empty' => [static fn (): mixed => $plan()['duplicate_seal_names'], []],
    'accepted kinds' => [static fn (): mixed => $plan()['accepted_seal_kinds'], ['directory-sync', 'journal-unlink', 'readmark-reset', 'shm-reset', 'wal-truncate']],
    'missing kinds empty' => [static fn (): mixed => $plan()['missing_seal_kinds'], []],
    'covered readers' => [static fn (): mixed => $plan()['covered_reader_names'], ['wp-next252-front-page', 'wp-next252-options-import', 'wp-next252-plugin-cache']],
    'missing readers empty' => [static fn (): mixed => $plan()['missing_reader_names'], []],
    'sealed pages' => [static fn (): mixed => $plan()['sealed_page_numbers'], [1, 2, 3, 4, 5, 8, 13, 21]],
    'missing pages empty' => [static fn (): mixed => $plan()['missing_page_numbers'], []],
    'operation order' => [static fn (): mixed => $plan()['operation_order'], ['wal-truncate', 'shm-reset', 'readmark-reset', 'journal-unlink', 'directory-sync']],
    'blocked seal names empty' => [static fn (): mixed => $plan()['blocked_seal_names'], []],
    'blocked reasons empty' => [static fn (): mixed => $plan()['blocked_reasons'], []],
    'guard names' => [static fn (): mixed => $plan()['guard_names'], ['next248_truncation_admitted', 'seal_receipt_names_unique', 'required_seal_kinds_present', 'released_readers_sealed', 'checkpoint_pages_sealed', 'seal_order_is_durable', 'all_seal_receipts_current']],
    'guard matches' => [static fn (): mixed => $plan()['guard_matches'], [true, true, true, true, true, true, true]],
    'blocked guards empty' => [static fn (): mixed => $plan()['blocked_guard_names'], []],
    'source sealed' => [static fn (): mixed => $plan()['post_truncate_source_sealed'], true],
    'source action' => [static fn (): mixed => $plan()['source_action'], 'advance_current_source_generation_253'],
    'wal action' => [static fn (): mixed => $plan()['wal_action'], 'keep_checkpoint_wal_truncated'],
    'journal action' => [static fn (): mixed => $plan()['journal_action'], 'hot_journal_unlink_committed'],
    'shm action' => [static fn (): mixed => $plan()['shm_action'], 'publish_zeroed_checkpoint_shm_readmarks'],
    'digest length' => [static fn (): mixed => strlen($plan()['seal_digest']), 64],
    'operation inherited' => [static fn (): mixed => in_array('truncate_checkpoint_wal_current_source_next248', $plan()['operation_names'], true), true],
    'operation verify' => [static fn (): mixed => in_array('verify_post_truncate_source_seal_current_source_next252', $plan()['operation_names'], true), true],
    'operation advance' => [static fn (): mixed => in_array('advance_checkpoint_current_source_next252', $plan()['operation_names'], true), true],
    'dependency inherited' => [static fn (): mixed => in_array('sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next248', $plan()['dependencies'], true), true],
    'dependency next252' => [static fn (): mixed => in_array('sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next252', $plan()['dependencies'], true), true],
    'dependency wordpress' => [static fn (): mixed => in_array('wordpress-import-current-source-after-wal-truncate', $plan()['dependencies'], true), true],
    'dependency closure' => [static fn (): mixed => str_contains($plan()['dependency_closure'], 'no new support component needed'), true],
    'non overlap' => [static fn (): mixed => str_contains($plan()['non_overlap'], 'does not repeat next248 release/truncate admission'), true],
    'first row accepted' => [static fn (): mixed => $plan()['seal_rows'][0]['accepted'], true],
    'first row reason' => [static fn (): mixed => $plan()['seal_rows'][0]['acceptance_reason'], 'post_truncate_seal_current'],
    'first row kind' => [static fn (): mixed => $plan()['seal_rows'][0]['kind'], 'wal-truncate'],
    'first row wal size' => [static fn (): mixed => $plan()['seal_rows'][0]['wal_size_after'], 0],
    'second row shm frame' => [static fn (): mixed => $plan()['seal_rows'][1]['shm_mx_frame_after'], 0],
    'blocked status' => [static fn (): mixed => $blocked(0, ['wal_size_after' => 4096])['status'], 'wal-hot-journal-savepoint-checkpoint-current-source-blocked-next252'],
    'blocked source action' => [static fn (): mixed => $blocked(0, ['wal_size_after' => 4096])['source_action'], 'retain_previous_current_source_until_sealed'],
    'blocked wal action' => [static fn (): mixed => $blocked(0, ['wal_size_after' => 4096])['wal_action'], 'retain_truncated_wal_guard_until_sealed'],
    'blocked journal action' => [static fn (): mixed => $blocked(0, ['wal_size_after' => 4096])['journal_action'], 'hold_hot_journal_unlink_receipt'],
    'blocked shm action' => [static fn (): mixed => $blocked(1, ['shm_mx_frame_after' => 5])['shm_action'], 'preserve_prior_shm_readmarks'],
    'database path block' => [static fn (): mixed => $blocked(0, ['database_path' => '/tmp/other.sqlite'])['seal_rows'][0]['blocked_reasons'], ['post_truncate_database_path_mismatch']],
    'wal path block' => [static fn (): mixed => $blocked(0, ['wal_path' => '/tmp/other.sqlite-wal'])['seal_rows'][0]['blocked_reasons'], ['post_truncate_wal_path_mismatch']],
    'journal path block' => [static fn (): mixed => $blocked(0, ['journal_path' => '/tmp/other.sqlite-journal'])['seal_rows'][0]['blocked_reasons'], ['post_truncate_journal_path_mismatch']],
    'source token block' => [static fn (): mixed => $blocked(0, ['source_token' => 'old-source'])['seal_rows'][0]['blocked_reasons'], ['post_truncate_source_token_mismatch']],
    'writer generation block' => [static fn (): mixed => $blocked(0, ['writer_generation' => 251])['seal_rows'][0]['blocked_reasons'], ['post_truncate_writer_generation_mismatch']],
    'source generation block' => [static fn (): mixed => $blocked(0, ['source_generation' => 252])['seal_rows'][0]['blocked_reasons'], ['post_truncate_source_generation_mismatch']],
    'digest block' => [static fn (): mixed => $blocked(0, ['database_digest' => $hash('stale image')])['seal_rows'][0]['blocked_reasons'], ['post_truncate_database_digest_mismatch']],
    'reader block' => [static fn (): mixed => $blocked(0, ['reader_names' => ['wp-next252-front-page', 'wp-next252-unknown']])['seal_rows'][0]['blocked_reasons'], ['post_truncate_reader_not_released']],
    'page block' => [static fn (): mixed => $blocked(0, ['page_numbers' => [1, 7]])['seal_rows'][0]['blocked_reasons'], ['post_truncate_page_not_checkpoint_covered']],
    'wal size block' => [static fn (): mixed => $blocked(0, ['wal_size_after' => 24])['seal_rows'][0]['blocked_reasons'], ['post_truncate_wal_not_empty']],
    'shm mx frame block' => [static fn (): mixed => $blocked(1, ['shm_mx_frame_after' => 1])['seal_rows'][1]['blocked_reasons'], ['post_truncate_shm_mxframe_not_reset']],
    'readmark block' => [static fn (): mixed => $blocked(2, ['readmarks_after' => [1, 3]])['seal_rows'][2]['blocked_reasons'], ['post_truncate_readmarks_not_reset']],
    'journal exists block' => [static fn (): mixed => $blocked(3, ['journal_exists_after' => true])['seal_rows'][3]['blocked_reasons'], ['post_truncate_hot_journal_still_exists']],
    'directory sync block' => [static fn (): mixed => $blocked(4, ['directory_synced' => false])['seal_rows'][4]['blocked_reasons'], ['post_truncate_directory_not_synced']],
    'durable block' => [static fn (): mixed => $blocked(0, ['durable' => false])['seal_rows'][0]['blocked_reasons'], ['post_truncate_receipt_not_durable']],
    'exclusive lock block' => [static fn (): mixed => $blocked(0, ['exclusive_lock_held' => false])['seal_rows'][0]['blocked_reasons'], ['post_truncate_exclusive_lock_missing']],
    'savepoint block' => [static fn (): mixed => $blocked(0, ['pending_savepoint_depth' => 1])['seal_rows'][0]['blocked_reasons'], ['post_truncate_savepoint_scope_open']],
    'combined block' => [static fn (): mixed => $blocked(0, ['wal_size_after' => 24, 'durable' => false, 'exclusive_lock_held' => false])['seal_rows'][0]['blocked_reasons'], ['post_truncate_wal_not_empty', 'post_truncate_receipt_not_durable', 'post_truncate_exclusive_lock_missing']],
    'missing kind' => [static fn (): mixed => $plan(null, [$seals[0], $seals[1], $seals[2], $seals[3]])['missing_seal_kinds'], ['directory-sync']],
    'missing kind guard' => [static fn (): mixed => in_array('required_seal_kinds_present', $plan(null, [$seals[0], $seals[1], $seals[2], $seals[3]])['blocked_guard_names'], true), true],
    'duplicate seal name' => [static fn (): mixed => $plan(null, [$seals[0], array_replace($seals[1], ['name' => $seals[0]['name']]), $seals[2], $seals[3], $seals[4]])['duplicate_seal_names'], ['wp-next252-wal-truncate']],
    'duplicate seal reason' => [static fn (): mixed => in_array('post_truncate_seal_name_duplicate:wp-next252-wal-truncate', $plan(null, [$seals[0], array_replace($seals[1], ['name' => $seals[0]['name']]), $seals[2], $seals[3], $seals[4]])['blocked_reasons'], true), true],
    'missing reader coverage' => [static fn (): mixed => $plan(null, array_map(static fn (array $row): array => array_replace($row, ['reader_names' => ['wp-next252-front-page']]), $seals))['missing_reader_names'], ['wp-next252-options-import', 'wp-next252-plugin-cache']],
    'missing page coverage' => [static fn (): mixed => $plan(null, array_map(static fn (array $row): array => array_replace($row, ['page_numbers' => [1, 2]]), $seals))['missing_page_numbers'], [3, 4, 5, 8, 13, 21]],
    'unsafe order guard' => [static fn (): mixed => in_array('seal_order_is_durable', $plan(null, [$seals[3], $seals[0], $seals[1], $seals[2], $seals[4]])['blocked_guard_names'], true), true],
];

foreach ($cases as $name => [$callback, $expected]) {
    $tests['wal hot journal savepoint checkpoint current source next252 ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        $t->same($expected, $callback());
    };
}

$throws = [
    'bad base status rejected' => static fn () => $plan(array_replace($truncationPlan, ['status' => 'bad'])),
    'not admitted rejected' => static fn () => $plan(array_replace($truncationPlan, ['checkpoint_truncation_admitted' => false])),
    'empty seals rejected' => static fn () => $plan(null, []),
    'bad database path rejected' => static fn () => $plan(array_replace($truncationPlan, ['database_path' => ''])),
    'bad source token rejected' => static fn () => $plan(array_replace($truncationPlan, ['source_token' => 'bad token'])),
    'bad writer generation rejected' => static fn () => $plan(array_replace($truncationPlan, ['writer_generation' => 0])),
    'bad next generation rejected' => static fn () => $plan(array_replace($truncationPlan, ['next_source_generation' => 0])),
    'bad digest rejected' => static fn () => $plan(array_replace($truncationPlan, ['database_digest' => 'short'])),
    'bad readers rejected' => static fn () => $plan(array_replace($truncationPlan, ['released_reader_names' => []])),
    'bad covered pages rejected' => static fn () => $plan(array_replace($truncationPlan, ['covered_page_numbers' => [0]])),
    'bad receipt name rejected' => static fn () => $plan(null, [array_replace($seals[0], ['name' => 'bad name'])]),
    'bad receipt kind rejected' => static fn () => $plan(null, [array_replace($seals[0], ['kind' => 'bad-kind'])]),
    'bad receipt source generation rejected' => static fn () => $plan(null, [array_replace($seals[0], ['source_generation' => 0])]),
    'bad receipt digest rejected' => static fn () => $plan(null, [array_replace($seals[0], ['database_digest' => 'short'])]),
    'bad receipt readers rejected' => static fn () => $plan(null, [array_replace($seals[0], ['reader_names' => []])]),
    'bad receipt pages rejected' => static fn () => $plan(null, [array_replace($seals[0], ['page_numbers' => [0]])]),
    'bad wal size rejected' => static fn () => $plan(null, [array_replace($seals[0], ['wal_size_after' => -1])]),
    'bad shm frame rejected' => static fn () => $plan(null, [array_replace($seals[0], ['shm_mx_frame_after' => -1])]),
    'bad readmark rejected' => static fn () => $plan(null, [array_replace($seals[2], ['readmarks_after' => [0]])]),
];

foreach ($throws as $name => $callback) {
    $tests['wal hot journal savepoint checkpoint current source next252 ' . $name] = static function (TestRunner $t) use ($callback): void {
        $t->throws(Throwable::class, $callback);
    };
}

return $tests;

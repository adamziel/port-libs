<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan;

require_once __DIR__ . '/../src/SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan.php';

$tests = [];

$hash = static fn (string $value): string => hash('sha256', $value);
$databaseDigest = $hash('next248 released reader checkpoint image');
$admissionPlan = [
    'status' => 'wal-hot-journal-savepoint-checkpoint-current-source-next245',
    'readers_admitted' => true,
    'database_path' => '/srv/www/wp-content/database/wp-next248.sqlite',
    'wal_path' => '/srv/www/wp-content/database/wp-next248.sqlite-wal',
    'journal_path' => '/srv/www/wp-content/database/wp-next248.sqlite-journal',
    'source_token' => 'wp-next248-current-source',
    'writer_generation' => 248,
    'next_source_generation' => 249,
    'database_digest' => $databaseDigest,
    'covered_page_numbers' => [1, 2, 3, 4, 5, 6, 8, 13],
    'accepted_reader_names' => ['wp-next248-front-page', 'wp-next248-options-import', 'wp-next248-plugin-cache'],
    'operation_names' => ['admit_reopened_reader_cache_current_source_next245'],
    'dependencies' => ['sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next245'],
];

$release = static function (string $name, array $overrides = []) use ($admissionPlan, $databaseDigest): array {
    return array_replace([
        'name' => $name,
        'database_path' => $admissionPlan['database_path'],
        'wal_path' => $admissionPlan['wal_path'],
        'journal_path' => $admissionPlan['journal_path'],
        'source_token' => $admissionPlan['source_token'],
        'writer_generation' => $admissionPlan['writer_generation'],
        'reader_generation' => $admissionPlan['next_source_generation'],
        'database_digest' => $databaseDigest,
        'last_visible_frame' => 5,
        'release_frame' => 6,
        'page_numbers' => [1, 2, 5],
        'snapshot_closed' => true,
        'page_cache_clean' => true,
        'shared_lock_released' => true,
        'reserved_lock_held' => false,
        'hot_journal_visible' => false,
        'savepoint_depth' => 0,
    ], $overrides);
};

$releases = [
    $release('wp-next248-front-page', ['page_numbers' => [1, 2, 3], 'last_visible_frame' => 3, 'release_frame' => 4]),
    $release('wp-next248-options-import', ['page_numbers' => [2, 5, 6], 'last_visible_frame' => 5, 'release_frame' => 6]),
    $release('wp-next248-plugin-cache', ['page_numbers' => [1, 4, 13], 'reader_generation' => 250, 'last_visible_frame' => 8, 'release_frame' => 8]),
];

$plan = static fn (?array $inputPlan = null, ?array $inputReleases = null): array =>
    SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next248PlanCheckpointTruncation($inputPlan ?? $admissionPlan, $inputReleases ?? $releases);
$blocked = static fn (int $index, array $overrides): array => $plan(null, array_map(
    static fn (array $row, int $rowIndex): array => $rowIndex === $index ? array_replace($row, $overrides) : $row,
    $releases,
    array_keys($releases)
));

$cases = [
    'status' => [static fn (): mixed => $plan()['status'], 'wal-hot-journal-savepoint-checkpoint-current-source-next248'],
    'reason' => [static fn (): mixed => $plan()['reason'], 'checkpoint_wal_can_truncate_after_all_reopened_readers_release'],
    'base status' => [static fn (): mixed => $plan()['base_status'], 'wal-hot-journal-savepoint-checkpoint-current-source-next245'],
    'database path' => [static fn (): mixed => $plan()['database_path'], $admissionPlan['database_path']],
    'wal path' => [static fn (): mixed => $plan()['wal_path'], $admissionPlan['wal_path']],
    'journal path' => [static fn (): mixed => $plan()['journal_path'], $admissionPlan['journal_path']],
    'source token' => [static fn (): mixed => $plan()['source_token'], $admissionPlan['source_token']],
    'writer generation' => [static fn (): mixed => $plan()['writer_generation'], 248],
    'next source generation' => [static fn (): mixed => $plan()['next_source_generation'], 249],
    'database digest' => [static fn (): mixed => $plan()['database_digest'], $databaseDigest],
    'covered pages' => [static fn (): mixed => $plan()['covered_page_numbers'], [1, 2, 3, 4, 5, 6, 8, 13]],
    'expected readers sorted' => [static fn (): mixed => $plan()['expected_reader_names'], ['wp-next248-front-page', 'wp-next248-options-import', 'wp-next248-plugin-cache']],
    'release names' => [static fn (): mixed => $plan()['release_reader_names'], ['wp-next248-front-page', 'wp-next248-options-import', 'wp-next248-plugin-cache']],
    'duplicate releases empty' => [static fn (): mixed => $plan()['duplicate_release_names'], []],
    'missing releases empty' => [static fn (): mixed => $plan()['missing_release_names'], []],
    'unexpected releases empty' => [static fn (): mixed => $plan()['unexpected_release_names'], []],
    'released names' => [static fn (): mixed => $plan()['released_reader_names'], ['wp-next248-front-page', 'wp-next248-options-import', 'wp-next248-plugin-cache']],
    'blocked names empty' => [static fn (): mixed => $plan()['blocked_reader_names'], []],
    'blocked reasons empty' => [static fn (): mixed => $plan()['blocked_reasons'], []],
    'guard names' => [static fn (): mixed => $plan()['guard_names'], ['next245_readers_admitted', 'release_receipt_names_unique', 'all_admitted_readers_released', 'no_unexpected_reader_release', 'release_tokens_match_current_source', 'release_snapshots_match_database_digest', 'release_generations_follow_committed_writer', 'release_page_cache_is_checkpoint_covered', 'release_locks_and_hot_journal_fences_clear', 'all_reader_releases_current']],
    'guard matches' => [static fn (): mixed => $plan()['guard_matches'], [true, true, true, true, true, true, true, true, true, true]],
    'blocked guards empty' => [static fn (): mixed => $plan()['blocked_guard_names'], []],
    'truncation admitted' => [static fn (): mixed => $plan()['checkpoint_truncation_admitted'], true],
    'reader action' => [static fn (): mixed => $plan()['reader_action'], 'close_reopened_reader_generation_249'],
    'wal action' => [static fn (): mixed => $plan()['wal_action'], 'truncate_checkpoint_wal_after_reader_release'],
    'journal action' => [static fn (): mixed => $plan()['journal_action'], 'delete_hot_journal_fence_after_release'],
    'cache action' => [static fn (): mixed => $plan()['cache_action'], 'evict_released_checkpoint_page_cache'],
    'digest length' => [static fn (): mixed => strlen($plan()['release_digest']), 64],
    'operation inherited' => [static fn (): mixed => in_array('admit_reopened_reader_cache_current_source_next245', $plan()['operation_names'], true), true],
    'operation verify' => [static fn (): mixed => in_array('verify_reopened_reader_release_current_source_next248', $plan()['operation_names'], true), true],
    'operation truncate' => [static fn (): mixed => in_array('truncate_checkpoint_wal_current_source_next248', $plan()['operation_names'], true), true],
    'dependency inherited' => [static fn (): mixed => in_array('sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next245', $plan()['dependencies'], true), true],
    'dependency next248' => [static fn (): mixed => in_array('sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next248', $plan()['dependencies'], true), true],
    'dependency application' => [static fn (): mixed => in_array('application-import-reader-release-before-wal-truncate', $plan()['dependencies'], true), true],
    'dependency closure' => [static fn (): mixed => str_contains($plan()['dependency_closure'], 'no new support component needed'), true],
    'non overlap' => [static fn (): mixed => str_contains($plan()['non_overlap'], 'does not repeat next245 reopened-reader admission'), true],
    'first row released' => [static fn (): mixed => $plan()['release_rows'][0]['released'], true],
    'first row reason' => [static fn (): mixed => $plan()['release_rows'][0]['release_reason'], 'reader_release_current'],
    'first row frames' => [static fn (): mixed => [$plan()['release_rows'][0]['last_visible_frame'], $plan()['release_rows'][0]['release_frame']], [3, 4]],
    'first row generation safe' => [static fn (): mixed => $plan()['release_rows'][0]['generation_safe'], true],
    'first row source match' => [static fn (): mixed => $plan()['release_rows'][0]['source_token_match'], true],
    'first row digest match' => [static fn (): mixed => $plan()['release_rows'][0]['database_digest_match'], true],
    'first row page covered' => [static fn (): mixed => $plan()['release_rows'][0]['page_cache_covered'], true],
    'first row fences clear' => [static fn (): mixed => $plan()['release_rows'][0]['fences_clear'], true],
    'third reader generation' => [static fn (): mixed => $plan()['release_rows'][2]['reader_generation'], 250],
    'blocked status' => [static fn (): mixed => $blocked(0, ['snapshot_closed' => false])['status'], 'wal-hot-journal-savepoint-checkpoint-current-source-blocked-next248'],
    'blocked reader action' => [static fn (): mixed => $blocked(0, ['snapshot_closed' => false])['reader_action'], 'wait_for_reopened_reader_release'],
    'blocked wal action' => [static fn (): mixed => $blocked(0, ['snapshot_closed' => false])['wal_action'], 'retain_checkpoint_wal_for_open_readers'],
    'blocked journal action' => [static fn (): mixed => $blocked(0, ['snapshot_closed' => false])['journal_action'], 'preserve_hot_journal_release_fence'],
    'blocked cache action' => [static fn (): mixed => $blocked(0, ['snapshot_closed' => false])['cache_action'], 'hold_checkpoint_page_cache_for_open_readers'],
    'database path block' => [static fn (): mixed => $blocked(0, ['database_path' => '/tmp/other.sqlite'])['release_rows'][0]['blocked_reasons'], ['reader_release_database_path_mismatch']],
    'wal path block' => [static fn (): mixed => $blocked(0, ['wal_path' => '/tmp/other.sqlite-wal'])['release_rows'][0]['blocked_reasons'], ['reader_release_wal_path_mismatch']],
    'journal path block' => [static fn (): mixed => $blocked(0, ['journal_path' => '/tmp/other.sqlite-journal'])['release_rows'][0]['blocked_reasons'], ['reader_release_journal_path_mismatch']],
    'source token block' => [static fn (): mixed => $blocked(0, ['source_token' => 'old-source'])['release_rows'][0]['blocked_reasons'], ['reader_release_source_token_mismatch']],
    'writer generation block' => [static fn (): mixed => $blocked(0, ['writer_generation' => 247])['release_rows'][0]['blocked_reasons'], ['reader_release_writer_generation_mismatch']],
    'reader generation block' => [static fn (): mixed => $blocked(0, ['reader_generation' => 248])['release_rows'][0]['blocked_reasons'], ['reader_release_generation_stale']],
    'database digest block' => [static fn (): mixed => $blocked(0, ['database_digest' => $hash('stale release')])['release_rows'][0]['blocked_reasons'], ['reader_release_database_digest_mismatch']],
    'release frame order block' => [static fn (): mixed => $blocked(0, ['last_visible_frame' => 6, 'release_frame' => 5])['release_rows'][0]['blocked_reasons'], ['reader_release_frame_before_visible_frame']],
    'release frame past writer block' => [static fn (): mixed => $blocked(0, ['release_frame' => 249])['release_rows'][0]['blocked_reasons'], ['reader_release_frame_past_writer_generation']],
    'page block' => [static fn (): mixed => $blocked(0, ['page_numbers' => [1, 7]])['release_rows'][0]['blocked_reasons'], ['reader_release_page_not_checkpoint_covered']],
    'snapshot open block' => [static fn (): mixed => $blocked(0, ['snapshot_closed' => false])['release_rows'][0]['blocked_reasons'], ['reader_release_snapshot_still_open']],
    'cache dirty block' => [static fn (): mixed => $blocked(0, ['page_cache_clean' => false])['release_rows'][0]['blocked_reasons'], ['reader_release_page_cache_dirty']],
    'shared lock block' => [static fn (): mixed => $blocked(0, ['shared_lock_released' => false])['release_rows'][0]['blocked_reasons'], ['reader_release_shared_lock_held']],
    'reserved lock block' => [static fn (): mixed => $blocked(0, ['reserved_lock_held' => true])['release_rows'][0]['blocked_reasons'], ['reader_release_reserved_lock_held']],
    'hot journal block' => [static fn (): mixed => $blocked(0, ['hot_journal_visible' => true])['release_rows'][0]['blocked_reasons'], ['reader_release_hot_journal_visible']],
    'savepoint block' => [static fn (): mixed => $blocked(0, ['savepoint_depth' => 1])['release_rows'][0]['blocked_reasons'], ['reader_release_savepoint_scope_open']],
    'combined block reasons' => [static fn (): mixed => $blocked(1, ['source_token' => 'old-source', 'page_cache_clean' => false, 'hot_journal_visible' => true])['release_rows'][1]['blocked_reasons'], ['reader_release_source_token_mismatch', 'reader_release_page_cache_dirty', 'reader_release_hot_journal_visible']],
    'missing release name' => [static fn (): mixed => $plan(null, [$releases[0], $releases[2]])['missing_release_names'], ['wp-next248-options-import']],
    'missing release guard' => [static fn (): mixed => in_array('all_admitted_readers_released', $plan(null, [$releases[0], $releases[2]])['blocked_guard_names'], true), true],
    'unexpected release name' => [static fn (): mixed => $plan(null, [$releases[0], $releases[1], $release('wp-next248-unexpected')])['unexpected_release_names'], ['wp-next248-unexpected']],
    'unexpected release row reason' => [static fn (): mixed => $plan(null, [$releases[0], $releases[1], $release('wp-next248-unexpected')])['release_rows'][2]['blocked_reasons'], ['reader_release_not_expected']],
    'duplicate release block' => [static fn (): mixed => $plan(null, [$releases[0], array_replace($releases[1], ['name' => $releases[0]['name']]), $releases[2]])['duplicate_release_names'], ['wp-next248-front-page']],
    'duplicate guard block' => [static fn (): mixed => $plan(null, [$releases[0], array_replace($releases[1], ['name' => $releases[0]['name']]), $releases[2]])['blocked_guard_names'], ['release_receipt_names_unique', 'all_admitted_readers_released']],
];

foreach ($cases as $name => [$callback, $expected]) {
    $tests['wal hot journal savepoint checkpoint current source next248 ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        $t->same($expected, $callback());
    };
}

$throws = [
    'bad base status rejected' => static fn () => $plan(array_replace($admissionPlan, ['status' => 'bad'])),
    'not admitted rejected' => static fn () => $plan(array_replace($admissionPlan, ['readers_admitted' => false])),
    'empty releases rejected' => static fn () => $plan(null, []),
    'bad database path rejected' => static fn () => $plan(array_replace($admissionPlan, ['database_path' => ''])),
    'bad source token rejected' => static fn () => $plan(array_replace($admissionPlan, ['source_token' => 'bad token'])),
    'bad writer generation rejected' => static fn () => $plan(array_replace($admissionPlan, ['writer_generation' => 0])),
    'bad next generation rejected' => static fn () => $plan(array_replace($admissionPlan, ['next_source_generation' => 0])),
    'bad digest rejected' => static fn () => $plan(array_replace($admissionPlan, ['database_digest' => 'short'])),
    'bad accepted names rejected' => static fn () => $plan(array_replace($admissionPlan, ['accepted_reader_names' => []])),
    'bad covered pages rejected' => static fn () => $plan(array_replace($admissionPlan, ['covered_page_numbers' => [0]])),
    'bad release name rejected' => static fn () => $plan(null, [array_replace($releases[0], ['name' => 'bad name'])]),
    'bad release generation rejected' => static fn () => $plan(null, [array_replace($releases[0], ['reader_generation' => 0])]),
    'bad release digest rejected' => static fn () => $plan(null, [array_replace($releases[0], ['database_digest' => 'short'])]),
    'bad last frame rejected' => static fn () => $plan(null, [array_replace($releases[0], ['last_visible_frame' => -1])]),
    'bad release frame rejected' => static fn () => $plan(null, [array_replace($releases[0], ['release_frame' => -1])]),
    'bad page rejected' => static fn () => $plan(null, [array_replace($releases[0], ['page_numbers' => [0]])]),
];

foreach ($throws as $name => $callback) {
    $tests['wal hot journal savepoint checkpoint current source next248 ' . $name] = static function (TestRunner $t) use ($callback): void {
        $t->throws(Throwable::class, $callback);
    };
}

return $tests;

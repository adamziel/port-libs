<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteWalHotJournalSavepointCheckpointCurrentSourceNext261Plan;

require_once __DIR__ . '/../src/SQLiteWalHotJournalSavepointCheckpointCurrentSourceNext261Plan.php';

$tests = [];

$hash = static fn (string $value): string => hash('sha256', $value);
$writerPlan = [
    'status' => 'wal-hot-journal-savepoint-checkpoint-current-source-next258',
    'post_restart_writer_admitted' => true,
    'database_path' => '/srv/www/wp-content/database/wp-next261.sqlite',
    'wal_path' => '/srv/www/wp-content/database/wp-next261.sqlite-wal',
    'source_token' => 'wp-next261-source',
    'commit_generation' => 261,
    'checkpoint_frame' => 0,
    'database_digest' => $hash('next261 database image'),
    'page_cache_digest' => $hash('next261 page cache'),
    'restart_wal_salt' => ['00000105', '00000206'],
    'accepted_writer_receipt_names' => ['first-frame', 'header-salt', 'reader-fence', 'sync'],
    'reopened_reader_names' => ['autoload-reader', 'options-reader', 'plugin-reader'],
    'readmark_slots' => [1, 2, 4],
    'operation_names' => ['admit_post_restart_writer_current_source_next258'],
    'dependencies' => ['sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next258'],
];

$receipt = static function (string $name, string $type, array $override = []) use ($writerPlan): array {
    return array_replace([
        'name' => $name,
        'receipt_type' => $type,
        'database_path' => $writerPlan['database_path'],
        'wal_path' => $writerPlan['wal_path'],
        'source_token' => $writerPlan['source_token'],
        'publish_generation' => 262,
        'checkpoint_frame' => 0,
        'database_digest' => $writerPlan['database_digest'],
        'page_cache_digest' => $writerPlan['page_cache_digest'],
        'published_wal_salt' => ['00000a0b', '00000c0d'],
        'writer_receipt_names' => $writerPlan['accepted_writer_receipt_names'],
        'readmark_slots' => $writerPlan['readmark_slots'],
        'reader_names' => $writerPlan['reopened_reader_names'],
        'mx_frame' => 1,
        'savepoint_depth' => 0,
        'exclusive_lock_held' => true,
        'hot_journal_visible' => false,
        'database_synced' => true,
        'wal_synced' => true,
        'shm_synced' => true,
        'io_error' => null,
    ], $override);
};

$receipts = [
    $receipt('next261-database-image', 'database-image'),
    $receipt('next261-wal-frame', 'wal-frame'),
    $receipt('next261-shm-index', 'shm-index'),
    $receipt('next261-readmark-fence', 'readmark-fence'),
    $receipt('next261-savepoint-release', 'savepoint-release'),
    $receipt('next261-sync', 'sync'),
];

$plan = static fn (?array $inputPlan = null, ?array $inputReceipts = null): array =>
    SQLiteWalHotJournalSavepointCheckpointCurrentSourceNext261Plan::sealPublishedCurrentSource($inputPlan ?? $writerPlan, $inputReceipts ?? $receipts);
$blocked = static fn (int $index, array $overrides): array => $plan(null, array_map(
    static fn (array $row, int $rowIndex): array => $rowIndex === $index ? array_replace($row, $overrides) : $row,
    $receipts,
    array_keys($receipts)
));
$without = static fn (int $index): array => $plan(null, array_values(array_filter(
    $receipts,
    static fn (array $_, int $key): bool => $key !== $index,
    ARRAY_FILTER_USE_BOTH
)));

$cases = [
    'status' => [static fn (): mixed => $plan()['status'], 'wal-hot-journal-savepoint-checkpoint-current-source-next261'],
    'reason' => [static fn (): mixed => $plan()['reason'], 'current_source_sealed_after_post_restart_writer'],
    'base status' => [static fn (): mixed => $plan()['base_status'], 'wal-hot-journal-savepoint-checkpoint-current-source-next258'],
    'database path' => [static fn (): mixed => $plan()['database_path'], $writerPlan['database_path']],
    'wal path' => [static fn (): mixed => $plan()['wal_path'], $writerPlan['wal_path']],
    'source token' => [static fn (): mixed => $plan()['source_token'], 'wp-next261-source'],
    'commit generation' => [static fn (): mixed => $plan()['commit_generation'], 261],
    'publish generation' => [static fn (): mixed => $plan()['publish_generation'], 262],
    'checkpoint frame' => [static fn (): mixed => $plan()['checkpoint_frame'], 0],
    'database digest' => [static fn (): mixed => $plan()['database_digest'], $hash('next261 database image')],
    'page cache digest' => [static fn (): mixed => $plan()['page_cache_digest'], $hash('next261 page cache')],
    'restart salt' => [static fn (): mixed => $plan()['restart_wal_salt'], ['00000105', '00000206']],
    'writer names' => [static fn (): mixed => $plan()['accepted_writer_receipt_names'], ['first-frame', 'header-salt', 'reader-fence', 'sync']],
    'reader names' => [static fn (): mixed => $plan()['reopened_reader_names'], ['autoload-reader', 'options-reader', 'plugin-reader']],
    'readmark slots' => [static fn (): mixed => $plan()['readmark_slots'], [1, 2, 4]],
    'receipt names' => [static fn (): mixed => $plan()['publish_receipt_names'], ['next261-database-image', 'next261-wal-frame', 'next261-shm-index', 'next261-readmark-fence', 'next261-savepoint-release', 'next261-sync']],
    'accepted receipt names' => [static fn (): mixed => $plan()['accepted_publish_receipt_names'], ['next261-database-image', 'next261-wal-frame', 'next261-shm-index', 'next261-readmark-fence', 'next261-savepoint-release', 'next261-sync']],
    'blocked names empty' => [static fn (): mixed => $plan()['blocked_publish_receipt_names'], []],
    'duplicate names empty' => [static fn (): mixed => $plan()['duplicate_publish_receipt_names'], []],
    'receipt types' => [static fn (): mixed => $plan()['publish_receipt_types'], ['database-image', 'wal-frame', 'shm-index', 'readmark-fence', 'savepoint-release', 'sync']],
    'missing types empty' => [static fn (): mixed => $plan()['missing_publish_receipt_types'], []],
    'covered writers' => [static fn (): mixed => $plan()['covered_writer_receipt_names'], ['first-frame', 'header-salt', 'reader-fence', 'sync']],
    'missing writers empty' => [static fn (): mixed => $plan()['missing_writer_receipt_names'], []],
    'covered readmarks' => [static fn (): mixed => $plan()['covered_readmark_slots'], [1, 2, 4]],
    'missing readmarks empty' => [static fn (): mixed => $plan()['missing_readmark_slots'], []],
    'covered readers' => [static fn (): mixed => $plan()['covered_reader_names'], ['autoload-reader', 'options-reader', 'plugin-reader']],
    'missing readers empty' => [static fn (): mixed => $plan()['missing_reader_names'], []],
    'blocked reasons empty' => [static fn (): mixed => $plan()['blocked_publish_reasons'], []],
    'guard names' => [static fn (): mixed => $plan()['guard_names'], ['next258_writer_admitted', 'publish_receipt_names_unique', 'all_publish_receipt_types_present', 'all_writer_receipts_covered', 'all_readmarks_fenced', 'all_reopened_readers_covered', 'all_publish_receipts_current']],
    'guard matches' => [static fn (): mixed => $plan()['guard_matches'], [true, true, true, true, true, true, true]],
    'blocked guards empty' => [static fn (): mixed => $plan()['blocked_guard_names'], []],
    'sealed bool' => [static fn (): mixed => $plan()['current_source_sealed'], true],
    'writer action' => [static fn (): mixed => $plan()['writer_action'], 'publish_post_restart_writer_generation_262'],
    'wal action' => [static fn (): mixed => $plan()['wal_action'], 'seal_wal_frame_one_as_current_source'],
    'reader action' => [static fn (): mixed => $plan()['reader_action'], 'advance_reopened_readers_to_source_digest'],
    'savepoint action' => [static fn (): mixed => $plan()['savepoint_action'], 'release_post_restart_publish_savepoint'],
    'sync action' => [static fn (): mixed => $plan()['sync_action'], 'fsync_database_wal_shm_before_reader_publish'],
    'digest length' => [static fn (): mixed => strlen($plan()['publication_digest']), 64],
    'operation inherited' => [static fn (): mixed => in_array('admit_post_restart_writer_current_source_next258', $plan()['operation_names'], true), true],
    'operation added' => [static fn (): mixed => in_array('seal_current_source_next261', $plan()['operation_names'], true), true],
    'dependency inherited' => [static fn (): mixed => in_array('sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next258', $plan()['dependencies'], true), true],
    'dependency added' => [static fn (): mixed => in_array('sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next261', $plan()['dependencies'], true), true],
    'dependency wordpress' => [static fn (): mixed => in_array('wordpress-import-wal-current-source-seal', $plan()['dependencies'], true), true],
    'dependency closure' => [static fn (): mixed => str_contains($plan()['dependency_closure'], 'no new support component needed'), true],
    'non overlap' => [static fn (): mixed => str_contains($plan()['non_overlap'], 'does not repeat next258 writer admission'), true],
    'first row accepted' => [static fn (): mixed => $plan()['publish_rows'][0]['accepted'], true],
    'first row reason' => [static fn (): mixed => $plan()['publish_rows'][0]['receipt_reason'], 'current_source_publish_receipt_matches'],
    'first row type' => [static fn (): mixed => $plan()['publish_rows'][0]['receipt_type'], 'database-image'],
    'first row salt' => [static fn (): mixed => $plan()['publish_rows'][0]['published_wal_salt'], ['00000a0b', '00000c0d']],
    'first row mx frame' => [static fn (): mixed => $plan()['publish_rows'][0]['mx_frame'], 1],
    'blocked status' => [static fn (): mixed => $blocked(0, ['database_synced' => false])['status'], 'wal-hot-journal-savepoint-checkpoint-current-source-blocked-next261'],
    'blocked reason' => [static fn (): mixed => $blocked(0, ['database_synced' => false])['reason'], 'current_source_publish_waits_for_receipts'],
    'blocked writer action' => [static fn (): mixed => $blocked(0, ['database_synced' => false])['writer_action'], 'hold_post_restart_writer_source_unpublished'],
    'blocked wal action' => [static fn (): mixed => $blocked(0, ['wal_synced' => false])['wal_action'], 'preserve_restarted_wal_pending_publish'],
    'blocked reader action' => [static fn (): mixed => $blocked(0, ['reader_names' => ['bad-reader']])['reader_action'], 'keep_reopened_readers_on_checkpoint_snapshot'],
    'blocked savepoint action' => [static fn (): mixed => $blocked(0, ['savepoint_depth' => 1])['savepoint_action'], 'keep_post_restart_publish_savepoint_replayable'],
    'blocked sync action' => [static fn (): mixed => $blocked(0, ['shm_synced' => false])['sync_action'], 'wait_for_database_wal_shm_sync'],
    'database path block' => [static fn (): mixed => $blocked(0, ['database_path' => '/tmp/other.sqlite'])['publish_rows'][0]['blocked_reasons'], ['current_source_publish_database_path_mismatch']],
    'wal path block' => [static fn (): mixed => $blocked(0, ['wal_path' => '/tmp/other.sqlite-wal'])['publish_rows'][0]['blocked_reasons'], ['current_source_publish_wal_path_mismatch']],
    'source token block' => [static fn (): mixed => $blocked(0, ['source_token' => 'old-source'])['publish_rows'][0]['blocked_reasons'], ['current_source_publish_source_token_mismatch']],
    'generation block' => [static fn (): mixed => $blocked(0, ['publish_generation' => 261])['publish_rows'][0]['blocked_reasons'], ['current_source_publish_generation_mismatch']],
    'checkpoint frame block' => [static fn (): mixed => $blocked(0, ['checkpoint_frame' => 1])['publish_rows'][0]['blocked_reasons'], ['current_source_publish_checkpoint_frame_mismatch']],
    'database digest block' => [static fn (): mixed => $blocked(0, ['database_digest' => $hash('bad db')])['publish_rows'][0]['blocked_reasons'], ['current_source_publish_database_digest_mismatch']],
    'page cache digest block' => [static fn (): mixed => $blocked(0, ['page_cache_digest' => $hash('bad cache')])['publish_rows'][0]['blocked_reasons'], ['current_source_publish_page_cache_digest_mismatch']],
    'reused salt block' => [static fn (): mixed => $blocked(0, ['published_wal_salt' => ['00000105', '00000206']])['publish_rows'][0]['blocked_reasons'], ['current_source_publish_reused_restart_salt']],
    'unknown writer block' => [static fn (): mixed => $blocked(0, ['writer_receipt_names' => ['unknown-writer']])['publish_rows'][0]['blocked_reasons'], ['current_source_publish_unknown_writer_receipt']],
    'unknown readmark block' => [static fn (): mixed => $blocked(0, ['readmark_slots' => [9]])['publish_rows'][0]['blocked_reasons'], ['current_source_publish_unknown_readmark_slot']],
    'unknown reader block' => [static fn (): mixed => $blocked(0, ['reader_names' => ['bad-reader']])['publish_rows'][0]['blocked_reasons'], ['current_source_publish_unknown_reader']],
    'savepoint block' => [static fn (): mixed => $blocked(0, ['savepoint_depth' => 1])['publish_rows'][0]['blocked_reasons'], ['current_source_publish_savepoint_scope_open']],
    'exclusive lock block' => [static fn (): mixed => $blocked(0, ['exclusive_lock_held' => false])['publish_rows'][0]['blocked_reasons'], ['current_source_publish_exclusive_lock_missing']],
    'hot journal block' => [static fn (): mixed => $blocked(0, ['hot_journal_visible' => true])['publish_rows'][0]['blocked_reasons'], ['current_source_publish_hot_journal_visible']],
    'database sync block' => [static fn (): mixed => $blocked(0, ['database_synced' => false])['publish_rows'][0]['blocked_reasons'], ['current_source_publish_database_not_synced']],
    'wal sync block' => [static fn (): mixed => $blocked(0, ['wal_synced' => false])['publish_rows'][0]['blocked_reasons'], ['current_source_publish_wal_not_synced']],
    'shm sync block' => [static fn (): mixed => $blocked(0, ['shm_synced' => false])['publish_rows'][0]['blocked_reasons'], ['current_source_publish_shm_not_synced']],
    'io error block' => [static fn (): mixed => $blocked(0, ['io_error' => 'SQLITE_IOERR_FSYNC'])['publish_rows'][0]['blocked_reasons'], ['current_source_publish_io_error']],
    'combined block' => [static fn (): mixed => $blocked(0, ['wal_synced' => false, 'shm_synced' => false, 'hot_journal_visible' => true])['publish_rows'][0]['blocked_reasons'], ['current_source_publish_hot_journal_visible', 'current_source_publish_wal_not_synced', 'current_source_publish_shm_not_synced']],
    'missing type' => [static fn (): mixed => $without(2)['missing_publish_receipt_types'], ['shm-index']],
    'missing type guard' => [static fn (): mixed => $without(2)['blocked_guard_names'], ['all_publish_receipt_types_present']],
    'duplicate name' => [static fn (): mixed => $plan(null, array_replace($receipts, [1 => array_replace($receipts[1], ['name' => 'next261-database-image'])]))['duplicate_publish_receipt_names'], ['next261-database-image']],
    'duplicate reason' => [static fn (): mixed => in_array('current_source_publish_receipt_name_duplicate:next261-database-image', $plan(null, array_replace($receipts, [1 => array_replace($receipts[1], ['name' => 'next261-database-image'])]))['blocked_publish_reasons'], true), true],
    'missing writer coverage' => [static fn (): mixed => $plan(null, array_map(static fn (array $row): array => array_replace($row, ['writer_receipt_names' => ['sync']]), $receipts))['missing_writer_receipt_names'], ['first-frame', 'header-salt', 'reader-fence']],
    'missing readmark coverage' => [static fn (): mixed => $plan(null, array_map(static fn (array $row): array => array_replace($row, ['readmark_slots' => [1]]), $receipts))['missing_readmark_slots'], [2, 4]],
    'missing reader coverage' => [static fn (): mixed => $plan(null, array_map(static fn (array $row): array => array_replace($row, ['reader_names' => ['autoload-reader']]), $receipts))['missing_reader_names'], ['options-reader', 'plugin-reader']],
];

foreach ($cases as $name => [$callback, $expected]) {
    $tests['wal hot journal savepoint checkpoint current source next261 ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        $t->same($expected, $callback());
    };
}

$throws = [
    'bad base rejected' => static fn () => $plan(array_replace($writerPlan, ['status' => 'bad'])),
    'not admitted rejected' => static fn () => $plan(array_replace($writerPlan, ['post_restart_writer_admitted' => false])),
    'empty receipts rejected' => static fn () => $plan(null, []),
    'bad database path rejected' => static fn () => $plan(array_replace($writerPlan, ['database_path' => ''])),
    'bad wal path rejected' => static fn () => $plan(array_replace($writerPlan, ['wal_path' => ''])),
    'bad token rejected' => static fn () => $plan(array_replace($writerPlan, ['source_token' => 'bad token'])),
    'bad generation rejected' => static fn () => $plan(array_replace($writerPlan, ['commit_generation' => 0])),
    'bad checkpoint rejected' => static fn () => $plan(array_replace($writerPlan, ['checkpoint_frame' => -1])),
    'bad digest rejected' => static fn () => $plan(array_replace($writerPlan, ['database_digest' => 'short'])),
    'bad cache digest rejected' => static fn () => $plan(array_replace($writerPlan, ['page_cache_digest' => 'short'])),
    'bad salt rejected' => static fn () => $plan(array_replace($writerPlan, ['restart_wal_salt' => ['bad', '00000206']])),
    'bad writers rejected' => static fn () => $plan(array_replace($writerPlan, ['accepted_writer_receipt_names' => []])),
    'bad readers rejected' => static fn () => $plan(array_replace($writerPlan, ['reopened_reader_names' => []])),
    'bad readmarks rejected' => static fn () => $plan(array_replace($writerPlan, ['readmark_slots' => [0]])),
    'bad receipt name rejected' => static fn () => $plan(null, [array_replace($receipts[0], ['name' => 'bad name'])]),
    'bad receipt type rejected' => static fn () => $plan(null, [array_replace($receipts[0], ['receipt_type' => 'bad'])]),
    'bad receipt path rejected' => static fn () => $plan(null, [array_replace($receipts[0], ['database_path' => ''])]),
    'bad receipt token rejected' => static fn () => $plan(null, [array_replace($receipts[0], ['source_token' => 'bad token'])]),
    'bad receipt generation rejected' => static fn () => $plan(null, [array_replace($receipts[0], ['publish_generation' => 0])]),
    'bad receipt digest rejected' => static fn () => $plan(null, [array_replace($receipts[0], ['database_digest' => 'short'])]),
    'bad receipt salt rejected' => static fn () => $plan(null, [array_replace($receipts[0], ['published_wal_salt' => ['bad', '00000c0d']])]),
    'bad receipt writers rejected' => static fn () => $plan(null, [array_replace($receipts[0], ['writer_receipt_names' => []])]),
    'bad receipt readmarks rejected' => static fn () => $plan(null, [array_replace($receipts[0], ['readmark_slots' => [0]])]),
    'bad receipt readers rejected' => static fn () => $plan(null, [array_replace($receipts[0], ['reader_names' => []])]),
    'bad mxframe rejected' => static fn () => $plan(null, [array_replace($receipts[0], ['mx_frame' => 0])]),
    'bad savepoint rejected' => static fn () => $plan(null, [array_replace($receipts[0], ['savepoint_depth' => -1])]),
];

foreach ($throws as $name => $callback) {
    $tests['wal hot journal savepoint checkpoint current source next261 ' . $name] = static function (TestRunner $t) use ($callback): void {
        $t->throws(Throwable::class, $callback);
    };
}

return $tests;

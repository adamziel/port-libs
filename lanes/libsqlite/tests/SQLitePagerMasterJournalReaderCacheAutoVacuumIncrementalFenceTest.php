<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePagerMasterJournalReaderCacheCurrentSourceNextPlan;

$tests = [];

$pageSize = 512;
$database = '/srv/wp-content/database/wp-next314.sqlite';
$usersDatabase = '/srv/wp-content/database/wp-next314-users.sqlite';
$mainJournal = $database . '-journal';
$usersJournal = $usersDatabase . '-journal';
$master = $database . '-mj';
$masterBytes = "{$mainJournal}\n{$usersJournal}\n";
$page = static fn (string $label): string => str_pad($label, $pageSize, '.', STR_PAD_RIGHT);
$formatPage = static function (string $label) use ($pageSize): string {
    $page = str_pad('SQLite format 3' . "\0", 100, "\0", STR_PAD_RIGHT) . str_repeat('.', $pageSize - 100);
    $page = substr_replace($page, pack('n', 512), 16, 2);
    $page = substr_replace($page, chr(4), 20, 1);
    $page = substr_replace($page, pack('N', 2), 56, 4);
    $page = substr_replace($page, pack('N', 314), 60, 4);

    return substr_replace($page, $label, 100, strlen($label));
};
$mapDigest = static function (array $map): string {
    ksort($map, SORT_STRING);

    return hash('sha256', implode('|', array_map(static fn (string $key, string $value): string => $key . '=' . $value, array_keys($map), $map)));
};
$recoveredDigest = static function (array $pages): string {
    ksort($pages, SORT_NUMERIC);

    return hash('sha256', implode('|', array_map(static fn (int $pageNumber, string $image): string => $pageNumber . ':' . hash('sha256', $image), array_keys($pages), $pages)));
};

$before = [1 => $formatPage('next314 stale schema'), 2 => $page('next314 stale options'), 3 => $page('next314 stale plugins'), 4 => $page('next314 stale users')];
$recovered = [1 => $formatPage('next314 current schema'), 2 => $page('next314 current options'), 3 => $page('next314 current plugins'), 4 => $page('next314 current users')];
$tokens = [$mainJournal => 'member-main-current-314', $usersJournal => 'member-users-current-314'];
$headers = [$mainJournal => hash('sha256', 'main header next314'), $usersJournal => hash('sha256', 'users header next314')];
$base = [
    'source_id' => 'pager-reader-cache-current-source-next314',
    'epoch' => 314,
    'format_signature' => hash('sha256', implode('|', [512, 4, 2, 314, 0])),
    'publication_generation' => 314,
    'master_source_digest' => hash('sha256', 'master-next314'),
    'recovery_sequence' => 314,
    'recovered_page_set_digest' => $recoveredDigest($recovered),
    'member_journal_tokens' => $tokens,
    'member_journal_header_digests' => $headers,
    'master_member_order_digest' => hash('sha256', implode("\n", array_keys($tokens))),
    'master_journal_file_token' => 'master-file-current-314',
    'master_journal_bytes_digest' => hash('sha256', $masterBytes),
    'database_file_token' => 'database-file-current-314',
    'master_journal_cleanup_token' => 'master-cleanup-current-314',
    'reader_lease_token' => 'reader-lease-current-314',
    'pager_cache_source_token' => 'pager-cache-source-current-314',
    'read_transaction_token' => 'read-transaction-current-314',
    'schema_reparse_token' => 'schema-reparse-current-314',
    'statement_schema_root_token' => 'statement-schema-root-current-314',
    'current_source_provenance_token' => 'source-provenance-current-314',
    'pager_reader_cache_generation_token' => 'cache-generation-current-314',
    'reader_snapshot_token' => 'reader-snapshot-current-314',
    'master_journal_recovery_receipt_token' => 'recovery-receipt-current-314',
    'pager_spill_drain_token' => 'spill-drain-current-314',
    'rollback_journal_reader_source_token' => 'rollback-source-current-314',
    'pager_hot_journal_header_token' => 'hot-header-current-314',
    'master_journal_member_epoch_token' => 'member-epoch-current-314',
    'reader_cache_schema_cookie_token' => 'schema-cookie-current-314',
    'reader_cache_vacuum_root_token' => 'vacuum-root-current-314',
    'pager_reserved_lock_token' => 'reserved-lock-current-314',
    'reader_cache_page_count_token' => 'page-count-current-314',
    'reader_cache_schema_version_token' => 'schema-version-current-314',
    'reader_cache_change_counter_token' => 'change-counter-current-314',
    'reader_cache_freelist_trunk_token' => 'freelist-trunk-current-314',
    'reader_cache_auto_vacuum_token' => 'auto-vacuum-current-314',
    'reader_cache_encoding_token' => 'encoding-current-314',
    'reader_cache_text_schema_token' => 'text-schema-current-314',
    'reader_cache_index_schema_token' => 'index-schema-current-314',
    'reader_cache_trigger_schema_token' => 'trigger-schema-current-314',
    'reader_cache_view_schema_token' => 'view-schema-current-314',
    'reader_cache_virtual_table_schema_token' => 'virtual-table-schema-current-314',
    'reader_cache_module_schema_token' => 'module-schema-current-314',
    'reader_cache_pragma_schema_token' => 'pragma-schema-current-314',
    'reader_cache_collation_schema_token' => 'collation-schema-current-314',
    'reader_cache_authorizer_schema_token' => 'authorizer-schema-current-314',
    'reader_cache_transaction_state_token' => 'transaction-state-current-314',
    'reader_cache_commit_phase_token' => 'commit-phase-current-314',
    'reader_cache_busy_handler_token' => 'busy-handler-current-314',
    'reader_cache_savepoint_stack_token' => 'savepoint-stack-current-314',
    'reader_cache_statement_journal_token' => 'statement-journal-current-314',
    'reader_cache_temp_page_token' => 'temp-page-current-314',
    'reader_cache_dirty_list_token' => 'dirty-list-current-314',
    'reader_cache_spill_epoch_token' => 'spill-epoch-current-314',
    'reader_cache_locking_mode_token' => 'locking-mode-current-314',
    'reader_cache_journal_mode_token' => 'journal-mode-current-314',
    'reader_cache_synchronous_token' => 'synchronous-current-314',
    'reader_cache_mmap_size_token' => 'mmap-size-current-314',
    'reader_cache_cache_size_token' => 'cache-size-current-314',
    'reader_cache_wal_autocheckpoint_token' => 'wal-autocheckpoint-current-314',
    'reader_cache_query_only_token' => 'query-only-current-314',
    'reader_cache_foreign_key_token' => 'foreign-key-current-314',
    'reader_cache_defer_foreign_key_token' => 'defer-foreign-key-current-314',
    'reader_cache_recursive_trigger_token' => 'recursive-trigger-current-314',
    'reader_cache_trusted_schema_token' => 'trusted-schema-current-314',
    'reader_cache_ignore_check_constraints_token' => 'ignore-check-constraints-current-314',
    'reader_cache_application_id_token' => 'application-id-current-314',
    'reader_cache_user_version_token' => 'user-version-current-314',
    'reader_cache_schema_format_token' => 'schema-format-current-314',
    'reader_cache_auto_vacuum_incremental_token' => 'auto-vacuum-incremental-current-314',
];
$cacheEntry = static fn (string $label, string $image, array $extra = []): array => array_merge($base, ['label' => $label, 'reader_id' => $label . '-reader', 'image' => $image], $extra);
$read = static fn (int $pageNumber, array $extra = []): array => array_merge([
    'reader_id' => 'read-' . $pageNumber,
    'page_number' => $pageNumber,
    'source_id' => $base['source_id'],
    'epoch' => 314,
    'format_signature' => $base['format_signature'],
    'publication_generation' => 314,
    'master_source_digest' => $base['master_source_digest'],
    'recovery_sequence' => 314,
    'recovered_page_set_digest' => $base['recovered_page_set_digest'],
    'member_journal_token_digest' => $mapDigest($tokens),
    'member_journal_header_digest' => $mapDigest($headers),
    'master_member_order_digest' => $base['master_member_order_digest'],
    'master_journal_file_token' => $base['master_journal_file_token'],
    'master_journal_bytes_digest' => $base['master_journal_bytes_digest'],
    'database_file_token' => $base['database_file_token'],
    'master_journal_cleanup_token' => $base['master_journal_cleanup_token'],
    'reader_lease_token' => $base['reader_lease_token'],
    'pager_cache_source_token' => $base['pager_cache_source_token'],
    'read_transaction_token' => $base['read_transaction_token'],
    'schema_reparse_token' => $base['schema_reparse_token'],
    'statement_schema_root_token' => $base['statement_schema_root_token'],
    'current_source_provenance_token' => $base['current_source_provenance_token'],
    'pager_reader_cache_generation_token' => $base['pager_reader_cache_generation_token'],
    'reader_snapshot_token' => $base['reader_snapshot_token'],
    'master_journal_recovery_receipt_token' => $base['master_journal_recovery_receipt_token'],
    'pager_spill_drain_token' => $base['pager_spill_drain_token'],
    'rollback_journal_reader_source_token' => $base['rollback_journal_reader_source_token'],
    'pager_hot_journal_header_token' => $base['pager_hot_journal_header_token'],
    'master_journal_member_epoch_token' => $base['master_journal_member_epoch_token'],
    'reader_cache_schema_cookie_token' => $base['reader_cache_schema_cookie_token'],
    'reader_cache_vacuum_root_token' => $base['reader_cache_vacuum_root_token'],
    'pager_reserved_lock_token' => $base['pager_reserved_lock_token'],
    'reader_cache_page_count_token' => $base['reader_cache_page_count_token'],
    'reader_cache_schema_version_token' => $base['reader_cache_schema_version_token'],
    'reader_cache_change_counter_token' => $base['reader_cache_change_counter_token'],
    'reader_cache_freelist_trunk_token' => $base['reader_cache_freelist_trunk_token'],
    'reader_cache_auto_vacuum_token' => $base['reader_cache_auto_vacuum_token'],
    'reader_cache_encoding_token' => $base['reader_cache_encoding_token'],
    'reader_cache_text_schema_token' => $base['reader_cache_text_schema_token'],
    'reader_cache_index_schema_token' => $base['reader_cache_index_schema_token'],
    'reader_cache_trigger_schema_token' => $base['reader_cache_trigger_schema_token'],
    'reader_cache_view_schema_token' => $base['reader_cache_view_schema_token'],
    'reader_cache_virtual_table_schema_token' => $base['reader_cache_virtual_table_schema_token'],
    'reader_cache_module_schema_token' => $base['reader_cache_module_schema_token'],
    'reader_cache_pragma_schema_token' => $base['reader_cache_pragma_schema_token'],
    'reader_cache_collation_schema_token' => $base['reader_cache_collation_schema_token'],
    'reader_cache_authorizer_schema_token' => $base['reader_cache_authorizer_schema_token'],
    'reader_cache_transaction_state_token' => $base['reader_cache_transaction_state_token'],
    'reader_cache_commit_phase_token' => $base['reader_cache_commit_phase_token'],
    'reader_cache_busy_handler_token' => $base['reader_cache_busy_handler_token'],
    'reader_cache_savepoint_stack_token' => $base['reader_cache_savepoint_stack_token'],
    'reader_cache_statement_journal_token' => $base['reader_cache_statement_journal_token'],
    'reader_cache_temp_page_token' => $base['reader_cache_temp_page_token'],
    'reader_cache_dirty_list_token' => $base['reader_cache_dirty_list_token'],
    'reader_cache_spill_epoch_token' => $base['reader_cache_spill_epoch_token'],
    'reader_cache_locking_mode_token' => $base['reader_cache_locking_mode_token'],
    'reader_cache_journal_mode_token' => $base['reader_cache_journal_mode_token'],
    'reader_cache_synchronous_token' => $base['reader_cache_synchronous_token'],
    'reader_cache_mmap_size_token' => $base['reader_cache_mmap_size_token'],
    'reader_cache_cache_size_token' => $base['reader_cache_cache_size_token'],
    'reader_cache_wal_autocheckpoint_token' => $base['reader_cache_wal_autocheckpoint_token'],
    'reader_cache_query_only_token' => $base['reader_cache_query_only_token'],
    'reader_cache_foreign_key_token' => $base['reader_cache_foreign_key_token'],
    'reader_cache_defer_foreign_key_token' => $base['reader_cache_defer_foreign_key_token'],
    'reader_cache_recursive_trigger_token' => $base['reader_cache_recursive_trigger_token'],
    'reader_cache_trusted_schema_token' => $base['reader_cache_trusted_schema_token'],
    'reader_cache_ignore_check_constraints_token' => $base['reader_cache_ignore_check_constraints_token'],
    'reader_cache_application_id_token' => $base['reader_cache_application_id_token'],
    'reader_cache_user_version_token' => $base['reader_cache_user_version_token'],
    'reader_cache_schema_format_token' => $base['reader_cache_schema_format_token'],
    'reader_cache_auto_vacuum_incremental_token' => $base['reader_cache_auto_vacuum_incremental_token'],
], $extra);
$plan = static function (?array $cache = null, ?array $reads = null) use ($database, $master, $masterBytes, $before, $pageSize, $recovered, $base, $tokens, $headers, $cacheEntry, $read): array {
    return SQLitePagerMasterJournalReaderCacheCurrentSourceNextPlan::currentSourceReaderCacheAutoVacuumIncrementalFence($database, $master, $masterBytes, implode('', $before), $pageSize, $recovered, $cache ?? [
        1 => $cacheEntry('schema-stale-module', $recovered[1], ['reader_cache_module_schema_token' => 'module-schema-old']),
        2 => $cacheEntry('options-stale-pragma', $recovered[2], ['reader_cache_pragma_schema_token' => 'pragma-schema-old']),
        3 => $cacheEntry('plugins-stale-collation', $recovered[3], ['reader_cache_collation_schema_token' => 'collation-schema-old']),
        4 => $cacheEntry('users-current-cache-stale-statement-ticket', $recovered[4]),
    ], $reads ?? [$read(1), $read(2), $read(3), $read(4, ['reader_cache_savepoint_stack_token' => 'savepoint-stack-old', 'reader_cache_statement_journal_token' => 'statement-journal-old', 'reader_cache_temp_page_token' => 'temp-page-old', 'reader_cache_dirty_list_token' => 'dirty-list-old', 'reader_cache_spill_epoch_token' => 'spill-epoch-old', 'reader_cache_locking_mode_token' => 'locking-mode-old', 'reader_cache_journal_mode_token' => 'journal-mode-old', 'reader_cache_synchronous_token' => 'synchronous-old', 'reader_cache_mmap_size_token' => 'mmap-size-old', 'reader_cache_cache_size_token' => 'cache-size-old', 'reader_cache_wal_autocheckpoint_token' => 'wal-autocheckpoint-old', 'reader_cache_query_only_token' => 'query-only-old', 'reader_cache_foreign_key_token' => 'foreign-key-old', 'reader_cache_defer_foreign_key_token' => 'defer-foreign-key-old', 'reader_cache_recursive_trigger_token' => 'recursive-trigger-old', 'reader_cache_trusted_schema_token' => 'trusted-schema-old', 'reader_cache_ignore_check_constraints_token' => 'ignore-check-constraints-old', 'reader_cache_application_id_token' => 'application-id-old', 'reader_cache_user_version_token' => 'user-version-old', 'reader_cache_schema_format_token' => 'schema-format-old', 'reader_cache_auto_vacuum_incremental_token' => 'auto-vacuum-incremental-old'])], $base['source_id'], 314, 314, $base['master_source_digest'], 314, $tokens, $headers, $base['master_journal_file_token'], $base['database_file_token'], $base['master_journal_cleanup_token'], $base['reader_lease_token'], $base['pager_cache_source_token'], $base['read_transaction_token'], $base['schema_reparse_token'], $base['statement_schema_root_token'], $base['current_source_provenance_token'], $base['pager_reader_cache_generation_token'], $base['reader_snapshot_token'], $base['master_journal_recovery_receipt_token'], $base['pager_spill_drain_token'], $base['rollback_journal_reader_source_token'], $base['pager_hot_journal_header_token'], $base['master_journal_member_epoch_token'], $base['reader_cache_schema_cookie_token'], $base['reader_cache_vacuum_root_token'], $base['pager_reserved_lock_token'], $base['reader_cache_page_count_token'], $base['reader_cache_schema_version_token'], $base['reader_cache_change_counter_token'], $base['reader_cache_freelist_trunk_token'], $base['reader_cache_auto_vacuum_token'], $base['reader_cache_encoding_token'], $base['reader_cache_text_schema_token'], $base['reader_cache_index_schema_token'], $base['reader_cache_trigger_schema_token'], $base['reader_cache_view_schema_token'], $base['reader_cache_virtual_table_schema_token'], $base['reader_cache_module_schema_token'], $base['reader_cache_pragma_schema_token'], $base['reader_cache_collation_schema_token'], $base['reader_cache_authorizer_schema_token'], $base['reader_cache_transaction_state_token'], $base['reader_cache_commit_phase_token'], $base['reader_cache_busy_handler_token'], $base['reader_cache_savepoint_stack_token'], $base['reader_cache_statement_journal_token'], $base['reader_cache_temp_page_token'], $base['reader_cache_dirty_list_token'], $base['reader_cache_spill_epoch_token'], $base['reader_cache_locking_mode_token'], $base['reader_cache_journal_mode_token'], $base['reader_cache_synchronous_token'], $base['reader_cache_mmap_size_token'], $base['reader_cache_cache_size_token'], $base['reader_cache_wal_autocheckpoint_token'], $base['reader_cache_query_only_token'], $base['reader_cache_foreign_key_token'], $base['reader_cache_defer_foreign_key_token'], $base['reader_cache_recursive_trigger_token'], $base['reader_cache_trusted_schema_token'], $base['reader_cache_ignore_check_constraints_token'], $base['reader_cache_application_id_token'], $base['reader_cache_user_version_token'], $base['reader_cache_schema_format_token'], $base['reader_cache_auto_vacuum_incremental_token']);
};
$opCount = static fn (array $plan, string $op): int => count(array_filter($plan['operations'], static fn (array $operation): bool => ($operation['op'] ?? '') === $op));

$cases = [
    'status' => [static fn (): mixed => $plan()['status'], 'pager-master-journal-reader-cache-current-source-next314'],
    'next287 module-schema invalidation' => [static fn (): mixed => $plan()['reader_cache_module_schema_invalidated_cache_page_numbers'], [1]],
    'next288 pragma-schema invalidation' => [static fn (): mixed => $plan()['reader_cache_pragma_schema_invalidated_cache_page_numbers'], [2]],
    'next289 collation-schema invalidation' => [static fn (): mixed => $plan()['reader_cache_collation_schema_invalidated_cache_page_numbers'], [3]],
    'next291 transaction-state fence is current' => [static fn (): mixed => $plan()['current_reader_cache_transaction_state_token'], 'transaction-state-current-314'],
    'next292 commit-phase fence is current' => [static fn (): mixed => $plan()['current_reader_cache_commit_phase_token'], 'commit-phase-current-314'],
    'next293 busy-handler fence is current' => [static fn (): mixed => $plan()['current_reader_cache_busy_handler_token'], 'busy-handler-current-314'],
    'next294 savepoint-stack ticket reopens reader' => [static fn (): mixed => $plan()['next_reads'][3]['reader_cache_savepoint_stack_token_reason'], 'reader_ticket_reader_cache_savepoint_stack_predates_current_source'],
    'next295 statement-journal ticket reopens reader' => [static fn (): mixed => $plan()['next_reads'][3]['reader_cache_statement_journal_token_reason'], 'reader_ticket_reader_cache_statement_journal_predates_current_source'],
    'next296 temp-page ticket reopens reader' => [static fn (): mixed => $plan()['next_reads'][3]['reader_cache_temp_page_token_reason'], 'reader_ticket_reader_cache_temp_page_predates_current_source'],
    'next297 dirty-list ticket reopens reader' => [static fn (): mixed => $plan()['next_reads'][3]['reader_cache_dirty_list_token_reason'], 'reader_ticket_reader_cache_dirty_list_predates_current_source'],
    'next298 spill-epoch ticket reopens reader' => [static fn (): mixed => $plan()['next_reads'][3]['reader_cache_spill_epoch_token_reason'], 'reader_ticket_reader_cache_spill_epoch_predates_current_source'],
    'next299 locking-mode ticket reopens reader' => [static fn (): mixed => $plan()['next_reads'][3]['reader_cache_locking_mode_token_reason'], 'reader_ticket_reader_cache_locking_mode_predates_current_source'],
    'next300 journal-mode ticket reopens reader' => [static fn (): mixed => $plan()['next_reads'][3]['reader_cache_journal_mode_token_reason'], 'reader_ticket_reader_cache_journal_mode_predates_current_source'],
    'next301 synchronous ticket reopens reader' => [static fn (): mixed => $plan()['next_reads'][3]['reader_cache_synchronous_token_reason'], 'reader_ticket_reader_cache_synchronous_predates_current_source'],
    'next302 mmap-size ticket reopens reader' => [static fn (): mixed => $plan()['next_reads'][3]['reader_cache_mmap_size_token_reason'], 'reader_ticket_reader_cache_mmap_size_predates_current_source'],
    'next303 cache-size ticket reopens reader' => [static fn (): mixed => $plan()['next_reads'][3]['reader_cache_cache_size_token_reason'], 'reader_ticket_reader_cache_cache_size_predates_current_source'],
    'next304 wal-autocheckpoint ticket reopens reader' => [static fn (): mixed => $plan()['next_reads'][3]['reader_cache_wal_autocheckpoint_token_reason'], 'reader_ticket_reader_cache_wal_autocheckpoint_predates_current_source'],
    'next305 query-only ticket reopens reader' => [static fn (): mixed => $plan()['next_reads'][3]['reader_cache_query_only_token_reason'], 'reader_ticket_reader_cache_query_only_predates_current_source'],
    'next306 foreign-key ticket reopens reader' => [static fn (): mixed => $plan()['next_reads'][3]['reader_cache_foreign_key_token_reason'], 'reader_ticket_reader_cache_foreign_key_predates_current_source'],
    'next307 defer-foreign-key ticket reopens reader' => [static fn (): mixed => $plan()['next_reads'][3]['reader_cache_defer_foreign_key_token_reason'], 'reader_ticket_reader_cache_defer_foreign_key_predates_current_source'],
    'next308 recursive-trigger ticket reopens reader' => [static fn (): mixed => $plan()['next_reads'][3]['reader_cache_recursive_trigger_token_reason'], 'reader_ticket_reader_cache_recursive_trigger_predates_current_source'],
    'next309 trusted-schema ticket reopens reader' => [static fn (): mixed => $plan()['next_reads'][3]['reader_cache_trusted_schema_token_reason'], 'reader_ticket_reader_cache_trusted_schema_predates_current_source'],
    'next310 ignore-check-constraints ticket reopens reader' => [static fn (): mixed => $plan()['next_reads'][3]['reader_cache_ignore_check_constraints_token_reason'], 'reader_ticket_reader_cache_ignore_check_constraints_predates_current_source'],
    'next311 application-id ticket reopens reader' => [static fn (): mixed => $plan()['next_reads'][3]['reader_cache_application_id_token_reason'], 'reader_ticket_reader_cache_application_id_predates_current_source'],
    'next312 user-version ticket reopens reader' => [static fn (): mixed => $plan()['next_reads'][3]['reader_cache_user_version_token_reason'], 'reader_ticket_reader_cache_user_version_predates_current_source'],
    'next313 schema-format ticket reopens reader' => [static fn (): mixed => $plan()['next_reads'][3]['reader_cache_schema_format_token_reason'], 'reader_ticket_reader_cache_schema_format_predates_current_source'],
    'next314 auto-vacuum-incremental ticket reopens reader' => [static fn (): mixed => $plan()['next_reads'][3]['reader_cache_auto_vacuum_incremental_token_reason'], 'reader_ticket_reader_cache_auto_vacuum_incremental_predates_current_source'],
    'combined invalidation set' => [static fn (): mixed => $plan()['invalidated_cache_page_numbers'], [1, 2, 3]],
    'read hits include savepoint-stack miss' => [static fn (): mixed => $plan()['read_cache_hits'], ['read-1' => false, 'read-2' => false, 'read-3' => false, 'read-4' => false]],
    'dependencies include next287' => [static fn (): mixed => in_array('sqlite-pager-master-journal-reader-cache-current-source-next287', $plan()['dependencies'], true), true],
    'dependencies include next314' => [static fn (): mixed => in_array('sqlite-pager-master-journal-reader-cache-current-source-next314', $plan()['dependencies'], true), true],
    'operation next295' => [static fn (): mixed => $opCount($plan(), 'reopen_reader_for_reader_cache_statement_journal_current_source_next295'), 1],
    'operation next296' => [static fn (): mixed => $opCount($plan(), 'reopen_reader_for_reader_cache_temp_page_current_source_next296'), 1],
    'operation next297' => [static fn (): mixed => $opCount($plan(), 'reopen_reader_for_reader_cache_dirty_list_current_source_next297'), 1],
    'operation next298' => [static fn (): mixed => $opCount($plan(), 'reopen_reader_for_reader_cache_spill_epoch_current_source_next298'), 1],
    'operation next299' => [static fn (): mixed => $opCount($plan(), 'reopen_reader_for_reader_cache_locking_mode_current_source_next299'), 1],
    'operation next300' => [static fn (): mixed => $opCount($plan(), 'reopen_reader_for_reader_cache_journal_mode_current_source_next300'), 1],
    'operation next301' => [static fn (): mixed => $opCount($plan(), 'reopen_reader_for_reader_cache_synchronous_current_source_next301'), 1],
    'operation next302' => [static fn (): mixed => $opCount($plan(), 'reopen_reader_for_reader_cache_mmap_size_current_source_next302'), 1],
    'operation next303' => [static fn (): mixed => $opCount($plan(), 'reopen_reader_for_reader_cache_cache_size_current_source_next303'), 1],
    'operation next304' => [static fn (): mixed => $opCount($plan(), 'reopen_reader_for_reader_cache_wal_autocheckpoint_current_source_next304'), 1],
    'operation next305' => [static fn (): mixed => $opCount($plan(), 'reopen_reader_for_reader_cache_query_only_current_source_next305'), 1],
    'operation next306' => [static fn (): mixed => $opCount($plan(), 'reopen_reader_for_reader_cache_foreign_key_current_source_next306'), 1],
    'operation next307' => [static fn (): mixed => $opCount($plan(), 'reopen_reader_for_reader_cache_defer_foreign_key_current_source_next307'), 1],
    'operation next308' => [static fn (): mixed => $opCount($plan(), 'reopen_reader_for_reader_cache_recursive_trigger_current_source_next308'), 1],
    'operation next309' => [static fn (): mixed => $opCount($plan(), 'reopen_reader_for_reader_cache_trusted_schema_current_source_next309'), 1],
    'operation next310' => [static fn (): mixed => $opCount($plan(), 'reopen_reader_for_reader_cache_ignore_check_constraints_current_source_next310'), 1],
    'operation next311' => [static fn (): mixed => $opCount($plan(), 'reopen_reader_for_reader_cache_application_id_current_source_next311'), 1],
    'operation next312' => [static fn (): mixed => $opCount($plan(), 'reopen_reader_for_reader_cache_user_version_current_source_next312'), 1],
    'operation next313' => [static fn (): mixed => $opCount($plan(), 'reopen_reader_for_reader_cache_schema_format_current_source_next313'), 1],
    'operation next314' => [static fn (): mixed => $opCount($plan(), 'reopen_reader_for_reader_cache_auto_vacuum_incremental_current_source_next314'), 1],
    'default plan points at next314' => [static fn (): mixed => $plan()['status'], 'pager-master-journal-reader-cache-current-source-next314'],
];

foreach ($cases as $name => [$callback, $expected]) {
    $tests['pager master journal reader cache current source next314 ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        $t->same($expected, $callback());
    };
}

$tests['pager master journal reader cache current source next314 missing token rejects'] = static function (TestRunner $t) use ($plan, $cacheEntry, $recovered): void {
    $t->throws(Throwable::class, static fn () => $plan([1 => array_diff_key($cacheEntry('missing', $recovered[1]), ['reader_cache_auto_vacuum_incremental_token' => true])]));
};

return $tests;

<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePagerMasterJournalReaderCacheCurrentSourceNextPlan;

$tests = [];

$pageSize = 512;
$database = '/srv/wp-content/database/wp-count-changes-fence.sqlite';
$usersDatabase = '/srv/wp-content/database/wp-count-changes-fence-users.sqlite';
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
    $page = substr_replace($page, pack('N', 326), 60, 4);

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

$before = [1 => $formatPage('count-changes-fence stale schema'), 2 => $page('count-changes-fence stale options'), 3 => $page('count-changes-fence stale plugins'), 4 => $page('count-changes-fence stale users')];
$recovered = [1 => $formatPage('count-changes-fence current schema'), 2 => $page('count-changes-fence current options'), 3 => $page('count-changes-fence current plugins'), 4 => $page('count-changes-fence current users')];
$tokens = [$mainJournal => 'member-main-current-326', $usersJournal => 'member-users-current-326'];
$headers = [$mainJournal => hash('sha256', 'main header count-changes-fence'), $usersJournal => hash('sha256', 'users header count-changes-fence')];
$base = [
    'source_id' => 'pager-reader-cache-count-changes-fence',
    'epoch' => 326,
    'format_signature' => hash('sha256', implode('|', [512, 4, 2, 326, 0])),
    'publication_generation' => 326,
    'master_source_digest' => hash('sha256', 'master-count-changes-fence'),
    'recovery_sequence' => 326,
    'recovered_page_set_digest' => $recoveredDigest($recovered),
    'member_journal_tokens' => $tokens,
    'member_journal_header_digests' => $headers,
    'master_member_order_digest' => hash('sha256', implode("\n", array_keys($tokens))),
    'master_journal_file_token' => 'master-file-current-326',
    'master_journal_bytes_digest' => hash('sha256', $masterBytes),
    'database_file_token' => 'database-file-current-326',
    'master_journal_cleanup_token' => 'master-cleanup-current-326',
    'reader_lease_token' => 'reader-lease-current-326',
    'pager_cache_source_token' => 'pager-cache-source-current-326',
    'read_transaction_token' => 'read-transaction-current-326',
    'schema_reparse_token' => 'schema-reparse-current-326',
    'statement_schema_root_token' => 'statement-schema-root-current-326',
    'current_source_provenance_token' => 'source-provenance-current-326',
    'pager_reader_cache_generation_token' => 'cache-generation-current-326',
    'reader_snapshot_token' => 'reader-snapshot-current-326',
    'master_journal_recovery_receipt_token' => 'recovery-receipt-current-326',
    'pager_spill_drain_token' => 'spill-drain-current-326',
    'rollback_journal_reader_source_token' => 'rollback-source-current-326',
    'pager_hot_journal_header_token' => 'hot-header-current-326',
    'master_journal_member_epoch_token' => 'member-epoch-current-326',
    'reader_cache_schema_cookie_token' => 'schema-cookie-current-326',
    'reader_cache_vacuum_root_token' => 'vacuum-root-current-326',
    'pager_reserved_lock_token' => 'reserved-lock-current-326',
    'reader_cache_page_count_token' => 'page-count-current-326',
    'reader_cache_schema_version_token' => 'schema-version-current-326',
    'reader_cache_change_counter_token' => 'change-counter-current-326',
    'reader_cache_freelist_trunk_token' => 'freelist-trunk-current-326',
    'reader_cache_auto_vacuum_token' => 'auto-vacuum-current-326',
    'reader_cache_encoding_token' => 'encoding-current-326',
    'reader_cache_text_schema_token' => 'text-schema-current-326',
    'reader_cache_index_schema_token' => 'index-schema-current-326',
    'reader_cache_trigger_schema_token' => 'trigger-schema-current-326',
    'reader_cache_view_schema_token' => 'view-schema-current-326',
    'reader_cache_virtual_table_schema_token' => 'virtual-table-schema-current-326',
    'reader_cache_module_schema_token' => 'module-schema-current-326',
    'reader_cache_pragma_schema_token' => 'pragma-schema-current-326',
    'reader_cache_collation_schema_token' => 'collation-schema-current-326',
    'reader_cache_authorizer_schema_token' => 'authorizer-schema-current-326',
    'reader_cache_transaction_state_token' => 'transaction-state-current-326',
    'reader_cache_commit_phase_token' => 'commit-phase-current-326',
    'reader_cache_busy_handler_token' => 'busy-handler-current-326',
    'reader_cache_savepoint_stack_token' => 'savepoint-stack-current-326',
    'reader_cache_statement_journal_token' => 'statement-journal-current-326',
    'reader_cache_temp_page_token' => 'temp-page-current-326',
    'reader_cache_dirty_list_token' => 'dirty-list-current-326',
    'reader_cache_spill_epoch_token' => 'spill-epoch-current-326',
    'reader_cache_locking_mode_token' => 'locking-mode-current-326',
    'reader_cache_journal_mode_token' => 'journal-mode-current-326',
    'reader_cache_synchronous_token' => 'synchronous-current-326',
    'reader_cache_mmap_size_token' => 'mmap-size-current-326',
    'reader_cache_cache_size_token' => 'cache-size-current-326',
    'reader_cache_wal_autocheckpoint_token' => 'wal-autocheckpoint-current-326',
    'reader_cache_query_only_token' => 'query-only-current-326',
    'reader_cache_foreign_key_token' => 'foreign-key-current-326',
    'reader_cache_defer_foreign_key_token' => 'defer-foreign-key-current-326',
    'reader_cache_recursive_trigger_token' => 'recursive-trigger-current-326',
    'reader_cache_trusted_schema_token' => 'trusted-schema-current-326',
    'reader_cache_ignore_check_constraints_token' => 'ignore-check-constraints-current-326',
    'reader_cache_application_id_token' => 'application-id-current-326',
    'reader_cache_user_version_token' => 'user-version-current-326',
    'reader_cache_data_version_token' => 'data-version-current-326',
    'reader_cache_schema_lock_token' => 'schema-lock-current-326',
    'reader_cache_schema_format_token' => 'schema-format-current-326',
    'reader_cache_auto_vacuum_incremental_token' => 'auto-vacuum-incremental-current-326',
    'reader_cache_reverse_unordered_selects_token' => 'reverse-unordered-selects-current-326',
    'reader_cache_automatic_index_token' => 'automatic-index-current-326',
    'reader_cache_case_sensitive_like_token' => 'case-sensitive-like-current-326',
    'reader_cache_count_changes_token' => 'count-changes-current-326',
    'reader_cache_secure_delete_token' => 'secure-delete-current-326',
    'reader_cache_temp_store_token' => 'temp-store-current-326',
    'reader_cache_cache_spill_token' => 'cache-spill-current-326',
    'reader_cache_cell_size_check_token' => 'cell-size-check-current-326',
];
$cacheEntry = static fn (string $label, string $image, array $extra = []): array => array_merge($base, ['label' => $label, 'reader_id' => $label . '-reader', 'image' => $image], $extra);
$read = static fn (int $pageNumber, array $extra = []): array => array_merge([
    'reader_id' => 'read-' . $pageNumber,
    'page_number' => $pageNumber,
    'source_id' => $base['source_id'],
    'epoch' => 326,
    'format_signature' => $base['format_signature'],
    'publication_generation' => 326,
    'master_source_digest' => $base['master_source_digest'],
    'recovery_sequence' => 326,
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
    'reader_cache_data_version_token' => $base['reader_cache_data_version_token'],
    'reader_cache_schema_lock_token' => $base['reader_cache_schema_lock_token'],
    'reader_cache_schema_format_token' => $base['reader_cache_schema_format_token'],
    'reader_cache_auto_vacuum_incremental_token' => $base['reader_cache_auto_vacuum_incremental_token'],
    'reader_cache_reverse_unordered_selects_token' => $base['reader_cache_reverse_unordered_selects_token'],
    'reader_cache_automatic_index_token' => $base['reader_cache_automatic_index_token'],
    'reader_cache_case_sensitive_like_token' => $base['reader_cache_case_sensitive_like_token'],
    'reader_cache_count_changes_token' => $base['reader_cache_count_changes_token'],
    'reader_cache_secure_delete_token' => $base['reader_cache_secure_delete_token'],
    'reader_cache_temp_store_token' => $base['reader_cache_temp_store_token'],
    'reader_cache_cache_spill_token' => $base['reader_cache_cache_spill_token'],
    'reader_cache_cell_size_check_token' => $base['reader_cache_cell_size_check_token'],
], $extra);
$plan = static function (?array $cache = null, ?array $reads = null) use ($database, $master, $masterBytes, $before, $pageSize, $recovered, $base, $tokens, $headers, $cacheEntry, $read): array {
    return SQLitePagerMasterJournalReaderCacheCurrentSourceNextPlan::currentSourceReaderCacheCountChangesFence($database, $master, $masterBytes, implode('', $before), $pageSize, $recovered, $cache ?? [
        1 => $cacheEntry('schema-stale-module', $recovered[1], ['reader_cache_module_schema_token' => 'module-schema-old']),
        2 => $cacheEntry('options-stale-pragma', $recovered[2], ['reader_cache_pragma_schema_token' => 'pragma-schema-old']),
        3 => $cacheEntry('plugins-stale-collation', $recovered[3], ['reader_cache_collation_schema_token' => 'collation-schema-old']),
        4 => $cacheEntry('users-stale-secure-delete', $recovered[4], ['reader_cache_secure_delete_token' => 'secure-delete-old']),
    ], $reads ?? [$read(1), $read(2), $read(3), $read(4, ['reader_cache_savepoint_stack_token' => 'savepoint-stack-old', 'reader_cache_statement_journal_token' => 'statement-journal-old', 'reader_cache_temp_page_token' => 'temp-page-old', 'reader_cache_dirty_list_token' => 'dirty-list-old', 'reader_cache_spill_epoch_token' => 'spill-epoch-old', 'reader_cache_locking_mode_token' => 'locking-mode-old', 'reader_cache_journal_mode_token' => 'journal-mode-old', 'reader_cache_synchronous_token' => 'synchronous-old', 'reader_cache_mmap_size_token' => 'mmap-size-old', 'reader_cache_cache_size_token' => 'cache-size-old', 'reader_cache_wal_autocheckpoint_token' => 'wal-autocheckpoint-old', 'reader_cache_query_only_token' => 'query-only-old', 'reader_cache_foreign_key_token' => 'foreign-key-old', 'reader_cache_defer_foreign_key_token' => 'defer-foreign-key-old', 'reader_cache_recursive_trigger_token' => 'recursive-trigger-old', 'reader_cache_trusted_schema_token' => 'trusted-schema-old', 'reader_cache_ignore_check_constraints_token' => 'ignore-check-constraints-old', 'reader_cache_application_id_token' => 'application-id-old', 'reader_cache_user_version_token' => 'user-version-old', 'reader_cache_data_version_token' => 'data-version-old', 'reader_cache_schema_lock_token' => 'schema-lock-old', 'reader_cache_secure_delete_token' => 'secure-delete-old', 'reader_cache_temp_store_token' => 'temp-store-old', 'reader_cache_cache_spill_token' => 'cache-spill-old', 'reader_cache_cell_size_check_token' => 'cell-size-check-old', 'reader_cache_reverse_unordered_selects_token' => 'reverse-unordered-selects-old', 'reader_cache_automatic_index_token' => 'automatic-index-old', 'reader_cache_case_sensitive_like_token' => 'case-sensitive-like-old', 'reader_cache_count_changes_token' => 'count-changes-old'])], $base['source_id'], 326, 326, $base['master_source_digest'], 326, $tokens, $headers, $base['master_journal_file_token'], $base['database_file_token'], $base['master_journal_cleanup_token'], $base['reader_lease_token'], $base['pager_cache_source_token'], $base['read_transaction_token'], $base['schema_reparse_token'], $base['statement_schema_root_token'], $base['current_source_provenance_token'], $base['pager_reader_cache_generation_token'], $base['reader_snapshot_token'], $base['master_journal_recovery_receipt_token'], $base['pager_spill_drain_token'], $base['rollback_journal_reader_source_token'], $base['pager_hot_journal_header_token'], $base['master_journal_member_epoch_token'], $base['reader_cache_schema_cookie_token'], $base['reader_cache_vacuum_root_token'], $base['pager_reserved_lock_token'], $base['reader_cache_page_count_token'], $base['reader_cache_schema_version_token'], $base['reader_cache_change_counter_token'], $base['reader_cache_freelist_trunk_token'], $base['reader_cache_auto_vacuum_token'], $base['reader_cache_encoding_token'], $base['reader_cache_text_schema_token'], $base['reader_cache_index_schema_token'], $base['reader_cache_trigger_schema_token'], $base['reader_cache_view_schema_token'], $base['reader_cache_virtual_table_schema_token'], $base['reader_cache_module_schema_token'], $base['reader_cache_pragma_schema_token'], $base['reader_cache_collation_schema_token'], $base['reader_cache_authorizer_schema_token'], $base['reader_cache_transaction_state_token'], $base['reader_cache_commit_phase_token'], $base['reader_cache_busy_handler_token'], $base['reader_cache_savepoint_stack_token'], $base['reader_cache_statement_journal_token'], $base['reader_cache_temp_page_token'], $base['reader_cache_dirty_list_token'], $base['reader_cache_spill_epoch_token'], $base['reader_cache_locking_mode_token'], $base['reader_cache_journal_mode_token'], $base['reader_cache_synchronous_token'], $base['reader_cache_mmap_size_token'], $base['reader_cache_cache_size_token'], $base['reader_cache_wal_autocheckpoint_token'], $base['reader_cache_query_only_token'], $base['reader_cache_foreign_key_token'], $base['reader_cache_defer_foreign_key_token'], $base['reader_cache_recursive_trigger_token'], $base['reader_cache_trusted_schema_token'], $base['reader_cache_ignore_check_constraints_token'], $base['reader_cache_application_id_token'], $base['reader_cache_user_version_token'], $base['reader_cache_data_version_token'], $base['reader_cache_schema_lock_token'], $base['reader_cache_secure_delete_token'], $base['reader_cache_temp_store_token'], $base['reader_cache_cache_spill_token'], $base['reader_cache_cell_size_check_token'], $base['reader_cache_reverse_unordered_selects_token'], $base['reader_cache_automatic_index_token'], $base['reader_cache_case_sensitive_like_token'], $base['reader_cache_count_changes_token']);
};
$opCount = static fn (array $plan, string $op): int => count(array_filter($plan['operations'], static fn (array $operation): bool => ($operation['op'] ?? '') === $op));

$cases = [
    'status' => [static fn (): mixed => $plan()['status'], 'pager-master-journal-reader-cache-current-source-count-changes-fence'],
    'next287 module-schema invalidation' => [static fn (): mixed => $plan()['reader_cache_module_schema_invalidated_cache_page_numbers'], [1]],
    'next288 pragma-schema invalidation' => [static fn (): mixed => $plan()['reader_cache_pragma_schema_invalidated_cache_page_numbers'], [2]],
    'next289 collation-schema invalidation' => [static fn (): mixed => $plan()['reader_cache_collation_schema_invalidated_cache_page_numbers'], [3]],
    'next291 transaction-state fence is current' => [static fn (): mixed => $plan()['current_reader_cache_transaction_state_token'], 'transaction-state-current-326'],
    'next292 commit-phase fence is current' => [static fn (): mixed => $plan()['current_reader_cache_commit_phase_token'], 'commit-phase-current-326'],
    'next293 busy-handler fence is current' => [static fn (): mixed => $plan()['current_reader_cache_busy_handler_token'], 'busy-handler-current-326'],
    'next294 savepoint-stack ticket reopens reader' => [static fn (): mixed => $plan()['next_reads'][3]['reader_cache_savepoint_stack_token_reason'], 'reader_ticket_reader_cache_savepoint_stack_predates_current_source'],
    'next295 statement-journal ticket reopens reader' => [static fn (): mixed => $plan()['next_reads'][3]['reader_cache_statement_journal_token_reason'], 'reader_ticket_reader_cache_statement_journal_predates_current_source'],
    'next296 temp-page ticket reopens reader' => [static fn (): mixed => $plan()['next_reads'][3]['reader_cache_temp_page_token_reason'], 'reader_ticket_reader_cache_temp_page_predates_current_source'],
    'next297 dirty-list ticket reopens reader' => [static fn (): mixed => $plan()['next_reads'][3]['reader_cache_dirty_list_token_reason'], 'reader_ticket_reader_cache_dirty_list_predates_current_source'],
    'next298 spill-epoch ticket reopens reader' => [static fn (): mixed => $plan()['next_reads'][3]['reader_cache_spill_epoch_token_reason'], 'reader_ticket_reader_cache_spill_epoch_predates_current_source'],
    'next299 locking-mode ticket reopens reader' => [static fn (): mixed => $plan()['next_reads'][3]['reader_cache_locking_mode_token_reason'], 'reader_ticket_reader_cache_locking_mode_predates_current_source'],
    'next300 journal-mode ticket reopens reader' => [static fn (): mixed => $plan()['next_reads'][3]['reader_cache_journal_mode_token_reason'], 'reader_ticket_reader_cache_journal_mode_predates_current_source'],
    'next301 synchronous ticket reopens reader' => [static fn (): mixed => $plan()['next_reads'][3]['reader_cache_synchronous_token_reason'], 'reader_ticket_reader_cache_synchronous_predates_current_source'],
    'mmap-size fence ticket reopens reader' => [static fn (): mixed => $plan()['next_reads'][3]['reader_cache_mmap_size_token_reason'], 'reader_ticket_reader_cache_mmap_size_predates_current_source'],
    'next303 cache-size ticket reopens reader' => [static fn (): mixed => $plan()['next_reads'][3]['reader_cache_cache_size_token_reason'], 'reader_ticket_reader_cache_cache_size_predates_current_source'],
    'next304 wal-autocheckpoint ticket reopens reader' => [static fn (): mixed => $plan()['next_reads'][3]['reader_cache_wal_autocheckpoint_token_reason'], 'reader_ticket_reader_cache_wal_autocheckpoint_predates_current_source'],
    'next305 query-only ticket reopens reader' => [static fn (): mixed => $plan()['next_reads'][3]['reader_cache_query_only_token_reason'], 'reader_ticket_reader_cache_query_only_predates_current_source'],
    'foreign-key fence ticket reopens reader' => [static fn (): mixed => $plan()['next_reads'][3]['reader_cache_foreign_key_token_reason'], 'reader_ticket_reader_cache_foreign_key_predates_current_source'],
    'next307 defer-foreign-key ticket reopens reader' => [static fn (): mixed => $plan()['next_reads'][3]['reader_cache_defer_foreign_key_token_reason'], 'reader_ticket_reader_cache_defer_foreign_key_predates_current_source'],
    'next308 recursive-trigger ticket reopens reader' => [static fn (): mixed => $plan()['next_reads'][3]['reader_cache_recursive_trigger_token_reason'], 'reader_ticket_reader_cache_recursive_trigger_predates_current_source'],
    'next309 trusted-schema ticket reopens reader' => [static fn (): mixed => $plan()['next_reads'][3]['reader_cache_trusted_schema_token_reason'], 'reader_ticket_reader_cache_trusted_schema_predates_current_source'],
    'next310 ignore-check-constraints ticket reopens reader' => [static fn (): mixed => $plan()['next_reads'][3]['reader_cache_ignore_check_constraints_token_reason'], 'reader_ticket_reader_cache_ignore_check_constraints_predates_current_source'],
    'next315 application-id ticket reopens reader' => [static fn (): mixed => $plan()['next_reads'][3]['reader_cache_application_id_token_reason'], 'reader_ticket_reader_cache_application_id_predates_current_source'],
    'next316 user-version ticket reopens reader' => [static fn (): mixed => $plan()['next_reads'][3]['reader_cache_user_version_token_reason'], 'reader_ticket_reader_cache_user_version_predates_current_source'],
    'next317 data-version ticket reopens reader' => [static fn (): mixed => $plan()['next_reads'][3]['reader_cache_data_version_token_reason'], 'reader_ticket_reader_cache_data_version_predates_current_source'],
    'schema-lock fence ticket reopens reader' => [static fn (): mixed => $plan()['next_reads'][3]['reader_cache_schema_lock_token_reason'], 'reader_ticket_reader_cache_schema_lock_predates_current_source'],
    'next319 secure-delete invalidation' => [static fn (): mixed => $plan()['reader_cache_secure_delete_invalidated_cache_page_numbers'], [4]],
    'next320 temp-store ticket reopens reader' => [static fn (): mixed => $plan()['next_reads'][3]['reader_cache_temp_store_token_reason'], 'reader_ticket_reader_cache_temp_store_predates_current_source'],
    'next321 cache-spill ticket reopens reader' => [static fn (): mixed => $plan()['next_reads'][3]['reader_cache_cache_spill_token_reason'], 'reader_ticket_reader_cache_cache_spill_predates_current_source'],
    'cell-size-check fence ticket reopens reader' => [static fn (): mixed => $plan()['next_reads'][3]['reader_cache_cell_size_check_token_reason'], 'reader_ticket_reader_cache_cell_size_check_predates_current_source'],
    'next323 reverse-unordered-selects ticket reopens reader' => [static fn (): mixed => $plan()['next_reads'][3]['reader_cache_reverse_unordered_selects_token_reason'], 'reader_ticket_reader_cache_reverse_unordered_selects_predates_current_source'],
    'next324 automatic-index ticket reopens reader' => [static fn (): mixed => $plan()['next_reads'][3]['reader_cache_automatic_index_token_reason'], 'reader_ticket_reader_cache_automatic_index_predates_current_source'],
    'next325 case-sensitive-like ticket reopens reader' => [static fn (): mixed => $plan()['next_reads'][3]['reader_cache_case_sensitive_like_token_reason'], 'reader_ticket_reader_cache_case_sensitive_like_predates_current_source'],
    'count-changes-fence count-changes ticket reopens reader' => [static fn (): mixed => $plan()['next_reads'][3]['reader_cache_count_changes_token_reason'], 'reader_ticket_reader_cache_count_changes_predates_current_source'],
    'combined invalidation set' => [static fn (): mixed => $plan()['invalidated_cache_page_numbers'], [1, 2, 3, 4]],
    'read hits include savepoint-stack miss' => [static fn (): mixed => $plan()['read_cache_hits'], ['read-1' => false, 'read-2' => false, 'read-3' => false, 'read-4' => false]],
    'dependencies include next287' => [static fn (): mixed => in_array('sqlite-pager-master-journal-reader-cache-current-source-next287', $plan()['dependencies'], true), true],
    'dependencies include count-changes-fence' => [static fn (): mixed => in_array('sqlite-pager-master-journal-reader-cache-current-source-count-changes-fence', $plan()['dependencies'], true), true],
    'operation next295' => [static fn (): mixed => $opCount($plan(), 'reopen_reader_for_reader_cache_statement_journal_current_source_next295'), 1],
    'operation next296' => [static fn (): mixed => $opCount($plan(), 'reopen_reader_for_reader_cache_temp_page_current_source_next296'), 1],
    'operation next297' => [static fn (): mixed => $opCount($plan(), 'reopen_reader_for_reader_cache_dirty_list_current_source_next297'), 1],
    'operation next298' => [static fn (): mixed => $opCount($plan(), 'reopen_reader_for_reader_cache_spill_epoch_current_source_next298'), 1],
    'operation next299' => [static fn (): mixed => $opCount($plan(), 'reopen_reader_for_reader_cache_locking_mode_current_source_next299'), 1],
    'operation next300' => [static fn (): mixed => $opCount($plan(), 'reopen_reader_for_reader_cache_journal_mode_current_source_next300'), 1],
    'operation next301' => [static fn (): mixed => $opCount($plan(), 'reopen_reader_for_reader_cache_synchronous_current_source_next301'), 1],
    'operation mmap-size fence' => [static fn (): mixed => $opCount($plan(), 'reopen_reader_for_reader_cache_mmap_size_current_source_mmap-size-fence'), 1],
    'operation next303' => [static fn (): mixed => $opCount($plan(), 'reopen_reader_for_reader_cache_cache_size_current_source_next303'), 1],
    'operation next304' => [static fn (): mixed => $opCount($plan(), 'reopen_reader_for_reader_cache_wal_autocheckpoint_current_source_next304'), 1],
    'operation next305' => [static fn (): mixed => $opCount($plan(), 'reopen_reader_for_reader_cache_query_only_current_source_next305'), 1],
    'operation foreign-key fence' => [static fn (): mixed => $opCount($plan(), 'reopen_reader_for_reader_cache_foreign_key_current_source_foreign-key-fence'), 1],
    'operation next307' => [static fn (): mixed => $opCount($plan(), 'reopen_reader_for_reader_cache_defer_foreign_key_current_source_next307'), 1],
    'operation next308' => [static fn (): mixed => $opCount($plan(), 'reopen_reader_for_reader_cache_recursive_trigger_current_source_next308'), 1],
    'operation next309' => [static fn (): mixed => $opCount($plan(), 'reopen_reader_for_reader_cache_trusted_schema_current_source_next309'), 1],
    'operation next310' => [static fn (): mixed => $opCount($plan(), 'reopen_reader_for_reader_cache_ignore_check_constraints_current_source_next310'), 1],
    'operation next315' => [static fn (): mixed => $opCount($plan(), 'reopen_reader_for_reader_cache_application_id_current_source_next315'), 1],
    'operation next316' => [static fn (): mixed => $opCount($plan(), 'reopen_reader_for_reader_cache_user_version_current_source_next316'), 1],
    'operation next317' => [static fn (): mixed => $opCount($plan(), 'reopen_reader_for_reader_cache_data_version_current_source_next317'), 1],
    'operation schema-lock fence' => [static fn (): mixed => $opCount($plan(), 'reopen_reader_for_reader_cache_schema_lock_current_source_schema-lock-fence'), 1],
    'operation next319' => [static fn (): mixed => $opCount($plan(), 'reopen_reader_for_reader_cache_secure_delete_current_source_next319'), 1],
    'operation next320' => [static fn (): mixed => $opCount($plan(), 'reopen_reader_for_reader_cache_temp_store_current_source_next320'), 1],
    'operation next321' => [static fn (): mixed => $opCount($plan(), 'reopen_reader_for_reader_cache_cache_spill_current_source_next321'), 1],
    'operation cell-size-check fence' => [static fn (): mixed => $opCount($plan(), 'reopen_reader_for_reader_cache_cell_size_check_current_source_cell-size-check-fence'), 1],
    'operation next323' => [static fn (): mixed => $opCount($plan(), 'reopen_reader_for_reader_cache_reverse_unordered_selects_current_source_next323'), 1],
    'operation next324' => [static fn (): mixed => $opCount($plan(), 'reopen_reader_for_reader_cache_automatic_index_current_source_next324'), 1],
    'operation next325' => [static fn (): mixed => $opCount($plan(), 'reopen_reader_for_reader_cache_case_sensitive_like_current_source_next325'), 1],
    'operation count-changes-fence' => [static fn (): mixed => $opCount($plan(), 'reopen_reader_for_reader_cache_count_changes_current_source_count-changes-fence'), 1],
    'default plan points at count-changes-fence' => [static fn (): mixed => $plan()['status'], 'pager-master-journal-reader-cache-current-source-count-changes-fence'],
];

foreach ($cases as $name => [$callback, $expected]) {
    $tests['pager master journal reader cache count changes fence ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        $t->same($expected, $callback());
    };
}

$tests['pager master journal reader cache count changes fence missing token rejects'] = static function (TestRunner $t) use ($plan, $cacheEntry, $recovered): void {
    $t->throws(Throwable::class, static fn () => $plan([1 => array_diff_key($cacheEntry('missing', $recovered[1]), ['reader_cache_cell_size_check_token' => true])]));
};

return $tests;

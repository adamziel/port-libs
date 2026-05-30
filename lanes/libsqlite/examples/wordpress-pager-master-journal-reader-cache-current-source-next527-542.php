<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePagerMasterJournalReaderCacheCurrentSourceNextPlan;

require_once __DIR__ . '/../src/SQLitePagerMasterJournalReaderCacheCurrentSourceNextPlan.php';

$pageSize = 512;
$database = '/srv/wp-content/database/wp-next542.sqlite';
$journal = $database . '-journal';
$master = $database . '-mj';
$masterBytes = $journal . "\n";
$page = static fn (string $label): string => str_pad($label, $pageSize, '.', STR_PAD_RIGHT);
$formatPage = static function (string $label) use ($pageSize): string {
    $page = str_pad('SQLite format 3' . "\0", 100, "\0", STR_PAD_RIGHT) . str_repeat('.', $pageSize - 100);
    $page = substr_replace($page, pack('n', 512), 16, 2);
    $page = substr_replace($page, chr(4), 20, 1);
    $page = substr_replace($page, pack('N', 2), 56, 4);
    $page = substr_replace($page, pack('N', 542), 60, 4);

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
$tokenFields = [
    'master_journal_file_token', 'database_file_token', 'master_journal_cleanup_token', 'reader_lease_token', 'pager_cache_source_token', 'read_transaction_token',
    'schema_reparse_token', 'statement_schema_root_token', 'current_source_provenance_token', 'pager_reader_cache_generation_token', 'reader_snapshot_token',
    'master_journal_recovery_receipt_token', 'pager_spill_drain_token', 'rollback_journal_reader_source_token', 'pager_hot_journal_header_token',
    'master_journal_member_epoch_token', 'reader_cache_schema_cookie_token', 'reader_cache_vacuum_root_token', 'pager_reserved_lock_token',
    'reader_cache_page_count_token', 'reader_cache_schema_version_token', 'reader_cache_change_counter_token', 'reader_cache_freelist_trunk_token',
    'reader_cache_auto_vacuum_token', 'reader_cache_encoding_token', 'reader_cache_text_schema_token', 'reader_cache_index_schema_token',
    'reader_cache_trigger_schema_token', 'reader_cache_view_schema_token', 'reader_cache_virtual_table_schema_token', 'reader_cache_module_schema_token',
    'reader_cache_pragma_schema_token', 'reader_cache_collation_schema_token', 'reader_cache_authorizer_schema_token', 'reader_cache_transaction_state_token',
    'reader_cache_commit_phase_token', 'reader_cache_busy_handler_token', 'reader_cache_savepoint_stack_token', 'reader_cache_statement_journal_token',
    'reader_cache_temp_page_token', 'reader_cache_dirty_list_token', 'reader_cache_spill_epoch_token', 'reader_cache_locking_mode_token',
    'reader_cache_journal_mode_token', 'reader_cache_synchronous_token', 'reader_cache_mmap_size_token', 'reader_cache_cache_size_token',
    'reader_cache_wal_autocheckpoint_token', 'reader_cache_query_only_token', 'reader_cache_foreign_key_token', 'reader_cache_defer_foreign_key_token',
    'reader_cache_recursive_trigger_token', 'reader_cache_trusted_schema_token', 'reader_cache_ignore_check_constraints_token', 'reader_cache_application_id_token',
    'reader_cache_user_version_token', 'reader_cache_data_version_token', 'reader_cache_schema_lock_token', 'reader_cache_secure_delete_token',
    'reader_cache_temp_store_token', 'reader_cache_cache_spill_token', 'reader_cache_cell_size_check_token', 'reader_cache_reverse_unordered_selects_token',
    'reader_cache_automatic_index_token', 'reader_cache_case_sensitive_like_token', 'reader_cache_count_changes_token', 'reader_cache_checkpoint_fullfsync_token',
    'reader_cache_fullfsync_token', 'reader_cache_legacy_file_format_token', 'reader_cache_read_uncommitted_token', 'reader_cache_reverse_scan_order_token',
    'reader_cache_defensive_token', 'reader_cache_writable_schema_token', 'reader_cache_journal_size_limit_token', 'reader_cache_threads_token',
    'reader_cache_optimize_token', 'reader_cache_analysis_limit_token', 'reader_cache_hard_heap_limit_token', 'reader_cache_soft_heap_limit_token',
    'reader_cache_page_size_token', 'reader_cache_max_page_count_token', 'reader_cache_locking_proxy_file_token', 'reader_cache_page_cache_overflow_token',
    'reader_cache_scratch_allocator_token', 'reader_cache_lookaside_token', 'reader_cache_pcache_dirty_limit_token', 'reader_cache_mmap_read_limit_token',
    'reader_cache_sorter_reference_token', 'reader_cache_worker_thread_token', 'reader_cache_memory_alarm_token', 'reader_cache_status_counter_token',
    'reader_cache_pagecache_size_token', 'reader_cache_pagecache_recycle_token', 'reader_cache_scratch_spill_token', 'reader_cache_lookaside_hit_token',
    'reader_cache_lookaside_miss_size_token', 'reader_cache_pcache_refcount_token', 'reader_cache_memory_used_token', 'reader_cache_memory_highwater_token',
    'reader_cache_pagecache_used_token', 'reader_cache_pagecache_overflow_token', 'reader_cache_scratch_used_token', 'reader_cache_scratch_overflow_token',
    'reader_cache_malloc_size_token', 'reader_cache_malloc_count_token', 'reader_cache_stmt_used_token', 'reader_cache_stmt_busy_token',
    'reader_cache_stmt_memused_token', 'reader_cache_stmt_scanstatus_token', 'reader_cache_stmt_reprepare_token', 'reader_cache_stmt_run_token',
    'reader_cache_stmt_sort_token', 'reader_cache_stmt_autoindex_token', 'reader_cache_stmt_fullscan_token', 'reader_cache_stmt_vmstep_token',
    'reader_cache_stmt_filter_hit_token', 'reader_cache_stmt_filter_miss_token', 'reader_cache_stmt_nsort_token', 'reader_cache_stmt_nautoindex_token',
    'reader_cache_stmt_nfullscan_token', 'reader_cache_stmt_expired_token', 'reader_cache_stmt_readonly_token', 'reader_cache_stmt_scanstatus_nloop_token',
    'reader_cache_stmt_scanstatus_nvisit_token', 'reader_cache_stmt_scanstatus_est_token', 'reader_cache_stmt_scanstatus_name_token',
    'reader_cache_stmt_scanstatus_explain_token', 'reader_cache_stmt_scanstatus_selectid_token', 'reader_cache_stmt_scanstatus_parentid_token',
    'reader_cache_stmt_scanstatus_ncycle_token', 'reader_cache_stmt_vm_halt_token', 'reader_cache_stmt_vm_reset_token', 'reader_cache_stmt_bind_parameter_token',
    'reader_cache_stmt_expanded_sql_token', 'reader_cache_stmt_normalized_sql_token', 'reader_cache_stmt_sql_text_token', 'reader_cache_stmt_readonly_schema_token',
    'reader_cache_stmt_busy_state_token', 'reader_cache_stmt_isexplain_token', 'reader_cache_stmt_explain_mode_token', 'reader_cache_stmt_vdbe_debug_token',
    'reader_cache_stmt_vdbe_listing_token', 'reader_cache_stmt_vdbe_trace_token', 'reader_cache_stmt_vdbe_addoptrace_token', 'reader_cache_stmt_vdbe_eqptrace_token',
    'reader_cache_stmt_vdbe_coverage_token', 'reader_cache_stmt_vdbe_comment_token', 'reader_cache_stmt_vdbe_profile_token', 'reader_cache_stmt_vdbe_scanstatus_token',
    'reader_cache_stmt_vdbe_reprep_token', 'reader_cache_stmt_vdbe_run_token', 'reader_cache_stmt_vdbe_yield_token', 'reader_cache_stmt_vdbe_pause_token',
    'reader_cache_stmt_vdbe_reset_token', 'reader_cache_stmt_vdbe_finalize_token', 'reader_cache_stmt_vdbe_transfer_token', 'reader_cache_stmt_vdbe_cursor_token',
    'reader_cache_stmt_vdbe_sorter_token', 'reader_cache_stmt_vdbe_auxdata_token', 'reader_cache_stmt_vdbe_mem_token', 'reader_cache_stmt_vdbe_memtype_token',
    'reader_cache_stmt_vdbe_column_token', 'reader_cache_stmt_vdbe_rowid_token', 'reader_cache_stmt_vdbe_seek_token', 'reader_cache_stmt_vdbe_btree_cursor_token',
    'reader_cache_stmt_vdbe_index_cursor_token', 'reader_cache_stmt_vdbe_ephemeral_cursor_token', 'reader_cache_stmt_vdbe_open_cursor_token',
    'reader_cache_stmt_vdbe_close_cursor_token', 'reader_cache_stmt_vdbe_rewind_token', 'reader_cache_stmt_vdbe_next_token',
    'reader_cache_stmt_vdbe_prev_token', 'reader_cache_stmt_vdbe_nextifopen_token', 'reader_cache_stmt_vdbe_previfopen_token',
    'reader_cache_stmt_vdbe_sorter_next_token', 'reader_cache_stmt_vdbe_seeklt_token', 'reader_cache_stmt_vdbe_seekle_token',
    'reader_cache_stmt_vdbe_seekge_token', 'reader_cache_stmt_vdbe_seekgt_token', 'reader_cache_stmt_vdbe_seekscan_token',
    'reader_cache_stmt_vdbe_notfound_token', 'reader_cache_stmt_vdbe_found_token', 'reader_cache_stmt_vdbe_notexists_token',
    'reader_cache_stmt_vdbe_last_token', 'reader_cache_stmt_vdbe_ifnosuchrow_token', 'reader_cache_stmt_vdbe_deferred_seek_token',
    'reader_cache_stmt_vdbe_moveto_token', 'reader_cache_stmt_vdbe_index_rowid_token', 'reader_cache_stmt_vdbe_rowset_read_token',
    'reader_cache_stmt_vdbe_rowset_test_token', 'reader_cache_stmt_vdbe_rowset_add_token', 'reader_cache_stmt_vdbe_idx_insert_token',
    'reader_cache_stmt_vdbe_idx_delete_token', 'reader_cache_stmt_vdbe_idx_rowid_token', 'reader_cache_stmt_vdbe_idx_ge_token',
    'reader_cache_stmt_vdbe_idx_gt_token', 'reader_cache_stmt_vdbe_idx_le_token', 'reader_cache_stmt_vdbe_idx_lt_token',
    'reader_cache_stmt_vdbe_idx_keyinfo_token', 'reader_cache_stmt_vdbe_open_read_token', 'reader_cache_stmt_vdbe_open_write_token',
    'reader_cache_stmt_vdbe_open_dup_token', 'reader_cache_stmt_vdbe_open_pseudo_token', 'reader_cache_stmt_vdbe_open_ephemeral_token',
    'reader_cache_stmt_vdbe_sorter_open_token', 'reader_cache_stmt_vdbe_sequence_token', 'reader_cache_stmt_vdbe_newrowid_token',
    'reader_cache_stmt_vdbe_insert_token', 'reader_cache_stmt_vdbe_delete_token', 'reader_cache_stmt_vdbe_rowdata_token',
    'reader_cache_stmt_vdbe_column_metadata_token', 'reader_cache_stmt_vdbe_make_record_token', 'reader_cache_stmt_vdbe_affinity_token',
    'reader_cache_stmt_vdbe_typecheck_token', 'reader_cache_stmt_vdbe_constraint_token', 'reader_cache_stmt_vdbe_conflict_token',
    'reader_cache_stmt_vdbe_fk_check_token', 'reader_cache_stmt_vdbe_returning_token',
    'reader_cache_stmt_vdbe_program_token', 'reader_cache_stmt_vdbe_param_token', 'reader_cache_stmt_vdbe_variable_token',
    'reader_cache_stmt_vdbe_copy_token', 'reader_cache_stmt_vdbe_scopy_token', 'reader_cache_stmt_vdbe_intcopy_token',
    'reader_cache_stmt_vdbe_result_row_token', 'reader_cache_stmt_vdbe_collseq_token', 'reader_cache_stmt_vdbe_function_token',
    'reader_cache_stmt_vdbe_agg_step_token', 'reader_cache_stmt_vdbe_agg_final_token', 'reader_cache_stmt_vdbe_real_token',
    'reader_cache_stmt_vdbe_real_affinity_token', 'reader_cache_stmt_vdbe_cast_token', 'reader_cache_stmt_vdbe_permutation_token',
    'reader_cache_stmt_vdbe_compare_token', 'reader_cache_stmt_vdbe_jump_token', 'reader_cache_stmt_vdbe_once_token',
    'reader_cache_stmt_vdbe_if_token', 'reader_cache_stmt_vdbe_ifnot_token', 'reader_cache_stmt_vdbe_isnull_token',
    'reader_cache_stmt_vdbe_notnull_token', 'reader_cache_stmt_vdbe_ne_token', 'reader_cache_stmt_vdbe_eq_token',
    'reader_cache_stmt_vdbe_gt_token', 'reader_cache_stmt_vdbe_le_token', 'reader_cache_stmt_vdbe_lt_token',
    'reader_cache_stmt_vdbe_ge_token', 'reader_cache_stmt_vdbe_else_eq_token', 'reader_cache_stmt_vdbe_zero_or_null_token',
    'reader_cache_stmt_vdbe_seek_hit_token', 'reader_cache_stmt_vdbe_if_not_open_token',
    'reader_cache_stmt_vdbe_not_open_token', 'reader_cache_stmt_vdbe_if_open_token', 'reader_cache_stmt_vdbe_transaction_token', 'reader_cache_stmt_vdbe_auto_commit_token',
    'reader_cache_stmt_vdbe_savepoint_token', 'reader_cache_stmt_vdbe_checkpoint_token', 'reader_cache_stmt_vdbe_journal_mode_token', 'reader_cache_stmt_vdbe_vacuum_token',
    'reader_cache_stmt_vdbe_incr_vacuum_token', 'reader_cache_stmt_vdbe_expire_token', 'reader_cache_stmt_vdbe_table_lock_token', 'reader_cache_stmt_vdbe_vbegin_token',
    'reader_cache_stmt_vdbe_vcreate_token', 'reader_cache_stmt_vdbe_vdestroy_token', 'reader_cache_stmt_vdbe_vopen_token', 'reader_cache_stmt_vdbe_vfilter_token',
    'reader_cache_stmt_vdbe_vcolumn_token', 'reader_cache_stmt_vdbe_vnext_token', 'reader_cache_stmt_vdbe_vrename_token', 'reader_cache_stmt_vdbe_pagecount_token',
    'reader_cache_stmt_vdbe_maxpgcnt_token', 'reader_cache_stmt_vdbe_opcode_trace_token', 'reader_cache_stmt_vdbe_cursorhint_token', 'reader_cache_stmt_vdbe_noop_token',
    'reader_cache_stmt_vdbe_init_token', 'reader_cache_stmt_vdbe_goto_token', 'reader_cache_stmt_vdbe_gosub_token', 'reader_cache_stmt_vdbe_return_token',
    'reader_cache_stmt_vdbe_yield_op_token', 'reader_cache_stmt_vdbe_halt_token', 'reader_cache_stmt_vdbe_halt_if_null_token', 'reader_cache_stmt_vdbe_must_be_int_token',
];
$before = [1 => $formatPage('stale schema'), 2 => $page('stale options')];
$recovered = [1 => $formatPage('current schema'), 2 => $page('current options')];
$tokens = [$journal => 'member-main-current-542'];
$headers = [$journal => hash('sha256', 'main header next542')];
$base = [
    'source_id' => 'wordpress-pager-reader-cache-next542',
    'epoch' => 542,
    'format_signature' => hash('sha256', implode('|', [512, 4, 2, 542, 0])),
    'publication_generation' => 542,
    'master_source_digest' => hash('sha256', 'wordpress next542 master source'),
    'recovery_sequence' => 542,
    'recovered_page_set_digest' => $recoveredDigest($recovered),
    'member_journal_tokens' => $tokens,
    'member_journal_header_digests' => $headers,
    'master_member_order_digest' => hash('sha256', $journal),
    'master_journal_bytes_digest' => hash('sha256', $masterBytes),
];
foreach ($tokenFields as $field) {
    $base[$field] = str_replace('_', '-', preg_replace('/_token$/', '', $field)) . '-current-542';
}
$cache = [1 => array_merge($base, ['reader_id' => 'wp-options-reader', 'image' => $recovered[1], 'reader_cache_stmt_vdbe_must_be_int_token' => 'stmt-vdbe-must-be-int-old'])];
$read = array_merge($base, [
    'reader_id' => 'read-options',
    'page_number' => 1,
    'member_journal_token_digest' => $mapDigest($tokens),
    'member_journal_header_digest' => $mapDigest($headers),
    'reader_cache_stmt_vdbe_must_be_int_token' => 'stmt-vdbe-must-be-int-old',
]);
$plan = SQLitePagerMasterJournalReaderCacheCurrentSourceNextPlan::readerCacheStatementFence(542,
    $database,
    $master,
    $masterBytes,
    implode('', $before),
    $pageSize,
    $recovered,
    $cache,
    [$read],
    $base['source_id'],
    542,
    542,
    $base['master_source_digest'],
    542,
    $tokens,
    $headers,
    ...array_map(static fn (string $field): string => $base[$field], $tokenFields),
);

echo json_encode([
    'status' => $plan['status'],
    'reopen_reader_ids' => $plan['reopen_reader_ids'],
    'next542_invalidated_pages' => $plan['reader_cache_stmt_vdbe_must_be_int_invalidated_cache_page_numbers'],
], JSON_PRETTY_PRINT) . PHP_EOL;

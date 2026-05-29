<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePagerMasterJournalReaderCacheCurrentSourceNextPlan;

require_once __DIR__ . '/../src/SQLitePagerMasterJournalReaderCacheCurrentSourceNextPlan.php';

$pageSize = 512;
$database = '/srv/wp-content/database/wp-next974.sqlite';
$journal = $database . '-journal';
$master = $database . '-mj';
$masterBytes = $journal . "\n";
$page = static fn (string $label): string => str_pad($label, $pageSize, '.', STR_PAD_RIGHT);
$formatPage = static function (string $label) use ($pageSize): string {
    $page = str_pad('SQLite format 3' . "\0", 100, "\0", STR_PAD_RIGHT) . str_repeat('.', $pageSize - 100);
    $page = substr_replace($page, pack('n', 512), 16, 2);
    $page = substr_replace($page, chr(4), 20, 1);
    $page = substr_replace($page, pack('N', 2), 56, 4);
    $page = substr_replace($page, pack('N', 974), 60, 4);

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
    'reader_cache_stmt_vdbe_string_token', 'reader_cache_stmt_vdbe_blob_token', 'reader_cache_stmt_vdbe_null_token', 'reader_cache_stmt_vdbe_soft_null_token',
    'reader_cache_stmt_vdbe_integer_token', 'reader_cache_stmt_vdbe_int64_token', 'reader_cache_stmt_vdbe_real_value_token', 'reader_cache_stmt_vdbe_boolean_token',
    'reader_cache_stmt_vdbe_null_row_token', 'reader_cache_stmt_vdbe_row_value_token', 'reader_cache_stmt_vdbe_zeroblob_token', 'reader_cache_stmt_vdbe_string8_token',
    'reader_cache_stmt_vdbe_concat_token', 'reader_cache_stmt_vdbe_add_token', 'reader_cache_stmt_vdbe_subtract_token', 'reader_cache_stmt_vdbe_multiply_token',
    'reader_cache_stmt_vdbe_divide_token', 'reader_cache_stmt_vdbe_remainder_token', 'reader_cache_stmt_vdbe_bit_and_token',
    'reader_cache_stmt_vdbe_bit_or_token', 'reader_cache_stmt_vdbe_shift_left_token', 'reader_cache_stmt_vdbe_shift_right_token', 'reader_cache_stmt_vdbe_add_imm_token',
    'reader_cache_stmt_vdbe_bit_not_token', 'reader_cache_stmt_vdbe_affinity_token', 'reader_cache_stmt_vdbe_cast_affinity_token', 'reader_cache_stmt_vdbe_permutation_affinity_token',
    'reader_cache_stmt_vdbe_compare_affinity_token', 'reader_cache_stmt_vdbe_compare_collseq_token', 'reader_cache_stmt_vdbe_jump_destination_token', 'reader_cache_stmt_vdbe_once_flag_token',
    'reader_cache_stmt_vdbe_if_branch_token', 'reader_cache_stmt_vdbe_ifnot_branch_token', 'reader_cache_stmt_vdbe_isnull_branch_token',
    'reader_cache_stmt_vdbe_notnull_branch_token', 'reader_cache_stmt_vdbe_ne_branch_token', 'reader_cache_stmt_vdbe_eq_branch_token',
    'reader_cache_stmt_vdbe_gt_branch_token', 'reader_cache_stmt_vdbe_le_branch_token', 'reader_cache_stmt_vdbe_lt_branch_token',
    'reader_cache_stmt_vdbe_ge_branch_token', 'reader_cache_stmt_vdbe_else_eq_branch_token', 'reader_cache_stmt_vdbe_zero_or_null_branch_token',
    'reader_cache_stmt_vdbe_seek_hit_branch_token', 'reader_cache_stmt_vdbe_if_not_open_branch_token', 'reader_cache_stmt_vdbe_not_open_branch_token',
    'reader_cache_stmt_vdbe_if_open_branch_token', 'reader_cache_stmt_vdbe_transaction_branch_token',
    'reader_cache_stmt_vdbe_auto_commit_branch_token', 'reader_cache_stmt_vdbe_savepoint_branch_token', 'reader_cache_stmt_vdbe_checkpoint_branch_token',
    'reader_cache_stmt_vdbe_journal_mode_branch_token', 'reader_cache_stmt_vdbe_vacuum_branch_token', 'reader_cache_stmt_vdbe_incr_vacuum_branch_token',
    'reader_cache_stmt_vdbe_expire_branch_token', 'reader_cache_stmt_vdbe_table_lock_branch_token', 'reader_cache_stmt_vdbe_vbegin_branch_token',
    'reader_cache_stmt_vdbe_vcreate_branch_token', 'reader_cache_stmt_vdbe_vdestroy_branch_token', 'reader_cache_stmt_vdbe_vopen_branch_token',
    'reader_cache_stmt_vdbe_vfilter_branch_token', 'reader_cache_stmt_vdbe_vcolumn_branch_token', 'reader_cache_stmt_vdbe_vnext_branch_token',
    'reader_cache_stmt_vdbe_vrename_branch_token',
    'reader_cache_stmt_vdbe_pagecount_branch_token', 'reader_cache_stmt_vdbe_maxpgcnt_branch_token', 'reader_cache_stmt_vdbe_opcode_trace_branch_token', 'reader_cache_stmt_vdbe_cursorhint_branch_token',
    'reader_cache_stmt_vdbe_noop_branch_token', 'reader_cache_stmt_vdbe_init_branch_token', 'reader_cache_stmt_vdbe_goto_branch_token', 'reader_cache_stmt_vdbe_gosub_branch_token',
    'reader_cache_stmt_vdbe_return_branch_token', 'reader_cache_stmt_vdbe_yield_op_branch_token', 'reader_cache_stmt_vdbe_halt_branch_token', 'reader_cache_stmt_vdbe_halt_if_null_branch_token',
    'reader_cache_stmt_vdbe_must_be_int_branch_token', 'reader_cache_stmt_vdbe_string_branch_token', 'reader_cache_stmt_vdbe_blob_branch_token', 'reader_cache_stmt_vdbe_null_branch_token',
    'reader_cache_stmt_vdbe_soft_null_handoff_token', 'reader_cache_stmt_vdbe_integer_handoff_token', 'reader_cache_stmt_vdbe_int64_handoff_token',
    'reader_cache_stmt_vdbe_real_value_handoff_token', 'reader_cache_stmt_vdbe_boolean_handoff_token', 'reader_cache_stmt_vdbe_null_row_handoff_token',
    'reader_cache_stmt_vdbe_row_value_handoff_token', 'reader_cache_stmt_vdbe_zeroblob_handoff_token', 'reader_cache_stmt_vdbe_string8_handoff_token',
    'reader_cache_stmt_vdbe_concat_handoff_token', 'reader_cache_stmt_vdbe_add_handoff_token', 'reader_cache_stmt_vdbe_subtract_handoff_token',
    'reader_cache_stmt_vdbe_multiply_handoff_token', 'reader_cache_stmt_vdbe_divide_handoff_token', 'reader_cache_stmt_vdbe_remainder_handoff_token',
    'reader_cache_stmt_vdbe_bit_and_handoff_token',
];
$next639654Fields = [
    'reader_cache_stmt_vdbe_bit_or_handoff_token', 'reader_cache_stmt_vdbe_shift_left_handoff_token', 'reader_cache_stmt_vdbe_shift_right_handoff_token', 'reader_cache_stmt_vdbe_add_imm_handoff_token',
    'reader_cache_stmt_vdbe_bit_not_handoff_token', 'reader_cache_stmt_vdbe_affinity_handoff_token', 'reader_cache_stmt_vdbe_cast_affinity_handoff_token',
    'reader_cache_stmt_vdbe_permutation_affinity_handoff_token', 'reader_cache_stmt_vdbe_compare_affinity_handoff_token', 'reader_cache_stmt_vdbe_compare_collseq_handoff_token',
    'reader_cache_stmt_vdbe_jump_destination_handoff_token', 'reader_cache_stmt_vdbe_once_flag_handoff_token', 'reader_cache_stmt_vdbe_if_branch_handoff_token',
    'reader_cache_stmt_vdbe_ifnot_branch_handoff_token', 'reader_cache_stmt_vdbe_isnull_branch_handoff_token', 'reader_cache_stmt_vdbe_notnull_branch_handoff_token',
];
$next671686Fields = [
    'reader_cache_stmt_vdbe_journal_mode_branch_handoff_token', 'reader_cache_stmt_vdbe_vacuum_branch_handoff_token', 'reader_cache_stmt_vdbe_incr_vacuum_branch_handoff_token',
    'reader_cache_stmt_vdbe_expire_branch_handoff_token', 'reader_cache_stmt_vdbe_table_lock_branch_handoff_token', 'reader_cache_stmt_vdbe_vbegin_branch_handoff_token',
    'reader_cache_stmt_vdbe_vcreate_branch_handoff_token', 'reader_cache_stmt_vdbe_vdestroy_branch_handoff_token', 'reader_cache_stmt_vdbe_vopen_branch_handoff_token',
    'reader_cache_stmt_vdbe_vfilter_branch_handoff_token', 'reader_cache_stmt_vdbe_vcolumn_branch_handoff_token', 'reader_cache_stmt_vdbe_vnext_branch_handoff_token',
    'reader_cache_stmt_vdbe_vrename_branch_handoff_token', 'reader_cache_stmt_vdbe_pagecount_branch_handoff_token', 'reader_cache_stmt_vdbe_maxpgcnt_branch_handoff_token',
    'reader_cache_stmt_vdbe_opcode_trace_branch_handoff_token',
];
$next655670Fields = [
    'reader_cache_stmt_vdbe_ne_branch_handoff_token', 'reader_cache_stmt_vdbe_eq_branch_handoff_token', 'reader_cache_stmt_vdbe_gt_branch_handoff_token',
    'reader_cache_stmt_vdbe_le_branch_handoff_token', 'reader_cache_stmt_vdbe_lt_branch_handoff_token', 'reader_cache_stmt_vdbe_ge_branch_handoff_token',
    'reader_cache_stmt_vdbe_else_eq_branch_handoff_token', 'reader_cache_stmt_vdbe_zero_or_null_branch_handoff_token', 'reader_cache_stmt_vdbe_seek_hit_branch_handoff_token',
    'reader_cache_stmt_vdbe_if_not_open_branch_handoff_token', 'reader_cache_stmt_vdbe_not_open_branch_handoff_token', 'reader_cache_stmt_vdbe_if_open_branch_handoff_token',
    'reader_cache_stmt_vdbe_transaction_branch_handoff_token', 'reader_cache_stmt_vdbe_auto_commit_branch_handoff_token', 'reader_cache_stmt_vdbe_savepoint_branch_handoff_token',
    'reader_cache_stmt_vdbe_checkpoint_branch_handoff_token',
];
$next687702Fields = [
    'reader_cache_stmt_vdbe_cursorhint_branch_handoff_token', 'reader_cache_stmt_vdbe_noop_branch_handoff_token', 'reader_cache_stmt_vdbe_init_branch_handoff_token',
    'reader_cache_stmt_vdbe_goto_branch_handoff_token', 'reader_cache_stmt_vdbe_gosub_branch_handoff_token', 'reader_cache_stmt_vdbe_return_branch_handoff_token',
    'reader_cache_stmt_vdbe_yield_op_branch_handoff_token', 'reader_cache_stmt_vdbe_halt_branch_handoff_token', 'reader_cache_stmt_vdbe_halt_if_null_branch_handoff_token',
    'reader_cache_stmt_vdbe_must_be_int_branch_handoff_token', 'reader_cache_stmt_vdbe_string_branch_handoff_token', 'reader_cache_stmt_vdbe_blob_branch_handoff_token',
    'reader_cache_stmt_vdbe_null_branch_handoff_token', 'reader_cache_stmt_vdbe_soft_null_branch_handoff_token', 'reader_cache_stmt_vdbe_integer_branch_handoff_token',
    'reader_cache_stmt_vdbe_int64_branch_handoff_token',
];
$next703718Fields = [
    'reader_cache_stmt_vdbe_real_value_branch_handoff_token', 'reader_cache_stmt_vdbe_boolean_branch_handoff_token', 'reader_cache_stmt_vdbe_null_row_branch_handoff_token',
    'reader_cache_stmt_vdbe_row_value_branch_handoff_token', 'reader_cache_stmt_vdbe_zeroblob_branch_handoff_token', 'reader_cache_stmt_vdbe_string8_branch_handoff_token',
    'reader_cache_stmt_vdbe_concat_branch_handoff_token', 'reader_cache_stmt_vdbe_add_branch_handoff_token', 'reader_cache_stmt_vdbe_subtract_branch_handoff_token',
    'reader_cache_stmt_vdbe_multiply_branch_handoff_token', 'reader_cache_stmt_vdbe_divide_branch_handoff_token', 'reader_cache_stmt_vdbe_remainder_branch_handoff_token',
    'reader_cache_stmt_vdbe_bit_and_branch_handoff_token', 'reader_cache_stmt_vdbe_bit_or_branch_handoff_token', 'reader_cache_stmt_vdbe_shift_left_branch_handoff_token',
    'reader_cache_stmt_vdbe_shift_right_branch_handoff_token',
];
$next719734Fields = [
    'reader_cache_stmt_vdbe_add_imm_branch_handoff_token', 'reader_cache_stmt_vdbe_bit_not_branch_handoff_token', 'reader_cache_stmt_vdbe_affinity_branch_handoff_token',
    'reader_cache_stmt_vdbe_cast_affinity_branch_handoff_token', 'reader_cache_stmt_vdbe_permutation_affinity_branch_handoff_token',
    'reader_cache_stmt_vdbe_seek_hit_branch_condition_handoff_token', 'reader_cache_stmt_vdbe_compare_collseq_branch_handoff_token',
    'reader_cache_stmt_vdbe_jump_destination_branch_handoff_token', 'reader_cache_stmt_vdbe_once_flag_branch_handoff_token',
    'reader_cache_stmt_vdbe_if_branch_handoff_token', 'reader_cache_stmt_vdbe_ifnot_branch_handoff_token', 'reader_cache_stmt_vdbe_isnull_branch_handoff_token',
    'reader_cache_stmt_vdbe_notnull_branch_handoff_token', 'reader_cache_stmt_vdbe_ne_branch_handoff_token', 'reader_cache_stmt_vdbe_eq_branch_handoff_token',
    'reader_cache_stmt_vdbe_gt_branch_handoff_token',
];
$next735766Fields = [
    'reader_cache_stmt_vdbe_le_branch_handoff_token', 'reader_cache_stmt_vdbe_lt_branch_handoff_token', 'reader_cache_stmt_vdbe_ge_branch_handoff_token',
    'reader_cache_stmt_vdbe_else_eq_branch_handoff_token', 'reader_cache_stmt_vdbe_zero_or_null_branch_handoff_token',
    'reader_cache_stmt_vdbe_seek_hit_branch_handoff_token', 'reader_cache_stmt_vdbe_if_not_open_branch_handoff_token',
    'reader_cache_stmt_vdbe_not_open_branch_handoff_token', 'reader_cache_stmt_vdbe_if_open_branch_handoff_token',
    'reader_cache_stmt_vdbe_transaction_branch_handoff_token', 'reader_cache_stmt_vdbe_auto_commit_branch_handoff_token',
    'reader_cache_stmt_vdbe_savepoint_branch_handoff_token', 'reader_cache_stmt_vdbe_checkpoint_branch_handoff_token',
    'reader_cache_stmt_vdbe_journal_mode_branch_handoff_token', 'reader_cache_stmt_vdbe_vacuum_branch_handoff_token',
    'reader_cache_stmt_vdbe_incr_vacuum_branch_handoff_token',
];
$next751766Fields = [
    'reader_cache_stmt_vdbe_expire_branch_handoff_token', 'reader_cache_stmt_vdbe_table_lock_branch_handoff_token', 'reader_cache_stmt_vdbe_vbegin_branch_handoff_token',
    'reader_cache_stmt_vdbe_vcreate_branch_handoff_token', 'reader_cache_stmt_vdbe_vdestroy_branch_handoff_token', 'reader_cache_stmt_vdbe_vopen_branch_handoff_token',
    'reader_cache_stmt_vdbe_vfilter_branch_handoff_token', 'reader_cache_stmt_vdbe_vcolumn_branch_handoff_token', 'reader_cache_stmt_vdbe_vnext_branch_handoff_token',
    'reader_cache_stmt_vdbe_vrename_branch_handoff_token', 'reader_cache_stmt_vdbe_pagecount_branch_handoff_token', 'reader_cache_stmt_vdbe_maxpgcnt_branch_handoff_token',
    'reader_cache_stmt_vdbe_opcode_trace_branch_handoff_token', 'reader_cache_stmt_vdbe_cursorhint_branch_handoff_token', 'reader_cache_stmt_vdbe_noop_branch_handoff_token',
    'reader_cache_stmt_vdbe_init_branch_handoff_token',
];
$next767782Fields = [
    'reader_cache_stmt_vdbe_goto_branch_handoff_token', 'reader_cache_stmt_vdbe_gosub_branch_handoff_token', 'reader_cache_stmt_vdbe_return_branch_handoff_token',
    'reader_cache_stmt_vdbe_yield_op_branch_handoff_token', 'reader_cache_stmt_vdbe_halt_branch_handoff_token', 'reader_cache_stmt_vdbe_halt_if_null_branch_handoff_token',
    'reader_cache_stmt_vdbe_must_be_int_branch_handoff_token', 'reader_cache_stmt_vdbe_string_branch_handoff_token', 'reader_cache_stmt_vdbe_blob_branch_handoff_token',
    'reader_cache_stmt_vdbe_null_branch_handoff_token', 'reader_cache_stmt_vdbe_soft_null_branch_handoff_token', 'reader_cache_stmt_vdbe_integer_branch_handoff_token',
    'reader_cache_stmt_vdbe_int64_branch_handoff_token', 'reader_cache_stmt_vdbe_real_value_branch_handoff_token', 'reader_cache_stmt_vdbe_boolean_branch_handoff_token',
    'reader_cache_stmt_vdbe_null_row_branch_handoff_token',
];
$next783798Fields = [
    'reader_cache_stmt_vdbe_row_value_branch_handoff_token', 'reader_cache_stmt_vdbe_zeroblob_branch_handoff_token', 'reader_cache_stmt_vdbe_string8_branch_handoff_token',
    'reader_cache_stmt_vdbe_concat_branch_handoff_token', 'reader_cache_stmt_vdbe_add_branch_handoff_token', 'reader_cache_stmt_vdbe_subtract_branch_handoff_token',
    'reader_cache_stmt_vdbe_multiply_branch_handoff_token', 'reader_cache_stmt_vdbe_divide_branch_handoff_token', 'reader_cache_stmt_vdbe_remainder_branch_handoff_token',
    'reader_cache_stmt_vdbe_bit_and_branch_handoff_token', 'reader_cache_stmt_vdbe_bit_or_branch_handoff_token', 'reader_cache_stmt_vdbe_shift_left_branch_handoff_token',
    'reader_cache_stmt_vdbe_shift_right_branch_handoff_token', 'reader_cache_stmt_vdbe_add_imm_branch_handoff_token', 'reader_cache_stmt_vdbe_bit_not_branch_handoff_token',
    'reader_cache_stmt_vdbe_real_affinity_value_branch_handoff_token',
];
$next799814Fields = [
    'reader_cache_stmt_vdbe_cast_branch_handoff_token', 'reader_cache_stmt_vdbe_eq_branch_handoff_token', 'reader_cache_stmt_vdbe_ne_branch_handoff_token',
    'reader_cache_stmt_vdbe_lt_branch_handoff_token', 'reader_cache_stmt_vdbe_le_branch_handoff_token', 'reader_cache_stmt_vdbe_gt_branch_handoff_token',
    'reader_cache_stmt_vdbe_ge_branch_handoff_token', 'reader_cache_stmt_vdbe_else_not_eq_branch_handoff_token', 'reader_cache_stmt_vdbe_permutation_branch_handoff_token',
    'reader_cache_stmt_vdbe_compare_branch_handoff_token', 'reader_cache_stmt_vdbe_jump_branch_handoff_token', 'reader_cache_stmt_vdbe_once_branch_handoff_token',
    'reader_cache_stmt_vdbe_if_branch_handoff_token', 'reader_cache_stmt_vdbe_if_not_branch_handoff_token', 'reader_cache_stmt_vdbe_column_branch_handoff_token',
    'reader_cache_stmt_vdbe_affinity_branch_handoff_token',
];
$next815830Fields = [
    'reader_cache_stmt_vdbe_cast_affinity_branch_handoff_token', 'reader_cache_stmt_vdbe_permutation_affinity_branch_handoff_token', 'reader_cache_stmt_vdbe_seek_hit_branch_condition_handoff_token',
    'reader_cache_stmt_vdbe_compare_collseq_branch_handoff_token', 'reader_cache_stmt_vdbe_jump_destination_branch_handoff_token', 'reader_cache_stmt_vdbe_once_flag_branch_handoff_token',
    'reader_cache_stmt_vdbe_if_branch_condition_handoff_token', 'reader_cache_stmt_vdbe_ifnot_branch_condition_handoff_token', 'reader_cache_stmt_vdbe_isnull_branch_condition_handoff_token',
    'reader_cache_stmt_vdbe_notnull_branch_condition_handoff_token', 'reader_cache_stmt_vdbe_ne_branch_condition_handoff_token', 'reader_cache_stmt_vdbe_eq_branch_condition_handoff_token',
    'reader_cache_stmt_vdbe_gt_branch_condition_handoff_token', 'reader_cache_stmt_vdbe_le_branch_condition_handoff_token', 'reader_cache_stmt_vdbe_lt_branch_condition_handoff_token',
    'reader_cache_stmt_vdbe_ge_branch_condition_handoff_token',
];
$next831846Fields = [
    'reader_cache_stmt_vdbe_else_eq_branch_condition_handoff_token', 'reader_cache_stmt_vdbe_zero_or_null_branch_condition_handoff_token',
    'reader_cache_stmt_vdbe_seek_hit_branch_condition_handoff_token', 'reader_cache_stmt_vdbe_if_not_open_branch_condition_handoff_token',
    'reader_cache_stmt_vdbe_not_open_branch_condition_handoff_token', 'reader_cache_stmt_vdbe_if_open_branch_condition_handoff_token',
    'reader_cache_stmt_vdbe_transaction_branch_handoff_token', 'reader_cache_stmt_vdbe_auto_commit_branch_handoff_token',
    'reader_cache_stmt_vdbe_savepoint_branch_handoff_token', 'reader_cache_stmt_vdbe_checkpoint_branch_handoff_token',
    'reader_cache_stmt_vdbe_journal_mode_branch_handoff_token', 'reader_cache_stmt_vdbe_vacuum_branch_handoff_token',
    'reader_cache_stmt_vdbe_incr_vacuum_branch_handoff_token', 'reader_cache_stmt_vdbe_expire_branch_handoff_token',
    'reader_cache_stmt_vdbe_table_lock_branch_handoff_token', 'reader_cache_stmt_vdbe_vbegin_branch_handoff_token',
];
$next847862Fields = [
    'reader_cache_stmt_vdbe_vcreate_branch_handoff_token', 'reader_cache_stmt_vdbe_vdestroy_branch_handoff_token',
    'reader_cache_stmt_vdbe_vopen_branch_handoff_token', 'reader_cache_stmt_vdbe_vfilter_branch_handoff_token',
    'reader_cache_stmt_vdbe_vcolumn_branch_handoff_token', 'reader_cache_stmt_vdbe_vnext_branch_handoff_token',
    'reader_cache_stmt_vdbe_vrename_branch_handoff_token', 'reader_cache_stmt_vdbe_pagecount_branch_handoff_token',
    'reader_cache_stmt_vdbe_maxpgcnt_branch_handoff_token', 'reader_cache_stmt_vdbe_opcode_trace_branch_handoff_token',
    'reader_cache_stmt_vdbe_cursorhint_branch_handoff_token', 'reader_cache_stmt_vdbe_noop_branch_handoff_token',
    'reader_cache_stmt_vdbe_init_branch_handoff_token', 'reader_cache_stmt_vdbe_goto_branch_handoff_token',
    'reader_cache_stmt_vdbe_gosub_branch_handoff_token', 'reader_cache_stmt_vdbe_return_branch_handoff_token',
];
$next863878Fields = [
    'reader_cache_stmt_vdbe_yield_op_branch_handoff_token', 'reader_cache_stmt_vdbe_halt_branch_handoff_token',
    'reader_cache_stmt_vdbe_halt_if_null_branch_handoff_token', 'reader_cache_stmt_vdbe_must_be_int_branch_handoff_token',
    'reader_cache_stmt_vdbe_string_branch_handoff_token', 'reader_cache_stmt_vdbe_blob_branch_handoff_token',
    'reader_cache_stmt_vdbe_null_branch_handoff_token', 'reader_cache_stmt_vdbe_soft_null_branch_handoff_token',
    'reader_cache_stmt_vdbe_integer_branch_handoff_token', 'reader_cache_stmt_vdbe_int64_branch_handoff_token',
    'reader_cache_stmt_vdbe_real_value_branch_handoff_token', 'reader_cache_stmt_vdbe_boolean_branch_handoff_token',
    'reader_cache_stmt_vdbe_null_row_branch_handoff_token', 'reader_cache_stmt_vdbe_row_value_branch_handoff_token',
    'reader_cache_stmt_vdbe_zeroblob_branch_handoff_token', 'reader_cache_stmt_vdbe_string8_branch_handoff_token',
];
$next879894Fields = [
    'reader_cache_stmt_vdbe_concat_branch_handoff_token', 'reader_cache_stmt_vdbe_add_branch_handoff_token',
    'reader_cache_stmt_vdbe_subtract_branch_handoff_token', 'reader_cache_stmt_vdbe_multiply_branch_handoff_token',
    'reader_cache_stmt_vdbe_divide_branch_handoff_token', 'reader_cache_stmt_vdbe_remainder_branch_handoff_token',
    'reader_cache_stmt_vdbe_bit_and_branch_handoff_token', 'reader_cache_stmt_vdbe_bit_or_branch_handoff_token',
    'reader_cache_stmt_vdbe_shift_left_branch_handoff_token', 'reader_cache_stmt_vdbe_shift_right_branch_handoff_token',
    'reader_cache_stmt_vdbe_add_imm_branch_handoff_token', 'reader_cache_stmt_vdbe_bit_not_branch_handoff_token',
    'reader_cache_stmt_vdbe_affinity_branch_handoff_token', 'reader_cache_stmt_vdbe_cast_affinity_branch_handoff_token',
    'reader_cache_stmt_vdbe_permutation_affinity_branch_handoff_token', 'reader_cache_stmt_vdbe_compare_affinity_branch_handoff_token',
];
$next895910Fields = [
    'reader_cache_stmt_vdbe_compare_collseq_branch_handoff_token', 'reader_cache_stmt_vdbe_jump_destination_branch_handoff_token',
    'reader_cache_stmt_vdbe_once_flag_branch_handoff_token', 'reader_cache_stmt_vdbe_if_branch_condition_handoff_token',
    'reader_cache_stmt_vdbe_ifnot_branch_condition_handoff_token', 'reader_cache_stmt_vdbe_isnull_branch_condition_handoff_token',
    'reader_cache_stmt_vdbe_notnull_branch_condition_handoff_token', 'reader_cache_stmt_vdbe_ne_branch_condition_handoff_token',
    'reader_cache_stmt_vdbe_eq_branch_condition_handoff_token', 'reader_cache_stmt_vdbe_gt_branch_condition_handoff_token',
    'reader_cache_stmt_vdbe_le_branch_condition_handoff_token', 'reader_cache_stmt_vdbe_lt_branch_condition_handoff_token',
    'reader_cache_stmt_vdbe_ge_branch_condition_handoff_token', 'reader_cache_stmt_vdbe_else_eq_branch_condition_handoff_token',
    'reader_cache_stmt_vdbe_zero_or_null_branch_condition_handoff_token', 'reader_cache_stmt_vdbe_seek_hit_branch_condition_handoff_token',
];
$next911926Fields = [
    'reader_cache_stmt_vdbe_transaction_branch_condition_handoff_token', 'reader_cache_stmt_vdbe_auto_commit_branch_condition_handoff_token',
    'reader_cache_stmt_vdbe_savepoint_branch_condition_handoff_token', 'reader_cache_stmt_vdbe_checkpoint_branch_condition_handoff_token',
    'reader_cache_stmt_vdbe_journal_mode_branch_condition_handoff_token', 'reader_cache_stmt_vdbe_vacuum_branch_condition_handoff_token',
    'reader_cache_stmt_vdbe_incr_vacuum_branch_condition_handoff_token', 'reader_cache_stmt_vdbe_expire_branch_condition_handoff_token',
    'reader_cache_stmt_vdbe_table_lock_branch_condition_handoff_token', 'reader_cache_stmt_vdbe_vbegin_branch_condition_handoff_token',
    'reader_cache_stmt_vdbe_vcreate_branch_condition_handoff_token', 'reader_cache_stmt_vdbe_vdestroy_branch_condition_handoff_token',
    'reader_cache_stmt_vdbe_vopen_branch_condition_handoff_token', 'reader_cache_stmt_vdbe_vfilter_branch_condition_handoff_token',
    'reader_cache_stmt_vdbe_vcolumn_branch_condition_handoff_token', 'reader_cache_stmt_vdbe_vnext_branch_condition_handoff_token',
];
$next927942Fields = [
    'reader_cache_stmt_vdbe_vrename_branch_condition_handoff_token', 'reader_cache_stmt_vdbe_pagecount_branch_condition_handoff_token',
    'reader_cache_stmt_vdbe_maxpgcnt_branch_condition_handoff_token', 'reader_cache_stmt_vdbe_opcode_trace_branch_condition_handoff_token',
    'reader_cache_stmt_vdbe_cursorhint_branch_condition_handoff_token', 'reader_cache_stmt_vdbe_noop_branch_condition_handoff_token',
    'reader_cache_stmt_vdbe_init_branch_condition_handoff_token', 'reader_cache_stmt_vdbe_goto_branch_condition_handoff_token',
    'reader_cache_stmt_vdbe_gosub_branch_condition_handoff_token', 'reader_cache_stmt_vdbe_return_branch_condition_handoff_token',
    'reader_cache_stmt_vdbe_yield_op_branch_condition_handoff_token', 'reader_cache_stmt_vdbe_halt_branch_condition_handoff_token',
    'reader_cache_stmt_vdbe_halt_if_null_branch_condition_handoff_token', 'reader_cache_stmt_vdbe_must_be_int_branch_condition_handoff_token',
    'reader_cache_stmt_vdbe_string_branch_condition_handoff_token', 'reader_cache_stmt_vdbe_blob_branch_condition_handoff_token',
];
$next943958Fields = [
    'reader_cache_stmt_vdbe_null_branch_condition_handoff_token', 'reader_cache_stmt_vdbe_soft_null_branch_condition_handoff_token',
    'reader_cache_stmt_vdbe_integer_branch_condition_handoff_token', 'reader_cache_stmt_vdbe_int64_branch_condition_handoff_token',
    'reader_cache_stmt_vdbe_real_value_branch_condition_handoff_token', 'reader_cache_stmt_vdbe_boolean_branch_condition_handoff_token',
    'reader_cache_stmt_vdbe_null_row_branch_condition_handoff_token', 'reader_cache_stmt_vdbe_row_value_branch_condition_handoff_token',
    'reader_cache_stmt_vdbe_zeroblob_branch_condition_handoff_token', 'reader_cache_stmt_vdbe_string8_branch_condition_handoff_token',
    'reader_cache_stmt_vdbe_concat_branch_condition_handoff_token', 'reader_cache_stmt_vdbe_add_branch_condition_handoff_token',
    'reader_cache_stmt_vdbe_subtract_branch_condition_handoff_token', 'reader_cache_stmt_vdbe_multiply_branch_condition_handoff_token',
    'reader_cache_stmt_vdbe_divide_branch_condition_handoff_token', 'reader_cache_stmt_vdbe_remainder_branch_condition_handoff_token',
];
$next959974Fields = [
    'reader_cache_stmt_vdbe_bit_and_branch_condition_handoff_token', 'reader_cache_stmt_vdbe_bit_or_branch_condition_handoff_token',
    'reader_cache_stmt_vdbe_shift_left_branch_condition_handoff_token', 'reader_cache_stmt_vdbe_shift_right_branch_condition_handoff_token',
    'reader_cache_stmt_vdbe_add_imm_branch_condition_handoff_token', 'reader_cache_stmt_vdbe_bit_not_branch_condition_handoff_token',
    'reader_cache_stmt_vdbe_affinity_branch_condition_handoff_token', 'reader_cache_stmt_vdbe_cast_affinity_branch_condition_handoff_token',
    'reader_cache_stmt_vdbe_permutation_affinity_branch_condition_handoff_token', 'reader_cache_stmt_vdbe_compare_affinity_branch_condition_handoff_token',
    'reader_cache_stmt_vdbe_compare_collseq_branch_condition_handoff_token', 'reader_cache_stmt_vdbe_jump_destination_branch_condition_handoff_token',
    'reader_cache_stmt_vdbe_once_flag_branch_condition_handoff_token', 'reader_cache_stmt_vdbe_if_branch_condition_branch_condition_handoff_token',
    'reader_cache_stmt_vdbe_ifnot_branch_condition_branch_condition_handoff_token', 'reader_cache_stmt_vdbe_isnull_branch_condition_branch_condition_handoff_token',
];
$variantFields = array_merge($tokenFields, $next639654Fields, $next655670Fields, $next671686Fields, $next687702Fields, $next703718Fields, $next719734Fields, $next735766Fields, $next751766Fields, $next767782Fields, $next783798Fields, $next799814Fields, $next815830Fields, $next831846Fields, $next847862Fields, $next863878Fields, $next879894Fields, $next895910Fields, $next911926Fields, $next927942Fields, $next943958Fields, $next959974Fields);
$before = [1 => $formatPage('stale schema'), 2 => $page('stale options')];
$recovered = [1 => $formatPage('current schema'), 2 => $page('current options')];
$tokens = [$journal => 'member-main-current-974'];
$headers = [$journal => hash('sha256', 'main header next974')];
$base = [
    'source_id' => 'wordpress-pager-reader-cache-next974',
    'epoch' => 974,
    'format_signature' => hash('sha256', implode('|', [512, 4, 2, 974, 0])),
    'publication_generation' => 974,
    'master_source_digest' => hash('sha256', 'wordpress next974 master source'),
    'recovery_sequence' => 974,
    'recovered_page_set_digest' => $recoveredDigest($recovered),
    'member_journal_tokens' => $tokens,
    'member_journal_header_digests' => $headers,
    'master_member_order_digest' => hash('sha256', $journal),
    'master_journal_bytes_digest' => hash('sha256', $masterBytes),
];
foreach ($variantFields as $field) {
    $base[$field] = str_replace('_', '-', preg_replace('/_token$/', '', $field)) . '-current-974';
}
$base['reader_cache_stmt_vdbe_real_affinity_value_token'] = 'reader-cache-stmt-vdbe-affinity-current-974';
$base['reader_cache_stmt_vdbe_real_affinity_value_handoff_token'] = 'reader-cache-stmt-vdbe-affinity-handoff-current-974';
$cache = [1 => array_merge($base, ['reader_id' => 'wp-options-reader', 'image' => $recovered[1], 'reader_cache_stmt_vdbe_le_branch_handoff_token' => 'stmt-vdbe-le-branch-handoff-old'])];
$read = array_merge($base, [
    'reader_id' => 'read-options',
    'page_number' => 1,
    'member_journal_token_digest' => $mapDigest($tokens),
    'member_journal_header_digest' => $mapDigest($headers),
    'reader_cache_stmt_vdbe_isnull_branch_condition_branch_condition_handoff_token' => 'stmt-vdbe-isnull-branch-condition-branch-condition-handoff-old',
]);
$plan = SQLitePagerMasterJournalReaderCacheCurrentSourceNextPlan::currentSourceVdbeArithmeticBranchConditionFence(
    $database,
    $master,
    $masterBytes,
    implode('', $before),
    $pageSize,
    $recovered,
    $cache,
    [$read],
    $base['source_id'],
    974,
    974,
    $base['master_source_digest'],
    974,
    $tokens,
    $headers,
    ...array_map(static fn (string $field): string => $base[$field], $variantFields),
);

echo json_encode([
    'status' => $plan['status'],
    'reopen_reader_ids' => $plan['reopen_reader_ids'],
    'next735_invalidated_pages' => $plan['reader_cache_stmt_vdbe_le_branch_handoff_invalidated_cache_page_numbers'],
    'next974_reopen_reason' => $plan['next_reads'][0]['reader_cache_stmt_vdbe_isnull_branch_condition_branch_condition_handoff_token_reason'],
], JSON_PRETTY_PRINT) . PHP_EOL;

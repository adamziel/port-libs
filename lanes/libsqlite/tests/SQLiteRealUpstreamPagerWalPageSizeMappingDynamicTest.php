<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteRealUpstreamPagerWalDynamicPlan;

$tests = [];

foreach (SQLiteRealUpstreamPagerWalDynamicPlan::wal8Wal9PageSizeMappingCases() as $case) {
    $tests[sprintf(
        'real upstream pager wal page size mapping dynamic %04d %s %s',
        $case['case'],
        $case['source_file'],
        $case['phase']
    )] = static function (TestRunner $t) use ($case): void {
        $t->true(in_array($case['source_file'], ['wal8.test', 'wal9.test'], true));
        $t->true(str_starts_with($case['upstream'], $case['source_file']));
        $t->true(in_array('sqlite-real-upstream-pager-wal-dynamic', $case['dependencies'], true));
        $t->true($case['case'] >= 1 && $case['case'] <= 1000);
        $t->true(in_array($case['checkpoint_mode'], ['passive', 'full', 'restart', 'truncate'], true));
        $t->true(in_array($case['schema_reader'], ['sqlite_master', 'schema-cache', 'pager-schema'], true));

        if ($case['source_file'] === 'wal8.test') {
            $t->same('wal8-empty-file-page-size-after-wal-init', $case['assertion_family']);
            $t->same(true, $case['database_was_empty_when_first_handle_opened']);
            $t->same(true, $case['wal_sidecar_exists']);
            $t->same('wal', $case['journal_mode']);
            $t->same(4096, $case['page_size_pragma_after_open']);
            $t->same(4096, $case['effective_page_size']);
            $t->same(['t1'], $case['schema_names']);
            $t->same([[1, 2]], $case['rows']);
            $t->same(true, $case['vacuum_keeps_database_readable']);
            $t->true(in_array($case['requested_page_size'], [1024, 2048, 4096, 8192], true));
            if ($case['vacuum_result_code'] !== null) {
                $t->same(0, $case['vacuum_result_code']);
                $t->same('', $case['vacuum_message']);
            } else {
                $t->same('empty-handle-page-size-pragma-does-not-hide-wal-schema', $case['phase']);
            }
            $t->same(true, $case['other_handle_initializes_wal_before_schema'] || $case['schema_created_before_wal']);
            $t->true(in_array('sqlite-upstream-wal8-empty-file-page-size', $case['dependencies'], true));
            return;
        }

        $t->same('wal9-partial-shm-rollback-after-full-checkpoint', $case['assertion_family']);
        $t->same(1024, $case['page_size']);
        $t->same(0, $case['wal_autocheckpoint']);
        $t->same(1024, $case['database_bytes_after_checkpoint']);
        $t->true($case['wal_bytes'] > $case['wal_bytes_greater_than']);
        $t->true($case['shm_bytes'] > $case['shm_bytes_greater_than']);
        $t->same($case['checkpoint'][1], $case['checkpoint'][2]);
        $t->same(0, $case['checkpoint'][0]);
        $t->true($case['checkpoint'][1] >= 14501);
        $t->same(32768, $case['partial_shm_mapping_bytes']);
        $t->same('hello', $case['rolled_back_insert_value']);
        $t->same(0, $case['rollback_result_code']);
        $t->same('', $case['rollback_message']);
        $t->same(false, $case['reader_requires_tail_mapping_after_checkpoint']);
        $t->true(in_array('sqlite-upstream-wal9-partial-shm-rollback', $case['dependencies'], true));
    };
}

$tests['real upstream pager wal page size mapping dynamic source inventory'] = static function (TestRunner $t): void {
    $cases = SQLiteRealUpstreamPagerWalDynamicPlan::wal8Wal9PageSizeMappingCases();
    $sources = array_values(array_unique(array_column($cases, 'source_file')));
    sort($sources);

    $t->same(['wal8.test', 'wal9.test'], $sources);
    $t->same(1000, count($cases));
    $t->same(750, count(array_filter($cases, static fn (array $case): bool => $case['source_file'] === 'wal8.test')));
    $t->same(250, count(array_filter($cases, static fn (array $case): bool => $case['source_file'] === 'wal9.test')));
    $t->same('wal8.test 1.0 1.1', $cases[0]['upstream']);
    $t->same('wal9.test 1.0 1.6 1.7', $cases[3]['upstream']);
};

return $tests;

<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteUpstreamSuiteEvidence;

function libsqlite_suite_prepared_window_evidence(): SQLiteUpstreamSuiteEvidence
{
    return SQLiteUpstreamSuiteEvidence::fromManifestPath(__DIR__ . '/../UPSTREAM_TEST_MANIFEST.json');
}

function libsqlite_suite_prepared_window_output(int $passLines = 44, int $assertions = 96, int $failures = 0): string
{
    $lines = ['Focused test run: 1 selected test files (root lock skipped)'];
    for ($i = 1; $i <= $passLines; $i++) {
        $lines[] = sprintf('PASS current source next405-420 upstream suite evidence case %02d', $i);
    }
    $lines[] = sprintf('1 test files, %d assertions, %d failures', $assertions, $failures);

    return implode("\n", $lines);
}

/**
 * @return list<array<string, mixed>>
 */
function libsqlite_suite_prepared_window_rows(
    int $case = 1,
    string $launcherBase = '8035502138608bf3cb7ee64d1b4686a5bb9f5ec6',
    string $integrationSource = '8035502138608bf3cb7ee64d1b4686a5bb9f5ec6'
): array {
    $rows = [];
    foreach (range(405, 420) as $slice) {
        $script = sprintf('veryquick-current-source-next%d-%02d.test', $slice, $case);
        $rows[] = [
            'unit' => 'suite-upstream-veryquick-shard-current-source-next' . $slice,
            'gap_id' => 'current-source-next' . $slice . '-veryquick-suite-evidence-gap',
            'gap_status' => 'prepared',
            'removed_blocker' => 'next' . $slice . ' prepares direct follow-on suite evidence after merged next389-404',
            'source_head' => 'suite-upstream-veryquick-suite-current-source-next405-420',
            'launcher_base_head' => $launcherBase,
            'dashboard_source_head' => $integrationSource,
            'status_source_head' => $integrationSource,
            'implementation_source_head' => $integrationSource,
            'artifact_path' => 'lanes/libsqlite/notes/upstream-suite-evidence-current-source-next405-420.md',
            'runner_command' => './testfixture ../libsqlite/test/testrunner.tcl --jobs 1 --stop-on-error veryquick ' . $script,
            'scripts' => [$script, 'testrunner.test'],
            'exit' => 0,
            'errors' => 0,
            'current_tests' => 0,
            'next_tests' => 7000 + $slice + $case,
        ];
    }

    return $rows;
}

/**
 * @param list<array<string, mixed>> $rows
 * @return array<string, mixed>
 */
function libsqlite_suite_prepared_window_record(
    array $rows,
    string $launcherBase = '8035502138608bf3cb7ee64d1b4686a5bb9f5ec6',
    string $integrationSource = '8035502138608bf3cb7ee64d1b4686a5bb9f5ec6',
    ?string $output = null,
    ?int $expected = 96,
    string $snapshot = ''
): array {
    return libsqlite_suite_prepared_window_evidence()->upstreamVeryquickShardPreparedWindowEvidence(
        $rows,
        637,
        42276,
        $launcherBase,
        $integrationSource,
        'lanes/libsqlite/tests/SQLiteUpstreamSuiteEvidencePreparedWindowTest.php',
        $output ?? libsqlite_suite_prepared_window_output(),
        'current-source next405-420 upstream-suite evidence avoids merged next389-404 suite evidence, earlier prepared ranges, release/all parity, full-suite countability, and individual shard recounting',
        $expected,
        $snapshot
    );
}

$tests = [];

foreach (range(1, 16) as $case) {
    $tests[sprintf('current source next405-420 prepares suite evidence case %02d', $case)] = static function (TestRunner $t) use ($case): void {
        $record = libsqlite_suite_prepared_window_record(libsqlite_suite_prepared_window_rows($case));

        $t->same('current-source-next405-420-suite-evidence-prepared', $record['status']);
        $t->same(637, $record['current_mapped']);
        $t->same(637, $record['next_mapped']);
        $t->same(0, $record['mapped_delta']);
        $t->same(96, $record['php_pass_delta']);
        $t->same(42372, $record['next_php_pass']);
        $t->same(16, $record['row_count']);
        $t->same(16, $record['zero_error_row_count']);
        $t->same(16, $record['lane_local_note_row_count']);
        $t->same(['next405', 'next406', 'next407', 'next408', 'next409', 'next410', 'next411', 'next412', 'next413', 'next414', 'next415', 'next416', 'next417', 'next418', 'next419', 'next420'], $record['covered_slices']);
        $t->same([], $record['missing_slices']);
        $t->same(false, $record['counts_upstream_veryquick_shard_current_source_next405_420']);
        $t->same(false, $record['counts_upstream_veryquick_shard_current_source_next389_404']);
        $t->same(false, $record['counts_release_parity']);
        $t->contains(sprintf('veryquick-current-source-next420-%02d.test', $case), implode(',', $record['target_scripts']));
    };
}

$tests['current source next405-420 blocks missing slice'] = static function (TestRunner $t): void {
    $rows = libsqlite_suite_prepared_window_rows();
    unset($rows[1]);
    $rows = array_values($rows);

    $record = libsqlite_suite_prepared_window_record($rows);

    $t->same('blocked', $record['status']);
    $t->same(['next406'], $record['missing_slices']);
    $t->contains('next406', implode(',', array_column($record['blockers'], 'evidence')));
};

$tests['current source next405-420 blocks outside slice and stale provenance'] = static function (TestRunner $t): void {
    $rows = libsqlite_suite_prepared_window_rows();
    $rows[0]['unit'] = 'suite-upstream-veryquick-shard-current-source-next404';
    $rows[1]['dashboard_source_head'] = 'stale-source';

    $record = libsqlite_suite_prepared_window_record($rows);
    $evidence = implode('; ', array_column($record['blockers'], 'evidence'));

    $t->same('blocked', $record['status']);
    $t->contains('suite-upstream-veryquick-shard-current-source-next404', $evidence);
    $t->contains('dashboard_source_head-mismatch', implode(',', array_column($record['blockers'], 'id')));
};

$tests['current source next405-420 blocks unguarded runner and non lane note'] = static function (TestRunner $t): void {
    $rows = libsqlite_suite_prepared_window_rows();
    $rows[2]['runner_command'] = './testfixture ../libsqlite/test/testrunner.tcl veryquick';
    $rows[2]['artifact_path'] = 'lanes/libsqlite/tests/tmp-next407.log';

    $record = libsqlite_suite_prepared_window_record($rows);
    $ids = implode(',', array_column($record['blockers'], 'id'));

    $t->same('blocked', $record['status']);
    $t->contains('guarded-veryquick-command-missing', $ids);
    $t->contains('artifact-path-not-lane-local-note', $ids);
};

$tests['current source next405-420 blocks runner errors and active broad runner'] = static function (TestRunner $t): void {
    $rows = libsqlite_suite_prepared_window_rows();
    $rows[3]['errors'] = 1;

    $record = libsqlite_suite_prepared_window_record($rows, snapshot: "777 testfixture ../libsqlite/test/testrunner.tcl --jobs 4 all\n");
    $ids = implode(',', array_column($record['blockers'], 'id'));

    $t->same('blocked', $record['status']);
    $t->contains('runner-artifact-not-zero-error', $ids);
    $t->contains('duplicate-broad-runner-active', $ids);
};

$tests['current source next405-420 blocks focused pass mismatch'] = static function (TestRunner $t): void {
    $record = libsqlite_suite_prepared_window_record(
        libsqlite_suite_prepared_window_rows(),
        output: libsqlite_suite_prepared_window_output(assertions: 83),
        expected: 96
    );

    $t->same('blocked', $record['status']);
    $t->same(83, $record['php_pass_delta']);
    $t->same(42359, $record['next_php_pass']);
    $t->contains('focused-php-pass-delta-mismatch', implode(',', array_column($record['blockers'], 'id')));
};

$tests['current source next405-420 records next gate and dependency closure'] = static function (TestRunner $t): void {
    $record = libsqlite_suite_prepared_window_record(libsqlite_suite_prepared_window_rows(8));

    $t->contains('publish next405-420 as prepared upstream-suite evidence only', $record['next_gate']);
    $t->contains('current-source next405-420 evidence prep', $record['dependency_closure']);
    $t->contains('avoids merged next389-404', $record['non_overlap_note']);
};

return $tests;

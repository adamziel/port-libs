<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteUpstreamSuiteEvidence;

function libsqlite_suite_prepared_window_beta_evidence(): SQLiteUpstreamSuiteEvidence
{
    return SQLiteUpstreamSuiteEvidence::fromManifestPath(__DIR__ . '/../UPSTREAM_TEST_MANIFEST.json');
}

function libsqlite_suite_prepared_window_beta_output(int $assertions = 144, int $failures = 0): string
{
    $lines = ['Focused test run: 1 selected test files (root lock skipped)'];
    for ($i = 1; $i <= $assertions; $i++) {
        $lines[] = sprintf('PASS prepared window beta evidence prep case %03d', $i);
    }
    $lines[] = sprintf('1 test files, %d assertions, %d failures', $assertions, $failures);

    return implode("\n", $lines);
}

/**
 * @return list<array<string, mixed>>
 */
function libsqlite_suite_prepared_window_beta_rows(
    string $launcherBase = 'c2a4861134089e1f5b6e787798c2a0d8958cc2d0',
    string $integrationSource = 'c2a4861134089e1f5b6e787798c2a0d8958cc2d0'
): array {
    $rows = [];
    foreach (range(1, 16) as $slice) {
        $script = sprintf('veryquick-prepared-window-beta-shard-%02d.test', $slice);
        $rows[] = [
            'unit' => sprintf('suite-upstream-veryquick-shard-prepared-window-beta-shard-%02d', $slice),
            'kind' => 'prepared-upstream-veryquick-shard-runner',
            'artifact_path' => sprintf('lanes/libsqlite/notes/upstream-veryquick-shard-prepared-window-beta-%02d.md', $slice),
            'runner_command' => './testfixture ../libsqlite/test/testrunner.tcl --jobs 1 --stop-on-error veryquick ' . $script,
            'scripts' => [$script, 'testrunner.test'],
            'launcher_base_head' => $launcherBase,
            'dashboard_source_head' => $integrationSource,
            'status_source_head' => $integrationSource,
            'implementation_source_head' => $integrationSource,
            'exit' => 0,
            'errors' => 0,
        ];
    }

    return $rows;
}

/**
 * @param list<array<string, mixed>> $rows
 * @return array<string, mixed>
 */
function libsqlite_suite_prepared_window_beta_record(
    array $rows,
    string $launcherBase = 'c2a4861134089e1f5b6e787798c2a0d8958cc2d0',
    string $integrationSource = 'c2a4861134089e1f5b6e787798c2a0d8958cc2d0',
    ?string $output = null,
    ?int $expected = 144,
    string $snapshot = ''
): array {
    return libsqlite_suite_prepared_window_beta_evidence()->upstreamVeryquickShardPreparedWindowBeta(
        $rows,
        629,
        110855,
        $launcherBase,
        $integrationSource,
        'lanes/libsqlite/tests/SQLiteUpstreamVeryquickShardPreparedWindowBetaTest.php',
        $output ?? libsqlite_suite_prepared_window_beta_output(),
        'prepared-window-beta suite evidence prep avoids merged prepared-window-alpha suite evidence, individual beta shards shard countability, exact-shard exact shard baseline, runner106/jsonvt104 rebase work, queued manifest-conflict work, and release/all parity claims',
        $expected,
        $snapshot
    );
}

$tests = [];

$tests['prepared window beta prepares all suite evidence rows'] = static function (TestRunner $t): void {
    $record = libsqlite_suite_prepared_window_beta_record(libsqlite_suite_prepared_window_beta_rows());

    $t->same('prepared-window-beta-suite-evidence-prepared', $record['status']);
    $t->same(16, $record['row_count']);
    $t->same(16, $record['zero_error_row_count']);
    $t->same(16, $record['lane_local_note_row_count']);
    $t->same(16, $record['slice_count']);
    $t->same([], $record['missing_slices']);
    $t->same('shard-01', $record['covered_slices'][0]);
    $t->same('shard-16', $record['covered_slices'][15]);
};

$tests['prepared window beta is prepared evidence without mapped inflation'] = static function (TestRunner $t): void {
    $record = libsqlite_suite_prepared_window_beta_record(libsqlite_suite_prepared_window_beta_rows());

    $t->same(629, $record['current_mapped']);
    $t->same(629, $record['next_mapped']);
    $t->same(0, $record['mapped_delta']);
    $t->same(false, $record['counts_upstream_veryquick_shard_prepared_window_beta']);
    $t->same(false, $record['counts_upstream_veryquick_shard_prepared_window_alpha']);
    $t->same(false, $record['counts_release_parity']);
    $t->contains('prepared upstream-suite evidence only', $record['next_gate']);
};

$tests['prepared window beta records focused php admission'] = static function (TestRunner $t): void {
    $record = libsqlite_suite_prepared_window_beta_record(libsqlite_suite_prepared_window_beta_rows());

    $t->same(110855, $record['current_php_pass']);
    $t->same(110999, $record['next_php_pass']);
    $t->same(144, $record['php_pass_delta']);
    $t->same('clear', $record['active_runner_status']);
    $t->same(0, $record['active_runner_count']);
};

$tests['prepared window beta records target scripts'] = static function (TestRunner $t): void {
    $record = libsqlite_suite_prepared_window_beta_record(libsqlite_suite_prepared_window_beta_rows());

    $t->same(17, $record['target_script_count']);
    $t->contains('testrunner.test', implode(',', $record['target_scripts']));
    $t->contains('veryquick-prepared-window-beta-shard-01.test', implode(',', $record['target_scripts']));
    $t->contains('veryquick-prepared-window-beta-shard-16.test', implode(',', $record['target_scripts']));
};

$tests['prepared window beta blocks missing slices and outside rows'] = static function (TestRunner $t): void {
    $rows = libsqlite_suite_prepared_window_beta_rows();
    array_pop($rows);
    $rows[] = array_merge($rows[0], ['unit' => 'suite-upstream-veryquick-shard-prepared-window-beta-shard-00']);

    $record = libsqlite_suite_prepared_window_beta_record($rows);

    $t->same('blocked', $record['status']);
    $t->same(['shard-16'], $record['missing_slices']);
    $evidence = implode('; ', array_column($record['blockers'], 'id'));
    $t->contains('slice-outside-prepared-window-beta', $evidence);
    $t->contains('missing-shard', $evidence);
};

$tests['prepared window beta blocks provenance and artifact gaps'] = static function (TestRunner $t): void {
    $rows = libsqlite_suite_prepared_window_beta_rows();
    $rows[3]['artifact_path'] = '/tmp/prepared-window-beta.md';
    $rows[4]['runner_command'] = './testfixture ../libsqlite/test/testrunner.tcl all';
    $rows[5]['scripts'] = ['README.md'];
    $rows[6]['dashboard_source_head'] = 'bad';
    $rows[7]['exit'] = 1;

    $record = libsqlite_suite_prepared_window_beta_record($rows);

    $t->same('blocked', $record['status']);
    $evidence = implode('; ', array_column($record['blockers'], 'id'));
    $t->contains('artifact-path-not-lane-local-note', $evidence);
    $t->contains('guarded-veryquick-command-missing', $evidence);
    $t->contains('concrete-test-scripts-missing', $evidence);
    $t->contains('dashboard_source_head-mismatch', $evidence);
    $t->contains('runner-artifact-not-zero-error', $evidence);
};

$tests['prepared window beta blocks duplicate broad runner and php mismatch'] = static function (TestRunner $t): void {
    $record = libsqlite_suite_prepared_window_beta_record(
        libsqlite_suite_prepared_window_beta_rows(),
        output: libsqlite_suite_prepared_window_beta_output(assertions: 143),
        snapshot: "12345 ./testfixture ../libsqlite/test/testrunner.tcl release\n"
    );

    $t->same('blocked', $record['status']);
    $t->same('blocked-active-runner', $record['active_runner_status']);
    $evidence = implode('; ', array_column($record['blockers'], 'id'));
    $t->contains('duplicate-broad-runner-active', $evidence);
    $t->contains('focused-php-pass-delta-mismatch', $evidence);
};

$tests['prepared window beta carries dependency closure and non overlap'] = static function (TestRunner $t): void {
    $record = libsqlite_suite_prepared_window_beta_record(libsqlite_suite_prepared_window_beta_rows());

    $t->contains('prepared-window-beta evidence prep', $record['dependency_closure']);
    $t->contains('lane-local notes', $record['dependency_closure']);
    $t->contains('prepared-window-alpha', $record['non_overlap_note']);
    $t->contains('release/all parity', $record['non_overlap_note']);
};

return $tests;

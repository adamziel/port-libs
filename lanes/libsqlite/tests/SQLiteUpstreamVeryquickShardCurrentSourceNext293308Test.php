<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteUpstreamSuiteEvidence;

function libsqlite_suite_next293308_evidence(): SQLiteUpstreamSuiteEvidence
{
    return SQLiteUpstreamSuiteEvidence::fromManifestPath(__DIR__ . '/../UPSTREAM_TEST_MANIFEST.json');
}

function libsqlite_suite_next293308_output(int $assertions = 160, int $failures = 0): string
{
    $lines = ['Focused test run: 1 selected test files (root lock skipped)'];
    for ($i = 1; $i <= $assertions; $i++) {
        $lines[] = sprintf('PASS current source next293-308 evidence prep case %03d', $i);
    }
    $lines[] = sprintf('1 test files, %d assertions, %d failures', $assertions, $failures);

    return implode("\n", $lines);
}

/**
 * @return list<array<string, mixed>>
 */
function libsqlite_suite_next293308_rows(
    string $launcherBase = '483323e72c0dc81d1e479309afb9cdc0cf8f649e',
    string $integrationSource = '8a447f4448e40477ad3aac75c8cb50d204e8cd53'
): array {
    $rows = [];
    foreach (range(293, 308) as $slice) {
        $script = sprintf('veryquick-current-source-next%d-prep.test', $slice);
        $rows[] = [
            'unit' => sprintf('suite-upstream-veryquick-shard-current-source-next%d', $slice),
            'kind' => 'prepared-upstream-veryquick-shard-runner',
            'artifact_path' => sprintf('lanes/libsqlite/notes/yield-suite-upstream-veryquick-shard-current-source-next%d.md', $slice),
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
function libsqlite_suite_next293308_record(
    array $rows,
    string $launcherBase = '483323e72c0dc81d1e479309afb9cdc0cf8f649e',
    string $integrationSource = '8a447f4448e40477ad3aac75c8cb50d204e8cd53',
    ?string $output = null,
    ?int $expected = 160,
    string $snapshot = ''
): array {
    return libsqlite_suite_next293308_evidence()->upstreamVeryquickShardCurrentSourceNext293308(
        $rows,
        684,
        138220,
        $launcherBase,
        $integrationSource,
        'lanes/libsqlite/tests/SQLiteUpstreamVeryquickShardCurrentSourceNext293308Test.php',
        $output ?? libsqlite_suite_next293308_output(),
        'current-source next293-308 suite evidence prep avoids merged next277-292 suite evidence, individual next293 through next308 shard countability, exact-shard next148, runner106/jsonvt104 rebase work, queued next317 planner/stat4 handoff, and release/all parity claims',
        $expected,
        $snapshot
    );
}

$tests = [];

$tests['current source next293-308 prepares all suite evidence rows'] = static function (TestRunner $t): void {
    $record = libsqlite_suite_next293308_record(libsqlite_suite_next293308_rows());

    $t->same('current-source-next293-308-suite-evidence-prepared', $record['status']);
    $t->same(16, $record['row_count']);
    $t->same(16, $record['zero_error_row_count']);
    $t->same(16, $record['lane_local_note_row_count']);
    $t->same(16, $record['slice_count']);
    $t->same([], $record['missing_slices']);
    $t->same('next293', $record['covered_slices'][0]);
    $t->same('next308', $record['covered_slices'][15]);
};

$tests['current source next293-308 is prepared evidence without mapped inflation'] = static function (TestRunner $t): void {
    $record = libsqlite_suite_next293308_record(libsqlite_suite_next293308_rows());

    $t->same(684, $record['current_mapped']);
    $t->same(684, $record['next_mapped']);
    $t->same(0, $record['mapped_delta']);
    $t->same(false, $record['counts_upstream_veryquick_shard_current_source_next293_308']);
    $t->same(false, $record['counts_upstream_veryquick_shard_current_source_next277_292']);
    $t->same(false, $record['counts_release_parity']);
    $t->contains('prepared upstream-suite evidence only', $record['next_gate']);
};

$tests['current source next293-308 records focused php admission'] = static function (TestRunner $t): void {
    $record = libsqlite_suite_next293308_record(libsqlite_suite_next293308_rows());

    $t->same(138220, $record['current_php_pass']);
    $t->same(138380, $record['next_php_pass']);
    $t->same(160, $record['php_pass_delta']);
    $t->same('clear', $record['active_runner_status']);
    $t->same(0, $record['active_runner_count']);
};

$tests['current source next293-308 records target scripts'] = static function (TestRunner $t): void {
    $record = libsqlite_suite_next293308_record(libsqlite_suite_next293308_rows());

    $t->same(17, $record['target_script_count']);
    $t->contains('testrunner.test', implode(',', $record['target_scripts']));
    $t->contains('veryquick-current-source-next293-prep.test', implode(',', $record['target_scripts']));
    $t->contains('veryquick-current-source-next308-prep.test', implode(',', $record['target_scripts']));
};

$tests['current source next293-308 blocks missing slices and outside rows'] = static function (TestRunner $t): void {
    $rows = libsqlite_suite_next293308_rows();
    array_pop($rows);
    $rows[] = array_merge($rows[0], ['unit' => 'suite-upstream-veryquick-shard-current-source-next292']);

    $record = libsqlite_suite_next293308_record($rows);

    $t->same('blocked', $record['status']);
    $t->same(['next308'], $record['missing_slices']);
    $evidence = implode('; ', array_column($record['blockers'], 'id'));
    $t->contains('slice-outside-next293-308', $evidence);
    $t->contains('missing-next-slice', $evidence);
};

$tests['current source next293-308 blocks provenance and artifact gaps'] = static function (TestRunner $t): void {
    $rows = libsqlite_suite_next293308_rows();
    $rows[3]['artifact_path'] = '/tmp/next296.md';
    $rows[4]['runner_command'] = './testfixture ../libsqlite/test/testrunner.tcl all';
    $rows[5]['scripts'] = ['README.md'];
    $rows[6]['dashboard_source_head'] = 'bad';
    $rows[7]['exit'] = 1;

    $record = libsqlite_suite_next293308_record($rows);

    $t->same('blocked', $record['status']);
    $evidence = implode('; ', array_column($record['blockers'], 'id'));
    $t->contains('artifact-path-not-lane-local-note', $evidence);
    $t->contains('guarded-veryquick-command-missing', $evidence);
    $t->contains('concrete-test-scripts-missing', $evidence);
    $t->contains('dashboard_source_head-mismatch', $evidence);
    $t->contains('runner-artifact-not-zero-error', $evidence);
};

$tests['current source next293-308 blocks duplicate broad runner and php mismatch'] = static function (TestRunner $t): void {
    $record = libsqlite_suite_next293308_record(
        libsqlite_suite_next293308_rows(),
        output: libsqlite_suite_next293308_output(assertions: 159),
        snapshot: "12345 ./testfixture ../libsqlite/test/testrunner.tcl release\n"
    );

    $t->same('blocked', $record['status']);
    $t->same('blocked-active-runner', $record['active_runner_status']);
    $evidence = implode('; ', array_column($record['blockers'], 'id'));
    $t->contains('duplicate-broad-runner-active', $evidence);
    $t->contains('focused-php-pass-delta-mismatch', $evidence);
};

$tests['current source next293-308 carries dependency closure and non overlap'] = static function (TestRunner $t): void {
    $record = libsqlite_suite_next293308_record(libsqlite_suite_next293308_rows());

    $t->contains('current-source next293-308 evidence prep', $record['dependency_closure']);
    $t->contains('lane-local notes', $record['dependency_closure']);
    $t->contains('next277-292', $record['non_overlap_note']);
    $t->contains('release/all parity', $record['non_overlap_note']);
};

return $tests;

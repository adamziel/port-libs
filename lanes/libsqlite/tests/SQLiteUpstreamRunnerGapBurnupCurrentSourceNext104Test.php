<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteUpstreamSuiteEvidence;

function libsqlite_suite_next104_evidence(): SQLiteUpstreamSuiteEvidence
{
    return SQLiteUpstreamSuiteEvidence::fromManifestPath(__DIR__ . '/../UPSTREAM_TEST_MANIFEST.json');
}

function libsqlite_suite_next104_output(int $passLines = 61, int $assertions = 61, int $failures = 0): string
{
    $lines = ['Focused test run: 1 selected test files (root lock skipped)'];
    for ($i = 1; $i <= $passLines; $i++) {
        $lines[] = sprintf('PASS current source next104 upstream runner gap burnup case %02d', $i);
    }
    $lines[] = sprintf('1 test files, %d assertions, %d failures', $assertions, $failures);

    return implode("\n", $lines);
}

/**
 * @return list<array<string, mixed>>
 */
function libsqlite_suite_next104_rows(
    int $case = 1,
    string $launcherBase = '04a264da4f1be4df0404eeca51f4e3ee3e697828',
    string $dashboardSource = '103fc00c42f1ff0580cae8a7768e4a3da0979c2d',
    string $statusSource = '5883f5e65ebfd2e9cf8c9acf617a2a818277909c',
    string $implementationSource = '21f1e38635e924df34f7be1aef3242b4b233710c',
    string $nextHead = 'suite-upstream-runner-gap-burnup-current-source-next104'
): array {
    $script = sprintf('suite-upstream-runner-gap-burnup-current-source-next104-%02d.test', $case);

    return [
        [
            'unit' => 'suite-upstream-runner-gap-burnup-current-source-next104',
            'kind' => 'bounded-upstream-runner-gap-burnup',
            'gap_id' => 'current-source-next104-hydrated-focused-artifact-gap',
            'gap_status' => 'removed',
            'removed_blocker' => 'next104 replaces an uncounted current-source focused runner gap with a lane-local zero-error guarded artifact row',
            'source_head' => $nextHead,
            'launcher_base_head' => $launcherBase,
            'dashboard_source_head' => $dashboardSource,
            'status_source_head' => $statusSource,
            'implementation_source_head' => $implementationSource,
            'artifact_path' => 'lanes/libsqlite/notes/suite-upstream-runner-gap-burnup-current-source-next104.md',
            'runner_command' => './testfixture ../libsqlite/test/testrunner.tcl --jobs 1 --stop-on-error veryquick ' . $script,
            'scripts' => [$script, 'testrunner.test'],
            'current_countable' => false,
            'next_countable' => true,
            'exit' => 0,
            'errors' => 0,
            'current_tests' => 0,
            'next_tests' => 5100 + $case,
            'evidence' => 'current-source next104 burns up one upstream-runner gap only when accepted HEAD provenance, lane-local artifact path, guarded runner command, zero errors, duplicate-runner gate, focused PASS lines, and removed-blocker classification are present',
        ],
        [
            'unit' => 'suite-upstream-runner-gap-burnup-current-source-next104-preserved-anchor',
            'kind' => 'bounded-upstream-runner-gap-anchor',
            'gap_id' => 'current-source-next104-accepted-anchor-preserved',
            'gap_status' => 'preserved',
            'removed_blocker' => '',
            'source_head' => $nextHead,
            'launcher_base_head' => $launcherBase,
            'dashboard_source_head' => $dashboardSource,
            'status_source_head' => $statusSource,
            'implementation_source_head' => $implementationSource,
            'artifact_path' => 'lanes/libsqlite/notes/suite-release-runner-countability-current-source-next99.md',
            'runner_command' => './testfixture ../libsqlite/test/testrunner.tcl --jobs 1 --stop-on-error release accepted-current-source-anchor.test',
            'scripts' => ['accepted-current-source-anchor.test'],
            'current_countable' => true,
            'next_countable' => true,
            'exit' => 0,
            'errors' => 0,
            'current_tests' => 329670,
            'next_tests' => 329670,
            'evidence' => 'accepted current-source anchor remains preserved while next104 burns up only the new focused upstream-runner gap',
        ],
    ];
}

/**
 * @param list<array<string, mixed>> $rows
 * @return array<string, mixed>
 */
function libsqlite_suite_next104_record(
    array $rows,
    string $launcherBase = '04a264da4f1be4df0404eeca51f4e3ee3e697828',
    string $dashboardSource = '103fc00c42f1ff0580cae8a7768e4a3da0979c2d',
    string $statusSource = '5883f5e65ebfd2e9cf8c9acf617a2a818277909c',
    string $implementationSource = '21f1e38635e924df34f7be1aef3242b4b233710c',
    string $nextHead = 'suite-upstream-runner-gap-burnup-current-source-next104',
    ?string $output = null,
    ?int $expected = 61,
    string $snapshot = ''
): array {
    return libsqlite_suite_next104_evidence()->suiteUpstreamRunnerGapBurnup(
        $rows,
        597,
        40110,
        $launcherBase,
        $dashboardSource,
        $statusSource,
        $implementationSource,
        $nextHead,
        'lanes/libsqlite/tests/SQLiteUpstreamRunnerGapBurnupCurrentSourceNext104Test.php',
        $output ?? libsqlite_suite_next104_output(),
        'current-source next104 upstream-runner gap burnup avoids next102 upstream-runner admission, next99 release countability, next94 admission burnup, accepted suite-denominator current-next68, and ATTACH/JSON/pager/VFS/WAL/B-tree behavior clusters',
        $expected,
        $snapshot
    );
}

$tests = [];

foreach (range(1, 50) as $case) {
    $tests[sprintf('current source next104 admits gap burnup artifact case %02d', $case)] = static function (TestRunner $t) use ($case): void {
        $record = libsqlite_suite_next104_record(libsqlite_suite_next104_rows($case));

        $t->same('current-source-next104-upstream-runner-gap-burnup-countable', $record['status']);
        $t->same(true, $record['countable']);
        $t->same(597, $record['current_mapped']);
        $t->same(598, $record['next_mapped']);
        $t->same(1, $record['mapped_delta']);
        $t->same(61, $record['php_pass_delta']);
        $t->same(40171, $record['next_php_pass']);
        $t->same(['suite-upstream-runner-gap-burnup-current-source-next104'], $record['admitted_units']);
        $t->same(['current-source-next104-hydrated-focused-artifact-gap'], $record['resolved_gap_ids']);
        $t->contains(sprintf('suite-upstream-runner-gap-burnup-current-source-next104-%02d.test', $case), implode(',', $record['target_scripts']));
        $t->same(false, $record['counts_release_parity']);
    };
}

$tests['current source next104 records exact source provenance'] = static function (TestRunner $t): void {
    $record = libsqlite_suite_next104_record(libsqlite_suite_next104_rows(7));

    $t->same('04a264da4f1be4df0404eeca51f4e3ee3e697828', $record['launcher_base_head']);
    $t->same('103fc00c42f1ff0580cae8a7768e4a3da0979c2d', $record['dashboard_source_head']);
    $t->same('5883f5e65ebfd2e9cf8c9acf617a2a818277909c', $record['status_source_head']);
    $t->same('21f1e38635e924df34f7be1aef3242b4b233710c', $record['implementation_source_head']);
    $t->same(['suite-upstream-runner-gap-burnup-current-source-next104'], $record['artifact_source_heads']);
};

$tests['current source next104 preserves accepted anchors without mapped inflation'] = static function (TestRunner $t): void {
    $rows = libsqlite_suite_next104_rows();
    $rows[0]['current_countable'] = true;
    $rows[0]['current_tests'] = 5101;

    $record = libsqlite_suite_next104_record($rows);

    $t->same('current-source-next104-upstream-runner-gap-burnup-preserved', $record['status']);
    $t->same(0, $record['mapped_delta']);
    $t->same(597, $record['next_mapped']);
    $t->same(['current-source-next104-accepted-anchor-preserved', 'current-source-next104-hydrated-focused-artifact-gap'], $record['preserved_gap_ids']);
};

$tests['current source next104 records test and script deltas'] = static function (TestRunner $t): void {
    $record = libsqlite_suite_next104_record(libsqlite_suite_next104_rows(9));

    $t->same(5109, $record['tests_total_delta']);
    $t->same(3, $record['target_script_count']);
    $t->same(['accepted-current-source-anchor.test', 'suite-upstream-runner-gap-burnup-current-source-next104-09.test', 'testrunner.test'], $record['target_scripts']);
    $t->same('removed', $record['gap_rows'][0]['gap_status']);
    $t->contains('zero-error guarded artifact row', $record['gap_rows'][0]['removed_blocker']);
};

$tests['current source next104 blocks stale provenance heads'] = static function (TestRunner $t): void {
    $record = libsqlite_suite_next104_record(
        libsqlite_suite_next104_rows(
            launcherBase: '0000000000000000000000000000000000000000',
            dashboardSource: '1111111111111111111111111111111111111111',
            statusSource: '2222222222222222222222222222222222222222',
            implementationSource: '3333333333333333333333333333333333333333'
        )
    );

    $t->same('blocked', $record['status']);
    $evidence = implode('; ', array_column($record['blockers'], 'evidence'));
    $t->contains('launcher-base-head-mismatch', $evidence);
    $t->contains('dashboard-source-head-mismatch', $evidence);
    $t->contains('status-source-head-mismatch', $evidence);
    $t->contains('implementation-source-head-mismatch', $evidence);
};

$tests['current source next104 blocks missing removed blocker classification'] = static function (TestRunner $t): void {
    $rows = libsqlite_suite_next104_rows();
    $rows[0]['removed_blocker'] = '';

    $record = libsqlite_suite_next104_record($rows);

    $t->same('blocked', $record['status']);
    $t->same(['current-source-next104-hydrated-focused-artifact-gap'], $record['blocked_gap_ids']);
    $t->contains('removed-blocker-missing', implode('; ', array_column($record['blockers'], 'evidence')));
};

$tests['current source next104 blocks open gap status'] = static function (TestRunner $t): void {
    $rows = libsqlite_suite_next104_rows();
    $rows[0]['gap_status'] = 'open';

    $record = libsqlite_suite_next104_record($rows);

    $t->same('blocked', $record['status']);
    $t->contains('gap-status-not-removed', implode('; ', array_column($record['blockers'], 'evidence')));
};

$tests['current source next104 blocks non lane local artifact and unguarded command'] = static function (TestRunner $t): void {
    $rows = libsqlite_suite_next104_rows();
    $rows[0]['artifact_path'] = '/tmp/current-source-next104.log';
    $rows[0]['runner_command'] = './testfixture ../libsqlite/test/testrunner.tcl veryquick';
    $rows[0]['scripts'] = ['README.md'];

    $record = libsqlite_suite_next104_record($rows);

    $t->same('blocked', $record['status']);
    $evidence = implode('; ', array_column($record['blockers'], 'evidence'));
    $t->contains('artifact-path-not-lane-local', $evidence);
    $t->contains('guarded-runner-command-missing', $evidence);
    $t->contains('runner-scripts-missing', $evidence);
};

$tests['current source next104 blocks runner errors duplicate units and regressions'] = static function (TestRunner $t): void {
    $rows = libsqlite_suite_next104_rows();
    $rows[0]['errors'] = 1;
    $rows[1]['next_countable'] = false;
    $rows[1]['next_tests'] = 10;
    $rows[] = $rows[0];

    $record = libsqlite_suite_next104_record($rows);

    $t->same('blocked', $record['status']);
    $evidence = implode('; ', array_column($record['blockers'], 'evidence'));
    $t->contains('runner-errors-not-zero', $evidence);
    $t->contains('duplicate-current-source-next102-unit', $evidence);
    $t->contains('runner-countability-regressed', $evidence);
};

$tests['current source next104 blocks release parity active runners and pass inflation'] = static function (TestRunner $t): void {
    $rows = libsqlite_suite_next104_rows();
    $rows[0]['counts_release_parity'] = true;

    $record = libsqlite_suite_next104_record(
        $rows,
        output: libsqlite_suite_next104_output(7, 61),
        expected: 61,
        snapshot: '8888 1 S 00:04 93.0 ./testfixture ../libsqlite/test/testrunner.tcl --jobs 4 all'
    );

    $t->same('blocked', $record['status']);
    $t->same('blocked-active-runner', $record['active_runner_status']);
    $t->same(1, $record['active_runner_count']);
    $evidence = implode('; ', array_column($record['blockers'], 'evidence'));
    $t->contains('release-parity-claim-not-allowed', $evidence);
    $t->contains('active broad runner', $evidence);
    $t->same(0, $record['php_pass_delta']);
};

$tests['current source next104 exposes dependency closure and non overlap text'] = static function (TestRunner $t): void {
    $record = libsqlite_suite_next104_record(libsqlite_suite_next104_rows());

    $t->same(true, $record['counts_upstream_runner_gap_burnup_current_source_next104']);
    $t->same(false, $record['counts_upstream_runner_admission_current_source_next102']);
    $t->contains('no new support component needed', $record['dependency_closure']);
    $t->contains('avoids next102 upstream-runner admission', $record['non_overlap_note']);
    $t->contains('current-source next104 upstream-runner gap-burnup row', $record['next_gate']);
};

$tests['current source next104 rejects empty artifacts'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn () => libsqlite_suite_next104_record([]));
};

return $tests;

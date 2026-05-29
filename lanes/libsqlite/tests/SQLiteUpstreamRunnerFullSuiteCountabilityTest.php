<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteUpstreamSuiteEvidence;

function libsqlite_suite_full_countability_evidence(): SQLiteUpstreamSuiteEvidence
{
    return SQLiteUpstreamSuiteEvidence::fromManifestPath(__DIR__ . '/../UPSTREAM_TEST_MANIFEST.json');
}

function libsqlite_suite_full_suite_countability_output(int $passLines = 76, int $assertions = 89, int $failures = 0): string
{
    $lines = ['Focused test run: 1 selected test files (root lock skipped)'];
    for ($i = 1; $i <= $passLines; $i++) {
        $lines[] = sprintf('PASS current source full-suite-countability full suite countability case %02d', $i);
    }
    $lines[] = sprintf('1 test files, %d assertions, %d failures', $assertions, $failures);

    return implode("\n", $lines);
}

/**
 * @return list<array<string, mixed>>
 */
function libsqlite_suite_full_suite_countability_rows(
    int $case = 1,
    string $launcherBase = '787669747da8551b14c97285aeffff4669d1c6e3',
    string $dashboardSource = '8a447f445e5d2fd32fc9fd463117f585d1416551',
    string $statusSource = '8a447f445e5d2fd32fc9fd463117f585d1416551',
    string $implementationSource = '8a447f445e5d2fd32fc9fd463117f585d1416551',
    string $nextHead = 'upstream-runner-full-suite-countability'
): array {
    $script = sprintf('full-suite-countability-%02d.test', $case);

    return [
        [
            'unit' => 'upstream-runner-full-suite-countability',
            'kind' => 'bounded-upstream-runner-full-suite-countability',
            'gap_id' => 'current-source-full-suite-countability-gap',
            'gap_status' => 'removed',
            'removed_blocker' => 'current-source-full-suite-countability replaces stale full-suite countability prose with a current accepted-head lane-local zero-error guarded artifact row',
            'tier' => 'full-suite-countability',
            'source_head' => $nextHead,
            'launcher_base_head' => $launcherBase,
            'dashboard_source_head' => $dashboardSource,
            'status_source_head' => $statusSource,
            'implementation_source_head' => $implementationSource,
            'artifact_path' => 'lanes/libsqlite/notes/upstream-runner-full-suite-countability.md',
            'runner_command' => './testfixture ../libsqlite/test/testrunner.tcl --jobs 1 --stop-on-error all ' . $script,
            'scripts' => [$script, 'testrunner.test'],
            'current_countable' => false,
            'next_countable' => true,
            'exit' => 0,
            'errors' => 0,
            'current_tests' => 0,
            'next_tests' => 44622 + $case,
        ],
        [
            'unit' => 'batch109-113-current-source-anchor',
            'kind' => 'accepted-upstream-runner-anchor',
            'gap_id' => 'accepted-batch109-113-suite-anchor',
            'gap_status' => 'preserved',
            'removed_blocker' => '',
            'tier' => 'accepted-anchor',
            'source_head' => $nextHead,
            'launcher_base_head' => $launcherBase,
            'dashboard_source_head' => $dashboardSource,
            'status_source_head' => $statusSource,
            'implementation_source_head' => $implementationSource,
            'artifact_path' => 'lanes/libsqlite/notes/suite-upstream-runner-gap-burnup-current-source-next104.md',
            'runner_command' => './testfixture ../libsqlite/test/testrunner.tcl --jobs 1 --stop-on-error veryquick accepted-batch109-anchor.test',
            'scripts' => ['accepted-batch109-anchor.test'],
            'current_countable' => true,
            'next_countable' => true,
            'exit' => 0,
            'errors' => 0,
            'current_tests' => 44622,
            'next_tests' => 44622,
        ],
    ];
}

/**
 * @param list<array<string, mixed>> $rows
 * @return array<string, mixed>
 */
function libsqlite_suite_full_suite_countability_record(
    array $rows,
    string $launcherBase = '787669747da8551b14c97285aeffff4669d1c6e3',
    string $dashboardSource = '8a447f445e5d2fd32fc9fd463117f585d1416551',
    string $statusSource = '8a447f445e5d2fd32fc9fd463117f585d1416551',
    string $implementationSource = '8a447f445e5d2fd32fc9fd463117f585d1416551',
    string $nextHead = 'upstream-runner-full-suite-countability',
    ?string $output = null,
    ?int $expected = 89,
    string $snapshot = ''
): array {
    return libsqlite_suite_full_countability_evidence()->upstreamRunnerFullSuiteCountability(
        $rows,
        604,
        44622,
        $launcherBase,
        $dashboardSource,
        $statusSource,
        $implementationSource,
        $nextHead,
        'lanes/libsqlite/tests/SQLiteUpstreamRunnerFullSuiteCountabilityTest.php',
        $output ?? libsqlite_suite_full_suite_countability_output(),
        'current-source full-suite-countability full-suite countability avoids next104 gap burnup, current-source-repro repro count, next108 suite evidence rebase, runner106 rebase, jsonvt104 rebase, and all accepted SQL/JSON/WAL/VFS/B-tree behavior clusters',
        $expected,
        $snapshot
    );
}

$tests = [];

foreach (range(1, 70) as $case) {
    $tests[sprintf('current source full-suite-countability admits full suite countability case %02d', $case)] = static function (TestRunner $t) use ($case): void {
        $record = libsqlite_suite_full_suite_countability_record(libsqlite_suite_full_suite_countability_rows($case));

        $t->same('current-source-full-suite-countability-advanced', $record['status']);
        $t->same(true, $record['countable']);
        $t->same(604, $record['current_mapped']);
        $t->same(605, $record['next_mapped']);
        $t->same(1, $record['mapped_delta']);
        $t->same(89, $record['php_pass_delta']);
        $t->same(44711, $record['next_php_pass']);
        $t->same(['upstream-runner-full-suite-countability'], $record['admitted_units']);
        $t->same(['batch109-113-current-source-anchor'], $record['preserved_units']);
        $t->contains(sprintf('full-suite-countability-%02d.test', $case), implode(',', $record['target_scripts']));
        $t->same(true, $record['counts_upstream_runner_full_suite_countability']);
        $t->same(false, $record['counts_upstream_runner_suite_evidence_rebase_current_source_next108']);
        $t->same(false, $record['counts_release_parity']);
    };
}

$tests['current source full-suite-countability records accepted provenance'] = static function (TestRunner $t): void {
    $record = libsqlite_suite_full_suite_countability_record(libsqlite_suite_full_suite_countability_rows(8));

    $t->same('787669747da8551b14c97285aeffff4669d1c6e3', $record['launcher_base_head']);
    $t->same('8a447f445e5d2fd32fc9fd463117f585d1416551', $record['dashboard_source_head']);
    $t->same('8a447f445e5d2fd32fc9fd463117f585d1416551', $record['status_source_head']);
    $t->same('8a447f445e5d2fd32fc9fd463117f585d1416551', $record['implementation_source_head']);
    $t->same(['upstream-runner-full-suite-countability'], $record['artifact_source_heads']);
};

$tests['current source full-suite-countability records target scripts and tier counts'] = static function (TestRunner $t): void {
    $record = libsqlite_suite_full_suite_countability_record(libsqlite_suite_full_suite_countability_rows(11));

    $t->same(44633, $record['tests_total_delta']);
    $t->same(['accepted-batch109-anchor.test', 'full-suite-countability-11.test', 'testrunner.test'], $record['target_scripts']);
    $t->same('accepted-anchor', $record['tier_counts'][0]['tier']);
    $t->same(0, $record['tier_counts'][0]['admitted']);
    $t->same(1, $record['tier_counts'][0]['preserved']);
    $t->same('full-suite-countability', $record['tier_counts'][1]['tier']);
    $t->same(1, $record['tier_counts'][1]['admitted']);
    $t->same(44633, $record['tier_counts'][1]['tests']);
};

$tests['current source full-suite-countability preserves already counted row without mapped inflation'] = static function (TestRunner $t): void {
    $rows = libsqlite_suite_full_suite_countability_rows();
    $rows[0]['current_countable'] = true;
    $rows[0]['current_tests'] = 44623;

    $record = libsqlite_suite_full_suite_countability_record($rows);

    $t->same('current-source-full-suite-countability-preserved', $record['status']);
    $t->same(0, $record['mapped_delta']);
    $t->same(604, $record['next_mapped']);
    $t->same(['batch109-113-current-source-anchor', 'upstream-runner-full-suite-countability'], $record['preserved_units']);
};

$tests['current source full-suite-countability blocks stale source provenance'] = static function (TestRunner $t): void {
    $record = libsqlite_suite_full_suite_countability_record(
        libsqlite_suite_full_suite_countability_rows(
            launcherBase: '0000000000000000000000000000000000000000',
            dashboardSource: '1111111111111111111111111111111111111111',
            statusSource: '2222222222222222222222222222222222222222',
            implementationSource: '3333333333333333333333333333333333333333'
        )
    );

    $t->same('blocked', $record['status']);
    $t->same(0, $record['mapped_delta']);
    $evidence = implode('; ', array_column($record['blockers'], 'evidence'));
    $t->contains('launcher-base-head-mismatch', $evidence);
    $t->contains('dashboard-source-head-mismatch', $evidence);
    $t->contains('status-source-head-mismatch', $evidence);
    $t->contains('implementation-source-head-mismatch', $evidence);
};

$tests['current source full-suite-countability blocks unguarded and non local artifacts'] = static function (TestRunner $t): void {
    $rows = libsqlite_suite_full_suite_countability_rows();
    $rows[0]['artifact_path'] = '/tmp/full-suite-countability.md';
    $rows[0]['runner_command'] = './testfixture ../libsqlite/test/testrunner.tcl all';
    $rows[0]['scripts'] = ['README.md'];

    $record = libsqlite_suite_full_suite_countability_record($rows);

    $t->same('blocked', $record['status']);
    $evidence = implode('; ', array_column($record['blockers'], 'evidence'));
    $t->contains('artifact-path-not-lane-local', $evidence);
    $t->contains('guarded-runner-command-missing', $evidence);
    $t->contains('concrete-test-scripts-missing', $evidence);
};

$tests['current source full-suite-countability blocks non zero runner artifacts'] = static function (TestRunner $t): void {
    $rows = libsqlite_suite_full_suite_countability_rows();
    $rows[0]['exit'] = 1;
    $rows[0]['errors'] = 2;

    $record = libsqlite_suite_full_suite_countability_record($rows);

    $t->same('blocked', $record['status']);
    $t->same(0, $record['php_pass_delta']);
    $t->contains('runner-artifact-not-zero-error', implode('; ', array_column($record['blockers'], 'evidence')));
};

$tests['current source full-suite-countability blocks missing removed blocker classification'] = static function (TestRunner $t): void {
    $rows = libsqlite_suite_full_suite_countability_rows();
    $rows[0]['removed_blocker'] = '';

    $record = libsqlite_suite_full_suite_countability_record($rows);

    $t->same('blocked', $record['status']);
    $t->contains('removed-blocker-missing', implode('; ', array_column($record['blockers'], 'evidence')));
};

$tests['current source full-suite-countability blocks focused php admission mismatch'] = static function (TestRunner $t): void {
    $record = libsqlite_suite_full_suite_countability_record(libsqlite_suite_full_suite_countability_rows(), output: libsqlite_suite_full_suite_countability_output(assertions: 88));

    $t->same('blocked', $record['status']);
    $t->same(0, $record['php_pass_delta']);
    $t->contains('focused PHP assertion delta did not match', implode('; ', array_column($record['blockers'], 'evidence')));
};

$tests['current source full-suite-countability blocks active broad runner snapshot'] = static function (TestRunner $t): void {
    $snapshot = '1234 1 S 00:10 7.5 ./testfixture ../libsqlite/test/testrunner.tcl --jobs 1 --stop-on-error all';
    $record = libsqlite_suite_full_suite_countability_record(libsqlite_suite_full_suite_countability_rows(), snapshot: $snapshot);

    $t->same('blocked', $record['status']);
    $t->same('blocked-active-runner', $record['active_runner_status']);
    $t->same(1, $record['active_runner_count']);
    $t->contains('active broad runner', implode('; ', array_column($record['blockers'], 'evidence')));
};

$tests['current source full-suite-countability reports dependency closure and next gate'] = static function (TestRunner $t): void {
    $record = libsqlite_suite_full_suite_countability_record(libsqlite_suite_full_suite_countability_rows(21));

    $t->contains('publish only the current-source full-suite-countability full-suite countability row', $record['next_gate']);
    $t->contains('no new support component needed', $record['dependency_closure']);
    $t->contains('runner106 rebase', $record['non_overlap_note']);
};

return $tests;

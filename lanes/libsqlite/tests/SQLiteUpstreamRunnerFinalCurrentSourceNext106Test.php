<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteUpstreamSuiteEvidence;

function libsqlite_suite_next106_evidence(): SQLiteUpstreamSuiteEvidence
{
    return SQLiteUpstreamSuiteEvidence::fromManifestPath(__DIR__ . '/../UPSTREAM_TEST_MANIFEST.json');
}

function libsqlite_suite_next106_output(int $passLines = 19, int $assertions = 126, int $failures = 0): string
{
    $lines = ['Focused test run: 1 selected test files (root lock skipped)'];
    for ($i = 1; $i <= $passLines; $i++) {
        $lines[] = sprintf('PASS current source next106 isolated suite evidence case %02d', $i);
    }
    $lines[] = sprintf('1 test files, %d assertions, %d failures', $assertions, $failures);

    return implode("\n", $lines);
}

/**
 * @return list<array<string, mixed>>
 */
function libsqlite_suite_next106_rows(
    int $case = 1,
    string $launcherBase = '04a264da4f1be4df0404eeca51f4e3ee3e697828',
    string $dashboardSource = '103fc00c42f1ff0580cae8a7768e4a3da0979c2d',
    string $statusSource = '5883f5e65ebfd2e9cf8c9acf617a2a818277909c',
    string $implementationSource = '21f1e38635e924df34f7be1aef3242b4b233710c',
    string $nextHead = 'suite-upstream-runner-final-current-source-next106'
): array {
    $script = sprintf('suite-upstream-runner-final-current-source-next106-%02d.test', $case);

    return [
        [
            'unit' => 'suite-upstream-runner-final-current-source-next106',
            'kind' => 'bounded-upstream-runner-final-suite-evidence',
            'gap_id' => 'current-source-next106-final-isolated-gap',
            'gap_status' => 'removed',
            'removed_blocker' => 'next106 replaces the prepared current-source next104-106 suite evidence gap with a fresh isolated lane-local zero-error artifact',
            'prerequisite_range' => 'current-source-next101-103',
            'prerequisite_status' => 'ready',
            'readiness_evidence' => 'ready next101-103 prerequisite evidence is accepted before final next104-106 suite evidence is counted',
            'final_range' => 'current-source-next104-106',
            'final_range_status' => 'isolated',
            'final_range_evidence' => 'final next104-106 evidence is isolated from stale baselines and only counts this requested range',
            'source_head' => $nextHead,
            'launcher_base_head' => $launcherBase,
            'dashboard_source_head' => $dashboardSource,
            'status_source_head' => $statusSource,
            'implementation_source_head' => $implementationSource,
            'artifact_path' => 'lanes/libsqlite/notes/suite-upstream-runner-final-current-source-next106.md',
            'runner_command' => './testfixture ../libsqlite/test/testrunner.tcl --jobs 1 --stop-on-error veryquick ' . $script,
            'scripts' => [$script, 'testrunner.test'],
            'current_countable' => false,
            'next_countable' => true,
            'exit' => 0,
            'errors' => 0,
            'current_tests' => 0,
            'next_tests' => 5300 + $case,
            'evidence' => 'current-source next106 counts only the final next104-106 suite evidence slice after next101-103 readiness and without duplicate stale baselines or release/all parity claims',
        ],
        [
            'unit' => 'suite-upstream-runner-final-current-source-next106-preserved-anchor',
            'kind' => 'bounded-upstream-runner-final-anchor',
            'gap_id' => 'current-source-next106-preserved-anchor',
            'gap_status' => 'preserved',
            'removed_blocker' => '',
            'prerequisite_range' => 'current-source-next101-103',
            'prerequisite_status' => 'ready',
            'readiness_evidence' => 'accepted prerequisite anchor remains ready',
            'final_range' => 'current-source-next104-106',
            'final_range_status' => 'isolated',
            'final_range_evidence' => 'accepted anchor is preserved without adding mapped movement',
            'source_head' => $nextHead,
            'launcher_base_head' => $launcherBase,
            'dashboard_source_head' => $dashboardSource,
            'status_source_head' => $statusSource,
            'implementation_source_head' => $implementationSource,
            'artifact_path' => 'lanes/libsqlite/notes/suite-upstream-runner-gap-burnup-current-source-next104.md',
            'runner_command' => './testfixture ../libsqlite/test/testrunner.tcl --jobs 1 --stop-on-error veryquick accepted-current-source-anchor.test',
            'scripts' => ['accepted-current-source-anchor.test'],
            'current_countable' => true,
            'next_countable' => true,
            'exit' => 0,
            'errors' => 0,
            'current_tests' => 329670,
            'next_tests' => 329670,
            'evidence' => 'accepted current-source anchor remains preserved while final next104-106 evidence counts only the new isolated row',
        ],
    ];
}

/**
 * @param list<array<string, mixed>> $rows
 * @return array<string, mixed>
 */
function libsqlite_suite_next106_record(
    array $rows,
    string $launcherBase = '04a264da4f1be4df0404eeca51f4e3ee3e697828',
    string $dashboardSource = '103fc00c42f1ff0580cae8a7768e4a3da0979c2d',
    string $statusSource = '5883f5e65ebfd2e9cf8c9acf617a2a818277909c',
    string $implementationSource = '21f1e38635e924df34f7be1aef3242b4b233710c',
    string $nextHead = 'suite-upstream-runner-final-current-source-next106',
    ?string $output = null,
    ?int $expected = 19,
    string $snapshot = ''
): array {
    return libsqlite_suite_next106_evidence()->suiteUpstreamRunnerFinalEvidence(
        $rows,
        598,
        40171,
        $launcherBase,
        $dashboardSource,
        $statusSource,
        $implementationSource,
        $nextHead,
        'lanes/libsqlite/tests/SQLiteUpstreamRunnerFinalCurrentSourceNext106Test.php',
        $output ?? libsqlite_suite_next106_output(),
        'current-source next104-106 final suite evidence follows ready next101-103 and avoids next102 admission, next104 gap burnup, next108 rebase work, stale accepted suite baselines, and release/all parity claims',
        $expected,
        $snapshot
    );
}

$tests = [];

foreach (range(1, 12) as $case) {
    $tests[sprintf('current source next106 counts isolated final suite evidence case %02d', $case)] = static function (TestRunner $t) use ($case): void {
        $record = libsqlite_suite_next106_record(libsqlite_suite_next106_rows($case));

        $t->same('current-source-next106-final-suite-evidence-countable', $record['status']);
        $t->same(true, $record['countable']);
        $t->same(598, $record['current_mapped']);
        $t->same(599, $record['next_mapped']);
        $t->same(1, $record['mapped_delta']);
        $t->same(19, $record['php_pass_delta']);
        $t->same(40190, $record['next_php_pass']);
        $t->same(['current-source-next106-final-isolated-gap'], $record['isolated_final_gap_ids']);
        $t->contains(sprintf('suite-upstream-runner-final-current-source-next106-%02d.test', $case), implode(',', $record['target_scripts']));
        $t->same(false, $record['counts_release_parity']);
    };
}

$tests['current source next106 records prerequisite and final metadata'] = static function (TestRunner $t): void {
    $record = libsqlite_suite_next106_record(libsqlite_suite_next106_rows(4));

    $t->same(true, $record['counts_upstream_runner_final_current_source_next106']);
    $t->same(false, $record['counts_upstream_runner_gap_burnup_current_source_next104']);
    $t->same(false, $record['counts_upstream_runner_prerequisite_ready_current_source_next105']);
    $t->same('current-source-next101-103', $record['final_range_rows'][0]['prerequisite_range']);
    $t->same('ready', $record['final_range_rows'][0]['prerequisite_status']);
    $t->same('current-source-next104-106', $record['final_range_rows'][0]['final_range']);
    $t->same('isolated', $record['final_range_rows'][0]['final_range_status']);
};

$tests['current source next106 preserves already counted final row'] = static function (TestRunner $t): void {
    $rows = libsqlite_suite_next106_rows();
    $rows[0]['current_countable'] = true;
    $rows[0]['current_tests'] = 5301;

    $record = libsqlite_suite_next106_record($rows);

    $t->same('current-source-next106-final-suite-evidence-preserved', $record['status']);
    $t->same(0, $record['mapped_delta']);
    $t->same(598, $record['next_mapped']);
    $t->same(['current-source-next106-final-isolated-gap', 'current-source-next106-preserved-anchor'], $record['preserved_final_gap_ids']);
};

$tests['current source next106 blocks stale prerequisite readiness'] = static function (TestRunner $t): void {
    $rows = libsqlite_suite_next106_rows();
    $rows[0]['prerequisite_range'] = 'current-source-next100-102';
    $rows[0]['prerequisite_status'] = 'blocked';
    $rows[0]['readiness_evidence'] = '';

    $record = libsqlite_suite_next106_record($rows);

    $t->same('blocked', $record['status']);
    $t->same(['current-source-next106-final-isolated-gap'], $record['blocked_prerequisite_gap_ids']);
    $evidence = implode('; ', array_column($record['blockers'], 'evidence'));
    $t->contains('prerequisite-range-not-next101-103', $evidence);
    $t->contains('prerequisite-status-not-ready', $evidence);
    $t->contains('readiness-evidence-missing', $evidence);
};

$tests['current source next106 blocks non isolated final range'] = static function (TestRunner $t): void {
    $rows = libsqlite_suite_next106_rows();
    $rows[0]['final_range'] = 'current-source-next104';
    $rows[0]['final_range_status'] = 'shared-baseline';
    $rows[0]['final_range_evidence'] = '';

    $record = libsqlite_suite_next106_record($rows);

    $t->same('blocked', $record['status']);
    $t->same(['current-source-next106-final-isolated-gap'], $record['blocked_final_gap_ids']);
    $evidence = implode('; ', array_column($record['blockers'], 'evidence'));
    $t->contains('final-range-not-next104-106', $evidence);
    $t->contains('final-range-not-isolated', $evidence);
    $t->contains('final-range-evidence-missing', $evidence);
};

$tests['current source next106 blocks stale source and duplicate broad runner'] = static function (TestRunner $t): void {
    $rows = libsqlite_suite_next106_rows();
    $rows[0]['source_head'] = 'stale-next-source';

    $record = libsqlite_suite_next106_record(
        $rows,
        snapshot: '9182 1 S 00:02 98.0 ./testfixture ../libsqlite/test/testrunner.tcl --jobs 4 all'
    );

    $t->same('blocked', $record['status']);
    $t->same('blocked-active-runner', $record['active_runner_status']);
    $evidence = implode('; ', array_column($record['blockers'], 'evidence'));
    $t->contains('next-source-head-mismatch', $evidence);
    $t->contains('active broad runner', $evidence);
};

$tests['current source next106 blocks focused php pass mismatch'] = static function (TestRunner $t): void {
    $record = libsqlite_suite_next106_record(
        libsqlite_suite_next106_rows(),
        output: libsqlite_suite_next106_output(passLines: 18),
        expected: 19
    );

    $t->same('blocked', $record['status']);
    $t->same(40171, $record['next_php_pass']);
    $t->contains('focused PHP PASS-line admission', implode('; ', array_column($record['blockers'], 'evidence')));
};

$tests['current source next106 exposes final gate and non overlap text'] = static function (TestRunner $t): void {
    $record = libsqlite_suite_next106_record(libsqlite_suite_next106_rows());

    $t->contains('isolated current-source next104-106 final suite-evidence row', $record['next_gate']);
    $t->contains('next101-103 readiness', $record['dependency_closure']);
    $t->contains('avoids next102 admission', $record['non_overlap_note']);
    $t->contains('stale accepted suite baselines', $record['non_overlap_note']);
};

$tests['current source next106 rejects empty artifacts through prerequisite validator'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn () => libsqlite_suite_next106_record([]));
};

return $tests;

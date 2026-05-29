<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteUpstreamSuiteEvidence;

function libsqlite_suite_next109_evidence(): SQLiteUpstreamSuiteEvidence
{
    return SQLiteUpstreamSuiteEvidence::fromManifestPath(__DIR__ . '/../UPSTREAM_TEST_MANIFEST.json');
}

function libsqlite_suite_next109_output(int $passLines = 71, int $assertions = 84, int $failures = 0): string
{
    $lines = ['Focused test run: 1 selected test files (root lock skipped)'];
    for ($i = 1; $i <= $passLines; $i++) {
        $lines[] = sprintf('PASS current source next109 upstream runner final evidence case %02d', $i);
    }
    $lines[] = sprintf('1 test files, %d assertions, %d failures', $assertions, $failures);

    return implode("\n", $lines);
}

/**
 * @return list<array<string, mixed>>
 */
function libsqlite_suite_next109_rows(
    int $case = 1,
    string $launcherBase = '432eeef3a780a882f63963e1ddad168744b946dd',
    string $dashboardSource = '271b286480bbfdef0408d3e5e495087bd433ae40',
    string $statusSource = '271b286480bbfdef0408d3e5e495087bd433ae40',
    string $implementationSource = 'b3c4ecbf768d15d978a740cbb75a8109bca7e0f1',
    string $nextHead = 'upstream-runner-final-current-source-next109'
): array {
    $script = sprintf('upstream-runner-final-current-source-next109-%02d.test', $case);

    return [
        [
            'unit' => 'upstream-runner-final-current-source-next109',
            'kind' => 'bounded-upstream-runner-final-evidence',
            'gap_id' => 'current-source-next109-final-evidence-gap',
            'gap_status' => 'removed',
            'removed_blocker' => 'next109 finalizes the rebased next108 suite evidence without carrying a duplicate stale baseline forward',
            'rebase_status' => 'rebased',
            'rebase_reason' => 'final evidence is tied to launcher base 432eeef3 plus dashboard/status 271b2864 and implementation b3c4ecbf',
            'final_evidence_id' => 'current-source-next109-final-suite-evidence',
            'final_evidence_status' => 'finalized',
            'stale_baseline_id' => 'pre-batch104-suite-runner-baseline',
            'source_head' => $nextHead,
            'launcher_base_head' => $launcherBase,
            'dashboard_source_head' => $dashboardSource,
            'status_source_head' => $statusSource,
            'implementation_source_head' => $implementationSource,
            'artifact_path' => 'lanes/libsqlite/notes/upstream-runner-final-current-source-next109.md',
            'runner_command' => './testfixture ../libsqlite/test/testrunner.tcl --jobs 1 --stop-on-error veryquick ' . $script,
            'scripts' => [$script, 'testrunner.test'],
            'current_countable' => false,
            'next_countable' => true,
            'exit' => 0,
            'errors' => 0,
            'current_tests' => 0,
            'next_tests' => 6400 + $case,
            'evidence' => 'current-source next109 admits one finalized suite evidence row only when next108 rebase gates, unique stale-baseline identity, lane-local guarded zero-error artifacts, focused PASS lines, and duplicate-runner gates are all clear',
        ],
        [
            'unit' => 'upstream-runner-final-current-source-next109-preserved-anchor',
            'kind' => 'bounded-upstream-runner-final-anchor',
            'gap_id' => 'current-source-next109-accepted-next108-anchor',
            'gap_status' => 'preserved',
            'removed_blocker' => '',
            'rebase_status' => 'preserved',
            'rebase_reason' => 'accepted next108 suite evidence rebase remains countable and is not remapped by next109',
            'final_evidence_id' => 'current-source-next109-preserved-next108-anchor',
            'final_evidence_status' => 'preserved',
            'stale_baseline_id' => 'accepted-next108-suite-evidence-anchor',
            'source_head' => $nextHead,
            'launcher_base_head' => $launcherBase,
            'dashboard_source_head' => $dashboardSource,
            'status_source_head' => $statusSource,
            'implementation_source_head' => $implementationSource,
            'artifact_path' => 'lanes/libsqlite/notes/upstream-runner-suite-evidence-rebase-current-source-next108.md',
            'runner_command' => './testfixture ../libsqlite/test/testrunner.tcl --jobs 1 --stop-on-error veryquick accepted-current-source-anchor.test',
            'scripts' => ['accepted-current-source-anchor.test'],
            'current_countable' => true,
            'next_countable' => true,
            'exit' => 0,
            'errors' => 0,
            'current_tests' => 41942,
            'next_tests' => 41942,
            'evidence' => 'accepted next108 current-source suite evidence remains preserved while next109 finalizes only the new current-source evidence row',
        ],
    ];
}

/**
 * @param list<array<string, mixed>> $rows
 * @return array<string, mixed>
 */
function libsqlite_suite_next109_record(
    array $rows,
    string $launcherBase = '432eeef3a780a882f63963e1ddad168744b946dd',
    string $dashboardSource = '271b286480bbfdef0408d3e5e495087bd433ae40',
    string $statusSource = '271b286480bbfdef0408d3e5e495087bd433ae40',
    string $implementationSource = 'b3c4ecbf768d15d978a740cbb75a8109bca7e0f1',
    string $nextHead = 'upstream-runner-final-current-source-next109',
    ?string $output = null,
    ?int $expected = 71,
    string $snapshot = ''
): array {
    return libsqlite_suite_next109_evidence()->upstreamRunnerFinalEvidence(
        $rows,
        605,
        41942,
        $launcherBase,
        $dashboardSource,
        $statusSource,
        $implementationSource,
        $nextHead,
        'lanes/libsqlite/tests/SQLiteUpstreamRunnerFinalCurrentSourceNext109Test.php',
        $output ?? libsqlite_suite_next109_output(),
        'current-source next109 upstream-runner final evidence avoids next107 current-source repro count, next108 suite evidence rebase, next104 gap burnup, accepted batch104/105 behavior clusters, and queued next106 blockers without duplicating stale baselines',
        $expected,
        $snapshot
    );
}

$tests = [];

foreach (range(1, 60) as $case) {
    $tests[sprintf('current source next109 admits finalized suite evidence case %02d', $case)] = static function (TestRunner $t) use ($case): void {
        $record = libsqlite_suite_next109_record(libsqlite_suite_next109_rows($case));

        $t->same('current-source-next109-upstream-runner-final-countable', $record['status']);
        $t->same(true, $record['countable']);
        $t->same(605, $record['current_mapped']);
        $t->same(606, $record['next_mapped']);
        $t->same(1, $record['mapped_delta']);
        $t->same(71, $record['php_pass_delta']);
        $t->same(42013, $record['next_php_pass']);
        $t->same(['current-source-next109-final-suite-evidence'], $record['finalized_evidence_ids']);
        $t->same(['current-source-next109-preserved-next108-anchor'], $record['preserved_final_evidence_ids']);
        $t->same([], $record['duplicate_stale_baseline_ids']);
        $t->contains(sprintf('upstream-runner-final-current-source-next109-%02d.test', $case), implode(',', $record['target_scripts']));
        $t->same(true, $record['counts_upstream_runner_final_current_source_next109']);
        $t->same(false, $record['counts_upstream_runner_suite_evidence_rebase_current_source_next108']);
        $t->same(false, $record['counts_release_parity']);
    };
}

$tests['current source next109 records final evidence row metadata'] = static function (TestRunner $t): void {
    $record = libsqlite_suite_next109_record(libsqlite_suite_next109_rows(9));

    $t->same(6409, $record['tests_total_delta']);
    $t->same('finalized', $record['final_evidence_rows'][0]['final_evidence_status']);
    $t->same('pre-batch104-suite-runner-baseline', $record['final_evidence_rows'][0]['stale_baseline_id']);
    $t->contains('without carrying a duplicate stale baseline', $record['final_evidence_rows'][0]['removed_blocker']);
};

$tests['current source next109 preserves already counted final evidence without mapped inflation'] = static function (TestRunner $t): void {
    $rows = libsqlite_suite_next109_rows();
    $rows[0]['current_countable'] = true;
    $rows[0]['current_tests'] = 6401;

    $record = libsqlite_suite_next109_record($rows);

    $t->same('current-source-next109-upstream-runner-final-preserved', $record['status']);
    $t->same(0, $record['mapped_delta']);
    $t->same(605, $record['next_mapped']);
    $t->same(['current-source-next109-final-suite-evidence', 'current-source-next109-preserved-next108-anchor'], $record['preserved_final_evidence_ids']);
};

$tests['current source next109 blocks duplicate stale baselines'] = static function (TestRunner $t): void {
    $rows = libsqlite_suite_next109_rows();
    $rows[1]['stale_baseline_id'] = 'pre-batch104-suite-runner-baseline';

    $record = libsqlite_suite_next109_record($rows);

    $t->same('blocked', $record['status']);
    $t->same(['pre-batch104-suite-runner-baseline'], $record['duplicate_stale_baseline_ids']);
    $t->contains('duplicate-stale-baseline-id', implode('; ', array_column($record['blockers'], 'evidence')));
};

$tests['current source next109 blocks missing finalization status'] = static function (TestRunner $t): void {
    $rows = libsqlite_suite_next109_rows();
    $rows[0]['final_evidence_status'] = 'open';
    $rows[0]['stale_baseline_id'] = '';

    $record = libsqlite_suite_next109_record($rows);

    $t->same('blocked', $record['status']);
    $evidence = implode('; ', array_column($record['blockers'], 'evidence'));
    $t->contains('final-evidence-status-not-finalized', $evidence);
    $t->contains('stale-baseline-id-missing', $evidence);
};

$tests['current source next109 blocks stale provenance and duplicate runner'] = static function (TestRunner $t): void {
    $record = libsqlite_suite_next109_record(
        libsqlite_suite_next109_rows(3, launcherBase: '0000000000000000000000000000000000000000'),
        snapshot: "444 testfixture ../libsqlite/test/testrunner.tcl --stop-on-error all\n"
    );

    $t->same('blocked', $record['status']);
    $t->contains('launcher-base-head-mismatch', implode('; ', array_column($record['blockers'], 'evidence')));
    $t->contains('duplicate-broad-runner-active', implode(',', array_column($record['blockers'], 'id')));
};

$tests['current source next109 blocks focused pass mismatch'] = static function (TestRunner $t): void {
    $record = libsqlite_suite_next109_record(libsqlite_suite_next109_rows(), output: libsqlite_suite_next109_output(passLines: 70), expected: 71);

    $t->same('blocked', $record['status']);
    $t->same(0, $record['php_pass_delta']);
    $t->contains('focused-current-head-php-pass-blocked', implode(',', array_column($record['blockers'], 'id')));
};

return $tests;

<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteUpstreamSuiteEvidence;

function libsqlite_suite_next110112_evidence(): SQLiteUpstreamSuiteEvidence
{
    return SQLiteUpstreamSuiteEvidence::fromManifestPath(__DIR__ . '/../UPSTREAM_TEST_MANIFEST.json');
}

function libsqlite_suite_next110112_output(int $passLines = 43, int $assertions = 96, int $failures = 0): string
{
    $lines = ['Focused test run: 1 selected test files (root lock skipped)'];
    for ($i = 1; $i <= $passLines; $i++) {
        $lines[] = sprintf('PASS current source next110-112 upstream suite evidence case %02d', $i);
    }
    $lines[] = sprintf('1 test files, %d assertions, %d failures', $assertions, $failures);

    return implode("\n", $lines);
}

/**
 * @return list<array<string, mixed>>
 */
function libsqlite_suite_next110112_rows(
    int $case = 1,
    string $launcherBase = '432eeef3a780a882f63963e1ddad168744b946dd',
    string $dashboardSource = '271b286480bbfdef0408d3e5e495087bd433ae40',
    string $statusSource = '271b286480bbfdef0408d3e5e495087bd433ae40',
    string $implementationSource = 'b3c4ecbf768d15d978a740cbb75a8109bca7e0f1',
    string $nextHead = 'upstream-suite-evidence-current-source-next110-112'
): array {
    $rows = [];
    foreach (['next110', 'next111', 'next112'] as $index => $phase) {
        $script = sprintf('upstream-suite-evidence-current-source-%s-%02d.test', $phase, $case);
        $rows[] = [
            'unit' => 'upstream-suite-evidence-current-source-' . $phase,
            'kind' => 'bounded-upstream-suite-evidence-triplet',
            'gap_id' => 'current-source-' . $phase . '-suite-evidence-gap',
            'gap_status' => 'removed',
            'removed_blocker' => $phase . ' prepares isolated current-source upstream suite evidence after next109 final preflight',
            'rebase_status' => 'rebased',
            'rebase_reason' => 'triplet evidence is tied to launcher base 432eeef3 plus dashboard/status 271b2864 and implementation b3c4ecbf',
            'final_evidence_id' => 'current-source-' . $phase . '-suite-evidence',
            'final_evidence_status' => 'finalized',
            'stale_baseline_id' => 'pre-batch104-suite-runner-baseline-' . $phase,
            'suite_phase' => $phase,
            'suite_phase_id' => 'current-source-' . $phase . '-prepared',
            'suite_phase_status' => 'prepared',
            'suite_phase_evidence' => $phase . ' has lane-local current-source upstream suite evidence and no release/all parity claim',
            'current_source_only' => true,
            'source_head' => $nextHead,
            'launcher_base_head' => $launcherBase,
            'dashboard_source_head' => $dashboardSource,
            'status_source_head' => $statusSource,
            'implementation_source_head' => $implementationSource,
            'artifact_path' => 'lanes/libsqlite/notes/upstream-suite-evidence-current-source-next110-112.md',
            'runner_command' => './testfixture ../libsqlite/test/testrunner.tcl --jobs 1 --stop-on-error veryquick ' . $script,
            'scripts' => [$script, 'testrunner.test'],
            'current_countable' => false,
            'next_countable' => true,
            'exit' => 0,
            'errors' => 0,
            'current_tests' => 0,
            'next_tests' => 6500 + $case + $index,
            'evidence' => $phase . ' admits one current-source suite evidence row only when next109 final gates, unique stale baselines, lane-local artifacts, zero runner errors, and focused PASS lines are clear',
        ];
    }

    $rows[] = [
        'unit' => 'upstream-suite-evidence-current-source-next109-anchor',
        'kind' => 'bounded-upstream-suite-evidence-anchor',
        'gap_id' => 'current-source-next109-final-anchor',
        'gap_status' => 'preserved',
        'removed_blocker' => '',
        'rebase_status' => 'preserved',
        'rebase_reason' => 'accepted next109 final evidence remains preserved and is not remapped by next110-112',
        'final_evidence_id' => 'current-source-next109-final-anchor',
        'final_evidence_status' => 'preserved',
        'stale_baseline_id' => 'accepted-next109-final-anchor',
        'source_head' => $nextHead,
        'launcher_base_head' => $launcherBase,
        'dashboard_source_head' => $dashboardSource,
        'status_source_head' => $statusSource,
        'implementation_source_head' => $implementationSource,
        'artifact_path' => 'lanes/libsqlite/notes/upstream-runner-final-current-source-next109.md',
        'runner_command' => './testfixture ../libsqlite/test/testrunner.tcl --jobs 1 --stop-on-error veryquick accepted-current-source-anchor.test',
        'scripts' => ['accepted-current-source-anchor.test'],
        'current_countable' => true,
        'next_countable' => true,
        'exit' => 0,
        'errors' => 0,
        'current_tests' => 42013,
        'next_tests' => 42013,
        'evidence' => 'accepted next109 final suite evidence remains preserved while next110-112 prepares only new current-source rows',
    ];

    return $rows;
}

/**
 * @param list<array<string, mixed>> $rows
 * @return array<string, mixed>
 */
function libsqlite_suite_next110112_record(
    array $rows,
    string $launcherBase = '432eeef3a780a882f63963e1ddad168744b946dd',
    string $dashboardSource = '271b286480bbfdef0408d3e5e495087bd433ae40',
    string $statusSource = '271b286480bbfdef0408d3e5e495087bd433ae40',
    string $implementationSource = 'b3c4ecbf768d15d978a740cbb75a8109bca7e0f1',
    string $nextHead = 'upstream-suite-evidence-current-source-next110-112',
    ?string $output = null,
    ?int $expected = 43,
    string $snapshot = ''
): array {
    return libsqlite_suite_next110112_evidence()->upstreamRunnerSuiteEvidenceInitialPreparedTriplet(
        $rows,
        606,
        42013,
        $launcherBase,
        $dashboardSource,
        $statusSource,
        $implementationSource,
        $nextHead,
        'lanes/libsqlite/tests/SQLiteUpstreamSuiteEvidenceCurrentSourceNext110112Test.php',
        $output ?? libsqlite_suite_next110112_output(),
        'current-source next110-112 upstream-suite evidence avoids next109 final evidence, next108 rebase, next104 gap burnup, accepted batch104/105 behavior clusters, queued next106 blockers, and release/all parity',
        $expected,
        $snapshot
    );
}

$tests = [];

foreach (range(1, 32) as $case) {
    $tests[sprintf('current source next110-112 prepares suite evidence triplet case %02d', $case)] = static function (TestRunner $t) use ($case): void {
        $record = libsqlite_suite_next110112_record(libsqlite_suite_next110112_rows($case));

        $t->same('current-source-next110-112-upstream-suite-evidence-prepared', $record['status']);
        $t->same(true, $record['countable']);
        $t->same(606, $record['current_mapped']);
        $t->same(609, $record['next_mapped']);
        $t->same(3, $record['mapped_delta']);
        $t->same(43, $record['php_pass_delta']);
        $t->same(42056, $record['next_php_pass']);
        $t->same(['next110', 'next111', 'next112'], $record['prepared_suite_phases']);
        $t->same([], $record['missing_suite_phases']);
        $t->same(true, $record['counts_upstream_suite_evidence_current_source_next110_112']);
        $t->same(false, $record['counts_upstream_runner_final_current_source_next109']);
        $t->same(false, $record['counts_release_parity']);
        $t->contains(sprintf('upstream-suite-evidence-current-source-next112-%02d.test', $case), implode(',', $record['target_scripts']));
    };
}

$tests['current source next110-112 records phase row metadata'] = static function (TestRunner $t): void {
    $record = libsqlite_suite_next110112_record(libsqlite_suite_next110112_rows(9));

    $t->same(19530, $record['tests_total_delta']);
    $t->same('next110', $record['suite_phase_rows'][0]['suite_phase']);
    $t->same('prepared', $record['suite_phase_rows'][1]['suite_phase_status']);
    $t->contains('no release/all parity claim', $record['suite_phase_rows'][2]['suite_phase_evidence']);
};

$tests['current source next110-112 blocks missing phase'] = static function (TestRunner $t): void {
    $rows = libsqlite_suite_next110112_rows();
    unset($rows[1]);
    $rows = array_values($rows);

    $record = libsqlite_suite_next110112_record($rows);

    $t->same('blocked', $record['status']);
    $t->same(['next111'], $record['missing_suite_phases']);
    $t->contains('missing prepared suite phases: next111', implode('; ', array_column($record['blockers'], 'evidence')));
};

$tests['current source next110-112 blocks duplicate phase'] = static function (TestRunner $t): void {
    $rows = libsqlite_suite_next110112_rows();
    $rows[1]['suite_phase'] = 'next110';

    $record = libsqlite_suite_next110112_record($rows);

    $t->same('blocked', $record['status']);
    $t->same(['next110'], $record['duplicate_suite_phases']);
    $t->contains('duplicate-suite-phase', implode('; ', array_column($record['blockers'], 'evidence')));
};

$tests['current source next110-112 blocks bad phase status and missing current source flag'] = static function (TestRunner $t): void {
    $rows = libsqlite_suite_next110112_rows();
    $rows[0]['suite_phase_status'] = 'open';
    $rows[0]['current_source_only'] = false;
    $rows[0]['suite_phase_evidence'] = '';

    $record = libsqlite_suite_next110112_record($rows);
    $evidence = implode('; ', array_column($record['blockers'], 'evidence'));

    $t->same('blocked', $record['status']);
    $t->contains('suite-phase-status-not-prepared', $evidence);
    $t->contains('current-source-only-flag-missing', $evidence);
    $t->contains('suite-phase-evidence-missing', $evidence);
};

$tests['current source next110-112 blocks release parity and non note artifact'] = static function (TestRunner $t): void {
    $rows = libsqlite_suite_next110112_rows();
    $rows[2]['counts_release_parity'] = true;
    $rows[2]['artifact_path'] = 'lanes/libsqlite/tests/tmp.log';

    $record = libsqlite_suite_next110112_record($rows);
    $evidence = implode('; ', array_column($record['blockers'], 'evidence'));

    $t->same('blocked', $record['status']);
    $t->contains('release-parity-claim-not-allowed', $evidence);
    $t->contains('suite-phase-artifact-not-lane-note', $evidence);
};

$tests['current source next110-112 blocks stale provenance and active broad runner'] = static function (TestRunner $t): void {
    $record = libsqlite_suite_next110112_record(
        libsqlite_suite_next110112_rows(3, launcherBase: '0000000000000000000000000000000000000000'),
        snapshot: "777 testfixture ../libsqlite/test/testrunner.tcl --jobs 4 all\n"
    );

    $t->same('blocked', $record['status']);
    $t->contains('launcher-base-head-mismatch', implode('; ', array_column($record['blockers'], 'evidence')));
    $t->contains('duplicate-broad-runner-active', implode(',', array_column($record['blockers'], 'id')));
};

$tests['current source next110-112 blocks focused pass mismatch'] = static function (TestRunner $t): void {
    $record = libsqlite_suite_next110112_record(
        libsqlite_suite_next110112_rows(),
        output: libsqlite_suite_next110112_output(passLines: 42),
        expected: 43
    );

    $t->same('blocked', $record['status']);
    $t->same(0, $record['php_pass_delta']);
    $t->same(42013, $record['next_php_pass']);
    $t->contains('focused-current-head-php-pass-blocked', implode(',', array_column($record['blockers'], 'id')));
};

$tests['current source next110-112 preserves already counted phases without mapped inflation'] = static function (TestRunner $t): void {
    $rows = libsqlite_suite_next110112_rows();
    foreach ([0, 1, 2] as $index) {
        $rows[$index]['current_countable'] = true;
        $rows[$index]['current_tests'] = $rows[$index]['next_tests'];
    }

    $record = libsqlite_suite_next110112_record($rows);

    $t->same('current-source-next110-112-upstream-suite-evidence-preserved', $record['status']);
    $t->same(0, $record['mapped_delta']);
    $t->same(606, $record['next_mapped']);
};

return $tests;

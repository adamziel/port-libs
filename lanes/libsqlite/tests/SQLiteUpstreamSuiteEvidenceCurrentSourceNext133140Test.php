<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteUpstreamSuiteEvidence;

function libsqlite_suite_next133140_evidence(): SQLiteUpstreamSuiteEvidence
{
    return SQLiteUpstreamSuiteEvidence::fromManifestPath(__DIR__ . '/../UPSTREAM_TEST_MANIFEST.json');
}

function libsqlite_suite_next133140_output(int $passLines = 44, int $assertions = 96, int $failures = 0): string
{
    $lines = ['Focused test run: 1 selected test files (root lock skipped)'];
    for ($i = 1; $i <= $passLines; $i++) {
        $lines[] = sprintf('PASS current source next133-140 upstream suite evidence case %02d', $i);
    }
    $lines[] = sprintf('1 test files, %d assertions, %d failures', $assertions, $failures);

    return implode("\n", $lines);
}

/**
 * @return list<array<string, mixed>>
 */
function libsqlite_suite_next133140_rows(
    int $case = 1,
    string $launcherBase = '841f9e58fdcd137ff784d157173e52f4d5beeaed',
    string $dashboardSource = '841f9e58fdcd137ff784d157173e52f4d5beeaed',
    string $statusSource = '841f9e58fdcd137ff784d157173e52f4d5beeaed',
    string $implementationSource = '841f9e58fdcd137ff784d157173e52f4d5beeaed',
    string $nextHead = 'upstream-suite-evidence-current-source-next133-140'
): array {
    $rows = [];
    foreach (['next133', 'next134', 'next135', 'next136', 'next137', 'next138', 'next139', 'next140'] as $index => $phase) {
        $script = sprintf('upstream-suite-evidence-current-source-%s-%02d.test', $phase, $case);
        $rows[] = [
            'unit' => 'upstream-suite-evidence-current-source-' . $phase,
            'kind' => 'bounded-upstream-suite-evidence-octet',
            'gap_id' => 'current-source-' . $phase . '-suite-evidence-gap',
            'gap_status' => 'removed',
            'removed_blocker' => $phase . ' prepares isolated current-source upstream suite evidence after merged next125-132 evidence',
            'rebase_status' => 'rebased',
            'rebase_reason' => 'octet evidence is tied to base 841f9e58 and the merged next125-132 evidence handoff',
            'final_evidence_id' => 'current-source-' . $phase . '-suite-evidence',
            'final_evidence_status' => 'finalized',
            'stale_baseline_id' => 'merged-next125-132-suite-evidence-baseline-' . $phase,
            'suite_phase' => $phase,
            'suite_phase_id' => 'current-source-' . $phase . '-prepared',
            'suite_phase_status' => 'prepared',
            'suite_phase_evidence' => $phase . ' has lane-local current-source upstream suite evidence after next125-132 and no release/all parity claim',
            'current_source_only' => true,
            'source_head' => $nextHead,
            'launcher_base_head' => $launcherBase,
            'dashboard_source_head' => $dashboardSource,
            'status_source_head' => $statusSource,
            'implementation_source_head' => $implementationSource,
            'artifact_path' => 'lanes/libsqlite/notes/upstream-suite-evidence-current-source-next133-140.md',
            'runner_command' => './testfixture ../libsqlite/test/testrunner.tcl --jobs 1 --stop-on-error veryquick ' . $script,
            'scripts' => [$script, 'testrunner.test'],
            'current_countable' => false,
            'next_countable' => true,
            'exit' => 0,
            'errors' => 0,
            'current_tests' => 0,
            'next_tests' => 6810 + $case + $index,
            'evidence' => $phase . ' admits one current-source suite evidence row only when merged next125-132 evidence gates, unique stale baselines, lane-local artifacts, zero runner errors, and focused PASS lines are clear',
        ];
    }

    $rows[] = [
        'unit' => 'upstream-suite-evidence-current-source-next125-132-anchor',
        'kind' => 'bounded-upstream-suite-evidence-anchor',
        'gap_id' => 'current-source-next125-132-final-anchor',
        'gap_status' => 'preserved',
        'removed_blocker' => '',
        'rebase_status' => 'preserved',
        'rebase_reason' => 'merged next125-132 prepared suite evidence remains preserved and is not remapped by next133-140',
        'final_evidence_id' => 'current-source-next125-132-final-anchor',
        'final_evidence_status' => 'preserved',
        'stale_baseline_id' => 'accepted-next125-132-final-anchor',
        'source_head' => $nextHead,
        'launcher_base_head' => $launcherBase,
        'dashboard_source_head' => $dashboardSource,
        'status_source_head' => $statusSource,
        'implementation_source_head' => $implementationSource,
        'artifact_path' => 'lanes/libsqlite/notes/upstream-suite-evidence-current-source-next125-132.md',
        'runner_command' => './testfixture ../libsqlite/test/testrunner.tcl --jobs 1 --stop-on-error veryquick accepted-current-source-next125-132-anchor.test',
        'scripts' => ['accepted-current-source-next125-132-anchor.test'],
        'current_countable' => true,
        'next_countable' => true,
        'exit' => 0,
        'errors' => 0,
        'current_tests' => 42232,
        'next_tests' => 42232,
        'evidence' => 'merged next125-132 prepared suite evidence remains preserved while next133-140 prepares only new current-source rows',
    ];

    return $rows;
}

/**
 * @param list<array<string, mixed>> $rows
 * @return array<string, mixed>
 */
function libsqlite_suite_next133140_record(
    array $rows,
    string $launcherBase = '841f9e58fdcd137ff784d157173e52f4d5beeaed',
    string $dashboardSource = '841f9e58fdcd137ff784d157173e52f4d5beeaed',
    string $statusSource = '841f9e58fdcd137ff784d157173e52f4d5beeaed',
    string $implementationSource = '841f9e58fdcd137ff784d157173e52f4d5beeaed',
    string $nextHead = 'upstream-suite-evidence-current-source-next133-140',
    ?string $output = null,
    ?int $expected = 44,
    string $snapshot = ''
): array {
    return libsqlite_suite_next133140_evidence()->upstreamRunnerSuiteEvidenceCurrentSourceNext133140(
        $rows,
        629,
        42232,
        $launcherBase,
        $dashboardSource,
        $statusSource,
        $implementationSource,
        $nextHead,
        'lanes/libsqlite/tests/SQLiteUpstreamSuiteEvidenceCurrentSourceNext133140Test.php',
        $output ?? libsqlite_suite_next133140_output(),
        'current-source next133-140 upstream-suite evidence avoids merged next125-132 suite evidence, next127 full-suite countability, next114 release admission, accepted behavior clusters, queued blockers, and release/all parity',
        $expected,
        $snapshot
    );
}

$tests = [];

foreach (range(1, 32) as $case) {
    $tests[sprintf('current source next133-140 prepares suite evidence octet case %02d', $case)] = static function (TestRunner $t) use ($case): void {
        $record = libsqlite_suite_next133140_record(libsqlite_suite_next133140_rows($case));

        $t->same('current-source-next133-140-upstream-suite-evidence-prepared', $record['status']);
        $t->same(true, $record['countable']);
        $t->same(629, $record['current_mapped']);
        $t->same(637, $record['next_mapped']);
        $t->same(8, $record['mapped_delta']);
        $t->same(44, $record['php_pass_delta']);
        $t->same(42276, $record['next_php_pass']);
        $t->same(['next133', 'next134', 'next135', 'next136', 'next137', 'next138', 'next139', 'next140'], $record['prepared_suite_phases']);
        $t->same([], $record['missing_suite_phases']);
        $t->same(true, $record['counts_upstream_suite_evidence_current_source_next133_140']);
        $t->same(false, $record['counts_upstream_suite_evidence_current_source_next125_132']);
        $t->same(false, $record['counts_release_parity']);
        $t->contains(sprintf('upstream-suite-evidence-current-source-next140-%02d.test', $case), implode(',', $record['target_scripts']));
    };
}

$tests['current source next133-140 records phase row metadata'] = static function (TestRunner $t): void {
    $record = libsqlite_suite_next133140_record(libsqlite_suite_next133140_rows(9));

    $t->same(54580, $record['tests_total_delta']);
    $t->same('next133', $record['suite_phase_rows'][0]['suite_phase']);
    $t->same('prepared', $record['suite_phase_rows'][1]['suite_phase_status']);
    $t->contains('no release/all parity claim', $record['suite_phase_rows'][2]['suite_phase_evidence']);
};

$tests['current source next133-140 blocks missing phase'] = static function (TestRunner $t): void {
    $rows = libsqlite_suite_next133140_rows();
    unset($rows[1]);
    $rows = array_values($rows);

    $record = libsqlite_suite_next133140_record($rows);

    $t->same('blocked', $record['status']);
    $t->same(['next134'], $record['missing_suite_phases']);
    $t->contains('missing prepared suite phases: next134', implode('; ', array_column($record['blockers'], 'evidence')));
};

$tests['current source next133-140 blocks duplicate phase'] = static function (TestRunner $t): void {
    $rows = libsqlite_suite_next133140_rows();
    $rows[1]['suite_phase'] = 'next133';

    $record = libsqlite_suite_next133140_record($rows);

    $t->same('blocked', $record['status']);
    $t->same(['next133'], $record['duplicate_suite_phases']);
    $t->contains('duplicate-suite-phase', implode('; ', array_column($record['blockers'], 'evidence')));
};

$tests['current source next133-140 blocks bad phase status and missing current source flag'] = static function (TestRunner $t): void {
    $rows = libsqlite_suite_next133140_rows();
    $rows[0]['suite_phase_status'] = 'open';
    $rows[0]['current_source_only'] = false;
    $rows[0]['suite_phase_evidence'] = '';

    $record = libsqlite_suite_next133140_record($rows);
    $evidence = implode('; ', array_column($record['blockers'], 'evidence'));

    $t->same('blocked', $record['status']);
    $t->contains('suite-phase-status-not-prepared', $evidence);
    $t->contains('current-source-only-flag-missing', $evidence);
    $t->contains('suite-phase-evidence-missing', $evidence);
};

$tests['current source next133-140 blocks release parity and non note artifact'] = static function (TestRunner $t): void {
    $rows = libsqlite_suite_next133140_rows();
    $rows[2]['counts_release_parity'] = true;
    $rows[2]['artifact_path'] = 'lanes/libsqlite/tests/tmp.log';

    $record = libsqlite_suite_next133140_record($rows);
    $evidence = implode('; ', array_column($record['blockers'], 'evidence'));

    $t->same('blocked', $record['status']);
    $t->contains('release-parity-claim-not-allowed', $evidence);
    $t->contains('suite-phase-artifact-not-lane-note', $evidence);
};

$tests['current source next133-140 blocks stale provenance and active broad runner'] = static function (TestRunner $t): void {
    $record = libsqlite_suite_next133140_record(
        libsqlite_suite_next133140_rows(3, launcherBase: '0000000000000000000000000000000000000000'),
        snapshot: "777 testfixture ../libsqlite/test/testrunner.tcl --jobs 4 all\n"
    );

    $t->same('blocked', $record['status']);
    $t->contains('launcher-base-head-mismatch', implode('; ', array_column($record['blockers'], 'evidence')));
    $t->contains('duplicate-broad-runner-active', implode(',', array_column($record['blockers'], 'id')));
};

$tests['current source next133-140 blocks focused pass mismatch'] = static function (TestRunner $t): void {
    $record = libsqlite_suite_next133140_record(
        libsqlite_suite_next133140_rows(),
        output: libsqlite_suite_next133140_output(passLines: 42),
        expected: 44
    );

    $t->same('blocked', $record['status']);
    $t->same(0, $record['php_pass_delta']);
    $t->same(42232, $record['next_php_pass']);
    $t->contains('focused-current-head-php-pass-blocked', implode(',', array_column($record['blockers'], 'id')));
};

$tests['current source next133-140 preserves already counted phases without mapped inflation'] = static function (TestRunner $t): void {
    $rows = libsqlite_suite_next133140_rows();
    foreach (range(0, 7) as $index) {
        $rows[$index]['current_countable'] = true;
        $rows[$index]['current_tests'] = $rows[$index]['next_tests'];
    }

    $record = libsqlite_suite_next133140_record($rows);

    $t->same('current-source-next133-140-upstream-suite-evidence-preserved', $record['status']);
    $t->same(0, $record['mapped_delta']);
    $t->same(629, $record['next_mapped']);
};

return $tests;

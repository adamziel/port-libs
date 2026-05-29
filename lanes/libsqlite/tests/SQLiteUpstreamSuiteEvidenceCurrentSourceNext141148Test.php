<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteUpstreamSuiteEvidence;

function libsqlite_suite_next141148_evidence(): SQLiteUpstreamSuiteEvidence
{
    return SQLiteUpstreamSuiteEvidence::fromManifestPath(__DIR__ . '/../UPSTREAM_TEST_MANIFEST.json');
}

function libsqlite_suite_next141148_output(int $passLines = 48, int $assertions = 104, int $failures = 0): string
{
    $lines = ['Focused test run: 1 selected test files (root lock skipped)'];
    for ($i = 1; $i <= $passLines; $i++) {
        $lines[] = sprintf('PASS current source next141-148 upstream suite evidence case %02d', $i);
    }
    $lines[] = sprintf('1 test files, %d assertions, %d failures', $assertions, $failures);

    return implode("\n", $lines);
}

/**
 * @return list<array<string, mixed>>
 */
function libsqlite_suite_next141148_rows(
    int $case = 1,
    string $launcherBase = '3fae248214826ad86e1f1372ce014a0a766b8138',
    string $dashboardSource = '3fae248214826ad86e1f1372ce014a0a766b8138',
    string $statusSource = '3fae248214826ad86e1f1372ce014a0a766b8138',
    string $implementationSource = '3fae248214826ad86e1f1372ce014a0a766b8138',
    string $nextHead = 'upstream-suite-evidence-current-source-next141-148'
): array {
    $rows = [];
    foreach (['next141', 'next142', 'next143', 'next144', 'next145', 'next146', 'next147', 'next148'] as $index => $phase) {
        $script = sprintf('upstream-suite-evidence-current-source-%s-%02d.test', $phase, $case);
        $rows[] = [
            'unit' => 'upstream-suite-evidence-current-source-' . $phase,
            'kind' => 'bounded-upstream-suite-evidence-octet',
            'gap_id' => 'current-source-' . $phase . '-suite-evidence-gap',
            'gap_status' => 'removed',
            'removed_blocker' => $phase . ' prepares isolated current-source upstream suite evidence after merged next133-140 evidence',
            'rebase_status' => 'rebased',
            'rebase_reason' => 'octet evidence is tied to base 3fae24821 and the merged next133-140 evidence handoff',
            'final_evidence_id' => 'current-source-' . $phase . '-suite-evidence',
            'final_evidence_status' => 'finalized',
            'stale_baseline_id' => 'merged-next133-140-suite-evidence-baseline-' . $phase,
            'suite_phase' => $phase,
            'suite_phase_id' => 'current-source-' . $phase . '-prepared',
            'suite_phase_status' => 'prepared',
            'suite_phase_evidence' => $phase . ' has lane-local current-source upstream suite evidence after next133-140 and no release/all parity claim',
            'current_source_only' => true,
            'source_head' => $nextHead,
            'launcher_base_head' => $launcherBase,
            'dashboard_source_head' => $dashboardSource,
            'status_source_head' => $statusSource,
            'implementation_source_head' => $implementationSource,
            'artifact_path' => 'lanes/libsqlite/notes/upstream-suite-evidence-current-source-next141-148.md',
            'runner_command' => './testfixture ../libsqlite/test/testrunner.tcl --jobs 1 --stop-on-error veryquick ' . $script,
            'scripts' => [$script, 'testrunner.test'],
            'current_countable' => false,
            'next_countable' => true,
            'exit' => 0,
            'errors' => 0,
            'current_tests' => 0,
            'next_tests' => 6900 + $case + $index,
            'evidence' => $phase . ' admits one current-source suite evidence row only when merged next133-140 evidence gates, unique stale baselines, lane-local artifacts, zero runner errors, and focused PASS lines are clear',
        ];
    }

    $rows[] = [
        'unit' => 'upstream-suite-evidence-current-source-next133-140-anchor',
        'kind' => 'bounded-upstream-suite-evidence-anchor',
        'gap_id' => 'current-source-next133-140-final-anchor',
        'gap_status' => 'preserved',
        'removed_blocker' => '',
        'rebase_status' => 'preserved',
        'rebase_reason' => 'merged next133-140 prepared suite evidence remains preserved and is not remapped by next141-148',
        'final_evidence_id' => 'current-source-next133-140-final-anchor',
        'final_evidence_status' => 'preserved',
        'stale_baseline_id' => 'accepted-next133-140-final-anchor',
        'source_head' => $nextHead,
        'launcher_base_head' => $launcherBase,
        'dashboard_source_head' => $dashboardSource,
        'status_source_head' => $statusSource,
        'implementation_source_head' => $implementationSource,
        'artifact_path' => 'lanes/libsqlite/notes/upstream-suite-evidence-current-source-next133-140.md',
        'runner_command' => './testfixture ../libsqlite/test/testrunner.tcl --jobs 1 --stop-on-error veryquick accepted-current-source-next133-140-anchor.test',
        'scripts' => ['accepted-current-source-next133-140-anchor.test'],
        'current_countable' => true,
        'next_countable' => true,
        'exit' => 0,
        'errors' => 0,
        'current_tests' => 42276,
        'next_tests' => 42276,
        'evidence' => 'merged next133-140 prepared suite evidence remains preserved while next141-148 prepares only new current-source rows',
    ];

    return $rows;
}

/**
 * @param list<array<string, mixed>> $rows
 * @return array<string, mixed>
 */
function libsqlite_suite_next141148_record(
    array $rows,
    string $launcherBase = '3fae248214826ad86e1f1372ce014a0a766b8138',
    string $dashboardSource = '3fae248214826ad86e1f1372ce014a0a766b8138',
    string $statusSource = '3fae248214826ad86e1f1372ce014a0a766b8138',
    string $implementationSource = '3fae248214826ad86e1f1372ce014a0a766b8138',
    string $nextHead = 'upstream-suite-evidence-current-source-next141-148',
    ?string $output = null,
    ?int $expected = 48,
    string $snapshot = ''
): array {
    return libsqlite_suite_next141148_evidence()->upstreamRunnerSuiteEvidenceExtendedPreparedOctet(
        $rows,
        637,
        42276,
        $launcherBase,
        $dashboardSource,
        $statusSource,
        $implementationSource,
        $nextHead,
        'lanes/libsqlite/tests/SQLiteUpstreamSuiteEvidenceCurrentSourceNext141148Test.php',
        $output ?? libsqlite_suite_next141148_output(),
        'current-source next141-148 upstream-suite evidence avoids merged next133-140 suite evidence, next127 full-suite countability, next114 release admission, accepted behavior clusters, queued blockers, and release/all parity',
        $expected,
        $snapshot
    );
}

$tests = [];

$tests['current source next141-148 prepares suite evidence octet'] = static function (TestRunner $t): void {
    $record = libsqlite_suite_next141148_record(libsqlite_suite_next141148_rows());

    $t->same('current-source-next141-148-upstream-suite-evidence-prepared', $record['status']);
    $t->same(true, $record['countable']);
    $t->same(637, $record['current_mapped']);
    $t->same(645, $record['next_mapped']);
    $t->same(8, $record['mapped_delta']);
    $t->same(48, $record['php_pass_delta']);
    $t->same(42324, $record['next_php_pass']);
    $t->same(['next141', 'next142', 'next143', 'next144', 'next145', 'next146', 'next147', 'next148'], $record['prepared_suite_phases']);
    $t->same([], $record['missing_suite_phases']);
    $t->same(true, $record['counts_upstream_suite_evidence_current_source_next141_148']);
    $t->same(false, $record['counts_upstream_suite_evidence_current_source_next133_140']);
    $t->same(false, $record['counts_release_parity']);
    $t->contains('upstream-suite-evidence-current-source-next148-01.test', implode(',', $record['target_scripts']));
};

$tests['current source next141-148 records phase row metadata'] = static function (TestRunner $t): void {
    $record = libsqlite_suite_next141148_record(libsqlite_suite_next141148_rows(9));

    $t->same(55300, $record['tests_total_delta']);
    $t->same('next141', $record['suite_phase_rows'][0]['suite_phase']);
    $t->same('prepared', $record['suite_phase_rows'][1]['suite_phase_status']);
    $t->contains('no release/all parity claim', $record['suite_phase_rows'][2]['suite_phase_evidence']);
};

$tests['current source next141-148 blocks missing phase'] = static function (TestRunner $t): void {
    $rows = libsqlite_suite_next141148_rows();
    unset($rows[1]);
    $rows = array_values($rows);

    $record = libsqlite_suite_next141148_record($rows);

    $t->same('blocked', $record['status']);
    $t->same(['next142'], $record['missing_suite_phases']);
    $t->contains('missing prepared suite phases: next142', implode('; ', array_column($record['blockers'], 'evidence')));
};

$tests['current source next141-148 blocks duplicate phase'] = static function (TestRunner $t): void {
    $rows = libsqlite_suite_next141148_rows();
    $rows[1]['suite_phase'] = 'next141';

    $record = libsqlite_suite_next141148_record($rows);

    $t->same('blocked', $record['status']);
    $t->same(['next141'], $record['duplicate_suite_phases']);
    $t->contains('duplicate-suite-phase', implode('; ', array_column($record['blockers'], 'evidence')));
};

$tests['current source next141-148 blocks release parity and bad artifact'] = static function (TestRunner $t): void {
    $rows = libsqlite_suite_next141148_rows();
    $rows[2]['counts_release_parity'] = true;
    $rows[2]['artifact_path'] = 'lanes/libsqlite/tests/tmp.log';

    $record = libsqlite_suite_next141148_record($rows);
    $evidence = implode('; ', array_column($record['blockers'], 'evidence'));

    $t->same('blocked', $record['status']);
    $t->contains('release-parity-claim-not-allowed', $evidence);
    $t->contains('suite-phase-artifact-not-lane-note', $evidence);
};

$tests['current source next141-148 blocks stale provenance and active broad runner'] = static function (TestRunner $t): void {
    $record = libsqlite_suite_next141148_record(
        libsqlite_suite_next141148_rows(3, launcherBase: '0000000000000000000000000000000000000000'),
        snapshot: "777 testfixture ../libsqlite/test/testrunner.tcl --jobs 4 all\n"
    );

    $t->same('blocked', $record['status']);
    $t->contains('launcher-base-head-mismatch', implode('; ', array_column($record['blockers'], 'evidence')));
    $t->contains('duplicate-broad-runner-active', implode(',', array_column($record['blockers'], 'id')));
};

$tests['current source next141-148 blocks focused pass mismatch'] = static function (TestRunner $t): void {
    $record = libsqlite_suite_next141148_record(
        libsqlite_suite_next141148_rows(),
        output: libsqlite_suite_next141148_output(passLines: 46),
        expected: 48
    );

    $t->same('blocked', $record['status']);
    $t->same(0, $record['php_pass_delta']);
    $t->same(42276, $record['next_php_pass']);
    $t->contains('focused-current-head-php-pass-blocked', implode(',', array_column($record['blockers'], 'id')));
};

$tests['current source next141-148 preserves already counted phases without mapped inflation'] = static function (TestRunner $t): void {
    $rows = libsqlite_suite_next141148_rows();
    foreach (range(0, 7) as $index) {
        $rows[$index]['current_countable'] = true;
        $rows[$index]['current_tests'] = $rows[$index]['next_tests'];
    }

    $record = libsqlite_suite_next141148_record($rows);

    $t->same('current-source-next141-148-upstream-suite-evidence-preserved', $record['status']);
    $t->same(0, $record['mapped_delta']);
    $t->same(637, $record['next_mapped']);
};

return $tests;

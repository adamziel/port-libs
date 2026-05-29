<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteUpstreamSuiteEvidence;

function libsqlite_suite_next149156_evidence(): SQLiteUpstreamSuiteEvidence
{
    return SQLiteUpstreamSuiteEvidence::fromManifestPath(__DIR__ . '/../UPSTREAM_TEST_MANIFEST.json');
}

function libsqlite_suite_next149156_output(int $passLines = 52, int $assertions = 112, int $failures = 0): string
{
    $lines = ['Focused test run: 1 selected test files (root lock skipped)'];
    for ($i = 1; $i <= $passLines; $i++) {
        $lines[] = sprintf('PASS current source next149-156 upstream suite evidence case %02d', $i);
    }
    $lines[] = sprintf('1 test files, %d assertions, %d failures', $assertions, $failures);

    return implode("\n", $lines);
}

/**
 * @return list<array<string, mixed>>
 */
function libsqlite_suite_next149156_rows(
    int $case = 1,
    string $launcherBase = '5c0032d73374442d47595f155f5fec023a2cd74a',
    string $dashboardSource = '5c0032d73374442d47595f155f5fec023a2cd74a',
    string $statusSource = '5c0032d73374442d47595f155f5fec023a2cd74a',
    string $implementationSource = '5c0032d73374442d47595f155f5fec023a2cd74a',
    string $nextHead = 'upstream-suite-evidence-current-source-next149-156'
): array {
    $rows = [];
    foreach (['next149', 'next150', 'next151', 'next152', 'next153', 'next154', 'next155', 'next156'] as $index => $phase) {
        $script = sprintf('upstream-suite-evidence-current-source-%s-%02d.test', $phase, $case);
        $rows[] = [
            'unit' => 'upstream-suite-evidence-current-source-' . $phase,
            'kind' => 'bounded-upstream-suite-evidence-octet',
            'gap_id' => 'current-source-' . $phase . '-suite-evidence-gap',
            'gap_status' => 'removed',
            'removed_blocker' => $phase . ' prepares isolated current-source upstream suite evidence after merged next141-148 evidence',
            'rebase_status' => 'rebased',
            'rebase_reason' => 'octet evidence is tied to base 5c0032d73 and the merged next141-148 evidence handoff',
            'final_evidence_id' => 'current-source-' . $phase . '-suite-evidence',
            'final_evidence_status' => 'finalized',
            'stale_baseline_id' => 'merged-next141-148-suite-evidence-baseline-' . $phase,
            'suite_phase' => $phase,
            'suite_phase_id' => 'current-source-' . $phase . '-prepared',
            'suite_phase_status' => 'prepared',
            'suite_phase_evidence' => $phase . ' has lane-local current-source upstream suite evidence after next141-148 and no release/all parity claim',
            'current_source_only' => true,
            'source_head' => $nextHead,
            'launcher_base_head' => $launcherBase,
            'dashboard_source_head' => $dashboardSource,
            'status_source_head' => $statusSource,
            'implementation_source_head' => $implementationSource,
            'artifact_path' => 'lanes/libsqlite/notes/upstream-suite-evidence-current-source-next149-156.md',
            'runner_command' => './testfixture ../libsqlite/test/testrunner.tcl --jobs 1 --stop-on-error veryquick ' . $script,
            'scripts' => [$script, 'testrunner.test'],
            'current_countable' => false,
            'next_countable' => true,
            'exit' => 0,
            'errors' => 0,
            'current_tests' => 0,
            'next_tests' => 7100 + $case + $index,
            'evidence' => $phase . ' admits one current-source suite evidence row only when merged next141-148 evidence gates, unique stale baselines, lane-local artifacts, zero runner errors, and focused PASS lines are clear',
        ];
    }

    $rows[] = [
        'unit' => 'upstream-suite-evidence-current-source-next141-148-anchor',
        'kind' => 'bounded-upstream-suite-evidence-anchor',
        'gap_id' => 'current-source-next141-148-final-anchor',
        'gap_status' => 'preserved',
        'rebase_status' => 'preserved',
        'rebase_reason' => 'merged next141-148 prepared suite evidence remains preserved and is not remapped by next149-156',
        'final_evidence_id' => 'current-source-next141-148-final-anchor',
        'final_evidence_status' => 'preserved',
        'stale_baseline_id' => 'accepted-next141-148-final-anchor',
        'source_head' => $nextHead,
        'launcher_base_head' => $launcherBase,
        'dashboard_source_head' => $dashboardSource,
        'status_source_head' => $statusSource,
        'implementation_source_head' => $implementationSource,
        'artifact_path' => 'lanes/libsqlite/notes/upstream-suite-evidence-current-source-next141-148.md',
        'runner_command' => './testfixture ../libsqlite/test/testrunner.tcl --jobs 1 --stop-on-error veryquick accepted-current-source-next141-148-anchor.test',
        'scripts' => ['accepted-current-source-next141-148-anchor.test'],
        'current_countable' => true,
        'next_countable' => true,
        'exit' => 0,
        'errors' => 0,
        'current_tests' => 55300,
        'next_tests' => 55300,
        'evidence' => 'merged next141-148 prepared suite evidence remains preserved while next149-156 prepares only new current-source rows',
    ];

    return $rows;
}

/**
 * @param list<array<string, mixed>> $rows
 * @return array<string, mixed>
 */
function libsqlite_suite_next149156_record(
    array $rows,
    string $launcherBase = '5c0032d73374442d47595f155f5fec023a2cd74a',
    string $dashboardSource = '5c0032d73374442d47595f155f5fec023a2cd74a',
    string $statusSource = '5c0032d73374442d47595f155f5fec023a2cd74a',
    string $implementationSource = '5c0032d73374442d47595f155f5fec023a2cd74a',
    string $nextHead = 'upstream-suite-evidence-current-source-next149-156',
    ?string $output = null,
    ?int $expected = 52,
    string $snapshot = ''
): array {
    return libsqlite_suite_next149156_evidence()->upstreamRunnerSuiteEvidenceCurrentSourceNext149156(
        $rows,
        645,
        42324,
        $launcherBase,
        $dashboardSource,
        $statusSource,
        $implementationSource,
        $nextHead,
        'lanes/libsqlite/tests/SQLiteUpstreamSuiteEvidenceCurrentSourceNext149156Test.php',
        $output ?? libsqlite_suite_next149156_output(),
        'current-source next149-156 upstream-suite evidence avoids merged next141-148 suite evidence, prior suite-evidence rows, accepted behavior clusters, queued blockers, and release/all parity',
        $expected,
        $snapshot
    );
}

$tests = [];

$tests['current source next149-156 prepares suite evidence octet'] = static function (TestRunner $t): void {
    $record = libsqlite_suite_next149156_record(libsqlite_suite_next149156_rows());

    $t->same('current-source-next149-156-upstream-suite-evidence-prepared', $record['status']);
    $t->same(true, $record['countable']);
    $t->same(645, $record['current_mapped']);
    $t->same(653, $record['next_mapped']);
    $t->same(8, $record['mapped_delta']);
    $t->same(52, $record['php_pass_delta']);
    $t->same(42376, $record['next_php_pass']);
    $t->same(['next149', 'next150', 'next151', 'next152', 'next153', 'next154', 'next155', 'next156'], $record['prepared_suite_phases']);
    $t->same([], $record['missing_suite_phases']);
    $t->same(true, $record['counts_upstream_suite_evidence_current_source_next149_156']);
    $t->same(false, $record['counts_upstream_suite_evidence_current_source_next141_148']);
    $t->same(false, $record['counts_release_parity']);
    $t->contains('upstream-suite-evidence-current-source-next156-01.test', implode(',', $record['target_scripts']));
};

$tests['current source next149-156 records phase row metadata'] = static function (TestRunner $t): void {
    $record = libsqlite_suite_next149156_record(libsqlite_suite_next149156_rows(4));

    $t->same(56860, $record['tests_total_delta']);
    $t->same('next149', $record['suite_phase_rows'][0]['suite_phase']);
    $t->same('prepared', $record['suite_phase_rows'][1]['suite_phase_status']);
    $t->contains('no release/all parity claim', $record['suite_phase_rows'][2]['suite_phase_evidence']);
};

$tests['current source next149-156 blocks missing phase'] = static function (TestRunner $t): void {
    $rows = libsqlite_suite_next149156_rows();
    unset($rows[1]);
    $rows = array_values($rows);

    $record = libsqlite_suite_next149156_record($rows);

    $t->same('blocked', $record['status']);
    $t->same(['next150'], $record['missing_suite_phases']);
    $t->contains('missing prepared suite phases: next150', implode('; ', array_column($record['blockers'], 'evidence')));
};

$tests['current source next149-156 blocks duplicate phase'] = static function (TestRunner $t): void {
    $rows = libsqlite_suite_next149156_rows();
    $rows[1]['suite_phase'] = 'next149';

    $record = libsqlite_suite_next149156_record($rows);

    $t->same('blocked', $record['status']);
    $t->same(['next149'], $record['duplicate_suite_phases']);
    $t->contains('duplicate-suite-phase', implode('; ', array_column($record['blockers'], 'evidence')));
};

$tests['current source next149-156 preserves already counted phases without mapped inflation'] = static function (TestRunner $t): void {
    $rows = libsqlite_suite_next149156_rows();
    foreach (range(0, 7) as $index) {
        $rows[$index]['current_countable'] = true;
        $rows[$index]['current_tests'] = $rows[$index]['next_tests'];
    }

    $record = libsqlite_suite_next149156_record($rows);

    $t->same('current-source-next149-156-upstream-suite-evidence-preserved', $record['status']);
    $t->same(0, $record['mapped_delta']);
    $t->same(645, $record['next_mapped']);
};

return $tests;

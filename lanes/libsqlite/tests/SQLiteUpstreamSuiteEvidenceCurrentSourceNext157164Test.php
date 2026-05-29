<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteUpstreamSuiteEvidence;

function libsqlite_suite_next157164_evidence(): SQLiteUpstreamSuiteEvidence
{
    return SQLiteUpstreamSuiteEvidence::fromManifestPath(__DIR__ . '/../UPSTREAM_TEST_MANIFEST.json');
}

function libsqlite_suite_next157164_output(int $passLines = 57, int $assertions = 126, int $failures = 0): string
{
    $lines = ['Focused test run: 1 selected test files (root lock skipped)'];
    for ($i = 1; $i <= $passLines; $i++) {
        $lines[] = sprintf('PASS current source next157-164 upstream suite evidence case %02d', $i);
    }
    $lines[] = sprintf('1 test files, %d assertions, %d failures', $assertions, $failures);

    return implode("\n", $lines);
}

/**
 * @return list<array<string, mixed>>
 */
function libsqlite_suite_next157164_rows(
    int $case = 1,
    string $launcherBase = '857804470116cff4df7c10f418e35f9035a112d9',
    string $dashboardSource = '857804470116cff4df7c10f418e35f9035a112d9',
    string $statusSource = '857804470116cff4df7c10f418e35f9035a112d9',
    string $implementationSource = '857804470116cff4df7c10f418e35f9035a112d9',
    string $nextHead = 'upstream-suite-evidence-current-source-next157-164'
): array {
    $rows = [];
    foreach (['next157', 'next158', 'next159', 'next160', 'next161', 'next162', 'next163', 'next164'] as $index => $phase) {
        $script = sprintf('upstream-suite-evidence-current-source-%s-%02d.test', $phase, $case);
        $rows[] = [
            'unit' => 'upstream-suite-evidence-current-source-' . $phase,
            'kind' => 'bounded-upstream-suite-evidence-octet',
            'gap_id' => 'current-source-' . $phase . '-suite-evidence-gap',
            'gap_status' => 'removed',
            'removed_blocker' => $phase . ' prepares isolated current-source upstream suite evidence after merged next149-156 evidence',
            'rebase_status' => 'rebased',
            'rebase_reason' => 'octet evidence is tied to base 857804470 and the merged next149-156 evidence handoff',
            'final_evidence_id' => 'current-source-' . $phase . '-suite-evidence',
            'final_evidence_status' => 'finalized',
            'stale_baseline_id' => 'merged-next149-156-suite-evidence-baseline-' . $phase,
            'suite_phase' => $phase,
            'suite_phase_id' => 'current-source-' . $phase . '-prepared',
            'suite_phase_status' => 'prepared',
            'suite_phase_evidence' => $phase . ' has lane-local current-source upstream suite evidence after next149-156 and no release/all parity claim',
            'current_source_only' => true,
            'source_head' => $nextHead,
            'launcher_base_head' => $launcherBase,
            'dashboard_source_head' => $dashboardSource,
            'status_source_head' => $statusSource,
            'implementation_source_head' => $implementationSource,
            'artifact_path' => 'lanes/libsqlite/notes/upstream-suite-evidence-current-source-next157-164.md',
            'runner_command' => './testfixture ../libsqlite/test/testrunner.tcl --jobs 1 --stop-on-error veryquick ' . $script,
            'scripts' => [$script, 'testrunner.test'],
            'current_countable' => false,
            'next_countable' => true,
            'exit' => 0,
            'errors' => 0,
            'current_tests' => 0,
            'next_tests' => 8200 + $case + $index,
            'evidence' => $phase . ' admits one current-source suite evidence row only when merged next149-156 evidence gates, unique stale baselines, lane-local artifacts, zero runner errors, and focused PASS lines are clear',
        ];
    }

    $rows[] = [
        'unit' => 'upstream-suite-evidence-current-source-next149-156-anchor',
        'kind' => 'bounded-upstream-suite-evidence-anchor',
        'gap_id' => 'current-source-next149-156-final-anchor',
        'gap_status' => 'preserved',
        'rebase_status' => 'preserved',
        'rebase_reason' => 'merged next149-156 prepared suite evidence remains preserved and is not remapped by next157-164',
        'final_evidence_id' => 'current-source-next149-156-final-anchor',
        'final_evidence_status' => 'preserved',
        'stale_baseline_id' => 'accepted-next149-156-final-anchor',
        'source_head' => $nextHead,
        'launcher_base_head' => $launcherBase,
        'dashboard_source_head' => $dashboardSource,
        'status_source_head' => $statusSource,
        'implementation_source_head' => $implementationSource,
        'artifact_path' => 'lanes/libsqlite/notes/upstream-suite-evidence-current-source-next149-156.md',
        'runner_command' => './testfixture ../libsqlite/test/testrunner.tcl --jobs 1 --stop-on-error veryquick accepted-current-source-next149-156-anchor.test',
        'scripts' => ['accepted-current-source-next149-156-anchor.test'],
        'current_countable' => true,
        'next_countable' => true,
        'exit' => 0,
        'errors' => 0,
        'current_tests' => 65300,
        'next_tests' => 65300,
        'evidence' => 'merged next149-156 prepared suite evidence remains preserved while next157-164 prepares only new current-source rows',
    ];

    return $rows;
}

/**
 * @param list<array<string, mixed>> $rows
 * @return array<string, mixed>
 */
function libsqlite_suite_next157164_record(
    array $rows,
    string $launcherBase = '857804470116cff4df7c10f418e35f9035a112d9',
    string $dashboardSource = '857804470116cff4df7c10f418e35f9035a112d9',
    string $statusSource = '857804470116cff4df7c10f418e35f9035a112d9',
    string $implementationSource = '857804470116cff4df7c10f418e35f9035a112d9',
    string $nextHead = 'upstream-suite-evidence-current-source-next157-164',
    ?string $output = null,
    ?int $expected = 57,
    string $snapshot = ''
): array {
    return libsqlite_suite_next157164_evidence()->upstreamRunnerSuiteEvidenceFinalPreparedOctet(
        $rows,
        653,
        42376,
        $launcherBase,
        $dashboardSource,
        $statusSource,
        $implementationSource,
        $nextHead,
        'lanes/libsqlite/tests/SQLiteUpstreamSuiteEvidenceCurrentSourceNext157164Test.php',
        $output ?? libsqlite_suite_next157164_output(),
        'current-source next157-164 upstream-suite evidence avoids merged next149-156 suite evidence, prior suite-evidence rows, accepted veryquick shard rows, accepted behavior clusters, queued blockers, and release/all parity',
        $expected,
        $snapshot
    );
}

$tests = [];

$tests['current source next157-164 prepares suite evidence octet'] = static function (TestRunner $t): void {
    $record = libsqlite_suite_next157164_record(libsqlite_suite_next157164_rows());

    $t->same('current-source-next157-164-upstream-suite-evidence-prepared', $record['status']);
    $t->same(true, $record['countable']);
    $t->same(653, $record['current_mapped']);
    $t->same(661, $record['next_mapped']);
    $t->same(8, $record['mapped_delta']);
    $t->same(57, $record['php_pass_delta']);
    $t->same(42433, $record['next_php_pass']);
    $t->same(['next157', 'next158', 'next159', 'next160', 'next161', 'next162', 'next163', 'next164'], $record['prepared_suite_phases']);
    $t->same([], $record['missing_suite_phases']);
    $t->same(true, $record['counts_upstream_suite_evidence_current_source_next157_164']);
    $t->same(false, $record['counts_upstream_suite_evidence_current_source_next149_156']);
    $t->same(false, $record['counts_upstream_veryquick_shard_current_source_next164']);
    $t->same(false, $record['counts_release_parity']);
    $t->contains('upstream-suite-evidence-current-source-next164-01.test', implode(',', $record['target_scripts']));
};

$tests['current source next157-164 records phase row metadata'] = static function (TestRunner $t): void {
    $record = libsqlite_suite_next157164_record(libsqlite_suite_next157164_rows(4));

    $t->same(65660, $record['tests_total_delta']);
    $t->same('next157', $record['suite_phase_rows'][0]['suite_phase']);
    $t->same('prepared', $record['suite_phase_rows'][1]['suite_phase_status']);
    $t->contains('no release/all parity claim', $record['suite_phase_rows'][2]['suite_phase_evidence']);
};

$tests['current source next157-164 blocks missing phase'] = static function (TestRunner $t): void {
    $rows = libsqlite_suite_next157164_rows();
    unset($rows[1]);
    $rows = array_values($rows);

    $record = libsqlite_suite_next157164_record($rows);

    $t->same('blocked', $record['status']);
    $t->same(['next158'], $record['missing_suite_phases']);
    $t->contains('missing prepared suite phases: next158', implode('; ', array_column($record['blockers'], 'evidence')));
};

$tests['current source next157-164 blocks duplicate phase'] = static function (TestRunner $t): void {
    $rows = libsqlite_suite_next157164_rows();
    $rows[1]['suite_phase'] = 'next157';

    $record = libsqlite_suite_next157164_record($rows);

    $t->same('blocked', $record['status']);
    $t->same(['next157'], $record['duplicate_suite_phases']);
    $t->contains('duplicate-suite-phase', implode('; ', array_column($record['blockers'], 'evidence')));
};

$tests['current source next157-164 preserves already counted phases without mapped inflation'] = static function (TestRunner $t): void {
    $rows = libsqlite_suite_next157164_rows();
    foreach (range(0, 7) as $index) {
        $rows[$index]['current_countable'] = true;
        $rows[$index]['current_tests'] = $rows[$index]['next_tests'];
    }

    $record = libsqlite_suite_next157164_record($rows);

    $t->same('current-source-next157-164-upstream-suite-evidence-preserved', $record['status']);
    $t->same(0, $record['mapped_delta']);
    $t->same(653, $record['next_mapped']);
};

return $tests;

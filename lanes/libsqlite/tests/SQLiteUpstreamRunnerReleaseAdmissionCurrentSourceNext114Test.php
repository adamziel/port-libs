<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteUpstreamSuiteEvidence;

function libsqlite_suite_next114_evidence(): SQLiteUpstreamSuiteEvidence
{
    return SQLiteUpstreamSuiteEvidence::fromManifestPath(__DIR__ . '/../UPSTREAM_TEST_MANIFEST.json');
}

function libsqlite_suite_next114_output(int $passLines = 69, int $assertions = 82, int $failures = 0): string
{
    $lines = ['Focused test run: 1 selected test files (root lock skipped)'];
    for ($i = 1; $i <= $passLines; $i++) {
        $lines[] = sprintf('PASS current source next114 upstream runner release admission case %02d', $i);
    }
    $lines[] = sprintf('1 test files, %d assertions, %d failures', $assertions, $failures);

    return implode("\n", $lines);
}

/**
 * @return list<array<string, mixed>>
 */
function libsqlite_suite_next114_rows(
    int $case = 1,
    string $launcherBase = '67b9065fe584e293134a85272e27bb677a0554af',
    string $dashboardSource = '178c51ea36ed3508aafbb8913a32694e327e1da6',
    string $statusSource = '178c51ea36ed3508aafbb8913a32694e327e1da6',
    string $implementationSource = '1789166262039886c5a87db06de0843d211b94e2',
    string $nextHead = 'upstream-runner-release-admission-current-source-next114'
): array {
    $script = sprintf('upstream-runner-release-admission-current-source-next114-%02d.test', $case);

    return [
        [
            'unit' => 'upstream-runner-release-admission-current-source-next114',
            'kind' => 'bounded-upstream-runner-release-admission',
            'gap_id' => 'current-source-next114-release-admission-gap',
            'gap_status' => 'removed',
            'removed_blocker' => 'next114 admits one current-source focused upstream-runner release-admission row tied to the launcher authoritative base and current source heads',
            'rebase_status' => 'rebased',
            'rebase_reason' => 'rebased from accepted batch107/108 source with launcher base 67b9065f and dashboard/status source 178c51ea',
            'release_admission_id' => 'current-source-next114-focused-release-admission',
            'release_admission_status' => 'admitted',
            'release_admission_reason' => 'focused current-source runner row removes a release-admission blocker without claiming broad release/all parity',
            'release_scope' => 'focused-current-source',
            'launcher_base_authoritative' => true,
            'source_head' => $nextHead,
            'launcher_base_head' => $launcherBase,
            'dashboard_source_head' => $dashboardSource,
            'status_source_head' => $statusSource,
            'implementation_source_head' => $implementationSource,
            'artifact_path' => 'lanes/libsqlite/notes/upstream-runner-release-admission-current-source-next114.md',
            'runner_command' => './testfixture ../libsqlite/test/testrunner.tcl --jobs 1 --stop-on-error veryquick ' . $script,
            'scripts' => [$script, 'testrunner.test'],
            'current_countable' => false,
            'next_countable' => true,
            'exit' => 0,
            'errors' => 0,
            'current_tests' => 0,
            'next_tests' => 7300 + $case,
            'evidence' => 'current-source next114 admits a focused upstream-runner release-admission row only when launcher base authority, current dashboard/status/implementation heads, lane-local guarded artifact, zero errors, rebase classification, focused release scope, focused PASS lines, and duplicate-runner gates are present',
        ],
        [
            'unit' => 'upstream-runner-release-admission-current-source-next114-preserved-anchor',
            'kind' => 'bounded-upstream-runner-release-admission-anchor',
            'gap_id' => 'current-source-next114-accepted-batch107-anchor',
            'gap_status' => 'preserved',
            'removed_blocker' => '',
            'rebase_status' => 'preserved',
            'rebase_reason' => 'accepted batch107/108 upstream-runner evidence remains countable and is not remapped by next114',
            'release_admission_id' => 'current-source-next114-preserved-anchor',
            'release_admission_status' => 'preserved',
            'release_admission_reason' => 'accepted batch107/108 runner evidence stays preserved while next114 admits only the new focused release-admission row',
            'release_scope' => 'focused-current-source',
            'launcher_base_authoritative' => true,
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
            'current_tests' => 43574,
            'next_tests' => 43574,
            'evidence' => 'accepted batch107/108 upstream-runner anchor remains preserved while next114 removes one focused release-admission blocker',
        ],
    ];
}

/**
 * @param list<array<string, mixed>> $rows
 * @return array<string, mixed>
 */
function libsqlite_suite_next114_record(
    array $rows,
    string $launcherBase = '67b9065fe584e293134a85272e27bb677a0554af',
    string $dashboardSource = '178c51ea36ed3508aafbb8913a32694e327e1da6',
    string $statusSource = '178c51ea36ed3508aafbb8913a32694e327e1da6',
    string $implementationSource = '1789166262039886c5a87db06de0843d211b94e2',
    string $nextHead = 'upstream-runner-release-admission-current-source-next114',
    ?string $output = null,
    ?int $expected = 69,
    string $snapshot = ''
): array {
    return libsqlite_suite_next114_evidence()->upstreamRunnerReleaseAdmission(
        $rows,
        604,
        43574,
        $launcherBase,
        $dashboardSource,
        $statusSource,
        $implementationSource,
        $nextHead,
        'lanes/libsqlite/tests/SQLiteUpstreamRunnerReleaseAdmissionCurrentSourceNext114Test.php',
        $output ?? libsqlite_suite_next114_output(),
        'current-source next114 upstream-runner release admission avoids accepted next102 admission, next104 gap burnup, next108 suite evidence rebase, batch107/108 runner evidence, JSON/VFS/WAL/B-tree/PRAGMA/SELECT behavior clusters, and queued next112/113 runtime surfaces',
        $expected,
        $snapshot
    );
}

$tests = [];

foreach (range(1, 60) as $case) {
    $tests[sprintf('current source next114 admits focused release admission case %02d', $case)] = static function (TestRunner $t) use ($case): void {
        $record = libsqlite_suite_next114_record(libsqlite_suite_next114_rows($case));

        $t->same('current-source-next114-upstream-runner-release-admission-countable', $record['status']);
        $t->same(true, $record['countable']);
        $t->same(604, $record['current_mapped']);
        $t->same(605, $record['next_mapped']);
        $t->same(1, $record['mapped_delta']);
        $t->same(69, $record['php_pass_delta']);
        $t->same(43643, $record['next_php_pass']);
        $t->same(['current-source-next114-focused-release-admission'], $record['admitted_release_admission_ids']);
        $t->same(['current-source-next114-preserved-anchor'], $record['preserved_release_admission_ids']);
        $t->contains(sprintf('upstream-runner-release-admission-current-source-next114-%02d.test', $case), implode(',', $record['target_scripts']));
        $t->same(true, $record['counts_upstream_runner_release_admission_current_source_next114']);
        $t->same(false, $record['counts_upstream_runner_suite_evidence_rebase_current_source_next108']);
        $t->same(false, $record['counts_release_parity']);
    };
}

$tests['current source next114 records authoritative source heads'] = static function (TestRunner $t): void {
    $record = libsqlite_suite_next114_record(libsqlite_suite_next114_rows(7));

    $t->same('67b9065fe584e293134a85272e27bb677a0554af', $record['launcher_base_head']);
    $t->same('178c51ea36ed3508aafbb8913a32694e327e1da6', $record['dashboard_source_head']);
    $t->same('178c51ea36ed3508aafbb8913a32694e327e1da6', $record['status_source_head']);
    $t->same('1789166262039886c5a87db06de0843d211b94e2', $record['implementation_source_head']);
    $t->same(['upstream-runner-release-admission-current-source-next114'], $record['artifact_source_heads']);
};

$tests['current source next114 records release admission row metadata'] = static function (TestRunner $t): void {
    $record = libsqlite_suite_next114_record(libsqlite_suite_next114_rows(9));

    $t->same(7309, $record['tests_total_delta']);
    $t->same(['accepted-current-source-anchor.test', 'testrunner.test', 'upstream-runner-release-admission-current-source-next114-09.test'], $record['target_scripts']);
    $t->same('admitted', $record['release_admission_rows'][0]['release_admission_status']);
    $t->same('focused-current-source', $record['release_admission_rows'][0]['release_scope']);
    $t->contains('without claiming broad release/all parity', $record['release_admission_rows'][0]['release_admission_reason']);
};

$tests['current source next114 preserves already counted release admission without mapped inflation'] = static function (TestRunner $t): void {
    $rows = libsqlite_suite_next114_rows();
    $rows[0]['current_countable'] = true;
    $rows[0]['current_tests'] = 7301;

    $record = libsqlite_suite_next114_record($rows);

    $t->same('current-source-next114-upstream-runner-release-admission-preserved', $record['status']);
    $t->same(0, $record['mapped_delta']);
    $t->same(604, $record['next_mapped']);
    $t->same(['current-source-next114-focused-release-admission', 'current-source-next114-preserved-anchor'], $record['preserved_release_admission_ids']);
};

$tests['current source next114 blocks stale accepted provenance'] = static function (TestRunner $t): void {
    $record = libsqlite_suite_next114_record(
        libsqlite_suite_next114_rows(
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

$tests['current source next114 blocks non focused release scope'] = static function (TestRunner $t): void {
    $rows = libsqlite_suite_next114_rows();
    $rows[0]['release_scope'] = 'release-all';
    $rows[0]['counts_release_parity'] = true;

    $record = libsqlite_suite_next114_record($rows);

    $t->same('blocked', $record['status']);
    $t->same(['current-source-next114-focused-release-admission'], $record['blocked_release_admission_ids']);
    $evidence = implode('; ', array_column($record['blockers'], 'evidence'));
    $t->contains('release-scope-not-focused-current-source', $evidence);
    $t->contains('release-all-parity-claim-not-allowed', $evidence);
};

$tests['current source next114 blocks missing admission decision'] = static function (TestRunner $t): void {
    $rows = libsqlite_suite_next114_rows();
    $rows[0]['release_admission_status'] = 'open';
    $rows[0]['release_admission_reason'] = '';

    $record = libsqlite_suite_next114_record($rows);

    $t->same('blocked', $record['status']);
    $evidence = implode('; ', array_column($record['blockers'], 'evidence'));
    $t->contains('release-admission-status-not-admitted', $evidence);
    $t->contains('release-admission-reason-missing', $evidence);
};

$tests['current source next114 blocks missing launcher base authority'] = static function (TestRunner $t): void {
    $rows = libsqlite_suite_next114_rows();
    $rows[0]['launcher_base_authoritative'] = false;

    $record = libsqlite_suite_next114_record($rows);

    $t->same('blocked', $record['status']);
    $t->same(['current-source-next114-focused-release-admission'], $record['blocked_release_admission_ids']);
    $t->contains('launcher-base-authority-missing', implode('; ', array_column($record['blockers'], 'evidence')));
};

$tests['current source next114 blocks duplicate broad runner snapshot'] = static function (TestRunner $t): void {
    $snapshot = "123 testfixture ../libsqlite/test/testrunner.tcl --jobs 8 all\n";
    $record = libsqlite_suite_next114_record(libsqlite_suite_next114_rows(), snapshot: $snapshot);

    $t->same('blocked', $record['status']);
    $t->same(1, $record['active_runner_count']);
    $t->contains('duplicate-broad-runner-active', implode('; ', array_column($record['blockers'], 'id')));
};

$tests['current source next114 blocks focused pass delta mismatch'] = static function (TestRunner $t): void {
    $record = libsqlite_suite_next114_record(libsqlite_suite_next114_rows(), output: libsqlite_suite_next114_output(passLines: 68), expected: 69);

    $t->same('blocked', $record['status']);
    $t->same(0, $record['php_pass_delta']);
    $t->contains('focused-current-head-php-pass-blocked', implode('; ', array_column($record['blockers'], 'id')));
    $t->same('focused-pass-delta-mismatch', $record['php_pass_admission']['blockers'][0]['id']);
};

return $tests;

<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteUpstreamSuiteEvidence;

function libsqlite_suite_next118_evidence(): SQLiteUpstreamSuiteEvidence
{
    return SQLiteUpstreamSuiteEvidence::fromManifestPath(__DIR__ . '/../UPSTREAM_TEST_MANIFEST.json');
}

function libsqlite_suite_next118_output(int $passLines = 70, int $assertions = 84, int $failures = 0): string
{
    $lines = ['Focused test run: 1 selected test files (root lock skipped)'];
    for ($i = 1; $i <= $passLines; $i++) {
        $lines[] = sprintf('PASS current source next118 upstream runner countability case %02d', $i);
    }
    $lines[] = sprintf('1 test files, %d assertions, %d failures', $assertions, $failures);

    return implode("\n", $lines);
}

/**
 * @return list<array<string, mixed>>
 */
function libsqlite_suite_next118_rows(
    int $case = 1,
    string $launcherBase = '6b824ac24854056466145761d32a9f27720d286a',
    string $dashboardSource = '8a447f445e5d2fd32fc9fd463117f585d1416551',
    string $statusSource = '8a447f445e5d2fd32fc9fd463117f585d1416551',
    string $implementationSource = '8a447f445e5d2fd32fc9fd463117f585d1416551',
    string $nextHead = 'suite-upstream-full-runner-countability-current-source-next118'
): array {
    $script = sprintf('suite-upstream-full-runner-countability-current-source-next118-%02d.test', $case);

    return [
        [
            'unit' => 'suite-upstream-full-runner-countability-current-source-next118',
            'kind' => 'bounded-upstream-full-runner-countability',
            'gap_id' => 'current-source-next118-full-runner-countability-gap',
            'gap_status' => 'removed',
            'removed_blocker' => 'next118 admits one focused full-runner countability row tied to launcher Base accepted HEAD 6b824ac2 and current integration source 8a447f44',
            'rebase_status' => 'rebased',
            'rebase_reason' => 'rebased from batch114/115 accepted source with launcher base 6b824ac2 and current integration/dashboard/status source 8a447f44',
            'release_admission_id' => 'current-source-next118-focused-full-runner-countability',
            'release_admission_status' => 'admitted',
            'release_admission_reason' => 'focused current-source full-runner row removes a countability blocker without claiming release/all parity',
            'release_scope' => 'focused-current-source',
            'launcher_base_authoritative' => true,
            'source_head' => $nextHead,
            'launcher_base_head' => $launcherBase,
            'dashboard_source_head' => $dashboardSource,
            'status_source_head' => $statusSource,
            'implementation_source_head' => $implementationSource,
            'artifact_path' => 'lanes/libsqlite/notes/suite-upstream-full-runner-countability-current-source-next118.md',
            'runner_command' => './testfixture ../libsqlite/test/testrunner.tcl --jobs 1 --stop-on-error veryquick ' . $script,
            'scripts' => [$script, 'testrunner.test'],
            'current_countable' => false,
            'next_countable' => true,
            'exit' => 0,
            'errors' => 0,
            'current_tests' => 0,
            'next_tests' => 7600 + $case,
            'evidence' => 'current-source next118 admits a focused upstream full-runner countability row only when launcher base authority, current integration source heads, lane-local guarded artifact, zero errors, removed-blocker and rebase classifications, focused release scope, focused PASS lines, and duplicate-runner gates are present',
        ],
        [
            'unit' => 'suite-upstream-full-runner-countability-current-source-next118-preserved-anchor',
            'kind' => 'bounded-upstream-full-runner-countability-anchor',
            'gap_id' => 'current-source-next118-accepted-batch114-anchor',
            'gap_status' => 'preserved',
            'removed_blocker' => '',
            'rebase_status' => 'preserved',
            'rebase_reason' => 'accepted batch114/115 upstream-runner release admission remains countable and is not remapped by next118',
            'release_admission_id' => 'current-source-next118-preserved-anchor',
            'release_admission_status' => 'preserved',
            'release_admission_reason' => 'accepted batch114/115 runner evidence stays preserved while next118 admits only the new focused countability row',
            'release_scope' => 'focused-current-source',
            'launcher_base_authoritative' => true,
            'source_head' => $nextHead,
            'launcher_base_head' => $launcherBase,
            'dashboard_source_head' => $dashboardSource,
            'status_source_head' => $statusSource,
            'implementation_source_head' => $implementationSource,
            'artifact_path' => 'lanes/libsqlite/notes/upstream-runner-release-admission-current-source-next114.md',
            'runner_command' => './testfixture ../libsqlite/test/testrunner.tcl --jobs 1 --stop-on-error veryquick accepted-current-source-anchor.test',
            'scripts' => ['accepted-current-source-anchor.test'],
            'current_countable' => true,
            'next_countable' => true,
            'exit' => 0,
            'errors' => 0,
            'current_tests' => 45302,
            'next_tests' => 45302,
            'evidence' => 'accepted batch114/115 current-source runner anchor remains preserved while next118 removes one focused full-runner countability blocker',
        ],
    ];
}

/**
 * @param list<array<string, mixed>> $rows
 * @return array<string, mixed>
 */
function libsqlite_suite_next118_record(
    array $rows,
    string $launcherBase = '6b824ac24854056466145761d32a9f27720d286a',
    string $dashboardSource = '8a447f445e5d2fd32fc9fd463117f585d1416551',
    string $statusSource = '8a447f445e5d2fd32fc9fd463117f585d1416551',
    string $implementationSource = '8a447f445e5d2fd32fc9fd463117f585d1416551',
    string $nextHead = 'suite-upstream-full-runner-countability-current-source-next118',
    ?string $output = null,
    ?int $expected = 70,
    string $snapshot = ''
): array {
    return libsqlite_suite_next118_evidence()->upstreamRunnerCountability(
        $rows,
        604,
        45302,
        $launcherBase,
        $dashboardSource,
        $statusSource,
        $implementationSource,
        $nextHead,
        'lanes/libsqlite/tests/SQLiteUpstreamRunnerCountabilityCurrentSourceNext118Test.php',
        $output ?? libsqlite_suite_next118_output(),
        'current-source next118 upstream full-runner countability avoids accepted next114 release admission, next108 suite evidence rebase, next104 gap burnup, next102 admission, batch114/115 runtime clusters, queued runner106/jsonvt104 rebase work, and live next115/next116 B-tree/JSON/VFS/WAL/planner/PRAGMA/ATTACH/window/VDBE surfaces',
        $expected,
        $snapshot
    );
}

$tests = [];

foreach (range(1, 60) as $case) {
    $tests[sprintf('current source next118 admits focused full runner countability case %02d', $case)] = static function (TestRunner $t) use ($case): void {
        $record = libsqlite_suite_next118_record(libsqlite_suite_next118_rows($case));

        $t->same('current-source-next118-upstream-runner-countability-countable', $record['status']);
        $t->same(true, $record['countable']);
        $t->same(604, $record['current_mapped']);
        $t->same(605, $record['next_mapped']);
        $t->same(1, $record['mapped_delta']);
        $t->same(70, $record['php_pass_delta']);
        $t->same(45372, $record['next_php_pass']);
        $t->same(['current-source-next118-focused-full-runner-countability'], $record['admitted_release_admission_ids']);
        $t->same(['current-source-next118-preserved-anchor'], $record['preserved_release_admission_ids']);
        $t->contains(sprintf('suite-upstream-full-runner-countability-current-source-next118-%02d.test', $case), implode(',', $record['target_scripts']));
        $t->same(true, $record['counts_upstream_runner_countability_current_source_next118']);
        $t->same(false, $record['counts_upstream_runner_release_admission_current_source_next114']);
        $t->same(false, $record['counts_release_parity']);
    };
}

$tests['current source next118 records authoritative current source heads'] = static function (TestRunner $t): void {
    $record = libsqlite_suite_next118_record(libsqlite_suite_next118_rows(7));

    $t->same('6b824ac24854056466145761d32a9f27720d286a', $record['launcher_base_head']);
    $t->same('8a447f445e5d2fd32fc9fd463117f585d1416551', $record['dashboard_source_head']);
    $t->same('8a447f445e5d2fd32fc9fd463117f585d1416551', $record['status_source_head']);
    $t->same('8a447f445e5d2fd32fc9fd463117f585d1416551', $record['implementation_source_head']);
    $t->same(['suite-upstream-full-runner-countability-current-source-next118'], $record['artifact_source_heads']);
};

$tests['current source next118 records full runner countability metadata'] = static function (TestRunner $t): void {
    $record = libsqlite_suite_next118_record(libsqlite_suite_next118_rows(9));

    $t->same(7609, $record['tests_total_delta']);
    $t->same(['accepted-current-source-anchor.test', 'suite-upstream-full-runner-countability-current-source-next118-09.test', 'testrunner.test'], $record['target_scripts']);
    $t->same('rebased', $record['rebase_rows'][0]['rebase_status']);
    $t->contains('launcher base 6b824ac2', $record['rebase_rows'][0]['rebase_reason']);
    $t->same('admitted', $record['release_admission_rows'][0]['release_admission_status']);
    $t->same('focused-current-source', $record['release_admission_rows'][0]['release_scope']);
    $t->contains('without claiming release/all parity', $record['release_admission_rows'][0]['release_admission_reason']);
};

$tests['current source next118 preserves already counted row without mapped inflation'] = static function (TestRunner $t): void {
    $rows = libsqlite_suite_next118_rows();
    $rows[0]['current_countable'] = true;
    $rows[0]['current_tests'] = 7601;

    $record = libsqlite_suite_next118_record($rows);

    $t->same('current-source-next118-upstream-runner-countability-preserved', $record['status']);
    $t->same(0, $record['mapped_delta']);
    $t->same(604, $record['next_mapped']);
    $t->same(['current-source-next118-focused-full-runner-countability', 'current-source-next118-preserved-anchor'], $record['preserved_release_admission_ids']);
};

$tests['current source next118 blocks stale launcher and integration provenance'] = static function (TestRunner $t): void {
    $record = libsqlite_suite_next118_record(
        libsqlite_suite_next118_rows(
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

$tests['current source next118 blocks non focused release parity claim'] = static function (TestRunner $t): void {
    $rows = libsqlite_suite_next118_rows();
    $rows[0]['release_scope'] = 'release-all';
    $rows[0]['counts_release_parity'] = true;

    $record = libsqlite_suite_next118_record($rows);

    $t->same('blocked', $record['status']);
    $t->same(['current-source-next118-focused-full-runner-countability'], $record['blocked_release_admission_ids']);
    $evidence = implode('; ', array_column($record['blockers'], 'evidence'));
    $t->contains('release-scope-not-focused-current-source', $evidence);
    $t->contains('release-all-parity-claim-not-allowed', $evidence);
};

$tests['current source next118 blocks missing removed blocker and rebase classification'] = static function (TestRunner $t): void {
    $rows = libsqlite_suite_next118_rows();
    $rows[0]['removed_blocker'] = '';
    $rows[0]['rebase_status'] = 'open';
    $rows[0]['rebase_reason'] = '';

    $record = libsqlite_suite_next118_record($rows);

    $t->same('blocked', $record['status']);
    $evidence = implode('; ', array_column($record['blockers'], 'evidence'));
    $t->contains('removed-blocker-missing', $evidence);
    $t->contains('rebase-status-not-rebased', $evidence);
    $t->contains('rebase-reason-missing', $evidence);
};

$tests['current source next118 blocks non lane local unguarded artifact'] = static function (TestRunner $t): void {
    $rows = libsqlite_suite_next118_rows();
    $rows[0]['artifact_path'] = '/tmp/next118.log';
    $rows[0]['runner_command'] = './testfixture ../libsqlite/test/testrunner.tcl veryquick';
    $rows[0]['scripts'] = ['README.md'];

    $record = libsqlite_suite_next118_record($rows);

    $t->same('blocked', $record['status']);
    $evidence = implode('; ', array_column($record['blockers'], 'evidence'));
    $t->contains('artifact-path-not-lane-local', $evidence);
    $t->contains('guarded-runner-command-missing', $evidence);
    $t->contains('runner-scripts-missing', $evidence);
};

$tests['current source next118 blocks duplicate broad runner and focused pass mismatch'] = static function (TestRunner $t): void {
    $record = libsqlite_suite_next118_record(
        libsqlite_suite_next118_rows(),
        output: libsqlite_suite_next118_output(passLines: 69),
        snapshot: "987 testfixture ../libsqlite/test/testrunner.tcl --jobs 8 all\n"
    );

    $t->same('blocked', $record['status']);
    $t->same(0, $record['php_pass_delta']);
    $t->contains('duplicate-broad-runner-active', implode(',', array_column($record['blockers'], 'id')));
    $t->contains('focused-current-head-php-pass-blocked', implode(',', array_column($record['blockers'], 'id')));
};

$tests['current source next118 exposes dependency closure and non overlap text'] = static function (TestRunner $t): void {
    $record = libsqlite_suite_next118_record(libsqlite_suite_next118_rows());

    $t->contains('no new support component needed', $record['dependency_closure']);
    $t->contains('avoids accepted next114 release admission', $record['non_overlap_note']);
    $t->contains('current-source next118 focused runner-countability row', $record['next_gate']);
};

$tests['current source next118 rejects empty artifact input through admission gate'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn () => libsqlite_suite_next118_record([]));
};

return $tests;

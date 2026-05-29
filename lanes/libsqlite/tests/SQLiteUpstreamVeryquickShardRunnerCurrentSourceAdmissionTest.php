<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteUpstreamSuiteEvidence;

function libsqlite_suite_next158_evidence(): SQLiteUpstreamSuiteEvidence
{
    return SQLiteUpstreamSuiteEvidence::fromManifestPath(__DIR__ . '/../UPSTREAM_TEST_MANIFEST.json');
}

function libsqlite_suite_next158_output(int $passLines = 72, int $assertions = 72, int $failures = 0): string
{
    $lines = ['Focused test run: 1 selected test files (root lock skipped)'];
    for ($i = 1; $i <= $passLines; $i++) {
        $lines[] = sprintf('PASS current source next158 veryquick shard runner case %02d', $i);
    }
    $lines[] = sprintf('1 test files, %d assertions, %d failures', $assertions, $failures);

    return implode("\n", $lines);
}

/**
 * @return list<array<string, mixed>>
 */
function libsqlite_suite_next158_rows(
    int $case = 1,
    string $launcherBase = '4880a03300afb083403cb85638f3d1cb0f0226ad',
    string $dashboardSource = '8a447f445e5d2fd32fc9fd463117f585d1416551',
    string $statusSource = '8a447f445e5d2fd32fc9fd463117f585d1416551',
    string $implementationSource = '8a447f445e5d2fd32fc9fd463117f585d1416551',
    string $nextHead = 'suite-upstream-veryquick-shard-current-source-next158'
): array {
    $script = sprintf('suite-upstream-veryquick-shard-current-source-next158-%02d.test', $case);

    return [
        [
            'unit' => 'suite-upstream-veryquick-shard-current-source-next158',
            'kind' => 'bounded-upstream-veryquick-shard-runner',
            'gap_id' => 'current-source-next158-veryquick-shard-runner-gap',
            'gap_status' => 'removed',
            'removed_blocker' => 'next158 admits one exact veryquick shard-runner row tied to launcher Base accepted HEAD 4880a033 and integration source 8a447f44',
            'tier' => 'focused-veryquick-shard',
            'source_head' => $nextHead,
            'launcher_base_head' => $launcherBase,
            'dashboard_source_head' => $dashboardSource,
            'status_source_head' => $statusSource,
            'implementation_source_head' => $implementationSource,
            'artifact_path' => 'lanes/libsqlite/notes/suite-upstream-veryquick-shard-current-source-next158.md',
            'runner_command' => './testfixture ../libsqlite/test/testrunner.tcl --jobs 1 --stop-on-error veryquick ' . $script,
            'scripts' => [$script, 'testrunner.test'],
            'current_countable' => false,
            'next_countable' => true,
            'exit' => 0,
            'errors' => 0,
            'current_tests' => 0,
            'next_tests' => 44622 + $case,
        ],
        [
            'unit' => 'batch148-current-source-anchor',
            'kind' => 'accepted-upstream-runner-anchor',
            'gap_id' => 'accepted-batch148-suite-anchor',
            'gap_status' => 'preserved',
            'removed_blocker' => '',
            'tier' => 'accepted-anchor',
            'source_head' => $nextHead,
            'launcher_base_head' => $launcherBase,
            'dashboard_source_head' => $dashboardSource,
            'status_source_head' => $statusSource,
            'implementation_source_head' => $implementationSource,
            'artifact_path' => 'lanes/libsqlite/notes/suite-upstream-exact-shard-runner-current-source-next148.md',
            'runner_command' => './testfixture ../libsqlite/test/testrunner.tcl --jobs 1 --stop-on-error veryquick accepted-batch148-anchor.test',
            'scripts' => ['accepted-batch148-anchor.test'],
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
function libsqlite_suite_next158_record(
    array $rows,
    string $launcherBase = '4880a03300afb083403cb85638f3d1cb0f0226ad',
    string $dashboardSource = '8a447f445e5d2fd32fc9fd463117f585d1416551',
    string $statusSource = '8a447f445e5d2fd32fc9fd463117f585d1416551',
    string $implementationSource = '8a447f445e5d2fd32fc9fd463117f585d1416551',
    string $nextHead = 'suite-upstream-veryquick-shard-current-source-next158',
    ?string $output = null,
    ?int $expected = 72,
    string $snapshot = ''
): array {
    return libsqlite_suite_next158_evidence()->upstreamVeryquickShardRunnerCurrentSource(
        $rows,
        604,
        44622,
        $launcherBase,
        $dashboardSource,
        $statusSource,
        $implementationSource,
        $nextHead,
        'lanes/libsqlite/tests/SQLiteUpstreamVeryquickShardRunnerCurrentSourceAdmissionTest.php',
        $output ?? libsqlite_suite_next158_output(),
        'current-source next158 veryquick shard runner admission avoids accepted batch107/108, batch109-113, next148 exact-shard evidence, runner106/jsonvt104 rebase items, and live current-source next115/next116 B-tree/JSON/VFS/WAL/planner/PRAGMA/ATTACH/window/VDBE surfaces',
        $expected,
        $snapshot
    );
}

$tests = [];

foreach (range(1, 58) as $case) {
    $tests[sprintf('current source next158 admits veryquick shard runner case %02d', $case)] = static function (TestRunner $t) use ($case): void {
        $record = libsqlite_suite_next158_record(libsqlite_suite_next158_rows($case));

        $t->same('current-source-next158-veryquick-shard-runner-advanced', $record['status']);
        $t->same(true, $record['countable']);
        $t->same(604, $record['current_mapped']);
        $t->same(605, $record['next_mapped']);
        $t->same(1, $record['mapped_delta']);
        $t->same(72, $record['php_pass_delta']);
        $t->same(44694, $record['next_php_pass']);
        $t->same(['suite-upstream-veryquick-shard-current-source-next158'], $record['admitted_units']);
        $t->same(['batch148-current-source-anchor'], $record['preserved_units']);
        $t->contains(sprintf('suite-upstream-veryquick-shard-current-source-next158-%02d.test', $case), implode(',', $record['target_scripts']));
        $t->same(true, $record['counts_upstream_veryquick_shard_runner_current_source_next158']);
        $t->same(false, $record['counts_upstream_exact_shard_runner_current_source_next148']);
        $t->same(false, $record['counts_release_parity']);
    };
}

$tests['current source next158 records authoritative launcher and integration heads'] = static function (TestRunner $t): void {
    $record = libsqlite_suite_next158_record(libsqlite_suite_next158_rows(8));

    $t->same('4880a03300afb083403cb85638f3d1cb0f0226ad', $record['launcher_base_head']);
    $t->same('8a447f445e5d2fd32fc9fd463117f585d1416551', $record['dashboard_source_head']);
    $t->same('8a447f445e5d2fd32fc9fd463117f585d1416551', $record['status_source_head']);
    $t->same('8a447f445e5d2fd32fc9fd463117f585d1416551', $record['implementation_source_head']);
    $t->same(['suite-upstream-veryquick-shard-current-source-next158'], $record['artifact_source_heads']);
};

$tests['current source next158 records target scripts and tier counts'] = static function (TestRunner $t): void {
    $record = libsqlite_suite_next158_record(libsqlite_suite_next158_rows(11));

    $t->same(44633, $record['tests_total_delta']);
    $t->same(['accepted-batch148-anchor.test', 'suite-upstream-veryquick-shard-current-source-next158-11.test', 'testrunner.test'], $record['target_scripts']);
    $t->same('accepted-anchor', $record['tier_counts'][0]['tier']);
    $t->same(0, $record['tier_counts'][0]['admitted']);
    $t->same(1, $record['tier_counts'][0]['preserved']);
    $t->same('focused-veryquick-shard', $record['tier_counts'][1]['tier']);
    $t->same(1, $record['tier_counts'][1]['admitted']);
    $t->same(44633, $record['tier_counts'][1]['tests']);
};

$tests['current source next158 preserves already counted row without mapped inflation'] = static function (TestRunner $t): void {
    $rows = libsqlite_suite_next158_rows();
    $rows[0]['current_countable'] = true;
    $rows[0]['current_tests'] = 44623;

    $record = libsqlite_suite_next158_record($rows);

    $t->same('current-source-next158-veryquick-shard-runner-preserved', $record['status']);
    $t->same(0, $record['mapped_delta']);
    $t->same(604, $record['next_mapped']);
    $t->same(['batch148-current-source-anchor', 'suite-upstream-veryquick-shard-current-source-next158'], $record['preserved_units']);
};

$tests['current source next158 blocks stale source provenance'] = static function (TestRunner $t): void {
    $record = libsqlite_suite_next158_record(
        libsqlite_suite_next158_rows(
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

$tests['current source next158 blocks unguarded and non local artifacts'] = static function (TestRunner $t): void {
    $rows = libsqlite_suite_next158_rows();
    $rows[0]['artifact_path'] = '/tmp/next158.md';
    $rows[0]['runner_command'] = './testfixture ../libsqlite/test/testrunner.tcl all';
    $rows[0]['scripts'] = ['README.md'];

    $record = libsqlite_suite_next158_record($rows);

    $t->same('blocked', $record['status']);
    $evidence = implode('; ', array_column($record['blockers'], 'evidence'));
    $t->contains('artifact-path-not-lane-local', $evidence);
    $t->contains('guarded-runner-command-missing', $evidence);
    $t->contains('concrete-test-scripts-missing', $evidence);
};

$tests['current source next158 blocks non zero runner artifacts'] = static function (TestRunner $t): void {
    $rows = libsqlite_suite_next158_rows();
    $rows[0]['exit'] = 1;
    $rows[0]['errors'] = 2;

    $record = libsqlite_suite_next158_record($rows);

    $t->same('blocked', $record['status']);
    $t->same(0, $record['php_pass_delta']);
    $t->contains('runner-artifact-not-zero-error', implode('; ', array_column($record['blockers'], 'evidence')));
};

$tests['current source next158 blocks missing removed blocker classification'] = static function (TestRunner $t): void {
    $rows = libsqlite_suite_next158_rows();
    $rows[0]['removed_blocker'] = '';

    $record = libsqlite_suite_next158_record($rows);

    $t->same('blocked', $record['status']);
    $t->contains('removed-blocker-missing', implode('; ', array_column($record['blockers'], 'evidence')));
};

$tests['current source next158 blocks duplicate broad runner snapshot'] = static function (TestRunner $t): void {
    $record = libsqlite_suite_next158_record(
        libsqlite_suite_next158_rows(),
        snapshot: "12345 ./testfixture ../libsqlite/test/testrunner.tcl all\n"
    );

    $t->same('blocked', $record['status']);
    $t->same('blocked-active-runner', $record['active_runner_status']);
    $t->same(1, $record['active_runner_count']);
    $t->contains('duplicate-broad-runner-active', implode('; ', array_column($record['blockers'], 'id')));
};

$tests['current source next158 blocks focused php admission mismatch'] = static function (TestRunner $t): void {
    $record = libsqlite_suite_next158_record(
        libsqlite_suite_next158_rows(),
        output: libsqlite_suite_next158_output(assertions: 71)
    );

    $t->same('blocked', $record['status']);
    $t->contains('focused-php-pass-delta-mismatch', implode('; ', array_column($record['blockers'], 'id')));
};

$tests['current source next158 blocks missing focused runner output'] = static function (TestRunner $t): void {
    $record = libsqlite_suite_next158_record(
        libsqlite_suite_next158_rows(),
        output: "PASS current source next158 veryquick shard runner case\n1 test files, 72 assertions, 0 failures"
    );

    $t->same('blocked', $record['status']);
    $t->contains('focused-php-pass-admission-blocked', implode('; ', array_column($record['blockers'], 'id')));
};

return $tests;

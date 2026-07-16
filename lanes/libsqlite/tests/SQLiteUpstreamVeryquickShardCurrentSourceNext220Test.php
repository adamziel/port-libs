<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteUpstreamSuiteEvidence;

function libsqlite_suite_next220_evidence(): SQLiteUpstreamSuiteEvidence
{
    return SQLiteUpstreamSuiteEvidence::fromManifestPath(__DIR__ . '/../UPSTREAM_TEST_MANIFEST.json');
}

function libsqlite_suite_next220_output(int $passLines = 96, int $assertions = 96, int $failures = 0): string
{
    $lines = ['Focused test run: 1 selected test files (root lock skipped)'];
    for ($i = 1; $i <= $passLines; $i++) {
        $lines[] = sprintf('PASS current source next220 veryquick shard admission case %02d', $i);
    }
    $lines[] = sprintf('1 test files, %d assertions, %d failures', $assertions, $failures);

    return implode("\n", $lines);
}

/**
 * @return list<array<string, mixed>>
 */
function libsqlite_suite_next220_rows(
    int $case = 1,
    string $launcherBase = '57174af3d894b5b3ad8472548b456d5ce17271cb',
    string $dashboardSource = '8a447f445e5d2fd32fc9fd463117f585d1416551',
    string $statusSource = '8a447f445e5d2fd32fc9fd463117f585d1416551',
    string $implementationSource = '8a447f445e5d2fd32fc9fd463117f585d1416551',
    string $nextHead = 'suite-upstream-veryquick-shard-current-source-next220'
): array {
    $script = sprintf('veryquick-current-source-next220-%02d.test', $case);

    return [
        [
            'unit' => 'suite-upstream-veryquick-shard-current-source-next220',
            'kind' => 'bounded-upstream-veryquick-shard-runner',
            'gap_id' => 'current-source-next220-veryquick-shard-gap',
            'gap_status' => 'removed',
            'removed_blocker' => 'next220 admits one focused veryquick shard row tied to launcher Base accepted HEAD 57174af3 and integration source 8a447f44 without duplicating accepted next155 through next213 suite evidence',
            'tier' => 'focused-veryquick-shard',
            'source_head' => $nextHead,
            'launcher_base_head' => $launcherBase,
            'dashboard_source_head' => $dashboardSource,
            'status_source_head' => $statusSource,
            'implementation_source_head' => $implementationSource,
            'artifact_path' => 'lanes/libsqlite/notes/yield-suite-upstream-veryquick-shard-current-source-next220.md',
            'runner_command' => './testfixture ../libsqlite/test/testrunner.tcl --jobs 1 --stop-on-error veryquick ' . $script,
            'scripts' => [$script, 'testrunner.test'],
            'current_countable' => false,
            'next_countable' => true,
            'exit' => 0,
            'errors' => 0,
            'current_tests' => 0,
            'next_tests' => 106763 + $case,
        ],
        [
            'unit' => 'batch196-current-source-anchor',
            'kind' => 'accepted-upstream-runner-anchor',
            'gap_id' => 'accepted-batch196-suite-anchor',
            'gap_status' => 'preserved',
            'removed_blocker' => '',
            'tier' => 'accepted-anchor',
            'source_head' => $nextHead,
            'launcher_base_head' => $launcherBase,
            'dashboard_source_head' => $dashboardSource,
            'status_source_head' => $statusSource,
            'implementation_source_head' => $implementationSource,
            'artifact_path' => 'lanes/libsqlite/notes/yield-suite-upstream-veryquick-shard-current-source-next213.md',
            'runner_command' => './testfixture ../libsqlite/test/testrunner.tcl --jobs 1 --stop-on-error veryquick accepted-batch196-anchor.test',
            'scripts' => ['accepted-batch196-anchor.test'],
            'current_countable' => true,
            'next_countable' => true,
            'exit' => 0,
            'errors' => 0,
            'current_tests' => 106763,
            'next_tests' => 106763,
        ],
    ];
}

/**
 * @param list<array<string, mixed>> $rows
 * @return array<string, mixed>
 */
function libsqlite_suite_next220_record(
    array $rows,
    string $launcherBase = '57174af3d894b5b3ad8472548b456d5ce17271cb',
    string $dashboardSource = '8a447f445e5d2fd32fc9fd463117f585d1416551',
    string $statusSource = '8a447f445e5d2fd32fc9fd463117f585d1416551',
    string $implementationSource = '8a447f445e5d2fd32fc9fd463117f585d1416551',
    string $nextHead = 'suite-upstream-veryquick-shard-current-source-next220',
    ?string $output = null,
    ?int $expected = 96,
    string $snapshot = ''
): array {
    return libsqlite_suite_next220_evidence()->upstreamVeryquickShardCurrentSourceShard(
        220,
        $rows,
        624,
        106763,
        $launcherBase,
        $dashboardSource,
        $statusSource,
        $implementationSource,
        $nextHead,
        'lanes/libsqlite/tests/SQLiteUpstreamVeryquickShardCurrentSourceNext220Test.php',
        $output ?? libsqlite_suite_next220_output(),
        'current-source next220 veryquick-shard admission avoids accepted next155/157/159/161/164/166/167/169/171/172/173/174/175/176/177/178/181/184/187/190/194/200/202/209/212/213 suite evidence, exact-shard next148, queued suite156/160/162/163/165/168/170/182/183/185/186/211 manifest-conflict work, runner106/jsonvt104 rebase work, accepted batch196 behavior surfaces, and live B-tree/JSON/VFS/WAL/planner/PRAGMA/ATTACH/window/VDBE work',
        $expected,
        $snapshot
    );
}

$tests = [];

foreach (range(1, 80) as $case) {
    $tests[sprintf('current source next220 admits veryquick shard case %02d', $case)] = static function (TestRunner $t) use ($case): void {
        $record = libsqlite_suite_next220_record(libsqlite_suite_next220_rows($case));

        $t->same('current-source-next220-veryquick-shard-advanced', $record['status']);
        $t->same(true, $record['countable']);
        $t->same(624, $record['current_mapped']);
        $t->same(625, $record['next_mapped']);
        $t->same(1, $record['mapped_delta']);
        $t->same(96, $record['php_pass_delta']);
        $t->same(106859, $record['next_php_pass']);
        $t->same(['suite-upstream-veryquick-shard-current-source-next220'], $record['admitted_units']);
        $t->same(['batch196-current-source-anchor'], $record['preserved_units']);
        $t->contains(sprintf('veryquick-current-source-next220-%02d.test', $case), implode(',', $record['target_scripts']));
        $t->same(true, $record['counts_upstream_veryquick_shard_current_source_next220']);
        $t->same(false, $record['counts_upstream_veryquick_shard_current_source_next212']);
        $t->same(false, $record['counts_upstream_veryquick_shard_current_source_next209']);
        $t->same(false, $record['counts_upstream_exact_shard_runner_current_source_next148']);
        $t->same(false, $record['counts_upstream_runner_full_suite_countability_current_source_next116']);
        $t->same(false, $record['counts_release_parity']);
    };
}

$tests['current source next220 records authoritative launcher and source heads'] = static function (TestRunner $t): void {
    $record = libsqlite_suite_next220_record(libsqlite_suite_next220_rows(8));

    $t->same('57174af3d894b5b3ad8472548b456d5ce17271cb', $record['launcher_base_head']);
    $t->same('8a447f445e5d2fd32fc9fd463117f585d1416551', $record['dashboard_source_head']);
    $t->same('8a447f445e5d2fd32fc9fd463117f585d1416551', $record['status_source_head']);
    $t->same('8a447f445e5d2fd32fc9fd463117f585d1416551', $record['implementation_source_head']);
    $t->same(['suite-upstream-veryquick-shard-current-source-next220'], $record['artifact_source_heads']);
};

$tests['current source next220 records target scripts and tier counts'] = static function (TestRunner $t): void {
    $record = libsqlite_suite_next220_record(libsqlite_suite_next220_rows(13));

    $t->same(106776, $record['tests_total_delta']);
    $t->same(['accepted-batch196-anchor.test', 'testrunner.test', 'veryquick-current-source-next220-13.test'], $record['target_scripts']);
    $t->same('accepted-anchor', $record['tier_counts'][0]['tier']);
    $t->same(0, $record['tier_counts'][0]['admitted']);
    $t->same(1, $record['tier_counts'][0]['preserved']);
    $t->same('focused-veryquick-shard', $record['tier_counts'][1]['tier']);
    $t->same(1, $record['tier_counts'][1]['admitted']);
    $t->same(106776, $record['tier_counts'][1]['tests']);
};

$tests['current source next220 preserves already counted row without mapped inflation'] = static function (TestRunner $t): void {
    $rows = libsqlite_suite_next220_rows();
    $rows[0]['current_countable'] = true;
    $rows[0]['current_tests'] = 106764;

    $record = libsqlite_suite_next220_record($rows);

    $t->same('current-source-next220-veryquick-shard-preserved', $record['status']);
    $t->same(0, $record['mapped_delta']);
    $t->same(624, $record['next_mapped']);
    $t->same(['batch196-current-source-anchor', 'suite-upstream-veryquick-shard-current-source-next220'], $record['preserved_units']);
};

$tests['current source next220 blocks stale source provenance'] = static function (TestRunner $t): void {
    $record = libsqlite_suite_next220_record(
        libsqlite_suite_next220_rows(
            launcherBase: '0000000000000000000000000000000000000000',
            dashboardSource: '1111111111111111111111111111111111111111',
            statusSource: '2222222222222222222222222222222222222222',
            implementationSource: '3333333333333333333333333333333333333'
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

$tests['current source next220 blocks unguarded and non local artifacts'] = static function (TestRunner $t): void {
    $rows = libsqlite_suite_next220_rows();
    $rows[0]['artifact_path'] = '/tmp/next220.md';
    $rows[0]['runner_command'] = './testfixture ../libsqlite/test/testrunner.tcl all';
    $rows[0]['scripts'] = ['README.md'];

    $record = libsqlite_suite_next220_record($rows);

    $t->same('blocked', $record['status']);
    $evidence = implode('; ', array_column($record['blockers'], 'evidence'));
    $t->contains('artifact-path-not-lane-local', $evidence);
    $t->contains('guarded-runner-command-missing', $evidence);
    $t->contains('concrete-test-scripts-missing', $evidence);
};

$tests['current source next220 blocks non zero runner artifacts'] = static function (TestRunner $t): void {
    $rows = libsqlite_suite_next220_rows();
    $rows[0]['exit'] = 1;
    $rows[0]['errors'] = 2;

    $record = libsqlite_suite_next220_record($rows);

    $t->same('blocked', $record['status']);
    $t->same(0, $record['php_pass_delta']);
    $t->contains('runner-artifact-not-zero-error', implode('; ', array_column($record['blockers'], 'evidence')));
};

$tests['current source next220 blocks missing removed blocker classification'] = static function (TestRunner $t): void {
    $rows = libsqlite_suite_next220_rows();
    $rows[0]['removed_blocker'] = '';

    $record = libsqlite_suite_next220_record($rows);

    $t->same('blocked', $record['status']);
    $t->contains('removed-blocker-missing', implode('; ', array_column($record['blockers'], 'evidence')));
};

$tests['current source next220 blocks duplicate broad runner snapshot'] = static function (TestRunner $t): void {
    $record = libsqlite_suite_next220_record(
        libsqlite_suite_next220_rows(),
        snapshot: "12345 ./testfixture ../libsqlite/test/testrunner.tcl release\n"
    );

    $t->same('blocked', $record['status']);
    $t->same('blocked-active-runner', $record['active_runner_status']);
    $t->same(1, $record['active_runner_count']);
    $t->contains('duplicate-broad-runner-active', implode('; ', array_column($record['blockers'], 'id')));
};

$tests['current source next220 blocks focused php admission mismatch'] = static function (TestRunner $t): void {
    $record = libsqlite_suite_next220_record(
        libsqlite_suite_next220_rows(),
        output: libsqlite_suite_next220_output(assertions: 83)
    );

    $t->same('blocked', $record['status']);
    $t->contains('focused-php-pass-delta-mismatch', implode('; ', array_column($record['blockers'], 'id')));
};

$tests['current source next220 records exact focused php admission'] = static function (TestRunner $t): void {
    $record = libsqlite_suite_next220_record(libsqlite_suite_next220_rows());

    $t->same('admitted', $record['php_pass_admission']['status']);
    $t->same(96, $record['php_pass_admission']['assertion_delta']);
    $t->same(106859, $record['php_pass_admission']['next_php_pass']);
    $t->same(null, $record['php_pass_admission']['blocker']);
};

$tests['current source next220 blocks missing focused runner summary'] = static function (TestRunner $t): void {
    $record = libsqlite_suite_next220_record(
        libsqlite_suite_next220_rows(),
        output: 'Focused test run: 1 selected test files (root lock skipped)'
    );

    $t->same('blocked', $record['status']);
    $t->same('blocked', $record['php_pass_admission']['status']);
    $t->same(0, $record['php_pass_admission']['assertion_delta']);
    $t->contains('focused-php-pass-admission-blocked', implode('; ', array_column($record['blockers'], 'id')));
    $t->contains('focused-php-pass-delta-mismatch', implode('; ', array_column($record['blockers'], 'id')));
};

$tests['current source next220 carries dependency closure and non overlap notes'] = static function (TestRunner $t): void {
    $record = libsqlite_suite_next220_record(libsqlite_suite_next220_rows());

    $t->contains('no new support component needed', $record['dependency_closure']);
    $t->contains('202/209/212', $record['non_overlap_note']);
    $t->contains('release/all parity remains unclaimed', $record['next_gate']);
};

$tests['current source next220 records no release all parity claim'] = static function (TestRunner $t): void {
    $record = libsqlite_suite_next220_record(libsqlite_suite_next220_rows(31));

    $t->same(false, $record['counts_release_parity']);
    $t->contains('release/all parity remains unclaimed', $record['next_gate']);
};

$tests['current source next220 keeps admitted shard lane local'] = static function (TestRunner $t): void {
    $record = libsqlite_suite_next220_record(libsqlite_suite_next220_rows(32));

    $t->same(['suite-upstream-veryquick-shard-current-source-next220'], $record['admitted_units']);
    $t->same(['suite-upstream-veryquick-shard-current-source-next220'], $record['artifact_source_heads']);
};

$tests['current source next220 blocks missing next countability flag'] = static function (TestRunner $t): void {
    $rows = libsqlite_suite_next220_rows();
    $rows[0]['next_countable'] = false;

    $record = libsqlite_suite_next220_record($rows);

    $t->same('blocked', $record['status']);
    $t->same(0, $record['mapped_delta']);
    $t->contains('next-countability-not-admitted', implode('; ', array_column($record['blockers'], 'evidence')));
};

$tests['current source next220 keeps broad release parity unclaimed'] = static function (TestRunner $t): void {
    $record = libsqlite_suite_next220_record(libsqlite_suite_next220_rows(21));

    $t->same(false, $record['counts_release_parity']);
    $t->same(false, $record['counts_upstream_runner_rebase_gap_current_source_next122']);
    $t->contains('veryquick shard', $record['next_gate']);
};

return $tests;

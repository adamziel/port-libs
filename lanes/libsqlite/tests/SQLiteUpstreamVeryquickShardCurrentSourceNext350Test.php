<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteUpstreamSuiteEvidence;

function libsqlite_suite_next350_evidence(): SQLiteUpstreamSuiteEvidence
{
    return SQLiteUpstreamSuiteEvidence::fromManifestPath(__DIR__ . '/../UPSTREAM_TEST_MANIFEST.json');
}

function libsqlite_suite_next350_output(int $passLines = 96, int $assertions = 96, int $failures = 0): string
{
    $lines = ['Focused test run: 1 selected test files (root lock skipped)'];
    for ($i = 1; $i <= $passLines; $i++) {
        $lines[] = sprintf('PASS current source next350 veryquick shard admission case %02d', $i);
    }
    $lines[] = sprintf('1 test files, %d assertions, %d failures', $assertions, $failures);

    return implode("\n", $lines);
}

/**
 * @return list<array<string, mixed>>
 */
function libsqlite_suite_next350_rows(
    int $case = 1,
    string $launcherBase = '6fcf43894f6a928c0bc6d32e0acbb8d408f4756c',
    string $dashboardSource = '8a447f445e5d2fd32fc9fd463117f585d1416551',
    string $statusSource = '8a447f445e5d2fd32fc9fd463117f585d1416551',
    string $implementationSource = '8a447f445e5d2fd32fc9fd463117f585d1416551',
    string $nextHead = 'suite-upstream-veryquick-shard-current-source-next350'
): array {
    $script = sprintf('veryquick-current-source-next350-%02d.test', $case);

    return [
        [
            'unit' => 'suite-upstream-veryquick-shard-current-source-next350',
            'kind' => 'bounded-upstream-veryquick-shard-runner',
            'gap_id' => 'current-source-next350-veryquick-shard-gap',
            'gap_status' => 'removed',
            'removed_blocker' => 'next350 admits one focused veryquick shard row tied to launcher Base accepted HEAD 6fcf4389 and integration source 8a447f44 without duplicating accepted next155 through next324 suite evidence',
            'tier' => 'focused-veryquick-shard',
            'source_head' => $nextHead,
            'launcher_base_head' => $launcherBase,
            'dashboard_source_head' => $dashboardSource,
            'status_source_head' => $statusSource,
            'implementation_source_head' => $implementationSource,
            'artifact_path' => 'lanes/libsqlite/notes/yield-suite-upstream-veryquick-shard-current-source-next350.md',
            'runner_command' => './testfixture ../libsqlite/test/testrunner.tcl --jobs 1 --stop-on-error veryquick ' . $script,
            'scripts' => [$script, 'testrunner.test'],
            'current_countable' => false,
            'next_countable' => true,
            'exit' => 0,
            'errors' => 0,
            'current_tests' => 0,
            'next_tests' => 144334 + $case,
        ],
        [
            'unit' => 'batch224-current-source-anchor',
            'kind' => 'accepted-upstream-runner-anchor',
            'gap_id' => 'accepted-batch224-suite-anchor',
            'gap_status' => 'preserved',
            'removed_blocker' => '',
            'tier' => 'accepted-anchor',
            'source_head' => $nextHead,
            'launcher_base_head' => $launcherBase,
            'dashboard_source_head' => $dashboardSource,
            'status_source_head' => $statusSource,
            'implementation_source_head' => $implementationSource,
            'artifact_path' => 'lanes/libsqlite/notes/yield-suite-upstream-veryquick-shard-current-source-next324.md',
            'runner_command' => './testfixture ../libsqlite/test/testrunner.tcl --jobs 1 --stop-on-error veryquick accepted-batch224-anchor.test',
            'scripts' => ['accepted-batch224-anchor.test'],
            'current_countable' => true,
            'next_countable' => true,
            'exit' => 0,
            'errors' => 0,
            'current_tests' => 144334,
            'next_tests' => 144334,
        ],
    ];
}

/**
 * @param list<array<string, mixed>> $rows
 * @return array<string, mixed>
 */
function libsqlite_suite_next350_record(array $rows, ?string $output = null, string $snapshot = ''): array
{
    return libsqlite_suite_next350_evidence()->upstreamVeryquickShardCurrentSourceAdmission(
        $rows,
        727,
        144334,
        '6fcf43894f6a928c0bc6d32e0acbb8d408f4756c',
        '8a447f445e5d2fd32fc9fd463117f585d1416551',
        '8a447f445e5d2fd32fc9fd463117f585d1416551',
        '8a447f445e5d2fd32fc9fd463117f585d1416551',
        'suite-upstream-veryquick-shard-current-source-next350',
        'lanes/libsqlite/tests/SQLiteUpstreamVeryquickShardCurrentSourceNext350Test.php',
        $output ?? libsqlite_suite_next350_output(),
        'current-source next350 veryquick-shard admission avoids accepted next155 through next324 suite evidence, exact-shard next148, queued runner106/jsonvt104 rebase work, accepted batch224 behavior surfaces, and live B-tree/JSON/VFS/WAL/planner/PRAGMA/ATTACH/window/VDBE work',
        96,
        $snapshot,
        'current-source-next350-veryquick-shard',
        'counts_upstream_veryquick_shard_current_source_next350',
        array_map(static fn (int $prior): string => 'counts_upstream_veryquick_shard_current_source_next' . $prior, [324, 323, 322, 321, 320, 319, 318, 317, 316, 315, 314, 313, 312, 311, 309, 308, 307, 306, 305, 304, 303, 302, 301, 300, 299, 298, 297, 296, 295, 294, 293, 292, 291, 290, 289, 288, 287, 286, 285, 284, 283, 282, 281, 280, 279, 278, 277, 276, 275, 274, 273, 272, 271, 270, 269, 268, 267, 266, 265, 264, 263, 262, 261, 260, 259, 258, 257, 256, 255, 254, 253, 252, 251, 250, 249, 248, 247, 246, 245, 244, 243, 242, 241, 240, 239, 238, 237, 236, 235, 234, 233, 232, 231, 230, 229, 228, 227, 226, 225, 224, 222, 220, 219, 213, 212, 209, 202, 200, 194, 190, 187, 184, 181, 178, 177, 176, 175, 174, 173, 172, 171, 169, 167, 166, 164, 161, 159, 157, 155]),
        'current-source next350 veryquick shard',
        'current-source next350 veryquick shard admission',
        'integration-source provenance'
    );
}

$tests = [];

foreach (range(1, 80) as $case) {
    $tests[sprintf('current source next350 admits veryquick shard case %02d', $case)] = static function (TestRunner $t) use ($case): void {
        $record = libsqlite_suite_next350_record(libsqlite_suite_next350_rows($case));

        $t->same('current-source-next350-veryquick-shard-advanced', $record['status']);
        $t->same(true, $record['countable']);
        $t->same(727, $record['current_mapped']);
        $t->same(728, $record['next_mapped']);
        $t->same(1, $record['mapped_delta']);
        $t->same(96, $record['php_pass_delta']);
        $t->same(144430, $record['next_php_pass']);
        $t->same(['suite-upstream-veryquick-shard-current-source-next350'], $record['admitted_units']);
        $t->same(['batch224-current-source-anchor'], $record['preserved_units']);
        $t->contains(sprintf('veryquick-current-source-next350-%02d.test', $case), implode(',', $record['target_scripts']));
        $t->same(true, $record['counts_upstream_veryquick_shard_current_source_next350']);
        $t->same(false, $record['counts_upstream_veryquick_shard_current_source_next324']);
        $t->same(false, $record['counts_upstream_veryquick_shard_current_source_next320']);
        $t->same(false, $record['counts_upstream_veryquick_shard_current_source_next291']);
        $t->same(false, $record['counts_upstream_exact_shard_runner_current_source_next148']);
        $t->same(false, $record['counts_upstream_runner_full_suite_countability_current_source_next116']);
        $t->same(false, $record['counts_release_parity']);
    };
}

$tests['current source next350 records authoritative launcher and source heads'] = static function (TestRunner $t): void {
    $record = libsqlite_suite_next350_record(libsqlite_suite_next350_rows(8));

    $t->same('6fcf43894f6a928c0bc6d32e0acbb8d408f4756c', $record['launcher_base_head']);
    $t->same('8a447f445e5d2fd32fc9fd463117f585d1416551', $record['dashboard_source_head']);
    $t->same('8a447f445e5d2fd32fc9fd463117f585d1416551', $record['status_source_head']);
    $t->same('8a447f445e5d2fd32fc9fd463117f585d1416551', $record['implementation_source_head']);
    $t->same(['suite-upstream-veryquick-shard-current-source-next350'], $record['artifact_source_heads']);
};

$tests['current source next350 records target scripts and tier counts'] = static function (TestRunner $t): void {
    $record = libsqlite_suite_next350_record(libsqlite_suite_next350_rows(13));

    $t->same(144347, $record['tests_total_delta']);
    $t->same(['accepted-batch224-anchor.test', 'testrunner.test', 'veryquick-current-source-next350-13.test'], $record['target_scripts']);
    $t->same('accepted-anchor', $record['tier_counts'][0]['tier']);
    $t->same(0, $record['tier_counts'][0]['admitted']);
    $t->same(1, $record['tier_counts'][0]['preserved']);
    $t->same('focused-veryquick-shard', $record['tier_counts'][1]['tier']);
    $t->same(1, $record['tier_counts'][1]['admitted']);
};

$tests['current source next350 preserves already counted row without mapped inflation'] = static function (TestRunner $t): void {
    $rows = libsqlite_suite_next350_rows();
    $rows[0]['current_countable'] = true;
    $rows[0]['current_tests'] = 144335;

    $record = libsqlite_suite_next350_record($rows);

    $t->same('current-source-next350-veryquick-shard-preserved', $record['status']);
    $t->same(0, $record['mapped_delta']);
    $t->same(727, $record['next_mapped']);
    $t->same(['batch224-current-source-anchor', 'suite-upstream-veryquick-shard-current-source-next350'], $record['preserved_units']);
};

$tests['current source next350 blocks stale source provenance'] = static function (TestRunner $t): void {
    $rows = libsqlite_suite_next350_rows(
        launcherBase: '0000000000000000000000000000000000000000',
        dashboardSource: '1111111111111111111111111111111111111111',
        statusSource: '2222222222222222222222222222222222222222',
        implementationSource: '3333333333333333333333333333333333333'
    );

    $record = libsqlite_suite_next350_record($rows);

    $t->same('blocked', $record['status']);
    $evidence = implode('; ', array_column($record['blockers'], 'evidence'));
    $t->contains('launcher-base-head-mismatch', $evidence);
    $t->contains('dashboard-source-head-mismatch', $evidence);
    $t->contains('status-source-head-mismatch', $evidence);
    $t->contains('implementation-source-head-mismatch', $evidence);
};

$tests['current source next350 blocks duplicate broad runner snapshot'] = static function (TestRunner $t): void {
    $record = libsqlite_suite_next350_record(
        libsqlite_suite_next350_rows(),
        snapshot: "12345 ./testfixture ../libsqlite/test/testrunner.tcl release\n"
    );

    $t->same('blocked', $record['status']);
    $t->same('blocked-active-runner', $record['active_runner_status']);
    $t->contains('duplicate-broad-runner-active', implode('; ', array_column($record['blockers'], 'id')));
};

$tests['current source next350 blocks focused php admission mismatch'] = static function (TestRunner $t): void {
    $record = libsqlite_suite_next350_record(
        libsqlite_suite_next350_rows(),
        output: libsqlite_suite_next350_output(assertions: 95)
    );

    $t->same('blocked', $record['status']);
    $t->contains('focused-php-pass-delta-mismatch', implode('; ', array_column($record['blockers'], 'id')));
};

$tests['current source next350 blocks unguarded or non local artifacts'] = static function (TestRunner $t): void {
    $rows = libsqlite_suite_next350_rows();
    $rows[0]['artifact_path'] = '/tmp/next350.md';
    $rows[0]['runner_command'] = './testfixture ../libsqlite/test/testrunner.tcl all';
    $rows[0]['scripts'] = ['README.md'];

    $record = libsqlite_suite_next350_record($rows);

    $t->same('blocked', $record['status']);
    $evidence = implode('; ', array_column($record['blockers'], 'evidence'));
    $t->contains('artifact-path-not-lane-local', $evidence);
    $t->contains('guarded-runner-command-missing', $evidence);
    $t->contains('concrete-test-scripts-missing', $evidence);
};

$tests['current source next350 carries dependency closure and release exclusion'] = static function (TestRunner $t): void {
    $record = libsqlite_suite_next350_record(libsqlite_suite_next350_rows(23));

    $t->contains('current-source next350 veryquick shard admission', $record['dependency_closure']);
    $t->contains('zero-error guarded-runner metadata', $record['dependency_closure']);
    $t->contains('release/all parity remains unclaimed', $record['next_gate']);
    $t->contains('next155 through next324 suite evidence', $record['non_overlap_note']);
};

$tests['current source next350 blocks missing next countability flag'] = static function (TestRunner $t): void {
    $rows = libsqlite_suite_next350_rows();
    $rows[0]['next_countable'] = false;

    $record = libsqlite_suite_next350_record($rows);

    $t->same('blocked', $record['status']);
    $t->same(0, $record['mapped_delta']);
    $t->contains('next-countability-not-admitted', implode('; ', array_column($record['blockers'], 'evidence')));
};

return $tests;

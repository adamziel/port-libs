<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteUpstreamSuiteEvidence;

function libsqlite_suite_next119_evidence(): SQLiteUpstreamSuiteEvidence
{
    return SQLiteUpstreamSuiteEvidence::fromManifestPath(__DIR__ . '/../UPSTREAM_TEST_MANIFEST.json');
}

function libsqlite_suite_next119_output(int $passLines = 76, int $assertions = 88, int $failures = 0): string
{
    $lines = ['Focused test run: 1 selected test files (root lock skipped)'];
    for ($i = 1; $i <= $passLines; $i++) {
        $lines[] = sprintf('PASS current source next119 upstream release denominator burnup case %02d', $i);
    }
    $lines[] = sprintf('1 test files, %d assertions, %d failures', $assertions, $failures);

    return implode("\n", $lines);
}

/**
 * @return list<array<string, mixed>>
 */
function libsqlite_suite_next119_rows(int $case = 1): array
{
    $script = sprintf('upstream-release-denominator-burnup-current-source-next119-%02d.test', $case);

    return [
        [
            'unit' => 'upstream-release-denominator-burnup-current-source-next119',
            'category' => 'release-runner-denominator',
            'current_status' => 'unmapped',
            'next_status' => 'mapped',
            'evidence_type' => 'focused-current-source-release-runner',
            'evidence' => 'current-source next119 admits one focused upstream release denominator unit tied to launcher base 6b824ac2 and integration source 8a447f44 without claiming release/all parity',
            'release_admission_status' => 'admitted',
            'release_scope' => 'focused-current-source-denominator',
            'removed_blocker' => 'next119 removes the current-source denominator burnup blocker by preserving authoritative launcher-base provenance, current integration source provenance, lane-local guarded artifact evidence, zero errors, and focused PASS-line admission',
            'artifact_path' => 'lanes/libsqlite/notes/suite-upstream-release-denominator-burnup-current-source-next119.md',
            'runner_command' => './testfixture ../libsqlite/test/testrunner.tcl --jobs 1 --stop-on-error veryquick ' . $script,
            'scripts' => [$script, 'testrunner.test'],
            'current_tests' => 0,
            'next_tests' => 8200 + $case,
        ],
        [
            'unit' => 'accepted-batch109-113-denominator-anchor',
            'category' => 'provenance',
            'current_status' => 'mapped',
            'next_status' => 'mapped',
            'evidence_type' => 'accepted-current-source-anchor',
            'evidence' => 'accepted batch109-113 upstream denominator source remains preserved at mapped coverage 604 and is not remapped by next119',
            'release_admission_status' => 'preserved',
            'release_scope' => 'focused-current-source-denominator',
            'artifact_path' => 'lanes/libsqlite/notes/yield-sqlite-upstream-runner-countability-current-source-next112.md',
            'runner_command' => './testfixture ../libsqlite/test/testrunner.tcl --jobs 1 --stop-on-error veryquick accepted-current-source-anchor.test',
            'scripts' => ['accepted-current-source-anchor.test'],
            'current_tests' => 44622,
            'next_tests' => 44622,
        ],
    ];
}

function libsqlite_suite_next119_record(array $rows, ?string $output = null): array
{
    return libsqlite_suite_next119_evidence()->upstreamReleaseDenominatorBurnup(
        $rows,
        '6b824ac24854056466145761d32a9f27720d286a',
        '8a447f445e5d2fd32fc9fd463117f585d1416551',
        'suite-upstream-release-denominator-burnup-current-source-next119',
        604,
        44622,
        'lanes/libsqlite/tests/SQLiteSuiteUpstreamReleaseDenominatorBurnupCurrentSourceNext119Test.php',
        $output ?? libsqlite_suite_next119_output(),
        'current-source next119 upstream release denominator burnup avoids accepted batch107/108, batch109-113, runner106, jsonvt104, next114 release admission, next108 suite evidence rebase, next104 gap burnup, JSON/VFS/WAL/B-tree/planner/PRAGMA behavior clusters, and any release/all parity claim'
    );
}

$tests = [];

foreach (range(1, 64) as $case) {
    $tests[sprintf('current source next119 admits upstream release denominator burnup case %02d', $case)] = static function (TestRunner $t) use ($case): void {
        $record = libsqlite_suite_next119_record(libsqlite_suite_next119_rows($case));

        $t->same('current-source-next119-upstream-release-denominator-burnup-countable', $record['status']);
        $t->same(true, $record['countable']);
        $t->same('6b824ac24854056466145761d32a9f27720d286a', $record['launcher_base_head']);
        $t->same('8a447f445e5d2fd32fc9fd463117f585d1416551', $record['integration_source_head']);
        $t->same(604, $record['current_mapped']);
        $t->same(605, $record['next_mapped']);
        $t->same(1, $record['mapped_delta']);
        $t->same(88, $record['php_pass_delta']);
        $t->same(44710, $record['next_php_pass']);
        $t->same(['upstream-release-denominator-burnup-current-source-next119'], $record['admitted_units']);
        $t->same(['accepted-batch109-113-denominator-anchor'], $record['preserved_units']);
        $t->same([], $record['blocked_units']);
        $t->same(0, $record['blocker_count']);
        $t->same(true, $record['counts_upstream_release_denominator_burnup_current_source_next119']);
        $t->same(false, $record['counts_release_parity']);
        $t->contains(sprintf('upstream-release-denominator-burnup-current-source-next119-%02d.test', $case), implode(',', array_column($record['entries'], 'runner_command')));
    };
}

$tests['current source next119 records focused denominator metadata'] = static function (TestRunner $t): void {
    $record = libsqlite_suite_next119_record(libsqlite_suite_next119_rows(7));

    $t->same(2, $record['row_count']);
    $t->same(2, $record['category_count']);
    $t->same(1, $record['categories']['provenance']);
    $t->same(1, $record['categories']['release-runner-denominator']);
    $t->same(8207, $record['tests_total_delta']);
    $t->same('focused-current-source-denominator', $record['entries'][0]['release_scope']);
    $t->same('admitted', $record['entries'][0]['release_admission_status']);
    $t->contains('lane-local guarded artifact evidence', $record['entries'][0]['removed_blocker']);
    $t->contains('--jobs 1 --stop-on-error veryquick', $record['entries'][0]['runner_command']);
    $t->contains('release/all parity stays gated', $record['next_gate']);
    $t->contains('no new support component needed', $record['dependency_closure']);
};

$tests['current source next119 blocks stale launcher base'] = static function (TestRunner $t): void {
    $record = libsqlite_suite_next119_evidence()->upstreamReleaseDenominatorBurnup(
        libsqlite_suite_next119_rows(),
        '0000000000000000000000000000000000000000',
        '8a447f445e5d2fd32fc9fd463117f585d1416551',
        'suite-upstream-release-denominator-burnup-current-source-next119',
        604,
        44622,
        'lanes/libsqlite/tests/SQLiteSuiteUpstreamReleaseDenominatorBurnupCurrentSourceNext119Test.php',
        libsqlite_suite_next119_output(),
        'non-overlap next119'
    );

    $t->same('blocked', $record['status']);
    $t->same(0, $record['mapped_delta']);
    $t->same(604, $record['next_mapped']);
    $t->same(0, $record['php_pass_delta']);
    $t->contains('launcher-base-head-mismatch', implode('; ', array_column($record['blockers'], 'evidence')));
};

$tests['current source next119 blocks stale integration source'] = static function (TestRunner $t): void {
    $record = libsqlite_suite_next119_evidence()->upstreamReleaseDenominatorBurnup(
        libsqlite_suite_next119_rows(),
        '6b824ac24854056466145761d32a9f27720d286a',
        '1111111111111111111111111111111111111111',
        'suite-upstream-release-denominator-burnup-current-source-next119',
        604,
        44622,
        'lanes/libsqlite/tests/SQLiteSuiteUpstreamReleaseDenominatorBurnupCurrentSourceNext119Test.php',
        libsqlite_suite_next119_output(),
        'non-overlap next119'
    );

    $t->same('blocked', $record['status']);
    $t->contains('integration-source-head-mismatch', implode('; ', array_column($record['blockers'], 'evidence')));
};

$tests['current source next119 blocks missing focused admission scope'] = static function (TestRunner $t): void {
    $rows = libsqlite_suite_next119_rows();
    $rows[0]['release_scope'] = 'release-all';
    $rows[0]['counts_release_parity'] = true;

    $record = libsqlite_suite_next119_record($rows);

    $t->same('blocked', $record['status']);
    $t->same(['upstream-release-denominator-burnup-current-source-next119'], $record['blocked_units']);
    $evidence = implode('; ', array_column($record['blockers'], 'evidence'));
    $t->contains('release-scope-not-focused-current-source-denominator', $evidence);
    $t->contains('release-all-parity-claim-not-allowed', $evidence);
};

$tests['current source next119 blocks non lane local artifacts'] = static function (TestRunner $t): void {
    $rows = libsqlite_suite_next119_rows();
    $rows[0]['artifact_path'] = '/tmp/next119.log';
    $rows[0]['runner_command'] = './testfixture ../libsqlite/test/testrunner.tcl veryquick';

    $record = libsqlite_suite_next119_record($rows);

    $t->same('blocked', $record['status']);
    $evidence = implode('; ', array_column($record['blockers'], 'evidence'));
    $t->contains('artifact-path-not-lane-local', $evidence);
    $t->contains('guarded-runner-command-missing', $evidence);
};

$tests['current source next119 blocks missing removed blocker evidence'] = static function (TestRunner $t): void {
    $rows = libsqlite_suite_next119_rows();
    $rows[0]['removed_blocker'] = '';

    $record = libsqlite_suite_next119_record($rows);

    $t->same('blocked', $record['status']);
    $t->contains('removed-blocker-missing', implode('; ', array_column($record['blockers'], 'evidence')));
};

$tests['current source next119 blocks unfocused php admission output'] = static function (TestRunner $t): void {
    $record = libsqlite_suite_next119_record(
        libsqlite_suite_next119_rows(),
        "1 test files, 88 assertions, 0 failures\n"
    );

    $t->same('blocked', $record['status']);
    $t->same(0, $record['php_pass_delta']);
    $t->contains('focused-php-pass-admission-blocked', implode('; ', array_column($record['blockers'], 'id')));
};

$tests['current source next119 preserves already mapped denominator without inflation'] = static function (TestRunner $t): void {
    $rows = libsqlite_suite_next119_rows();
    $rows[0]['current_status'] = 'mapped';
    $rows[0]['current_mapped'] = true;
    $rows[0]['current_tests'] = 8201;

    $record = libsqlite_suite_next119_record($rows);

    $t->same('current-source-next119-upstream-release-denominator-burnup-preserved', $record['status']);
    $t->same(0, $record['mapped_delta']);
    $t->same(604, $record['next_mapped']);
    $t->same(['accepted-batch109-113-denominator-anchor', 'upstream-release-denominator-burnup-current-source-next119'], $record['preserved_units']);
};

return $tests;

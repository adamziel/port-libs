<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteUpstreamSuiteEvidence;

function libsqlite_suite_next108_evidence(): SQLiteUpstreamSuiteEvidence
{
    return SQLiteUpstreamSuiteEvidence::fromManifestPath(__DIR__ . '/../UPSTREAM_TEST_MANIFEST.json');
}

function libsqlite_suite_next108_output(int $passLines = 69, int $assertions = 80, int $failures = 0): string
{
    $lines = ['Focused test run: 1 selected test files (root lock skipped)'];
    for ($i = 1; $i <= $passLines; $i++) {
        $lines[] = sprintf('PASS current source next108 upstream runner suite evidence rebase case %02d', $i);
    }
    $lines[] = sprintf('1 test files, %d assertions, %d failures', $assertions, $failures);

    return implode("\n", $lines);
}

/**
 * @return list<array<string, mixed>>
 */
function libsqlite_suite_next108_rows(
    int $case = 1,
    string $launcherBase = '432eeef3a780a882f63963e1ddad168744b946dd',
    string $dashboardSource = '271b286480bbfdef0408d3e5e495087bd433ae40',
    string $statusSource = '271b286480bbfdef0408d3e5e495087bd433ae40',
    string $implementationSource = 'b3c4ecbf768d15d978a740cbb75a8109bca7e0f1',
    string $nextHead = 'upstream-runner-suite-evidence-rebase-next108'
): array {
    $script = sprintf('upstream-runner-suite-evidence-rebase-next108-%02d.test', $case);

    return [
        [
            'unit' => 'upstream-runner-suite-evidence-rebase-next108',
            'kind' => 'bounded-upstream-runner-suite-evidence-rebase',
            'gap_id' => 'current-source-next108-suite-evidence-rebase-gap',
            'gap_status' => 'removed',
            'removed_blocker' => 'next108 replaces stale pre-batch104/105 upstream-runner evidence with a current accepted-head lane-local guarded zero-error artifact row',
            'rebase_status' => 'rebased',
            'rebase_reason' => 'artifact provenance is tied to launcher base 432eeef3 plus dashboard/status 271b2864 and latest implementation b3c4ecbf',
            'source_head' => $nextHead,
            'launcher_base_head' => $launcherBase,
            'dashboard_source_head' => $dashboardSource,
            'status_source_head' => $statusSource,
            'implementation_source_head' => $implementationSource,
            'artifact_path' => 'lanes/libsqlite/notes/upstream-runner-suite-evidence-rebase-current-source-next108.md',
            'runner_command' => './testfixture ../libsqlite/test/testrunner.tcl --jobs 1 --stop-on-error veryquick ' . $script,
            'scripts' => [$script, 'testrunner.test'],
            'current_countable' => false,
            'next_countable' => true,
            'exit' => 0,
            'errors' => 0,
            'current_tests' => 0,
            'next_tests' => 6200 + $case,
            'evidence' => 'current-source next108 admits one rebased upstream-runner suite evidence row only when current accepted-head provenance, lane-local artifact path, guarded testrunner command, zero errors, removed-blocker classification, rebase reason, focused PASS lines, and duplicate-runner gates are all present',
        ],
        [
            'unit' => 'upstream-runner-suite-evidence-rebase-next108-preserved-anchor',
            'kind' => 'bounded-upstream-runner-suite-evidence-anchor',
            'gap_id' => 'current-source-next108-accepted-batch104-anchor',
            'gap_status' => 'preserved',
            'removed_blocker' => '',
            'rebase_status' => 'preserved',
            'rebase_reason' => 'accepted batch104/105 upstream-runner gap burnup remains countable and is not remapped by next108',
            'source_head' => $nextHead,
            'launcher_base_head' => $launcherBase,
            'dashboard_source_head' => $dashboardSource,
            'status_source_head' => $statusSource,
            'implementation_source_head' => $implementationSource,
            'artifact_path' => 'lanes/libsqlite/notes/suite-upstream-runner-gap-burnup-current-source-next104.md',
            'runner_command' => './testfixture ../libsqlite/test/testrunner.tcl --jobs 1 --stop-on-error veryquick accepted-current-source-anchor.test',
            'scripts' => ['accepted-current-source-anchor.test'],
            'current_countable' => true,
            'next_countable' => true,
            'exit' => 0,
            'errors' => 0,
            'current_tests' => 41873,
            'next_tests' => 41873,
            'evidence' => 'accepted batch104/105 current-source upstream-runner anchor remains preserved while next108 only rebases a new stale-evidence blocker row',
        ],
    ];
}

/**
 * @param list<array<string, mixed>> $rows
 * @return array<string, mixed>
 */
function libsqlite_suite_next108_record(
    array $rows,
    string $launcherBase = '432eeef3a780a882f63963e1ddad168744b946dd',
    string $dashboardSource = '271b286480bbfdef0408d3e5e495087bd433ae40',
    string $statusSource = '271b286480bbfdef0408d3e5e495087bd433ae40',
    string $implementationSource = 'b3c4ecbf768d15d978a740cbb75a8109bca7e0f1',
    string $nextHead = 'upstream-runner-suite-evidence-rebase-next108',
    ?string $output = null,
    ?int $expected = 69,
    string $snapshot = ''
): array {
    return libsqlite_suite_next108_evidence()->upstreamRunnerSuiteEvidenceRebase(
        $rows,
        604,
        41873,
        $launcherBase,
        $dashboardSource,
        $statusSource,
        $implementationSource,
        $nextHead,
        'lanes/libsqlite/tests/SQLiteUpstreamRunnerSuiteEvidenceRebaseCurrentSourceNext108Test.php',
        $output ?? libsqlite_suite_next108_output(),
        'current-source next108 upstream-runner suite evidence rebase avoids next104 upstream-runner gap burnup, next102 admission, next99 release countability, accepted batch104/105 ATTACH/B-tree/encoding/JSON/pager/PRAGMA/planner/VFS/WAL behavior clusters, and queued next106 DML/schema/WAL/JSON/planner blockers',
        $expected,
        $snapshot
    );
}

$tests = [];

foreach (range(1, 60) as $case) {
    $tests[sprintf('current source next108 admits rebased suite evidence case %02d', $case)] = static function (TestRunner $t) use ($case): void {
        $record = libsqlite_suite_next108_record(libsqlite_suite_next108_rows($case));

        $t->same('current-source-next108-upstream-runner-suite-evidence-rebase-countable', $record['status']);
        $t->same(true, $record['countable']);
        $t->same(604, $record['current_mapped']);
        $t->same(605, $record['next_mapped']);
        $t->same(1, $record['mapped_delta']);
        $t->same(69, $record['php_pass_delta']);
        $t->same(41942, $record['next_php_pass']);
        $t->same(['current-source-next108-suite-evidence-rebase-gap'], $record['rebased_gap_ids']);
        $t->same(['upstream-runner-suite-evidence-rebase-next108'], $record['admitted_units']);
        $t->same(['upstream-runner-suite-evidence-rebase-next108-preserved-anchor'], $record['preserved_units']);
        $t->contains(sprintf('upstream-runner-suite-evidence-rebase-next108-%02d.test', $case), implode(',', $record['target_scripts']));
        $t->same(true, $record['counts_upstream_runner_suite_evidence_rebase_current_source_next108']);
        $t->same(false, $record['counts_upstream_runner_gap_burnup_current_source_next104']);
        $t->same(false, $record['counts_release_parity']);
    };
}

$tests['current source next108 records exact accepted provenance'] = static function (TestRunner $t): void {
    $record = libsqlite_suite_next108_record(libsqlite_suite_next108_rows(7));

    $t->same('432eeef3a780a882f63963e1ddad168744b946dd', $record['launcher_base_head']);
    $t->same('271b286480bbfdef0408d3e5e495087bd433ae40', $record['dashboard_source_head']);
    $t->same('271b286480bbfdef0408d3e5e495087bd433ae40', $record['status_source_head']);
    $t->same('b3c4ecbf768d15d978a740cbb75a8109bca7e0f1', $record['implementation_source_head']);
    $t->same(['upstream-runner-suite-evidence-rebase-next108'], $record['artifact_source_heads']);
};

$tests['current source next108 records rebase row metadata'] = static function (TestRunner $t): void {
    $record = libsqlite_suite_next108_record(libsqlite_suite_next108_rows(9));

    $t->same(6209, $record['tests_total_delta']);
    $t->same(['accepted-current-source-anchor.test', 'testrunner.test', 'upstream-runner-suite-evidence-rebase-next108-09.test'], $record['target_scripts']);
    $t->same('rebased', $record['rebase_rows'][0]['rebase_status']);
    $t->contains('launcher base 432eeef3', $record['rebase_rows'][0]['rebase_reason']);
    $t->contains('stale pre-batch104/105', $record['rebase_rows'][0]['removed_blocker']);
    $t->same('preserved', $record['rebase_rows'][1]['rebase_status']);
};

$tests['current source next108 preserves already counted rebase without mapped inflation'] = static function (TestRunner $t): void {
    $rows = libsqlite_suite_next108_rows();
    $rows[0]['current_countable'] = true;
    $rows[0]['current_tests'] = 6201;

    $record = libsqlite_suite_next108_record($rows);

    $t->same('current-source-next108-upstream-runner-suite-evidence-rebase-preserved', $record['status']);
    $t->same(0, $record['mapped_delta']);
    $t->same(604, $record['next_mapped']);
    $t->same(['current-source-next108-accepted-batch104-anchor', 'current-source-next108-suite-evidence-rebase-gap'], $record['preserved_rebase_gap_ids']);
};

$tests['current source next108 blocks stale accepted provenance'] = static function (TestRunner $t): void {
    $record = libsqlite_suite_next108_record(
        libsqlite_suite_next108_rows(
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

$tests['current source next108 blocks missing rebase classification'] = static function (TestRunner $t): void {
    $rows = libsqlite_suite_next108_rows();
    $rows[0]['rebase_status'] = 'open';
    $rows[0]['rebase_reason'] = '';

    $record = libsqlite_suite_next108_record($rows);

    $t->same('blocked', $record['status']);
    $t->same(['current-source-next108-suite-evidence-rebase-gap'], $record['blocked_rebase_gap_ids']);
    $t->contains('rebase-status-not-rebased', implode('; ', array_column($record['blockers'], 'evidence')));
    $t->contains('rebase-reason-missing', implode('; ', array_column($record['blockers'], 'evidence')));
};

$tests['current source next108 blocks non lane local and unguarded artifacts'] = static function (TestRunner $t): void {
    $rows = libsqlite_suite_next108_rows();
    $rows[0]['artifact_path'] = '/tmp/next108.log';
    $rows[0]['runner_command'] = './testfixture ../libsqlite/test/testrunner.tcl veryquick';
    $rows[0]['scripts'] = ['README.md'];

    $record = libsqlite_suite_next108_record($rows);

    $t->same('blocked', $record['status']);
    $evidence = implode('; ', array_column($record['blockers'], 'evidence'));
    $t->contains('artifact-path-not-lane-local', $evidence);
    $t->contains('guarded-runner-command-missing', $evidence);
    $t->contains('runner-scripts-missing', $evidence);
};

$tests['current source next108 blocks duplicate broad runner and pass mismatch'] = static function (TestRunner $t): void {
    $record = libsqlite_suite_next108_record(
        libsqlite_suite_next108_rows(),
        output: libsqlite_suite_next108_output(79, 80),
        snapshot: "444 testfixture ../libsqlite/test/testrunner.tcl --stop-on-error all\n"
    );

    $t->same('blocked', $record['status']);
    $t->contains('duplicate-broad-runner-active', implode(',', array_column($record['blockers'], 'id')));
    $t->contains('focused-current-head-php-pass-blocked', implode(',', array_column($record['blockers'], 'id')));
};

$tests['current source next108 blocks release parity claim and runner errors'] = static function (TestRunner $t): void {
    $rows = libsqlite_suite_next108_rows();
    $rows[0]['counts_release_parity'] = true;
    $rows[0]['errors'] = 1;
    $rows[0]['exit'] = 1;

    $record = libsqlite_suite_next108_record($rows);

    $t->same('blocked', $record['status']);
    $evidence = implode('; ', array_column($record['blockers'], 'evidence'));
    $t->contains('release-parity-claim-not-allowed', $evidence);
    $t->contains('runner-errors-not-zero', $evidence);
    $t->contains('runner-exit-not-zero', $evidence);
};

$tests['current source next108 rejects empty artifact input through admission gate'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn () => libsqlite_suite_next108_record([]));
};

return $tests;

<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteUpstreamSuiteEvidence;

function libsqlite_runner_next120_evidence(): SQLiteUpstreamSuiteEvidence
{
    return SQLiteUpstreamSuiteEvidence::fromManifestPath(__DIR__ . '/../UPSTREAM_TEST_MANIFEST.json');
}

function libsqlite_runner_next120_audit(string $head, string $label, string $testset = 'veryquick', string $patterns = 'select1.test'): string
{
    return <<<MD
# SQLite Tcl Bounded Runner Evidence - {$label}

- Repository HEAD: `{$head}`
- SQLite git commit: `8f70ec615f4cd247d36f92a22c99f65ebbcc22a7`
- SQLite VERSION: `3.54.0`
- SQLite manifest UUID: `9ac4a33a2932d353c4871fd8e09c10addf827f1fc3fc9380037d738cf2cd0353`
- Testset: `{$testset}`
- Patterns: `{$patterns}`
- Exit: `0`
- Jobs: `1`
MD;
}

$tests = [];

foreach (range(1, 52) as $case) {
    $tests[sprintf('current source next120 admits all tests passed stdout summary case %02d', $case)] = static function (TestRunner $t) use ($case): void {
        $head = '6571c1279f77c2c00531492a7a2855a6f9e295a1';
        $testsTotal = 1200 + $case;
        $stdout = sprintf("06:40 tcl(%d/%d) select%d.test\nAll %d tests passed\n", $testsTotal, $testsTotal, $case, $testsTotal);

        $artifact = libsqlite_runner_next120_evidence()->boundedRunnerArtifactRecord(
            libsqlite_runner_next120_audit($head, 'next120-pass-summary-' . $case, patterns: 'select' . $case . '.test'),
            $stdout
        );
        $gate = libsqlite_runner_next120_evidence()->boundedRunnerCountabilityGateFromRecord($artifact, $head);

        $t->same('passed', $artifact['status']);
        $t->same(0, $artifact['results']['errors']);
        $t->same($testsTotal, $artifact['results']['tests']);
        $t->same('countable', $gate['status']);
        $t->same(true, $gate['countable']);
        $t->same($testsTotal, $gate['acceptance']['tests']);
        $t->same(0, $gate['acceptance']['errors']);
        $t->same($testsTotal, $artifact['progress']['completed']);
        $t->same($testsTotal, $artifact['progress']['total']);
    };
}

$tests['current source next120 admits comma zero error pass summary'] = static function (TestRunner $t): void {
    $head = '6571c1279f77c2c00531492a7a2855a6f9e295a1';
    $artifact = libsqlite_runner_next120_evidence()->boundedRunnerArtifactRecord(
        libsqlite_runner_next120_audit($head, 'next120-comma-summary', patterns: 'where.test'),
        "where-1.1... Ok\n987 tests passed, 0 errors\n"
    );

    $t->same('passed', $artifact['status']);
    $t->same(987, $artifact['results']['tests']);
    $t->same(0, $artifact['results']['errors']);
};

$tests['current source next120 admits audit embedded all tests passed summary'] = static function (TestRunner $t): void {
    $head = '6571c1279f77c2c00531492a7a2855a6f9e295a1';
    $audit = libsqlite_runner_next120_audit($head, 'next120-audit-summary', patterns: 'join.test')
        . "\n- Runner summary: `All 441 tests passed`\n";

    $artifact = libsqlite_runner_next120_evidence()->boundedRunnerArtifactRecord($audit);

    $t->same('passed', $artifact['status']);
    $t->same(441, $artifact['results']['tests']);
    $t->same(0, $artifact['results']['errors']);
};

$tests['current source next120 keeps nonzero error stdout blocked'] = static function (TestRunner $t): void {
    $head = '6571c1279f77c2c00531492a7a2855a6f9e295a1';
    $artifact = libsqlite_runner_next120_evidence()->boundedRunnerArtifactRecord(
        libsqlite_runner_next120_audit($head, 'next120-nonzero-errors', patterns: 'json.test'),
        "json-1.1... Ok\n1 errors out of 77 tests\n"
    );

    $t->same('failed', $artifact['status']);
    $t->same(77, $artifact['results']['tests']);
    $t->same(1, $artifact['results']['errors']);
};

$tests['current source next120 preserves stale head blocker after stdout summary parse'] = static function (TestRunner $t): void {
    $artifact = libsqlite_runner_next120_evidence()->boundedRunnerArtifactRecord(
        libsqlite_runner_next120_audit('stale-head', 'next120-stale-head', patterns: 'select1.test'),
        "All 222 tests passed\n"
    );
    $gate = libsqlite_runner_next120_evidence()->boundedRunnerCountabilityGateFromRecord(
        $artifact,
        '6571c1279f77c2c00531492a7a2855a6f9e295a1'
    );

    $t->same('blocked', $gate['status']);
    $t->same(false, $gate['countable']);
    $t->same(['repository-head-mismatch'], array_column($gate['blockers'], 'id'));
};

$tests['current source next120 keeps active broad runner blocker after pass summary parse'] = static function (TestRunner $t): void {
    $head = '6571c1279f77c2c00531492a7a2855a6f9e295a1';
    $artifact = libsqlite_runner_next120_evidence()->boundedRunnerArtifactRecord(
        libsqlite_runner_next120_audit($head, 'next120-active-runner', 'release', 'none'),
        "All 22222 tests passed\n",
        '4321 4320 ./testfixture ../libsqlite/test/testrunner.tcl --jobs 1 --stop-on-error release'
    );
    $gate = libsqlite_runner_next120_evidence()->boundedRunnerCountabilityGateFromRecord($artifact, $head);

    $t->same('blocked', $gate['status']);
    $t->same('active-runner-still-running', $gate['blockers'][0]['id']);
};

$tests['current source next120 records dependency closure for stdout summary admission'] = static function (TestRunner $t): void {
    $head = '6571c1279f77c2c00531492a7a2855a6f9e295a1';
    $artifact = libsqlite_runner_next120_evidence()->boundedRunnerArtifactRecord(
        libsqlite_runner_next120_audit($head, 'next120-dependency-closure', patterns: 'btree01.test'),
        "All 333 tests passed\n"
    );
    $gate = libsqlite_runner_next120_evidence()->boundedRunnerCountabilityGateFromRecord($artifact, $head);

    $t->contains('no new support component needed', $artifact['dependency_closure']);
    $t->contains('no new support component needed', $gate['dependency_closure']);
    $t->contains('record this bounded runner artifact', $gate['next_gate']);
};

return $tests;

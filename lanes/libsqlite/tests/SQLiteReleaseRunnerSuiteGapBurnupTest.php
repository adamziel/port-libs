<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteUpstreamSuiteEvidence;

function libsqlite_release_burnup_evidence(): SQLiteUpstreamSuiteEvidence
{
    return SQLiteUpstreamSuiteEvidence::fromManifestPath(__DIR__ . '/../UPSTREAM_TEST_MANIFEST.json');
}

function libsqlite_release_burnup_output(int $assertions = 65, int $failures = 0): string
{
    return "Focused test run: 1 selected test files (root lock skipped)\n"
        . "1 test files, {$assertions} assertions, {$failures} failures\n";
}

function libsqlite_release_burnup_rows(int $case): array
{
    return [
        [
            'id' => 'veryquick-current',
            'tier' => 'veryquick',
            'current_status' => 'passed',
            'next_status' => 'passed',
            'current_tests' => 329670,
            'next_tests' => 329670 + $case,
        ],
        [
            'id' => 'focused-jsonb-' . $case,
            'tier' => 'focused',
            'current_status' => $case % 4 === 0 ? 'missing' : 'passed',
            'next_status' => 'passed',
            'current_tests' => $case % 4 === 0 ? 0 : 900 + $case,
            'next_tests' => 1000 + $case,
        ],
        [
            'id' => 'release-all-' . $case,
            'tier' => 'release',
            'current_status' => 'missing',
            'next_status' => $case % 6 === 0 ? 'passed' : 'missing',
            'current_tests' => 0,
            'next_tests' => $case % 6 === 0 ? 24000 + $case : 0,
            'blocker' => $case % 6 === 0 ? '' : 'release/all artifact not yet admitted for next source',
        ],
    ];
}

$currentHead51 = '28488284c6b42b08db024e7e34c788f71b24a201';
$nextHead51 = 'current-next51-suite-gap-burnup';
$focusedPath51 = 'lanes/libsqlite/tests/SQLiteReleaseRunnerSuiteGapBurnupTest.php';
$nonOverlap51 = 'current-next51 suite gap burnup avoids accepted release-runner canonical map, suite progress current-next48, artifact directory evidence, and JSON/VFS/WAL/B-tree/SQL behavior clusters';

$tests = [];

for ($i = 1; $i <= 56; $i++) {
    $tests['current next51 suite gap burnup generated case ' . str_pad((string) $i, 2, '0', STR_PAD_LEFT)] = static function (TestRunner $t) use ($i, $currentHead51, $nextHead51, $focusedPath51, $nonOverlap51): void {
        $record = libsqlite_release_burnup_evidence()->releaseRunnerSuiteGapBurnup(
            libsqlite_release_burnup_rows($i),
            $currentHead51,
            $nextHead51,
            18565,
            $focusedPath51,
            libsqlite_release_burnup_output(65),
            $nonOverlap51,
            3
        );

        $expectedNextCountable = 2 + ($i % 6 === 0 ? 1 : 0);
        $t->same($i % 6 === 0 ? 'current-next51-suite-gap-burnup-complete' : 'current-next51-suite-gap-burnup-open', $record['status']);
        $t->same($expectedNextCountable, $record['next_countable_count']);
        $t->same(3 - $expectedNextCountable, $record['remaining_countable_gap']);
        $t->same($expectedNextCountable > 0, $record['counts_suite_burnup']);
        $t->same(65, $record['php_pass_delta']);
        $t->same(18630, $record['next_php_pass']);
        $t->same(3, count($record['entries']));
        $t->same($i % 6 === 0 ? 0 : 1, $record['open_blocker_count']);
        $t->true($record['next_countable_tests'] >= 330670 + $i, 'Expected current veryquick plus focused rows to burn up');
    };
}

$tests['current next51 suite gap burnup completes when all target rows count'] = static function (TestRunner $t) use ($currentHead51, $nextHead51, $focusedPath51, $nonOverlap51): void {
    $record = libsqlite_release_burnup_evidence()->releaseRunnerSuiteGapBurnup(
        [
            ['id' => 'veryquick', 'tier' => 'veryquick', 'current_status' => 'passed', 'next_status' => 'passed', 'current_tests' => 329670, 'next_tests' => 329670],
            ['id' => 'release', 'tier' => 'release', 'current_status' => 'missing', 'next_status' => 'passed', 'current_tests' => 0, 'next_tests' => 24000],
        ],
        $currentHead51,
        $nextHead51,
        18565,
        $focusedPath51,
        libsqlite_release_burnup_output(65),
        $nonOverlap51,
        2
    );

    $t->same('current-next51-suite-gap-burnup-complete', $record['status']);
    $t->same(0, $record['remaining_countable_gap']);
    $t->same(100.0, $record['burnup_percent']);
    $t->same([], $record['open_blockers']);
};

$tests['current next51 suite gap burnup leaves release blockers explicit'] = static function (TestRunner $t) use ($currentHead51, $nextHead51, $focusedPath51, $nonOverlap51): void {
    $record = libsqlite_release_burnup_evidence()->releaseRunnerSuiteGapBurnup(
        libsqlite_release_burnup_rows(5),
        $currentHead51,
        $nextHead51,
        18565,
        $focusedPath51,
        libsqlite_release_burnup_output(65),
        $nonOverlap51,
        3
    );

    $t->same([['id' => 'release-all-5', 'tier' => 'release', 'status' => 'missing', 'blocker' => 'release/all artifact not yet admitted for next source']], $record['open_blockers']);
    $t->contains('remaining release/all blockers explicit', $record['next_gate']);
};

$tests['current next51 suite gap burnup sorts tier summaries'] = static function (TestRunner $t) use ($currentHead51, $nextHead51, $focusedPath51, $nonOverlap51): void {
    $record = libsqlite_release_burnup_evidence()->releaseRunnerSuiteGapBurnup(
        libsqlite_release_burnup_rows(6),
        $currentHead51,
        $nextHead51,
        18565,
        $focusedPath51,
        libsqlite_release_burnup_output(65),
        $nonOverlap51,
        3
    );

    $t->same(['focused', 'release', 'veryquick'], array_column($record['tiers'], 'tier'));
    $t->same([1, 1, 1], array_column($record['tiers'], 'countable_rows'));
};

$tests['current next51 suite gap burnup blocks unfocused php output'] = static function (TestRunner $t) use ($currentHead51, $nextHead51, $focusedPath51, $nonOverlap51): void {
    $record = libsqlite_release_burnup_evidence()->releaseRunnerSuiteGapBurnup(
        libsqlite_release_burnup_rows(6),
        $currentHead51,
        $nextHead51,
        18565,
        $focusedPath51,
        "1 test files, 65 assertions, 0 failures\n",
        $nonOverlap51,
        3
    );

    $t->same('blocked', $record['status']);
    $t->same('blocked', $record['php_pass_admission']['status']);
    $t->same(0, $record['php_pass_delta']);
};

$tests['current next51 suite gap burnup blocks regressed suite rows'] = static function (TestRunner $t) use ($currentHead51, $nextHead51, $focusedPath51, $nonOverlap51): void {
    $record = libsqlite_release_burnup_evidence()->releaseRunnerSuiteGapBurnup(
        [
            ['id' => 'veryquick', 'tier' => 'veryquick', 'current_status' => 'passed', 'next_status' => 'failed', 'current_tests' => 329670, 'next_tests' => 0, 'blocker' => 'next runner failed'],
        ],
        $currentHead51,
        $nextHead51,
        18565,
        $focusedPath51,
        libsqlite_release_burnup_output(65),
        $nonOverlap51,
        1
    );

    $t->same('blocked', $record['status']);
    $t->same(1, $record['open_blocker_count']);
    $t->same('next runner failed', $record['open_blockers'][0]['blocker']);
};

$tests['current next51 suite gap burnup clamps negative test totals'] = static function (TestRunner $t) use ($currentHead51, $nextHead51, $focusedPath51, $nonOverlap51): void {
    $record = libsqlite_release_burnup_evidence()->releaseRunnerSuiteGapBurnup(
        [
            ['id' => 'focused', 'tier' => 'focused', 'current_status' => 'missing', 'next_status' => 'passed', 'next_tests' => -20],
        ],
        $currentHead51,
        $nextHead51,
        18565,
        $focusedPath51,
        libsqlite_release_burnup_output(65),
        $nonOverlap51,
        1
    );

    $t->same(0, $record['next_countable_tests']);
    $t->same(0, $record['entries'][0]['tests']);
};

$tests['current next51 suite gap burnup defaults target to row count'] = static function (TestRunner $t) use ($currentHead51, $nextHead51, $focusedPath51, $nonOverlap51): void {
    $record = libsqlite_release_burnup_evidence()->releaseRunnerSuiteGapBurnup(
        libsqlite_release_burnup_rows(1),
        $currentHead51,
        $nextHead51,
        18565,
        $focusedPath51,
        libsqlite_release_burnup_output(65),
        $nonOverlap51
    );

    $t->same(3, $record['target_countable']);
    $t->same(1, $record['remaining_countable_gap']);
};

$tests['current next51 suite gap burnup rejects missing heads'] = static function (TestRunner $t) use ($focusedPath51, $nonOverlap51): void {
    $t->throws(
        InvalidArgumentException::class,
        static fn () => libsqlite_release_burnup_evidence()->releaseRunnerSuiteGapBurnup(
            [['id' => 'release', 'next_status' => 'passed']],
            '',
            'next',
            18565,
            $focusedPath51,
            libsqlite_release_burnup_output(65),
            $nonOverlap51
        )
    );
};

$tests['current next51 suite gap burnup records dependency closure'] = static function (TestRunner $t) use ($currentHead51, $nextHead51, $focusedPath51, $nonOverlap51): void {
    $record = libsqlite_release_burnup_evidence()->releaseRunnerSuiteGapBurnup(
        libsqlite_release_burnup_rows(6),
        $currentHead51,
        $nextHead51,
        18565,
        $focusedPath51,
        libsqlite_release_burnup_output(65),
        $nonOverlap51,
        3
    );

    $t->contains('no new support component needed', $record['dependency_closure']);
    $t->contains('current-next51 suite gap burnup', $record['dependency_closure']);
};

return $tests;

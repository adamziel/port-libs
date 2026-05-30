<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteUpstreamSuiteEvidence;

function libsqlite_release_suite_burnup_evidence(): SQLiteUpstreamSuiteEvidence
{
    return SQLiteUpstreamSuiteEvidence::fromManifestPath(__DIR__ . '/../UPSTREAM_TEST_MANIFEST.json');
}

function libsqlite_release_suite_burnup_output(int $assertions = 50, int $failures = 0): string
{
    return "Focused test run: 1 selected test files (root lock skipped)\n"
        . "1 test files, {$assertions} assertions, {$failures} failures\n";
}

function libsqlite_release_suite_burnup_rows(int $case): array
{
    $rows = [
        [
            'id' => 'veryquick-baseline',
            'tier' => 'veryquick',
            'artifact' => 'current-veryquick',
            'current_status' => 'passed',
            'next_status' => 'passed',
            'current_tests' => 329670,
            'next_tests' => 329670 + $case,
        ],
        [
            'id' => 'focused-parser-subquery',
            'tier' => 'focused',
            'artifact' => 'next-focused-parser',
            'current_status' => $case % 4 === 0 ? 'missing' : 'passed',
            'next_status' => 'passed',
            'current_tests' => $case % 4 === 0 ? 0 : 610 + $case,
            'next_tests' => 740 + $case,
        ],
        [
            'id' => 'release-all',
            'tier' => 'release',
            'artifact' => 'next-release-all',
            'current_status' => 'missing',
            'next_status' => $case % 6 === 0 ? 'passed' : 'missing',
            'current_countable' => false,
            'next_countable' => $case % 6 === 0,
            'current_tests' => 0,
            'next_tests' => $case % 6 === 0 ? 18000 + $case : 0,
            'blocker' => $case % 6 === 0 ? '' : 'release/all artifact not countable for next accepted source',
        ],
    ];

    if ($case % 13 === 0) {
        $rows[] = [
            'id' => 'mptest',
            'tier' => 'mptest',
            'artifact' => 'next-mptest',
            'current_status' => 'passed',
            'next_status' => 'failed',
            'current_tests' => 900 + $case,
            'next_tests' => 0,
            'blockers' => ['next mptest artifact regressed', 'next mptest artifact regressed'],
        ];
    }

    return $rows;
}

$currentHead = '7f2e907cdacec133fa2b2281a4367be65c1ac7e0';
$nextHead = 'yield-sqlite-release-runner-upstream-suite-burnup-current-next50';
$focusedPath = 'lanes/libsqlite/tests/SQLiteReleaseRunnerSuiteBurnupTest.php';
$nonOverlap = 'current-next50 release-runner suite burnup avoids accepted batch48 suite progress, canonical artifact maps, selected-script gap proof, VFS/WAL/B-tree/JSON/SQL behavior clusters';

$tests = [];

for ($i = 1; $i <= 50; $i++) {
    $tests['current next50 suite burnup classifies focused row ' . $i] = static function (TestRunner $t) use ($i, $currentHead, $nextHead, $focusedPath, $nonOverlap): void {
        $record = libsqlite_release_suite_burnup_evidence()->releaseRunnerUpstreamSuiteBurnup(
            libsqlite_release_suite_burnup_rows($i),
            $currentHead,
            $nextHead,
            18565,
            $focusedPath,
            libsqlite_release_suite_burnup_output(50),
            $nonOverlap,
            40
        );

        $expectedCurrent = 1 + ($i % 4 === 0 ? 0 : 1) + ($i % 13 === 0 ? 1 : 0);
        $expectedNext = 2 + ($i % 6 === 0 ? 1 : 0);
        $t->same($expectedCurrent, $record['current_countable_count']);
        $t->same($expectedNext, $record['next_countable_count']);
        $t->same($expectedNext - $expectedCurrent, $record['countable_delta']);
        $t->same(50, $record['php_pass_delta']);
        $t->same(18615, $record['next_php_pass']);
        $t->same(3 + ($i % 13 === 0 ? 1 : 0), $record['row_count']);
        $t->same(40, $record['minimum_focused_assertions']);
        $t->contains('current-next50 suite burnup', $record['dependency_closure']);
    };
}

$tests['current next50 suite burnup advances a newly countable release artifact'] = static function (TestRunner $t) use ($currentHead, $nextHead, $focusedPath, $nonOverlap): void {
    $record = libsqlite_release_suite_burnup_evidence()->releaseRunnerUpstreamSuiteBurnup(
        [
            ['id' => 'veryquick', 'tier' => 'veryquick', 'artifact' => 'current', 'current_status' => 'passed', 'next_status' => 'passed', 'current_tests' => 329670, 'next_tests' => 329670],
            ['id' => 'release', 'tier' => 'release', 'artifact' => 'next-release', 'current_status' => 'missing', 'next_status' => 'passed', 'next_tests' => 45000],
        ],
        $currentHead,
        $nextHead,
        18565,
        $focusedPath,
        libsqlite_release_suite_burnup_output(50),
        $nonOverlap
    );

    $t->same('next50-suite-burnup-advanced', $record['status']);
    $t->same(true, $record['counts_next_suite_burnup']);
    $t->same(['release'], $record['advanced_ids']);
    $t->contains('publish only the countable', $record['next_gate']);
};

$tests['current next50 suite burnup preserves current countability with open blockers'] = static function (TestRunner $t) use ($currentHead, $nextHead, $focusedPath, $nonOverlap): void {
    $record = libsqlite_release_suite_burnup_evidence()->releaseRunnerUpstreamSuiteBurnup(
        libsqlite_release_suite_burnup_rows(1),
        $currentHead,
        $nextHead,
        18565,
        $focusedPath,
        libsqlite_release_suite_burnup_output(50),
        $nonOverlap
    );

    $t->same('current-suite-burnup-preserved-with-open-gaps', $record['status']);
    $t->same(true, $record['preserves_current_suite_burnup']);
    $t->same(['release-all'], $record['open_ids']);
    $t->same(1, $record['blocker_count']);
};

$tests['current next50 suite burnup preserves clean equal rows'] = static function (TestRunner $t) use ($currentHead, $nextHead, $focusedPath, $nonOverlap): void {
    $record = libsqlite_release_suite_burnup_evidence()->releaseRunnerUpstreamSuiteBurnup(
        [
            ['id' => 'veryquick', 'tier' => 'veryquick', 'artifact' => 'current', 'current_status' => 'passed', 'next_status' => 'passed', 'current_tests' => 329670, 'next_tests' => 329670],
            ['id' => 'focused-json', 'tier' => 'focused', 'artifact' => 'focused', 'current_status' => 'passed', 'next_status' => 'passed', 'current_tests' => 700, 'next_tests' => 701],
        ],
        $currentHead,
        $nextHead,
        18565,
        $focusedPath,
        libsqlite_release_suite_burnup_output(50),
        $nonOverlap
    );

    $t->same('next50-suite-burnup-preserved', $record['status']);
    $t->same(2, $record['preserved_count']);
    $t->same(0, $record['regressed_count']);
};

$tests['current next50 suite burnup blocks regressed artifacts'] = static function (TestRunner $t) use ($currentHead, $nextHead, $focusedPath, $nonOverlap): void {
    $record = libsqlite_release_suite_burnup_evidence()->releaseRunnerUpstreamSuiteBurnup(
        libsqlite_release_suite_burnup_rows(13),
        $currentHead,
        $nextHead,
        18565,
        $focusedPath,
        libsqlite_release_suite_burnup_output(50),
        $nonOverlap
    );

    $t->same('blocked', $record['status']);
    $t->true(in_array('mptest', $record['regressed_ids'], true), 'Expected mptest regression to block burnup');
    $t->same(['next mptest artifact regressed', 'next-countability-regressed'], $record['entries'][3]['blockers']);
};

$tests['current next50 suite burnup blocks under threshold php evidence'] = static function (TestRunner $t) use ($currentHead, $nextHead, $focusedPath, $nonOverlap): void {
    $record = libsqlite_release_suite_burnup_evidence()->releaseRunnerUpstreamSuiteBurnup(
        [
            ['id' => 'release', 'tier' => 'release', 'artifact' => 'next-release', 'current_status' => 'missing', 'next_status' => 'passed', 'next_tests' => 45000],
        ],
        $currentHead,
        $nextHead,
        18565,
        $focusedPath,
        libsqlite_release_suite_burnup_output(39),
        $nonOverlap,
        40
    );

    $t->same('blocked', $record['status']);
    $t->same(39, $record['php_pass_delta']);
    $t->true(in_array('focused-php-pass-delta-below-minimum', array_column($record['blockers'], 'id'), true), 'Expected under-threshold focused PHP blocker');
};

$tests['current next50 suite burnup blocks unfocused php output'] = static function (TestRunner $t) use ($currentHead, $nextHead, $focusedPath, $nonOverlap): void {
    $record = libsqlite_release_suite_burnup_evidence()->releaseRunnerUpstreamSuiteBurnup(
        [
            ['id' => 'release', 'tier' => 'release', 'artifact' => 'next-release', 'current_status' => 'missing', 'next_status' => 'passed', 'next_tests' => 45000],
        ],
        $currentHead,
        $nextHead,
        18565,
        $focusedPath,
        "1 test files, 50 assertions, 0 failures\n",
        $nonOverlap
    );

    $t->same('blocked', $record['status']);
    $t->same(0, $record['php_pass_delta']);
    $t->same('blocked', $record['php_pass_admission']['status']);
};

$tests['current next50 suite burnup records artifact labels deterministically'] = static function (TestRunner $t) use ($currentHead, $nextHead, $focusedPath, $nonOverlap): void {
    $record = libsqlite_release_suite_burnup_evidence()->releaseRunnerUpstreamSuiteBurnup(
        libsqlite_release_suite_burnup_rows(6),
        $currentHead,
        $nextHead,
        18565,
        $focusedPath,
        libsqlite_release_suite_burnup_output(50),
        $nonOverlap
    );

    $t->same([
        'current-veryquick',
        'next-focused-parser',
        'next-release-all',
    ], $record['artifact_labels']);
    $t->same(3, $record['artifact_count']);
    $t->true($record['tests_total_delta'] > 0, 'Expected next accepted source tests to increase');
};

$tests['current next50 suite burnup aggregates tiers in stable order'] = static function (TestRunner $t) use ($currentHead, $nextHead, $focusedPath, $nonOverlap): void {
    $record = libsqlite_release_suite_burnup_evidence()->releaseRunnerUpstreamSuiteBurnup(
        libsqlite_release_suite_burnup_rows(6),
        $currentHead,
        $nextHead,
        18565,
        $focusedPath,
        libsqlite_release_suite_burnup_output(50),
        $nonOverlap
    );

    $t->same(['focused', 'release', 'veryquick'], array_column($record['tiers'], 'tier'));
    $t->same(1, $record['tiers'][1]['advanced']);
    $t->same(18006, $record['tiers'][1]['next_tests']);
};

$tests['current next50 suite burnup preserves non overlap through admission'] = static function (TestRunner $t) use ($currentHead, $nextHead, $focusedPath, $nonOverlap): void {
    $record = libsqlite_release_suite_burnup_evidence()->releaseRunnerUpstreamSuiteBurnup(
        [
            ['id' => 'release', 'tier' => 'release', 'artifact' => 'next-release', 'current_status' => 'missing', 'next_status' => 'passed', 'next_tests' => 45000],
        ],
        $currentHead,
        $nextHead,
        18565,
        $focusedPath,
        libsqlite_release_suite_burnup_output(50),
        $nonOverlap
    );

    $t->contains('avoids accepted batch48 suite progress', $record['php_pass_admission']['non_overlap_note']);
};

$tests['current next50 suite burnup protects negative test totals'] = static function (TestRunner $t) use ($currentHead, $nextHead, $focusedPath, $nonOverlap): void {
    $record = libsqlite_release_suite_burnup_evidence()->releaseRunnerUpstreamSuiteBurnup(
        [
            ['id' => 'release', 'tier' => 'release', 'artifact' => 'next-release', 'current_status' => 'missing', 'next_status' => 'passed', 'current_tests' => -5, 'next_tests' => -10],
        ],
        $currentHead,
        $nextHead,
        18565,
        $focusedPath,
        libsqlite_release_suite_burnup_output(50),
        $nonOverlap
    );

    $t->same(0, $record['next_tests_total']);
    $t->same(0, $record['entries'][0]['next_tests']);
};

$tests['current next50 suite burnup reports invalid rows'] = static function (TestRunner $t) use ($currentHead, $nextHead, $focusedPath, $nonOverlap): void {
    $record = libsqlite_release_suite_burnup_evidence()->releaseRunnerUpstreamSuiteBurnup(
        [
            'release' => ['id' => 'release', 'tier' => 'release', 'artifact' => 'next-release', 'current_status' => 'missing', 'next_status' => 'passed', 'next_tests' => 45000],
            'invalid' => 'not-a-row',
        ],
        $currentHead,
        $nextHead,
        18565,
        $focusedPath,
        libsqlite_release_suite_burnup_output(50),
        $nonOverlap
    );

    $t->same('blocked', $record['status']);
    $t->true(in_array('suite-row-invalid', array_column($record['blockers'], 'id'), true), 'Expected invalid suite row blocker');
};

$tests['current next50 suite burnup rejects missing heads'] = static function (TestRunner $t) use ($focusedPath, $nonOverlap): void {
    $t->throws(
        InvalidArgumentException::class,
        static fn () => libsqlite_release_suite_burnup_evidence()->releaseRunnerUpstreamSuiteBurnup(
            [['id' => 'release', 'next_status' => 'passed']],
            '',
            'next',
            18565,
            $focusedPath,
            libsqlite_release_suite_burnup_output(50),
            $nonOverlap
        )
    );
};

$tests['current next50 suite burnup rejects empty rows'] = static function (TestRunner $t) use ($currentHead, $nextHead, $focusedPath, $nonOverlap): void {
    $t->throws(
        InvalidArgumentException::class,
        static fn () => libsqlite_release_suite_burnup_evidence()->releaseRunnerUpstreamSuiteBurnup(
            [],
            $currentHead,
            $nextHead,
            18565,
            $focusedPath,
            libsqlite_release_suite_burnup_output(50),
            $nonOverlap
        )
    );
};

$tests['current next50 suite burnup rejects zero minimum'] = static function (TestRunner $t) use ($currentHead, $nextHead, $focusedPath, $nonOverlap): void {
    $t->throws(
        InvalidArgumentException::class,
        static fn () => libsqlite_release_suite_burnup_evidence()->releaseRunnerUpstreamSuiteBurnup(
            [['id' => 'release', 'next_status' => 'passed']],
            $currentHead,
            $nextHead,
            18565,
            $focusedPath,
            libsqlite_release_suite_burnup_output(50),
            $nonOverlap,
            0
        )
    );
};

$tests['current next50 suite burnup records dependency closure'] = static function (TestRunner $t) use ($currentHead, $nextHead, $focusedPath, $nonOverlap): void {
    $record = libsqlite_release_suite_burnup_evidence()->releaseRunnerUpstreamSuiteBurnup(
        [
            ['id' => 'veryquick', 'tier' => 'veryquick', 'artifact' => 'current', 'current_status' => 'passed', 'next_status' => 'passed', 'current_tests' => 329670, 'next_tests' => 329670],
        ],
        $currentHead,
        $nextHead,
        18565,
        $focusedPath,
        libsqlite_release_suite_burnup_output(50),
        $nonOverlap
    );

    $t->contains('no new support component needed', $record['dependency_closure']);
};

return $tests;

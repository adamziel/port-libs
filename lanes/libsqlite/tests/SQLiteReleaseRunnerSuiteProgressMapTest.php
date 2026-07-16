<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteUpstreamSuiteEvidence;

function libsqlite_release_progress_evidence(): SQLiteUpstreamSuiteEvidence
{
    return SQLiteUpstreamSuiteEvidence::fromManifestPath(__DIR__ . '/../UPSTREAM_TEST_MANIFEST.json');
}

function libsqlite_release_progress_output(int $assertions = 60, int $failures = 0): string
{
    return "Focused test run: 1 selected test files (root lock skipped)\n"
        . "1 test files, {$assertions} assertions, {$failures} failures\n";
}

function libsqlite_release_progress_rows(int $case): array
{
    $rows = [
        [
            'id' => 'veryquick-baseline',
            'tier' => 'veryquick',
            'current_status' => 'passed',
            'next_status' => 'passed',
            'current_tests' => 329670,
            'next_tests' => 329670 + $case,
        ],
        [
            'id' => 'focused-json-window',
            'tier' => 'focused',
            'current_status' => $case % 3 === 0 ? 'missing' : 'passed',
            'next_status' => 'passed',
            'current_tests' => $case % 3 === 0 ? 0 : 640 + $case,
            'next_tests' => 700 + $case,
        ],
        [
            'id' => 'release-all',
            'tier' => 'release',
            'current_status' => 'missing',
            'next_status' => $case % 5 === 0 ? 'passed' : 'missing',
            'current_countable' => false,
            'next_countable' => $case % 5 === 0,
            'current_tests' => 0,
            'next_tests' => $case % 5 === 0 ? 12000 + $case : 0,
            'blocker' => $case % 5 === 0 ? '' : 'release all artifact not present for next source',
        ],
    ];

    if ($case % 11 === 0) {
        $rows[] = [
            'id' => 'mptest',
            'tier' => 'mptest',
            'current_status' => 'passed',
            'next_status' => 'failed',
            'current_tests' => 900 + $case,
            'next_tests' => 0,
            'blockers' => ['next countability regressed in supplied artifact'],
        ];
    }

    return $rows;
}

$currentHead48 = '28488284c6b42b08db024e7e34c788f71b24a201';
$nextHead48 = 'current-next48-suite-progress';
$focusedPath48 = 'lanes/libsqlite/tests/SQLiteReleaseRunnerSuiteProgressMapTest.php';
$nonOverlap48 = 'current-next48 suite progress map avoids accepted release-runner canonical map, gap proof, suite ledger, artifact directory evidence, JSON/VFS/WAL/B-tree/SQL execution clusters';

$tests = [];

for ($i = 1; $i <= 44; $i++) {
    $tests['current next48 suite progress maps focused case ' . $i] = static function (TestRunner $t) use ($i, $currentHead48, $nextHead48, $focusedPath48, $nonOverlap48): void {
        $record = libsqlite_release_progress_evidence()->releaseRunnerSuiteProgressMap(
            libsqlite_release_progress_rows($i),
            $currentHead48,
            $nextHead48,
            17373,
            $focusedPath48,
            libsqlite_release_progress_output(60),
            $nonOverlap48
        );

        $expectedNext = 2 + ($i % 5 === 0 ? 1 : 0);
        $expectedCurrent = 1 + ($i % 3 === 0 ? 0 : 1) + ($i % 11 === 0 ? 1 : 0);
        $t->same($expectedCurrent, $record['current_countable_count']);
        $t->same($expectedNext, $record['next_countable_count']);
        $t->same($expectedNext - $expectedCurrent, $record['countable_delta']);
        $t->same(60, $record['php_pass_delta']);
        $t->same(17433, $record['next_php_pass']);
        $t->same(3 + ($i % 11 === 0 ? 1 : 0), $record['row_count']);
        $t->same(true, $record['php_pass_admission']['status'] === 'admitted');
        $t->contains('current-next48 suite progress map', $record['dependency_closure']);
    };
}

$tests['current next48 suite progress advances clean next release rows'] = static function (TestRunner $t) use ($currentHead48, $nextHead48, $focusedPath48, $nonOverlap48): void {
    $record = libsqlite_release_progress_evidence()->releaseRunnerSuiteProgressMap(
        [
            ['id' => 'veryquick', 'tier' => 'veryquick', 'current_status' => 'passed', 'next_status' => 'passed', 'current_tests' => 329670, 'next_tests' => 329670],
            ['id' => 'release', 'tier' => 'release', 'current_status' => 'missing', 'next_status' => 'passed', 'current_tests' => 0, 'next_tests' => 25000],
        ],
        $currentHead48,
        $nextHead48,
        17373,
        $focusedPath48,
        libsqlite_release_progress_output(60),
        $nonOverlap48
    );

    $t->same('next48-suite-progress-advanced', $record['status']);
    $t->same(true, $record['counts_next_suite_progress']);
    $t->same(['release'], $record['advanced_ids']);
};

$tests['current next48 suite progress preserves clean current rows'] = static function (TestRunner $t) use ($currentHead48, $nextHead48, $focusedPath48, $nonOverlap48): void {
    $record = libsqlite_release_progress_evidence()->releaseRunnerSuiteProgressMap(
        [
            ['id' => 'veryquick', 'tier' => 'veryquick', 'current_status' => 'passed', 'next_status' => 'passed', 'current_tests' => 329670, 'next_tests' => 329670],
            ['id' => 'focused-json', 'tier' => 'focused', 'current_status' => 'passed', 'next_status' => 'passed', 'current_tests' => 700, 'next_tests' => 700],
        ],
        $currentHead48,
        $nextHead48,
        17373,
        $focusedPath48,
        libsqlite_release_progress_output(60),
        $nonOverlap48
    );

    $t->same('next48-suite-progress-preserved', $record['status']);
    $t->same(true, $record['preserves_current_suite_progress']);
    $t->same(2, $record['preserved_count']);
};

$tests['current next48 suite progress reports open release blockers'] = static function (TestRunner $t) use ($currentHead48, $nextHead48, $focusedPath48, $nonOverlap48): void {
    $record = libsqlite_release_progress_evidence()->releaseRunnerSuiteProgressMap(
        libsqlite_release_progress_rows(1),
        $currentHead48,
        $nextHead48,
        17373,
        $focusedPath48,
        libsqlite_release_progress_output(60),
        $nonOverlap48
    );

    $t->same('current-suite-progress-preserved-with-open-gaps', $record['status']);
    $t->same(1, $record['blocker_count']);
    $t->same(['release-all'], $record['open_ids']);
};

$tests['current next48 suite progress blocks regressed current rows'] = static function (TestRunner $t) use ($currentHead48, $nextHead48, $focusedPath48, $nonOverlap48): void {
    $record = libsqlite_release_progress_evidence()->releaseRunnerSuiteProgressMap(
        libsqlite_release_progress_rows(11),
        $currentHead48,
        $nextHead48,
        17373,
        $focusedPath48,
        libsqlite_release_progress_output(60),
        $nonOverlap48
    );

    $t->same('blocked', $record['status']);
    $t->true(in_array('mptest', $record['regressed_ids'], true), 'Expected mptest regression');
    $t->true($record['blocker_count'] >= 1, 'Expected regression blocker');
};

$tests['current next48 suite progress blocks unfocused php output'] = static function (TestRunner $t) use ($currentHead48, $nextHead48, $focusedPath48, $nonOverlap48): void {
    $record = libsqlite_release_progress_evidence()->releaseRunnerSuiteProgressMap(
        [
            ['id' => 'release', 'tier' => 'release', 'current_status' => 'missing', 'next_status' => 'passed', 'next_tests' => 25000],
        ],
        $currentHead48,
        $nextHead48,
        17373,
        $focusedPath48,
        "1 test files, 60 assertions, 0 failures\n",
        $nonOverlap48
    );

    $t->same('blocked', $record['status']);
    $t->same(0, $record['php_pass_delta']);
    $t->same('blocked', $record['php_pass_admission']['status']);
};

$tests['current next48 suite progress rejects missing heads'] = static function (TestRunner $t) use ($focusedPath48, $nonOverlap48): void {
    $t->throws(
        InvalidArgumentException::class,
        static fn () => libsqlite_release_progress_evidence()->releaseRunnerSuiteProgressMap(
            [['id' => 'release', 'next_status' => 'passed']],
            '',
            'next',
            17373,
            $focusedPath48,
            libsqlite_release_progress_output(60),
            $nonOverlap48
        )
    );
};

$tests['current next48 suite progress rejects empty rows'] = static function (TestRunner $t) use ($currentHead48, $nextHead48, $focusedPath48, $nonOverlap48): void {
    $t->throws(
        InvalidArgumentException::class,
        static fn () => libsqlite_release_progress_evidence()->releaseRunnerSuiteProgressMap(
            [],
            $currentHead48,
            $nextHead48,
            17373,
            $focusedPath48,
            libsqlite_release_progress_output(60),
            $nonOverlap48
        )
    );
};

$tests['current next48 suite progress reports invalid rows as blockers'] = static function (TestRunner $t) use ($currentHead48, $nextHead48, $focusedPath48, $nonOverlap48): void {
    $record = libsqlite_release_progress_evidence()->releaseRunnerSuiteProgressMap(
        [
            'release' => ['id' => 'release', 'tier' => 'release', 'current_status' => 'missing', 'next_status' => 'passed', 'next_tests' => 25000],
            'invalid' => 'not-a-row',
        ],
        $currentHead48,
        $nextHead48,
        17373,
        $focusedPath48,
        libsqlite_release_progress_output(60),
        $nonOverlap48
    );

    $t->same('blocked', $record['status']);
    $t->true(in_array('suite-row-invalid', array_column($record['blockers'], 'id'), true), 'Expected invalid row blocker');
};

$tests['current next48 suite progress aggregates tiers deterministically'] = static function (TestRunner $t) use ($currentHead48, $nextHead48, $focusedPath48, $nonOverlap48): void {
    $record = libsqlite_release_progress_evidence()->releaseRunnerSuiteProgressMap(
        libsqlite_release_progress_rows(5),
        $currentHead48,
        $nextHead48,
        17373,
        $focusedPath48,
        libsqlite_release_progress_output(60),
        $nonOverlap48
    );

    $t->same(['focused', 'release', 'veryquick'], array_column($record['tiers'], 'tier'));
    $t->true($record['tests_total_delta'] > 0, 'Expected next tests to increase');
    $t->contains('publish only the advanced', $record['next_gate']);
};

$tests['current next48 suite progress keeps duplicate blocker values unique'] = static function (TestRunner $t) use ($currentHead48, $nextHead48, $focusedPath48, $nonOverlap48): void {
    $record = libsqlite_release_progress_evidence()->releaseRunnerSuiteProgressMap(
        [
            [
                'id' => 'release',
                'tier' => 'release',
                'current_status' => 'missing',
                'next_status' => 'missing',
                'blocker' => 'runner missing',
                'blockers' => ['runner missing', 'runner missing'],
            ],
        ],
        $currentHead48,
        $nextHead48,
        17373,
        $focusedPath48,
        libsqlite_release_progress_output(60),
        $nonOverlap48
    );

    $t->same(['runner missing'], $record['entries'][0]['blockers']);
};

$tests['current next48 suite progress records non overlap through php admission'] = static function (TestRunner $t) use ($currentHead48, $nextHead48, $focusedPath48, $nonOverlap48): void {
    $record = libsqlite_release_progress_evidence()->releaseRunnerSuiteProgressMap(
        [
            ['id' => 'release', 'tier' => 'release', 'current_status' => 'missing', 'next_status' => 'passed', 'next_tests' => 25000],
        ],
        $currentHead48,
        $nextHead48,
        17373,
        $focusedPath48,
        libsqlite_release_progress_output(60),
        $nonOverlap48
    );

    $t->contains('suite progress map avoids', $record['php_pass_admission']['non_overlap_note']);
};

$tests['current next48 suite progress protects negative test totals'] = static function (TestRunner $t) use ($currentHead48, $nextHead48, $focusedPath48, $nonOverlap48): void {
    $record = libsqlite_release_progress_evidence()->releaseRunnerSuiteProgressMap(
        [
            ['id' => 'release', 'tier' => 'release', 'current_status' => 'missing', 'next_status' => 'passed', 'current_tests' => -5, 'next_tests' => -10],
        ],
        $currentHead48,
        $nextHead48,
        17373,
        $focusedPath48,
        libsqlite_release_progress_output(60),
        $nonOverlap48
    );

    $t->same(0, $record['next_tests_total']);
    $t->same(0, $record['entries'][0]['next_tests']);
};

$tests['current next48 suite progress records dependency closure'] = static function (TestRunner $t) use ($currentHead48, $nextHead48, $focusedPath48, $nonOverlap48): void {
    $record = libsqlite_release_progress_evidence()->releaseRunnerSuiteProgressMap(
        [
            ['id' => 'veryquick', 'tier' => 'veryquick', 'current_status' => 'passed', 'next_status' => 'passed', 'current_tests' => 329670, 'next_tests' => 329670],
        ],
        $currentHead48,
        $nextHead48,
        17373,
        $focusedPath48,
        libsqlite_release_progress_output(60),
        $nonOverlap48
    );

    $t->contains('no new support component needed', $record['dependency_closure']);
};

return $tests;

<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteUpstreamSuiteEvidence;

function libsqlite_release_denominator_evidence(): SQLiteUpstreamSuiteEvidence
{
    return SQLiteUpstreamSuiteEvidence::fromManifestPath(__DIR__ . '/../UPSTREAM_TEST_MANIFEST.json');
}

function libsqlite_release_denominator_output(int $assertions = 70, int $failures = 0): string
{
    return "Focused test run: 1 selected test files (root lock skipped)\n"
        . "1 test files, {$assertions} assertions, {$failures} failures\n";
}

function libsqlite_release_denominator_rows(int $case): array
{
    $rows = [
        [
            'id' => 'accepted-veryquick-baseline',
            'family' => 'runner-baseline',
            'units' => 1235,
            'current_status' => 'mapped',
            'next_status' => 'mapped',
            'current_runner_countable' => true,
            'next_runner_countable' => true,
        ],
        [
            'id' => 'focused-jsonb-planner-' . $case,
            'family' => 'focused-subset',
            'units' => 2 + ($case % 4),
            'current_status' => $case % 3 === 0 ? 'missing' : 'mapped',
            'next_status' => 'mapped',
            'current_runner_countable' => $case % 3 !== 0,
            'next_runner_countable' => true,
        ],
        [
            'id' => 'release-all-next-source-' . $case,
            'family' => 'release-tier',
            'units' => 1,
            'current_status' => 'missing',
            'next_status' => $case % 5 === 0 ? 'mapped' : 'missing',
            'next_runner_countable' => $case % 5 === 0,
            'blocker' => $case % 5 === 0 ? '' : 'next accepted release/all artifact not present',
        ],
    ];

    if ($case % 11 === 0) {
        $rows[] = [
            'id' => 'mptest-regression-' . $case,
            'family' => 'stress-tier',
            'units' => 6,
            'current_status' => 'mapped',
            'next_status' => 'missing',
            'current_runner_countable' => true,
            'blockers' => ['next mptest artifact missing after current accepted baseline'],
        ];
    }

    return $rows;
}

$currentHead54 = '28488284c6b42b08db024e7e34c788f71b24a201';
$nextHead54 = 'current-next54-suite-denominator-burnup';
$focusedPath54 = 'lanes/libsqlite/tests/SQLiteReleaseRunnerSuiteDenominatorBurnupTest.php';
$nonOverlap54 = 'current-next54 denominator burnup avoids accepted current-next48 suite progress, current-next49 upstream gap map, release admission/countability, SQL/JSON/WAL/B-tree/VFS behavior clusters';

$tests = [];

for ($i = 1; $i <= 54; $i++) {
    $tests['current next54 suite denominator burnup maps case ' . $i] = static function (TestRunner $t) use ($i, $currentHead54, $nextHead54, $focusedPath54, $nonOverlap54): void {
        $record = libsqlite_release_denominator_evidence()->releaseRunnerSuiteDenominatorBurnup(
            libsqlite_release_denominator_rows($i),
            $currentHead54,
            $nextHead54,
            19277,
            $focusedPath54,
            libsqlite_release_denominator_output(70),
            $nonOverlap54
        );

        $focusedUnits = 2 + ($i % 4);
        $expectedCurrent = 1235 + ($i % 3 === 0 ? 0 : $focusedUnits) + ($i % 11 === 0 ? 6 : 0);
        $expectedNext = 1235 + $focusedUnits + ($i % 5 === 0 ? 1 : 0);

        $t->same($expectedCurrent, $record['current_mapped_units']);
        $t->same($expectedNext, $record['next_mapped_units']);
        $t->same($expectedNext - $expectedCurrent, $record['mapped_unit_delta']);
        $t->same(70, $record['php_pass_delta']);
        $t->same(19347, $record['next_php_pass']);
        $t->same(3 + ($i % 11 === 0 ? 1 : 0), $record['row_count']);
        $t->same(false, $record['counts_release_parity']);
        $t->contains('current-next54 denominator burnup', $record['dependency_closure']);
    };
}

$tests['current next54 advances mapped denominator units cleanly'] = static function (TestRunner $t) use ($currentHead54, $nextHead54, $focusedPath54, $nonOverlap54): void {
    $record = libsqlite_release_denominator_evidence()->releaseRunnerSuiteDenominatorBurnup(
        [
            ['id' => 'veryquick', 'family' => 'runner-baseline', 'units' => 1235, 'current_status' => 'mapped', 'next_status' => 'mapped'],
            ['id' => 'json-dynamic-joins', 'family' => 'focused-subset', 'units' => 8, 'current_status' => 'missing', 'next_status' => 'mapped', 'next_runner_countable' => true],
        ],
        $currentHead54,
        $nextHead54,
        19277,
        $focusedPath54,
        libsqlite_release_denominator_output(70),
        $nonOverlap54
    );

    $t->same('current-next54-denominator-burnup-advanced', $record['status']);
    $t->same(true, $record['counts_denominator_burnup']);
    $t->same(['json-dynamic-joins'], $record['advanced_ids']);
    $t->same(8, $record['mapped_unit_delta']);
    $t->contains('zero-error artifacts before claiming release/all parity', $record['next_gate']);
};

$tests['current next54 preserves mapped denominator units without release parity'] = static function (TestRunner $t) use ($currentHead54, $nextHead54, $focusedPath54, $nonOverlap54): void {
    $record = libsqlite_release_denominator_evidence()->releaseRunnerSuiteDenominatorBurnup(
        [
            ['id' => 'veryquick', 'family' => 'runner-baseline', 'units' => 1235, 'current_status' => 'mapped', 'next_status' => 'mapped'],
            ['id' => 'focused-json', 'family' => 'focused-subset', 'units' => 3, 'current_status' => 'mapped', 'next_status' => 'mapped'],
        ],
        $currentHead54,
        $nextHead54,
        19277,
        $focusedPath54,
        libsqlite_release_denominator_output(70),
        $nonOverlap54
    );

    $t->same('current-next54-denominator-burnup-preserved', $record['status']);
    $t->same(false, $record['counts_denominator_burnup']);
    $t->same(2, $record['preserved_count']);
    $t->same(0, $record['mapped_unit_delta']);
};

$tests['current next54 reports open next denominator gaps'] = static function (TestRunner $t) use ($currentHead54, $nextHead54, $focusedPath54, $nonOverlap54): void {
    $record = libsqlite_release_denominator_evidence()->releaseRunnerSuiteDenominatorBurnup(
        libsqlite_release_denominator_rows(1),
        $currentHead54,
        $nextHead54,
        19277,
        $focusedPath54,
        libsqlite_release_denominator_output(70),
        $nonOverlap54
    );

    $t->same('current-denominator-preserved-with-open-gaps', $record['status']);
    $t->same(['release-all-next-source-1'], $record['open_ids']);
    $t->same(1, $record['blocker_count']);
};

$tests['current next54 blocks regressed denominator units'] = static function (TestRunner $t) use ($currentHead54, $nextHead54, $focusedPath54, $nonOverlap54): void {
    $record = libsqlite_release_denominator_evidence()->releaseRunnerSuiteDenominatorBurnup(
        libsqlite_release_denominator_rows(11),
        $currentHead54,
        $nextHead54,
        19277,
        $focusedPath54,
        libsqlite_release_denominator_output(70),
        $nonOverlap54
    );

    $t->same('blocked', $record['status']);
    $t->true(in_array('mptest-regression-11', $record['regressed_ids'], true), 'Expected mptest denominator regression');
    $t->true($record['blocker_count'] >= 2, 'Expected release and regression blockers');
};

$tests['current next54 blocks runner countability without mapped denominator row'] = static function (TestRunner $t) use ($currentHead54, $nextHead54, $focusedPath54, $nonOverlap54): void {
    $record = libsqlite_release_denominator_evidence()->releaseRunnerSuiteDenominatorBurnup(
        [
            ['id' => 'release-runner', 'family' => 'release-tier', 'units' => 1, 'current_status' => 'missing', 'next_status' => 'missing', 'next_runner_countable' => true],
        ],
        $currentHead54,
        $nextHead54,
        19277,
        $focusedPath54,
        libsqlite_release_denominator_output(70),
        $nonOverlap54
    );

    $t->same('blocked', $record['status']);
    $t->same(['runner-countable-without-denominator-mapping'], $record['entries'][0]['blockers']);
};

$tests['current next54 blocks unfocused php output'] = static function (TestRunner $t) use ($currentHead54, $nextHead54, $focusedPath54, $nonOverlap54): void {
    $record = libsqlite_release_denominator_evidence()->releaseRunnerSuiteDenominatorBurnup(
        [
            ['id' => 'json-dynamic-joins', 'family' => 'focused-subset', 'units' => 8, 'current_status' => 'missing', 'next_status' => 'mapped'],
        ],
        $currentHead54,
        $nextHead54,
        19277,
        $focusedPath54,
        "1 test files, 70 assertions, 0 failures\n",
        $nonOverlap54
    );

    $t->same('blocked', $record['status']);
    $t->same(0, $record['php_pass_delta']);
    $t->same('blocked', $record['php_pass_admission']['status']);
};

$tests['current next54 rejects missing heads'] = static function (TestRunner $t) use ($focusedPath54, $nonOverlap54): void {
    $t->throws(
        InvalidArgumentException::class,
        static fn () => libsqlite_release_denominator_evidence()->releaseRunnerSuiteDenominatorBurnup(
            [['id' => 'release', 'next_status' => 'mapped']],
            '',
            'next',
            19277,
            $focusedPath54,
            libsqlite_release_denominator_output(70),
            $nonOverlap54
        )
    );
};

$tests['current next54 rejects empty denominator rows'] = static function (TestRunner $t) use ($currentHead54, $nextHead54, $focusedPath54, $nonOverlap54): void {
    $t->throws(
        InvalidArgumentException::class,
        static fn () => libsqlite_release_denominator_evidence()->releaseRunnerSuiteDenominatorBurnup(
            [],
            $currentHead54,
            $nextHead54,
            19277,
            $focusedPath54,
            libsqlite_release_denominator_output(70),
            $nonOverlap54
        )
    );
};

$tests['current next54 reports invalid denominator rows as blockers'] = static function (TestRunner $t) use ($currentHead54, $nextHead54, $focusedPath54, $nonOverlap54): void {
    $record = libsqlite_release_denominator_evidence()->releaseRunnerSuiteDenominatorBurnup(
        [
            'json' => ['id' => 'json-dynamic-joins', 'family' => 'focused-subset', 'units' => 8, 'current_status' => 'missing', 'next_status' => 'mapped'],
            'invalid' => 'not-a-row',
        ],
        $currentHead54,
        $nextHead54,
        19277,
        $focusedPath54,
        libsqlite_release_denominator_output(70),
        $nonOverlap54
    );

    $t->same('blocked', $record['status']);
    $t->same('denominator-row-invalid', $record['blockers'][0]['id']);
    $t->same('invalid', $record['blockers'][0]['label']);
};

return $tests;

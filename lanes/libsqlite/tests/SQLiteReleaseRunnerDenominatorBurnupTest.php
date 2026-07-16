<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteUpstreamSuiteEvidence;

function libsqlite_release_burnup52_evidence(): SQLiteUpstreamSuiteEvidence
{
    return SQLiteUpstreamSuiteEvidence::fromManifestPath(__DIR__ . '/../UPSTREAM_TEST_MANIFEST.json');
}

function libsqlite_release_burnup52_output(int $assertions = 64, int $failures = 0): string
{
    return "Focused test run: 1 selected test files (root lock skipped)\n"
        . "1 test files, {$assertions} assertions, {$failures} failures\n";
}

function libsqlite_release_burnup52_rows(int $case): array
{
    return [
        [
            'unit' => 'release-runner-denominator-burnup-current-next52',
            'category' => 'release-runner',
            'current_status' => 'unmapped',
            'next_status' => 'mapped',
            'evidence_type' => 'focused-php-runner-admission',
            'evidence' => 'current-next52 focused TestRunner PASS output admits one new denominator burnup inventory unit',
            'current_tests' => 0,
            'next_tests' => 64 + $case,
        ],
        [
            'unit' => 'accepted-head-current-artifact-anchor',
            'category' => 'provenance',
            'current_status' => 'mapped',
            'next_status' => 'mapped',
            'evidence_type' => 'accepted-head-artifact',
            'evidence' => 'accepted batch49 artifact provenance remains the anchor and does not count as a fresh mapped unit',
            'current_tests' => 22000,
            'next_tests' => 22000 + ($case % 2),
        ],
        [
            'unit' => 'release-all-zero-error-parity',
            'category' => 'release-parity',
            'current_status' => 'unmapped',
            'next_status' => $case % 7 === 0 ? 'mapped' : 'unmapped',
            'next_mapped' => $case % 7 === 0,
            'evidence_type' => 'zero-error-release-artifact',
            'evidence' => $case % 7 === 0 ? 'synthetic zero-error release artifact supplied for burnup row admission' : '',
            'current_tests' => 0,
            'next_tests' => $case % 7 === 0 ? 18000 + $case : 0,
        ],
    ];
}

$currentHead52 = '28488284c6b42b08db024e7e34c788f71b24a201';
$nextHead52 = 'current-next52-denominator-burnup';
$focusedPath52 = 'lanes/libsqlite/tests/SQLiteReleaseRunnerDenominatorBurnupTest.php';
$nonOverlap52 = 'current-next52 denominator burnup avoids accepted release-runner gap map, suite progress, artifact directory evidence, batch23 runner preflight, batch49 upstream gap mapping, and JSON/VFS/WAL/B-tree/SQL behavior clusters';

$tests = [];

for ($i = 1; $i <= 52; $i++) {
    $tests['current next52 denominator burnup maps focused case ' . $i] = static function (TestRunner $t) use ($i, $currentHead52, $nextHead52, $focusedPath52, $nonOverlap52): void {
        $record = libsqlite_release_burnup52_evidence()->releaseRunnerDenominatorBurnup(
            libsqlite_release_burnup52_rows($i),
            $currentHead52,
            $nextHead52,
            462,
            19277,
            $focusedPath52,
            libsqlite_release_burnup52_output(64),
            $nonOverlap52
        );

        $extraReleaseRow = $i % 7 === 0 ? 1 : 0;
        $t->same('current-next52-denominator-burnup-advanced', $record['status']);
        $t->same(3, $record['row_count']);
        $t->same(462, $record['current_mapped']);
        $t->same(463 + $extraReleaseRow, $record['next_mapped']);
        $t->same(1 + $extraReleaseRow, $record['mapped_delta']);
        $t->same(1 + $extraReleaseRow, $record['newly_mapped_count']);
        $t->same(1, $record['preserved_count']);
        $t->same(0, $record['blocker_count']);
        $t->same(64, $record['php_pass_delta']);
        $t->same(19341, $record['next_php_pass']);
        $t->same(true, $record['counts_denominator_burnup']);
        $t->same(false, $record['counts_release_parity']);
        $t->contains('current-next52 denominator burnup', $record['dependency_closure']);
    };
}

$tests['current next52 denominator burnup preserves category totals'] = static function (TestRunner $t) use ($currentHead52, $nextHead52, $focusedPath52, $nonOverlap52): void {
    $record = libsqlite_release_burnup52_evidence()->releaseRunnerDenominatorBurnup(
        libsqlite_release_burnup52_rows(1),
        $currentHead52,
        $nextHead52,
        462,
        19277,
        $focusedPath52,
        libsqlite_release_burnup52_output(),
        $nonOverlap52
    );

    $t->same(3, $record['category_count']);
    $t->same(1, $record['categories']['provenance']);
    $t->same(1, $record['categories']['release-parity']);
    $t->same(1, $record['categories']['release-runner']);
    $t->same(['release-all-zero-error-parity'], $record['open_units']);
};

$tests['current next52 denominator burnup blocks missing row evidence'] = static function (TestRunner $t) use ($currentHead52, $nextHead52, $focusedPath52, $nonOverlap52): void {
    $rows = libsqlite_release_burnup52_rows(1);
    $rows[0]['evidence'] = '';

    $record = libsqlite_release_burnup52_evidence()->releaseRunnerDenominatorBurnup(
        $rows,
        $currentHead52,
        $nextHead52,
        462,
        19277,
        $focusedPath52,
        libsqlite_release_burnup52_output(),
        $nonOverlap52
    );

    $t->same('blocked', $record['status']);
    $t->same(0, $record['mapped_delta']);
    $t->same(462, $record['next_mapped']);
    $t->same(0, $record['php_pass_delta']);
    $t->same('mapped-unit-missing-evidence', $record['blockers'][0]['evidence']);
};

$tests['current next52 denominator burnup blocks duplicate units'] = static function (TestRunner $t) use ($currentHead52, $nextHead52, $focusedPath52, $nonOverlap52): void {
    $rows = libsqlite_release_burnup52_rows(1);
    $rows[] = $rows[0];

    $record = libsqlite_release_burnup52_evidence()->releaseRunnerDenominatorBurnup(
        $rows,
        $currentHead52,
        $nextHead52,
        462,
        19277,
        $focusedPath52,
        libsqlite_release_burnup52_output(),
        $nonOverlap52
    );

    $t->same('blocked', $record['status']);
    $t->true(in_array('duplicate-denominator-unit', array_column($record['blockers'], 'evidence'), true), 'Expected duplicate denominator blocker');
};

$tests['current next52 denominator burnup blocks mapped regressions'] = static function (TestRunner $t) use ($currentHead52, $nextHead52, $focusedPath52, $nonOverlap52): void {
    $rows = libsqlite_release_burnup52_rows(1);
    $rows[1]['next_status'] = 'unmapped';
    $rows[1]['next_mapped'] = false;

    $record = libsqlite_release_burnup52_evidence()->releaseRunnerDenominatorBurnup(
        $rows,
        $currentHead52,
        $nextHead52,
        462,
        19277,
        $focusedPath52,
        libsqlite_release_burnup52_output(),
        $nonOverlap52
    );

    $t->same('blocked', $record['status']);
    $t->same(['accepted-head-current-artifact-anchor'], $record['regressed_units']);
    $t->true(in_array('mapped-unit-regressed', array_column($record['blockers'], 'evidence'), true), 'Expected mapped regression blocker');
};

$tests['current next52 denominator burnup blocks unfocused php output'] = static function (TestRunner $t) use ($currentHead52, $nextHead52, $focusedPath52, $nonOverlap52): void {
    $record = libsqlite_release_burnup52_evidence()->releaseRunnerDenominatorBurnup(
        libsqlite_release_burnup52_rows(1),
        $currentHead52,
        $nextHead52,
        462,
        19277,
        $focusedPath52,
        "1 test files, 64 assertions, 0 failures\n",
        $nonOverlap52
    );

    $t->same('blocked', $record['status']);
    $t->same(0, $record['php_pass_delta']);
    $t->same('focused-php-pass-admission-blocked', $record['blockers'][0]['id']);
};

$tests['current next52 denominator burnup rejects missing heads'] = static function (TestRunner $t) use ($focusedPath52, $nonOverlap52): void {
    $t->throws(
        InvalidArgumentException::class,
        static fn () => libsqlite_release_burnup52_evidence()->releaseRunnerDenominatorBurnup(
            libsqlite_release_burnup52_rows(1),
            '',
            'next',
            462,
            19277,
            $focusedPath52,
            libsqlite_release_burnup52_output(),
            $nonOverlap52
        )
    );
};

$tests['current next52 denominator burnup rejects negative current mapped'] = static function (TestRunner $t) use ($currentHead52, $nextHead52, $focusedPath52, $nonOverlap52): void {
    $t->throws(
        InvalidArgumentException::class,
        static fn () => libsqlite_release_burnup52_evidence()->releaseRunnerDenominatorBurnup(
            libsqlite_release_burnup52_rows(1),
            $currentHead52,
            $nextHead52,
            -1,
            19277,
            $focusedPath52,
            libsqlite_release_burnup52_output(),
            $nonOverlap52
        )
    );
};

$tests['current next52 denominator burnup rejects empty rows'] = static function (TestRunner $t) use ($currentHead52, $nextHead52, $focusedPath52, $nonOverlap52): void {
    $t->throws(
        InvalidArgumentException::class,
        static fn () => libsqlite_release_burnup52_evidence()->releaseRunnerDenominatorBurnup(
            [],
            $currentHead52,
            $nextHead52,
            462,
            19277,
            $focusedPath52,
            libsqlite_release_burnup52_output(),
            $nonOverlap52
        )
    );
};

$tests['current next52 denominator burnup blocks invalid rows'] = static function (TestRunner $t) use ($currentHead52, $nextHead52, $focusedPath52, $nonOverlap52): void {
    $record = libsqlite_release_burnup52_evidence()->releaseRunnerDenominatorBurnup(
        [
            'valid' => libsqlite_release_burnup52_rows(1)[0],
            'invalid' => 'not-a-row',
        ],
        $currentHead52,
        $nextHead52,
        462,
        19277,
        $focusedPath52,
        libsqlite_release_burnup52_output(),
        $nonOverlap52
    );

    $t->same('blocked', $record['status']);
    $t->same('burnup-row-invalid', $record['blockers'][0]['id']);
};

$tests['current next52 denominator burnup preserves evidence type counts'] = static function (TestRunner $t) use ($currentHead52, $nextHead52, $focusedPath52, $nonOverlap52): void {
    $record = libsqlite_release_burnup52_evidence()->releaseRunnerDenominatorBurnup(
        libsqlite_release_burnup52_rows(7),
        $currentHead52,
        $nextHead52,
        462,
        19277,
        $focusedPath52,
        libsqlite_release_burnup52_output(),
        $nonOverlap52
    );

    $t->same(1, $record['evidence_types']['accepted-head-artifact']);
    $t->same(1, $record['evidence_types']['focused-php-runner-admission']);
    $t->same(1, $record['evidence_types']['zero-error-release-artifact']);
    $t->same(['release-all-zero-error-parity', 'release-runner-denominator-burnup-current-next52'], $record['newly_mapped_units']);
};

$tests['current next52 denominator burnup keeps non overlap note'] = static function (TestRunner $t) use ($currentHead52, $nextHead52, $focusedPath52, $nonOverlap52): void {
    $record = libsqlite_release_burnup52_evidence()->releaseRunnerDenominatorBurnup(
        libsqlite_release_burnup52_rows(1),
        $currentHead52,
        $nextHead52,
        462,
        19277,
        $focusedPath52,
        libsqlite_release_burnup52_output(),
        $nonOverlap52
    );

    $t->contains('avoids accepted release-runner gap map', $record['non_overlap_note']);
    $t->contains('publish only the newly mapped', $record['next_gate']);
};

return $tests;

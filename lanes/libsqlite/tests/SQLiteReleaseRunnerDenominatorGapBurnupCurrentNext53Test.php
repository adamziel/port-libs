<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteUpstreamSuiteEvidence;

function libsqlite_release_burnup53_evidence(): SQLiteUpstreamSuiteEvidence
{
    return SQLiteUpstreamSuiteEvidence::fromManifestPath(__DIR__ . '/../UPSTREAM_TEST_MANIFEST.json');
}

function libsqlite_release_burnup53_output(int $assertions = 75, int $failures = 0): string
{
    return "Focused test run: 1 selected test files (root lock skipped)\n"
        . "1 test files, {$assertions} assertions, {$failures} failures\n";
}

function libsqlite_release_burnup53_rows(int $case): array
{
    return [
        [
            'id' => 'json-dynamic-' . $case,
            'bucket' => 'json-table',
            'current_mapped' => 462 + $case,
            'next_mapped' => 463 + $case,
            'current_countable_scripts' => 3,
            'next_countable_scripts' => 4,
            'denominator_total' => 1589,
            'scripts' => ['json101.test', 'json102.test', 'jsonb01.test'],
        ],
        [
            'id' => 'wal-pager-' . $case,
            'bucket' => 'wal-pager',
            'current_mapped' => 74,
            'next_mapped' => 74 + ($case % 4 === 0 ? 1 : 0),
            'current_countable_scripts' => 2,
            'next_countable_scripts' => 2 + ($case % 4 === 0 ? 1 : 0),
            'denominator_total' => 1589,
            'scripts' => ['wal.test', 'wal2.test', 'savepoint.test'],
        ],
        [
            'id' => 'btree-preserved-' . $case,
            'bucket' => 'btree',
            'current_mapped' => 91,
            'next_mapped' => 91,
            'current_countable_scripts' => 3,
            'next_countable_scripts' => 3,
            'denominator_total' => 1589,
            'scripts' => ['btree01.test', 'delete2.test'],
        ],
    ];
}

$currentHead53 = '28488284c6b42b08db024e7e34c788f71b24a201';
$nextHead53 = 'current-next53-denominator-gap-burnup';
$focusedPath53 = 'lanes/libsqlite/tests/SQLiteReleaseRunnerDenominatorGapBurnupCurrentNext53Test.php';
$nonOverlap53 = 'current-next53 denominator burnup avoids accepted release gap map/proof/audit/progress ledgers, guarded runner preflight, JSON table source/cursor/constraint work, SQL SELECT text/subquery/group/order clusters, VFS/WAL/B-tree/Unicode behavior clusters, and batch23 runner countability preflight.';

$tests = [];

for ($i = 1; $i <= 52; $i++) {
    $tests['current next53 denominator burnup case ' . $i] = static function (TestRunner $t) use ($i, $currentHead53, $nextHead53, $focusedPath53, $nonOverlap53): void {
        $record = libsqlite_release_burnup53_evidence()->releaseRunnerDenominatorGapBurnup(
            libsqlite_release_burnup53_rows($i),
            $currentHead53,
            $nextHead53,
            19277,
            $focusedPath53,
            libsqlite_release_burnup53_output(75),
            $nonOverlap53
        );

        $expectedWalDelta = $i % 4 === 0 ? 1 : 0;
        $t->same('current-next53-denominator-burnup-ready', $record['status']);
        $t->same(3, $record['row_count']);
        $t->same(3, $record['bucket_count']);
        $t->same(1 + $expectedWalDelta, $record['mapped_delta_total']);
        $t->same(1 + $expectedWalDelta, $record['countable_script_delta_total']);
        $t->same(1 + ($expectedWalDelta === 1 ? 1 : 0), $record['advanced_count']);
        $t->same($expectedWalDelta === 1 ? 1 : 2, $record['preserved_count']);
        $t->same(0, $record['blocked_count']);
        $t->same(0, $record['regressed_count']);
        $t->same(8, $record['target_script_count']);
        $t->same(['btree01.test', 'delete2.test', 'json101.test', 'json102.test', 'jsonb01.test', 'savepoint.test', 'wal.test', 'wal2.test'], $record['target_scripts']);
        $t->same('clear', $record['active_runner_status']);
        $t->same('admitted', $record['php_pass_admission']['status']);
        $t->same(75, $record['php_pass_delta']);
        $t->same(19352, $record['next_php_pass']);
        $t->same(true, $record['counts_denominator_gap_burnup']);
        $t->same(false, $record['counts_release_parity']);
        $t->contains('guarded runner', $record['next_gate']);
        $t->contains('no new support component needed', $record['dependency_closure']);
    };
}

$tests['current next53 denominator burnup preserves already covered rows'] = static function (TestRunner $t) use ($currentHead53, $nextHead53, $focusedPath53, $nonOverlap53): void {
    $record = libsqlite_release_burnup53_evidence()->releaseRunnerDenominatorGapBurnup(
        [
            ['id' => 'veryquick', 'bucket' => 'suite', 'current_mapped' => 462, 'next_mapped' => 462, 'current_countable_scripts' => 40, 'next_countable_scripts' => 40, 'denominator_total' => 1589, 'scripts' => ['select1.test']],
        ],
        $currentHead53,
        $nextHead53,
        19277,
        $focusedPath53,
        libsqlite_release_burnup53_output(75),
        $nonOverlap53
    );

    $t->same('current-next53-denominator-preserved', $record['status']);
    $t->same(0, $record['mapped_delta_total']);
    $t->same(0, $record['countable_script_delta_total']);
    $t->same(['veryquick'], $record['preserved_gap_ids']);
};

$tests['current next53 denominator burnup reports partial blocked rows'] = static function (TestRunner $t) use ($currentHead53, $nextHead53, $focusedPath53, $nonOverlap53): void {
    $record = libsqlite_release_burnup53_evidence()->releaseRunnerDenominatorGapBurnup(
        [
            ['id' => 'json-ready', 'bucket' => 'json', 'current_mapped' => 462, 'next_mapped' => 464, 'current_countable_scripts' => 2, 'next_countable_scripts' => 4, 'denominator_total' => 1589, 'scripts' => ['json101.test']],
            ['id' => 'release-waiting', 'bucket' => 'release', 'current_mapped' => 0, 'next_mapped' => 1, 'current_countable_scripts' => 0, 'next_countable_scripts' => 1, 'denominator_total' => 1589, 'scripts' => ['fts5aux.test'], 'hydrated' => false],
        ],
        $currentHead53,
        $nextHead53,
        19277,
        $focusedPath53,
        libsqlite_release_burnup53_output(75),
        $nonOverlap53
    );

    $t->same('current-next53-denominator-burnup-partial', $record['status']);
    $t->same(['json-ready'], $record['advanced_gap_ids']);
    $t->same(['release-waiting'], $record['blocked_gap_ids']);
    $t->same(1, $record['blocker_count']);
    $t->contains('not-hydrated', $record['blockers'][0]['evidence']);
    $t->same(0, $record['php_pass_delta']);
};

$tests['current next53 denominator burnup blocks mapped regression'] = static function (TestRunner $t) use ($currentHead53, $nextHead53, $focusedPath53, $nonOverlap53): void {
    $record = libsqlite_release_burnup53_evidence()->releaseRunnerDenominatorGapBurnup(
        [
            ['id' => 'regressed', 'bucket' => 'release', 'current_mapped' => 12, 'next_mapped' => 11, 'current_countable_scripts' => 4, 'next_countable_scripts' => 4, 'denominator_total' => 1589, 'scripts' => ['where.test']],
        ],
        $currentHead53,
        $nextHead53,
        19277,
        $focusedPath53,
        libsqlite_release_burnup53_output(75),
        $nonOverlap53
    );

    $t->same('blocked', $record['status']);
    $t->same(['regressed'], $record['regressed_gap_ids']);
    $t->contains('mapped-count-regressed', $record['blockers'][0]['evidence']);
};

$tests['current next53 denominator burnup blocks duplicate broad runner'] = static function (TestRunner $t) use ($currentHead53, $nextHead53, $focusedPath53, $nonOverlap53): void {
    $record = libsqlite_release_burnup53_evidence()->releaseRunnerDenominatorGapBurnup(
        libsqlite_release_burnup53_rows(2),
        $currentHead53,
        $nextHead53,
        19277,
        $focusedPath53,
        libsqlite_release_burnup53_output(75),
        $nonOverlap53,
        "12345 12344 S+ 00:10 90.0 ./testfixture ../libsqlite/test/testrunner.tcl --jobs 2 --stop-on-error all\n"
    );

    $t->same('current-next53-denominator-burnup-partial', $record['status']);
    $t->same('blocked-active-runner', $record['active_runner_status']);
    $t->same(1, $record['active_runner_count']);
    $t->same('duplicate-broad-runner-active', $record['blockers'][0]['id']);
};

$tests['current next53 denominator burnup blocks unfocused php output'] = static function (TestRunner $t) use ($currentHead53, $nextHead53, $focusedPath53, $nonOverlap53): void {
    $record = libsqlite_release_burnup53_evidence()->releaseRunnerDenominatorGapBurnup(
        libsqlite_release_burnup53_rows(3),
        $currentHead53,
        $nextHead53,
        19277,
        $focusedPath53,
        "1 test files, 75 assertions, 0 failures\n",
        $nonOverlap53
    );

    $t->same('current-next53-denominator-burnup-partial', $record['status']);
    $t->same('blocked', $record['php_pass_admission']['status']);
    $t->same('focused-php-pass-admission-blocked', $record['blockers'][0]['id']);
    $t->same(0, $record['php_pass_delta']);
};

$tests['current next53 denominator burnup rejects missing heads and rows'] = static function (TestRunner $t) use ($currentHead53, $nextHead53, $focusedPath53, $nonOverlap53): void {
    $evidence = libsqlite_release_burnup53_evidence();

    $t->throws(InvalidArgumentException::class, static fn () => $evidence->releaseRunnerDenominatorGapBurnup([], $currentHead53, $nextHead53, 19277, $focusedPath53, libsqlite_release_burnup53_output(75), $nonOverlap53));
    $t->throws(InvalidArgumentException::class, static fn () => $evidence->releaseRunnerDenominatorGapBurnup(libsqlite_release_burnup53_rows(1), '', $nextHead53, 19277, $focusedPath53, libsqlite_release_burnup53_output(75), $nonOverlap53));
    $t->throws(InvalidArgumentException::class, static fn () => $evidence->releaseRunnerDenominatorGapBurnup(libsqlite_release_burnup53_rows(1), $currentHead53, '', 19277, $focusedPath53, libsqlite_release_burnup53_output(75), $nonOverlap53));
};

return $tests;

<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteUpstreamSuiteEvidence;

function libsqlite_release_denominator62_evidence(): SQLiteUpstreamSuiteEvidence
{
    return SQLiteUpstreamSuiteEvidence::fromManifestPath(__DIR__ . '/../UPSTREAM_TEST_MANIFEST.json');
}

function libsqlite_release_denominator62_output(int $assertions = 72, int $failures = 0): string
{
    return "Focused test run: 1 selected test files (root lock skipped)\n"
        . "1 test files, {$assertions} assertions, {$failures} failures\n";
}

function libsqlite_release_denominator62_candidate(int $case): array
{
    return [
        'id' => 'suite-denominator-current-next62-' . $case,
        'family' => 'suite-denominator-current-next',
        'surface' => 'release-denominator-current-next62-' . $case,
        'base_head' => '77217644481cc13ece794019a17d140500682bab',
        'current_status' => $case % 5 === 0 ? 'mapped' : 'missing',
        'next_status' => 'mapped',
        'tests_delta' => 18000 + $case,
        'evidence' => 'current-next62 focused PHP evidence admits one non-duplicate suite denominator row without release/all parity',
    ];
}

$currentHead62 = '77217644481cc13ece794019a17d140500682bab';
$focusedPath62 = 'lanes/libsqlite/tests/SQLiteReleaseRunnerCurrentNextDenominatorCurrentNext62Test.php';
$nonOverlap62 = 'current-next62 suite denominator decision avoids queued attach/temp WAL schema cache, B-tree overflow/freelist/pointer-map/vacuum, JSONB delete/cascade/check, planner covering/stat/OR/JSON, PRAGMA integrity/FK/root, SELECT recursive JSON/materialization, trigger/FK/UPSERT/RETURNING, VDBE window, WAL checkpoint/restart/reader snapshot/savepoint release, Application JSON/schema/import WAL savepoint, and accepted current-next54 denominator burnup surfaces';

$tests = [];

for ($i = 1; $i <= 62; $i++) {
    $tests['current next62 admits non duplicate denominator case ' . $i] = static function (TestRunner $t) use ($i, $currentHead62, $focusedPath62, $nonOverlap62): void {
        $record = libsqlite_release_denominator62_evidence()->releaseRunnerCurrentDenominatorDecision(
            [libsqlite_release_denominator62_candidate($i)],
            [
                'attach-temp-wal-schema-cache',
                'btree-overflow-freelist-pointer-map-vacuum',
                'jsonb-delete-cascade-check',
                'planner-covering-stat-or-json',
                'pragma-integrity-fk-root',
                'select-recursive-json-materialization',
                'trigger-fk-upsert-returning',
                'vdbe-window-filter-range-group-value',
                'wal-checkpoint-restart-reader-snapshot-savepoint-release',
                'application-json-schema-import-wal-savepoint',
            ],
            $currentHead62,
            463,
            22945,
            $focusedPath62,
            libsqlite_release_denominator62_output(72),
            $nonOverlap62
        );

        $isPreserved = $i % 5 === 0;
        $t->same($isPreserved ? 'current-next62-denominator-preserved' : 'current-next62-denominator-admitted', $record['status']);
        $t->same(1, $record['row_count']);
        $t->same(463, $record['current_mapped']);
        $t->same($isPreserved ? 463 : 464, $record['next_mapped']);
        $t->same($isPreserved ? 0 : 1, $record['mapped_delta']);
        $t->same($isPreserved ? [] : ['suite-denominator-current-next62-' . $i], $record['accepted_ids']);
        $t->same($isPreserved ? ['suite-denominator-current-next62-' . $i] : [], $record['preserved_ids']);
        $t->same($isPreserved ? 0 : 18000 + $i, $record['tests_total_delta']);
        $t->same(72, $record['php_pass_delta']);
        $t->same(23017, $record['next_php_pass']);
        $t->same(false, $record['counts_release_parity']);
        $t->contains('current-next62 denominator decision', $record['dependency_closure']);
    };
}

$tests['current next62 blocks queued duplicate surfaces'] = static function (TestRunner $t) use ($currentHead62, $focusedPath62, $nonOverlap62): void {
    $row = libsqlite_release_denominator62_candidate(63);
    $row['surface'] = 'jsonb-delete-cascade-check';

    $record = libsqlite_release_denominator62_evidence()->releaseRunnerCurrentDenominatorDecision(
        [$row],
        ['jsonb-delete-cascade-check'],
        $currentHead62,
        463,
        22945,
        $focusedPath62,
        libsqlite_release_denominator62_output(),
        $nonOverlap62
    );

    $t->same('blocked', $record['status']);
    $t->same(['current-next62-candidate-blocked'], array_column($record['blockers'], 'id'));
    $t->contains('candidate-surface-already-queued-or-accepted', $record['blockers'][0]['evidence']);
    $t->same(0, $record['mapped_delta']);
    $t->same(0, $record['php_pass_delta']);
};

$tests['current next62 blocks stale accepted base candidates'] = static function (TestRunner $t) use ($currentHead62, $focusedPath62, $nonOverlap62): void {
    $row = libsqlite_release_denominator62_candidate(64);
    $row['base_head'] = 'aa5c67a8d70941079503fe746744a6952caec0a5';

    $record = libsqlite_release_denominator62_evidence()->releaseRunnerCurrentDenominatorDecision(
        [$row],
        [],
        $currentHead62,
        463,
        22945,
        $focusedPath62,
        libsqlite_release_denominator62_output(),
        $nonOverlap62
    );

    $t->same('blocked', $record['status']);
    $t->contains('candidate-base-head-mismatch', $record['blockers'][0]['evidence']);
};

$tests['current next62 blocks missing focused evidence'] = static function (TestRunner $t) use ($currentHead62, $focusedPath62, $nonOverlap62): void {
    $row = libsqlite_release_denominator62_candidate(65);
    $row['evidence'] = '';

    $record = libsqlite_release_denominator62_evidence()->releaseRunnerCurrentDenominatorDecision(
        [$row],
        [],
        $currentHead62,
        463,
        22945,
        $focusedPath62,
        libsqlite_release_denominator62_output(),
        $nonOverlap62
    );

    $t->same('blocked', $record['status']);
    $t->contains('candidate-missing-focused-evidence', $record['blockers'][0]['evidence']);
};

$tests['current next62 blocks release all parity claims'] = static function (TestRunner $t) use ($currentHead62, $focusedPath62, $nonOverlap62): void {
    $row = libsqlite_release_denominator62_candidate(66);
    $row['release_parity'] = true;

    $record = libsqlite_release_denominator62_evidence()->releaseRunnerCurrentDenominatorDecision(
        [$row],
        [],
        $currentHead62,
        463,
        22945,
        $focusedPath62,
        libsqlite_release_denominator62_output(),
        $nonOverlap62
    );

    $t->same('blocked', $record['status']);
    $t->contains('release-all-parity-claim-not-admitted', $record['blockers'][0]['evidence']);
    $t->same(false, $record['counts_release_parity']);
};

$tests['current next62 blocks duplicate candidate ids'] = static function (TestRunner $t) use ($currentHead62, $focusedPath62, $nonOverlap62): void {
    $row = libsqlite_release_denominator62_candidate(67);

    $record = libsqlite_release_denominator62_evidence()->releaseRunnerCurrentDenominatorDecision(
        [$row, $row],
        [],
        $currentHead62,
        463,
        22945,
        $focusedPath62,
        libsqlite_release_denominator62_output(),
        $nonOverlap62
    );

    $t->same('blocked', $record['status']);
    $t->true(in_array('duplicate-current-next62-denominator-id', array_column($record['blockers'], 'evidence'), true), 'Expected duplicate id blocker');
};

$tests['current next62 blocks unfocused php output'] = static function (TestRunner $t) use ($currentHead62, $focusedPath62, $nonOverlap62): void {
    $record = libsqlite_release_denominator62_evidence()->releaseRunnerCurrentDenominatorDecision(
        [libsqlite_release_denominator62_candidate(68)],
        [],
        $currentHead62,
        463,
        22945,
        $focusedPath62,
        "1 test files, 72 assertions, 0 failures\n",
        $nonOverlap62
    );

    $t->same('blocked', $record['status']);
    $t->same(0, $record['php_pass_delta']);
    $t->same('blocked', $record['php_pass_admission']['status']);
};

$tests['current next62 reports family rollup for mixed candidates'] = static function (TestRunner $t) use ($currentHead62, $focusedPath62, $nonOverlap62): void {
    $first = libsqlite_release_denominator62_candidate(69);
    $second = libsqlite_release_denominator62_candidate(70);
    $second['current_status'] = 'mapped';

    $record = libsqlite_release_denominator62_evidence()->releaseRunnerCurrentDenominatorDecision(
        [$first, $second],
        [],
        $currentHead62,
        463,
        22945,
        $focusedPath62,
        libsqlite_release_denominator62_output(),
        $nonOverlap62
    );

    $t->same('current-next62-denominator-admitted', $record['status']);
    $t->same(2, $record['row_count']);
    $t->same(1, $record['family_count']);
    $t->same(2, $record['families'][0]['rows']);
    $t->same(1, $record['families'][0]['accepted']);
    $t->same(1, $record['families'][0]['preserved']);
    $t->same(18069, $record['families'][0]['tests_delta']);
};

$tests['current next62 rejects missing accepted base head'] = static function (TestRunner $t) use ($focusedPath62, $nonOverlap62): void {
    $t->throws(
        InvalidArgumentException::class,
        static fn () => libsqlite_release_denominator62_evidence()->releaseRunnerCurrentDenominatorDecision(
            [libsqlite_release_denominator62_candidate(71)],
            [],
            '',
            463,
            22945,
            $focusedPath62,
            libsqlite_release_denominator62_output(),
            $nonOverlap62
        )
    );
};

$tests['current next62 rejects empty candidate rows'] = static function (TestRunner $t) use ($currentHead62, $focusedPath62, $nonOverlap62): void {
    $t->throws(
        InvalidArgumentException::class,
        static fn () => libsqlite_release_denominator62_evidence()->releaseRunnerCurrentDenominatorDecision(
            [],
            [],
            $currentHead62,
            463,
            22945,
            $focusedPath62,
            libsqlite_release_denominator62_output(),
            $nonOverlap62
        )
    );
};

$tests['current next62 rejects negative mapped baseline'] = static function (TestRunner $t) use ($currentHead62, $focusedPath62, $nonOverlap62): void {
    $t->throws(
        InvalidArgumentException::class,
        static fn () => libsqlite_release_denominator62_evidence()->releaseRunnerCurrentDenominatorDecision(
            [libsqlite_release_denominator62_candidate(72)],
            [],
            $currentHead62,
            -1,
            22945,
            $focusedPath62,
            libsqlite_release_denominator62_output(),
            $nonOverlap62
        )
    );
};

return $tests;

<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteUpstreamSuiteEvidence;

function libsqlite_release_gate60_evidence(): SQLiteUpstreamSuiteEvidence
{
    return SQLiteUpstreamSuiteEvidence::fromManifestPath(__DIR__ . '/../UPSTREAM_TEST_MANIFEST.json');
}

function libsqlite_release_gate60_output(int $assertions = 64, int $failures = 0): string
{
    return "Focused test run: 1 selected test files (root lock skipped)\n"
        . "1 test files, {$assertions} assertions, {$failures} failures\n";
}

function libsqlite_release_gate60_artifact(string $id, string $head, string $tier, int $tests, int $errors = 0, int $exit = 0, string $status = 'passed'): array
{
    return [
        'id' => $id,
        'status' => $status,
        'repository_head' => $head,
        'requested' => [
            'testset' => $tier,
            'jobs' => 2,
            'patterns' => $tier === 'all' ? [] : ['fts5aux.test', 'json101.test'],
        ],
        'results' => [
            'exit' => $exit,
            'tests' => $tests,
            'errors' => $errors,
        ],
    ];
}

$acceptedHead60 = '778938e77304dab463d63bda640825b6591b12b6';
$focusedPath60 = 'lanes/libsqlite/tests/SQLiteUpstreamReleaseGateCurrentNext60Test.php';
$nonOverlap60 = 'current-next60 release gate avoids accepted focused-runner admission, denominator burnup, suite gap ledgers, JSON table source/cursor/constraint work, SQL SELECT text/subquery/group/order clusters, VFS/WAL/B-tree/Unicode implementation clusters, and duplicate release/all broad runner launches.';

$tests = [];

for ($i = 1; $i <= 42; $i++) {
    $tests['current next60 release gate admits accepted head artifact case ' . $i] = static function (TestRunner $t) use ($i, $acceptedHead60, $focusedPath60, $nonOverlap60): void {
        $record = libsqlite_release_gate60_evidence()->upstreamSuiteReleaseGateEvidence(
            [
                libsqlite_release_gate60_artifact('all-zero-error-' . $i, $acceptedHead60, 'all', 10785 + $i),
                libsqlite_release_gate60_artifact('release-zero-error-' . $i, $acceptedHead60, 'release', 22000 + $i),
            ],
            $acceptedHead60,
            22215,
            $focusedPath60,
            libsqlite_release_gate60_output(64),
            $nonOverlap60
        );

        $t->same('current-next60-release-gate-countable', $record['status']);
        $t->same($acceptedHead60, $record['accepted_head']);
        $t->same(2, $record['artifact_count']);
        $t->same(2, $record['countable_artifact_count']);
        $t->same(0, $record['blocked_artifact_count']);
        $t->same(32785 + ($i * 2), $record['countable_test_total']);
        $t->same(0, $record['countable_error_total']);
        $t->same(['all' => 1, 'release' => 1], $record['tier_counts']);
        $t->same(2, $record['requested_script_count']);
        $t->same(['fts5aux.test', 'json101.test'], $record['requested_scripts']);
        $t->same('clear', $record['active_runner_status']);
        $t->same(0, $record['active_runner_count']);
        $t->same('admitted', $record['php_pass_admission']['status']);
        $t->same(64, $record['php_pass_delta']);
        $t->same(22279, $record['next_php_pass']);
        $t->same(0, $record['global_blocker_count']);
        $t->same(true, $record['counts_release_parity']);
        $t->same(true, $record['counts_runner_blocker_removal']);
        $t->contains('current-next60', $record['next_gate']);
        $t->contains('no new support component needed', $record['dependency_closure']);
        $t->contains('denominator burnup', $record['non_overlap_note']);
    };
}

$tests['current next60 release gate keeps stale head artifact uncounted'] = static function (TestRunner $t) use ($acceptedHead60, $focusedPath60, $nonOverlap60): void {
    $record = libsqlite_release_gate60_evidence()->upstreamSuiteReleaseGateEvidence(
        [
            libsqlite_release_gate60_artifact('accepted-release', $acceptedHead60, 'release', 22000),
            libsqlite_release_gate60_artifact('stale-all', '36d0dc6f9ad9153a9cf6dd45f76c3dadd789ad3f', 'all', 10785),
        ],
        $acceptedHead60,
        22215,
        $focusedPath60,
        libsqlite_release_gate60_output(64),
        $nonOverlap60
    );

    $t->same('current-next60-release-gate-partial', $record['status']);
    $t->same(1, $record['countable_artifact_count']);
    $t->same(1, $record['blocked_artifact_count']);
    $t->same(22000, $record['countable_test_total']);
    $t->same(['release' => 1], $record['tier_counts']);
    $t->same('stale-all', $record['blocked_artifacts'][0]['id']);
    $t->same(['artifact-head-mismatch'], $record['blocked_artifacts'][0]['blocker_ids']);
    $t->same(64, $record['php_pass_delta']);
    $t->same(false, $record['counts_release_parity']);
    $t->same(true, $record['counts_runner_blocker_removal']);
    $t->contains('blocked/stale artifacts uncounted', $record['next_gate']);
};

$tests['current next60 release gate blocks failed and non release artifacts'] = static function (TestRunner $t) use ($acceptedHead60, $focusedPath60, $nonOverlap60): void {
    $record = libsqlite_release_gate60_evidence()->upstreamSuiteReleaseGateEvidence(
        [
            libsqlite_release_gate60_artifact('failed-release', $acceptedHead60, 'release', 21999, 1, 1, 'failed'),
            libsqlite_release_gate60_artifact('focused-veryquick', $acceptedHead60, 'veryquick', 1235),
        ],
        $acceptedHead60,
        22215,
        $focusedPath60,
        libsqlite_release_gate60_output(64),
        $nonOverlap60
    );

    $t->same('blocked', $record['status']);
    $t->same(0, $record['countable_artifact_count']);
    $t->same(2, $record['blocked_artifact_count']);
    $t->same(0, $record['countable_test_total']);
    $t->same(0, $record['php_pass_delta']);
    $t->same(22215, $record['next_php_pass']);
    $t->same(['artifact-not-zero-error'], $record['blocked_artifacts'][0]['blocker_ids']);
    $t->same(['not-release-or-all-tier'], $record['blocked_artifacts'][1]['blocker_ids']);
    $t->same(1, $record['global_blocker_count']);
    $t->same('no-countable-release-artifact', $record['global_blockers'][0]['id']);
    $t->same(false, $record['counts_release_parity']);
    $t->same(false, $record['counts_runner_blocker_removal']);
    $t->contains('do not count current-next60', $record['next_gate']);
};

$tests['current next60 release gate blocks duplicate active broad runner'] = static function (TestRunner $t) use ($acceptedHead60, $focusedPath60, $nonOverlap60): void {
    $snapshot = '601001 599999 00:37 make -C .upstream-cache/libsqlite-build-port-libsqlite test';
    $record = libsqlite_release_gate60_evidence()->upstreamSuiteReleaseGateEvidence(
        [libsqlite_release_gate60_artifact('accepted-all', $acceptedHead60, 'all', 10785)],
        $acceptedHead60,
        22215,
        $focusedPath60,
        libsqlite_release_gate60_output(64),
        $nonOverlap60,
        $snapshot
    );

    $t->same('current-next60-release-gate-partial', $record['status']);
    $t->same(1, $record['countable_artifact_count']);
    $t->same('blocked-active-runner', $record['active_runner_status']);
    $t->same(1, $record['active_runner_count']);
    $t->same(1, $record['global_blocker_count']);
    $t->same('duplicate-broad-runner-active', $record['global_blockers'][0]['id']);
    $t->same(false, $record['counts_release_parity']);
    $t->same(true, $record['counts_runner_blocker_removal']);
    $t->contains('preserve the countable accepted-HEAD artifact', $record['next_gate']);
};

$tests['current next60 release gate blocks failed focused php admission'] = static function (TestRunner $t) use ($acceptedHead60, $focusedPath60, $nonOverlap60): void {
    $record = libsqlite_release_gate60_evidence()->upstreamSuiteReleaseGateEvidence(
        [libsqlite_release_gate60_artifact('accepted-release', $acceptedHead60, 'release', 22000)],
        $acceptedHead60,
        22215,
        $focusedPath60,
        libsqlite_release_gate60_output(64, 1),
        $nonOverlap60
    );

    $t->same('blocked', $record['status']);
    $t->same(1, $record['countable_artifact_count']);
    $t->same('blocked', $record['php_pass_admission']['status']);
    $t->same(0, $record['php_pass_delta']);
    $t->same(22215, $record['next_php_pass']);
    $t->same(1, $record['global_blocker_count']);
    $t->same('focused-php-pass-admission-blocked', $record['global_blockers'][0]['id']);
    $t->same(false, $record['counts_release_parity']);
    $t->same(false, $record['counts_runner_blocker_removal']);
    $t->contains('focused PHP admission', $record['next_gate']);
};

$tests['current next60 release gate rejects missing accepted head'] = static function (TestRunner $t) use ($focusedPath60, $nonOverlap60): void {
    try {
        libsqlite_release_gate60_evidence()->upstreamSuiteReleaseGateEvidence(
            [],
            '',
            22215,
            $focusedPath60,
            libsqlite_release_gate60_output(64),
            $nonOverlap60
        );
        $t->fail('Expected current-next60 release gate to require accepted HEAD');
    } catch (InvalidArgumentException $exception) {
        $t->contains('accepted HEAD', $exception->getMessage());
    }
};

return $tests;

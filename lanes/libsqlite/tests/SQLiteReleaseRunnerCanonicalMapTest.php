<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteUpstreamSuiteEvidence;

function libsqlite_release_canonical_evidence(): SQLiteUpstreamSuiteEvidence
{
    return SQLiteUpstreamSuiteEvidence::fromManifestPath(__DIR__ . '/../UPSTREAM_TEST_MANIFEST.json');
}

function libsqlite_release_canonical_artifact(
    string $head,
    string $testset,
    array|string $patterns,
    int $tests,
    int $errors = 0,
    int $exit = 0,
    string $manifest = '9ac4a33a2932d353c4871fd8e09c10addf827f1fc3fc9380037d738cf2cd0353',
    string $status = 'passed',
    string $commit = '8f70ec615f4cd247d36f92a22c99f65ebbcc22a7',
    string $version = '3.54.0'
): array {
    return [
        'status' => $status,
        'label' => $testset . '-' . $tests,
        'repository_head' => $head,
        'sqlite_commit' => $commit,
        'sqlite_version' => $version,
        'sqlite_manifest_uuid' => $manifest,
        'requested' => [
            'testset' => $testset,
            'patterns' => $patterns,
            'jobs' => 2,
            'timeout_seconds' => 7200,
        ],
        'results' => [
            'exit' => $exit,
            'tests' => $tests,
            'errors' => $errors,
            'failure_count' => $errors,
            'failures' => [],
            'failure_blockers' => [],
        ],
        'active_gate' => [
            'status' => 'clear',
            'active_count' => 0,
            'active_tiers' => [],
        ],
    ];
}

$currentHead36 = '28488284c6b42b08db024e7e34c788f71b24a201';
$nextHead36 = '197c6704da63994d739cab3173def106d59e109d';

$tests = [
    'current next36 canonical map preserves current release baseline' => static function (TestRunner $t) use ($currentHead36, $nextHead36): void {
        $map = libsqlite_release_canonical_evidence()->releaseRunnerUpstreamCanonicalMap([
            'current-release' => libsqlite_release_canonical_artifact($currentHead36, 'release', [], 12000),
        ], $currentHead36, $nextHead36);

        $t->same('current-canonical-baseline-only', $map['status']);
        $t->same(1, $map['artifact_count']);
        $t->same(1, $map['canonical_entry_count']);
        $t->same(1, $map['current_countable_count']);
        $t->same(0, $map['next_countable_count']);
        $t->same(true, $map['counts_current_baseline']);
        $t->same(true, $map['ready_to_launch_next_guarded_runner']);
        $t->contains('preserve the current accepted canonical baseline', $map['next_gate']);
    },
    'current next36 canonical map suppresses launch when next release exists' => static function (TestRunner $t) use ($currentHead36, $nextHead36): void {
        $map = libsqlite_release_canonical_evidence()->releaseRunnerUpstreamCanonicalMap([
            'current-release' => libsqlite_release_canonical_artifact($currentHead36, 'release', [], 12000),
            'next-release' => libsqlite_release_canonical_artifact($nextHead36, 'release', [], 12100),
        ], $currentHead36, $nextHead36);

        $t->same('next-canonical-artifact-present', $map['status']);
        $t->same(1, $map['current_countable_count']);
        $t->same(1, $map['next_countable_count']);
        $t->same(true, $map['counts_next_artifacts']);
        $t->same(false, $map['ready_to_launch_next_guarded_runner']);
        $t->contains('suppress duplicate broad runner launch', $map['next_gate']);
    },
    'current next36 canonical map blocks stale release artifacts' => static function (TestRunner $t) use ($currentHead36, $nextHead36): void {
        $map = libsqlite_release_canonical_evidence()->releaseRunnerUpstreamCanonicalMap([
            'current-release' => libsqlite_release_canonical_artifact($currentHead36, 'release', [], 12000),
            'stale-release' => libsqlite_release_canonical_artifact('stale-head', 'release', [], 13000),
        ], $currentHead36, $nextHead36);

        $t->same('current-canonical-baseline-with-blockers', $map['status']);
        $t->same(1, $map['stale_count']);
        $t->same(1, $map['blocked_count']);
        $t->true(in_array('artifact-head-not-current-or-next', $map['entries'][1]['blocker_ids'], true), 'Expected stale-head blocker');
        $t->same(false, $map['ready_to_launch_next_guarded_runner']);
    },
    'current next36 canonical map normalizes focused pattern order' => static function (TestRunner $t) use ($currentHead36, $nextHead36): void {
        $map = libsqlite_release_canonical_evidence()->releaseRunnerUpstreamCanonicalMap([
            'focused-a' => libsqlite_release_canonical_artifact($currentHead36, 'veryquick', ['wal.test', 'pager.test'], 900),
            'focused-b' => libsqlite_release_canonical_artifact($currentHead36, 'veryquick', ['pager.test', 'wal.test'], 901),
        ], $currentHead36, $nextHead36);

        $t->same(2, $map['artifact_count']);
        $t->same(1, $map['canonical_entry_count']);
        $t->same(1, $map['duplicate_count']);
        $t->same(['pager.test', 'wal.test'], $map['canonical_entries'][0]['patterns']);
        $t->same(901, $map['canonical_entries'][0]['tests']);
    },
    'current next36 canonical map rejects missing head values' => static function (TestRunner $t) use ($currentHead36, $nextHead36): void {
        $t->throws(
            InvalidArgumentException::class,
            static fn () => libsqlite_release_canonical_evidence()->releaseRunnerUpstreamCanonicalMap([], '', $nextHead36)
        );
        $t->throws(
            InvalidArgumentException::class,
            static fn () => libsqlite_release_canonical_evidence()->releaseRunnerUpstreamCanonicalMap([], $currentHead36, '')
        );
    },
    'current next36 canonical map records failed artifact blockers' => static function (TestRunner $t) use ($currentHead36, $nextHead36): void {
        $map = libsqlite_release_canonical_evidence()->releaseRunnerUpstreamCanonicalMap([
            'current-release' => libsqlite_release_canonical_artifact($currentHead36, 'release', [], 12000),
            'failed-release' => libsqlite_release_canonical_artifact($currentHead36, 'all', [], 400, 1, 1),
        ], $currentHead36, $nextHead36);

        $t->same('current-canonical-baseline-with-blockers', $map['status']);
        $t->same(1, $map['blocked_count']);
        $t->true(in_array('artifact-not-passed', $map['entries'][1]['blocker_ids'], true), 'Expected artifact-not-passed blocker');
    },
    'current next36 canonical map records manifest mismatch blockers' => static function (TestRunner $t) use ($currentHead36, $nextHead36): void {
        $map = libsqlite_release_canonical_evidence()->releaseRunnerUpstreamCanonicalMap([
            'current-release' => libsqlite_release_canonical_artifact($currentHead36, 'release', [], 12000),
            'wrong-manifest' => libsqlite_release_canonical_artifact($currentHead36, 'release', [], 401, 0, 0, 'wrong-manifest'),
        ], $currentHead36, $nextHead36);

        $t->same(1, $map['blocked_count']);
        $t->true(in_array('sqlite-manifest-uuid-mismatch', $map['entries'][1]['blocker_ids'], true), 'Expected manifest mismatch blocker');
    },
    'current next36 canonical map keeps focused evidence out of release-like count' => static function (TestRunner $t) use ($currentHead36, $nextHead36): void {
        $map = libsqlite_release_canonical_evidence()->releaseRunnerUpstreamCanonicalMap([
            'focused-current' => libsqlite_release_canonical_artifact($currentHead36, 'release', ['json101.test'], 222),
        ], $currentHead36, $nextHead36);

        $t->same(0, $map['release_like_count']);
        $t->same(1, $map['focused_count']);
        $t->same('focused', $map['entries'][0]['kind']);
    },
    'current next36 canonical map normalizes comma pattern strings' => static function (TestRunner $t) use ($currentHead36, $nextHead36): void {
        $map = libsqlite_release_canonical_evidence()->releaseRunnerUpstreamCanonicalMap([
            'focused-current' => libsqlite_release_canonical_artifact($currentHead36, 'veryquick', 'json102.test, json101.test', 333),
        ], $currentHead36, $nextHead36);

        $t->same(['json101.test', 'json102.test'], $map['entries'][0]['patterns']);
        $t->same('focused', $map['entries'][0]['kind']);
    },
    'current next36 canonical map blocks empty artifact sets' => static function (TestRunner $t) use ($currentHead36, $nextHead36): void {
        $map = libsqlite_release_canonical_evidence()->releaseRunnerUpstreamCanonicalMap([], $currentHead36, $nextHead36);

        $t->same('blocked', $map['status']);
        $t->same(0, $map['artifact_count']);
        $t->same(false, $map['counts_current_baseline']);
        $t->contains('collect at least one current accepted zero-error artifact', $map['next_gate']);
    },
];

for ($i = 1; $i <= 46; $i++) {
    $tests['current next36 canonical release sample ' . $i] = static function (TestRunner $t) use ($currentHead36, $nextHead36, $i): void {
        $testset = $i % 2 === 0 ? 'all' : 'release';
        $records = [
            'current-' . $i => libsqlite_release_canonical_artifact($currentHead36, $testset, [], 1000 + $i),
        ];

        if ($i % 5 === 0) {
            $records['focused-' . $i] = libsqlite_release_canonical_artifact($currentHead36, 'veryquick', ['json' . (100 + $i) . '.test'], 200 + $i);
        }
        if ($i % 7 === 0) {
            $records['next-' . $i] = libsqlite_release_canonical_artifact($nextHead36, $testset, [], 3000 + $i);
        }
        if ($i % 11 === 0) {
            $records['stale-' . $i] = libsqlite_release_canonical_artifact('old-' . $i, $testset, [], 4000 + $i);
        }

        $map = libsqlite_release_canonical_evidence()->releaseRunnerUpstreamCanonicalMap($records, $currentHead36, $nextHead36);

        $t->same(true, $map['counts_current_baseline']);
        $t->true($map['current_countable_count'] >= 1, 'Expected current countable artifact');
        $t->true($map['release_like_count'] >= 1, 'Expected release-like artifact');
        $t->true($map['tests_total'] >= 1000 + $i, 'Expected countable tests to accumulate');
        if ($i % 7 === 0) {
            $t->same('next-canonical-artifact-present', $map['status']);
            $t->same(true, $map['counts_next_artifacts']);
        } elseif ($i % 11 === 0) {
            $t->same('current-canonical-baseline-with-blockers', $map['status']);
            $t->same(1, $map['stale_count']);
        } else {
            $t->same('current-canonical-baseline-only', $map['status']);
            $t->same(true, $map['ready_to_launch_next_guarded_runner']);
        }
    };
}

return $tests;

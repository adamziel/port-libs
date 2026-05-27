<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteUpstreamSuiteEvidence;

function libsqlite_focused_artifact_set_evidence(): SQLiteUpstreamSuiteEvidence
{
    return SQLiteUpstreamSuiteEvidence::fromManifestPath(__DIR__ . '/../UPSTREAM_TEST_MANIFEST.json');
}

/**
 * @param list<string> $patterns
 * @return array<string, mixed>
 */
function libsqlite_focused_artifact(
    string $head,
    array $patterns,
    int $tests = 100,
    int $errors = 0,
    string $testset = 'veryquick',
    int $exit = 0,
    string $manifestUuid = '9ac4a33a2932d353c4871fd8e09c10addf827f1fc3fc9380037d738cf2cd0353'
): array {
    return [
        'status' => $exit === 0 && $errors === 0 ? 'passed' : 'failed',
        'repository_head' => $head,
        'sqlite_commit' => '8f70ec615f4cd247d36f92a22c99f65ebbcc22a7',
        'sqlite_version' => '3.54.0',
        'sqlite_manifest_uuid' => $manifestUuid,
        'requested' => [
            'testset' => $testset,
            'patterns' => $patterns,
        ],
        'results' => [
            'exit' => $exit,
            'tests' => $tests,
            'errors' => $errors,
        ],
        'active_gate' => [
            'status' => 'clear',
            'active_tiers' => [],
        ],
    ];
}

return [
    'admits a single accepted focused artifact set' => static function (TestRunner $t): void {
        $set = libsqlite_focused_artifact_set_evidence()->focusedRunnerArtifactSetAdmission([
            'json-focused' => libsqlite_focused_artifact('accepted-head', ['json101.test', 'json102.test'], 812),
        ], 'accepted-head');

        $t->same('focused-evidence-countable', $set['status']);
        $t->same(1, $set['countable_count']);
        $t->same(['json101.test', 'json102.test'], $set['unique_scripts']);
    },
    'keeps focused artifact set from counting release parity' => static function (TestRunner $t): void {
        $set = libsqlite_focused_artifact_set_evidence()->focusedRunnerArtifactSetAdmission([
            'encoding-focused' => libsqlite_focused_artifact('accepted-head', ['enc.test'], 144),
        ], 'accepted-head');

        $t->same(false, $set['counts_as_release_parity']);
        $t->contains('focused upstream evidence', $set['next_gate']);
    },
    'sums tests from countable focused artifacts' => static function (TestRunner $t): void {
        $set = libsqlite_focused_artifact_set_evidence()->focusedRunnerArtifactSetAdmission([
            'json-focused' => libsqlite_focused_artifact('accepted-head', ['json101.test'], 812),
            'wal-focused' => libsqlite_focused_artifact('accepted-head', ['wal.test'], 144),
        ], 'accepted-head');

        $t->same(956, $set['tests_total']);
        $t->same(0, $set['errors_total']);
    },
    'deduplicates selected focused scripts across artifacts' => static function (TestRunner $t): void {
        $set = libsqlite_focused_artifact_set_evidence()->focusedRunnerArtifactSetAdmission([
            'json-a' => libsqlite_focused_artifact('accepted-head', ['json101.test', 'json102.test'], 10),
            'json-b' => libsqlite_focused_artifact('accepted-head', ['json102.test', 'json103.test'], 10),
        ], 'accepted-head');

        $t->same(3, $set['unique_script_count']);
        $t->same(['json101.test', 'json102.test', 'json103.test'], $set['unique_scripts']);
    },
    'keeps countable labels stable' => static function (TestRunner $t): void {
        $set = libsqlite_focused_artifact_set_evidence()->focusedRunnerArtifactSetAdmission([
            'json-focused' => libsqlite_focused_artifact('accepted-head', ['json101.test']),
            'wal-focused' => libsqlite_focused_artifact('accepted-head', ['wal.test']),
        ], 'accepted-head');

        $t->same(['json-focused', 'wal-focused'], $set['countable_labels']);
    },
    'blocks stale focused artifact heads' => static function (TestRunner $t): void {
        $set = libsqlite_focused_artifact_set_evidence()->focusedRunnerArtifactSetAdmission([
            'stale-focused' => libsqlite_focused_artifact('stale-head', ['json101.test']),
        ], 'accepted-head');

        $t->same('blocked', $set['status']);
        $t->same(['stale-focused'], $set['stale_head_labels']);
    },
    'reports repository head mismatch blocker ids in entries' => static function (TestRunner $t): void {
        $set = libsqlite_focused_artifact_set_evidence()->focusedRunnerArtifactSetAdmission([
            'stale-focused' => libsqlite_focused_artifact('stale-head', ['json101.test']),
        ], 'accepted-head');

        $t->same(['repository-head-mismatch'], $set['entries'][0]['blocker_ids']);
        $t->same('stale-head', $set['entries'][0]['admission']['repository_head']);
    },
    'blocks wrong SQLite manifest focused artifacts' => static function (TestRunner $t): void {
        $set = libsqlite_focused_artifact_set_evidence()->focusedRunnerArtifactSetAdmission([
            'wrong-manifest' => libsqlite_focused_artifact('accepted-head', ['btree01.test'], 91, 0, 'veryquick', 0, 'wrong-manifest'),
        ], 'accepted-head');

        $t->same('blocked', $set['status']);
        $t->same(['sqlite-manifest-uuid-mismatch'], $set['entries'][0]['blocker_ids']);
    },
    'blocks broad release artifacts without focused patterns' => static function (TestRunner $t): void {
        $set = libsqlite_focused_artifact_set_evidence()->focusedRunnerArtifactSetAdmission([
            'release-zero-error' => libsqlite_focused_artifact('accepted-head', [], 22000, 0, 'release'),
        ], 'accepted-head');

        $t->same(['release-zero-error'], $set['broad_artifact_labels']);
        $t->same(['focused-patterns-missing'], $set['entries'][0]['blocker_ids']);
    },
    'routes all artifacts with selected patterns as focused evidence' => static function (TestRunner $t): void {
        $set = libsqlite_focused_artifact_set_evidence()->focusedRunnerArtifactSetAdmission([
            'release-selected' => libsqlite_focused_artifact('accepted-head', ['pager.test'], 300, 0, 'release'),
        ], 'accepted-head');

        $t->same('focused-evidence-countable', $set['status']);
        $t->same('release', $set['entries'][0]['testset']);
    },
    'blocks unsupported focused testsets' => static function (TestRunner $t): void {
        $set = libsqlite_focused_artifact_set_evidence()->focusedRunnerArtifactSetAdmission([
            'make-focused' => libsqlite_focused_artifact('accepted-head', ['pager.test'], 300, 0, 'make-test'),
        ], 'accepted-head');

        $t->same('blocked', $set['status']);
        $t->same(['unsupported-focused-testset'], $set['entries'][0]['blocker_ids']);
    },
    'blocks failed focused artifacts' => static function (TestRunner $t): void {
        $set = libsqlite_focused_artifact_set_evidence()->focusedRunnerArtifactSetAdmission([
            'failed-focused' => libsqlite_focused_artifact('accepted-head', ['pager.test'], 300, 1, 'veryquick', 1),
        ], 'accepted-head');

        $t->same(['failed-focused'], $set['failed_labels']);
        $t->same('failed', $set['entries'][0]['artifact_status']);
    },
    'reports failed artifact-not-passed blocker' => static function (TestRunner $t): void {
        $set = libsqlite_focused_artifact_set_evidence()->focusedRunnerArtifactSetAdmission([
            'failed-focused' => libsqlite_focused_artifact('accepted-head', ['pager.test'], 300, 1, 'veryquick', 1),
        ], 'accepted-head');

        $t->true(in_array('artifact-not-passed', $set['entries'][0]['blocker_ids'], true), 'Expected failed focused artifact to be uncounted');
    },
    'keeps active focused runner artifacts blocked' => static function (TestRunner $t): void {
        $artifact = libsqlite_focused_artifact('accepted-head', ['wal.test'], 300);
        $artifact['active_gate'] = [
            'status' => 'blocked-active-runner',
            'active_tiers' => ['release'],
        ];

        $set = libsqlite_focused_artifact_set_evidence()->focusedRunnerArtifactSetAdmission(['active-focused' => $artifact], 'accepted-head');

        $t->same(['active-focused'], $set['active_labels']);
        $t->same('active-runner-still-running', $set['entries'][0]['blocker_ids'][0]);
    },
    'returns partially countable status for mixed focused sets' => static function (TestRunner $t): void {
        $set = libsqlite_focused_artifact_set_evidence()->focusedRunnerArtifactSetAdmission([
            'json-focused' => libsqlite_focused_artifact('accepted-head', ['json101.test'], 812),
            'stale-focused' => libsqlite_focused_artifact('stale-head', ['wal.test'], 144),
        ], 'accepted-head');

        $t->same('partially-countable-focused-evidence', $set['status']);
        $t->same(1, $set['countable_count']);
    },
    'keeps blocked labels for mixed focused sets' => static function (TestRunner $t): void {
        $set = libsqlite_focused_artifact_set_evidence()->focusedRunnerArtifactSetAdmission([
            'json-focused' => libsqlite_focused_artifact('accepted-head', ['json101.test'], 812),
            'stale-focused' => libsqlite_focused_artifact('stale-head', ['wal.test'], 144),
        ], 'accepted-head');

        $t->same(['stale-focused'], $set['blocked_labels']);
        $t->same(1, $set['blocked_count']);
    },
    'keeps invalid artifact records as explicit blockers' => static function (TestRunner $t): void {
        $set = libsqlite_focused_artifact_set_evidence()->focusedRunnerArtifactSetAdmission([
            'invalid-focused' => 'not-an-artifact',
        ], 'accepted-head');

        $t->same('blocked', $set['status']);
        $t->same(['artifact-record-invalid'], $set['entries'][0]['blocker_ids']);
    },
    'marks empty focused artifact sets as blocked empty' => static function (TestRunner $t): void {
        $set = libsqlite_focused_artifact_set_evidence()->focusedRunnerArtifactSetAdmission([], 'accepted-head');

        $t->same('blocked-empty-focused-artifact-set', $set['status']);
        $t->same(0, $set['artifact_count']);
    },
    'uses numeric labels for unkeyed focused artifacts' => static function (TestRunner $t): void {
        $set = libsqlite_focused_artifact_set_evidence()->focusedRunnerArtifactSetAdmission([
            libsqlite_focused_artifact('accepted-head', ['json101.test']),
        ], 'accepted-head');

        $t->same(['artifact-0'], $set['countable_labels']);
        $t->same('artifact-0', $set['entries'][0]['label']);
    },
    'preserves artifact entry patterns' => static function (TestRunner $t): void {
        $set = libsqlite_focused_artifact_set_evidence()->focusedRunnerArtifactSetAdmission([
            'json-focused' => libsqlite_focused_artifact('accepted-head', ['json101.test', 'json102.test']),
        ], 'accepted-head');

        $t->same(['json101.test', 'json102.test'], $set['entries'][0]['patterns']);
        $t->same(2, $set['entries'][0]['admission']['pattern_count']);
    },
    'preserves artifact entry result totals' => static function (TestRunner $t): void {
        $set = libsqlite_focused_artifact_set_evidence()->focusedRunnerArtifactSetAdmission([
            'json-focused' => libsqlite_focused_artifact('accepted-head', ['json101.test'], 812),
        ], 'accepted-head');

        $t->same(812, $set['entries'][0]['tests']);
        $t->same(0, $set['entries'][0]['errors']);
    },
    'sorts unique selected scripts deterministically' => static function (TestRunner $t): void {
        $set = libsqlite_focused_artifact_set_evidence()->focusedRunnerArtifactSetAdmission([
            'mixed-focused' => libsqlite_focused_artifact('accepted-head', ['wal.test', 'json101.test', 'btree01.test']),
        ], 'accepted-head');

        $t->same(['btree01.test', 'json101.test', 'wal.test'], $set['unique_scripts']);
    },
    'keeps broad artifact tests out of focused totals' => static function (TestRunner $t): void {
        $set = libsqlite_focused_artifact_set_evidence()->focusedRunnerArtifactSetAdmission([
            'json-focused' => libsqlite_focused_artifact('accepted-head', ['json101.test'], 812),
            'release-zero-error' => libsqlite_focused_artifact('accepted-head', [], 22000, 0, 'release'),
        ], 'accepted-head');

        $t->same(812, $set['tests_total']);
        $t->same(1, $set['broad_artifact_count']);
    },
    'keeps blocked artifacts out of unique script totals' => static function (TestRunner $t): void {
        $set = libsqlite_focused_artifact_set_evidence()->focusedRunnerArtifactSetAdmission([
            'json-focused' => libsqlite_focused_artifact('accepted-head', ['json101.test'], 812),
            'stale-focused' => libsqlite_focused_artifact('stale-head', ['wal.test'], 144),
        ], 'accepted-head');

        $t->same(1, $set['unique_script_count']);
        $t->same(['json101.test'], $set['unique_scripts']);
    },
    'returns dependency closure for focused artifact set admission' => static function (TestRunner $t): void {
        $set = libsqlite_focused_artifact_set_evidence()->focusedRunnerArtifactSetAdmission([
            'json-focused' => libsqlite_focused_artifact('accepted-head', ['json101.test']),
        ], 'accepted-head');

        $t->contains('no new support component needed', $set['dependency_closure']);
        $t->contains('focused artifact-set admission', $set['dependency_closure']);
    },
    'returns countability blocker guidance when no focused artifact counts' => static function (TestRunner $t): void {
        $set = libsqlite_focused_artifact_set_evidence()->focusedRunnerArtifactSetAdmission([
            'stale-focused' => libsqlite_focused_artifact('stale-head', ['wal.test'], 144),
        ], 'accepted-head');

        $t->contains('do not count this focused artifact set', $set['next_gate']);
        $t->same(0, $set['countable_count']);
    },
];

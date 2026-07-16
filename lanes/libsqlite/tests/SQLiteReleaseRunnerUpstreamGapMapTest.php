<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteUpstreamSuiteEvidence;

function libsqlite_gap_map_evidence(): SQLiteUpstreamSuiteEvidence
{
    return SQLiteUpstreamSuiteEvidence::fromManifestPath(__DIR__ . '/../UPSTREAM_TEST_MANIFEST.json');
}

function libsqlite_gap_map_artifact(
    string $head,
    string $label,
    string $testset,
    array $patterns,
    int $tests = 128
): array {
    $patternText = $patterns === [] ? 'none' : implode('`, `', $patterns);
    $audit = "# SQLite Tcl Bounded Runner Evidence - {$label}\n\n"
        . "- Repository HEAD: `{$head}`\n"
        . "- SQLite git commit: `8f70ec615f4cd247d36f92a22c99f65ebbcc22a7`\n"
        . "- SQLite VERSION: `3.54.0`\n"
        . "- SQLite manifest UUID: `9ac4a33a2932d353c4871fd8e09c10addf827f1fc3fc9380037d738cf2cd0353`\n"
        . "- Scratch: `/tmp/libsqlite-gap49`\n"
        . "- Log: `/tmp/libsqlite-gap49.log`\n"
        . "- Testset: `{$testset}`\n"
        . "- Jobs: `1`\n"
        . "- Timeout seconds: `900`\n"
        . "- Patterns: `{$patternText}`\n"
        . "- Exit: `0`\n"
        . "- Parsed errors: `0`\n"
        . "- Parsed tests: `{$tests}`\n";

    return libsqlite_gap_map_evidence()->boundedRunnerArtifactRecord($audit);
}

function libsqlite_gap_map_focused_output(int $assertions = 64, int $failures = 0, int $files = 1): string
{
    return "Focused test run: {$files} selected test files (root lock skipped)\n"
        . "{$files} test files, {$assertions} assertions, {$failures} failures";
}

$currentHead49 = '28488284c6b42b08db024e7e34c788f71b24a201';
$nextHead49 = 'current-next49-gap-map-head';
$focusedPath49 = 'lanes/libsqlite/tests/SQLiteReleaseRunnerUpstreamGapMapTest.php';
$nonOverlap49 = 'Avoids accepted next38 failure-ledger admission, next37 gap proof, next34 denominator audit, and all accepted SQL/JSON/WAL/B-tree/VFS behavior clusters; maps only current artifact to next focused runner target gaps.';

return [
    'current next49 maps current release artifact to ready focused next targets' => static function (TestRunner $t) use ($currentHead49, $nextHead49, $focusedPath49, $nonOverlap49): void {
        $artifacts = [
            libsqlite_gap_map_artifact($currentHead49, 'current-release', 'release', ['fts5aux.test'], 4096),
            libsqlite_gap_map_artifact($currentHead49, 'current-json', 'veryquick', ['json101.test'], 48),
        ];
        $targets = [
            'jsonb-malformed-planner' => [
                'testset' => 'veryquick',
                'scripts' => ['json102.test', 'jsonb01.test'],
                'priority' => 'high',
                'reason' => 'next focused JSONB malformed planner gap',
            ],
            'wal-restart-boundary' => [
                'testset' => 'veryquick',
                'scripts' => ['wal.test'],
                'priority' => 'high',
                'reason' => 'next focused WAL restart gap',
            ],
        ];

        $record = libsqlite_gap_map_evidence()->releaseRunnerUpstreamGapMap(
            $artifacts,
            $targets,
            $currentHead49,
            $nextHead49,
            17920,
            $focusedPath49,
            libsqlite_gap_map_focused_output(64),
            $nonOverlap49
        );

        $t->same('current-next49-gap-map-ready', $record['status']);
        $t->same($currentHead49, $record['current_accepted_head']);
        $t->same($nextHead49, $record['next_accepted_head']);
        $t->same(2, $record['current_countable_artifact_count']);
        $t->same(0, $record['current_blocked_artifact_count']);
        $t->same(1, $record['current_testset_counts']['release']);
        $t->same(1, $record['current_testset_counts']['veryquick']);
        $t->same(2, $record['target_count']);
        $t->same(3, $record['target_script_count']);
        $t->same(['json102.test', 'jsonb01.test', 'wal.test'], $record['target_scripts']);
        $t->same(2, $record['ready_target_count']);
        $t->same(['jsonb-malformed-planner', 'wal-restart-boundary'], $record['ready_target_labels']);
        $t->same(0, $record['blocked_target_count']);
        $t->same(0, $record['covered_target_count']);
        $t->same('clear', $record['active_runner_status']);
        $t->same(0, $record['active_runner_count']);
        $t->same('admitted', $record['php_pass_admission']['status']);
        $t->same(64, $record['php_pass_delta']);
        $t->same(17984, $record['next_php_pass']);
        $t->same(0, $record['global_blocker_count']);
        $t->same(true, $record['ready_to_launch_next_guarded_runner']);
        $t->same(false, $record['counts_as_release_parity']);
        $t->same(true, $record['counts_as_gap_map']);
        $t->contains('guarded runner', $record['next_gate']);
        $t->contains('no new support component needed', $record['dependency_closure']);
    },
    'current next49 marks targets already covered by current focused artifacts' => static function (TestRunner $t) use ($currentHead49, $nextHead49, $focusedPath49, $nonOverlap49): void {
        $artifacts = [
            libsqlite_gap_map_artifact($currentHead49, 'current-json', 'veryquick', ['json101.test', 'json102.test'], 54),
        ];
        $targets = [
            'json-already-covered' => [
                'testset' => 'veryquick',
                'scripts' => ['json101.test', 'json102.test'],
                'priority' => 'normal',
            ],
        ];

        $record = libsqlite_gap_map_evidence()->releaseRunnerUpstreamGapMap(
            $artifacts,
            $targets,
            $currentHead49,
            $nextHead49,
            17920,
            $focusedPath49,
            libsqlite_gap_map_focused_output(59),
            $nonOverlap49
        );

        $t->same('current-next49-targets-already-covered', $record['status']);
        $t->same(0, $record['ready_target_count']);
        $t->same(1, $record['covered_target_count']);
        $t->same(['json-already-covered'], $record['covered_target_labels']);
        $t->same('current-artifact-already-covers-target', $record['targets'][0]['status']);
        $t->same('covered', $record['targets'][0]['script_coverage']);
        $t->same(['json101.test', 'json102.test'], $record['targets'][0]['covered_scripts']);
        $t->same([], $record['targets'][0]['missing_scripts']);
        $t->same(false, $record['ready_to_launch_next_guarded_runner']);
        $t->same(59, $record['php_pass_delta']);
    },
    'current next49 blocks release target without release tier baseline' => static function (TestRunner $t) use ($currentHead49, $nextHead49, $focusedPath49, $nonOverlap49): void {
        $artifacts = [
            libsqlite_gap_map_artifact($currentHead49, 'current-focused', 'veryquick', ['json101.test'], 21),
        ];
        $targets = [
            'full-release-map' => [
                'testset' => 'release',
                'scripts' => ['fts5aux.test'],
                'priority' => 'release',
            ],
        ];

        $record = libsqlite_gap_map_evidence()->releaseRunnerUpstreamGapMap(
            $artifacts,
            $targets,
            $currentHead49,
            $nextHead49,
            17920,
            $focusedPath49,
            libsqlite_gap_map_focused_output(55),
            $nonOverlap49
        );

        $t->same('current-next49-gap-map-partial', $record['status']);
        $t->same(0, $record['ready_target_count']);
        $t->same(1, $record['blocked_target_count']);
        $t->same(['full-release-map'], $record['blocked_target_labels']);
        $t->same(['current-release-tier-baseline-missing'], $record['targets'][0]['blocker_ids']);
        $t->same('blocked', $record['targets'][0]['status']);
        $t->same(false, $record['ready_to_launch_next_guarded_runner']);
        $t->contains('repair blocked targets', $record['next_gate']);
    },
    'current next49 blocks non hydrated next target scripts' => static function (TestRunner $t) use ($currentHead49, $nextHead49, $focusedPath49, $nonOverlap49): void {
        $artifacts = [
            libsqlite_gap_map_artifact($currentHead49, 'current-release', 'release', ['fts5aux.test'], 4096),
        ];
        $targets = [
            'missing-hydration' => [
                'testset' => 'veryquick',
                'scripts' => ['pager1.test', 'corrupt.test'],
                'hydrated' => false,
            ],
        ];

        $record = libsqlite_gap_map_evidence()->releaseRunnerUpstreamGapMap(
            $artifacts,
            $targets,
            $currentHead49,
            $nextHead49,
            17920,
            $focusedPath49,
            libsqlite_gap_map_focused_output(52),
            $nonOverlap49
        );

        $t->same('current-next49-gap-map-partial', $record['status']);
        $t->same(['target-scripts-not-hydrated'], $record['targets'][0]['blocker_ids']);
        $t->same('uncovered', $record['targets'][0]['script_coverage']);
        $t->same(2, $record['targets'][0]['missing_script_count']);
        $t->same(['corrupt.test', 'pager1.test'], $record['targets'][0]['missing_scripts']);
        $t->same(0, $record['ready_target_count']);
        $t->same(1, $record['blocked_target_count']);
    },
    'current next49 blocks duplicate broad runner snapshot' => static function (TestRunner $t) use ($currentHead49, $nextHead49, $focusedPath49, $nonOverlap49): void {
        $artifacts = [
            libsqlite_gap_map_artifact($currentHead49, 'current-release', 'release', ['fts5aux.test'], 4096),
        ];
        $targets = [
            'wal-target' => [
                'testset' => 'veryquick',
                'scripts' => ['wal.test'],
            ],
        ];

        $record = libsqlite_gap_map_evidence()->releaseRunnerUpstreamGapMap(
            $artifacts,
            $targets,
            $currentHead49,
            $nextHead49,
            17920,
            $focusedPath49,
            libsqlite_gap_map_focused_output(51),
            $nonOverlap49,
            '12345 ./testfixture ../libsqlite/test/testrunner.tcl --jobs 2 --stop-on-error release'
        );

        $t->same('blocked', $record['status']);
        $t->same('blocked-active-runner', $record['active_runner_status']);
        $t->same(1, $record['active_runner_count']);
        $t->same(1, $record['global_blocker_count']);
        $t->same('duplicate-broad-runner-active', $record['global_blockers'][0]['id']);
        $t->same(false, $record['ready_to_launch_next_guarded_runner']);
    },
    'current next49 blocks stale or failed current artifacts globally' => static function (TestRunner $t) use ($currentHead49, $nextHead49, $focusedPath49, $nonOverlap49): void {
        $artifacts = [
            libsqlite_gap_map_artifact('stale-head', 'stale-release', 'release', ['fts5aux.test'], 4096),
        ];
        $targets = [
            'json-target' => [
                'testset' => 'veryquick',
                'scripts' => ['json101.test'],
            ],
        ];

        $record = libsqlite_gap_map_evidence()->releaseRunnerUpstreamGapMap(
            $artifacts,
            $targets,
            $currentHead49,
            $nextHead49,
            17920,
            $focusedPath49,
            libsqlite_gap_map_focused_output(50),
            $nonOverlap49
        );

        $t->same('blocked', $record['status']);
        $t->same(0, $record['current_countable_artifact_count']);
        $t->same(1, $record['current_blocked_artifact_count']);
        $t->same(1, $record['global_blocker_count']);
        $t->same('current-countable-artifact-missing', $record['global_blockers'][0]['id']);
        $t->same(false, $record['counts_as_gap_map']);
    },
    'current next49 blocks php pass admission without focused output' => static function (TestRunner $t) use ($currentHead49, $nextHead49, $focusedPath49, $nonOverlap49): void {
        $artifacts = [
            libsqlite_gap_map_artifact($currentHead49, 'current-release', 'release', ['fts5aux.test'], 4096),
        ];
        $targets = [
            'json-target' => [
                'testset' => 'veryquick',
                'scripts' => ['json101.test'],
            ],
        ];

        $record = libsqlite_gap_map_evidence()->releaseRunnerUpstreamGapMap(
            $artifacts,
            $targets,
            $currentHead49,
            $nextHead49,
            17920,
            $focusedPath49,
            "1 test files, 50 assertions, 0 failures",
            $nonOverlap49
        );

        $t->same('blocked', $record['status']);
        $t->same('blocked', $record['php_pass_admission']['status']);
        $t->same(0, $record['php_pass_delta']);
        $t->same(1, $record['global_blocker_count']);
        $t->same('php-pass-admission-blocked', $record['global_blockers'][0]['id']);
        $t->contains('missing focused TestRunner output', $record['global_blockers'][0]['evidence']);
    },
    'current next49 reports partial script coverage for mixed target' => static function (TestRunner $t) use ($currentHead49, $nextHead49, $focusedPath49, $nonOverlap49): void {
        $artifacts = [
            libsqlite_gap_map_artifact($currentHead49, 'current-json', 'veryquick', ['json101.test'], 48),
        ];
        $targets = [
            'mixed-json-target' => [
                'testset' => 'veryquick',
                'scripts' => ['json101.test', 'json102.test', 'jsonb01.test'],
                'reason' => 'mixed existing and next target map',
            ],
        ];

        $record = libsqlite_gap_map_evidence()->releaseRunnerUpstreamGapMap(
            $artifacts,
            $targets,
            $currentHead49,
            $nextHead49,
            17920,
            $focusedPath49,
            libsqlite_gap_map_focused_output(49),
            $nonOverlap49
        );

        $t->same('current-next49-gap-map-ready', $record['status']);
        $t->same('partial', $record['targets'][0]['script_coverage']);
        $t->same(1, $record['targets'][0]['covered_script_count']);
        $t->same(['json101.test'], $record['targets'][0]['covered_scripts']);
        $t->same(2, $record['targets'][0]['missing_script_count']);
        $t->same(['json102.test', 'jsonb01.test'], $record['targets'][0]['missing_scripts']);
        $t->contains('json102.test', $record['targets'][0]['command']);
    },
    'current next49 validates required inputs' => static function (TestRunner $t) use ($currentHead49, $nextHead49, $focusedPath49, $nonOverlap49): void {
        $evidence = libsqlite_gap_map_evidence();
        $artifact = libsqlite_gap_map_artifact($currentHead49, 'current-release', 'release', ['fts5aux.test'], 4096);
        $targets = ['json' => ['testset' => 'veryquick', 'scripts' => ['json101.test']]];

        $t->throws(InvalidArgumentException::class, static fn () => $evidence->releaseRunnerUpstreamGapMap([$artifact], $targets, '', $nextHead49, 1, $focusedPath49, libsqlite_gap_map_focused_output(), $nonOverlap49));
        $t->throws(InvalidArgumentException::class, static fn () => $evidence->releaseRunnerUpstreamGapMap([$artifact], $targets, $currentHead49, '', 1, $focusedPath49, libsqlite_gap_map_focused_output(), $nonOverlap49));
        $t->throws(InvalidArgumentException::class, static fn () => $evidence->releaseRunnerUpstreamGapMap([$artifact], [], $currentHead49, $nextHead49, 1, $focusedPath49, libsqlite_gap_map_focused_output(), $nonOverlap49));
        $t->throws(InvalidArgumentException::class, static fn () => $evidence->releaseRunnerUpstreamGapMap([$artifact], $targets, $currentHead49, $nextHead49, -1, $focusedPath49, libsqlite_gap_map_focused_output(), $nonOverlap49));
    },
];

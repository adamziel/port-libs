<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteUpstreamSuiteEvidence;

return [
    'blocks release runner artifact directory scans when the directory is missing' => static function (TestRunner $t): void {
        $evidence = SQLiteUpstreamSuiteEvidence::fromManifestPath(__DIR__ . '/../UPSTREAM_TEST_MANIFEST.json');
        $record = $evidence->boundedRunnerArtifactDirectoryRecord(
            sys_get_temp_dir() . '/missing-libsqlite-runner-artifacts-' . bin2hex(random_bytes(4)),
            'accepted-head'
        );

        $t->same('blocked-missing-artifact-directory', $record['status']);
        $t->same(0, $record['artifact_count']);
        $t->same(0, $record['countable_count']);
        $t->contains('artifact directory', $record['next_gate']);
        $t->contains('no new support component needed', $record['dependency_closure']);
    },
    'marks an empty release runner artifact directory as blocked evidence' => static function (TestRunner $t): void {
        $evidence = SQLiteUpstreamSuiteEvidence::fromManifestPath(__DIR__ . '/../UPSTREAM_TEST_MANIFEST.json');
        $root = sys_get_temp_dir() . '/libsqlite-empty-runner-artifacts-' . bin2hex(random_bytes(4));
        mkdir($root, 0777, true);

        try {
            $record = $evidence->boundedRunnerArtifactDirectoryRecord($root, 'accepted-head');

            $t->same('blocked-empty-artifact-set', $record['status']);
            $t->same(0, $record['artifact_count']);
            $t->same(0, $record['missing_log_count']);
            $t->same([], $record['entries']);
            $t->contains('audit Markdown files', $record['next_gate']);
        } finally {
            @rmdir($root);
        }
    },
    'discovers a countable release runner artifact and pairs the recorded log basename' => static function (TestRunner $t): void {
        $evidence = SQLiteUpstreamSuiteEvidence::fromManifestPath(__DIR__ . '/../UPSTREAM_TEST_MANIFEST.json');
        $root = sys_get_temp_dir() . '/libsqlite-countable-runner-artifacts-' . bin2hex(random_bytes(4));
        mkdir($root, 0777, true);
        $auditPath = $root . '/release-runner.md';
        $logPath = $root . '/release-runner.log';
        file_put_contents($auditPath, <<<'MD'
# SQLite Tcl Bounded Runner Evidence - libsqlite-release-runner-20260527T170000Z

- Repository HEAD: `accepted-head`
- Scratch: `/tmp/libsqlite-release-runner`
- Log: `/tmp/release-runner.log`
- SQLite git commit: `8f70ec615f4cd247d36f92a22c99f65ebbcc22a7`
- SQLite VERSION: `3.54.0`
- SQLite manifest UUID: `9ac4a33a2932d353c4871fd8e09c10addf827f1fc3fc9380037d738cf2cd0353`
- Testset: `release`
- Jobs: `2`
- Timeout seconds: `7200`
- Patterns: none
- Exit: `0`
- Elapsed seconds: `211`
- Parsed summary: `0 errors out of 22000 tests`
- Runner time: `00:03:31`
MD);
        file_put_contents($logPath, "03:31 tcl(22000/22000) r0\n");

        try {
            $record = $evidence->boundedRunnerArtifactDirectoryRecord($root, 'accepted-head');

            $t->same('countable', $record['status']);
            $t->same(1, $record['artifact_count']);
            $t->same(1, $record['countable_count']);
            $t->same(0, $record['blocked_count']);
            $t->same(0, $record['missing_log_count']);
            $t->same(['libsqlite-release-runner-20260527T170000Z'], $record['countable_labels']);
            $t->same(22000, $record['tests_total']);
            $t->same(0, $record['errors_total']);
            $t->same($logPath, $record['entries'][0]['gate']['artifact']['stdout_path']);
            $t->contains('countable zero-error artifact entries', $record['next_gate']);
        } finally {
            @unlink($auditPath);
            @unlink($logPath);
            @rmdir($root);
        }
    },
    'keeps stale release runner directory artifacts blocked by accepted head provenance' => static function (TestRunner $t): void {
        $evidence = SQLiteUpstreamSuiteEvidence::fromManifestPath(__DIR__ . '/../UPSTREAM_TEST_MANIFEST.json');
        $root = sys_get_temp_dir() . '/libsqlite-stale-runner-artifacts-' . bin2hex(random_bytes(4));
        mkdir($root, 0777, true);
        $auditPath = $root . '/stale-release.md';
        file_put_contents($auditPath, <<<'MD'
# SQLite Tcl Bounded Runner Evidence - libsqlite-stale-release-20260527T170000Z

- Repository HEAD: `stale-head`
- Log: `/tmp/stale-release.log`
- SQLite git commit: `8f70ec615f4cd247d36f92a22c99f65ebbcc22a7`
- SQLite VERSION: `3.54.0`
- SQLite manifest UUID: `9ac4a33a2932d353c4871fd8e09c10addf827f1fc3fc9380037d738cf2cd0353`
- Testset: `release`
- Exit: `0`
- Parsed summary: `0 errors out of 22000 tests`
MD);

        try {
            $record = $evidence->boundedRunnerArtifactDirectoryRecord($root, 'accepted-head');

            $t->same('blocked', $record['status']);
            $t->same(1, $record['artifact_count']);
            $t->same(0, $record['countable_count']);
            $t->same(1, $record['blocked_count']);
            $t->same(1, $record['missing_log_count']);
            $t->same(['libsqlite-stale-release-20260527T170000Z'], $record['blocked_labels']);
            $t->same(['repository-head-mismatch'], $record['entries'][0]['blocker_ids']);
            $t->same(null, $record['entries'][0]['gate']['artifact']['stdout_path']);
            $t->contains('rerun or repair', $record['next_gate']);
        } finally {
            @unlink($auditPath);
            @rmdir($root);
        }
    },
    'preserves active runner blockers discovered while scanning artifact directories' => static function (TestRunner $t): void {
        $evidence = SQLiteUpstreamSuiteEvidence::fromManifestPath(__DIR__ . '/../UPSTREAM_TEST_MANIFEST.json');
        $root = sys_get_temp_dir() . '/libsqlite-active-runner-artifacts-' . bin2hex(random_bytes(4));
        mkdir($root, 0777, true);
        $auditPath = $root . '/active-release.md';
        $logPath = $root . '/active-release.log';
        file_put_contents($auditPath, <<<'MD'
# SQLite Tcl Bounded Runner Evidence - libsqlite-active-release-20260527T170000Z

- Repository HEAD: `accepted-head`
- Log: `/tmp/active-release.log`
- SQLite git commit: `8f70ec615f4cd247d36f92a22c99f65ebbcc22a7`
- SQLite VERSION: `3.54.0`
- SQLite manifest UUID: `9ac4a33a2932d353c4871fd8e09c10addf827f1fc3fc9380037d738cf2cd0353`
- Testset: `release`
- Jobs: `2`
- Timeout seconds: `7200`
MD);
        file_put_contents($logPath, "04:00 tcl(1200/22000) r2 ETC 01:01:00\n");
        $snapshot = '777001 777000 04:00 ./testfixture ../libsqlite/test/testrunner.tcl --jobs 2 --stop-on-error release';

        try {
            $record = $evidence->boundedRunnerArtifactDirectoryRecord($root, 'accepted-head', $snapshot);

            $t->same('blocked', $record['status']);
            $t->same(1, $record['artifact_count']);
            $t->same(0, $record['countable_count']);
            $t->same(1, $record['active_count']);
            $t->same(['libsqlite-active-release-20260527T170000Z'], $record['active_labels']);
            $t->same('active-runner-in-progress', $record['entries'][0]['artifact_status']);
            $t->same('active-runner-still-running', $record['entries'][0]['blocker_ids'][0]);
            $t->same(['release'], $record['entries'][0]['gate']['artifact']['active_gate']['active_tiers']);
            $t->same(1200, $record['entries'][0]['gate']['artifact']['progress']['completed']);
            $t->contains('rerun or repair', $record['next_gate']);
        } finally {
            @unlink($auditPath);
            @unlink($logPath);
            @rmdir($root);
        }
    },
];

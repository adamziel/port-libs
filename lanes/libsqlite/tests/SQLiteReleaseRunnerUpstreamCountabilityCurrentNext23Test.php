<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteUpstreamSuiteEvidence;

return [
    'current next23 records guarded runner disk gate as non-countable upstream evidence' => static function (TestRunner $t): void {
        $evidence = SQLiteUpstreamSuiteEvidence::fromManifestPath(__DIR__ . '/../UPSTREAM_TEST_MANIFEST.json');
        $output = "[2026-05-27T19:51:43Z] libsqlite-current-next23-countability start\n"
            . "[2026-05-27T19:51:43Z] stop: root free 39411300 KiB < 80 GiB\n";
        $snapshot = "1450820 1447663 Ss 00:00 0.0 /bin/bash -c ps -eo pid,ppid,stat,etime,pcpu,args | rg 'testfixture|run-sqlite-tcl-bounded-runner'\n"
            . "1450824 1450820 S 00:00 0.0 rg testfixture|run-sqlite-tcl-bounded-runner\n";

        $record = $evidence->guardedRunnerPreflightRecord($output, $snapshot, true, 1, dirname(__DIR__, 3));

        $t->same('blocked-disk-gate', $record['status']);
        $t->same('libsqlite-current-next23-countability', $record['runner_label']);
        $t->same(true, $record['started']);
        $t->same(true, $record['supervisor_approved']);
        $t->same(1, $record['jobs']);
        $t->same(2, $record['line_count']);
        $t->same(false, $record['counts_as_release_parity']);
        $t->same('clear', $record['active_gate_status']);
        $t->same(0, $record['active_count']);
        $t->same('blocked', $record['command_manifest_status']);
        $t->same(7, $record['command_count']);
        $t->same(0, $record['runnable_command_count']);
        $t->same(7, $record['blocked_command_count']);
        $t->true($record['blocker_count'] >= 2, 'Expected disk gate plus command manifest blockers');
        $t->same('disk-gate-root-free-space', $record['blockers'][0]['id']);
        $t->same('[2026-05-27T19:51:43Z] stop: root free 39411300 KiB < 80 GiB', $record['blockers'][0]['evidence']);
        $t->same(39411300, $record['blockers'][0]['root_free_kib']);
        $t->same(80, $record['blockers'][0]['required_gib']);
        $t->same(83886080, $record['blockers'][0]['required_kib']);
        $t->same(44474780, $record['blockers'][0]['shortfall_kib']);
        $t->true(in_array('command-manifest-not-ready', array_column($record['blockers'], 'id'), true), 'Expected command manifest blocker to remain visible');
        $t->same('blocked', $record['launch_gate']['status']);
        $t->same(false, $record['launch_gate']['launch_allowed']);
        $t->same(true, $record['launch_gate']['supervisor_approved']);
        $t->same(1, $record['launch_gate']['jobs']);
        $t->same('clear', $record['launch_gate']['active_gate']['status']);
        $t->contains('free enough root disk', $record['next_gate']);
        $t->contains('do not count a skipped preflight as SQLite release parity', $record['next_gate']);
        $t->contains('no new support component needed', $record['dependency_closure']);
    },
    'current next23 keeps supervisor and duplicate runner gates ahead of disk countability' => static function (TestRunner $t): void {
        $evidence = SQLiteUpstreamSuiteEvidence::fromManifestPath(__DIR__ . '/../UPSTREAM_TEST_MANIFEST.json');
        $output = "[2026-05-27T20:02:11Z] libsqlite-current-next23-countability start\n"
            . "[2026-05-27T20:02:11Z] stop: root free 39411300 KiB < 80 GiB\n";
        $snapshot = "577248 1 02:16 bash scripts/run-sqlite-tcl-bounded-runner.sh libsqlite-release audits/libsqlite-release.md .tmp .log release 2 7200\n"
            . "577297 577296 02:14 ./testfixture ../src/test/testrunner.tcl --jobs 2 --stop-on-error release\n";

        $record = $evidence->guardedRunnerPreflightRecord($output, $snapshot, false, 2, dirname(__DIR__, 3));
        $blockerIds = array_column($record['blockers'], 'id');

        $t->same('blocked-disk-gate', $record['status']);
        $t->same('libsqlite-current-next23-countability', $record['runner_label']);
        $t->same(false, $record['supervisor_approved']);
        $t->same(2, $record['jobs']);
        $t->same('blocked-active-runner', $record['active_gate_status']);
        $t->same(2, $record['active_count']);
        $t->same('disk-gate-root-free-space', $blockerIds[0]);
        $t->true(in_array('supervisor-approval-required', $blockerIds, true), 'Expected supervisor approval blocker');
        $t->true(in_array('active-runner-still-running', $blockerIds, true), 'Expected active runner blocker');
        $t->true(in_array('command-manifest-not-ready', $blockerIds, true), 'Expected command manifest blocker');
        $t->same(['release'], $record['launch_gate']['active_gate']['active_tiers']);
        $t->same(false, $record['launch_gate']['launch_allowed']);
        $t->same(3, $record['launch_gate']['blocker_count']);
        $t->same(4, $record['blocker_count']);
        $t->same(false, $record['counts_as_release_parity']);
        $t->contains('SQLite release parity', $record['next_gate']);
    },
    'current next23 reports launch ready when guarded preflight output has no stop blocker' => static function (TestRunner $t): void {
        $evidence = SQLiteUpstreamSuiteEvidence::fromManifestPath(__DIR__ . '/../UPSTREAM_TEST_MANIFEST.json');
        $root = sys_get_temp_dir() . '/libsqlite-current-next23-launch-ready-' . bin2hex(random_bytes(4));
        $buildDirectory = $root . '/.upstream-cache/libsqlite-build-port-libsqlite';
        $testDirectory = $root . '/.upstream-cache/libsqlite/test';
        $mptestDirectory = $root . '/.upstream-cache/libsqlite/mptest';
        mkdir($buildDirectory, 0777, true);
        mkdir($testDirectory, 0777, true);
        mkdir($mptestDirectory, 0777, true);
        file_put_contents($buildDirectory . '/testfixture', "#!/bin/sh\n");
        chmod($buildDirectory . '/testfixture', 0755);
        file_put_contents($buildDirectory . '/Makefile', "test:\n\t@true\nmptest:\n\t@true\n");
        file_put_contents($testDirectory . '/testrunner.tcl', '# current next23 fixture');

        $source = '';
        for ($i = 1; $i <= 58; $i++) {
            $source .= sprintf("test_suite \"suite%02d\"\n", $i);
        }
        file_put_contents($testDirectory . '/permutations.test', $source);
        foreach ($evidence->runnerCoverageAudit()['pattern_scripts'] as $pattern) {
            file_put_contents($testDirectory . '/' . str_replace('*', '01', $pattern), '# wildcard fixture');
        }

        try {
            $record = $evidence->guardedRunnerPreflightRecord(
                "[2026-05-27T20:05:00Z] libsqlite-current-next23-countability start\n",
                '',
                true,
                3,
                $root
            );

            $t->same('launch-ready', $record['status']);
            $t->same('libsqlite-current-next23-countability', $record['runner_label']);
            $t->same(true, $record['started']);
            $t->same(null, $record['disk_gate']);
            $t->same(0, $record['blocker_count']);
            $t->same('clear', $record['active_gate_status']);
            $t->same('ready', $record['command_manifest_status']);
            $t->same(7, $record['command_count']);
            $t->same(7, $record['runnable_command_count']);
            $t->same(0, $record['blocked_command_count']);
            $t->same(true, $record['launch_gate']['launch_allowed']);
            $t->same('launch-allowed', $record['launch_gate']['status']);
            $t->same(3, $record['launch_gate']['jobs']);
            $t->contains('--jobs 3 --stop-on-error all', (string) $record['launch_gate']['next_command']);
            $t->contains('launch one guarded runner', $record['next_gate']);
            $t->same(false, $record['counts_as_release_parity']);
        } finally {
            foreach (glob($testDirectory . '/*') ?: [] as $file) {
                unlink($file);
            }
            foreach (glob($buildDirectory . '/*') ?: [] as $file) {
                unlink($file);
            }
            @rmdir($mptestDirectory);
            @rmdir($testDirectory);
            @rmdir($root . '/.upstream-cache/libsqlite');
            @rmdir($buildDirectory);
            @rmdir($root . '/.upstream-cache');
            @rmdir($root);
        }
    },
    'current next23 rejects invalid guarded runner job counts' => static function (TestRunner $t): void {
        $evidence = SQLiteUpstreamSuiteEvidence::fromManifestPath(__DIR__ . '/../UPSTREAM_TEST_MANIFEST.json');

        $t->throws(
            InvalidArgumentException::class,
            static fn () => $evidence->guardedRunnerPreflightRecord('output', '', true, 0, dirname(__DIR__, 3))
        );
    },
];

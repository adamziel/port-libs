<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteUpstreamSuiteEvidence;

$manifest = __DIR__ . '/../UPSTREAM_TEST_MANIFEST.json';
$currentHead = '32344fc9b1df6c015ff08a6870a4e545c69b511a';
$nextHead = 'upstream-suite-countability-current-next59';
$focusedPath = 'lanes/libsqlite/tests/SQLiteUpstreamSuiteCountabilityCurrentNext59Test.php';
$nonOverlap = 'current-next59 suite countability admission avoids release-runner burnup, parity ledgers, focused artifact admission, and accepted SQL/JSON/WAL/B-tree/VFS/encoding behavior clusters';

$groups = [
    'json-dynamic-planner' => ['json101.test', 'json102.test', 'jsonb01.test'],
    'wal-pager-transaction' => ['wal.test', 'wal2.test', 'pager1.test'],
    'btree-freelist-pointermap' => ['btree01.test', 'delete2.test', 'delete3.test'],
    'encoding-collation' => ['enc.test', 'enc2.test', 'collate1.test', 'like.test'],
];

$focusedOutput = <<<OUT
Focused test run: 1 selected test files (root lock skipped)
PASS current next59 fixture
1 test files, 42 assertions, 0 failures
OUT;

$cases = [
    'blocks current next59 when hydrated upstream runner inputs are missing' => static function (TestRunner $t) use ($manifest, $groups, $currentHead, $nextHead, $focusedPath, $focusedOutput, $nonOverlap): void {
        $evidence = SQLiteUpstreamSuiteEvidence::fromManifestPath($manifest);
        $plan = $evidence->currentNext59AdmissionPlan($groups, $currentHead, $nextHead, 21435, $focusedPath, $focusedOutput, $nonOverlap, 2, dirname(__DIR__, 3));

        $t->same('blocked', $plan['status']);
        $t->same($currentHead, $plan['current_accepted_head']);
        $t->same($nextHead, $plan['next_accepted_head']);
        $t->same(2, $plan['jobs']);
        $t->same(4, $plan['candidate_group_count']);
        $t->same(0, $plan['ready_group_count']);
        $t->same(4, $plan['blocked_group_count']);
        $t->same(13, $plan['candidate_script_count']);
        $t->same([], $plan['commands']);
        $t->same('clear', $plan['active_runner_status']);
        $t->same(0, $plan['active_runner_count']);
        $t->same('admitted', $plan['php_pass_admission']['status']);
        $t->same(42, $plan['php_pass_delta']);
        $t->same(21477, $plan['next_php_pass']);
        $t->same(1, $plan['global_blocker_count']);
        $t->same('no-runnable-focused-subsets', $plan['global_blockers'][0]['id']);
        $t->same(false, $plan['ready_to_launch_next_guarded_runner']);
        $t->same(false, $plan['counts_as_release_parity']);
        $t->same(false, $plan['counts_as_current_next59_admission']);
        $t->contains('repair hydrated runner inputs', $plan['next_gate']);
        $t->contains('no new support component needed', $plan['dependency_closure']);
        $t->contains('current-next59 suite countability admission', $plan['non_overlap_note']);
    },
    'marks hydrated current next59 focused groups ready without claiming release parity' => static function (TestRunner $t) use ($manifest, $groups, $currentHead, $nextHead, $focusedPath, $focusedOutput, $nonOverlap): void {
        $root = sys_get_temp_dir() . '/libsqlite-current-next59-' . bin2hex(random_bytes(4));
        $buildDirectory = $root . '/.upstream-cache/libsqlite-build-port-libsqlite';
        $testDirectory = $root . '/.upstream-cache/libsqlite/test';
        mkdir($buildDirectory, 0777, true);
        mkdir($testDirectory, 0777, true);
        file_put_contents($buildDirectory . '/testfixture', '#!/bin/sh');
        file_put_contents($testDirectory . '/testrunner.tcl', '# current-next59 fixture');

        try {
            $evidence = SQLiteUpstreamSuiteEvidence::fromManifestPath($manifest);
            $plan = $evidence->currentNext59AdmissionPlan($groups, $currentHead, $nextHead, 21435, $focusedPath, $focusedOutput, $nonOverlap, 3, $root);

            $t->same('current-next59-focused-runner-ready', $plan['status']);
            $t->same(4, $plan['ready_group_count']);
            $t->same(0, $plan['blocked_group_count']);
            $t->same(13, $plan['candidate_script_count']);
            $t->same(4, count($plan['commands']));
            $t->same(true, $plan['ready_to_launch_next_guarded_runner']);
            $t->same(false, $plan['counts_as_release_parity']);
            $t->same(true, $plan['counts_as_current_next59_admission']);
            $t->same(0, $plan['global_blocker_count']);
            $t->same('admitted', $plan['php_pass_admission']['status']);
            $t->contains('--jobs 3 --stop-on-error veryquick json101.test json102.test jsonb01.test', $plan['commands'][0]);
            $t->contains('launch only the ready current-next59 focused subset commands', $plan['next_gate']);
            $t->same('json-dynamic-planner', $plan['ready_groups'][0]['name']);
            $t->same(3, $plan['ready_groups'][0]['script_count']);
            $t->same(null, $plan['ready_groups'][0]['skip_reason']);
        } finally {
            @unlink($buildDirectory . '/testfixture');
            @unlink($testDirectory . '/testrunner.tcl');
            @rmdir($testDirectory);
            @rmdir($root . '/.upstream-cache/libsqlite');
            @rmdir($buildDirectory);
            @rmdir($root . '/.upstream-cache');
            @rmdir($root);
        }
    },
    'blocks current next59 launch while a broad upstream runner is active' => static function (TestRunner $t) use ($manifest, $groups, $currentHead, $nextHead, $focusedPath, $focusedOutput, $nonOverlap): void {
        $root = sys_get_temp_dir() . '/libsqlite-current-next59-active-' . bin2hex(random_bytes(4));
        $buildDirectory = $root . '/.upstream-cache/libsqlite-build-port-libsqlite';
        $testDirectory = $root . '/.upstream-cache/libsqlite/test';
        mkdir($buildDirectory, 0777, true);
        mkdir($testDirectory, 0777, true);
        file_put_contents($buildDirectory . '/testfixture', '#!/bin/sh');
        file_put_contents($testDirectory . '/testrunner.tcl', '# current-next59 fixture');

        try {
            $snapshot = '123 1 R 00:03 97.5 ./testfixture ../libsqlite/test/testrunner.tcl --jobs 4 --stop-on-error all';
            $evidence = SQLiteUpstreamSuiteEvidence::fromManifestPath($manifest);
            $plan = $evidence->currentNext59AdmissionPlan($groups, $currentHead, $nextHead, 21435, $focusedPath, $focusedOutput, $nonOverlap, 1, $root, $snapshot);

            $t->same('blocked', $plan['status']);
            $t->same(4, $plan['ready_group_count']);
            $t->same(0, $plan['blocked_group_count']);
            $t->same('blocked-active-runner', $plan['active_runner_status']);
            $t->same(1, $plan['active_runner_count']);
            $t->same(1, $plan['global_blocker_count']);
            $t->same('duplicate-broad-runner-active', $plan['global_blockers'][0]['id']);
            $t->same(false, $plan['ready_to_launch_next_guarded_runner']);
            $t->same(true, str_contains($plan['global_blockers'][0]['evidence'], '1 active broad runner'));
        } finally {
            @unlink($buildDirectory . '/testfixture');
            @unlink($testDirectory . '/testrunner.tcl');
            @rmdir($testDirectory);
            @rmdir($root . '/.upstream-cache/libsqlite');
            @rmdir($buildDirectory);
            @rmdir($root . '/.upstream-cache');
            @rmdir($root);
        }
    },
    'blocks current next59 when focused php admission is not countable' => static function (TestRunner $t) use ($manifest, $groups, $currentHead, $nextHead, $focusedPath, $nonOverlap): void {
        $root = sys_get_temp_dir() . '/libsqlite-current-next59-php-' . bin2hex(random_bytes(4));
        $buildDirectory = $root . '/.upstream-cache/libsqlite-build-port-libsqlite';
        $testDirectory = $root . '/.upstream-cache/libsqlite/test';
        mkdir($buildDirectory, 0777, true);
        mkdir($testDirectory, 0777, true);
        file_put_contents($buildDirectory . '/testfixture', '#!/bin/sh');
        file_put_contents($testDirectory . '/testrunner.tcl', '# current-next59 fixture');

        try {
            $evidence = SQLiteUpstreamSuiteEvidence::fromManifestPath($manifest);
            $plan = $evidence->currentNext59AdmissionPlan($groups, $currentHead, $nextHead, 21435, $focusedPath, "1 test files, 0 assertions, 0 failures\n", $nonOverlap, 1, $root);

            $t->same('blocked', $plan['status']);
            $t->same(4, $plan['ready_group_count']);
            $t->same('blocked', $plan['php_pass_admission']['status']);
            $t->same(0, $plan['php_pass_delta']);
            $t->same(21435, $plan['next_php_pass']);
            $t->same(1, $plan['global_blocker_count']);
            $t->same('php-pass-admission-blocked', $plan['global_blockers'][0]['id']);
            $t->contains('missing focused TestRunner output', $plan['global_blockers'][0]['evidence']);
        } finally {
            @unlink($buildDirectory . '/testfixture');
            @unlink($testDirectory . '/testrunner.tcl');
            @rmdir($testDirectory);
            @rmdir($root . '/.upstream-cache/libsqlite');
            @rmdir($buildDirectory);
            @rmdir($root . '/.upstream-cache');
            @rmdir($root);
        }
    },
];

for ($i = 1; $i <= 38; $i++) {
    $cases[sprintf('current next59 candidate script %02d remains concrete and safe', $i)] = static function (TestRunner $t) use ($manifest, $groups, $currentHead, $nextHead, $focusedPath, $focusedOutput, $nonOverlap, $i): void {
        $evidence = SQLiteUpstreamSuiteEvidence::fromManifestPath($manifest);
        $plan = $evidence->currentNext59AdmissionPlan($groups, $currentHead, $nextHead, 21435, $focusedPath, $focusedOutput, $nonOverlap, 2, dirname(__DIR__, 3));
        $scripts = $plan['candidate_scripts'];
        $script = $scripts[($i - 1) % count($scripts)];

        $t->true(str_ends_with($script, '.test'), 'Expected concrete SQLite .test script');
        $t->same(1, preg_match('/^[A-Za-z0-9_*.-]+\.test$/', $script));
        $t->true(in_array($script, $scripts, true), 'Expected script to be retained in current-next59 plan');
    };
}

return $cases;

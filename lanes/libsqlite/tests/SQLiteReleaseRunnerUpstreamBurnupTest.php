<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteUpstreamSuiteEvidence;

function libsqlite_release_burnup_evidence(): SQLiteUpstreamSuiteEvidence
{
    return SQLiteUpstreamSuiteEvidence::fromManifestPath(__DIR__ . '/../UPSTREAM_TEST_MANIFEST.json');
}

function libsqlite_release_burnup_root(string $label): string
{
    return sys_get_temp_dir() . '/libsqlite-release-burnup-' . $label . '-' . bin2hex(random_bytes(4));
}

function libsqlite_release_burnup_mkdir(string $path): void
{
    if (!is_dir($path)) {
        mkdir($path, 0777, true);
    }
}

function libsqlite_release_burnup_write(string $path, string $contents = '# fixture'): void
{
    libsqlite_release_burnup_mkdir(dirname($path));
    file_put_contents($path, $contents);
}

function libsqlite_release_burnup_cleanup(string $root): void
{
    if (!is_dir($root)) {
        return;
    }

    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );
    foreach ($iterator as $path) {
        if ($path->isDir()) {
            @rmdir($path->getPathname());
        } else {
            @chmod($path->getPathname(), 0666);
            @unlink($path->getPathname());
        }
    }
    @rmdir($root);
}

function libsqlite_release_burnup_fixture(string $label, bool $hydrated = true): string
{
    $root = libsqlite_release_burnup_root($label);
    if (!$hydrated) {
        libsqlite_release_burnup_mkdir($root . '/.upstream-cache/libsqlite');

        return $root;
    }

    $source = $root . '/.upstream-cache/libsqlite';
    $test = $source . '/test';
    $build = $root . '/.upstream-cache/libsqlite-build-port-libsqlite';
    libsqlite_release_burnup_mkdir($test);
    libsqlite_release_burnup_mkdir($source . '/mptest');
    libsqlite_release_burnup_mkdir($build);
    libsqlite_release_burnup_write($test . '/testrunner.tcl');
    libsqlite_release_burnup_write($build . '/testfixture', "#!/bin/sh\nexit 0\n");
    chmod($build . '/testfixture', 0755);
    libsqlite_release_burnup_write($build . '/Makefile', "test:\n\t@true\nmptest:\n\t@true\n");

    $permutations = '';
    for ($i = 1; $i <= 58; $i++) {
        $permutations .= 'test_suite suite' . str_pad((string) $i, 2, '0', STR_PAD_LEFT) . "\n";
    }
    libsqlite_release_burnup_write($test . '/permutations.test', $permutations);

    foreach (libsqlite_release_burnup_evidence()->runnerCoverageAudit()['selected_scripts'] as $script) {
        libsqlite_release_burnup_write($test . '/' . $script);
    }
    foreach (libsqlite_release_burnup_evidence()->runnerCoverageAudit()['pattern_scripts'] as $pattern) {
        libsqlite_release_burnup_write($test . '/' . str_replace('*', '01', $pattern));
    }

    return $root;
}

function libsqlite_release_burnup_artifact(string $directory, string $head, string $label, int $tests = 21, int $errors = 0, int $exit = 0): void
{
    libsqlite_release_burnup_mkdir($directory);
    $log = $directory . '/' . $label . '.log';
    $status = $errors === 0 ? '0 errors' : (string) $errors . ' errors';
    libsqlite_release_burnup_write($directory . '/' . $label . '.md', <<<MD
# SQLite Tcl Bounded Runner Evidence - {$label}

- Repository HEAD: `{$head}`
- Scratch: `/tmp/{$label}`
- Log: `{$log}`
- SQLite git commit: `8f70ec615f4cd247d36f92a22c99f65ebbcc22a7`
- SQLite VERSION: `3.54.0`
- SQLite manifest UUID: `9ac4a33a2932d353c4871fd8e09c10addf827f1fc3fc9380037d738cf2cd0353`
- Testset: `release`
- Jobs: `2`
- Timeout seconds: `7200`
- Patterns: none
- Exit: `{$exit}`
- Elapsed seconds: `4`
- Parsed summary: `{$status} out of {$tests} tests`
- Parsed errors: `{$errors}`
- Parsed tests: `{$tests}`
- Runner time: `00:00:04`
MD);
    libsqlite_release_burnup_write($log, "{$errors} errors out of {$tests} tests in 00:00:04\n");
}

$headA = '28488284c6b42b08db024e7e34c788f71b24a201';
$headB = 'f1811cc4298a2c293ae718fb1f1dae1e656bc706';
$headC = '51e928e238d8d5d3e25cf7508399227fadad761f';

$tests = [
    'accepted-head burnup reports ready burnup for the next missing accepted source' => static function (TestRunner $t) use ($headA, $headB, $headC): void {
        $root = libsqlite_release_burnup_fixture('ready-next');
        $artifacts = $root . '/artifacts';
        libsqlite_release_burnup_artifact($artifacts, $headA, 'head-a');
        libsqlite_release_burnup_artifact($artifacts, $headB, 'head-b');

        try {
            $record = libsqlite_release_burnup_evidence()->releaseRunnerAcceptedHeadBurnup([$headA, $headB, $headC], $root, $artifacts, '', 3);

            $t->same('ready-for-next-accepted-head-runner', $record['status']);
            $t->same(3, $record['head_count']);
            $t->same(2, $record['countable_head_count']);
            $t->same(1, $record['missing_head_count']);
            $t->same(2, $record['prefix_countable_head_count']);
            $t->same(66.67, $record['burnup_percent']);
            $t->same([$headA, $headB], $record['countable_heads']);
            $t->same([$headC], $record['missing_heads']);
            $t->same($headC, $record['next_missing_head']);
            $t->same(true, $record['ready_to_launch_next_guarded_runner']);
            $t->same('hydrated', $record['hydration_status']);
            $t->same('ready', $record['command_manifest_status']);
            $t->same('clear', $record['active_runner_status']);
            $t->same(1, $record['blocker_count']);
            $t->same('next-accepted-artifact-missing', $record['blockers'][0]['id']);
            $t->same(false, $record['counts_as_release_parity']);
            $t->contains('launch at most one guarded runner', $record['next_gate']);
            $t->contains('no new support component needed', $record['dependency_closure']);
        } finally {
            libsqlite_release_burnup_cleanup($root);
        }
    },
    'accepted-head burnup completes burnup only when every accepted head has countable artifact provenance' => static function (TestRunner $t) use ($headA, $headB, $headC): void {
        $root = libsqlite_release_burnup_fixture('complete');
        $artifacts = $root . '/artifacts';
        libsqlite_release_burnup_artifact($artifacts, $headA, 'head-a');
        libsqlite_release_burnup_artifact($artifacts, $headB, 'head-b');
        libsqlite_release_burnup_artifact($artifacts, $headC, 'head-c');

        try {
            $record = libsqlite_release_burnup_evidence()->releaseRunnerAcceptedHeadBurnup([$headA, $headB, $headC], $root, $artifacts);

            $t->same('all-accepted-heads-countable', $record['status']);
            $t->same(3, $record['countable_head_count']);
            $t->same(0, $record['missing_head_count']);
            $t->same(100.0, $record['burnup_percent']);
            $t->same(null, $record['next_missing_head']);
            $t->same(false, $record['ready_to_launch_next_guarded_runner']);
            $t->same(true, $record['counts_as_release_parity']);
            $t->same(0, $record['blocker_count']);
            $t->contains('record the burnup as complete', $record['next_gate']);
        } finally {
            libsqlite_release_burnup_cleanup($root);
        }
    },
    'accepted-head burnup preserves counted heads but blocks when hydration is incomplete' => static function (TestRunner $t) use ($headA, $headB): void {
        $root = libsqlite_release_burnup_fixture('blocked-hydration', false);
        $artifacts = $root . '/artifacts';
        libsqlite_release_burnup_artifact($artifacts, $headA, 'head-a');

        try {
            $record = libsqlite_release_burnup_evidence()->releaseRunnerAcceptedHeadBurnup([$headA, $headB], $root, $artifacts);
            $blockerIds = array_column($record['blockers'], 'id');

            $t->same('blocked', $record['status']);
            $t->same(1, $record['countable_head_count']);
            $t->same(1, $record['missing_head_count']);
            $t->same('blocked-missing-hydration', $record['hydration_status']);
            $t->true(in_array('runner-hydration-incomplete', $blockerIds, true), 'Expected hydration blocker');
            $t->true(in_array('command-manifest-blocked', $blockerIds, true), 'Expected command blocker');
            $t->same(false, $record['ready_to_launch_next_guarded_runner']);
        } finally {
            libsqlite_release_burnup_cleanup($root);
        }
    },
    'accepted-head burnup blocks duplicate broad runner while preserving burnup progress' => static function (TestRunner $t) use ($headA, $headB): void {
        $root = libsqlite_release_burnup_fixture('active');
        $artifacts = $root . '/artifacts';
        libsqlite_release_burnup_artifact($artifacts, $headA, 'head-a');
        $snapshot = "1666104 1666103 S+ 28:39 0.2 ./testfixture ../src/test/testrunner.tcl --jobs 2 --stop-on-error all\n";

        try {
            $record = libsqlite_release_burnup_evidence()->releaseRunnerAcceptedHeadBurnup([$headA, $headB], $root, $artifacts, $snapshot);
            $blockerIds = array_column($record['blockers'], 'id');

            $t->same('blocked', $record['status']);
            $t->same(1, $record['countable_head_count']);
            $t->same('blocked-active-runner', $record['active_runner_status']);
            $t->same(1, $record['active_runner_count']);
            $t->true(in_array('duplicate-broad-runner-active', $blockerIds, true), 'Expected active-runner blocker');
            $t->same(false, $record['ready_to_launch_next_guarded_runner']);
        } finally {
            libsqlite_release_burnup_cleanup($root);
        }
    },
    'accepted-head burnup ignores failed artifacts for burnup countability' => static function (TestRunner $t) use ($headA, $headB): void {
        $root = libsqlite_release_burnup_fixture('failed-artifact');
        $artifacts = $root . '/artifacts';
        libsqlite_release_burnup_artifact($artifacts, $headA, 'head-a');
        libsqlite_release_burnup_artifact($artifacts, $headB, 'head-b-failed', 99, 1, 1);

        try {
            $record = libsqlite_release_burnup_evidence()->releaseRunnerAcceptedHeadBurnup([$headA, $headB], $root, $artifacts);

            $t->same('ready-for-next-accepted-head-runner', $record['status']);
            $t->same(1, $record['countable_head_count']);
            $t->same(1, $record['missing_head_count']);
            $t->same($headB, $record['next_missing_head']);
            $t->same(2, $record['records'][1]['artifact_count']);
            $t->same(2, $record['records'][1]['blocked_count']);
            $t->same('missing-countable-artifact', $record['records'][1]['status']);
        } finally {
            libsqlite_release_burnup_cleanup($root);
        }
    },
    'accepted-head burnup rejects empty head list' => static function (TestRunner $t): void {
        $t->throws(
            InvalidArgumentException::class,
            static fn () => libsqlite_release_burnup_evidence()->releaseRunnerAcceptedHeadBurnup([])
        );
    },
    'accepted-head burnup rejects blank head values' => static function (TestRunner $t) use ($headA): void {
        $t->throws(
            InvalidArgumentException::class,
            static fn () => libsqlite_release_burnup_evidence()->releaseRunnerAcceptedHeadBurnup([$headA, ''])
        );
    },
    'accepted-head burnup rejects zero jobs' => static function (TestRunner $t) use ($headA): void {
        $t->throws(
            InvalidArgumentException::class,
            static fn () => libsqlite_release_burnup_evidence()->releaseRunnerAcceptedHeadBurnup([$headA], null, null, '', 0)
        );
    },
];

for ($i = 1; $i <= 52; $i++) {
    $tests['accepted-head burnup sample ' . $i] = static function (TestRunner $t) use ($headA, $headB, $headC, $i): void {
        $root = libsqlite_release_burnup_fixture('sample-' . $i);
        $artifacts = $root . '/artifacts';
        libsqlite_release_burnup_artifact($artifacts, $headA, 'sample-' . $i . '-a', 20 + $i);
        if ($i % 2 === 0) {
            libsqlite_release_burnup_artifact($artifacts, $headB, 'sample-' . $i . '-b', 40 + $i);
        }

        try {
            $record = libsqlite_release_burnup_evidence()->releaseRunnerAcceptedHeadBurnup([$headA, $headB, $headC], $root, $artifacts, '', 2);

            $expectedCount = $i % 2 === 0 ? 2 : 1;
            $t->same('ready-for-next-accepted-head-runner', $record['status']);
            $t->same($expectedCount, $record['countable_head_count']);
            $t->same(3 - $expectedCount, $record['missing_head_count']);
            $t->same($expectedCount === 2 ? $headC : $headB, $record['next_missing_head']);
            $t->same(true, $record['ready_to_launch_next_guarded_runner']);
            $t->same($expectedCount, $record['prefix_countable_head_count']);
        } finally {
            libsqlite_release_burnup_cleanup($root);
        }
    };
}

return $tests;

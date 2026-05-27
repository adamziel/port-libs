<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteUpstreamSuiteEvidence;

function libsqlite_release_hydration24_evidence(): SQLiteUpstreamSuiteEvidence
{
    return SQLiteUpstreamSuiteEvidence::fromManifestPath(__DIR__ . '/../UPSTREAM_TEST_MANIFEST.json');
}

function libsqlite_release_hydration24_root(string $label): string
{
    return sys_get_temp_dir() . '/libsqlite-release-hydration24-' . $label . '-' . bin2hex(random_bytes(4));
}

function libsqlite_release_hydration24_mkdir(string $path): void
{
    if (!is_dir($path)) {
        mkdir($path, 0777, true);
    }
}

function libsqlite_release_hydration24_write(string $path, string $contents = '# fixture'): void
{
    libsqlite_release_hydration24_mkdir(dirname($path));
    file_put_contents($path, $contents);
}

function libsqlite_release_hydration24_cleanup(string $root): void
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

function libsqlite_release_hydration24_fixture(string $label, array $parts): string
{
    $root = libsqlite_release_hydration24_root($label);
    $source = $root . '/.upstream-cache/libsqlite';
    $test = $source . '/test';
    $build = $root . '/.upstream-cache/libsqlite-build-port-libsqlite';

    if (in_array('source', $parts, true)) {
        libsqlite_release_hydration24_mkdir($source);
    }
    if (in_array('testdir', $parts, true)) {
        libsqlite_release_hydration24_mkdir($test);
    }
    if (in_array('testrunner', $parts, true)) {
        libsqlite_release_hydration24_write($test . '/testrunner.tcl');
    }
    if (in_array('permutations', $parts, true)) {
        $permutationText = '';
        for ($i = 1; $i <= 58; $i++) {
            $permutationText .= 'test_suite suite' . str_pad((string) $i, 2, '0', STR_PAD_LEFT) . "\n";
        }
        libsqlite_release_hydration24_write($test . '/permutations.test', $permutationText);
    }
    if (in_array('selected-scripts', $parts, true)) {
        foreach (libsqlite_release_hydration24_evidence()->runnerCoverageAudit()['selected_scripts'] as $script) {
            libsqlite_release_hydration24_write($test . '/' . $script);
        }
        foreach (['btree1.test', 'pager1.test', 'quick1.test', 'rowid1.test', 'schema1.test', 'table1.test'] as $script) {
            libsqlite_release_hydration24_write($test . '/' . $script);
        }
    }
    if (in_array('mptest', $parts, true)) {
        libsqlite_release_hydration24_mkdir($source . '/mptest');
    }
    if (in_array('build', $parts, true)) {
        libsqlite_release_hydration24_mkdir($build);
    }
    if (in_array('testfixture', $parts, true)) {
        libsqlite_release_hydration24_write($build . '/testfixture', "#!/bin/sh\nexit 0\n");
        chmod($build . '/testfixture', in_array('executable', $parts, true) ? 0755 : 0644);
    }
    if (in_array('makefile', $parts, true)) {
        libsqlite_release_hydration24_write($build . '/Makefile', "test:\n\t@true\nmptest:\n\t@true\n");
    }

    return $root;
}

function libsqlite_release_hydration24_artifact(string $directory, string $head, string $label = 'libsqlite-release-current-next24'): void
{
    libsqlite_release_hydration24_mkdir($directory);
    $audit = $directory . '/' . $label . '.md';
    $log = $directory . '/' . $label . '.log';
    libsqlite_release_hydration24_write($audit, <<<MD
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
- Exit: `0`
- Elapsed seconds: `3`
- Parsed summary: `0 errors out of 12 tests`
- Parsed errors: `0`
- Parsed tests: `12`
- Runner time: `00:00:03`
MD);
    libsqlite_release_hydration24_write($log, "0 errors out of 12 tests in 00:00:03\n");
}

$tests = [
    'blocks current-next24 cluster on empty hydration root' => static function (TestRunner $t): void {
        $root = libsqlite_release_hydration24_root('empty');
        try {
            $record = libsqlite_release_hydration24_evidence()->releaseRunnerHydrationClusterRecord('2526c99030a288ad50fc53257065420d1dcd6b85', $root, $root . '/artifacts');
            $t->same('blocked', $record['status']);
            $t->same('blocked-missing-hydration', $record['hydration_status']);
            $t->true(in_array('runner-hydration-incomplete', $record['blocked_reasons'], true), 'Expected hydration blocker');
            $t->same(false, $record['ready_to_launch_guarded_runner']);
        } finally {
            libsqlite_release_hydration24_cleanup($root);
        }
    },
    'reports duplicate broad runner as a launch blocker' => static function (TestRunner $t): void {
        $root = libsqlite_release_hydration24_fixture('active-runner', ['source', 'testdir', 'testrunner', 'permutations', 'selected-scripts', 'mptest', 'build', 'testfixture', 'executable', 'makefile']);
        try {
            $snapshot = '1666104 1666103 S+ 28:39 0.2 ./testfixture ../src/test/testrunner.tcl --jobs 2 --stop-on-error release';
            $record = libsqlite_release_hydration24_evidence()->releaseRunnerHydrationClusterRecord('2526c99030a288ad50fc53257065420d1dcd6b85', $root, $root . '/artifacts', $snapshot);
            $t->same('blocked-active-runner', $record['active_runner_status']);
            $t->same(1, $record['active_runner_count']);
            $t->true(in_array('duplicate-broad-runner-active', $record['blocked_reasons'], true), 'Expected duplicate-runner blocker');
        } finally {
            libsqlite_release_hydration24_cleanup($root);
        }
    },
    'marks fully hydrated runner ready when artifact evidence is still absent' => static function (TestRunner $t): void {
        $root = libsqlite_release_hydration24_fixture('ready-no-artifact', ['source', 'testdir', 'testrunner', 'permutations', 'selected-scripts', 'mptest', 'build', 'testfixture', 'executable', 'makefile']);
        try {
            $record = libsqlite_release_hydration24_evidence()->releaseRunnerHydrationClusterRecord('2526c99030a288ad50fc53257065420d1dcd6b85', $root, $root . '/artifacts', '', 3);
            $t->same('ready-for-guarded-runner', $record['status']);
            $t->same(true, $record['ready_to_launch_guarded_runner']);
            $t->same(3, $record['jobs']);
            $t->same(['no-current-accepted-artifact'], $record['blocked_reasons']);
            $t->contains('launch at most one guarded bounded runner', $record['next_gate']);
        } finally {
            libsqlite_release_hydration24_cleanup($root);
        }
    },
    'counts current accepted artifact and suppresses duplicate launch' => static function (TestRunner $t): void {
        $head = '2526c99030a288ad50fc53257065420d1dcd6b85';
        $root = libsqlite_release_hydration24_fixture('accepted-artifact', ['source', 'testdir', 'testrunner', 'build', 'testfixture', 'executable']);
        $artifacts = $root . '/artifacts';
        libsqlite_release_hydration24_artifact($artifacts, $head);
        try {
            $record = libsqlite_release_hydration24_evidence()->releaseRunnerHydrationClusterRecord($head, $root, $artifacts);
            $t->same('current-accepted-artifact-ready', $record['status']);
            $t->same(1, $record['artifact_count']);
            $t->same(1, $record['current_accepted_artifact_count']);
            $t->same(true, $record['counts_current_accepted_artifact']);
            $t->contains('do not launch a duplicate broad runner', $record['next_gate']);
        } finally {
            libsqlite_release_hydration24_cleanup($root);
        }
    },
    'rejects missing accepted head in current-next24 cluster record' => static function (TestRunner $t): void {
        $t->throws(
            InvalidArgumentException::class,
            static fn () => libsqlite_release_hydration24_evidence()->releaseRunnerHydrationClusterRecord('')
        );
    },
    'rejects zero jobs in current-next24 cluster record' => static function (TestRunner $t): void {
        $t->throws(
            InvalidArgumentException::class,
            static fn () => libsqlite_release_hydration24_evidence()->releaseRunnerHydrationClusterRecord('2526c99030a288ad50fc53257065420d1dcd6b85', null, null, '', 0)
        );
    },
];

$missingCases = [
    'source cache' => ['parts' => [], 'missing' => 8, 'reason' => 'runner-hydration-incomplete'],
    'test directory' => ['parts' => ['source', 'build', 'testfixture', 'executable'], 'missing' => 5, 'reason' => 'runner-hydration-incomplete'],
    'testrunner' => ['parts' => ['source', 'testdir', 'build', 'testfixture', 'executable'], 'missing' => 4, 'reason' => 'runner-hydration-incomplete'],
    'build directory' => ['parts' => ['source', 'testdir', 'testrunner'], 'missing' => 5, 'reason' => 'runner-hydration-incomplete'],
    'testfixture executable bit' => ['parts' => ['source', 'testdir', 'testrunner', 'build', 'testfixture'], 'missing' => 4, 'reason' => 'runner-hydration-incomplete'],
    'permutation source' => ['parts' => ['source', 'testdir', 'testrunner', 'build', 'testfixture', 'executable'], 'missing' => 3, 'reason' => 'runner-hydration-incomplete'],
    'makefile' => ['parts' => ['source', 'testdir', 'testrunner', 'permutations', 'build', 'testfixture', 'executable'], 'missing' => 2, 'reason' => 'runner-hydration-incomplete'],
    'mptest directory' => ['parts' => ['source', 'testdir', 'testrunner', 'permutations', 'build', 'testfixture', 'executable', 'makefile'], 'missing' => 1, 'reason' => 'runner-hydration-incomplete'],
];

foreach ($missingCases as $label => $case) {
    $tests['blocks release-runner hydration when ' . $label . ' is absent'] = static function (TestRunner $t) use ($label, $case): void {
        $root = libsqlite_release_hydration24_fixture(str_replace(' ', '-', $label), $case['parts']);
        try {
            $record = libsqlite_release_hydration24_evidence()->releaseRunnerHydrationClusterRecord('2526c99030a288ad50fc53257065420d1dcd6b85', $root, $root . '/artifacts');
            $t->same('blocked', $record['status']);
            $t->same($case['missing'], $record['hydration_missing_count']);
            $t->true(in_array($case['reason'], $record['blocked_reasons'], true), 'Expected named blocker');
        } finally {
            libsqlite_release_hydration24_cleanup($root);
        }
    };
}

$readyCommandCases = [
    'focused subset command' => ['parts' => ['source', 'testdir', 'testrunner', 'build', 'testfixture', 'executable'], 'id' => 'focused-veryquick-subset'],
    'release all command' => ['parts' => ['source', 'testdir', 'testrunner', 'build', 'testfixture', 'executable'], 'id' => 'release-all'],
    'permutation command' => ['parts' => ['source', 'testdir', 'testrunner', 'permutations', 'build', 'testfixture', 'executable'], 'id' => 'permutation-suites'],
    'make test command' => ['parts' => ['source', 'build', 'makefile'], 'id' => 'make-test'],
    'mptest command' => ['parts' => ['source', 'mptest', 'build', 'makefile'], 'id' => 'mptest'],
];

foreach ($readyCommandCases as $label => $case) {
    $tests['reports hydrated runnable ' . $label . ' in current-next24 cluster'] = static function (TestRunner $t) use ($label, $case): void {
        $root = libsqlite_release_hydration24_fixture(str_replace(' ', '-', $label), $case['parts']);
        try {
            $record = libsqlite_release_hydration24_evidence()->releaseRunnerHydrationClusterRecord('2526c99030a288ad50fc53257065420d1dcd6b85', $root, $root . '/artifacts');
            $t->true(in_array($case['id'], $record['hydration_runnable_command_ids'], true), 'Expected runnable command id');
            $t->true($record['hydration_runnable_command_count'] >= 1, 'Expected at least one runnable hydration command');
        } finally {
            libsqlite_release_hydration24_cleanup($root);
        }
    };
}

for ($i = 1; $i <= 30; $i++) {
    $tests['preserves accepted-head artifact provenance for current-next24 sample ' . $i] = static function (TestRunner $t) use ($i): void {
        $head = '2526c99030a288ad50fc53257065420d1dcd6b85';
        $root = libsqlite_release_hydration24_fixture('artifact-' . $i, ['source']);
        $artifacts = $root . '/artifacts';
        libsqlite_release_hydration24_artifact($artifacts, $head, 'libsqlite-release-current-next24-' . $i);
        try {
            $record = libsqlite_release_hydration24_evidence()->releaseRunnerHydrationClusterRecord($head, $root, $artifacts);
            $t->same('current-accepted-artifact-ready', $record['status']);
            $t->same($head, $record['accepted_repository_head']);
            $t->same(1, $record['current_accepted_artifact_count']);
            $t->same(0, $record['artifact_blocked_count']);
        } finally {
            libsqlite_release_hydration24_cleanup($root);
        }
    };
}

return $tests;

<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteUpstreamSuiteEvidence;

function libsqlite_release_map_evidence(): SQLiteUpstreamSuiteEvidence
{
    return SQLiteUpstreamSuiteEvidence::fromManifestPath(__DIR__ . '/../UPSTREAM_TEST_MANIFEST.json');
}

function libsqlite_release_map_root(string $label): string
{
    return sys_get_temp_dir() . '/libsqlite-release-map-' . $label . '-' . bin2hex(random_bytes(4));
}

function libsqlite_release_map_mkdir(string $path): void
{
    if (!is_dir($path)) {
        mkdir($path, 0777, true);
    }
}

function libsqlite_release_map_write(string $path, string $contents = '# fixture'): void
{
    libsqlite_release_map_mkdir(dirname($path));
    file_put_contents($path, $contents);
}

function libsqlite_release_map_cleanup(string $root): void
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

function libsqlite_release_map_fixture(string $label, array $parts): string
{
    $root = libsqlite_release_map_root($label);
    $source = $root . '/.upstream-cache/libsqlite';
    $test = $source . '/test';
    $build = $root . '/.upstream-cache/libsqlite-build-port-libsqlite';

    if (in_array('source', $parts, true)) {
        libsqlite_release_map_mkdir($source);
    }
    if (in_array('testdir', $parts, true)) {
        libsqlite_release_map_mkdir($test);
    }
    if (in_array('testrunner', $parts, true)) {
        libsqlite_release_map_write($test . '/testrunner.tcl');
    }
    if (in_array('permutations', $parts, true)) {
        $text = '';
        for ($i = 1; $i <= 58; $i++) {
            $text .= 'test_suite suite' . str_pad((string) $i, 2, '0', STR_PAD_LEFT) . "\n";
        }
        libsqlite_release_map_write($test . '/permutations.test', $text);
    }
    if (in_array('selected-scripts', $parts, true)) {
        foreach (libsqlite_release_map_evidence()->runnerCoverageAudit()['selected_scripts'] as $script) {
            libsqlite_release_map_write($test . '/' . $script);
        }
        foreach (libsqlite_release_map_evidence()->runnerCoverageAudit()['pattern_scripts'] as $pattern) {
            libsqlite_release_map_write($test . '/' . str_replace('*', '01', $pattern));
        }
    }
    if (in_array('mptest', $parts, true)) {
        libsqlite_release_map_mkdir($source . '/mptest');
    }
    if (in_array('build', $parts, true)) {
        libsqlite_release_map_mkdir($build);
    }
    if (in_array('testfixture', $parts, true)) {
        libsqlite_release_map_write($build . '/testfixture', "#!/bin/sh\nexit 0\n");
        chmod($build . '/testfixture', in_array('executable', $parts, true) ? 0755 : 0644);
    }
    if (in_array('makefile', $parts, true)) {
        libsqlite_release_map_write($build . '/Makefile', "test:\n\t@true\nmptest:\n\t@true\n");
    }

    return $root;
}

function libsqlite_release_map_artifact(string $directory, string $head, string $label): void
{
    libsqlite_release_map_mkdir($directory);
    $log = $directory . '/' . $label . '.log';
    libsqlite_release_map_write($directory . '/' . $label . '.md', <<<MD
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
- Elapsed seconds: `4`
- Parsed summary: `0 errors out of 14 tests`
- Parsed errors: `0`
- Parsed tests: `14`
- Runner time: `00:00:04`
MD);
    libsqlite_release_map_write($log, "0 errors out of 14 tests in 00:00:04\n");
}

function libsqlite_release_map_hydrated_parts(): array
{
    return ['source', 'testdir', 'testrunner', 'permutations', 'selected-scripts', 'mptest', 'build', 'testfixture', 'executable', 'makefile'];
}

$currentHead = '28488284c6b42b08db024e7e34c788f71b24a201';
$nextHead = '51e928e238d8d5d3e25cf7508399227fadad761f';

$tests = [
    'accepted-head map maps current artifact to next guarded launch' => static function (TestRunner $t) use ($currentHead, $nextHead): void {
        $root = libsqlite_release_map_fixture('ready', libsqlite_release_map_hydrated_parts());
        $artifacts = $root . '/artifacts';
        libsqlite_release_map_artifact($artifacts, $currentHead, 'current');

        try {
            $record = libsqlite_release_map_evidence()->releaseRunnerAcceptedHeadMap($currentHead, $nextHead, $root, $artifacts, '', 3);

            $t->same('ready-map-current-to-next-runner', $record['status']);
            $t->same($currentHead, $record['current_accepted_head']);
            $t->same($nextHead, $record['next_accepted_head']);
            $t->same(1, $record['current_artifact_count']);
            $t->same(0, $record['next_artifact_count']);
            $t->same('all-current-accepted-head', $record['current_artifact_status']);
            $t->same('blocked', $record['next_artifact_status']);
            $t->same('hydrated', $record['hydration_status']);
            $t->same('ready', $record['command_manifest_status']);
            $t->same(7, $record['command_count']);
            $t->same(7, $record['runnable_command_count']);
            $t->same(0, $record['blocked_command_count']);
            $t->same(['release-all', 'permutation-suites', 'make-test', 'mptest', 'wildcard-expansion', 'permutation-suite-map', 'permutation-suite-commands'], $record['runnable_command_ids']);
            $t->same('clear', $record['active_runner_status']);
            $t->same(0, $record['active_runner_count']);
            $t->same(0, $record['blocker_count']);
            $t->same(true, $record['counts_current_artifact_only']);
            $t->same(true, $record['ready_to_launch_next_guarded_runner']);
            $t->contains('launch at most one guarded runner', $record['next_gate']);
            $t->contains('no new support component needed', $record['dependency_closure']);
        } finally {
            libsqlite_release_map_cleanup($root);
        }
    },
    'accepted-head map suppresses duplicate launch when next artifact already exists' => static function (TestRunner $t) use ($currentHead, $nextHead): void {
        $root = libsqlite_release_map_fixture('next-ready', libsqlite_release_map_hydrated_parts());
        $artifacts = $root . '/artifacts';
        libsqlite_release_map_artifact($artifacts, $currentHead, 'current');
        libsqlite_release_map_artifact($artifacts, $nextHead, 'next');

        try {
            $record = libsqlite_release_map_evidence()->releaseRunnerAcceptedHeadMap($currentHead, $nextHead, $root, $artifacts);

            $t->same('next-artifact-already-countable', $record['status']);
            $t->same(1, $record['current_artifact_count']);
            $t->same(1, $record['next_artifact_count']);
            $t->same(false, $record['counts_current_artifact_only']);
            $t->same(false, $record['ready_to_launch_next_guarded_runner']);
            $t->same('next-source-artifact-already-present', $record['blockers'][0]['id']);
            $t->contains('suppress duplicate broad runner launch', $record['next_gate']);
        } finally {
            libsqlite_release_map_cleanup($root);
        }
    },
    'accepted-head map preserves current artifact while hydration is blocked' => static function (TestRunner $t) use ($currentHead, $nextHead): void {
        $root = libsqlite_release_map_fixture('missing-hydration', ['source']);
        $artifacts = $root . '/artifacts';
        libsqlite_release_map_artifact($artifacts, $currentHead, 'current');

        try {
            $record = libsqlite_release_map_evidence()->releaseRunnerAcceptedHeadMap($currentHead, $nextHead, $root, $artifacts);
            $blockerIds = array_column($record['blockers'], 'id');

            $t->same('current-artifact-preserved-next-blocked', $record['status']);
            $t->same(1, $record['current_artifact_count']);
            $t->same(0, $record['next_artifact_count']);
            $t->same('blocked-missing-hydration', $record['hydration_status']);
            $t->true(in_array('runner-hydration-incomplete', $blockerIds, true), 'Expected hydration blocker');
            $t->true(in_array('command-manifest-blocked', $blockerIds, true), 'Expected command blocker');
            $t->same(false, $record['ready_to_launch_next_guarded_runner']);
            $t->contains('preserve current accepted artifact evidence', $record['next_gate']);
        } finally {
            libsqlite_release_map_cleanup($root);
        }
    },
    'accepted-head map blocks duplicate active runner before launch' => static function (TestRunner $t) use ($currentHead, $nextHead): void {
        $root = libsqlite_release_map_fixture('active-runner', libsqlite_release_map_hydrated_parts());
        $artifacts = $root . '/artifacts';
        libsqlite_release_map_artifact($artifacts, $currentHead, 'current');
        $snapshot = "1666104 1666103 S+ 28:39 0.2 ./testfixture ../src/test/testrunner.tcl --jobs 2 --stop-on-error all\n";

        try {
            $record = libsqlite_release_map_evidence()->releaseRunnerAcceptedHeadMap($currentHead, $nextHead, $root, $artifacts, $snapshot);
            $blockerIds = array_column($record['blockers'], 'id');

            $t->same('current-artifact-preserved-next-blocked', $record['status']);
            $t->same('blocked-active-runner', $record['active_runner_status']);
            $t->same(1, $record['active_runner_count']);
            $t->true(in_array('duplicate-broad-runner-active', $blockerIds, true), 'Expected duplicate active runner blocker');
            $t->same(false, $record['ready_to_launch_next_guarded_runner']);
            $t->same(true, $record['counts_current_artifact_only']);
        } finally {
            libsqlite_release_map_cleanup($root);
        }
    },
    'accepted-head map blocks when current accepted artifact is missing' => static function (TestRunner $t) use ($currentHead, $nextHead): void {
        $root = libsqlite_release_map_fixture('no-current', libsqlite_release_map_hydrated_parts());
        $artifacts = $root . '/artifacts';

        try {
            $record = libsqlite_release_map_evidence()->releaseRunnerAcceptedHeadMap($currentHead, $nextHead, $root, $artifacts);
            $blockerIds = array_column($record['blockers'], 'id');

            $t->same('blocked', $record['status']);
            $t->same(0, $record['current_artifact_count']);
            $t->same(0, $record['next_artifact_count']);
            $t->true(in_array('current-accepted-artifact-missing', $blockerIds, true), 'Expected current artifact blocker');
            $t->same(false, $record['counts_current_artifact_only']);
            $t->contains('do not count release/all movement', $record['next_gate']);
        } finally {
            libsqlite_release_map_cleanup($root);
        }
    },
    'accepted-head map rejects missing current head' => static function (TestRunner $t) use ($nextHead): void {
        $t->throws(
            InvalidArgumentException::class,
            static fn () => libsqlite_release_map_evidence()->releaseRunnerAcceptedHeadMap('', $nextHead)
        );
    },
    'accepted-head map rejects missing next head' => static function (TestRunner $t) use ($currentHead): void {
        $t->throws(
            InvalidArgumentException::class,
            static fn () => libsqlite_release_map_evidence()->releaseRunnerAcceptedHeadMap($currentHead, '')
        );
    },
    'accepted-head map rejects zero jobs' => static function (TestRunner $t) use ($currentHead, $nextHead): void {
        $t->throws(
            InvalidArgumentException::class,
            static fn () => libsqlite_release_map_evidence()->releaseRunnerAcceptedHeadMap($currentHead, $nextHead, null, null, '', 0)
        );
    },
];

foreach ([
    'source cache' => ['parts' => [], 'blocked' => ['release-all', 'permutation-suites', 'make-test', 'mptest', 'wildcard-expansion', 'permutation-suite-map', 'permutation-suite-commands']],
    'test directory' => ['parts' => ['source', 'build', 'testfixture', 'executable', 'makefile'], 'blocked' => ['release-all', 'permutation-suites', 'wildcard-expansion', 'permutation-suite-map', 'permutation-suite-commands']],
    'testfixture executable' => ['parts' => ['source', 'testdir', 'testrunner', 'permutations', 'selected-scripts', 'mptest', 'build', 'testfixture', 'makefile'], 'blocked' => [], 'hydrationBlocked' => true],
] as $label => $case) {
    $tests['accepted-head map maps blocked command ids when ' . $label . ' is missing'] = static function (TestRunner $t) use ($currentHead, $nextHead, $label, $case): void {
        $root = libsqlite_release_map_fixture(str_replace(' ', '-', $label), $case['parts']);
        $artifacts = $root . '/artifacts';
        libsqlite_release_map_artifact($artifacts, $currentHead, 'current');

        try {
            $record = libsqlite_release_map_evidence()->releaseRunnerAcceptedHeadMap($currentHead, $nextHead, $root, $artifacts);

            foreach ($case['blocked'] as $id) {
                $t->true(in_array($id, $record['blocked_command_ids'], true), 'Expected blocked command ' . $id);
            }
            if ($case['blocked'] === [] && ($case['hydrationBlocked'] ?? false) === true) {
                $t->same('current-artifact-preserved-next-blocked', $record['status']);
                $t->same('partially-hydrated', $record['hydration_status']);
                $t->same(0, $record['blocked_command_count']);
            } elseif ($case['blocked'] === []) {
                $t->same('ready-map-current-to-next-runner', $record['status']);
                $t->same(0, $record['blocked_command_count']);
            } else {
                $t->same('current-artifact-preserved-next-blocked', $record['status']);
                $t->true($record['blocked_command_count'] >= count($case['blocked']), 'Expected blocked command count');
            }
        } finally {
            libsqlite_release_map_cleanup($root);
        }
    };
}

for ($i = 1; $i <= 42; $i++) {
    $tests['accepted-head map current artifact sample ' . $i] = static function (TestRunner $t) use ($currentHead, $nextHead, $i): void {
        $root = libsqlite_release_map_fixture('sample-' . $i, libsqlite_release_map_hydrated_parts());
        $artifacts = $root . '/artifacts';
        libsqlite_release_map_artifact($artifacts, $currentHead, 'current-' . $i);

        try {
            $record = libsqlite_release_map_evidence()->releaseRunnerAcceptedHeadMap($currentHead, $nextHead, $root, $artifacts, '', 2);

            $t->same('ready-map-current-to-next-runner', $record['status']);
            $t->same(1, $record['current_artifact_count']);
            $t->same(0, $record['next_artifact_count']);
            $t->same(true, $record['ready_to_launch_next_guarded_runner']);
            $t->same(2, $record['jobs']);
        } finally {
            libsqlite_release_map_cleanup($root);
        }
    };
}

return $tests;
